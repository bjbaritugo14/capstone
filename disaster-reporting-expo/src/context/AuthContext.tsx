import React, { createContext, PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { supabase } from '../services/supabase';
import { api } from '../services/api';
import { User } from '../types';

type AuthContextValue = {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [initializing, setInitializing] = useState(true);

  useEffect(() => {
    let active = true;
    const { data: { subscription } } = supabase.auth.onAuthStateChange((event, session) => {
      if (!active) return;
      if (!session) { setToken(null); setUser(null); }
      else if (event === 'TOKEN_REFRESHED') setToken(session.access_token);
    });
    void (async () => {
      try {
        const { data: { session }, error } = await supabase.auth.getSession();
        if (error) throw error;
        if (session) {
          const profile = await api.getProfile();
          if (active) { setUser(profile); setToken(session.access_token); }
        }
      } catch {
        await supabase.auth.signOut({ scope: 'local' });
      } finally {
        if (active) setInitializing(false);
      }
    })();
    return () => { active = false; subscription.unsubscribe(); };
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(token && user),
      login: async (email: string, password: string) => {
        const response = await api.login(email, password);

        if (response.user.role !== 'field_officer') {
          await api.logout(response.token).catch(() => undefined);
          throw new Error('This mobile application is available only to Field Officer accounts.');
        }

        setToken(response.token);
        setUser(response.user);
      },
      logout: async () => {
        if (token) {
          await api.logout(token).catch(() => undefined);
        }
        setToken(null);
        setUser(null);
      },
    }),
    [token, user]
  );

  return <AuthContext.Provider value={value}>{initializing
    ? <View style={{ flex: 1, justifyContent: 'center' }}><ActivityIndicator size="large" /></View>
    : children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }
  return context;
}
