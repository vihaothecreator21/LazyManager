import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const api = readFileSync(
  new URL('./features/inventory/api/stockImportApi.ts', import.meta.url),
  'utf8',
)
const apiClient = readFileSync(new URL('./lib/apiClient.ts', import.meta.url), 'utf8')

assert.match(app, /path="\/inventory\/import"/)
assert.doesNotMatch(app, /<RequireManager>\s*<StockImportPage/)
assert.match(dashboard, /to="\/inventory\/import"/)
assert.match(api, /uploadStockImport/)
assert.match(api, /confirmStockImport/)
assert.match(apiClient, /body instanceof FormData/)

console.log('stock import route policy ok')
