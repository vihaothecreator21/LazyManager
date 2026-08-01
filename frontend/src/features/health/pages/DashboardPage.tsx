import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { checkService, healthEndpoints } from '../../health/api/healthApi'
import type { ServiceCheck } from '../../health/types'

const initialChecks: ServiceCheck[] = healthEndpoints.map((ep) => ({
  ...ep,
  status: 'idle',
  message: 'Chưa kiểm tra',
}))

export function DashboardPage() {
  const { session, logout } = useAuth()
  const [checks, setChecks] = useState<ServiceCheck[]>(initialChecks)
  const [isChecking, setIsChecking] = useState(false)

  const refreshHealth = useCallback(async () => {
    setIsChecking(true)
    setChecks((cur) =>
      cur.map((s) => ({ ...s, status: 'loading', message: 'Đang kiểm tra...' })),
    )
    const next = await Promise.all(healthEndpoints.map(checkService))
    setChecks(next)
    setIsChecking(false)
  }, [])

  useEffect(() => {
    void refreshHealth()
  }, [refreshHealth])

  const allReady = useMemo(
    () => checks.every((s) => s.status === 'ready'),
    [checks],
  )

  if (!session) return null

  return (
    <main className="shell">
      <section className="overview" aria-labelledby="page-title">
        <div>
          <p className="eyebrow">Operations</p>
          <h1 id="page-title">Service Control</h1>
          <p className="summary">
            Đang theo dõi health check cho gateway, service và database của
            LazyManager.
          </p>
        </div>
        <div className="actions">
          <Link className="link-button" to="/employees">
            Nhân viên
          </Link>
          <Link className="link-button" to="/schedule">
            Lịch làm việc
          </Link>
          <button type="button" onClick={refreshHealth} disabled={isChecking}>
            {isChecking ? 'Đang kiểm tra' : 'Kiểm tra lại'}
          </button>
          <button
            type="button"
            id="btn-logout"
            className="btn-danger"
            onClick={logout}
          >
            Đăng xuất
          </button>
        </div>
      </section>

      <section className="status-strip" aria-live="polite">
        <div className={allReady ? 'status-dot ready' : 'status-dot'} />
        <div>
          <strong>
            {allReady ? 'Walking skeleton sẵn sàng' : 'Đang chờ service'}
          </strong>
          <p>
            {allReady
              ? 'Frontend, gateway, services và databases đã nối với nhau.'
              : 'Bật Docker Compose, sau đó kiểm tra lại trang này.'}
          </p>
        </div>
      </section>

      <section className="flow" aria-label="Walking skeleton flow">
        <span>React</span>
        <span>Nginx</span>
        <span>People Service</span>
        <span>people_db</span>
        <span>Inventory Service</span>
        <span>inventory_db</span>
      </section>

      <section
        className="session-panel"
        aria-labelledby="session-title"
      >
        <div>
          <p className="panel-label">Phiên hiện tại</p>
          <h2 id="session-title">{session.user.email}</h2>
          <dl className="session-meta">
            <div>
              <dt>Role</dt>
              <dd>{session.user.role}</dd>
            </div>
            <div>
              <dt>Auth</dt>
              <dd>Cookie HttpOnly</dd>
            </div>
          </dl>
        </div>
      </section>

      <section className="services" aria-label="Service readiness">
        {checks.map((service) => (
          <article className="service-card" key={service.key}>
            <div className="service-header">
              <div>
                <h2>{service.name}</h2>
                <p>{service.path}</p>
              </div>
              <span className={`pill ${service.status}`}>{service.status}</span>
            </div>
            <dl>
              <div>
                <dt>Laravel</dt>
                <dd>{service.service}</dd>
              </div>
              <div>
                <dt>Database</dt>
                <dd>{service.database}</dd>
              </div>
              <div>
                <dt>Kết quả</dt>
                <dd>{service.message}</dd>
              </div>
            </dl>
          </article>
        ))}
      </section>
    </main>
  )
}
