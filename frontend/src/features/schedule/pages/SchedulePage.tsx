import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { PageHeader } from '../../../components/PageHeader'
import { listEmployees, type Employee } from '../../health/api/employeesApi'
import {
  createScheduleDayOff,
  createShiftAssignment,
  deleteScheduleDayOff,
  deleteShiftAssignment,
  getWeekSchedule,
  type ScheduleDay,
  type ShiftAssignment,
  type ShiftType,
  type WeekSchedule,
} from '../api/scheduleApi'

type AssignmentForm = {
  work_date: string
  shift_type: ShiftType
  employee_id: string
  note: string
}

type DayOffForm = {
  off_date: string
  employee_id: string
  note: string
}

const initialWeekStart = mondayOf(new Date())

export function SchedulePage() {
  const [weekStart, setWeekStart] = useState(initialWeekStart)
  const [schedule, setSchedule] = useState<WeekSchedule | null>(null)
  const [employees, setEmployees] = useState<Employee[]>([])
  const [canPickEmployee, setCanPickEmployee] = useState(true)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [assignmentForm, setAssignmentForm] = useState<AssignmentForm>({
    work_date: weekStart,
    shift_type: 'MORNING',
    employee_id: '',
    note: '',
  })
  const [dayOffForm, setDayOffForm] = useState<DayOffForm>({
    off_date: weekStart,
    employee_id: '',
    note: '',
  })

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
    setAssignmentForm((current) => ({ ...current, work_date: weekStart }))
    setDayOffForm((current) => ({ ...current, off_date: weekStart }))
  }, [weekStart])

  useEffect(() => {
    let isMounted = true

    listEmployees()
      .then((result) => {
        if (!isMounted) return
        setEmployees(result.filter((employee) => employee.status === 'ACTIVE'))
        setCanPickEmployee(true)
      })
      .catch(() => {
        if (!isMounted) return
        setCanPickEmployee(false)
      })

    return () => {
      isMounted = false
    }
  }, [])

  async function saveAssignment() {
    setIsSaving(true)
    setError(null)

    try {
      await createShiftAssignment({
        work_date: assignmentForm.work_date,
        shift_type: assignmentForm.shift_type,
        employee_id: Number(assignmentForm.employee_id),
        note: cleanNote(assignmentForm.note),
      })
      setAssignmentForm((current) => ({ ...current, note: '' }))
      await refreshSchedule()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setIsSaving(false)
    }
  }

  async function saveDayOff() {
    setIsSaving(true)
    setError(null)

    try {
      await createScheduleDayOff({
        off_date: dayOffForm.off_date,
        employee_id: Number(dayOffForm.employee_id),
        note: cleanNote(dayOffForm.note),
      })
      setDayOffForm((current) => ({ ...current, note: '' }))
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

  async function removeDayOff(id: number) {
    setError(null)

    try {
      await deleteScheduleDayOff(id)
      await refreshSchedule()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <main className="shell schedule-shell">
      <PageHeader
        eyebrow="Schedule"
        title="Lịch làm việc"
        summary="Bảng tuần gồm ca sáng, ca chiều và danh sách nhân viên OFF theo từng ngày."
        actions={
          <Link className="link-button" to="/employees">
            Nhân viên
          </Link>
        }
      />

      <section className="schedule-tools" aria-label="Điều khiển lịch">
        <label>
          Tuần bắt đầu
          <input
            type="date"
            value={weekStart}
            onChange={(event) => setWeekStart(mondayOf(new Date(event.target.value)))}
          />
        </label>

        <form
          onSubmit={(event) => {
            event.preventDefault()
            void saveAssignment()
          }}
        >
          <strong>Gán ca</strong>
          <ScheduleDaySelect
            days={days}
            value={assignmentForm.work_date}
            onChange={(workDate) =>
              setAssignmentForm((current) => ({ ...current, work_date: workDate }))
            }
          />
          <label>
            Ca
            <select
              value={assignmentForm.shift_type}
              onChange={(event) =>
                setAssignmentForm((current) => ({
                  ...current,
                  shift_type: event.target.value as ShiftType,
                }))
              }
            >
              <option value="MORNING">Sáng</option>
              <option value="AFTERNOON">Chiều</option>
            </select>
          </label>
          <EmployeeInput
            canPickEmployee={canPickEmployee}
            employees={employees}
            value={assignmentForm.employee_id}
            onChange={(employeeId) =>
              setAssignmentForm((current) => ({ ...current, employee_id: employeeId }))
            }
          />
          <label>
            Ghi chú
            <input
              value={assignmentForm.note}
              onChange={(event) =>
                setAssignmentForm((current) => ({ ...current, note: event.target.value }))
              }
            />
          </label>
          <button type="submit" disabled={isSaving || !assignmentForm.employee_id}>
            Thêm vào ca
          </button>
        </form>

        <form
          onSubmit={(event) => {
            event.preventDefault()
            void saveDayOff()
          }}
        >
          <strong>Đánh OFF</strong>
          <ScheduleDaySelect
            days={days}
            value={dayOffForm.off_date}
            onChange={(offDate) =>
              setDayOffForm((current) => ({ ...current, off_date: offDate }))
            }
          />
          <EmployeeInput
            canPickEmployee={canPickEmployee}
            employees={employees}
            value={dayOffForm.employee_id}
            onChange={(employeeId) =>
              setDayOffForm((current) => ({ ...current, employee_id: employeeId }))
            }
          />
          <label>
            Ghi chú
            <input
              value={dayOffForm.note}
              onChange={(event) =>
                setDayOffForm((current) => ({ ...current, note: event.target.value }))
              }
            />
          </label>
          <button type="submit" disabled={isSaving || !dayOffForm.employee_id}>
            Thêm OFF
          </button>
        </form>
      </section>

      {error ? <p className="form-error">{error}</p> : null}

      <section className="schedule-board" aria-live="polite">
        {isLoading || schedule === null ? (
          <div className="schedule-loading">Đang tải lịch làm việc...</div>
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
                    onDelete={removeAssignment}
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
                    onDelete={removeAssignment}
                  />
                ))}

                <div className="schedule-side schedule-off-side" role="rowheader">
                  OFF
                </div>
                {schedule.days.map((day) => (
                  <OffCell key={`${day.date}-off`} day={day} onDelete={removeDayOff} />
                ))}
              </div>
            </div>
          </>
        )}
      </section>
    </main>
  )
}

