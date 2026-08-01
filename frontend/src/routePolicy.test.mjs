import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const schedule = readFileSync(
  new URL('./features/schedule/pages/ScheduleGridPage.tsx', import.meta.url),
  'utf8',
)

assert.match(app, /<Route path="\/" element={<DashboardPage \/>} \/>/)
assert.match(app, /<Route path="\/schedule" element={<ScheduleGridPage \/>} \/>/)
assert.match(
  app,
  /<Route element={<RequireManager \/>}>[\s\S]*<Route path="\/employees" element={<EmployeesPage \/>} \/>/,
)
assert.doesNotMatch(dashboard, /if \(!session\) return null/)
assert.doesNotMatch(schedule, /useAuth|Đăng xuất/)
