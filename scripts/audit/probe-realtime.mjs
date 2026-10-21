#!/usr/bin/env node
/*
 * probe-realtime.mjs — live half of axis A8 (issue #28, plan #12).
 *
 * The static half (`realtime-map.mjs`) proves the wiring shape. This half proves
 * delivery over Reverb's Pusher protocol with two real sessions:
 *   - session A triggers the action through the REST API;
 *   - one raw WebSocket client subscribes (or is refused) on a private channel;
 *   - the probe asserts the event either arrives within EVENT_TIMEOUT_MS with
 *     the expected `broadcastAs` name, or never arrives for a client that was
 *     not authorized to subscribe.
 *
 * Every domain channel is private since #183 and #249, so the probe tests the
 * posture the project holds: no bearer token, no delivery.
 *
 * Cases:
 *   1. negative, private `appointments` — a raw WebSocket client with NO bearer
 *      token subscribes to `private-appointments`. Reverb must answer with an
 *      explicit authorization rejection (`pusher:error` code 4009 "Connection
 *      is unauthorized" on Reverb 1.x, or `pusher_internal:subscription_error`)
 *      and no `appointment.created` may arrive while session A creates the
 *      appointment through POST /api/appointments. The refusal is asserted
 *      explicitly — silence is a failure, not a pass — and the same appointment
 *      is delivered to case 2, so the absence proves channel authorization
 *      rather than a trigger that never fired. The unauthenticated client also
 *      subscribes to the old public names `appointments` and
 *      `dashboard-updates`, so a regression that returns the event to a public
 *      channel fails this case too.
 *   2. positive, private `appointments` — a client authenticated with session
 *      B's token through POST /api/broadcasting/auth subscribes to
 *      `private-appointments` and receives `appointment.created` carrying the
 *      appointment id created by the probe.
 *   3. private session channel — `cash-session.{sessionId}` when a cash session
 *      is open (authenticate via POST /api/broadcasting/auth, then subscribe).
 *      With no open session the case is `skipped-no-open-session`, never
 *      fabricated.
 *
 * Prerequisites: `php artisan serve`, `php artisan reverb:start` and a queue
 * worker. This project runs QUEUE_CONNECTION=database and AppointmentCreated is
 * a queued broadcast, so without `php artisan queue:listen` the event never
 * reaches Reverb and case 2 fails with its event timeout.
 *
 * Cleanup is mandatory: every created appointment is deleted in a `finally`
 * even when a case fails, so the probe is idempotent against the seeded DB.
 *
 * The Reverb app key is read from `.env` (`REVERB_APP_KEY`), falling back to
 * `local-key` exactly like `useEcho.js` does. No secret is hardcoded.
 *
 * Usage:
 *   node scripts/audit/probe-realtime.mjs [--base=url] [--ws=url] [--out=dir]
 *                                         [--fail-on-unproven] [--json]
 *
 * Exit 0 unless a connection to :8000/:<REVERB_PORT> is refused (hint printed) or
 * --fail-on-unproven and a required case is neither live-verified nor an
 * allowed skip.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')

const USAGE = `Usage: node scripts/audit/probe-realtime.mjs [--base=url] [--ws=url] [--out=dir] [--fail-on-unproven] [--json]

Live Reverb delivery probe (axis A8, issue #28): two sessions, private-channel
subscriptions and one REST trigger.

Options:
  --base=url          API origin (default http://localhost:8000)
  --ws=url            Reverb WebSocket base (default ws://<REVERB_HOST>:<REVERB_PORT>)
  --out=dir           evidence directory (default .atl/qa-evidence/audit/realtime)
  --fail-on-unproven  exit 1 when a required case is not live-verified
  --json              print the machine-readable summary to stdout
  --help              print this usage and exit 0

The Reverb key is read from .env (REVERB_APP_KEY), default local-key.
Writes <out>/probe.json and <out>/probe.md.
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
const BASE = String(args.base ?? 'http://localhost:8000').replace(/\/+$/, '')
const TIMEOUT_MS = 30000
const EVENT_TIMEOUT_MS = 15000

// --- Reverb config from .env (never hardcoded) -------------------------------

function dotenv() {
  const path = resolve(ROOT, '.env')
  if (!existsSync(path)) return {}
  const out = {}
  for (const line of readFileSync(path, 'utf8').split('\n')) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/)
    if (m)
      out[m[1]] = m[2]
        .trim()
        .replace(/^["']|["']$/g, '')
        .trim()
  }
  return out
}
const ENV = dotenv()
const pick = (name, fallback) => {
  const v = ENV[name] ?? process.env[name]
  return v === undefined || v === '' ? fallback : v
}
const REVERB_KEY = pick('REVERB_APP_KEY', 'local-key')
const REVERB_HOST = pick('REVERB_HOST', 'localhost')
const REVERB_PORT = pick('REVERB_PORT', '8081')
const REVERB_SCHEME = pick('REVERB_SCHEME', 'http')
const WS_BASE =
  String(args.ws ?? '') ||
  `${REVERB_SCHEME === 'https' ? 'wss' : 'ws'}://${REVERB_HOST}:${REVERB_PORT}`
const WS_URL = `${WS_BASE.replace(/\/+$/, '')}/app/${encodeURIComponent(REVERB_KEY)}?protocol=7&client=js&version=8.4.0&flash=false`

// --- Sessions ----------------------------------------------------------------

const PASSWORD = 'password123'
const SESSION_A = { role: 'administrador', username: 'elizabet' } // triggers
const SESSION_B = { role: 'recepcionista', username: 'recepcionista_test' } // subscribes

class ConnectionError extends Error {}

const apiHint = () =>
  `Cannot reach the API at ${BASE}. Start it with \`php artisan serve\` (or pass --base=<url>) and retry.`
const wsHint = () =>
  `Cannot reach Reverb at ${WS_BASE}. Start it with \`php artisan reverb:start\` (or pass --ws=<url>) and retry.`

async function request(path, { method = 'GET', token, body, form } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (form !== undefined) headers['Content-Type'] = 'application/x-www-form-urlencoded'
  if (token) headers.Authorization = `Bearer ${token}`
  let res
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body:
        body !== undefined
          ? JSON.stringify(body)
          : form !== undefined
            ? new URLSearchParams(form).toString()
            : undefined,
      signal: AbortSignal.timeout(TIMEOUT_MS)
    })
  } catch (err) {
    if (
      err?.cause?.code === 'ECONNREFUSED' ||
      /ECONNREFUSED/.test(String(err?.cause?.message ?? ''))
    )
      throw new ConnectionError(apiHint())
    if (err?.name === 'TimeoutError' || err?.name === 'AbortError')
      throw new Error(`timeout calling ${path}`)
    throw err
  }
  const text = await res.text()
  let json = null
  try {
    json = JSON.parse(text)
  } catch {
    /* non-json body */
  }
  return { status: res.status, text, json }
}

