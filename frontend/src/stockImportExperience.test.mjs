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
const table = readFileSync(
  new URL('./features/inventory/components/StockImportPreviewTable.tsx', import.meta.url),
  'utf8',
)
const page = readFileSync(
  new URL('./features/inventory/pages/StockImportPage.tsx', import.meta.url),
  'utf8',
)
const apiClient = readFileSync(new URL('./lib/apiClient.ts', import.meta.url), 'utf8')

assert.match(app, /path="\/inventory\/import"/)
assert.doesNotMatch(app, /<RequireManager>\s*<StockImportPage/)
assert.match(dashboard, /to: '\/inventory\/import'/)
assert.match(api, /uploadStockImport/)
assert.match(api, /confirmStockImport/)
assert.match(api, /raw_product_name/)
assert.match(api, /raw_variant/)
assert.match(table, /Tên sản phẩm/)
assert.doesNotMatch(table, /Tồn trước|Tồn sau|SKU gốc|Số lượng gốc/)
assert.match(table, /Biến thể/)
assert.match(table, /raw_variant/)
assert.match(page, /TÊN SẢN PHẨM, BIẾN THỂ, SKU và Tồn kho/)
assert.match(apiClient, /body instanceof FormData/)

console.log('stock import route policy ok')
