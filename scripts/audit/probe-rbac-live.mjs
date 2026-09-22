#!/usr/bin/env node
/*
 * probe-rbac-live.mjs — live half of axis A4 (issues #25/#26, plan #12).
 *
 * The static half (`probe-rbac.mjs`) crosses routes/api.php, AGENTS.md and the
 * SPA navigation. This half crosses the `role:` middleware in routes/api.php
 * (expectation, derived from code) against what the API actually answers when
 * called with one bearer token per role (observation). A divergence is:
 *   - a role the middleware allows that receives 401/403, or
 *   - a role the middleware denies that receives 2xx.
 *
 * Only parameterless GET routes are swept: a route with `{...}` needs a
 * fixture id and is not fabricated here. Routes under the `cash.session`
 * middleware are still called, but their status is recorded as an
 * informational `cash-session-note` and never fails the run, because the gate
 * depends on whether a cash session happens to be open. Closing that gap (an
 * open -> call -> close cycle) is a documented follow-up, not part of this
 * probe.
 *
 * Usage:
 *   node scripts/audit/probe-rbac-live.mjs [--base=url] [--roles=a,b] [--fail-on-divergence] [--json] [--out=dir]
 *
 * Exit 0 when no unaccepted divergence remains. Accepted divergences live in
 * `<out>/acceptances.json` as `{ "id": "written reason" }`, where id is
 * `METHOD path [role]`. A green run with an empty acceptances file means the
 * middleware and the live API agree, not that the file was edited to agree:
 * widening one API group must move a cell.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const USAGE = `Usage: node scripts/audit/probe-rbac-live.mjs [--base=url] [--roles=a,b] [--fail-on-divergence] [--json] [--out=dir]

Derive the expected role set per parameterless GET api/* route from routes/api.php,
call each route with one bearer token per role, and report the divergence.

Options:
  --base=url           API origin (default http://localhost:8000)
  --roles=a,b          subset of callable roles (default all)
  --out=dir            evidence directory (default .atl/qa-evidence/audit/rbac)
  --fail-on-divergence exit 1 on unaccepted divergences
  --json               print the machine-readable summary to stdout
  --help               print this usage and exit 0

Acceptances: <out>/acceptances.json as { "id": "written reason" } with id
"METHOD path [role]" (e.g. { "GET api/users [odontologo]": "..." }).
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
const OUT = resolve(ROOT, String(args.out ?? '.atl/qa-evidence/audit/rbac'))
const BASE = String(args.base ?? 'http://localhost:8000').replace(/\/+$/, '')
const TIMEOUT_MS = 30000

// One demo user per role. `implantologo`, `tecnico_dental` and `asistente`
// exist in the product but have no seeded demo credential, so they cannot be
// called live here; that is a known limit of the probe, not a divergence.
const ROLE_USERS = [
  { role: 'administrador', username: 'elizabet' },
  { role: 'recepcionista', username: 'recepcionista_test' },
  { role: 'odontologo', username: 'odontologo_test' },
  { role: 'finanzas', username: 'milagros' }
]
const CALLABLE = ROLE_USERS.map(r => r.role)
const PASSWORD = 'password123'

const wanted = args.roles ? String(args.roles).split(',').map(s => s.trim()).filter(Boolean) : CALLABLE
for (const role of wanted) {
  if (!CALLABLE.includes(role)) {
    console.error(`Unknown or unavailable role ${role}. Callable roles (seeded demo users): ${CALLABLE.join(',')}`)
    process.exit(2)
  }
}
const roleUsers = ROLE_USERS.filter(r => wanted.includes(r.role))

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
  return { status: res.status, text }
}

async function login(username) {
  const res = await request('/api/login', { method: 'POST', body: { username, password: PASSWORD } })
  let json = null
  try {
    json = JSON.parse(res.text)
  } catch {
    /* non-json body */
  }
  if (res.status < 200 || res.status >= 300 || !json?.data?.token)
    throw new Error(`login failed for ${username}: HTTP ${res.status} ${res.text.slice(0, 200)}`)
  return json.data.token
}

const reachError = () =>
  `Cannot reach ${BASE}. Start the API with \`php artisan serve\` (or pass --base=<url>) and retry.`

// --- 1. Expectation: role middleware per GET route, parsed from routes/api.php
// Same stack model as the static probe (`probe-rbac.mjs`): the effective role
// set is the intersection of every `role:` seen on the enclosing groups and on
// the route line itself; no group means every authenticated role.

const RESOURCE_GET = '' // apiResource GET routes: index ({uri}) and show ({uri}/{id})

