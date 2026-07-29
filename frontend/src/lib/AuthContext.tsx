import {
  useCallback,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import {
  getCurrentSession,
  logoutSession,
  refreshSession,
} from '../features/auth/api/authApi'
import type { LoginResponse } from '../features/auth/types'
import { AuthContext } from './authContext'
import {
  clearSession,
  saveSession,
  type StoredSession,
} from './tokenStorage'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<StoredSession | null>(null)
  const [isInitializing, setIsInitializing] = useState(true)

  useEffect(() => {
    let isActive = true

    async function restoreSession() {
      try {
        const response = await loadCurrentSession()
        const next: StoredSession = { user: response.user }
        saveSession(next)

        if (isActive) {
          setSession(next)
        }
      } catch {
        clearSession()

        if (isActive) {
          setSession(null)
        }
      } finally {
        if (isActive) {
          setIsInitializing(false)
        }
      }
    }

    void restoreSession()

    return () => {
      isActive = false
    }
  }, [])

  const login = useCallback((response: LoginResponse) => {
    const next: StoredSession = {
      user: response.user,
    }
    saveSession(next)
    setSession(next)
  }, [])

  const logout = useCallback(async () => {
    await logoutSession().catch(() => undefined)
    clearSession()
    setSession(null)
  }, [])

  return (
    <AuthContext.Provider value={{ session, isInitializing, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

async function loadCurrentSession(): Promise<LoginResponse> {
  try {
    return await getCurrentSession()
  } catch (err) {
    if (!hasStatus(err, 401)) {
      throw err
    }
  }

  await refreshSession()

  return getCurrentSession()
}

function hasStatus(err: unknown, status: number): boolean {
  return err instanceof Error && 'status' in err && err.status === status
}
