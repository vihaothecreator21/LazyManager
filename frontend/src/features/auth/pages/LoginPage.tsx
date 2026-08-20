import { Navigate } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { LoginForm } from '../components/LoginForm'
import heroImage from '../../../assets/hero.png'

export function LoginPage() {
  const { session, isInitializing, login } = useAuth()

  if (isInitializing) {
    return null
  }

  if (session) {
    return <Navigate to="/" replace />
  }

  return (
    <main className="auth-page">
      <section className="auth-panel" aria-labelledby="login-title">
        <div className="auth-copy">
          <p className="eyebrow">LazyManager</p>
          <h1 id="login-title">Đăng nhập vận hành</h1>
          <p>
            Truy cập không gian quản lý ca làm, tồn kho và giao dịch hằng ngày của cửa hàng.
          </p>
          <div className="auth-visual" aria-hidden="true">
            <img src={heroImage} alt="" />
          </div>
        </div>
        <aside className="login-card" aria-label="Đăng nhập">
          <LoginForm onLoginSuccess={login} />
        </aside>
      </section>
    </main>
  )
}
