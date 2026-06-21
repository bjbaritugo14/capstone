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
import { useReports } from '../../src/context/ReportContext';
import { Report } from '../../src/types';

export default function DashboardScreen() {
  const router = useRouter();
  const { reports, loading, refreshReports, deleteReport } = useReports();
  const [search, setSearch] = useState('');

  const filteredReports = useMemo(() => {
    const keyword = search.trim().toLowerCase();
    if (!keyword) return reports;

    return reports.filter((report) => {
      return [
        report.barangay ?? '',
        report.purok ?? '',
        report.description ?? '',
        report.disasterType ?? '',
        report.severity ?? '',
      ]
        .join(' ')
        .toLowerCase()
        .includes(keyword);
    });
  }, [reports, search]);

  const confirmDelete = (report: Report) => {
    Alert.alert('Delete report', `Delete the ${report.disasterType ?? ''} report for ${report.barangay ?? ''}?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          await deleteReport(report.id);
        },
      },
    ]);
  };

  return (
    <SafeAreaView style={styles.container}>
      <FlatList
        data={filteredReports}
        keyExtractor={(item, index) => item.id != null ? String(item.id) : String(index)}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={refreshReports} />}
        contentContainerStyle={styles.contentContainer}
        ListHeaderComponent={
          <>
            <View style={styles.statCard}>
              <Text style={styles.statLabel}>Records you submitted</Text>
              <Text style={styles.statValue}>{reports.length}</Text>
              <Text style={styles.statHelper}>Search, edit, and delete your reports below.</Text>
            </View>

            <View style={styles.toolbar}>
              <TextInput
                style={styles.searchInput}
                placeholder="Search by place, disaster, or description"
                value={search}
                onChangeText={setSearch}
              />
              <TouchableOpacity
                style={styles.addButton}
                onPress={() => router.push('/(tabs)/report-form')}
              >
                <Text style={styles.addButtonText}>New</Text>
              </TouchableOpacity>
            </View>

            <Text style={styles.sectionTitle}>My reports</Text>
          </>
        }
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>{item.disasterType ?? 'Unknown'}</Text>
              <Text style={styles.badge}>{item.severity ?? '-'}</Text>
            </View>

            <Text style={styles.cardMeta}>
              {item.barangay ?? ''} • {item.purok ?? ''}
            </Text>
            <Text style={styles.cardMeta}>
              Families: {item.families?.length ?? 0} • Structures: {item.affectedStructures ?? 0}
            </Text>
            <Text style={styles.description}>{item.description ?? ''}</Text>
            <Text style={styles.cardMeta}>Date reported: {item.reportDate ?? '-'}</Text>
            <Text style={styles.cardMeta}>GPS: {item.latitude ?? '-'}, {item.longitude ?? '-'}</Text>

            <View style={styles.actions}>
              <TouchableOpacity
                style={styles.editButton}
                onPress={() =>
                  router.push({
                    pathname: '/(tabs)/report-form',
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
          loading ? (
            <ActivityIndicator style={{ marginTop: 32 }} />
          ) : (
            <Text style={styles.emptyText}>No reports found.</Text>
          )
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
    backgroundColor: '#2563eb',
    borderRadius: 18,
    padding: 18,
    marginBottom: 16,
  },
  statLabel: {
    color: '#dbeafe',
    fontSize: 14,
  },
  statValue: {
    color: '#fff',
    fontSize: 34,
    fontWeight: '800',
    marginTop: 6,
  },
  statHelper: {
    color: '#e0e7ff',
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
    fontSize: 16,
    fontWeight: '800',
    color: '#111827',
  },
  badge: {
    color: '#1d4ed8',
    backgroundColor: '#dbeafe',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    fontSize: 12,
    fontWeight: '700',
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
  actions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  editButton: {
    flex: 1,
    backgroundColor: '#eff6ff',
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
  },
  editButtonText: {
    color: '#1d4ed8',
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
