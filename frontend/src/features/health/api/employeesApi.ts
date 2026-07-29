import { apiClient } from '../../../lib/apiClient'

export type EmployeeRole = 'STORE_MANAGER' | 'STAFF'
export type EmployeeStatus = 'ACTIVE' | 'LOCKED'

export type Employee = {
  id: number
  name: string
  email: string
  role: EmployeeRole
  status: EmployeeStatus
  created_at: string | null
  updated_at: string | null
}

export type EmployeePayload = {
  name: string
  email: string
  password?: string
  role: EmployeeRole
  status: EmployeeStatus
}

export async function listEmployees(): Promise<Employee[]> {
  const response = await apiClient<{ employees: Employee[] }>(
    '/api/people/v1/employees',
  )

  return response.employees
}

export async function createEmployee(payload: EmployeePayload): Promise<Employee> {
  const response = await apiClient<{ employee: Employee }>(
    '/api/people/v1/employees',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.employee
}

export async function updateEmployee(
  id: number,
  payload: Partial<EmployeePayload>,
): Promise<Employee> {
  const response = await apiClient<{ employee: Employee }>(
    `/api/people/v1/employees/${id.toString()}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
  )

  return response.employee
}

export async function lockEmployee(id: number): Promise<void> {
  await apiClient<void>(`/api/people/v1/employees/${id.toString()}`, {
    method: 'DELETE',
  })
}
