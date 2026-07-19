export type ServiceStatus = 'idle' | 'loading' | 'ready' | 'not_ready' | 'error'

export type HealthEndpoint = {
  key: 'people' | 'inventory'
  name: string
  service: string
  path: string
  database: string
}

export type ReadyResponse = {
  status: 'ready' | 'not_ready'
  service: string
  database: 'connected' | 'disconnected'
}

export type ServiceCheck = HealthEndpoint & {
  status: ServiceStatus
  message: string
  checkedAt?: string
}
