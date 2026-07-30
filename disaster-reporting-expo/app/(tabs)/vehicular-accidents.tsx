import { useRouter } from 'expo-router';
import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useVehicularAccidents } from '../../src/context/VehicularAccidentContext';
import { VehicularAccident } from '../../src/types';

function formatStatus(status?: VehicularAccident['status']) {
  return (status ?? 'recorded').replace(/^\w/, (letter) => letter.toUpperCase());
}

function hasCoordinates(latitude?: string, longitude?: string) {
  return Boolean(latitude && longitude && latitude !== '0' && longitude !== '0');
}

export default function VehicularAccidentsScreen() {
  const router = useRouter();
  const { accidents, loading, refreshAccidents, deleteAccident } = useVehicularAccidents();
  const [search, setSearch] = useState('');

  const filteredAccidents = useMemo(() => {
    const keyword = search.trim().toLowerCase();
    if (!keyword) return accidents;

    return accidents.filter((accident) =>
      [
        accident.barangay ?? '',
        accident.purok ?? '',
        accident.personName ?? '',
        accident.accidentType ?? '',
        accident.description ?? '',
        accident.status ?? '',
        accident.validationRemarks ?? '',
      ]
        .join(' ')
        .toLowerCase()
        .includes(keyword)
    );
  }, [accidents, search]);

  const confirmDelete = (accident: VehicularAccident) => {
    Alert.alert('Delete accident report', `Delete the ${accident.accidentType ?? ''} report for ${accident.barangay ?? ''}?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          await deleteAccident(accident.id);
        },
      },
    ]);
  };

  return (
    <SafeAreaView style={styles.container}>
      <FlatList
        data={filteredAccidents}
        keyExtractor={(item, index) => item.id != null ? String(item.id) : String(index)}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={refreshAccidents} />}
        contentContainerStyle={styles.contentContainer}
        ListHeaderComponent={
          <>
            <View style={styles.statCard}>
              <Text style={styles.statLabel}>Vehicular accident records</Text>
              <Text style={styles.statValue}>{accidents.length}</Text>
              <Text style={styles.statHelper}>Submit, track validation status, revise returned reports, and delete accident records.</Text>
            </View>

            <View style={styles.toolbar}>
              <TextInput
                style={styles.searchInput}
                placeholder="Search by place, type, status, or remarks"
                value={search}
                onChangeText={setSearch}
              />
              <TouchableOpacity style={styles.addButton} onPress={() => router.push('/(tabs)/accident-form')}>
                <Text style={styles.addButtonText}>New</Text>
              </TouchableOpacity>
            </View>

            <Text style={styles.sectionTitle}>Accident reports</Text>
          </>
        }
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>{item.accidentType ?? 'Unknown'}</Text>
              <Text
                style={[
                  styles.badge,
                  item.status === 'returned'
                    ? styles.badgeReturned
                    : item.status === 'validated'
                      ? styles.badgeValidated
                      : styles.badgeRecorded,
                ]}
              >
                {formatStatus(item.status)}
              </Text>
            </View>

            <Text style={styles.cardMeta}>
              {item.barangay ?? ''} | {item.purok ?? ''}
            </Text>
            <Text style={styles.cardMeta}>Person involved: {item.personName || 'Not specified'}</Text>
            <Text style={styles.cardMeta}>
              Vehicles: {item.vehiclesInvolved ?? 0} | Injured: {item.injuredCount ?? 0} | Fatalities: {item.fatalityCount ?? 0}
            </Text>
            <Text style={styles.description}>{item.description ?? ''}</Text>
            <Text style={styles.cardMeta}>Incident date: {item.incidentDate ?? '-'}</Text>
            <Text style={styles.cardMeta}>
              GPS: {hasCoordinates(item.latitude, item.longitude) ? `${item.latitude}, ${item.longitude}` : 'No GPS captured'}
            </Text>
            {item.validationRemarks ? (
              <View
                style={[
                  styles.feedbackBox,
                  item.status === 'returned' ? styles.feedbackReturned : styles.feedbackNeutral,
                ]}
              >
                <Text style={styles.feedbackTitle}>
                  {item.status === 'returned' ? 'Returned with remarks' : 'Validator remarks'}
                </Text>
                <Text style={styles.feedbackText}>{item.validationRemarks}</Text>
              </View>
            ) : null}

            <View style={styles.actions}>
              <TouchableOpacity
                style={styles.editButton}
                onPress={() =>
                  router.push({
                    pathname: '/(tabs)/accident-form',
                    params: { id: String(item.id) },
                  })
                }
              >
                <Text style={styles.editButtonText}>Edit</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.deleteButton} onPress={() => confirmDelete(item)}>
                <Text style={styles.deleteButtonText}>Delete</Text>
              </TouchableOpacity>
            </View>
          </View>
        )}
        ListEmptyComponent={
          loading ? <ActivityIndicator style={{ marginTop: 32 }} /> : <Text style={styles.emptyText}>No accident reports found.</Text>
        }
      />
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
    paddingBottom: 28,
  },
  statCard: {
    backgroundColor: '#0f766e',
    borderRadius: 18,
    padding: 18,
    marginBottom: 16,
  },
  statLabel: {
    color: '#ccfbf1',
    fontSize: 14,
  },
  statValue: {
    color: '#fff',
    fontSize: 34,
    fontWeight: '800',
    marginTop: 6,
  },
  statHelper: {
    color: '#d1fae5',
    marginTop: 6,
  },
  toolbar: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'center',
    marginBottom: 12,
  },
  searchInput: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#d1d5db',
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  addButton: {
    backgroundColor: '#111827',
    paddingHorizontal: 18,
    paddingVertical: 13,
    borderRadius: 12,
  },
  addButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 12,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    padding: 14,
    marginBottom: 12,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  cardTitle: {
    flex: 1,
    color: '#111827',
    fontSize: 16,
    fontWeight: '800',
    marginRight: 10,
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    fontSize: 12,
    fontWeight: '700',
  },
  badgeRecorded: {
    color: '#0f766e',
    backgroundColor: '#ccfbf1',
  },
  badgeValidated: {
    color: '#166534',
    backgroundColor: '#dcfce7',
  },
  badgeReturned: {
    color: '#991b1b',
    backgroundColor: '#fee2e2',
  },
  cardMeta: {
    fontSize: 13,
    color: '#4b5563',
    marginBottom: 4,
  },
  description: {
    color: '#111827',
    marginVertical: 8,
    lineHeight: 20,
  },
  feedbackBox: {
    borderRadius: 12,
    padding: 12,
    marginTop: 8,
  },
  feedbackNeutral: {
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
  },
  feedbackReturned: {
    backgroundColor: '#fef2f2',
    borderWidth: 1,
    borderColor: '#fecaca',
  },
  feedbackTitle: {
    color: '#111827',
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 4,
  },
  feedbackText: {
    color: '#374151',
    lineHeight: 19,
  },
  actions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  editButton: {
    flex: 1,
    backgroundColor: '#ecfdf5',
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
  },
  editButtonText: {
    color: '#047857',
    fontWeight: '700',
  },
  deleteButton: {
    flex: 1,
    backgroundColor: '#fee2e2',
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
  },
  deleteButtonText: {
    color: '#dc2626',
    fontWeight: '700',
  },
  emptyText: {
    textAlign: 'center',
    color: '#6b7280',
    marginTop: 36,
  },
});
