import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { listEmployees, type Employee } from '../../health/api/employeesApi'
import {
  createShiftAssignment,
  deleteScheduleDayOff,
  deleteShiftAssignment,
  getWeekSchedule,
  type ScheduleDay,
  type ScheduleDayOff,
  type ScheduleEmployee,
  type ShiftAssignment,
  type ShiftType,
  type WeekSchedule,
} from '../api/scheduleApi'

const initialWeekStart = mondayOf(new Date())

export function ScheduleGridPage() {
  const { logout } = useAuth()
  const [weekStart, setWeekStart] = useState(initialWeekStart)
  const [schedule, setSchedule] = useState<WeekSchedule | null>(null)
  const [employees, setEmployees] = useState<Employee[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const days = useMemo(() => schedule?.days ?? [], [schedule])

  const refreshSchedule = useCallback(async () => {
    setIsLoading(true)
    setError(null)

    try {
      setSchedule(await getWeekSchedule(weekStart))
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsLoading(false)
    }
  }, [weekStart])

  useEffect(() => {
    void refreshSchedule()
  }, [refreshSchedule])

  useEffect(() => {
    listEmployees()
      .then((result) =>
        setEmployees(result.filter((employee) => employee.status === 'ACTIVE')),
      )
      .catch(() =>
        setError('Không tải được danh sách nhân viên. Tài khoản cần quyền quản lý.'),
      )
  }, [])

  async function toggleShift(
    day: ScheduleDay,
    employee: Employee,
    shiftType: ShiftType,
  ) {
    setIsSaving(true)
    setError(null)

    try {
      const existing = assignmentFor(day, employee.id, shiftType)

      if (existing) {
        await deleteShiftAssignment(existing.id)
      } else {
        const manualOff = dayOffFor(day, employee.id)

        if (manualOff) {
          await deleteScheduleDayOff(manualOff.id)
        }

        await createShiftAssignment({
          work_date: day.date,
          shift_type: shiftType,
          employee_id: employee.id,
        })
      }

      await refreshSchedule()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsSaving(false)
    }
  }

  async function setEmployeeOff(day: ScheduleDay, employee: Employee) {
    setIsSaving(true)
    setError(null)

    try {
      await Promise.all(
        assignmentsFor(day, employee.id).map((assignment) =>
          deleteShiftAssignment(assignment.id),
        ),
      )
      await refreshSchedule()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsSaving(false)
    }
  }

  async function removeAssignment(id: number) {
    setError(null)

    try {
      await deleteShiftAssignment(id)
      await refreshSchedule()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <main className="shell schedule-shell">
      <section className="overview" aria-labelledby="schedule-title">
        <div>
          <p className="eyebrow">Schedule</p>
          <h1 id="schedule-title">Lịch làm việc</h1>
          <p className="summary">
            Chọn ca theo từng nhân viên. Ai không có ca trong ngày sẽ nằm ở hàng OFF.
          </p>
        </div>
        <div className="actions">
          <Link className="link-button" to="/">
            Dashboard
          </Link>
          <Link className="link-button" to="/employees">
            Nhân viên
          </Link>
          <button type="button" className="btn-danger" onClick={logout}>
            Đăng xuất
          </button>
        </div>
      </section>

      <section className="schedule-picker" aria-label="Chọn ca cho nhân viên">
        <div className="schedule-picker-header">
          <div>
            <p className="panel-label">Chọn ca ở đây</p>
            <h2>{schedule?.title ?? 'Lịch làm việc'}</h2>
          </div>
          <label>
            Tuần bắt đầu
            <input
              type="date"
              value={weekStart}
              onChange={(event) => setWeekStart(mondayOf(new Date(event.target.value)))}
            />
          </label>
        </div>

        {error ? <p className="form-error">{error}</p> : null}

        {isLoading || schedule === null ? (
          <div className="schedule-loading">Đang tải lịch làm việc...</div>
        ) : employees.length === 0 ? (
          <p className="empty-state">Chưa có nhân viên active để xếp lịch.</p>
        ) : (
          <div className="shift-picker-wrap">
            <div className="shift-picker-grid">
              <div />
              {days.map((day) => (
                <div className="day-header" key={day.date}>
                  <strong>{day.weekday_label}</strong>
                  <span>{formatDisplayDate(day.date)}</span>
                </div>
              ))}

              {employees.map((employee) => (
                <EmployeeScheduleRow
                  days={days}
                  disabled={isSaving}
                  employee={employee}
                  key={employee.id}
                  onToggleOff={setEmployeeOff}
                  onToggleShift={toggleShift}
                />
              ))}
            </div>
          </div>
        )}
      </section>

      <ScheduleResult
        employees={employees}
        isLoading={isLoading}
        onDeleteAssignment={removeAssignment}
        schedule={schedule}
      />
    </main>
  )
}

function EmployeeScheduleRow({
  days,
  disabled,
  employee,
  onToggleOff,
  onToggleShift,
}: {
  days: ScheduleDay[]
  disabled: boolean
  employee: Employee
  onToggleOff: (day: ScheduleDay, employee: Employee) => Promise<void>
  onToggleShift: (
    day: ScheduleDay,
    employee: Employee,
    shiftType: ShiftType,
  ) => Promise<void>
}) {
  return (
    <>
      <div className="member-name">{employee.name}</div>
      {days.map((day) => {
        const hasMorning = Boolean(assignmentFor(day, employee.id, 'MORNING'))
        const hasAfternoon = Boolean(assignmentFor(day, employee.id, 'AFTERNOON'))
        const isOff = !hasMorning && !hasAfternoon

        return (
          <div className="day-cell" key={`${employee.id}-${day.date}`}>
            <button
              type="button"
              className={`shift-btn morning ${hasMorning ? 'selected' : ''}`}
              disabled={disabled}
              onClick={() => void onToggleShift(day, employee, 'MORNING')}
            >
              Sáng
            </button>
            <button
              type="button"
              className={`shift-btn afternoon ${hasAfternoon ? 'selected' : ''}`}
              disabled={disabled}
              onClick={() => void onToggleShift(day, employee, 'AFTERNOON')}
            >
              Chiều
            </button>
            <button
              type="button"
              className={`shift-btn off ${isOff ? 'selected' : ''}`}
              disabled={disabled || isOff}
              onClick={() => void onToggleOff(day, employee)}
            >
              OFF
            </button>
          </div>
        )
      })}
    </>
  )
}

function ScheduleResult({
  employees,
  isLoading,
  onDeleteAssignment,
  schedule,
}: {
  employees: Employee[]
  isLoading: boolean
  onDeleteAssignment: (id: number) => Promise<void>
  schedule: WeekSchedule | null
}) {
  return (
    <section className="schedule-board" aria-live="polite">
      {isLoading || schedule === null ? (
        <div className="schedule-loading">Đang tải bảng lịch...</div>
      ) : (
        <>
          <h2>{schedule.title}</h2>
          <div className="schedule-table-wrap">
            <div className="schedule-table" role="table" aria-label={schedule.title}>
              <div className="schedule-head schedule-side" role="columnheader">
                THỨ/ NGÀY
              </div>
              {schedule.days.map((day) => (
                <div className="schedule-head" role="columnheader" key={day.date}>
                  <strong>{day.weekday_label}</strong>
                  <span>{formatDisplayDate(day.date)}</span>
                </div>
              ))}

              <div className="schedule-side" role="rowheader">
                SÁNG (8:30 - 15:30)
              </div>
              {schedule.days.map((day) => (
                <ShiftCell
                  key={`${day.date}-morning`}
                  assignments={day.morning.assignments}
                  tone="morning"
                  onDelete={onDeleteAssignment}
                />
              ))}

              <div className="schedule-side" role="rowheader">
                CHIỀU (15:00 - 22:00)
              </div>
              {schedule.days.map((day) => (
                <ShiftCell
                  key={`${day.date}-afternoon`}
                  assignments={day.afternoon.assignments}
                  tone="afternoon"
                  onDelete={onDeleteAssignment}
                />
              ))}

              <div className="schedule-side schedule-off-side" role="rowheader">
                OFF
              </div>
              {schedule.days.map((day) => (
                <OffCell day={day} employees={employees} key={`${day.date}-off`} />
              ))}
            </div>
          </div>
        </>
      )}
    </section>
  )
}

function ShiftCell({
  assignments,
  tone,
  onDelete,
}: {
  assignments: ShiftAssignment[]
  tone: 'morning' | 'afternoon'
  onDelete: (id: number) => Promise<void>
}) {
  return (
    <div className={`schedule-cell ${tone}`} role="cell">
      {assignments.map((assignment) => (
        <div className="shift-card" key={assignment.id}>
          <button
            type="button"
            className="icon-delete"
            aria-label={`Gỡ ${assignment.employee.name}`}
            onClick={() => void onDelete(assignment.id)}
          >
            ×
          </button>
          <strong>{assignment.employee.name}</strong>
          <ScheduleNoteInput assignment={assignment} />
        </div>
      ))}
    </div>
  )
}

function ScheduleNoteInput({ assignment }: { assignment: ShiftAssignment }) {
  const storageKey = `schedule-note-${assignment.id.toString()}`
  const [value, setValue] = useState(
    () => localStorage.getItem(storageKey) ?? assignment.note ?? '',
  )

  return (
    <input
      className="note-input"
      value={value}
      aria-label={`Ghi chú cho ${assignment.employee.name}`}
      onChange={(event) => {
        setValue(event.target.value)
        localStorage.setItem(storageKey, event.target.value)
      }}
    />
  )
}

function OffCell({ day, employees }: { day: ScheduleDay; employees: Employee[] }) {
  return (
    <div className="schedule-cell off" role="cell">
      {autoOffEmployees(day, employees).map((employee) => (
        <span className="off-name" key={employee.id}>
          {employee.name}
        </span>
      ))}
    </div>
  )
}

function assignmentsFor(day: ScheduleDay, employeeId: number): ShiftAssignment[] {
  return [...day.morning.assignments, ...day.afternoon.assignments].filter(
    (assignment) => assignment.employee.id === employeeId,
  )
}

function assignmentFor(
  day: ScheduleDay,
  employeeId: number,
  shiftType: ShiftType,
): ShiftAssignment | undefined {
  const assignments =
    shiftType === 'MORNING' ? day.morning.assignments : day.afternoon.assignments

  return assignments.find((assignment) => assignment.employee.id === employeeId)
}

function dayOffFor(day: ScheduleDay, employeeId: number): ScheduleDayOff | undefined {
  return day.offs.find((dayOff) => dayOff.employee.id === employeeId)
}

function autoOffEmployees(
  day: ScheduleDay,
  employees: ScheduleEmployee[],
): ScheduleEmployee[] {
  const assignedIds = new Set(
    [...day.morning.assignments, ...day.afternoon.assignments].map(
      (assignment) => assignment.employee.id,
    ),
  )
  const seen = new Set<number>()

  return [...day.offs.map((dayOff) => dayOff.employee), ...employees]
    .filter((employee) => !assignedIds.has(employee.id))
    .filter((employee) => {
      if (seen.has(employee.id)) {
        return false
      }

      seen.add(employee.id)
      return true
    })
}

function mondayOf(date: Date): string {
  const next = new Date(date)
  const day = next.getDay()
  const diff = day === 0 ? -6 : 1 - day
  next.setDate(next.getDate() + diff)

  return toDateInputValue(next)
}

function toDateInputValue(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function formatDisplayDate(value: string): string {
  const [year, month, day] = value.split('-')

  return `${day}/${month}/${year}`
}

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : 'Không thể xử lý yêu cầu.'
}
