import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const api = readFileSync(
  new URL('./features/inventory/api/borrowRecordsApi.ts', import.meta.url),
  'utf8',
)

assert.match(app, /path="\/borrowed"/)
assert.doesNotMatch(app, /<RequireManager>\s*<BorrowedPage/)
assert.match(dashboard, /to="\/borrowed"/)
assert.match(api, /listBorrowRecords/)
assert.match(api, /createBorrowRecord/)
assert.match(api, /returnBorrowRecord/)

console.log('borrowed route policy ok')
