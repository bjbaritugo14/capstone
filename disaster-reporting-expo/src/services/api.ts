import { mockApi } from '../mocks/mockApi';
import { BarangayOption, Report, ReportPayload, User, VehicularAccident, VehicularAccidentPayload } from '../types';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_BASE_URL ?? 'http://192.168.1.50:8000/api';
const USE_MOCK_API = false; // Hardcoded to use real API

console.log('[API] Using real API at:', API_BASE_URL);

async function request<T>(endpoint: string, options: RequestInit = {}) {
  const url = `${API_BASE_URL}${endpoint}`;
  console.log(`[API] ${options.method || 'GET'} ${url}`);
  if (options.body) {
    console.log('[API] Body:', options.body);
  }

  const { headers: optionHeaders, ...restOptions } = options;

  let response: Response;
  try {
    response = await fetch(url, {
      ...restOptions,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(optionHeaders || {}),
      },
    });
  } catch (networkError) {
    console.error('[API] Network error:', networkError);
    throw new Error('Network error — cannot reach the server. Check your connection and API URL.');
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    console.error(`[API] ${response.status} Error:`, JSON.stringify(data, null, 2));
    const validationErrors = data?.errors
      ? Object.values(data.errors).flat().join('\n')
      : '';
    throw new Error(validationErrors || data?.message || `Request failed (${response.status}).`);
  }

  console.log('[API] Success:', response.status);
  return data as T;
}

