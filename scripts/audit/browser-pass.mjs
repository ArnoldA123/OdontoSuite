#!/usr/bin/env node
/*
 * browser-pass.mjs — wrapper CLI for the axis A7 browser smoke pass (issue #31).
 *
 * Runs `pnpm exec playwright test tests/e2e/smoke.spec.js` against an app that
 * is already listening (php artisan serve on :8000, Vite HMR on :5173), then
 * reads the per-page evidence JSON written by the spec (<out>/<page>.json) and
 * summarizes the console errors, page errors and failed requests each page
 * produced. A page that does not render stays a finding: this script never
 * touches application source.
 *
 * Exit 0 only when every spec passed, every expected page recorded
 * `rendered: true` and no pageerror was captured. Console errors and HTTP
 * responses >= 400 are reported per page but do not fail the run by
 * themselves; they are the diagnostic payload, not the gate.
 *
 * Usage:
 *   node scripts/audit/browser-pass.mjs [--out=dir] [--base=url]
 */
import { existsSync, mkdirSync, readFileSync, readdirSync } from 'node:fs'
import { resolve, dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const DEFAULT_OUT = '.atl/qa-evidence/audit/browser'
const DEFAULT_BASE = 'http://localhost:8000'
const EXPECTED_PAGES = [
  'login',
  'dashboard',
  'calendar',
  'patients',
  'cash-register',
  'quotations',
  'business-intelligence'
]

const USAGE = `Usage: node scripts/audit/browser-pass.mjs [--out=dir] [--base=url]

Runs the browser smoke pass (tests/e2e/smoke.spec.js) against a running app and
summarizes the per-page console/page errors recorded as JSON evidence.

Options:
  --out=dir   evidence directory (default ${DEFAULT_OUT})
  --base=url  app origin (default ${DEFAULT_BASE})
  --help      print this usage and exit 0

Exit 0 only when every page rendered and no pageerror was recorded. Console
errors and HTTP >= 400 responses are reported but do not fail the run.
`

const args = Object.fromEntries(
  process.argv.slice(2).map(arg => {
    const match = arg.match(/^--([^=]+)(?:=(.*))?$/)
    return match ? [match[1], match[2] ?? true] : ['_', arg]
  })
)

if (args.h || args.help) {
  console.log(USAGE)
  process.exit(0)
}

const OUT = resolve(ROOT, String(args.out ?? DEFAULT_OUT))
const BASE = String(args.base ?? DEFAULT_BASE).replace(/\/+$/, '')

mkdirSync(OUT, { recursive: true })

const result = spawnSync('pnpm', ['exec', 'playwright', 'test', 'tests/e2e/smoke.spec.js'], {
  cwd: ROOT,
  stdio: 'inherit',
  env: { ...process.env, BROWSER_PASS_OUT: OUT, BROWSER_PASS_BASE: BASE }
})

const evidenceFiles = existsSync(OUT) ? readdirSync(OUT).filter(name => name.endsWith('.json')) : []

const evidenceByPage = new Map()
for (const name of evidenceFiles) {
  const filePath = join(OUT, name)
  let parsed
  try {
    parsed = JSON.parse(readFileSync(filePath, 'utf8'))
  } catch (error) {
    console.error(`  ! could not parse ${name}: ${error.message}`)
    continue
  }
  const { page = name.replace(/\.json$/, '') } = parsed
  evidenceByPage.set(page, parsed)
}

let failed = result.status !== 0
const missing = []

console.log('\nbrowser-pass summary')
console.log(`  evidence: ${OUT}`)
console.log('')

for (const page of EXPECTED_PAGES) {
  const data = evidenceByPage.get(page)
  if (!data) {
    missing.push(page)
    failed = true
    console.log(`  ${page.padEnd(22)} MISSING evidence (spec did not record)`)
    continue
  }

  const {
    consoleErrors = [],
    pageErrors = [],
    failedRequests = [],
    rendered: renderedFlag = false
  } = data
  const rendered = renderedFlag === true

  if (!rendered || pageErrors.length > 0) {
    failed = true
  }

  const status = rendered ? 'rendered' : 'NOT RENDERED'
  const counters = `console:${consoleErrors.length} pageerror:${pageErrors.length} http>=400:${failedRequests.length}`
  console.log(`  ${page.padEnd(22)} ${status.padEnd(13)} ${counters}`)

  for (const error of consoleErrors.slice(0, 3)) {
    console.error(`      [console.error] ${error}`)
  }
  for (const error of pageErrors.slice(0, 3)) {
    console.error(`      [pageerror] ${error}`)
  }
  for (const request of failedRequests.slice(0, 3)) {
    console.error(`      [http ${request.status ?? 'failed'}] ${request.url}`)
  }
}

if (missing.length > 0) {
  console.error(`\n  no evidence for: ${missing.join(', ')}`)
}

console.log('')
if (failed) {
  console.error('browser-pass: FAIL (a page did not render or a pageerror was raised)')
  process.exit(1)
}
console.log('browser-pass: PASS (every page rendered, no pageerror)')
