import { Outlet } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { LoginForm } from './LoginForm'

export function RequireManager() {
  const { session, isInitializing, login, logout } = useAuth()

  if (isInitializing) {
    return null
  }

  if (!session) {
    return (
      <main className="auth-page compact-auth-page">
        <aside className="login-card" aria-label="Đăng nhập quản lý">
          <p className="panel-label">Quản lý nhân viên</p>
          <h1>Đăng nhập quản lý</h1>
          <LoginForm onLoginSuccess={login} />
        </aside>
      </main>
    )
  }

  if (session.user.role !== 'STORE_MANAGER') {
    return (
      <main className="shell">
        <section className="overview" aria-labelledby="manager-only-title">
          <div>
            <p className="eyebrow">Quản lý nhân viên</p>
            <h1 id="manager-only-title">Cần quyền quản lý</h1>
            <p className="summary">
              Tài khoản hiện tại không có quyền chỉnh sửa nhân viên.
            </p>
          </div>
          <div className="actions">
            <button type="button" className="btn-danger" onClick={() => void logout()}>
              Đăng xuất
            </button>
          </div>
        </section>
      </main>
    )
  }

  return <Outlet />
}
