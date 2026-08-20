import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const api = readFileSync(
  new URL('./features/inventory/api/stockCountsApi.ts', import.meta.url),
  'utf8',
)
const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const page = readFileSync(
  new URL('./features/inventory/pages/StockCountsPage.tsx', import.meta.url),
  'utf8',
)

assert.match(api, /listStockCounts/)
assert.match(api, /createStockCount/)
assert.match(api, /getStockCount/)
assert.match(api, /updateStockCountLines/)
assert.match(api, /stockCountExportCsvUrl/)
assert.match(app, /path="\/stock-counts"/)
assert.match(page, /Phiên kiểm kho/)
assert.match(page, /Tạo phiên/)
assert.match(page, /Tải CSV/)
assert.match(page, /Số thực tế/)
assert.match(page, /Chênh lệch/)

console.log('stock counts experience ok')
