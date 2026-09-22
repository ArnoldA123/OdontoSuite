#!/usr/bin/env node
/*
 * realtime-map.mjs — static half of axis A8 (issue #28, plan #12).
 *
 * Builds the wiring map `dispatch -> event -> channel -> consumer -> composable`
 * without touching the network:
 *   - per class in `app/Events/*.php`: class name, the channels returned by
 *     `broadcastOn` (with their Channel / PrivateChannel / PresenceChannel
 *     type) and the `broadcastAs` name;
 *   - the dispatch sites: every `event(new X(` under `app/` (file:line);
 *   - the consumers: every `channel('...')`, `privateChannel('...')`,
 *     `echo.private('...')` and `echo.leave('...')` under `resources/js`
 *     (file:line) plus the composable/page that contains it.
 *
 * Each event is classified on three orthogonal axes:
 *   - dispatched | no-dispatch   (has at least one `event(new X(` site)
 *   - consumed   | unconsumed    (at least one broadcast channel has a consumer)
 *   - private-needs-auth         (broadcasts on a Private/Presence channel)
 *
 * This is the cheap half. The live half (`probe-realtime.mjs`) proves that the
 * wiring actually delivers an event with two sessions; this map proves the
 * static shape the live probe is derived from.
 *
 * Usage:
 *   node scripts/audit/realtime-map.mjs [--json] [--out=dir]
 *
 * Exit 0 always (a static inventory is evidence, not a gate). A run that parses
 * zero events prints a warning to stderr: the parser and the tree have drifted.
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync } from 'node:fs'
import { resolve, dirname, join, basename } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const USAGE = `Usage: node scripts/audit/realtime-map.mjs [--json] [--out=dir]

Static map dispatch -> event -> channel -> consumer for the Reverb/WebSocket
surface (axis A8, issue #28).

Options:
  --out=dir  evidence directory (default .atl/qa-evidence/audit/realtime)
  --json     print the machine-readable map to stdout
  --help     print this usage and exit 0

Writes <out>/map.md (wiring table) and <out>/map.json (full detail).
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
const OUT = resolve(ROOT, String(args.out ?? '.atl/qa-evidence/audit/realtime'))
const read = p => readFileSync(resolve(ROOT, p), 'utf8')

// --- 1. Events: broadcastOn channels, broadcastAs ---------------------------

// The body of a method, up to the next `function` declaration.
function methodBody(src, name) {
  const start = src.indexOf(`function ${name}`)
  if (start === -1) return ''
  const next = src.indexOf('function ', start + name.length + 9)
  return src.slice(start, next === -1 ? src.length : next)
}

// First quoted literal of a channel expression, with the dynamic tail removed:
//   "cash-session.{$this->sessionId}" -> cash-session
//   'user.' . $this->user->id         -> user
//   'appointments'                    -> appointments
function channelName(expr) {
  const q = expr.match(/(['"])([\s\S]*?)\1/)
  const lit = q ? q[2] : expr
  return lit
    .replace(/\$\{[^}]*\}/g, '')
    .replace(/\{[^}]*\}/g, '')
    .replace(/[.\-]+$/, '')
    .trim()
}

function parseEvent(file) {
  const src = readFileSync(file, 'utf8')
  const rel = file.slice(ROOT.length + 1)
  const className = (src.match(/\bclass\s+(\w+)/) ?? [])[1] ?? basename(file, '.php')
  const onBody = methodBody(src, 'broadcastOn')
  const channels = []
  const re = /new\s+(Channel|PrivateChannel|PresenceChannel)\s*\(\s*([^()]*)\)/g
  let m
  while ((m = re.exec(onBody)) !== null) {
    const expr = m[2].trim()
    channels.push({ type: m[1], expr, name: channelName(expr) })
  }
  const asBody = methodBody(src, 'broadcastAs')
  const broadcastAs = (asBody.match(/return\s+(['"])([^'"]+)\1/) ?? [])[2] ?? null
  return { name: className, file: rel, broadcastAs, channels }
}

const eventsDir = resolve(ROOT, 'app/Events')
const events = readdirSync(eventsDir)
  .filter(f => f.endsWith('.php'))
  .sort()
  .map(f => parseEvent(join(eventsDir, f)))

// --- 2. Dispatch sites: `event(new X(` under app/ ----------------------------

function walk(dir, extRe, out = []) {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry)
    if (statSync(p).isDirectory()) walk(p, extRe, out)
    else if (extRe.test(entry)) out.push(p)
  }
  return out
}

const dispatchSites = new Map() // className -> [{ file, line }]
for (const file of walk(resolve(ROOT, 'app'), /\.php$/)) {
  const rel = file.slice(ROOT.length + 1)
  const lines = readFileSync(file, 'utf8').split('\n')
  lines.forEach((line, i) => {
    const re = /event\(\s*new\s+([A-Za-z_\\][\w\\]*)\s*\(/g
    let m
    while ((m = re.exec(line)) !== null) {
      const name = m[1].split('\\').pop()
      if (!dispatchSites.has(name)) dispatchSites.set(name, [])
      dispatchSites.get(name).push({ file: rel, line: i + 1 })
    }
  })
}

// --- 3. Consumers under resources/js -----------------------------------------

const containerOf = rel => basename(rel).replace(/\.(js|vue)$/, '')

const consumers = []
for (const file of walk(resolve(ROOT, 'resources/js'), /\.(js|vue)$/)) {
  const rel = file.slice(ROOT.length + 1)
  const src = readFileSync(file, 'utf8')
  const patterns = [
    { kind: 'channel', re: /\bchannel\s*\(\s*(['"`])([^'"`]*)\1/g },
    { kind: 'privateChannel', re: /\bprivateChannel\s*\(\s*(['"`])([^'"`]*)\1/g },
    { kind: 'echo.private', re: /\becho\.private\s*\(\s*(['"`])([^'"`]*)\1/g },
    { kind: 'echo.leave', re: /\becho\.leave\s*\(\s*(['"`])([^'"`]*)\1/g }
  ]
  for (const { kind, re } of patterns) {
    let m
    while ((m = re.exec(src)) !== null) {
      const raw = m[2]
      consumers.push({
        file: rel,
        line: src.slice(0, m.index).split('\n').length,
        kind,
        raw,
        name: channelName(raw),
        container: containerOf(rel)
      })
    }
  }
}

// --- 4. Cross + classify -----------------------------------------------------

const consumedBy = event =>
  event.channels.flatMap(ch =>
    consumers.filter(c => c.name === ch.name).map(c => ({ channel: ch.name, ...c }))
  )

const rows = events.map(event => {
  const sites = dispatchSites.get(event.name) ?? []
  const matches = consumedBy(event)
  const dispatched = sites.length > 0
  const consumed = matches.length > 0
  const privateNeedsAuth = event.channels.some(c => c.type === 'PrivateChannel' || c.type === 'PresenceChannel')
  const flags = [
    dispatched ? 'dispatched' : 'no-dispatch',
    consumed ? 'consumed' : 'unconsumed',
    ...(privateNeedsAuth ? ['private-needs-auth'] : [])
  ]
  return {
    name: event.name,
    file: event.file,
    broadcastAs: event.broadcastAs,
    channels: event.channels,
    dispatched,
    consumed,
    privateNeedsAuth,
    dispatchSites: sites,
    consumedBy: matches,
    unconsumedChannels: event.channels.filter(ch => !matches.some(m => m.channel === ch.name)).map(ch => ch.name),
    flags
  }
})

const counts = {
  events: rows.length,
  dispatched: rows.filter(r => r.dispatched).length,
  noDispatch: rows.filter(r => !r.dispatched).length,
  consumed: rows.filter(r => r.consumed).length,
  unconsumed: rows.filter(r => !r.consumed).length,
  privateNeedsAuth: rows.filter(r => r.privateNeedsAuth).length,
  consumers: consumers.length,
  dispatchSites: [...dispatchSites.values()].reduce((n, v) => n + v.length, 0)
}

// --- 5. Emit -----------------------------------------------------------------

mkdirSync(OUT, { recursive: true })
let revision = 'unknown'
try {
  revision = execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim()
} catch {
  /* detached tree or no git: artifact records unknown */
}
const rev = { revision, measured: new Date().toISOString().slice(0, 10) }

