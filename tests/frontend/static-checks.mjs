// Frontend regression checks that need no browser: node tests/frontend/static-checks.mjs
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'

let failed = 0
const fail = (msg) => { console.log('FAIL  ' + msg); failed++ }

// 1. A component that calls DOMPurify in its template must import it: in
//    <script setup>, a missing import is `undefined` at render time and the
//    whole page stops rendering ("Cannot read properties of undefined (reading 'sanitize')").
const walk = (dir) => readdirSync(dir).flatMap((f) => {
  const p = join(dir, f)
  return statSync(p).isDirectory() ? walk(p) : [p]
})
for (const file of walk('resources/js').filter((f) => f.endsWith('.vue') || f.endsWith('.js'))) {
  const src = readFileSync(file, 'utf8')
  if (/DOMPurify\./.test(src) && !/import\s+DOMPurify\s+from\s+['"]dompurify['"]/.test(src)) {
    fail(`${file} uses DOMPurify without importing it`)
  }
}

// 2. Incomplete themes (older versions, hand-edited) must not throw.
const set = []
globalThis.document = { querySelector: () => ({ style: { setProperty: (n, v) => set.push([n, v]) } }) }
const { default: injectThemeVariables } = await import('../../resources/js/lib/injectThemeVariables.js')
try {
  injectThemeVariables('body', { links: { default: '#123456' }, panel: { item: { hover: {} } }, buttons: {}, dimensions: {} })
  if (!set.some(([n, v]) => n === '--link-color' && v === '#123456')) fail('theme values are not applied')
  if (set.some(([, v]) => v === undefined)) fail('undefined theme values are applied')
} catch (e) {
  fail('incomplete theme throws: ' + e.message)
}

console.log(failed ? `${failed} failure(s)` : 'all frontend static checks passed')
process.exit(failed ? 1 : 0)
