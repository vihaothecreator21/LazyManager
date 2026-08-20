import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const apiClient = readFileSync(new URL('./lib/apiClient.ts', import.meta.url), 'utf8')
const demoApi = readFileSync(new URL('./lib/demoApi.ts', import.meta.url), 'utf8')
const vercel = readFileSync(new URL('../vercel.json', import.meta.url), 'utf8')

assert.match(apiClient, /handleDemoRequest/)
assert.match(apiClient, /const demoResponse = handleDemoRequest/)
assert.match(demoApi, /VITE_DEMO_MODE/)
assert.match(demoApi, /\/api\/inventory\/v1\/products/)
assert.match(demoApi, /\/api\/inventory\/v1\/inventory/)
assert.match(demoApi, /\/api\/inventory\/v1\/daily-sales/)
assert.match(demoApi, /\/api\/inventory\/v1\/stock-counts/)
assert.match(demoApi, /\/api\/people\/v1\/auth\/me/)
assert.match(demoApi, /demo@lazymanager\.local/)

const vercelConfig = JSON.parse(vercel)
assert.equal(vercelConfig.buildCommand, 'npm run build')
assert.equal(vercelConfig.outputDirectory, 'dist')
assert.equal(vercelConfig.framework, 'vite')

console.log('demo mode experience ok')