async function login(username) {
  const res = await request('/api/login', {
    method: 'POST',
    body: { username, password: PASSWORD }
  })
  if (res.status < 200 || res.status >= 300 || !res.json?.data?.token)
    throw new Error(`login failed for ${username}: HTTP ${res.status} ${res.text.slice(0, 200)}`)
  return res.json.data.token
}

// --- Minimal Pusher-protocol client over the global WebSocket (Node >= 22) ---

class PusherClient {
  constructor(url) {
    this.url = url
    this.ws = null
    this.socketId = null
    this.messages = []
    this.waiters = []
  }

  connect(timeoutMs = 10000) {
    return new Promise((resolve, reject) => {
      let settled = false
      const done = (fn, value) => {
        if (settled) return
        settled = true
        clearTimeout(timer)
        fn(value)
      }
      const timer = setTimeout(() => done(reject, new ConnectionError(wsHint())), timeoutMs)
      let ws
      try {
        ws = new WebSocket(this.url)
      } catch {
        return done(reject, new ConnectionError(wsHint()))
      }
      this.ws = ws
      ws.addEventListener('message', ev => {
        let msg
        try {
          msg = JSON.parse(String(ev.data))
        } catch {
          return
        }
        if (msg.event === 'pusher:connection_established') {
          try {
            this.socketId = JSON.parse(msg.data).socket_id
          } catch {
            /* keep null */
          }
          return done(resolve, this.socketId)
        }
        if (msg.event === 'pusher:ping') {
          ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }))
          return
        }
        this.messages.push(msg)
        this.waiters = this.waiters.filter(w => {
          if (!w.predicate(msg)) return true
          w.resolve(msg)
          return false
        })
      })
      ws.addEventListener('error', () => done(reject, new ConnectionError(wsHint())))
      ws.addEventListener('close', ev => {
        if (ev.code !== 1000) done(reject, new ConnectionError(`${wsHint()} (closed ${ev.code})`))
      })
    })
  }

  subscribe(channel, auth, timeoutMs = 10000) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(
        () => reject(new Error(`subscribe timeout on ${channel}`)),
        timeoutMs
      )
      this.waiters.push({
        predicate: m =>
          (m.event === 'pusher_internal:subscription_succeeded' ||
            m.event === 'pusher_internal:subscription_error') &&
          m.channel === channel,
        resolve: m => {
          clearTimeout(timer)
          if (m.event === 'pusher_internal:subscription_error')
            reject(new Error(`subscription_error on ${channel}${m.data ? `: ${m.data}` : ''}`))
          else resolve(m)
        }
      })
      const data = { channel }
      if (auth) data.auth = auth
      this.ws.send(JSON.stringify({ event: 'pusher:subscribe', data }))
    })
  }

  // Resolves `{ delivered: true }` if the event arrives, `{ delivered: false }`
  // once the window closes. Used by the negative case: absence is the assertion.
  waitForAbsence(channel, event, timeoutMs = EVENT_TIMEOUT_MS) {
    const existing = this.messages.find(m => m.channel === channel && m.event === event)
    if (existing) return Promise.resolve({ delivered: true, message: existing })
    return new Promise(resolve => {
      let waiter
      const timer = setTimeout(() => {
        this.waiters = this.waiters.filter(w => w !== waiter)
        resolve({ delivered: false })
      }, timeoutMs)
      waiter = {
        predicate: m => m.channel === channel && m.event === event,
        resolve: m => {
          clearTimeout(timer)
          resolve({ delivered: true, message: m })
        }
      }
      this.waiters.push(waiter)
    })
  }

  waitFor(channel, event, timeoutMs = EVENT_TIMEOUT_MS) {
    const existing = this.messages.find(m => m.channel === channel && m.event === event)
    if (existing) return Promise.resolve(existing)
    return new Promise((resolve, reject) => {
      const timer = setTimeout(
        () => reject(new Error(`no ${event} on ${channel} within ${timeoutMs}ms`)),
        timeoutMs
      )
      this.waiters.push({
        predicate: m => m.channel === channel && m.event === event,
        resolve: m => {
          clearTimeout(timer)
          resolve(m)
        }
      })
    })
  }

  waitForMessage(predicate, timeoutMs, description = 'message') {
    const existing = this.messages.find(predicate)
    if (existing) return Promise.resolve(existing)
    return new Promise((resolve, reject) => {
      const timer = setTimeout(
        () => reject(new Error(`no ${description} within ${timeoutMs}ms`)),
        timeoutMs
      )
      this.waiters.push({
        predicate,
        resolve: m => {
          clearTimeout(timer)
          resolve(m)
        }
      })
    })
  }

  close() {
    try {
      this.ws?.close(1000)
    } catch {
      /* already closed */
    }
  }
}

