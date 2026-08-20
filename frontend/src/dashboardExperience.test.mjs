import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const css = readFileSync(new URL('./App.css', import.meta.url), 'utf8')
const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const loginPage = readFileSync(
  new URL('./features/auth/pages/LoginPage.tsx', import.meta.url),
  'utf8',
)
const loginForm = readFileSync(
  new URL('./features/auth/components/LoginForm.tsx', import.meta.url),
  'utf8',
)

assert.doesNotMatch(dashboard, /checkService|healthEndpoints|ServiceCheck|refreshHealth|Service Control/)
assert.doesNotMatch(dashboard, /MVP|de[m]o|De[m]o|Golden path|scope|walking skeleton/)
assert.doesNotMatch(loginPage, /de[m]o|De[m]o|thử nghiệm|walking skeleton|manager@example\.com|staff@example\.com/)
assert.doesNotMatch(loginForm, /manager@example\.com/)
assert.match(dashboard, /operationGroups/)
assert.doesNotMatch(dashboard, /home-nav/)
assert.doesNotMatch(css, /\.home-nav/)
assert.doesNotMatch(css, /\.inv-topnav/)

// /products route policy: must exist and must NOT be inside RequireManager block
assert.match(app, /path="\/products"/, '/products route must exist in App.tsx')

// RequireManager block should only contain /employees, not /products
const requireManagerBlock = app.slice(app.indexOf('<Route element={<RequireManager'))
assert.match(
  requireManagerBlock,
  /path="\/employees"/,
  '/employees must be inside RequireManager',
)
assert.doesNotMatch(
  app,
  /<Route element={<RequireAuth \/>}>[\s\S]*<Route path="\/" element={<DashboardPage \/>}/,
  'Home page must be public',
)
assert.doesNotMatch(
  requireManagerBlock,
  /path="\/products"/,
  '/products must not be inside RequireManager',
)

for (const route of [
  '/schedule',
  '/employees',
  '/products',
  '/inventory',
  '/inventory/import',
  '/daily-sales',
  '/daily-sales/new',
  '/borrowed',
  '/stock-counts',
]) {
  assert.match(dashboard, new RegExp(`to: '${route}'`), `Home hub must link to ${route}`)
}

// /inventory route policy: must exist and must NOT be inside RequireManager block
assert.match(app, /path="\/inventory"/, '/inventory route must exist in App.tsx')

assert.doesNotMatch(
  requireManagerBlock,
  /path="\/inventory"/,
  '/inventory must not be inside RequireManager',
)