const fmtChannels = channels =>
  channels.length === 0 ? '—' : channels.map(c => `${c.name || c.expr} (${c.type})`).join(', ')

const fmtDispatch = sites =>
  sites.length === 0 ? 'no' : `yes (${sites.map(s => `${s.file}:${s.line}`).join(', ')})`

const fmtConsumed = matches =>
  matches.length === 0
    ? '—'
    : matches.map(m => `${m.channel} ← ${m.container} (${m.file}:${m.line})`).join('; ')

const md = [
  '# Realtime wiring map — dispatch → event → channel → consumer (static)',
  '',
  `Revision ${rev.revision}, measured ${rev.measured}. Derived by regex from \`app/Events/*.php\`, \`app/**/*.php\` and \`resources/js/**/*.{js,vue}\`.`,
  '',
  `Events: ${counts.events} | dispatched: ${counts.dispatched} | no-dispatch: ${counts.noDispatch} | consumed: ${counts.consumed} | unconsumed: ${counts.unconsumed} | private-needs-auth: ${counts.privateNeedsAuth}`,
  '',
  `| Event | broadcastAs | Channels | Dispatched | Consumed by | Flags |`,
  `| --- | --- | --- | --- | --- | --- |`,
  ...rows.map(
    r =>
      `| ${r.name} | ${r.broadcastAs ?? '—'} | ${fmtChannels(r.channels)} | ${fmtDispatch(r.dispatchSites)} | ${fmtConsumed(r.consumedBy)} | ${r.flags.join(', ')} |`
  ),
  '',
  `## Orphans (declared, not hidden)`,
  '',
  ...(rows.filter(r => !r.dispatched).length === 0
    ? ['- no-dispatch: none']
    : rows.filter(r => !r.dispatched).map(r => `- no-dispatch: ${r.name} (${r.file})`)),
  ...(rows.filter(r => !r.consumed).length === 0
    ? ['- unconsumed: none']
    : rows
        .filter(r => !r.consumed)
        .map(r => `- unconsumed: ${r.name} -> ${fmtChannels(r.channels)}`))
]
writeFileSync(resolve(OUT, 'map.md'), `${md.join('\n')}\n`)
writeFileSync(
  resolve(OUT, 'map.json'),
  JSON.stringify({ ...rev, counts, events: rows, consumers }, null, 2)
)

if (args.json) console.log(JSON.stringify({ ...rev, counts, events: rows }, null, 2))
else {
  console.log(
    `Events: ${counts.events} | dispatched: ${counts.dispatched} | no-dispatch: ${counts.noDispatch} | consumed: ${counts.consumed} | unconsumed: ${counts.unconsumed} | private-needs-auth: ${counts.privateNeedsAuth} | consumers: ${counts.consumers} | dispatch sites: ${counts.dispatchSites}`
  )
  for (const r of rows.filter(x => !x.dispatched || !x.consumed)) {
    console.log(`- [${r.flags.join(', ')}] ${r.name}: ${fmtChannels(r.channels)}`)
  }
}
if (rows.length === 0) {
  console.error('WARN: parsed zero events from app/Events/*.php; the parser and the tree have drifted.')
}