// --- Cases -------------------------------------------------------------------

const cases = []
const record = (id, channel, expected, status, detail) => {
  const entry = { id, channel, expected, status, detail }
  cases.push(entry)
  return entry
}

const APPOINTMENTS_CHANNEL = 'private-appointments'
const APPOINTMENT_EVENT = 'appointment.created'

// Reverb treats any channel without the `private-`/`presence-` prefix as public.
// Issue #273: those are the names AppointmentCreated used before #183 and #249,
// so the negative case watches them as a regression guard.
const PUBLIC_REGRESSION_CHANNELS = ['appointments', 'dashboard-updates']

// Reverb 1.x answers an unauthenticated subscribe to a private channel with a
// connection-level `pusher:error` (code 4009, "Connection is unauthorized");
// Pusher-protocol servers may answer `pusher_internal:subscription_error` on the
// channel instead. Both are explicit refusals; anything else is not evidence.
function classifySubscribeReply(msg, channel) {
  if (msg.event === 'pusher_internal:subscription_succeeded' && msg.channel === channel)
    return { accepted: true, text: `pusher_internal:subscription_succeeded on ${channel}` }
  if (msg.event === 'pusher_internal:subscription_error' && msg.channel === channel)
    return {
      accepted: false,
      text: `pusher_internal:subscription_error on ${channel}${msg.data ? `: ${String(msg.data).slice(0, 200)}` : ''}`
    }
  if (msg.event === 'pusher:error') {
    let data = null
    try {
      data = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data
    } catch {
      data = null
    }
    const code = data?.code
    const message = data?.message ?? ''
    const text = `pusher:error ${code ?? '?'}${message ? ` "${message}"` : ''}`
    if (Number(code) === 4009 || /unauthori[sz]ed/i.test(String(message)))
      return { accepted: false, text }
    return { accepted: true, text: `${text} (not an authorization refusal)` }
  }
  return { accepted: true, text: `${msg.event} on ${msg.channel ?? 'connection'} (not a refusal)` }
}

