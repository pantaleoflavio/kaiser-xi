import { useCallback, useEffect, useMemo, useState } from 'react';
import { authApi } from '../api/auth';
import { clearStoredToken, getStoredToken, storeToken } from './tokenStorage';
import type { LoginPayload, RegisterPayload, User } from '../types/auth';
import { AuthContext, type AuthContextValue } from './auth-context';
import { applyTheme, defaultThemeId } from '../theme/themes';

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(() => getStoredToken());
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(() => Boolean(getStoredToken()));
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    applyTheme(user?.theme ?? defaultThemeId);
  }, [user?.theme]);

  const clearSession = useCallback(() => {
    clearStoredToken();
    setToken(null);
    setUser(null);
  }, []);

  useEffect(() => {
    window.addEventListener('kaiser-xi:unauthorized', clearSession);
    return () => window.removeEventListener('kaiser-xi:unauthorized', clearSession);
  }, [clearSession]);

  const refreshUser = useCallback(async () => {
    if (!getStoredToken()) return;

    setIsLoading(true);
    setError(null);
    try {
      setUser(await authApi.me());
    } catch (err) {
      clearSession();
      setError(err instanceof Error ? err.message : 'Unable to load authenticated user.');
    } finally {
      setIsLoading(false);
    }
  }, [clearSession]);

  useEffect(() => {
    let isMounted = true;

    async function loadCurrentUser() {
      if (!getStoredToken()) return;
      try {
        const currentUser = await authApi.me();
        if (isMounted) setUser(currentUser);
      } catch (err) {
        if (isMounted) {
          clearSession();
          setError(err instanceof Error ? err.message : 'Unable to load authenticated user.');
        }
      } finally {
        if (isMounted) setIsLoading(false);
      }
    }

    void loadCurrentUser();
    return () => {
      isMounted = false;
    };
  }, [clearSession]);

  const login = useCallback(async (payload: LoginPayload) => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await authApi.login(payload);
      storeToken(response.token);
      setToken(response.token);
      setUser(response.user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed.');
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  const register = useCallback(async (payload: RegisterPayload) => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await authApi.register(payload);
      storeToken(response.token);
      setToken(response.token);
      setUser(response.user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed.');
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      if (getStoredToken()) await authApi.logout();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Logout failed on the server.');
    } finally {
      clearSession();
      setIsLoading(false);
    }
  }, [clearSession]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(user && token),
      isLoading,
      error,
      login,
      register,
      logout,
      refreshUser,
      setAuthenticatedUser: setUser,
      clearError: () => setError(null),
    }),
    [error, isLoading, login, logout, refreshUser, register, token, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
