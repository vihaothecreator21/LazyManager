export type UserRole = 'STORE_MANAGER' | 'STAFF'

export type AuthUser = {
  id: number
  email: string
  role: UserRole
}

export type LoginResponse = {
  user: AuthUser
}

export type LoginApiError = {
  status: number
  message: string
  validationErrors?: Record<string, string[]>
}
