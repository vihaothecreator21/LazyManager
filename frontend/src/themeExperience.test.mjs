import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const indexCss = readFileSync(new URL('./index.css', import.meta.url), 'utf8')
const appCss = readFileSync(new URL('./App.css', import.meta.url), 'utf8')

assert.match(indexCss, /--bg:\s*#08083f/)
assert.match(indexCss, /--surface:\s*#141457/)
assert.match(indexCss, /--button-bg:\s*#7b2cff/)
assert.match(indexCss, /--button-bg-hover:\s*#9a35ff/)
assert.match(appCss, /linear-gradient\(135deg,\s*#7b2cff,\s*#b338ff\)/)
assert.match(appCss, /\.inv-panel,[\s\S]*\.stock-count-side[\s\S]*background:\s*var\(--glass-surface\)/)
assert.doesNotMatch(indexCss, /--bg:\s*#f4f6f1/)

console.log('theme experience ok')
