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
  return { status: 0, message: 'Không thể đăng nhập. Vui lòng thử lại.' }
}

function getFallbackMessage(status: number): string {
  if (status === 401) return 'Email hoặc mật khẩu không đúng.'
  if (status === 403) return 'Tài khoản đã bị khóa hoặc không có quyền.'
  if (status === 422) return 'Dữ liệu đăng nhập chưa hợp lệ.'
  return 'Không thể đăng nhập. Vui lòng thử lại.'
}
