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
import { useVehicularAccidents } from '../../src/context/VehicularAccidentContext';
import { api } from '../../src/services/api';
import { InvolvedPerson, MATANAO_BARANGAYS, VehicularAccidentPayload } from '../../src/types';

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

function buildEmptyForm(): VehicularAccidentPayload {
  const today = new Date().toISOString().split('T')[0];
  return {
    barangay: 'Asbang',
    purok: '',
    roadSegment: '',
    personName: '',
    accidentType: '',
    vehicleType: '',
    description: '',
    vehiclesInvolved: 1,
    injuredCount: 0,
    fatalityCount: 0,
    involvedPersons: [],
    photos: [],
    longitude: '',
    latitude: '',
    incidentDate: today,
  };
}

function buildEmptyPerson(): InvolvedPerson {
  return { personName: '', role: '', contactNumber: '' };
}

export default function AccidentFormScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ id?: string }>();
  const accidentId = params.id ? Number(params.id) : undefined;
  const { token } = useAuth();
  const { getAccidentById, createAccident, updateAccident } = useVehicularAccidents();

  const existingAccident = useMemo(() => {
    if (!accidentId) return undefined;
    return getAccidentById(accidentId);
  }, [getAccidentById, accidentId]);

  const [form, setForm] = useState<VehicularAccidentPayload>(buildEmptyForm());
  const [barangayOptions, setBarangayOptions] = useState<string[]>([...MATANAO_BARANGAYS]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (existingAccident) {
      const { id, status, validationRemarks, validatedAt, validatedBy, createdAt, ...payload } = existingAccident;
      setForm(payload);
    } else {
      setForm(buildEmptyForm());
    }
  }, [existingAccident, accidentId]);

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
        console.error('[AccidentForm] Failed to load barangays:', error);
      }
    }

    loadBarangays();

    return () => {
      cancelled = true;
    };
  }, [token]);

  const setField = <K extends keyof VehicularAccidentPayload>(key: K, value: VehicularAccidentPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const addPerson = () => {
    setField('involvedPersons', [...form.involvedPersons, buildEmptyPerson()]);
  };

  const updatePerson = (index: number, field: keyof InvolvedPerson, value: string) => {
    const updated = form.involvedPersons.map((person, personIndex) => (
      personIndex === index ? { ...person, [field]: value } : person
    ));
    setField('involvedPersons', updated);
  };

  const removePerson = (index: number) => {
    setField('involvedPersons', form.involvedPersons.filter((_, personIndex) => personIndex !== index));
  };

  const fillCurrentLocation = async () => {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission required', 'Please allow location access first.');
      return;
    }

    const position = await Location.getCurrentPositionAsync({});
    setField('latitude', position.coords.latitude.toFixed(6));
    setField('longitude', position.coords.longitude.toFixed(6));
  };

  const pickImages = async () => {
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
      const newPhotos = result.assets.map((asset) => asset.uri);
      setField('photos', [...(form.photos || []), ...newPhotos]);
    }
  };

  const removePhoto = (index: number) => {
    setField('photos', (form.photos || []).filter((_, photoIndex) => photoIndex !== index));
  };

  const submit = async () => {
    if (!form.barangay || !form.accidentType || !form.description) {
      Alert.alert('Missing fields', 'Please complete barangay, accident type, and description.');
      return;
    }

    if (!hasValidCoordinates(form.latitude, form.longitude)) {
      Alert.alert('Missing GPS', 'Capture a valid GPS location before submitting the accident report.');
      return;
    }

    try {
      setSubmitting(true);
      if (existingAccident && accidentId) {
        await updateAccident(accidentId, form);
      } else {
        await createAccident(form);
      }
      Alert.alert('Success', existingAccident ? 'Accident report updated and sent back for review.' : 'Accident report submitted.');
      router.replace('/(tabs)/vehicular-accidents');
    } catch (error) {
      Alert.alert('Error', error instanceof Error ? error.message : 'Unable to save accident report.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.contentContainer}>
        <Text style={styles.title}>{existingAccident ? 'Edit accident report' : 'New accident report'}</Text>
        {existingAccident?.status === 'returned' ? (
          <View style={styles.noticeBox}>
            <Text style={styles.noticeTitle}>Returned accident report</Text>
            <Text style={styles.noticeText}>
              {existingAccident.validationRemarks || 'This accident report was returned for revision. Update the details and submit again.'}
            </Text>
          </View>
        ) : null}

        <Text style={styles.sectionTitle}>Location</Text>
        <View style={styles.pickerWrapper}>
          <Picker selectedValue={form.barangay} onValueChange={(value) => setField('barangay', value)}>
            {barangayOptions.map((item) => (
              <Picker.Item key={item} label={item} value={item} />
            ))}
          </Picker>
        </View>
        <TextInput style={styles.input} placeholder="Purok / Sitio" value={form.purok} onChangeText={(value) => setField('purok', value)} />
        <TextInput style={styles.input} placeholder="Road segment (e.g. National Highway, Barangay Road)" value={form.roadSegment} onChangeText={(value) => setField('roadSegment', value)} />

        <Text style={styles.sectionTitle}>Accident details</Text>
        <TextInput
          style={styles.input}
          placeholder="Accident type (e.g. Collision, Hit and Run)"
          value={form.accidentType}
          onChangeText={(value) => setField('accidentType', value)}
        />
        <TextInput
          style={styles.input}
          placeholder="Vehicle type (e.g. Motorcycle, Truck, Car)"
          value={form.vehicleType}
          onChangeText={(value) => setField('vehicleType', value)}
        />
        <TextInput
          style={[styles.input, styles.textArea]}
          multiline
          placeholder="Description"
          value={form.description}
          onChangeText={(value) => setField('description', value)}
        />

        <Text style={styles.fieldLabel}>Vehicles involved</Text>
        <TextInput
          style={styles.input}
          keyboardType="numeric"
          placeholder="Vehicles involved"
          accessibilityLabel="Vehicles involved"
          value={String(form.vehiclesInvolved)}
          onChangeText={(value) => setField('vehiclesInvolved', Number(value || 0))}
        />
        <Text style={styles.fieldLabel}>Number of injured persons</Text>
        <TextInput
          style={styles.input}
          keyboardType="numeric"
          placeholder="Injured count"
          accessibilityLabel="Number of injured persons"
          value={String(form.injuredCount)}
          onChangeText={(value) => setField('injuredCount', Number(value || 0))}
        />
        <Text style={styles.fieldLabel}>Number of fatalities</Text>
        <TextInput
          style={styles.input}
          keyboardType="numeric"
          placeholder="Fatality count"
          accessibilityLabel="Number of fatalities"
          value={String(form.fatalityCount)}
          onChangeText={(value) => setField('fatalityCount', Number(value || 0))}
        />

        <Text style={styles.sectionTitle}>Involved Persons</Text>
        <TextInput
          style={styles.input}
          placeholder="Primary person name (optional)"
          value={form.personName}
          onChangeText={(value) => setField('personName', value)}
        />
        {form.involvedPersons.map((person, index) => (
          <View key={index} style={styles.personCard}>
            <View style={styles.personHeader}>
              <Text style={styles.personLabel}>Person {index + 1}</Text>
              <TouchableOpacity onPress={() => removePerson(index)}>
                <Ionicons name="close-circle" size={22} color="#ef4444" />
              </TouchableOpacity>
            </View>
            <TextInput
              style={styles.input}
              placeholder="Full name"
              value={person.personName}
              onChangeText={(value) => updatePerson(index, 'personName', value)}
            />
            <TextInput
              style={styles.input}
              placeholder="Role (e.g. Driver, Passenger, Pedestrian)"
              value={person.role}
              onChangeText={(value) => updatePerson(index, 'role', value)}
            />
            <TextInput
              style={styles.input}
              placeholder="Contact number"
              keyboardType="phone-pad"
              value={person.contactNumber}
              onChangeText={(value) => updatePerson(index, 'contactNumber', value)}
            />
          </View>
        ))}
        <TouchableOpacity style={styles.secondaryButton} onPress={addPerson}>
          <Ionicons name="person-add-outline" size={18} color="#047857" />
          <Text style={styles.secondaryButtonText}>Add involved person</Text>
        </TouchableOpacity>

        <Text style={styles.sectionTitle}>Photos</Text>
        <View style={styles.photoRow}>
          {(form.photos || []).length > 0 ? (
            (form.photos || []).map((photo, index) => (
              <TouchableOpacity key={index} onPress={() => removePhoto(index)}>
                <Image source={{ uri: photo }} style={styles.photo} />
                <View style={styles.photoRemoveBadge}>
                  <Ionicons name="close-circle" size={18} color="#dc2626" />
                </View>
              </TouchableOpacity>
            ))
          ) : (
            <Text style={styles.mutedText}>No photos selected yet.</Text>
          )}
        </View>
        <TouchableOpacity style={styles.secondaryButton} onPress={pickImages}>
          <Ionicons name="images-outline" size={18} color="#047857" />
          <Text style={styles.secondaryButtonText}>Choose photos</Text>
        </TouchableOpacity>

        <Text style={styles.sectionTitle}>GPS</Text>
        <Text style={styles.mutedText}>Capture a valid GPS point so the accident appears correctly on the GIS map.</Text>
        <View style={styles.gpsRow}>
          <TextInput
            style={[styles.input, styles.halfInput]}
            placeholder="Latitude"
            value={form.latitude}
            onChangeText={(value) => setField('latitude', value)}
          />
          <TextInput
            style={[styles.input, styles.halfInput]}
            placeholder="Longitude"
            value={form.longitude}
            onChangeText={(value) => setField('longitude', value)}
          />
        </View>
        <TouchableOpacity style={styles.secondaryButton} onPress={fillCurrentLocation}>
          <Ionicons name="locate-outline" size={18} color="#047857" />
          <Text style={styles.secondaryButtonText}>Use current location</Text>
        </TouchableOpacity>

        <Text style={styles.sectionTitle}>Incident date</Text>
        <TextInput
          style={styles.input}
          placeholder="YYYY-MM-DD"
          value={form.incidentDate}
          onChangeText={(value) => setField('incidentDate', value)}
        />
        <TouchableOpacity
          style={styles.secondaryButton}
          onPress={() => setField('incidentDate', new Date().toISOString().split('T')[0])}
        >
          <Ionicons name="calendar-outline" size={18} color="#047857" />
          <Text style={styles.secondaryButtonText}>Use today</Text>
        </TouchableOpacity>

        <View style={styles.footerActions}>
          <TouchableOpacity style={styles.cancelButton} onPress={() => router.replace('/(tabs)/vehicular-accidents')}>
            <Text style={styles.cancelButtonText}>Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.submitButton} onPress={submit} disabled={submitting}>
            <Text style={styles.submitButtonText}>{submitting ? 'Saving...' : existingAccident ? 'Update' : 'Submit'}</Text>
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
    color: '#111827',
    fontSize: 24,
    fontWeight: '800',
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
    color: '#111827',
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 10,
    marginTop: 10,
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
  fieldLabel: {
    color: '#374151',
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 6,
  },
  textArea: {
    minHeight: 110,
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
  gpsRow: {
    flexDirection: 'row',
    gap: 10,
  },
  halfInput: {
    flex: 1,
  },
  secondaryButton: {
    borderRadius: 12,
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
  },
  secondaryButtonText: {
    color: '#047857',
    fontWeight: '700',
  },
  footerActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
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
    backgroundColor: '#0f766e',
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  submitButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
  personCard: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  personHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  personLabel: {
    fontWeight: '700',
    color: '#374151',
  },
  photoRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginBottom: 12,
  },
  photo: {
    width: 80,
    height: 80,
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
    lineHeight: 18,
  },
});