function parseApiRoutes(src) {
  const stack = []
  const routes = []
  const prefix = () => stack.map(f => f.prefix).filter(Boolean).join('/')
  const full = uri => [prefix(), uri.replace(/^\/+/, '')].filter(Boolean).join('/')
  const lineMods = chain => {
    const pm = chain.match(/prefix\((['"])(.*?)\1\)/)
    const middles = [...chain.matchAll(/middleware\((['"])(.*?)\1\)/g)].map(m => m[2])
    return { prefix: pm ? pm[2].replace(/^\/+|\/+$/g, '') : '', mods: middles.join(',') }
  }
  const rolesOf = mods => {
    const m = mods.match(/role:([^'")]+)/)
    return m ? m[1].split(',').map(s => s.trim()) : null
  }

  src.split('\n').forEach((raw, i) => {
    const line = i + 1
    const grp = raw.match(/Route::((?:(?:prefix|middleware)\([^)]*\)->)*)group\(\s*function/)
    if (grp) {
      const { prefix: p, mods } = lineMods(grp[1])
      stack.push({ prefix: p, roles: rolesOf(mods), cash: mods.includes('cash.session'), line })
      return
    }
    if (/^\s*\}\);?\s*(?:\/\/.*)?$/.test(raw) && stack.length > 0) {
      stack.pop()
      return
    }
    const def = raw.match(
      /Route::((?:(?:prefix|middleware)\([^)]*\)->)*)(get|post|put|patch|delete|apiResource)\(\s*(['"])([^'"]+)\3/
    )
    if (!def) return
    const { mods } = lineMods(def[1])
    const trail = [...raw.matchAll(/->middleware\((['"])(.*?)\1\)/g)].map(m => m[2]).join(',')
    const own = `${mods},${trail}`
    const sets = [...stack.filter(f => f.roles).map(f => f.roles), ...(rolesOf(own) ? [rolesOf(own)] : [])]
    const effective = sets.length === 0 ? null : sets.reduce((a, b) => a.filter(r => b.includes(r)))
    const cash = stack.some(f => f.cash) || own.includes('cash.session')
    if (def[2] === 'get')
      routes.push({ method: 'GET', uri: full(def[4]), roles: effective, cash, line })
    else if (def[2] === 'apiResource')
      routes.push({ method: 'GET', uri: full(def[4] + RESOURCE_GET), roles: effective, cash, line })
  })
  return routes
}

const allRoutes = parseApiRoutes(readFileSync(resolve(ROOT, 'routes/api.php'), 'utf8'))
const seen = new Set()
const targets = allRoutes
  .filter(r => r.method === 'GET')
  .filter(r => !seen.has(r.uri) && seen.add(r.uri))
  .map(r => ({ ...r, params: /\{[^}]+\}/.test(r.uri) }))
  .sort((a, b) => a.uri.localeCompare(b.uri))

if (targets.length === 0) {
  console.error('FAIL: routes/api.php parsed to zero GET routes; the parser and the file have drifted.')
  process.exit(1)
}

// --- 2. Observation: one token per role, each parameterless GET route --------

const roleTokens = []
for (const entry of roleUsers) {
  try {
    roleTokens.push({ ...entry, token: await login(entry.username) })
  } catch (err) {
    if (err instanceof ConnectionError) {
      console.error(reachError())
      process.exit(1)
    }
    console.error(`FAIL: ${err.message}`)
    process.exit(1)
  }
}

const allows = (rolesOrNull, role) => rolesOrNull === null || rolesOrNull.includes(role)
const fmtRoles = rolesOrNull => (rolesOrNull === null ? 'all-authenticated' : rolesOrNull.join(','))

const divergences = []
const notes = []
const routes = []

for (const target of targets) {
  const row = {
    method: target.method,
    path: `api/${target.uri}`,
    roles: target.roles,
    cash: target.cash,
    line: target.line,
    skipped: target.params,
    reason: target.params ? 'path carries a required {...} parameter; not fabricated here' : '',
    cells: []
  }
  if (target.params) {
    routes.push(row)
    continue
  }
  for (const role of roleTokens) {
    let status
    let note = ''
    try {
      const res = await request(`/${row.path}`, { token: role.token })
      status = res.status
    } catch (err) {
      if (err instanceof ConnectionError) {
        console.error(reachError())
        process.exit(1)
      }
      status = 'error'
      note = err.message
    }
    const observed = typeof status === 'number' && status >= 200 && status < 300 ? 'allow' : status === 401 || status === 403 ? 'deny' : 'unknown'
    const expected = allows(target.roles, role.role) ? 'allow' : 'deny'
    const isDivergence = (expected === 'allow' && observed === 'deny') || (expected === 'deny' && observed === 'allow')
    const cell = {
      role: role.role,
      status,
      observed,
      expected,
      class: isDivergence ? (target.cash ? 'cash-session-note' : 'divergence') : observed === 'unknown' ? 'note' : 'match',
      note
    }
    row.cells.push(cell)
    const id = `${row.method} ${row.path} [${role.role}]`
    if (cell.class === 'divergence')
      divergences.push({
        id,
        role: role.role,
        path: row.path,
        line: target.line,
        expected,
        observed,
        status,
        title:
          expected === 'allow'
            ? `${role.role} is allowed by role: middleware but gets HTTP ${status}`
            : `${role.role} is denied by role: middleware but gets HTTP ${status}`,
        evidence: `routes/api.php:${target.line} expected ${fmtRoles(target.roles)} | live GET /${row.path} -> ${status}`
      })
    else if (cell.class === 'cash-session-note')
      notes.push({ id, kind: 'cash-session-note', path: row.path, role: role.role, status, title: `${id}: divergence on a cash.session route, recorded as informational` })
    else if (observed === 'unknown')
      notes.push({ id, kind: 'unclassified-status', path: row.path, role: role.role, status, title: `${id}: HTTP ${status}${note ? ` (${note})` : ''}, neither allow nor deny` })
  }
  routes.push(row)
}

// --- 3. Accept, report, write ------------------------------------------------

const acceptancesPath = resolve(OUT, 'acceptances.json')
const acceptances = existsSync(acceptancesPath)
  ? JSON.parse(readFileSync(acceptancesPath, 'utf8'))
  : {}
const open = divergences.filter(d => !(d.id in acceptances))

const counts = {
  routes: routes.filter(r => !r.skipped).length,
  skipped: routes.filter(r => r.skipped).length,
  cells: routes.reduce((n, r) => n + r.cells.length, 0),
  matches: routes.reduce((n, r) => n + r.cells.filter(c => c.class === 'match').length, 0),
  divergences: divergences.length,
  cashNotes: notes.filter(n => n.kind === 'cash-session-note').length,
  unclassified: notes.filter(n => n.kind === 'unclassified-status').length,
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
    { ...rev, base: BASE, roles: roleTokens.map(r => r.role), counts, routes, divergences, notes, open },
    null,
    2
  )
)
if (!existsSync(acceptancesPath)) writeFileSync(acceptancesPath, '{}\n')

const md = [
  `# RBAC live matrix — expected (role: middleware) vs observed (live HTTP)`,
  ``,
  `Revision ${rev.revision}, measured ${rev.measured}. Base ${BASE}. Expectation parsed from routes/api.php.`,
  ``,
  `Routes: ${counts.routes} | cells: ${counts.cells} | matches: ${counts.matches} | divergences: ${counts.divergences} (${counts.open} open) | cash-session notes: ${counts.cashNotes} | unclassified statuses: ${counts.unclassified} | skipped-needs-fixture: ${counts.skipped}`,
  ``,
  `## Divergences (${counts.open} open, ${counts.divergences - counts.open} accepted)`,
  ``,
  ...(divergences.length === 0
    ? ['- none']
    : divergences.map(
        d => `- ${d.id in acceptances ? '[accepted] ' : ''}\`${d.id}\`: ${d.title}\n\n  Evidence: ${d.evidence}`
      )),
  ``,
  `## Informational notes (never fail the run)`,
  ``,
  ...(notes.length === 0
    ? ['- none']
    : notes.map(n => `- [${n.kind}] ${n.path} (${n.role}): HTTP ${n.status}`)),
  ``,
  `## Full matrix`,
  ``,
  `| Method | Path | Expected | cash.session | ${roleTokens.map(r => r.role).join(' | ')} |`,
  `| --- | --- | --- | --- |${roleTokens.map(() => ' --- |').join('')}`,
  ...routes.map(r => {
    const cells = roleTokens
      .map(role => {
        const c = r.cells.find(x => x.role === role.role)
        return c ? `${c.status}${c.class === 'match' ? '' : ` (${c.class})`}` : '—'
      })
      .join(' | ')
    return `| ${r.method} | ${r.path} | ${fmtRoles(r.roles)} | ${r.cash ? 'yes' : ''} | ${cells} |`
  })
]
writeFileSync(resolve(OUT, 'matrix.md'), `${md.join('\n')}\n`)

if (args.json)
  console.log(
    JSON.stringify(
      { ...rev, base: BASE, counts, open: open.map(d => ({ id: d.id, title: d.title })) },
      null,
      2
    )
  )
else {
  console.log(
    `Routes: ${counts.routes} | cells: ${counts.cells} | matches: ${counts.matches} | divergences: ${counts.divergences} (${counts.open} open) | cash-session notes: ${counts.cashNotes} | unclassified statuses: ${counts.unclassified} | skipped-needs-fixture: ${counts.skipped}`
  )
  for (const d of divergences) {
    const tag = d.id in acceptances ? '[accepted] ' : ''
    console.log(`- ${tag}${d.id}: ${d.title}`)
  }
  for (const n of notes) console.log(`- [${n.kind}] ${n.path} (${n.role}): HTTP ${n.status}`)
}
if (args['fail-on-divergence'] && open.length > 0) {
  console.error(
    `\nFAIL: ${open.length} unaccepted divergence(s). Each needs a follow-up issue or a written acceptance in ${acceptancesPath}.`
  )
  process.exit(1)
}
