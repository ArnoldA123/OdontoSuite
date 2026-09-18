#!/usr/bin/env node
/*
 * probe-api.mjs — axis A3 of plan #12.
 * Static cross of routes/api.php (method+path) against SPA consumption
 * sites in resources/js (useApi verbs and aliases, raw fetch with
 * method detection). Live HTTP validation against a running API is
 * out of scope by design; this probe proves static reachability only.
 *
 * Usage:
 *   node scripts/audit/probe-api.mjs [--fail-on-mismatch] [--json] [--out=dir]
 *
 * Exit 0 unless --fail-on-mismatch and unaccepted unmatched routes remain.
 * Accepted mismatches live in `<out>/acceptances.json` as
 * `{ "METHOD path": "written reason" }`.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, statSync } from 'node:fs'
import { resolve, dirname, join } from 'node:path'
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
  console.error('Usage: node scripts/audit/probe-api.mjs [--fail-on-mismatch] [--json] [--out=dir]')
  process.exit(2)
}
const OUT = resolve(ROOT, String(args.out ?? '.atl/qa-evidence/audit/api'))
const read = p => readFileSync(resolve(ROOT, p), 'utf8')

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
  const prefix = () => stack.map(f => f.prefix).filter(Boolean).join('/')
  const full = uri => [prefix(), uri.replace(/^\/+/, '')].filter(Boolean).join('/')
  const lines = src.split('\n')
  lines.forEach((raw, i) => {
    const line = i + 1
    const grp = raw.match(/Route::((?:prefix\([^)]+\)->|middleware\([^)]+\)->)*)group\(\s*function/)
    if (grp) {
      const chain = grp[1]
      const pm = chain.match(/prefix\(['"]([^'"]+)['"]\)/)
      stack.push({ prefix: pm ? pm[1].replace(/^\/+|\/+$/g, '') : '', line })
      return
    }
    if (/^\s*\}\);?\s*(?:\/\/.*)?$/.test(raw) && stack.length > 0) {
      stack.pop()
      return
    }
    const single = raw.match(/Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/)
    if (single) {
      routes.push({ method: single[1].toUpperCase(), path: full(single[2]), line })
      return
    }
    const res = raw.match(/Route::(?:middleware\([^)]+\)->)?apiResource\(\s*['"]([^'"]+)['"]/)
    if (res) {
      for (const [m, suffix] of RESOURCE_METHODS)
        routes.push({ method: m, path: full(res[1] + suffix), line })
    }
  })
  return routes
}

const norm = p => {
  const cut = p.split('?')[0].replace(/\$\{[^}`]*\}?/g, '{}').replace(/\{[^}]*\}/g, '{}')
  const segs = cut.split('/').map(s => (/[{}$]/.test(s) ? '{}' : s))
  return segs.join('/').replace(/\/+$/, '') || '/'
}

const segMatch = (a, b) => {
  const sa = a.split('/').filter(Boolean)
  const sb = b.split('/').filter(Boolean)
  return sa.length === sb.length && sa.every((s, i) => s === sb[i] || s === '{}' || sb[i] === '{}')
}

const isComment = line => /^\s*(\/\/|\*|<!--)/.test(line)

const VERB = { get: 'GET', post: 'POST', put: 'PUT', patch: 'PATCH', del: 'DELETE', delete: 'DELETE', apiGet: 'GET', apiPost: 'POST', apiPut: 'PUT', apiPatch: 'PATCH', apiDelete: 'DELETE' }

function walk(dir, out = []) {
  for (const e of readdirSync(dir)) {
    const p = join(dir, e)
    if (statSync(p).isDirectory()) walk(p, out)
    else if (/\.(js|vue)$/.test(e)) out.push(p)
  }
  return out
}

function parseConsumers() {
  const sites = []
  for (const file of walk(resolve(ROOT, 'resources/js'))) {
    const src = readFileSync(file, 'utf8')
    const rel = file.slice(ROOT.length + 1)
    const callRe = /(get|post|put|patch|del|delete|apiGet|apiPost|apiPut|apiPatch|apiDelete)\(\s*(['"`])\/api([^'"`\s?]*)\2?/g
    let m
    while ((m = callRe.exec(src)) !== null) {
      sites.push({ method: VERB[m[1]], path: norm(m[3].replace(/^\/+/, '')), file: rel, kind: m[1] })
    }
    const fetchRe = /fetch\(\s*(['"`])\/api([^'"`\s?]*)\1?/g
    while ((m = fetchRe.exec(src)) !== null) {
      const mm = src.slice(m.index, m.index + 600).match(/method:\s*['"]([A-Z]+)['"]/)
      sites.push({ method: mm ? mm[1] : 'ANY', path: norm(m[2].replace(/^\/+/, '')), file: rel, kind: 'fetch' })
    }
    const lines = src.split('\n')
    const litRe = /['"`]\/api([^'"`\s?]*?)['"`]/g
    lines.forEach((line, idx) => {
      if (isComment(line)) return
      for (const lm of line.matchAll(litRe)) {
        const path = norm(lm[1].replace(/^\/+/, ''))
        if (!sites.some(s => s.file === rel && s.path === path && s.method === 'ANY'))
          sites.push({ method: 'ANY', path, file: rel, kind: 'literal' })
      }
    })
    const tmplRe = /\$\{\w*baseurl\}\/api([^'"`\s?]*)/gi
    while ((m = tmplRe.exec(src)) !== null) {
      const before = src.slice(Math.max(0, m.index - 200), m.index)
      if (/fetch\(\s*[$\w]+\s*,/.test(before) || /fetch\(\s*[`'"]$/.test(before) || /const url =/.test(src.slice(Math.max(0, m.index - 120), m.index))) {
        const mm = src.slice(m.index, m.index + 800).match(/method:\s*['"]([A-Z]+)['"]/)
        if (!sites.some(s => s.file === rel && s.path === norm(m[1].replace(/^\/+/, '')))) {
          sites.push({ method: mm ? mm[1] : 'ANY', path: norm(m[1].replace(/^\/+/, '')), file: rel, kind: 'fetch-url' })
        }
      }
    }
  }
  return sites
}

const EXCLUSIONS = {
  'POST payments/webhooks/mercadopago': 'inbound webhook called by MercadoPago servers (HMAC in controller), never by the SPA',
  'POST broadcasting/auth': 'consumed by the Laravel Echo client config (resources/js/composables/useEcho.js authEndpoint), not via a useApi/fetch callsite',
  'POST login': 'root compat alias for POST auth/login; the SPA posts /api/auth/login only (resources/js/composables/useAuth.js)'
}

const routes = parseRoutes(read('routes/api.php'))
const consumers = parseConsumers()

const match = (route, site) =>
  segMatch(norm(route.path), site.path) && (site.method === 'ANY' || site.method === route.method)

const inventory = routes.map(r => {
  const key = `${r.method} ${r.path}`
  const hits = consumers.filter(s => match(r, s))
  const cls = key in EXCLUSIONS ? 'excluded' : hits.length > 0 ? 'matched' : 'unmatched'
  return { ...r, key, class: cls, reason: EXCLUSIONS[key] ?? '', consumers: hits.map(h => `${h.method} ${h.file}`) }
})

const orphans = consumers.filter(
  s => s.method !== 'ANY' && !routes.some(r => r.method === s.method && segMatch(norm(r.path), s.path))
)

let acceptances = {}
if (existsSync(resolve(OUT, 'acceptances.json')))
  acceptances = JSON.parse(readFileSync(resolve(OUT, 'acceptances.json'), 'utf8'))
const open = inventory.filter(r => r.class === 'unmatched' && !(r.key in acceptances))

mkdirSync(OUT, { recursive: true })
let revision = 'unknown'
try {
  revision = execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim()
} catch { /* detached tree or no git */ }
const rev = { revision, measured: new Date().toISOString().slice(0, 10) }
const counts = {
  routes: routes.length,
  matched: inventory.filter(r => r.class === 'matched').length,
  unmatched: inventory.filter(r => r.class === 'unmatched').length,
  excluded: inventory.filter(r => r.class === 'excluded').length,
  orphanConsumers: orphans.length
}

