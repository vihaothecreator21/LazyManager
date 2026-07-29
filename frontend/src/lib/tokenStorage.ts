import type { AuthUser } from '../features/auth/types'

const TOKEN_KEY = 'lazymanager.jwt'
const USER_KEY = 'lazymanager.user'
const EXPIRES_AT_KEY = 'lazymanager.expires_at'

export type StoredSession = {
  user: AuthUser
}

export function saveSession(_session: StoredSession): void {
  clearSession()
}

export function loadSession(): StoredSession | null {
  clearSession()

  return null
}

export function clearSession(): void {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
  localStorage.removeItem(EXPIRES_AT_KEY)
}
