#!/usr/bin/env node
/*
 * probe-api-live.mjs — live half of axis A3 (issues #25/#26, plan #12).
 *
 * The static half (`probe-api.mjs`) proves that every route in routes/api.php
 * has a SPA consumer. This half proves something the static one cannot: that
 * the route actually answers. It enumerates every parameterless GET `api/*`
 * route with `php artisan route:list --json` and calls it once per role token,
 * recording the HTTP status and the top-level JSON keys (or `non-json`).
 *
 * Routes with a required parameter (`{...}`) are never guessed: they are
 * recorded as `skipped-needs-fixture` with a written reason. Fabricating ids
 * would turn a coverage gap into a false green.
 *
 * Per-route classes:
 *   ok                   2xx in at least one role
 *   auth-gated           only 401/403 across the swept roles
 *   server-error         any 5xx observed
 *   skipped-needs-fixture path carries a `{...}` parameter
 *   unclassified         any other status (3xx, 404, 422, 429, ...)
 *
 * Usage:
 *   node scripts/audit/probe-api-live.mjs [--base=url] [--fail-on-mismatch] [--json] [--out=dir]
 *
 * Exit 0 unless --fail-on-mismatch and a non-accepted `server-error` route or
 * unclassified cell remains. Accepted failures live in `<out>/acceptances.json`
 * as `{ "METHOD path": "written reason" }`, where path is the route:list uri
 * (e.g. `{ "GET api/patients": "..." }`).
 */
import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const USAGE = `Usage: node scripts/audit/probe-api-live.mjs [--base=url] [--fail-on-mismatch] [--json] [--out=dir]

Live GET sweep of every parameterless api/* route with one bearer token per role.

Options:
  --base=url          API origin (default http://localhost:8000)
  --out=dir           evidence directory (default .atl/qa-evidence/audit/api-contracts)
  --fail-on-mismatch  exit 1 on server-error routes or unclassified cells
  --json              print the machine-readable summary to stdout
  --help              print this usage and exit 0

Acceptances: <out>/acceptances.json as { "METHOD path": "written reason" },
keyed by the route:list uri (e.g. { "GET api/patients": "..." }).
`

const args = Object.fromEntries(
  process.argv.slice(2).map(a => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/)
    return m ? [m[1], m[2] ?? true] : ['_', a]
  })
)
if (args.h || args.help) {
  console.log(USAGE)
  process.exit(0)
}
const OUT = resolve(ROOT, String(args.out ?? '.atl/qa-evidence/audit/api-contracts'))
const BASE = String(args.base ?? 'http://localhost:8000').replace(/\/+$/, '')
const TIMEOUT_MS = 30000

// One demo user per role; the login throttle is per ip+username, so logging in
// once per role and reusing the token is both cheaper and safer.
const ROLE_USERS = [
  { role: 'administrador', username: 'elizabet' },
  { role: 'recepcionista', username: 'recepcionista_test' },
  { role: 'odontologo', username: 'odontologo_test' },
  { role: 'finanzas', username: 'milagros' }
]
const PASSWORD = 'password123'

class ConnectionError extends Error {}

