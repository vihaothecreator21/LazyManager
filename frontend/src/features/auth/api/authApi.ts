import { apiClient } from '../../../lib/apiClient'
import type { LoginFormData } from '../schemas/loginSchema'
import type { LoginApiError, LoginResponse } from '../types'

export async function login(data: LoginFormData): Promise<LoginResponse> {
  try {
    return await apiClient<LoginResponse>('/api/people/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify(data),
      skipAuthRedirect: true,
    })
  } catch (err) {
    throw toLoginApiError(err)
  }
}

export async function refreshSession(): Promise<LoginResponse> {
  return apiClient<LoginResponse>('/api/people/v1/auth/refresh', {
    method: 'POST',
    skipAuthRedirect: true,
  })
}

export async function getCurrentSession(): Promise<LoginResponse> {
  return apiClient<LoginResponse>('/api/people/v1/auth/me', {
    method: 'GET',
    skipAuthRedirect: true,
  })
}

export async function logoutSession(): Promise<void> {
  await apiClient<void>('/api/people/v1/auth/logout', {
    method: 'POST',
    skipAuthRedirect: true,
  })
}

function toLoginApiError(err: unknown): LoginApiError {
  if (err instanceof Error && 'status' in err) {
    const status = (err as Error & { status: number }).status
    return {
      status,
      message: err.message || getFallbackMessage(status),
    }
  }
  return { status: 0, message: 'Khong the dang nhap. Vui long thu lai.' }
}

function getFallbackMessage(status: number): string {
  if (status === 401) return 'Email hoac mat khau khong dung.'
  if (status === 403) return 'Tai khoan da bi khoa hoac khong co quyen.'
  if (status === 422) return 'Du lieu dang nhap chua hop le.'
  return 'Khong the dang nhap. Vui long thu lai.'
}
