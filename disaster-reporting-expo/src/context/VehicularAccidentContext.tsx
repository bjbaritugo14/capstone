import React, { createContext, PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../services/api';
import { VehicularAccident, VehicularAccidentPayload } from '../types';
import { useAuth } from './AuthContext';

type VehicularAccidentContextValue = {
  accidents: VehicularAccident[];
  loading: boolean;
  refreshAccidents: () => Promise<void>;
  createAccident: (payload: VehicularAccidentPayload) => Promise<void>;
  updateAccident: (id: number, payload: VehicularAccidentPayload) => Promise<void>;
  deleteAccident: (id: number) => Promise<void>;
  getAccidentById: (id: number) => VehicularAccident | undefined;
};

const VehicularAccidentContext = createContext<VehicularAccidentContextValue | undefined>(undefined);

export function VehicularAccidentProvider({ children }: PropsWithChildren) {
  const { token, isAuthenticated } = useAuth();
  const [accidents, setAccidents] = useState<VehicularAccident[]>([]);
  const [loading, setLoading] = useState(false);

  const refreshAccidents = async () => {
    if (!isAuthenticated && !api.useMockApi) {
      setAccidents([]);
      return;
    }

    setLoading(true);
    try {
      const data = await api.getVehicularAccidents(token || undefined);
      setAccidents(Array.isArray(data) ? data : []);
    } catch {
      setAccidents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthenticated || api.useMockApi) {
      refreshAccidents();
    } else {
      setAccidents([]);
    }
  }, [isAuthenticated]);

  const value = useMemo<VehicularAccidentContextValue>(
    () => ({
      accidents,
      loading,
      refreshAccidents,
      createAccident: async (payload) => {
        await api.createVehicularAccident(payload, token || undefined);
        await refreshAccidents();
      },
      updateAccident: async (id, payload) => {
        const updated = await api.updateVehicularAccident(id, payload, token || undefined);
        setAccidents((current) => current.map((item) => (item.id === id ? updated : item)));
      },
      deleteAccident: async (id) => {
        await api.deleteVehicularAccident(id, token || undefined);
        setAccidents((current) => current.filter((item) => item.id !== id));
      },
      getAccidentById: (id) => accidents.find((accident) => accident.id === id),
    }),
    [accidents, loading, token]
  );

  return <VehicularAccidentContext.Provider value={value}>{children}</VehicularAccidentContext.Provider>;
}

export function useVehicularAccidents() {
  const context = useContext(VehicularAccidentContext);
  if (!context) {
    throw new Error('useVehicularAccidents must be used inside VehicularAccidentProvider');
  }
  return context;
}