async function request(path, { method = 'GET', token, body } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`
  let res
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: AbortSignal.timeout(TIMEOUT_MS)
    })
  } catch (err) {
    if (err?.cause?.code === 'ECONNREFUSED' || /ECONNREFUSED/.test(String(err?.cause?.message ?? '')))
      throw new ConnectionError(`cannot connect to ${BASE}`)
    if (err?.name === 'TimeoutError' || err?.name === 'AbortError') throw new Error('timeout')
    throw err
  }
  const text = await res.text()
  let json = null
  try {
    json = JSON.parse(text)
  } catch {
    /* non-json body */
  }
  return { status: res.status, text, json, contentType: res.headers.get('content-type') ?? '' }
}

async function login(username) {
  const res = await request('/api/login', { method: 'POST', body: { username, password: PASSWORD } })
  if (res.status < 200 || res.status >= 300 || !res.json?.data?.token)
    throw new Error(`login failed for ${username}: HTTP ${res.status} ${res.text.slice(0, 200)}`)
  return res.json.data.token
}

const topKeysOf = body => {
  if (body.json === null) return 'non-json'
  if (Array.isArray(body.json)) return `array[${body.json.length}]`
  if (body.json && typeof body.json === 'object') {
    const keys = Object.keys(body.json).sort()
    return keys.length === 0 ? '{}' : keys.join(',')
  }
  return typeof body.json
}

const cellClass = status => {
  if (typeof status !== 'number') return 'unclassified'
  if (status >= 200 && status < 300) return 'ok'
  if (status === 401 || status === 403) return 'denied'
  if (status >= 500) return 'server-error'
  return 'unclassified'
}

function classify(cells) {
  if (cells.some(c => c.class === 'server-error')) return 'server-error'
  if (cells.some(c => c.class === 'ok')) return 'ok'
  if (cells.length > 0 && cells.every(c => c.class === 'denied')) return 'auth-gated'
  return 'unclassified'
}

const reachError = () =>
  `Cannot reach ${BASE}. Start the API with \`php artisan serve\` (or pass --base=<url>) and retry.`

// --- 1. Tokens: one login per role ------------------------------------------

const roles = []
for (const entry of ROLE_USERS) {
  try {
    roles.push({ ...entry, token: await login(entry.username) })
  } catch (err) {
    if (err instanceof ConnectionError) {
      console.error(reachError())
      process.exit(1)
    }
    console.error(`FAIL: ${err.message}`)
    process.exit(1)
  }
}

// --- 2. Route inventory (live routing table, not a regex) -------------------

let routeList
try {
  routeList = JSON.parse(
    execSync('php artisan route:list --json', { cwd: ROOT, encoding: 'utf8', maxBuffer: 1 << 28 })
  )
} catch (err) {
  console.error(`FAIL: could not enumerate routes with \`php artisan route:list --json\` in ${ROOT}: ${err.message}`)
  process.exit(1)
}
const seen = new Set()
const targets = routeList
  .filter(r => r.uri.startsWith('api/') && r.method.split('|').includes('GET'))
  .map(r => ({ method: 'GET', path: r.uri, params: /\{[^}]+\}/.test(r.uri) }))
  .filter(t => !seen.has(t.path) && seen.add(t.path))
  .sort((a, b) => a.path.localeCompare(b.path))

// --- 3. Sweep every target with every role token ----------------------------

const routes = []
for (const target of targets) {
  if (target.params) {
    routes.push({
      method: target.method,
      path: target.path,
      class: 'skipped-needs-fixture',
      reason: 'path carries a required {...} parameter; the live half does not fabricate fixture ids',
      cells: []
    })
    continue
  }
  const cells = []
  for (const role of roles) {
    let cell
    try {
      const res = await request(`/${target.path}`, { token: role.token })
      const cls = cellClass(res.status)
      cell = {
        role: role.role,
        status: res.status,
        class: cls,
        topKeys: topKeysOf(res),
        contentType: res.contentType
      }
    } catch (err) {
      if (err instanceof ConnectionError) {
        console.error(reachError())
        process.exit(1)
      }
      cell = { role: role.role, status: 'error', class: 'unclassified', note: err.message, topKeys: null, contentType: '' }
    }
    cells.push(cell)
  }
  routes.push({ method: target.method, path: target.path, class: classify(cells), cells })
}

// --- 4. Classify, accept, write ---------------------------------------------

const acceptancesPath = resolve(OUT, 'acceptances.json')
const acceptances = existsSync(acceptancesPath)
  ? JSON.parse(readFileSync(acceptancesPath, 'utf8'))
  : {}

const failures = routes.filter(
  r => r.class === 'server-error' || r.cells.some(c => c.class === 'server-error' || c.class === 'unclassified')
)
const open = failures.filter(r => !(`${r.method} ${r.path}` in acceptances))

const byClass = key => routes.filter(r => r.class === key).length
const cellCounts = {}
for (const r of routes)
  for (const c of r.cells) cellCounts[c.class] = (cellCounts[c.class] ?? 0) + 1
const counts = {
  routes: routes.length,
  ok: byClass('ok'),
  authGated: byClass('auth-gated'),
  serverError: byClass('server-error'),
  skipped: byClass('skipped-needs-fixture'),
  unclassified: byClass('unclassified'),
  cells: routes.reduce((n, r) => n + r.cells.length, 0),
  cellClasses: cellCounts,
  failures: failures.length,
  open: open.length
}

