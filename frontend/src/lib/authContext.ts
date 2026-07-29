import { createContext } from 'react'
import type { LoginResponse } from '../features/auth/types'
import type { StoredSession } from './tokenStorage'

export type AuthContextValue = {
  session: StoredSession | null
  isInitializing: boolean
  login: (response: LoginResponse) => void
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
