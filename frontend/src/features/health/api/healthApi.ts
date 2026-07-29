import type { HealthEndpoint, ReadyResponse, ServiceCheck } from '../types'

export const healthEndpoints: HealthEndpoint[] = [
  {
    key: 'people',
    name: 'People Service',
    service: 'people-service',
    path: '/api/people/ready',
    database: 'people_db',
  },
  {
    key: 'inventory',
    name: 'Inventory Service',
    service: 'inventory-service',
    path: '/api/inventory/ready',
    database: 'inventory_db',
  },
]

export async function checkService(endpoint: HealthEndpoint): Promise<ServiceCheck> {
  try {
    const response = await fetch(endpoint.path, {
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      return {
        ...endpoint,
        status: response.status === 503 ? 'not_ready' : 'error',
        message: `HTTP ${response.status}`,
        checkedAt: new Date().toISOString(),
      }
    }

    const data = (await response.json()) as ReadyResponse

    return {
      ...endpoint,
      status: data.status,
      message:
        data.database === 'connected'
          ? `${data.service} đã kết nối ${endpoint.database}`
          : `${data.service} chưa kết nối được ${endpoint.database}`,
      checkedAt: new Date().toISOString(),
    }
  } catch {
    return {
      ...endpoint,
      status: 'error',
      message: 'Gateway hoặc service chưa truy cập được',
      checkedAt: new Date().toISOString(),
    }
  }
}
