import React, { createContext, PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../services/api';
import { Report, ReportPayload } from '../types';
import { useAuth } from './AuthContext';

type ReportContextValue = {
  reports: Report[];
  loading: boolean;
  refreshReports: () => Promise<void>;
  createReport: (payload: ReportPayload) => Promise<void>;
  updateReport: (id: number, payload: ReportPayload) => Promise<void>;
  deleteReport: (id: number) => Promise<void>;
  getReportById: (id: number) => Report | undefined;
};

const ReportContext = createContext<ReportContextValue | undefined>(undefined);

export function ReportProvider({ children }: PropsWithChildren) {
  const { token, isAuthenticated } = useAuth();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(false);

  const refreshReports = async () => {
    if (!isAuthenticated && !api.useMockApi) {
      setReports([]);
      return;
    }

    setLoading(true);
    try {
      console.log('[ReportContext] Fetching reports with token:', token ? token.substring(0, 20) + '...' : 'NONE');
      const data = await api.getReports(token || undefined);
      console.log('[ReportContext] Got reports:', data.length);
      setReports(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('[ReportContext] Failed to fetch reports:', err);
      setReports([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthenticated || api.useMockApi) {
      refreshReports();
    } else {
      setReports([]);
    }
  }, [isAuthenticated]);

  const value = useMemo<ReportContextValue>(
    () => ({
      reports,
      loading,
      refreshReports,
      createReport: async (payload) => {
        const result = await api.createReport(payload, token || undefined);
        console.log('[ReportContext] Report created on server, ID:', result?.id);
        await refreshReports();
      },
      updateReport: async (id, payload) => {
        const updated = await api.updateReport(id, payload, token || undefined);
        setReports((current) => current.map((item) => (item.id === id ? updated : item)));
      },
      deleteReport: async (id) => {
        await api.deleteReport(id, token || undefined);
        setReports((current) => current.filter((item) => item.id !== id));
      },
      getReportById: (id) => reports.find((report) => report.id === id),
    }),
    [loading, reports, token]
  );

  return <ReportContext.Provider value={value}>{children}</ReportContext.Provider>;
}

export function useReports() {
  const context = useContext(ReportContext);
  if (!context) {
    throw new Error('useReports must be used inside ReportProvider');
  }
  return context;
}