mkdirSync(OUT, { recursive: true })
let revision = 'unknown'
try {
  revision = execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim()
} catch {
  /* detached tree or no git: artifact records unknown */
}
const rev = { revision, measured: new Date().toISOString().slice(0, 10) }

writeFileSync(
  resolve(OUT, 'matrix.json'),
  JSON.stringify(
    { ...rev, base: BASE, roles: roles.map(r => r.role), counts, routes, failures, open },
    null,
    2
  )
)
if (!existsSync(acceptancesPath))
  writeFileSync(acceptancesPath, '{}\n')

const md = [
  `# API live matrix — GET api/* with one token per role`,
  ``,
  `Revision ${rev.revision}, measured ${rev.measured}. Base ${BASE}. Live HTTP: this is the observed half of A3.`,
  ``,
  `Routes: ${counts.routes} | ok: ${counts.ok} | auth-gated: ${counts.authGated} | server-error: ${counts.serverError} | skipped-needs-fixture: ${counts.skipped} | unclassified: ${counts.unclassified}`,
  ``,
  `Cells: ${counts.cells} | ${Object.entries(cellCounts).map(([k, v]) => `${k}: ${v}`).join(' | ')}`,
  ``,
  `## Failures (${open.length} open, ${failures.length - open.length} accepted)`,
  ``,
  ...(failures.length === 0
    ? ['- none']
    : failures.map(
        r =>
          `- ${`${r.method} ${r.path}` in acceptances ? '[accepted] ' : ''}\`${r.method} ${r.path}\` (${r.class}) — ` +
          r.cells.filter(c => c.class === 'server-error' || c.class === 'unclassified').map(c => `${c.role}: ${c.status}`).join(', ')
      )),
  ``,
  `## Skipped (needs fixture)`,
  ``,
  ...(counts.skipped === 0
    ? ['- none']
    : routes.filter(r => r.class === 'skipped-needs-fixture').map(r => `- \`${r.method} ${r.path}\`: ${r.reason}`)),
  ``,
  `## Full matrix`,
  ``,
  `| Method | Path | Class | ${roles.map(r => r.role).join(' | ')} |`,
  `| --- | --- | --- |${roles.map(() => ' --- |').join('')}`,
  ...routes.map(
    r =>
      `| ${r.method} | ${r.path} | ${r.class} | ` +
      roles
        .map(role => {
          const c = r.cells.find(x => x.role === role.role)
          return c ? `${c.status}${c.class === 'ok' ? '' : ` (${c.class})`}` : '—'
        })
        .join(' | ') +
      ' |'
  )
]
writeFileSync(resolve(OUT, 'matrix.md'), `${md.join('\n')}\n`)

if (args.json)
  console.log(
    JSON.stringify(
      { ...rev, base: BASE, counts, open: open.map(r => ({ key: `${r.method} ${r.path}`, class: r.class })) },
      null,
      2
    )
  )
else {
  console.log(
    `Routes: ${counts.routes} | ok: ${counts.ok} | auth-gated: ${counts.authGated} | server-error: ${counts.serverError} | skipped-needs-fixture: ${counts.skipped} | unclassified: ${counts.unclassified}`
  )
  console.log(`Cells: ${counts.cells} | ${Object.entries(cellCounts).map(([k, v]) => `${k}: ${v}`).join(' | ')}`)
  for (const r of failures) {
    const tag = `${r.method} ${r.path}` in acceptances ? '[accepted] ' : ''
    console.log(`- ${tag}${r.class.toUpperCase()} ${r.method} ${r.path}`)
    for (const c of r.cells)
      if (c.class === 'server-error' || c.class === 'unclassified')
        console.log(`    ${c.role}: ${c.status}${c.note ? ` (${c.note})` : ''}`)
  }
}
if (args['fail-on-mismatch'] && open.length > 0) {
  console.error(
    `\nFAIL: ${open.length} route(s) with server errors or unclassified cells. Each needs a follow-up issue or a written acceptance in ${acceptancesPath}.`
  )
  process.exit(1)
}
