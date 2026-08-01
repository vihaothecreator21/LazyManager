import { apiClient } from '../../../lib/apiClient'

export type ShiftType = 'MORNING' | 'AFTERNOON'

export type ScheduleEmployee = {
  id: number
  name: string
  email: string
}

export type ShiftAssignment = {
  id: number
  work_date: string
  shift_type: ShiftType
  employee: ScheduleEmployee
  note: string | null
}

export type ScheduleDayOff = {
  id: number
  off_date: string
  employee: ScheduleEmployee
  note: string | null
}

export type ScheduleShift = {
  label: string
  assignments: ShiftAssignment[]
}

export type ScheduleDay = {
  date: string
  weekday_label: string
  morning: ScheduleShift
  afternoon: ScheduleShift
  offs: ScheduleDayOff[]
}

export type WeekSchedule = {
  title: string
  week_start: string
  week_end: string
  days: ScheduleDay[]
}

export type ShiftAssignmentPayload = {
  work_date: string
  shift_type: ShiftType
  employee_id: number
  note?: string | null
}

export type ScheduleDayOffPayload = {
  off_date: string
  employee_id: number
  note?: string | null
}

export async function getWeekSchedule(weekStart: string): Promise<WeekSchedule> {
  return apiClient<WeekSchedule>(
    `/api/people/v1/schedules?week_start=${encodeURIComponent(weekStart)}`,
  )
}

export async function createShiftAssignment(
  payload: ShiftAssignmentPayload,
): Promise<ShiftAssignment> {
  const response = await apiClient<{ assignment: ShiftAssignment }>(
    '/api/people/v1/schedules/assignments',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.assignment
}

export async function deleteShiftAssignment(id: number): Promise<void> {
  await apiClient<void>(`/api/people/v1/schedules/assignments/${id.toString()}`, {
    method: 'DELETE',
  })
}

export async function createScheduleDayOff(
  payload: ScheduleDayOffPayload,
): Promise<ScheduleDayOff> {
  const response = await apiClient<{ day_off: ScheduleDayOff }>(
    '/api/people/v1/schedules/day-offs',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.day_off
}

export async function deleteScheduleDayOff(id: number): Promise<void> {
  await apiClient<void>(`/api/people/v1/schedules/day-offs/${id.toString()}`, {
    method: 'DELETE',
  })
}
