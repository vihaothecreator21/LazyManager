import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const css = readFileSync(new URL('./App.css', import.meta.url), 'utf8')
const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')

assert.doesNotMatch(dashboard, /checkService|healthEndpoints|ServiceCheck|refreshHealth|Service Control/)
assert.match(dashboard, /dashboardSlides/)
assert.match(dashboard, /window.setInterval/)
assert.match(dashboard, /pointer/i)
assert.match(css, /\.home-swiper/)
assert.match(css, /@keyframes float-card/)

// /products route policy: must exist and must NOT be inside RequireManager block
assert.match(app, /path="\/products"/, '/products route must exist in App.tsx')

// RequireManager block should only contain /employees, not /products
const requireManagerBlock = app.slice(app.indexOf('<Route element={<RequireManager'))
assert.doesNotMatch(
  requireManagerBlock,
  /path="\/products"/,
  '/products must not be inside RequireManager',
)

// Dashboard nav must link to /products and /inventory
assert.match(dashboard, /to="\/products"/, 'Dashboard nav must have /products link')
assert.match(dashboard, /to="\/inventory"/, 'Dashboard nav must have /inventory link')

