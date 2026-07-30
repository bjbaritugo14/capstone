import React, { createContext, PropsWithChildren, useContext, useMemo, useState } from 'react';
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

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(token),
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

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }
  return context;
}
