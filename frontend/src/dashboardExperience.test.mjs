import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const css = readFileSync(new URL('./App.css', import.meta.url), 'utf8')

assert.doesNotMatch(dashboard, /checkService|healthEndpoints|ServiceCheck|refreshHealth|Service Control/)
assert.match(dashboard, /dashboardSlides/)
assert.match(dashboard, /window.setInterval/)
assert.match(dashboard, /pointer/i)
assert.match(css, /\.home-swiper/)
assert.match(css, /@keyframes float-card/)
