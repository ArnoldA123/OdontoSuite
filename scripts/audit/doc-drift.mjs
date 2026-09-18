#!/usr/bin/env node
/**
 * doc-drift.mjs — guard for issue #21 (AGENTS.md numeric drift).
 *
 * Compares every numeric claim in AGENTS.md (§4, §6, §11) against the value
 * measured from source, and fails printing the diff. The §12 changelog is
 * excluded: it records history, so old numbers there are the point.
 *
 * Usage:
 *   node scripts/audit/doc-drift.mjs [--fail-on-divergence] [--no-fail] [--json]
 *
 * Exit 0 when every claim matches. Exit 1 on any divergence, unless --no-fail
 * (report only). --fail-on-divergence is accepted for parity with
 * probe-rbac.mjs; failing is already the default.
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const args = Object.fromEntries(
  process.argv.slice(2).map(a => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/)
    return m ? [m[1], m[2] ?? true] : ['_', a]
  })
)
if (args.h || args.help) {
  console.error('Usage: node scripts/audit/doc-drift.mjs [--fail-on-divergence] [--no-fail] [--json]')
  process.exit(2)
}
const JSON_OUT = !!args.json
const NO_FAIL = !!args['no-fail']

const read = p => readFileSync(resolve(ROOT, p), 'utf8')
const fullDocs = read('AGENTS.md')
const docs = fullDocs.split(/^## 12\. /m)[0]

const countDir = (dir, ext) =>
  readdirSync(resolve(ROOT, dir)).filter(f => f.endsWith(ext)).length

const countMatches = (src, re) => (src.match(re) ?? []).length

function apiRouteCount() {
  const raw = execSync('php artisan route:list --json', { cwd: ROOT, encoding: 'utf8' })
  const routes = JSON.parse(raw)
  return routes.filter(r => (r.uri ?? '').replace(/^\/+/, '').startsWith('api/')).length
}

const allOccurrences = (re) => {
  const out = []
  const g = new RegExp(re.source, re.flags.includes('g') ? re.flags : re.flags + 'g')
  let m
  while ((m = g.exec(docs)) !== null) out.push(Number(m[1]))
  return out
}

let measured
try {
  const seederSrc = read('database/seeders/DatabaseSeeder.php')
  const providerSrc = read('app/Providers/AppServiceProvider.php')
  const reminderSrc = read('app/Http/Controllers/Api/ReminderController.php')
  const reminderTplSrc = read('app/Http/Controllers/Api/ReminderTemplateController.php')
  measured = {
    seeders: countMatches(seederSrc, /::class\s*,/g),
    controllers:
      countDir('app/Http/Controllers/Api', '.php') +
      countDir('app/Http/Controllers/Api/Reports', '.php'),
    models: countDir('app/Models', '.php'),
    migrations: countDir('database/migrations', '.php'),
    deprecatedEvents: readdirSync(resolve(ROOT, 'app/Events'))
      .filter(f => f.endsWith('.php'))
      .filter(f => read(`app/Events/${f}`).includes('@deprecated')).length,
    listenerClasses: countDir('app/Listeners', '.php'),
    cableos: countMatches(providerSrc, /Event::listen\(/g),
    softDeletes: readdirSync(resolve(ROOT, 'app/Models'))
      .filter(f => f.endsWith('.php'))
      .filter(f => read(`app/Models/${f}`).includes('SoftDeletes')).length,
    apiRoutes: apiRouteCount(),
    reminderCrud:
      ['index', 'store', 'show', 'update', 'destroy'].every(m =>
        reminderSrc.includes(`function ${m}`) && reminderTplSrc.includes(`function ${m}`)) &&
      !reminderSrc.includes('abort(501)') && !reminderTplSrc.includes('abort(501)'),
    waitingListRemoved: !existsSync(resolve(ROOT, 'app/Http/Controllers/Api/WaitingListController.php')),
  }
} catch (err) {
  console.error(`ERROR: cannot measure source of truth: ${err.message}`)
  process.exit(1)
}

const rows = []
const numeric = (id, label, documented, expected, source) => {
  const ok = documented.length > 0 && documented.every(v => v === expected)
  rows.push({ id, label, documented, measured: expected, source, ok })
}
const structural = (id, label, pass, evidence) => {
  rows.push({ id, label, documented: null, measured: pass ? 'holds' : 'broken', source: evidence, ok: pass })
}

numeric('seeders', 'active seeders (§4 "N activos")',
  allOccurrences(/(\d+)\s+activos/), measured.seeders, 'grep -c "::class," database/seeders/DatabaseSeeder.php')
numeric('controllers', 'API controllers (§4/§6/§11 "N controllers API")',
  allOccurrences(/(\d+)\s+controllers?\s+API/), measured.controllers, 'ls app/Http/Controllers/Api/*.php + Api/Reports/*.php')
numeric('models', 'Eloquent models (§4/§6/§11 "N modelos")',
  allOccurrences(/(\d+)\s+modelos(?! con)/), measured.models, 'ls app/Models/*.php')
numeric('soft-deletes', 'models with SoftDeletes (§6 "N modelos con SoftDeletes")',
  allOccurrences(/(\d+)\s+modelos?\s+con\s+SoftDeletes/), measured.softDeletes, 'grep -rl SoftDeletes app/Models')
numeric('migrations', 'migrations (§4 "N migraciones")',
  allOccurrences(/(\d+)\s+migraciones/), measured.migrations, 'ls database/migrations/*.php')
numeric('deprecated-events', 'orphan @deprecated events (§6)',
  allOccurrences(/\*\*(\d+)\s+eventos?\s+`@deprecated`\s+huérfanos\*\*/), measured.deprecatedEvents, "grep -rl '@deprecated' app/Events | wc -l")