async function privateAppointmentCases(tokenA, tokenB) {
  const negative = record(
    'private-appointments-unauthenticated',
    APPOINTMENTS_CHANNEL,
    'explicit subscription rejection (pusher:error 4009 / pusher_internal:subscription_error) and no appointment.created',
    'failed',
    'not run'
  )
  const positive = record(
    'private-appointments-authenticated',
    APPOINTMENTS_CHANNEL,
    APPOINTMENT_EVENT,
    'failed',
    'not run'
  )

  let raw = null
  let authed = null
  let createdId = null
  let delivery = null
  let watched = []
  let authenticated = false
  let rawSubscribed = false
  let rejection = null
  let protocolFailure = null
  const pending = []

  try {
    const fixture = await loadFixture(tokenA)

    raw = new PusherClient(WS_URL)
    await raw.connect()
    authed = new PusherClient(WS_URL)
    const socketId = await authed.connect()

    // Positive case: session B's bearer token must pass the handshake Reverb
    // requires for a private channel.
    const auth = await request('/api/broadcasting/auth', {
      method: 'POST',
      token: tokenB,
      form: { socket_id: socketId, channel_name: APPOINTMENTS_CHANNEL }
    })
    if (auth.status < 200 || auth.status >= 300 || !auth.json?.auth) {
      positive.status = 'failed'
      positive.detail = `POST /api/broadcasting/auth for ${APPOINTMENTS_CHANNEL} -> HTTP ${auth.status}: ${auth.text.slice(0, 200)}`
    } else {
      await authed.subscribe(APPOINTMENTS_CHANNEL, auth.json.auth)
      authenticated = true
    }

    // Negative case: the same channel, no bearer token and no `auth` key. The
    // refusal itself is the assertion, so the attempt is not awaited in
    // isolation: a silent timeout must fail the case, never pass it.
    const rawAttempt = raw
      .subscribe(APPOINTMENTS_CHANNEL)
      .then(() => {
        rawSubscribed = true
      })
      .catch(() => {
        /* the protocol reply below is the evidence */
      })
    pending.push(rawAttempt)

    try {
      const reply = await raw.waitForMessage(
        m =>
          (m.channel === APPOINTMENTS_CHANNEL &&
            (m.event === 'pusher_internal:subscription_succeeded' ||
              m.event === 'pusher_internal:subscription_error')) ||
          m.event === 'pusher:error',
        10000,
        `protocol answer for the unauthenticated subscribe to ${APPOINTMENTS_CHANNEL}`
      )
      const verdict = classifySubscribeReply(reply, APPOINTMENTS_CHANNEL)
      if (verdict.accepted)
        protocolFailure = `${verdict.text}; the private channel did not refuse an unauthenticated client`
      else rejection = verdict.text
    } catch (err) {
      protocolFailure = `${err.message}; silence is not proof that the subscribe was refused`
    }

    // Regression guard for #273: the same unauthenticated client subscribes to
    // the old public names. If the event returns to a public channel it is
    // delivered here and the negative case fails.
    const publicChannels = []
    for (const channel of PUBLIC_REGRESSION_CHANNELS) {
      try {
        await raw.subscribe(channel)
        publicChannels.push(channel)
      } catch {
        /* the channel is no longer public: nothing to watch */
      }
    }

    if (authenticated) {
      delivery = authed.waitFor(APPOINTMENTS_CHANNEL, APPOINTMENT_EVENT)
      pending.push(delivery)
    }
    watched = [APPOINTMENTS_CHANNEL, ...publicChannels].map(channel => ({
      channel,
      result: raw.waitForAbsence(channel, APPOINTMENT_EVENT)
    }))
    for (const w of watched) pending.push(w.result)

    const create = await request('/api/appointments', {
      method: 'POST',
      token: tokenA,
      body: fixture.payload
    })
    if (create.status < 200 || create.status >= 300) {
      const detail = `trigger POST /api/appointments -> HTTP ${create.status}: ${create.text.slice(0, 200)}`
      for (const c of [negative, positive]) {
        c.status = 'failed'
        c.detail = detail
      }
      return
    }
    createdId = create.json?.data?.id ?? null

    if (authenticated) {
      try {
        const msg = await delivery
        let eventId = null
        try {
          eventId = JSON.parse(msg.data)?.appointment?.id ?? null
        } catch {
          /* payload not JSON */
        }
        if (eventId !== null && createdId !== null && Number(eventId) !== Number(createdId)) {
          positive.status = 'failed'
          positive.detail = `received ${APPOINTMENT_EVENT} but payload appointment ${eventId} != created ${createdId}`
        } else {
          positive.status = 'live-verified'
          positive.detail = `received ${APPOINTMENT_EVENT} on ${APPOINTMENTS_CHANNEL}${eventId !== null ? ` (appointment ${eventId})` : ''} after POST /api/broadcasting/auth as ${SESSION_B.role}`
        }
      } catch (err) {
        positive.status = 'failed'
        positive.detail = `${err.message} (is a queue worker running? AppointmentCreated is a queued broadcast)`
      }
    }

    const observed = await Promise.all(watched.map(w => w.result))
    const leaked = watched.filter((w, i) => observed[i].delivered).map(w => w.channel)

    if (rawSubscribed) {
      negative.status = 'failed'
      negative.detail = `${APPOINTMENTS_CHANNEL} accepted an unauthenticated subscribe${leaked.length ? ` and delivered appointment.created on ${leaked.join(', ')}` : ''}; the private channel is not enforcing authorization`
      return
    }
    if (protocolFailure) {
      negative.status = 'failed'
      negative.detail = `${protocolFailure}${leaked.length ? ` and appointment.created still arrived on ${leaked.join(', ')}` : ''}`
      return
    }
    if (!rejection) {
      negative.status = 'failed'
      negative.detail = `no authorization refusal observed for the unauthenticated subscribe to ${APPOINTMENTS_CHANNEL}`
      return
    }
    if (leaked.length) {
      negative.status = 'failed'
      negative.detail = `observed ${rejection} but ${APPOINTMENT_EVENT} arrived on ${leaked.join(', ')} (a public channel carried the event)`
      return
    }
    if (positive.status !== 'live-verified') {
      negative.status = 'failed'
      negative.detail = `observed ${rejection} and no ${APPOINTMENT_EVENT} within ${EVENT_TIMEOUT_MS}ms, but the authenticated client did not receive it either, so non-delivery is unproven`
      return
    }
    negative.status = 'live-verified'
    negative.detail = `observed ${rejection}; no ${APPOINTMENT_EVENT} on ${watched.map(w => w.channel).join(', ')} within ${EVENT_TIMEOUT_MS}ms while appointment ${createdId} was published to the authenticated client`
  } catch (err) {
    const detail = err.message
    for (const c of [negative, positive]) {
      if (c.detail === 'not run') {
        c.status = 'failed'
        c.detail = detail
      }
    }
  } finally {
    // Let the pending waits settle so no rejection escapes, then clean up.
    if (pending.length) await Promise.allSettled(pending)
    if (createdId !== null) {
      try {
        const del = await request(`/api/appointments/${createdId}`, {
          method: 'DELETE',
          token: tokenA
        })
        if (del.status >= 400)
          console.error(`WARN: cleanup DELETE /api/appointments/${createdId} -> HTTP ${del.status}`)
      } catch (err) {
        console.error(`WARN: cleanup failed for appointment ${createdId}: ${err.message}`)
      }
    }
    raw?.close()
    authed?.close()
  }
}

