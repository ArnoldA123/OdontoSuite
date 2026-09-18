#!/usr/bin/env node
/**
 * probe-rbac.mjs — axis A4 of plan #12
 * (docs/mejoras/12-programa-auditoria-integral-2026-08.md).
 *
 * Compares the three sources nobody crosses: the `role:` middleware in
 * `routes/api.php` (expectation, derived from code), the roles table in
 * `AGENTS.md` §5 plus the permission matrix in `CREDENTIALS.md` (documented),
 * and what the frontend shows (`AppLayout.navigation`, no `meta.roles`).
 * Every divergence is a row with an id, evidence and a proposed owner.
 *
 * Usage:
 *   node scripts/audit/probe-rbac.mjs [--roles=a,b] [--fail-on-divergence] [--json] [--out=dir]
 *
 * Exit 0 when no unaccepted divergence remains. Accepted divergences live in
 * `<out>/acceptances.json` as `{ "id": "written reason" }`. A green run with
 * an empty acceptances file means the three sources agree, not that the file
 * was edited to agree: widening one API group must shrink the diff.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')
const ROLES = [
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
]

const args = Object.fromEntries(
  process.argv.slice(2).map(a => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/)
    return m ? [m[1], m[2] ?? true] : ['_', a]
  })
)
if (args.h || args.help) {
  console.error(
    'Usage: node scripts/audit/probe-rbac.mjs [--roles=a,b] [--fail-on-divergence] [--json] [--out=dir]'
  )
  process.exit(2)
}
const roleFilter = args.roles ? String(args.roles).split(',').map(s => s.trim()) : ROLES
for (const r of roleFilter) {
  if (!ROLES.includes(r)) {
    console.error(`Unknown role ${r}. Known: ${ROLES.join(',')}`)
    process.exit(2)
  }
}
const OUT = resolve(ROOT, String(args.out ?? '.atl/qa-evidence/audit/rbac'))
const read = p => readFileSync(resolve(ROOT, p), 'utf8')

const allows = (rolesOrNull, role) => rolesOrNull === null || rolesOrNull.includes(role)
const fmtRoles = rolesOrNull => (rolesOrNull === null ? 'all-authenticated' : rolesOrNull.join(','))

// --- 1. Expectation: role middleware per route, parsed from routes/api.php ---

const RESOURCE_METHODS = [
  ['GET', ''],
  ['POST', ''],
  ['GET', '/{id}'],
  ['PUT', '/{id}'],
  ['PATCH', '/{id}'],
  ['DELETE', '/{id}']
]

function parseRoutes(src) {
  const stack = []
  const routes = []
  const pushRoute = (method, uri, line, extra) => {
    const roleSets = stack.filter(f => f.roles).map(f => f.roles)
    if (extra?.roles) roleSets.push(extra.roles)
    const effective =
      roleSets.length === 0
        ? null
        : roleSets.reduce((a, b) => a.filter(r => b.includes(r)))
    routes.push({
      method,
      uri: uri.replace(/^\/+/, ''),
      roles: effective,
      cash: stack.some(f => f.cash) || !!extra?.cash,
      line
    })
  }
  const lines = src.split('\n')
  lines.forEach((raw, i) => {
    const line = i + 1
    const groupOpen = raw.match(/Route::(?:middleware\(([^)]+)\)->)?group\(\s*function/)
    if (groupOpen) {
      const mods = groupOpen[1] ?? ''
      const roleM = mods.match(/['"]role:([^'"]+)['"]/)
      stack.push({
        roles: roleM ? roleM[1].split(',').map(s => s.trim()) : null,
        cash: mods.includes('cash.session'),
        line
      })
      return
    }
    if (/^\s*\}\);?\s*(?:\/\/.*)?$/.test(raw) && stack.length > 0) {
      stack.pop()
      return
    }
    const single = raw.match(
      /Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/
    )
    if (single) {
      const extra = {}
      const rm = raw.match(/->middleware\(['"]role:([^'"]+)['"]\)/)
      if (rm) extra.roles = rm[1].split(',').map(s => s.trim())
      if (raw.includes('cash.session')) extra.cash = true
      pushRoute(single[1].toUpperCase(), single[2], line, extra)
      return
    }
    const res = raw.match(/Route::apiResource\(\s*['"]([^'"]+)['"]/)
    if (res) {
      const extra = {}
      const rm = raw.match(/->middleware\(['"]role:([^'"]+)['"]\)/)
      if (rm) extra.roles = rm[1].split(',').map(s => s.trim())
      if (raw.includes('cash.session')) extra.cash = true
      for (const [m, suffix] of RESOURCE_METHODS)
        pushRoute(m, res[1] + suffix, line, extra)
    }
  })
  return routes
}

// --- 2. Frontend: navigation roles + router guard fact ---

function parseNavigation(src) {
  const items = []
  const re = /name:\s*'([^']+)'[\s\S]*?to:\s*'([^']+)'[\s\S]*?roles:\s*\[([^\]]*)\]/g
  let m
  while ((m = re.exec(src)) !== null) {
    const roles = [...m[3].matchAll(/'([^']+)'/g)].map(x => x[1])
    items.push({ name: m[1], to: m[2], roles })
  }
  return items
}

// --- 3. Docs: AGENTS §5 + CREDENTIALS.md matrix ---

function parseAgentsTable(src) {
  const rows = []
  for (const line of src.split('\n')) {
    const m = line.match(/^\|\s*([^|]+?)\s*\|\s*(`[^`]+`)\s*\|\s*([^|]+?)\s*\|$/)
    if (m && !/Módulo/.test(m[1])) rows.push({ module: m[1].trim(), route: m[2].replace(/`/g, ''), rolesRaw: m[3].trim() })
  }
  return rows
}

function parseCredentialsMatrix(src) {
  const rows = []
  for (const line of src.split('\n')) {
    const cells = line.split('|').map(c => c.trim()).filter(c => c !== '')
    if (cells.length === 8 && (cells[1] === '✅' || cells[1] === '❌')) rows.push(cells)
  }
  return rows
}

// --- 4. Declared module correspondence (auditor-owned, versioned here) ---

const MODULES = [
  { to: '/dashboard', api: ['dashboard', 'reports'] },
  { to: '/calendar', api: ['appointments', 'dental-chairs', 'appointment-types', 'reminders', 'reminder-templates', 'audit-logs', 'products'], exclude: ['appointments/ready-to-bill', 'appointments/{appointment}/payment-preview', 'appointments/{appointment}/generate-quotation'] },
  { to: '/patients', api: ['patients'] },
  { to: '/professionals', api: ['users'] },
  { to: '/environments', api: ['dental-chairs'], note: 'no environments API: page consumes /api/dental-chairs' },
  { to: '/appointment-types', api: ['appointment-types'] },
  { to: '/settings/branches', api: ['branches'] },
  { to: '/settings/payment-methods', api: ['payment-methods'] },
  { to: '/procedure-catalog', api: ['procedure-catalog'], family: 'catalog' },
  { to: '/procedure-stats', api: ['admin/procedure-stats', 'admin/procedure-catalog'] },
  { to: '/my-procedures', api: ['procedure-catalog-favorites', 'procedure-catalog'], family: 'catalog' },
  { to: '/reception-procedures', api: ['procedure-catalog'], family: 'catalog' },
  { to: '/business-intelligence', api: ['reports'] },
  { to: '/cash-register', api: ['transactions', 'cash-movements', 'cash-register-sessions', 'cash-reports', 'cash-register', 'pending-payments', 'payments/mercadopago', 'appointments'] },
  { to: '/treatment-plans', api: ['treatment-plans'] },
  { to: '/quotations', api: ['quotations'] },
  { to: '/medical-records', api: ['medical-records'] },
  { to: '/specialty-records', api: ['specialty-records'] },
  { to: '/ai-analysis', api: ['ai-analysis'] }
]

const matchPrefix = (uri, prefix) =>
  uri === prefix || uri.startsWith(`${prefix}/`) || uri.startsWith(`${prefix}{`) 

const matchModule = (route, mod) =>
  mod.api.some(p => matchPrefix(route.uri, p)) &&
  !(mod.exclude ?? []).some(p => matchPrefix(route.uri, p))

const unionRoles = routes => {
  if (routes.some(r => r.roles === null)) return null
  return [...new Set(routes.flatMap(r => r.roles))]
}

const splitRW = routes => ({
  read: unionRoles(routes.filter(r => r.method === 'GET')),
  write: unionRoles(routes.filter(r => r.method !== 'GET'))
})

// --- 5. Run ---

const apiSrc = read('routes/api.php')
const routes = parseRoutes(apiSrc)
const nav = parseNavigation(read('resources/js/components/layout/AppLayout.vue'))
const agents = parseAgentsTable(read('AGENTS.md'))
const creds = parseCredentialsMatrix(read('CREDENTIALS.md'))
const routerSrc = read('resources/js/app.js')
const metaRoles = (routerSrc.match(/meta:\s*\{[^}]*roles[^}]*\}/g) ?? []).length
const throttleSrc = read('app/Http/Middleware/ThrottleLoginAttempts.php')
const cashSrc = read('app/Http/Middleware/RequireActiveCashSession.php')
const authTestSrc = read('tests/Feature/Api/AuthTest.php')

const divergences = []
const div = (id, severity, title, evidence, proposal) =>
  divergences.push({ id, severity, title, evidence, proposal })

const navByTo = Object.fromEntries(nav.map(n => [n.to, n]))
const agentsByRoute = Object.fromEntries(agents.map(a => [a.route, a]))

for (const mod of MODULES) {
  const endpoints = routes.filter(r => matchModule(r, mod))
  if (endpoints.length === 0) {
    div(`NAV-${mod.to}-noapi`, 'info', `${mod.to} matches no API prefix`, `prefixes: ${mod.api.join(', ')}`, 'follow-up: confirm dead page or fix mapping')
    continue
  }
  const { read: readRoles, write: writeRoles } = splitRW(endpoints)
  const navItem = navByTo[mod.to]
  if (!navItem) {
    div(`NAV-${mod.to}-missing`, 'media', `${mod.to} has API surface but no nav entry`, `prefixes: ${mod.api.join(', ')}`, 'follow-up: reachable-but-hidden endpoint class')
    continue
  }
  const navRoles = navItem.roles.includes('all') ? null : navItem.roles
  for (const role of roleFilter) {
    if (navRoles !== null && !allows(navRoles, role)) continue
    const deniedRead = readRoles !== null && !readRoles.includes(role)
    const deniedWrite = writeRoles !== null && !writeRoles.includes(role)
    if (deniedRead && deniedWrite)
      div(`NAV-${mod.to}-denied-${role}`, 'media', `nav shows ${mod.to} to ${role}, API denies all methods`, `nav: ${navItem.roles.join(',')} | api read: ${fmtRoles(readRoles)} write: ${fmtRoles(writeRoles)}`, 'follow-up: UI promise the API rejects (403)')
  }
  const familyShown = role =>
    !!mod.family &&
    MODULES.filter(m => m.family === mod.family).some(m => navByTo[m.to]?.roles.includes(role))
  for (const role of roleFilter) {
    const shown = navRoles === null || navRoles.includes(role)
    if (!shown && !familyShown(role) && allows(readRoles, role) && allows(writeRoles, role))
      div(`API-${mod.to}-hidden-${role}`, 'media', `API allows ${role} on ${mod.to}, nav hides the page`, `api read: ${fmtRoles(readRoles)} write: ${fmtRoles(writeRoles)}`, 'follow-up: reachable-but-undocumented capability')
  }
  const doc = agentsByRoute[mod.to]
  if (!doc)
    div(`DOC-${mod.to}-missing`, 'baja', `AGENTS.md §5 has no row for ${mod.to}`, `nav: ${navItem.name}`, 'follow-up: document or remove the page')
}

// CREDENTIALS.md functional claims against the API (row index, 0-based).
const credByAction = Object.fromEntries(creds.map(c => [c[0], c]))
const checkCred = (id, action, roleIdx, role, uriPrefix, method) => {
  const row = credByAction[action]
  if (!row) return
  const documented = row[roleIdx] === '✅' ? 'allow' : 'deny'
  const eps = routes.filter(r => matchPrefix(r.uri, uriPrefix) && (method === 'ANY' || r.method === method))
  const actual = eps.length > 0 && eps.every(r => allows(r.roles, role)) ? 'allow' : 'deny'
  if (documented !== actual)
    div(id, 'media', `CREDENTIALS.md "${action}" says ${documented} for ${role}, API ${actual}`, `doc: ${row.slice(1).join(' ')} | ${method} ${uriPrefix}: ${fmtRoles(unionRoles(eps))}`, 'follow-up: doc promise vs middleware class')
}
checkCred('CRED-cash-recep', 'Abrir/cerrar caja', 2, 'recepcionista', 'cash-register-sessions', 'POST')
checkCred('CRED-tx-recep', 'Registrar transacciones', 2, 'recepcionista', 'transactions', 'POST')
checkCred('CRED-quot-recep', 'Ver presupuestos', 2, 'recepcionista', 'quotations', 'GET')
checkCred('CRED-patcreate-odonto', 'Crear pacientes', 3, 'odontologo', 'patients', 'POST')
checkCred('CRED-patedit-fin', 'Editar pacientes', 7, 'finanzas', 'patients', 'PUT')
checkCred('CRED-reports-recep', 'Ver reportes', 2, 'recepcionista', 'reports', 'GET')
checkCred('CRED-reports-odonto', 'Ver reportes', 3, 'odontologo', 'reports', 'GET')

// cash.session structural gaps.
const cashDeletes = routes.filter(r => r.cash && r.method === 'DELETE')
const cashGateMethods = cashSrc.match(/in_array\(\$request->method\(\),\s*\[([^\]]*)\]/s)?.[1] ?? ''
if (cashDeletes.length > 0 && /POST/.test(cashGateMethods) && !/DELETE/.test(cashGateMethods))
  div('CASH-delete-bypass', 'media', 'cash.session gates POST/PUT/PATCH only: DELETE bypasses the active-session check', cashDeletes.map(r => `${r.method} ${r.uri} (routes/api.php:${r.line})`).join(' | '), 'follow-up: extend gate or accept DELETE-without-session in writing')

// Auth throttle vs lockout (owner-delegated policy question on #26).
const rateLimit = throttleSrc.match(/attempts >= (\d+)/)?.[1]
const lockoutAt = throttleSrc.match(/failedCount >= (\d+)/)?.[1]
const rapidLoop = (authTestSrc.match(/for \(\$i = 1; \$i <= (\d+); \$i\+\+\)/g) ?? []).map(s => s.match(/<= (\d+)/)[1])
const resetsBucket = /Cache::(forget|flush)/.test(
  authTestSrc.split('test_login_blocked_after_five_failed_attempts')[1]?.split('public function')[0] ?? ''
)
if (rateLimit && lockoutAt && Number(lockoutAt) > Number(rateLimit) && rapidLoop.includes(lockoutAt) && !resetsBucket)
  div('AUTH-throttle-shadows-lockout', 'media', `3/min throttle shadows the 5-failure lockout: 6 rapid posts never set blockedKey`, `ThrottleLoginAttempts.php: attempts>=${rateLimit} returns before $next; failedCount>=${lockoutAt} needs ${lockoutAt} $next calls | AuthTest loops ${rapidLoop.join(',')} rapid posts with no bucket reset`, 'decision WU-3: keep middleware, isolate lockout layer in test')

if (metaRoles > 0)
  div('ROUTER-meta-roles', 'info', 'router carries meta.roles after all', `${metaRoles} occurrence(s)`, 'follow-up: re-audit assuming guarded routes')
else divergences.push({ id: 'ROUTER-no-meta-roles', severity: 'info', title: 'router has zero meta.roles: every SPA route is requireAuth-only', evidence: 'resources/js/app.js + resources/js/router/', proposal: 'accepted fact, recorded as the baseline' })

let acceptances = {}
if (existsSync(resolve(OUT, 'acceptances.json')))
  acceptances = JSON.parse(readFileSync(resolve(OUT, 'acceptances.json'), 'utf8'))
const open = divergences.filter(d => !(d.id in acceptances) && d.severity !== 'info')
const infos = divergences.filter(d => !open.includes(d))

mkdirSync(OUT, { recursive: true })
let revision = 'unknown'
try {
  revision = execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim()
} catch { /* detached tree or no git: artifact records unknown */ }
const rev = { revision, measured: new Date().toISOString().slice(0, 10) }
const matrixLines = [
  `# RBAC matrix — expected (code) vs observed (docs + frontend)`,
  ``,
  `Revision ${rev.revision}, measured ${rev.measured}. Expectation derived from \`role:\` middleware per route in routes/api.php.`,
  ``,
  `| Frontend route | Nav roles | API read | API write | AGENTS §5 |`,
  `| --- | --- | --- | --- | --- |`
]
for (const mod of MODULES) {
  const endpoints = routes.filter(r => matchModule(r, mod))
  const { read: rr, write: ww } = splitRW(endpoints)
  const n = navByTo[mod.to]
  matrixLines.push(`| ${mod.to} | ${n ? n.roles.join(',') : 'NO-NAV'} | ${fmtRoles(rr)} | ${fmtRoles(ww)} | ${agentsByRoute[mod.to]?.rolesRaw ?? 'NO-ROW'} |`)
}
writeFileSync(resolve(OUT, 'matrix.md'), `${matrixLines.join('\n')}\n`)
writeFileSync(
  resolve(OUT, 'divergences.md'),
  `# Divergences (${open.length} open, ${infos.length} informational)\n\n` +
    divergences.map(d => `## ${d.id} [${d.severity}]${d.id in acceptances ? ' (accepted)' : ''}\n${d.title}\n\nEvidence: ${d.evidence}\n\nProposal: ${d.proposal}\n`).join('\n')
)
writeFileSync(resolve(OUT, 'result.json'), JSON.stringify({ ...rev, open: open.length, divergences }, null, 2))

if (args.json) console.log(JSON.stringify({ open: open.length, divergences }, null, 2))
else {
  console.log(`Routes parsed: ${routes.length} | nav items: ${nav.length} | AGENTS rows: ${agents.length} | CRED rows: ${creds.length}`)
  console.log(`Divergences: ${open.length} open, ${infos.length} informational`)
  for (const d of divergences) console.log(`- [${d.severity}] ${d.id}: ${d.title}`)
}
if (args['fail-on-divergence'] && open.length > 0) {
  console.error(`\nFAIL: ${open.length} unaccepted divergence(s). Each needs a follow-up issue or a written acceptance in ${OUT}/acceptances.json.`)
  process.exit(1)
}
