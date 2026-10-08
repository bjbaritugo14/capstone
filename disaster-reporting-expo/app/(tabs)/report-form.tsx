import { Picker } from '@react-native-picker/picker';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Image,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../../src/context/AuthContext';
import { useReports } from '../../src/context/ReportContext';
import { api } from '../../src/services/api';
import { AffectedFamily, DISASTER_TYPES, MATANAO_BARANGAYS, ReportPayload, SEVERITY_LEVELS } from '../../src/types';

function hasValidCoordinates(latitude?: string, longitude?: string) {
  if (!latitude || !longitude) {
    return false;
  }

  const lat = Number(latitude);
  const lng = Number(longitude);

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return false;
  }

  return !(lat === 0 && lng === 0);
}

function buildEmptyForm(): ReportPayload {
  const today = new Date().toISOString().split('T')[0];
  return {
    barangay: 'Asbang',
    purok: '',
    description: '',
    disasterType: 'Flood',
    severity: 'minor',
    families: [],
    affectedStructures: 0,
    photos: [],
    longitude: '',
    latitude: '',
    reportDate: today,
  };
}

function buildEmptyFamily(): AffectedFamily {
  return {
    firstName: '',
    lastName: '',
    familyHeadName: '',
    householdMembers: 0,
    contactNumber: '',
    evacuationStatus: 'Not Evacuated',
    description: '',
    severity: 'minor',
    latitude: '',
    longitude: '',
    photos: [],
  };
}

function splitName(name?: string) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean);

  if (parts.length <= 1) {
    return {
      firstName: parts[0] || '',
      lastName: '',
    };
  }

  return {
    firstName: parts.slice(0, -1).join(' '),
    lastName: parts[parts.length - 1],
  };
}

function normalizeFamilyName(family: AffectedFamily): AffectedFamily {
  if (family.firstName || family.lastName) {
    return {
      ...family,
      firstName: family.firstName || '',
      lastName: family.lastName || '',
      familyHeadName: `${family.firstName || ''} ${family.lastName || ''}`.trim(),
    };
  }

  const split = splitName(family.familyHeadName);

  return {
    ...family,
    ...split,
    familyHeadName: `${split.firstName} ${split.lastName}`.trim(),
  };
}

