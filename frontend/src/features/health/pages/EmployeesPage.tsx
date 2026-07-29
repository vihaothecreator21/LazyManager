import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import {
  createEmployee,
  listEmployees,
  lockEmployee,
  updateEmployee,
  type Employee,
  type EmployeePayload,
  type EmployeeRole,
  type EmployeeStatus,
} from '../api/employeesApi'

const emptyForm: EmployeePayload = {
  name: '',
  email: '',
  password: '',
  role: 'STAFF',
  status: 'ACTIVE',
}

export function EmployeesPage() {
  const { logout } = useAuth()
  const [employees, setEmployees] = useState<Employee[]>([])
  const [form, setForm] = useState<EmployeePayload>(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const activeCount = useMemo(
    () => employees.filter((employee) => employee.status === 'ACTIVE').length,
    [employees],
  )

  const refreshEmployees = useCallback(async () => {
    setIsLoading(true)
    setError(null)

    try {
      setEmployees(await listEmployees())
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    void refreshEmployees()
  }, [refreshEmployees])

  async function saveEmployee() {
    setIsSaving(true)
    setError(null)

    try {
      if (editingId === null) {
        await createEmployee(form)
      } else {
        const payload: Partial<EmployeePayload> = {
          name: form.name,
          email: form.email,
          role: form.role,
          status: form.status,
        }

        if (form.password) {
          payload.password = form.password
        }

        await updateEmployee(editingId, payload)
      }

      setForm(emptyForm)
      setEditingId(null)
      await refreshEmployees()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsSaving(false)
    }
  }

  async function lock(id: number) {
    setError(null)

    try {
      await lockEmployee(id)
      await refreshEmployees()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  function edit(employee: Employee) {
    setEditingId(employee.id)
    setForm({
      name: employee.name,
      email: employee.email,
      password: '',
      role: employee.role,
      status: employee.status,
    })
  }

  return (
    <main className="shell">
      <section className="overview" aria-labelledby="employees-title">
        <div>
          <p className="eyebrow">People</p>
          <h1 id="employees-title">Nhân viên</h1>
          <p className="summary">
            Quản lý tài khoản nhân viên, vai trò và trạng thái đăng nhập trong
            LazyManager.
          </p>
        </div>
        <div className="actions">
          <Link className="link-button" to="/">
            Dashboard
          </Link>
          <button type="button" className="btn-danger" onClick={logout}>
            Đăng xuất
          </button>
        </div>
      </section>

      <section className="employee-grid">
        <form
          className="employee-form"
          onSubmit={(event) => {
            event.preventDefault()
            void saveEmployee()
          }}
        >
          <div>
            <p className="panel-label">
              {editingId === null ? 'Tạo nhân viên' : 'Cập nhật nhân viên'}
            </p>
            <h2>{editingId === null ? 'Nhân viên mới' : form.email}</h2>
          </div>

          {error ? <p className="form-error">{error}</p> : null}

          <label>
            Họ tên
            <input
              required
              value={form.name}
              onChange={(event) =>
                setForm((current) => ({ ...current, name: event.target.value }))
              }
            />
          </label>

          <label>
            Email
            <input
              required
              type="email"
              value={form.email}
              onChange={(event) =>
                setForm((current) => ({ ...current, email: event.target.value }))
              }
            />
          </label>

          <label>
            Mật khẩu
            <input
              required={editingId === null}
              type="password"
              minLength={8}
              value={form.password}
              placeholder={editingId === null ? '' : 'Để trống nếu giữ mật khẩu cũ'}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  password: event.target.value,
                }))
              }
            />
          </label>

          <div className="form-row">
            <label>
              Role
              <select
                value={form.role}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    role: event.target.value as EmployeeRole,
                  }))
                }
              >
                <option value="STORE_MANAGER">STORE_MANAGER</option>
                <option value="STAFF">STAFF</option>
              </select>
            </label>

            <label>
              Trạng thái
              <select
                value={form.status}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    status: event.target.value as EmployeeStatus,
                  }))
                }
              >
                <option value="ACTIVE">ACTIVE</option>
                <option value="LOCKED">LOCKED</option>
              </select>
            </label>
          </div>

          <div className="actions">
            <button type="submit" disabled={isSaving}>
              {isSaving ? 'Đang lưu' : 'Lưu'}
            </button>
            {editingId === null ? null : (
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setEditingId(null)
                  setForm(emptyForm)
                }}
              >
                Hủy
              </button>
            )}
          </div>
        </form>

        <section className="employee-panel" aria-live="polite">
          <div className="employee-panel-header">
            <div>
              <p className="panel-label">Danh sách</p>
              <h2>{employees.length.toString()} nhân viên</h2>
            </div>
            <span className="pill ready">{activeCount.toString()} ACTIVE</span>
          </div>

          {isLoading ? (
            <p className="empty-state">Đang tải danh sách nhân viên...</p>
          ) : employees.length === 0 ? (
            <p className="empty-state">Chưa có nhân viên nào.</p>
          ) : (
            <div className="employee-table-wrap">
              <table className="employee-table">
                <thead>
                  <tr>
                    <th>Tên</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {employees.map((employee) => (
                    <tr key={employee.id}>
                      <td>{employee.name}</td>
                      <td>{employee.email}</td>
                      <td>{employee.role}</td>
                      <td>
                        <span
                          className={`pill ${
                            employee.status === 'ACTIVE' ? 'ready' : 'error'
                          }`}
                        >
                          {employee.status}
                        </span>
                      </td>
                      <td>
                        <div className="table-actions">
                          <button type="button" onClick={() => edit(employee)}>
                            Sửa
                          </button>
                          <button
                            type="button"
                            className="btn-danger"
                            disabled={employee.status === 'LOCKED'}
                            onClick={() => void lock(employee.id)}
                          >
                            Khóa
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </section>
    </main>
  )
}

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : 'Không thể xử lý yêu cầu.'
}