function ScheduleDaySelect({
  days,
  value,
  onChange,
}: {
  days: ScheduleDay[]
  value: string
  onChange: (value: string) => void
}) {
  return (
    <label>
      Ngày
      <select value={value} onChange={(event) => onChange(event.target.value)}>
        {days.map((day) => (
          <option value={day.date} key={day.date}>
            {day.weekday_label} - {formatDisplayDate(day.date)}
          </option>
        ))}
      </select>
    </label>
  )
}

function EmployeeInput({
  canPickEmployee,
  employees,
  value,
  onChange,
}: {
  canPickEmployee: boolean
  employees: Employee[]
  value: string
  onChange: (value: string) => void
}) {
  if (!canPickEmployee) {
    return (
      <label>
        ID nhân viên
        <input
          required
          type="number"
          min={1}
          value={value}
          onChange={(event) => onChange(event.target.value)}
        />
      </label>
    )
  }

  return (
    <label>
      Nhân viên
      <select required value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">Chọn nhân viên</option>
        {employees.map((employee) => (
          <option value={employee.id.toString()} key={employee.id}>
            {employee.name} - {employee.email}
          </option>
        ))}
      </select>
    </label>
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
          <span>{assignment.note || ' '}</span>
        </div>
      ))}
    </div>
  )
}

function OffCell({
  day,
  onDelete,
}: {
  day: ScheduleDay
  onDelete: (id: number) => Promise<void>
}) {
  return (
    <div className="schedule-cell off" role="cell">
      {day.offs.map((dayOff) => (
        <button
          type="button"
          className="off-name"
          title={dayOff.note ?? undefined}
          key={dayOff.id}
          onClick={() => void onDelete(dayOff.id)}
        >
          {dayOff.employee.name}
        </button>
      ))}
    </div>
  )
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

function cleanNote(value: string): string | null {
  const note = value.trim()

  return note.length > 0 ? note : null
}

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : 'Không thể xử lý yêu cầu.'
}
