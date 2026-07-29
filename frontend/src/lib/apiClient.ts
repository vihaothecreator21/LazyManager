import { clearSession } from './tokenStorage'

type RequestOptions = RequestInit & {
  skipAuthRedirect?: boolean
  hasRetriedAfterRefresh?: boolean
}

export async function apiClient<T>(
  url: string,
  options: RequestOptions = {},
): Promise<T> {
  const {
    skipAuthRedirect = false,
    hasRetriedAfterRefresh = false,
    ...fetchOptions
  } = options

  const response = await fetchWithJsonHeaders(url, fetchOptions)

  if (!response.ok) {
    if (response.status === 401 && !skipAuthRedirect) {
      if (!hasRetriedAfterRefresh && (await refreshAuthCookies())) {
        return apiClient<T>(url, {
          ...options,
          hasRetriedAfterRefresh: true,
        })
      }

      clearSessionAndRedirect()
      throw Object.assign(
        new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'),
        { status: 401 },
      )
    }

    throw await toApiError(response)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

function fetchWithJsonHeaders(
  url: string,
  fetchOptions: RequestInit,
): Promise<Response> {
  return fetch(url, {
    ...fetchOptions,
    credentials: 'include',
    headers: jsonHeaders(fetchOptions),
  })
}

async function refreshAuthCookies(): Promise<boolean> {
  const response = await fetch('/api/people/v1/auth/refresh', {
    method: 'POST',
    credentials: 'include',
    headers: jsonHeaders({ method: 'POST' }),
  }).catch(() => null)

  return response?.ok === true
}

function jsonHeaders(fetchOptions: RequestInit): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(fetchOptions.headers as Record<string, string> | undefined),
  }
  const csrfToken = getCookie('lm_csrf_token')

  if (csrfToken && shouldSendCsrf(fetchOptions.method)) {
    headers['X-CSRF-TOKEN'] = csrfToken
  }

  return headers
}

function shouldSendCsrf(method: string | undefined): boolean {
  return !['GET', 'HEAD', 'OPTIONS'].includes((method ?? 'GET').toUpperCase())
}

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))

  return match ? decodeURIComponent(match[1]) : null
}

async function toApiError(response: Response): Promise<Error & { status: number }> {
  const body = await response.json().catch(() => ({}))
  const message =
    (body as { message?: string }).message ?? `HTTP ${response.status.toString()}`

  return Object.assign(new Error(message), { status: response.status })
}

function clearSessionAndRedirect(): void {
  clearSession()

  if (window.location.pathname !== '/login') {
    window.location.replace('/login')
  }
}
