import { File } from 'expo-file-system';
import { randomUUID } from 'expo-crypto';
import { Platform } from 'react-native';
import { supabase } from './supabase';
import { BarangayOption, Report, ReportPayload, User, VehicularAccident, VehicularAccidentPayload } from '../types';

const BUCKET = 'mobile-evidence';
const PREFIX = `supabase://${BUCKET}/`;

async function rpc<T>(action: string, payload: unknown = {}, recordId?: number): Promise<T> {
  const { data, error } = await supabase.rpc('mobile_api', { action, payload, record_id: recordId ?? null });
  if (error) throw new Error(error.message);
  return data as T;
}

// Keep the storage reference on signed URLs; edits must not save an expiring URL.
function storedPhoto(uri: string): string {
  const marker = '#mobile-object=';
  const index = uri.lastIndexOf(marker);
  return index < 0 ? uri : decodeURIComponent(uri.slice(index + marker.length));
}
async function displayPhoto(reference: string): Promise<string> {
  if (!reference.startsWith(PREFIX)) return reference;
  const { data, error } = await supabase.storage.from(BUCKET).createSignedUrl(reference.slice(PREFIX.length), 86400);
  if (error) throw new Error(`Unable to load photo: ${error.message}`);
  return `${data.signedUrl}#mobile-object=${encodeURIComponent(reference)}`;
}
async function displayRecord<T extends Report | VehicularAccident>(record: T): Promise<T> {
  const result = { ...record, photos: await Promise.all(record.photos.map(displayPhoto)) };
  if ('families' in result) result.families = await Promise.all(result.families.map(async family => ({
    ...family, photos: await Promise.all(family.photos.map(displayPhoto)),
  })));
  return result;
}

async function saveWithPhotos<T extends Report | VehicularAccident>(
  action: string, payload: ReportPayload | VehicularAccidentPayload, recordId?: number,
): Promise<T> {
  const { data: { user }, error } = await supabase.auth.getUser();
  if (error || !user) throw new Error('Please log in again.');
  const uploaded: string[] = [];
  const cache = new Map<string, Promise<string>>();
  const upload = (uri: string): Promise<string> => {
    if (cache.has(uri)) return cache.get(uri)!;
    const promise = (async () => {
      const reference = storedPhoto(uri);
      if (reference.startsWith(PREFIX) || /^https?:\/\//.test(reference)) return reference;
      const bytes = Platform.OS === 'web' ? await (await fetch(uri)).arrayBuffer() : await new File(uri).arrayBuffer();
      if (bytes.byteLength > 10 * 1024 * 1024) throw new Error('Each photo must be 10 MB or smaller.');
      const extension = uri.split('?')[0].split('.').pop()?.toLowerCase();
      const ext = extension && ['jpg','jpeg','png','webp','heic','heif'].includes(extension) ? extension : 'jpg';
      const path = `${user.id}/${randomUUID()}.${ext}`;
      const { error: uploadError } = await supabase.storage.from(BUCKET).upload(path, bytes, { contentType: `image/${ext === 'jpg' ? 'jpeg' : ext}` });
      if (uploadError) throw new Error(`Photo upload failed: ${uploadError.message}`);
      uploaded.push(path);
      return PREFIX + path;
    })();
    cache.set(uri, promise);
    return promise;
  };
  let saved: T;
  try {
    const photos: string[] = [];
    for (const photo of payload.photos) photos.push(await upload(photo));
    const prepared = { ...payload, photos };
    if ('families' in prepared) {
      const families = [];
      for (const family of prepared.families) {
        const familyPhotos = [];
        for (const photo of family.photos) familyPhotos.push(await upload(photo));
        families.push({ ...family, photos: familyPhotos });
      }
      prepared.families = families;
    }
    saved = await rpc<T>(action, prepared, recordId);
  } catch (error) {
    // A lost response can follow a committed save; preserve evidence in that case.
    const message = error instanceof Error ? error.message : '';
    if (uploaded.length && !/fetch|network|timeout|abort/i.test(message)) {
      await supabase.storage.from(BUCKET).remove(uploaded).catch(() => undefined);
    }
    throw error;
  }
  return displayRecord(saved);
}

export const api = {
  useMockApi: false,
  getProfile: () => rpc<User>('profile'),
  async login(email: string, password: string): Promise<{ token: string; user: User }> {
    const { data, error } = await supabase.auth.signInWithPassword({ email: email.trim(), password });
    if (error) throw new Error(error.message);
    try { return { token: data.session.access_token, user: await rpc<User>('profile') }; }
    catch (error) { await supabase.auth.signOut({ scope: 'local' }); throw error; }
  },
  async logout(_token?: string) {
    const { error } = await supabase.auth.signOut({ scope: 'local' });
    if (error) throw new Error(error.message);
  },
  async getReports(_token?: string): Promise<Report[]> {
    return Promise.all((await rpc<Report[]>('reports')).map(displayRecord));
  },
  getBarangays: (_token?: string) => rpc<BarangayOption[]>('barangays'),
  createReport: (payload: ReportPayload, _token?: string) => saveWithPhotos<Report>('save_report', payload),
  updateReport: (id: number, payload: ReportPayload, _token?: string) => saveWithPhotos<Report>('save_report', payload, id),
  async deleteReport(id: number, _token?: string) { await rpc('delete_report', {}, id); return true; },
  async updatePassword(currentPassword: string, newPassword: string, _token?: string) {
    const { data: { user }, error } = await supabase.auth.getUser();
    if (error || !user?.email) throw new Error('Please log in again.');
    await this.login(user.email, currentPassword);
    const { error: updateError } = await supabase.auth.updateUser({ password: newPassword });
    if (updateError) throw new Error(updateError.message);
    return { message: 'Password updated.' };
  },
  async getVehicularAccidents(_token?: string): Promise<VehicularAccident[]> {
    return Promise.all((await rpc<VehicularAccident[]>('accidents')).map(displayRecord));
  },
  createVehicularAccident: (payload: VehicularAccidentPayload, _token?: string) => saveWithPhotos<VehicularAccident>('save_accident', payload),
  updateVehicularAccident: (id: number, payload: VehicularAccidentPayload, _token?: string) => saveWithPhotos<VehicularAccident>('save_accident', payload, id),
  async deleteVehicularAccident(id: number, _token?: string) { await rpc('delete_accident', {}, id); return true; },
};
