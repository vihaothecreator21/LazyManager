import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const api = readFileSync(
  new URL('./features/inventory/api/dailySalesApi.ts', import.meta.url),
  'utf8',
)

assert.match(app, /path="\/daily-sales"/)
assert.match(app, /path="\/daily-sales\/new"/)
assert.doesNotMatch(app, /<RequireManager>\s*<DailySalesPage/)
assert.doesNotMatch(app, /<RequireManager>\s*<NewDailySalePage/)
assert.match(dashboard, /to="\/daily-sales"/)
assert.match(api, /createDailySale/)
assert.match(api, /confirmDailySale/)
assert.match(api, /cancelDailySale/)

console.log('daily sales route policy ok')