const md = [
  `# API route inventory — routes/api.php vs resources/js`,
  ``,
  `Revision ${rev.revision}, measured ${rev.measured}. Static only: no HTTP calls.`,
  ``,
  `Routes: ${counts.routes} | matched: ${counts.matched} | unmatched: ${counts.unmatched} | excluded: ${counts.excluded} | orphan consumers: ${counts.orphanConsumers}`,
  ``,
  `## Unmatched routes (${open.length} open)`,
  ``,
  ...open.map(r => `- \`${r.key}\` (routes/api.php:${r.line})`),
  ``,
  `## Excluded routes (written reason)`,
  ``,
  ...inventory.filter(r => r.class === 'excluded').map(r => `- \`${r.key}\`: ${r.reason}`),
  ``,
  `## Orphan consumers (SPA calls with no route)`,
  ``,
  ...orphans.map(s => `- \`${s.method} /api/${s.path}\` (${s.file})`),
  ``,
  `## Full inventory`,
  ``,
  `| Method | Path | Class | Consumers |`,
  `| --- | --- | --- | --- |`,
  ...inventory.map(r => `| ${r.method} | ${r.path} | ${r.class} | ${r.consumers.length} |`)
]
writeFileSync(resolve(OUT, 'inventory.md'), `${md.join('\n')}\n`)
writeFileSync(resolve(OUT, 'result.json'), JSON.stringify({ ...rev, counts, inventory, orphans }, null, 2))

if (args.json) console.log(JSON.stringify({ counts, open: open.map(r => r.key), orphans }, null, 2))
else {
  console.log(`Routes: ${counts.routes} | matched: ${counts.matched} | unmatched: ${counts.unmatched} | excluded: ${counts.excluded} | orphan consumers: ${counts.orphanConsumers}`)
  for (const r of open) console.log(`- UNMATCHED ${r.key} (routes/api.php:${r.line})`)
  for (const s of orphans) console.log(`- ORPHAN ${s.method} /api/${s.path} (${s.file})`)
}
if (args['fail-on-mismatch'] && open.length > 0) {
  console.error(`\nFAIL: ${open.length} unmatched route(s). Each needs a follow-up issue or a written acceptance in ${OUT}/acceptances.json.`)
  process.exit(1)
}