numeric('listener-classes', 'listener classes (§4/§6 "N listener classes")',
  allOccurrences(/(\d+)\s+listener\s+classes/), measured.listenerClasses, 'ls app/Listeners/*.php')
numeric('cableos', 'Event::listen wires (§4/§6 "N cableos")',
  allOccurrences(/(\d+)\s+cableos/), measured.cableos, 'grep -c "Event::listen" app/Providers/AppServiceProvider.php')
numeric('api-routes', 'API routes (§4 "api.php # N rutas")',
  allOccurrences(/api\.php\s*#\s*(\d+)\s+rutas/), measured.apiRoutes, 'php artisan route:list --json (uris api/…)')
structural('reminder-crud', 'ReminderController + ReminderTemplateController ship full CRUD, no 501',
  measured.reminderCrud && !/\\b501\\b/.test(docs),
  'public function index/store/show/update/destroy in both controllers; no abort(501)')
structural('waiting-list', 'WaitingListController absent; docs record removal, not a 501 stub',
  measured.waitingListRemoved && /WaitingListController/.test(docs) && !/WaitingListController.*501/.test(docs),
  'controller file absent (removed slice 04); StubsRemovedEndpointsTest guards the 404s')

const failed = rows.filter(r => !r.ok)

if (JSON_OUT) {
  console.log(JSON.stringify({ ok: failed.length === 0, checks: rows }, null, 2))
} else {
  for (const r of rows) {
    const doc = r.documented === null ? 'structural' : (r.documented.length ? r.documented.join(',') : 'NOT-FOUND')
    console.log(`${r.ok ? 'OK   ' : 'DRIFT'} ${r.id}: documented=${doc} measured=${r.measured} [${r.source}]`)
  }
  console.log(failed.length === 0
    ? `All ${rows.length} doc-drift checks hold.`
    : `\nFAIL: ${failed.length} drifted claim(s). Fix AGENTS.md §4/§6/§11 to the measured values above.`)
}
if (failed.length > 0 && !NO_FAIL) process.exit(1)
