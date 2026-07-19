import { useEffect, useMemo, useState } from 'react'
import './App.css'
import { checkService, healthEndpoints } from './features/health/api/healthApi'
import type { ServiceCheck } from './features/health/types'

const initialChecks: ServiceCheck[] = healthEndpoints.map((endpoint) => ({
  ...endpoint,
  status: 'idle',
  message: 'Not checked yet',
}))

function App() {
  const [checks, setChecks] = useState<ServiceCheck[]>(initialChecks)
  const [isChecking, setIsChecking] = useState(false)

  async function refreshHealth() {
    setIsChecking(true)
    setChecks((current) =>
      current.map((service) => ({
        ...service,
        status: 'loading',
        message: 'Checking service and database',
      })),
    )

    const nextChecks = await Promise.all(healthEndpoints.map(checkService))
    setChecks(nextChecks)
    setIsChecking(false)
  }

  useEffect(() => {
    void refreshHealth()
  }, [])

  const allReady = useMemo(
    () => checks.every((service) => service.status === 'ready'),
    [checks],
  )

  return (
    <main className="shell">
      <section className="overview" aria-labelledby="page-title">
        <div>
          <p className="eyebrow">LazyManager MVP</p>
          <h1 id="page-title">Health Dashboard</h1>
          <p className="summary">
            React is checking both Laravel services through the Nginx gateway.
          </p>
        </div>
        <button type="button" onClick={refreshHealth} disabled={isChecking}>
          {isChecking ? 'Checking' : 'Refresh'}
        </button>
      </section>

      <section className="flow" aria-label="Walking skeleton flow">
        <span>React</span>
        <span>Nginx</span>
        <span>People Service</span>
        <span>people_db</span>
        <span>Inventory Service</span>
        <span>inventory_db</span>
      </section>

      <section className="status-strip" aria-live="polite">
        <div className={allReady ? 'status-dot ready' : 'status-dot'} />
        <div>
          <strong>{allReady ? 'Walking skeleton ready' : 'Waiting for services'}</strong>
          <p>
            {allReady
              ? 'Frontend, gateway, services, and databases are connected.'
              : 'Start Docker Compose, then refresh this page.'}
          </p>
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
                <dt>Result</dt>
                <dd>{service.message}</dd>
              </div>
            </dl>
          </article>
        ))}
      </section>
    </main>
  )
}

export default App