export const api = {
  useMockApi: USE_MOCK_API,

  async login(email: string, password: string): Promise<{ token: string; user: User }> {
    if (USE_MOCK_API) {
      return mockApi.login(email, password);
    }

    return request('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
  },

  async logout(token?: string) {
    if (USE_MOCK_API) {
      return { message: 'Logged out.' };
    }

    return request('/logout', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
  },

  async getReports(token?: string): Promise<Report[]> {
    if (USE_MOCK_API) {
      return mockApi.getReports();
    }

    const data = await request<Report[] | { data: Report[] }>('/reports', {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    const list = Array.isArray(data) ? data : data.data ?? [];
    return list.filter((item) => item && item.id != null);
  },

  async getBarangays(token?: string): Promise<BarangayOption[]> {
    if (USE_MOCK_API) {
      return [];
    }

    const data = await request<BarangayOption[] | { data: BarangayOption[] }>('/barangays', {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    const list = Array.isArray(data) ? data : data.data ?? [];
    return list.filter((item) => item && item.id != null && item.name);
  },

  async createReport(payload: ReportPayload, token?: string): Promise<Report> {
    if (USE_MOCK_API) {
      return mockApi.createReport(payload);
    }

    console.log('[API] createReport called, token present:', !!token);

    const formData = new FormData();
    formData.append('barangay', payload.barangay);
    formData.append('purok', payload.purok || '');
    formData.append('description', payload.description);
    formData.append('disasterType', payload.disasterType);
    formData.append('severity', payload.severity);
    formData.append('affectedStructures', String(payload.affectedStructures || 0));
    formData.append('reportDate', payload.reportDate);

    if (payload.latitude) formData.append('latitude', payload.latitude);
    if (payload.longitude) formData.append('longitude', payload.longitude);

    // Append families as JSON (without photos - photos sent as files)
    const familiesForJson = (payload.families || []).map(({ photos, ...rest }) => rest);
    formData.append('families', JSON.stringify(familiesForJson));

    // Append per-family photos as actual files
    (payload.families || []).forEach((family, familyIndex) => {
      (family.photos || []).forEach((uri, photoIndex) => {
        if (uri) {
          const filename = uri.split('/').pop() || `family${familyIndex}_photo${photoIndex}.jpg`;
          const match = /\.(\w+)$/.exec(filename);
          const ext = match ? match[1] : 'jpg';
          const mimeType = `image/${ext === 'jpg' ? 'jpeg' : ext}`;

          formData.append(`family_photos_${familyIndex}[]`, {
            uri,
            name: filename,
            type: mimeType,
          } as any);
        }
      });
    });

    // Also send top-level photos if any (backward compat)
    (payload.photos || []).forEach((uri, index) => {
      if (uri) {
        const filename = uri.split('/').pop() || `photo_${index}.jpg`;
        const match = /\.(\w+)$/.exec(filename);
        const ext = match ? match[1] : 'jpg';
        const mimeType = `image/${ext === 'jpg' ? 'jpeg' : ext}`;

        formData.append('photos[]', {
          uri,
          name: filename,
          type: mimeType,
        } as any);
      }
    });

    const url = `${API_BASE_URL}/reports`;
    console.log(`[API] POST ${url} (multipart/form-data)`);

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      console.error(`[API] ${response.status} Error:`, JSON.stringify(data, null, 2));
      const validationErrors = data?.errors
        ? Object.values(data.errors).flat().join('\n')
        : '';
      throw new Error(validationErrors || data?.message || `Request failed (${response.status}).`);
    }

    console.log('[API] Success:', response.status);
    return data as Report;
  },

  async updateReport(id: number, payload: ReportPayload, token?: string): Promise<Report> {
    if (USE_MOCK_API) {
      return mockApi.updateReport(id, payload);
    }

    const formData = new FormData();
    formData.append('barangay', payload.barangay);
    formData.append('purok', payload.purok || '');
    formData.append('description', payload.description);
    formData.append('disasterType', payload.disasterType);
    formData.append('severity', payload.severity);
    formData.append('affectedStructures', String(payload.affectedStructures || 0));
    formData.append('reportDate', payload.reportDate);

    if (payload.latitude) formData.append('latitude', payload.latitude);
    if (payload.longitude) formData.append('longitude', payload.longitude);

    // Append families as JSON (without photos - photos sent as files)
    const familiesForJson = (payload.families || []).map(({ photos, ...rest }) => rest);
    formData.append('families', JSON.stringify(familiesForJson));

    // Append per-family photos as actual files or existing paths
    (payload.families || []).forEach((family, familyIndex) => {
      (family.photos || []).forEach((uri, photoIndex) => {
        if (uri && (uri.startsWith('file://') || uri.startsWith('content://'))) {
          const filename = uri.split('/').pop() || `family${familyIndex}_photo${photoIndex}.jpg`;
          const match = /\.(\w+)$/.exec(filename);
          const ext = match ? match[1] : 'jpg';
          const mimeType = `image/${ext === 'jpg' ? 'jpeg' : ext}`;

          formData.append(`family_photos_${familyIndex}[]`, {
            uri,
            name: filename,
            type: mimeType,
          } as any);
        } else if (uri) {
          formData.append(`existing_family_photos_${familyIndex}[]`, uri);
        }
      });
    });

    // Laravel doesn't support PUT with multipart, use POST with _method override
    formData.append('_method', 'PUT');

    const url = `${API_BASE_URL}/reports/${id}`;
    console.log(`[API] POST ${url} (multipart/form-data, _method=PUT)`);

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      console.error(`[API] ${response.status} Error:`, JSON.stringify(data, null, 2));
      const validationErrors = data?.errors
        ? Object.values(data.errors).flat().join('\n')
        : '';
      throw new Error(validationErrors || data?.message || `Request failed (${response.status}).`);
    }

    console.log('[API] Success:', response.status);
    return data as Report;
  },

  async deleteReport(id: number, token?: string): Promise<boolean> {
    if (USE_MOCK_API) {
      return mockApi.deleteReport(id);
    }

    await request(`/reports/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    return true;
  },

  async updatePassword(currentPassword: string, newPassword: string, token?: string) {
    if (USE_MOCK_API) {
      return mockApi.updatePassword(currentPassword, newPassword);
    }

    return request('/profile/password', {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: newPassword,
      }),
    });
  },

  async getVehicularAccidents(token?: string): Promise<VehicularAccident[]> {
    if (USE_MOCK_API) {
      return mockApi.getVehicularAccidents();
    }

    const data = await request<VehicularAccident[] | { data: VehicularAccident[] }>('/vehicular-accidents', {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    const list = Array.isArray(data) ? data : data.data ?? [];
    return list.filter((item) => item && item.id != null);
  },

  async createVehicularAccident(payload: VehicularAccidentPayload, token?: string): Promise<VehicularAccident> {
    if (USE_MOCK_API) {
      return mockApi.createVehicularAccident(payload);
    }

    const formData = new FormData();
    formData.append('barangay', payload.barangay);
    formData.append('purok', payload.purok || '');
    formData.append('roadSegment', payload.roadSegment || '');
    formData.append('personName', payload.personName || '');
    formData.append('accidentType', payload.accidentType);
    formData.append('vehicleType', payload.vehicleType || '');
    formData.append('description', payload.description);
    formData.append('vehiclesInvolved', String(payload.vehiclesInvolved || 1));
    formData.append('injuredCount', String(payload.injuredCount || 0));
    formData.append('fatalityCount', String(payload.fatalityCount || 0));
    formData.append('incidentDate', payload.incidentDate);
    formData.append('involvedPersons', JSON.stringify(payload.involvedPersons || []));

    if (payload.latitude) formData.append('latitude', payload.latitude);
    if (payload.longitude) formData.append('longitude', payload.longitude);

    // Append photos as actual files
    (payload.photos || []).forEach((uri, index) => {
      if (uri) {
        const filename = uri.split('/').pop() || `accident_photo_${index}.jpg`;
        const match = /\.(\w+)$/.exec(filename);
        const ext = match ? match[1] : 'jpg';
        const mimeType = `image/${ext === 'jpg' ? 'jpeg' : ext}`;

        formData.append('photos[]', {
          uri,
          name: filename,
          type: mimeType,
        } as any);
      }
    });

    const url = `${API_BASE_URL}/vehicular-accidents`;
    console.log(`[API] POST ${url} (multipart/form-data)`);

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      console.error(`[API] ${response.status} Error:`, JSON.stringify(data, null, 2));
      const validationErrors = data?.errors
        ? Object.values(data.errors).flat().join('\n')
        : '';
      throw new Error(validationErrors || data?.message || `Request failed (${response.status}).`);
    }

    console.log('[API] Success:', response.status);
    return data as VehicularAccident;
  },

  async updateVehicularAccident(id: number, payload: VehicularAccidentPayload, token?: string): Promise<VehicularAccident> {
    if (USE_MOCK_API) {
      return mockApi.updateVehicularAccident(id, payload);
    }

    const formData = new FormData();
    formData.append('barangay', payload.barangay);
    formData.append('purok', payload.purok || '');
    formData.append('roadSegment', payload.roadSegment || '');
    formData.append('personName', payload.personName || '');
    formData.append('accidentType', payload.accidentType);
    formData.append('vehicleType', payload.vehicleType || '');
    formData.append('description', payload.description);
    formData.append('vehiclesInvolved', String(payload.vehiclesInvolved || 1));
    formData.append('injuredCount', String(payload.injuredCount || 0));
    formData.append('fatalityCount', String(payload.fatalityCount || 0));
    formData.append('incidentDate', payload.incidentDate);
    formData.append('involvedPersons', JSON.stringify(payload.involvedPersons || []));

    if (payload.latitude) formData.append('latitude', payload.latitude);
    if (payload.longitude) formData.append('longitude', payload.longitude);

    // Append photos
    (payload.photos || []).forEach((uri, index) => {
      if (uri && (uri.startsWith('file://') || uri.startsWith('content://'))) {
        const filename = uri.split('/').pop() || `accident_photo_${index}.jpg`;
        const match = /\.(\w+)$/.exec(filename);
        const ext = match ? match[1] : 'jpg';
        const mimeType = `image/${ext === 'jpg' ? 'jpeg' : ext}`;

        formData.append('photos[]', {
          uri,
          name: filename,
          type: mimeType,
        } as any);
      } else if (uri) {
        formData.append('existing_photos[]', uri);
      }
    });

    formData.append('_method', 'PUT');

    const url = `${API_BASE_URL}/vehicular-accidents/${id}`;
    console.log(`[API] POST ${url} (multipart/form-data, _method=PUT)`);

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      console.error(`[API] ${response.status} Error:`, JSON.stringify(data, null, 2));
      const validationErrors = data?.errors
        ? Object.values(data.errors).flat().join('\n')
        : '';
      throw new Error(validationErrors || data?.message || `Request failed (${response.status}).`);
    }

    console.log('[API] Success:', response.status);
    return data as VehicularAccident;
  },

  async deleteVehicularAccident(id: number, token?: string): Promise<boolean> {
    if (USE_MOCK_API) {
      return mockApi.deleteVehicularAccident(id);
    }

    await request(`/vehicular-accidents/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    return true;
  },
};