export default function ReportFormScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ id?: string }>();
  const reportId = params.id ? Number(params.id) : undefined;
  const { token } = useAuth();
  const { getReportById, createReport, updateReport } = useReports();

  const existingReport = useMemo(() => {
    if (!reportId) return undefined;
    return getReportById(reportId);
  }, [getReportById, reportId]);

  const [form, setForm] = useState<ReportPayload>(buildEmptyForm());
  const [barangayOptions, setBarangayOptions] = useState<string[]>([...MATANAO_BARANGAYS]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (existingReport) {
      const { id, status, validationRemarks, validatedAt, validatedBy, createdAt, ...payload } = existingReport;
      setForm({
        ...payload,
        families: payload.families.map(normalizeFamilyName),
        affectedStructures: payload.families.length,
      });
    } else {
      setForm(buildEmptyForm());
    }
  }, [existingReport, reportId]);

  useEffect(() => {
    let cancelled = false;

    async function loadBarangays() {
      try {
        const options = await api.getBarangays(token || undefined);
        const names = options.map((item) => item.name);

        if (cancelled || names.length === 0) {
          return;
        }

        setBarangayOptions(names);
        setForm((current) => {
          if (current.barangay && names.includes(current.barangay)) {
            return current;
          }

          return {
            ...current,
            barangay: names[0],
          };
        });
      } catch (error) {
        console.error('[ReportForm] Failed to load barangays:', error);
      }
    }

    loadBarangays();

    return () => {
      cancelled = true;
    };
  }, [token]);

  const setField = <K extends keyof ReportPayload>(key: K, value: ReportPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const addFamily = () => {
    const updated = [...form.families, buildEmptyFamily()];
    setForm((current) => ({
      ...current,
      families: updated,
      affectedStructures: updated.length,
    }));
  };

  const updateFamily = (index: number, field: keyof AffectedFamily, value: string | number | string[]) => {
    const updated = form.families.map((family, familyIndex) => (
      familyIndex === index ? { ...family, [field]: value } : family
    ));
    setForm((current) => ({ ...current, families: updated }));
  };

  const removeFamily = (index: number) => {
    const updated = form.families.filter((_, familyIndex) => familyIndex !== index);
    setForm((current) => ({
      ...current,
      families: updated,
      affectedStructures: updated.length,
    }));
  };

  const pickFamilyImages = async (index: number) => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission required', 'Please allow access to the photo library first.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: Platform.OS !== 'web',
      quality: 0.8,
      selectionLimit: 5,
    });

    if (!result.canceled) {
      const uris = result.assets.map((asset) => asset.uri);
      const currentPhotos = form.families[index].photos || [];
      updateFamily(index, 'photos', [...currentPhotos, ...uris]);
    }
  };

  const removeFamilyPhoto = (familyIndex: number, photoIndex: number) => {
    const currentPhotos = form.families[familyIndex].photos || [];
    updateFamily(familyIndex, 'photos', currentPhotos.filter((_, index) => index !== photoIndex));
  };

  const fillFamilyLocation = async (index: number) => {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission required', 'Please allow location access first.');
      return;
    }

    const position = await Location.getCurrentPositionAsync({});
    const latitude = position.coords.latitude.toFixed(6);
    const longitude = position.coords.longitude.toFixed(6);

    const updated = form.families.map((family, familyIndex) => (
      familyIndex === index ? { ...family, latitude, longitude } : family
    ));

    setForm((current) => ({
      ...current,
      families: updated,
      latitude: current.latitude || latitude,
      longitude: current.longitude || longitude,
    }));
  };

  const submit = async () => {
    if (!form.barangay) {
      Alert.alert('Missing fields', 'Please select a barangay.');
      return;
    }

    if (form.families.length === 0) {
      Alert.alert('Missing fields', 'Please add at least one affected family.');
      return;
    }

    const normalizedFamilies = form.families.map(normalizeFamilyName);
    const familyMissingName = normalizedFamilies.some((family) => !family.firstName.trim() || !family.lastName.trim());

    if (familyMissingName) {
      Alert.alert('Missing name', 'Enter first name and last name for each affected family.');
      return;
    }

    const familyWithCoordinates = normalizedFamilies.find((family) => hasValidCoordinates(family.latitude, family.longitude));
    const hasMapLocation = Boolean(familyWithCoordinates) || hasValidCoordinates(form.latitude, form.longitude);

    if (!hasMapLocation) {
      Alert.alert('Missing GPS', 'Capture GPS coordinates for at least one affected family before submitting.');
      return;
    }

    const submissionForm: ReportPayload = {
      ...form,
      families: normalizedFamilies,
      description: normalizedFamilies[0]?.description || form.description || 'Disaster report',
      severity: normalizedFamilies[0]?.severity || form.severity,
      latitude: familyWithCoordinates?.latitude || form.latitude,
      longitude: familyWithCoordinates?.longitude || form.longitude,
      photos: normalizedFamilies.flatMap((family) => family.photos || []),
      affectedStructures: normalizedFamilies.length,
    };

    try {
      setSubmitting(true);
      if (existingReport && reportId) {
        await updateReport(reportId, submissionForm);
      } else {
        await createReport(submissionForm);
      }
      setForm(buildEmptyForm());
      router.setParams({ id: '' });
      Alert.alert('Success', existingReport ? 'Report updated and sent back for review.' : 'Report submitted and saved to server.');
      router.replace('/(tabs)/dashboard');
    } catch (error) {
      console.error('[ReportForm] Submit error:', error);
      const message = error instanceof Error ? error.message : 'Unable to save report.';
      Alert.alert('Error', `Failed to save: ${message}`);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.contentContainer}>
        <Text style={styles.title}>{existingReport ? 'Edit disaster damage report' : 'New disaster damage report'}</Text>
        {existingReport?.status === 'returned' ? (
          <View style={styles.noticeBox}>
            <Text style={styles.noticeTitle}>Returned report</Text>
            <Text style={styles.noticeText}>
              {existingReport.validationRemarks || 'This report was returned for revision. Update the details and submit again.'}
            </Text>
          </View>
        ) : null}

        <Text style={styles.sectionTitle}>Barangay location</Text>
        <View style={styles.pickerWrapper}>
          <Picker selectedValue={form.barangay} onValueChange={(value) => setField('barangay', value)}>
            {barangayOptions.map((item) => (
              <Picker.Item key={item} label={item} value={item} />
            ))}
          </Picker>
        </View>
        <TextInput style={styles.input} placeholder="Purok" value={form.purok} onChangeText={(value) => setField('purok', value)} />

        <Text style={styles.sectionTitle}>Disaster type</Text>
        <View style={styles.pickerWrapper}>
          <Picker selectedValue={form.disasterType} onValueChange={(value) => setField('disasterType', value)}>
            {DISASTER_TYPES.map((item) => (
              <Picker.Item key={item} label={item} value={item} />
            ))}
          </Picker>
        </View>

        <Text style={styles.sectionTitle}>Incident date</Text>
        <TextInput
          style={styles.input}
          placeholder="YYYY-MM-DD"
          value={form.reportDate}
          onChangeText={(value) => setField('reportDate', value)}
        />
        <TouchableOpacity
          style={styles.secondaryButton}
          onPress={() => setField('reportDate', new Date().toISOString().split('T')[0])}
        >
          <Ionicons name="calendar-outline" size={18} color="#1d4ed8" />
          <Text style={styles.secondaryButtonText}>Use today</Text>
        </TouchableOpacity>

        <Text style={styles.sectionTitle}>Affected family records ({form.families.length})</Text>

        {form.families.map((family, index) => (
          <View key={index} style={styles.familyCard}>
            <View style={styles.familyHeader}>
              <Text style={styles.familyLabel}>Affected family {index + 1}</Text>
              <TouchableOpacity onPress={() => removeFamily(index)}>
                <Ionicons name="trash-outline" size={20} color="#dc2626" />
              </TouchableOpacity>
            </View>

            <View style={styles.nameRow}>
              <TextInput
                style={[styles.input, styles.nameInput]}
                placeholder="First name"
                value={family.firstName}
                onChangeText={(value) => updateFamily(index, 'firstName', value)}
              />
              <TextInput
                style={[styles.input, styles.nameInput]}
                placeholder="Last name"
                value={family.lastName}
                onChangeText={(value) => updateFamily(index, 'lastName', value)}
              />
            </View>
            <TextInput
              style={styles.input}
              keyboardType="numeric"
              placeholder="Household members"
              value={family.householdMembers ? String(family.householdMembers) : ''}
              onChangeText={(value) => updateFamily(index, 'householdMembers', Number(value || 0))}
            />
            <TextInput
              style={styles.input}
              keyboardType="phone-pad"
              placeholder="Contact number"
              value={family.contactNumber}
              onChangeText={(value) => updateFamily(index, 'contactNumber', value)}
            />

            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={family.evacuationStatus}
                onValueChange={(value) => updateFamily(index, 'evacuationStatus', value)}
              >
                <Picker.Item label="Not Evacuated" value="Not Evacuated" />
                <Picker.Item label="Evacuated" value="Evacuated" />
                <Picker.Item label="In Evacuation Center" value="In Evacuation Center" />
                <Picker.Item label="Returned Home" value="Returned Home" />
              </Picker>
            </View>

            <Text style={styles.subLabel}>Family damage description</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              multiline
              placeholder="Describe the damage or situation for this family"
              value={family.description}
              onChangeText={(value) => updateFamily(index, 'description', value)}
            />

            <Text style={styles.subLabel}>Severity</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={family.severity}
                onValueChange={(value) => updateFamily(index, 'severity', value)}
              >
                {SEVERITY_LEVELS.map((item) => (
                  <Picker.Item key={item} label={item.charAt(0).toUpperCase() + item.slice(1)} value={item} />
                ))}
              </Picker>
            </View>

            <Text style={styles.subLabel}>Family GPS coordinates</Text>
            <View style={styles.gpsRow}>
              <TextInput
                style={[styles.input, styles.halfInput]}
                placeholder="Latitude"
                value={family.latitude}
                onChangeText={(value) => updateFamily(index, 'latitude', value)}
              />
              <TextInput
                style={[styles.input, styles.halfInput]}
                placeholder="Longitude"
                value={family.longitude}
                onChangeText={(value) => updateFamily(index, 'longitude', value)}
              />
            </View>
            <TouchableOpacity style={styles.secondaryButton} onPress={() => fillFamilyLocation(index)}>
              <Ionicons name="locate-outline" size={16} color="#1d4ed8" />
              <Text style={styles.secondaryButtonText}>Use current location</Text>
            </TouchableOpacity>

            <Text style={styles.subLabel}>Photos</Text>
            <View style={styles.photoRow}>
              {(family.photos || []).length > 0 ? (
                (family.photos || []).map((photo, photoIndex) => (
                  <TouchableOpacity key={photoIndex} onPress={() => removeFamilyPhoto(index, photoIndex)}>
                    <Image source={{ uri: photo }} style={styles.photo} />
                    <View style={styles.photoRemoveBadge}>
                      <Ionicons name="close-circle" size={18} color="#dc2626" />
                    </View>
                  </TouchableOpacity>
                ))
              ) : (
                <Text style={styles.mutedText}>No photos.</Text>
              )}
            </View>
            <TouchableOpacity style={styles.secondaryButton} onPress={() => pickFamilyImages(index)}>
              <Ionicons name="images-outline" size={16} color="#1d4ed8" />
              <Text style={styles.secondaryButtonText}>Add photos</Text>
            </TouchableOpacity>
          </View>
        ))}

        <TouchableOpacity style={styles.addFamilyButton} onPress={addFamily}>
          <Ionicons name="add-circle-outline" size={20} color="#1d4ed8" />
          <Text style={styles.secondaryButtonText}>Add affected family</Text>
        </TouchableOpacity>

        <View style={styles.footerActions}>
          <TouchableOpacity style={styles.cancelButton} onPress={() => router.replace('/(tabs)/dashboard')}>
            <Text style={styles.cancelButtonText}>Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.submitButton} onPress={submit} disabled={submitting}>
            <Text style={styles.submitButtonText}>{submitting ? 'Saving...' : existingReport ? 'Update' : 'Submit'}</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f4f7fb',
  },
  contentContainer: {
    padding: 16,
    paddingBottom: 34,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 16,
  },
  noticeBox: {
    backgroundColor: '#fef2f2',
    borderWidth: 1,
    borderColor: '#fecaca',
    borderRadius: 12,
    padding: 12,
    marginBottom: 14,
  },
  noticeTitle: {
    color: '#991b1b',
    fontSize: 14,
    fontWeight: '800',
    marginBottom: 4,
  },
  noticeText: {
    color: '#7f1d1d',
    lineHeight: 19,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 10,
    marginTop: 14,
  },
  subLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
    marginTop: 8,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 12,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  pickerWrapper: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 12,
    marginBottom: 12,
    overflow: 'hidden',
  },
  familyCard: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 12,
    padding: 12,
    marginBottom: 14,
  },
  familyHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  familyLabel: {
    fontWeight: '700',
    color: '#374151',
    fontSize: 15,
  },
  gpsRow: {
    flexDirection: 'row',
    gap: 10,
  },
  nameRow: {
    flexDirection: 'row',
    gap: 10,
  },
  nameInput: {
    flex: 1,
  },
  halfInput: {
    flex: 1,
  },
  photoRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginBottom: 12,
  },
  photo: {
    width: 72,
    height: 72,
    borderRadius: 10,
    backgroundColor: '#e5e7eb',
  },
  photoRemoveBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
  },
  mutedText: {
    color: '#6b7280',
    marginBottom: 8,
    fontSize: 13,
    lineHeight: 18,
  },
  secondaryButton: {
    borderRadius: 12,
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
    paddingVertical: 10,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
  },
  addFamilyButton: {
    borderRadius: 12,
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
  secondaryButtonText: {
    color: '#1d4ed8',
    fontWeight: '700',
  },
  footerActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  cancelButton: {
    flex: 1,
    backgroundColor: '#e5e7eb',
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: '#111827',
    fontWeight: '700',
  },
  submitButton: {
    flex: 1,
    backgroundColor: '#2563eb',
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  submitButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
});
