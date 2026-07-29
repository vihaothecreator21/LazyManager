import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'

/**
 * Bảo vệ route: nếu chưa có session → redirect /login.
 * Dùng làm wrapper cho tất cả route cần xác thực.
 */
export function RequireAuth() {
  const { session, isInitializing } = useAuth()

  if (isInitializing) {
    return null
  }

  if (!session) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}