async function loadFixture(token) {
  const first = async path => {
    const res = await request(path, { token })
    const data = res.json?.data
    const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : []
    if (arr.length === 0) throw new Error(`no fixture rows from ${path} (HTTP ${res.status})`)
    return arr[0]
  }
  const patient = await first('/api/patients')
  const users = await first('/api/users/active')
  const type = await first('/api/appointment-types/active')
  const chair = await first('/api/dental-chairs/active')
  const scheduledAt = new Date(
    Date.now() +
      (3 + Math.floor(Math.random() * 20)) * 86400000 +
      Math.floor(Math.random() * 1440) * 60000
  )
    .toISOString()
    .slice(0, 19)
    .replace('T', ' ')
  return {
    ids: {
      patient_id: patient.id,
      user_id: users.id,
      appointment_type_id: type.id,
      dental_chair_id: chair.id
    },
    payload: {
      patient_id: patient.id,
      user_id: users.id,
      dental_chair_id: chair.id,
      appointment_type_id: type.id,
      scheduled_at: scheduledAt,
      duration_minutes: 30
    }
  }
}

async function privateCase(tokenA, tokenB) {
  const c = record('private-channel', null, 'subscription', 'failed', 'not run')
  let client = null
  try {
    const current = await request('/api/cash-register/current', { token: tokenA })
    const sessionId = current.json?.data?.session?.id ?? null
    if (!sessionId) {
      c.status = 'skipped-no-open-session'
      c.detail =
        'GET /api/cash-register/current returned no open session; no private channel to test'
      return
    }
    c.channel = `private-cash-session.${sessionId}`
    client = new PusherClient(WS_URL)
    const socketId = await client.connect()
    const auth = await request('/api/broadcasting/auth', {
      method: 'POST',
      token: tokenB,
      form: { socket_id: socketId, channel_name: c.channel }
    })
    if (auth.status < 200 || auth.status >= 300 || !auth.json?.auth) {
      c.status = 'failed'
      c.detail = `POST /api/broadcasting/auth for ${c.channel} -> HTTP ${auth.status}: ${auth.text.slice(0, 200)}`
      return
    }
    await client.subscribe(c.channel, auth.json.auth)
    c.status = 'live-verified'
    c.detail = `session ${SESSION_B.role} authenticated and subscribed to ${c.channel} (subscription-level proof; no event triggered on this channel)`
  } catch (err) {
    c.status = 'failed'
    c.detail = err instanceof ConnectionError ? err.message : err.message
  } finally {
    client?.close()
  }
}

