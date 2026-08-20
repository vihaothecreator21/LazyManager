import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const nginx = readFileSync(new URL('./nginx.conf', import.meta.url), 'utf8')

assert.match(nginx, /location \/api\/people\/v1\/ \{[\s\S]*proxy_pass http:\/\/people-service:8000\/api\/v1\//)
assert.match(nginx, /location \/api\/inventory\/v1\/ \{[\s\S]*proxy_pass http:\/\/inventory-service:8000\/api\/v1\//)
assert.doesNotMatch(nginx, /rewrite \^\/api\/(?:people|inventory)\/v1\//)