// --- Main --------------------------------------------------------------------

const isAllowedSkip = c => c.id === 'private-channel' && c.status === 'skipped-no-open-session'
const unproven = () => cases.filter(c => c.status !== 'live-verified' && !isAllowedSkip(c))

async function main() {
  let tokenA
  let tokenB
  try {
    tokenA = await login(SESSION_A.username)
    tokenB = await login(SESSION_B.username)
  } catch (err) {
    if (err instanceof ConnectionError) {
      console.error(err.message)
      record('session-login', null, null, 'failed', err.message)
      return 1
    }
    console.error(`FAIL: ${err.message}`)
    record('session-login', null, null, 'failed', err.message)
    return 1
  }

  await privateAppointmentCases(tokenA, tokenB)
  await privateCase(tokenA, tokenB)

  // A WS connection failure surfaces as a failed case; keep the hint prominent.
  const connectionFailure = cases.find(
    c => c.status === 'failed' && /Cannot reach Reverb|Cannot reach the API/.test(c.detail)
  )
  if (connectionFailure) console.error(connectionFailure.detail)
  return 0
}

const exitCode = await main()

// --- Evidence ----------------------------------------------------------------

mkdirSync(OUT, { recursive: true })
let revision = 'unknown'
try {
  revision = execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim()
} catch {
  /* detached tree or no git */
}
const rev = { revision, measured: new Date().toISOString().slice(0, 10) }
const counts = {
  cases: cases.length,
  liveVerified: cases.filter(c => c.status === 'live-verified').length,
  skipped: cases.filter(c => c.status === 'skipped-no-open-session').length,
  failed: cases.filter(c => c.status === 'failed').length,
  unproven: unproven().length
}
const result = {
  ...rev,
  base: BASE,
  ws: WS_BASE,
  reverbKey: REVERB_KEY,
  sessions: { a: SESSION_A, b: SESSION_B },
  counts,
  cases
}
writeFileSync(resolve(OUT, 'probe.json'), JSON.stringify(result, null, 2))
const mdCell = v => String(v ?? '—').replace(/\|/g, '\\|')
writeFileSync(
  resolve(OUT, 'probe.md'),
  [
    '# Realtime live probe — private-channel delivery over Reverb/Pusher',
    '',
    `Revision ${rev.revision}, measured ${rev.measured}. API ${BASE}, Reverb ${WS_BASE}. Session A (${SESSION_A.role}) triggers; session B (${SESSION_B.role}) authenticates the private subscription.`,
    '',
    `Cases: ${counts.cases} | live-verified: ${counts.liveVerified} | skipped: ${counts.skipped} | failed: ${counts.failed} | unproven: ${counts.unproven}`,
    '',
    `| Case | Channel | Expected | Status | Detail |`,
    `| --- | --- | --- | --- | --- |`,
    ...cases.map(
      c =>
        `| ${mdCell(c.id)} | ${mdCell(c.channel)} | ${mdCell(c.expected)} | ${c.status} | ${mdCell(c.detail)} |`
    ),
    ''
  ].join('\n')
)

if (args.json)
  console.log(JSON.stringify({ ...rev, base: BASE, ws: WS_BASE, counts, cases }, null, 2))
else {
  console.log(
    `Cases: ${counts.cases} | live-verified: ${counts.liveVerified} | skipped: ${counts.skipped} | failed: ${counts.failed} | unproven: ${counts.unproven}`
  )
  for (const c of cases) console.log(`- [${c.status}] ${c.id}: ${c.detail}`)
}

const hardFailure = cases.some(
  c => c.status === 'failed' && /Cannot reach Reverb|Cannot reach the API/.test(c.detail)
)
if (hardFailure) process.exit(1)
if (args['fail-on-unproven'] && counts.unproven > 0) {
  console.error(
    `\nFAIL: ${counts.unproven} required case(s) not live-verified. See ${OUT}/probe.md.`
  )
  process.exit(1)
}
process.exit(exitCode)
