// Plan #13 (issue #366, U3) — API-only fixtures for the agenda and consultation
// audit rows.
//
// The audit database is disposable and today's seeded appointments all belong
// to the administrator on chair 1, so these fixtures create their own rows
// through the public API: one scheduled (drag + cancel source), one arrived
// (announce + start source) and one in consultation (wizard contract source).
// Nothing here writes product code or the database directly.
//
// The lifecycle appointment is created by the calendar row through the UI; that
// row registers it here so the wizard rows (a different module) can close it.
// Module state is shared because the suite runs with `workers: 1` and
// `fullyParallel: false`, in import order.
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { test } from 'playwright/test'

import { BASE_URL, loginAs, writeEvidence } from '../harness.js'

export const U3_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U3'

export const MARKER = 'AUDIT-U3'

const API_TIMEOUT = 20000
const DURATION_MINUTES = 15
// The plan's three slots. Professional and chair cannot host two overlapping
// appointments, so a refused slot falls forward on the same day; the evidence
// keeps both the requested and the accepted offsets.
const REQUESTED_OFFSETS = [15, 20, 25]
const FALLBACK_OFFSETS = [45, 60, 75, 90, 120, 150, 180, 210, 240, 300, 360, 420]

const localDay = date => {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

const messageOf = body => {
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.error?.message === 'string') return body.error.message
  if (typeof body?.error === 'string') return body.error
  const firstError = Object.values(body?.errors ?? {})[0]
  return Array.isArray(firstError) ? firstError[0] : null
}

const call = async (request, session, method, path, data) => {
  const options = {
    method,
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
    timeout: API_TIMEOUT
  }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const body = await response.json().catch(() => null)

    return {
      method,
      path,
      status: response.status(),
      ok: response.ok(),
      error: response.ok() ? null : messageOf(body),
      body
    }
  } catch (thrown) {
    return {
      method,
      path,
      status: null,
      ok: false,
      error: String(thrown?.message ?? thrown),
      body: null
    }
  }
}

const rowsOf = body => (Array.isArray(body?.data) ? body.data : [])

const pick = response => ({ status: response.status, error: response.error })

const serverToday = async (request, admin) => {
  const response = await call(request, admin, 'GET', '/api/dashboard/appointments-today')
  const date = response.body?.meta?.date

  return {
    value: typeof date === 'string' ? date : localDay(new Date()),
    source: typeof date === 'string' ? 'dashboard/appointments-today meta.date' : 'browser clock'
  }
}

const freeChairsAcross = (chairs, appointments) => {
  const used = new Set(
    appointments
      .filter(row => localDay(new Date(row.scheduled_at)) === localDay(new Date()))
      .map(row => row.dental_chair_id)
  )
  const free = chairs.filter(chair => !used.has(chair.id))

  return free.length >= 3 ? free.slice(0, 3) : chairs.slice(0, 3)
}

const resolveCatalog = async (request, admin, today) => {
  const [patientsResponse, typesResponse, chairsResponse, appointmentsResponse] = await Promise.all(
    [
      call(request, admin, 'GET', '/api/patients'),
      call(request, admin, 'GET', '/api/appointment-types/active'),
      call(request, admin, 'GET', '/api/dental-chairs/active'),
      call(
        request,
        admin,
        'GET',
        `/api/appointments?start_date=${today.value}&end_date=${today.value}&per_page=1000`
      )
    ]
  )

  const calls = [patientsResponse, typesResponse, chairsResponse, appointmentsResponse]
  const patients = rowsOf(patientsResponse.body).filter(patient => patient?.is_active !== false)
  const types = rowsOf(typesResponse.body)
  const chairs = rowsOf(chairsResponse.body).filter(chair => chair?.status !== 'maintenance')

  // Row targeting uses the patient name, so the three fixtures must be distinct.
  const distinct = []
  const seen = new Set()

  for (const patient of patients) {
    const name = patient?.full_name ?? [patient?.first_name, patient?.last_name].join(' ').trim()
    if (!name || seen.has(name)) continue
    seen.add(name)
    distinct.push({ id: patient.id, name, raw: patient })
  }

  if (distinct.length < 3) {
    return { ok: false, reason: `patients unavailable: ${distinct.length} distinct active rows` }
  }
  if (types.length === 0) return { ok: false, reason: 'appointment types: no active row' }
  if (chairs.length < 3) return { ok: false, reason: `dental chairs unavailable: ${chairs.length}` }

  const seeded = rowsOf(appointmentsResponse.body)

  return {
    ok: true,
    patients: distinct.slice(0, 3),
    type: types[0],
    chairs: freeChairsAcross(chairs, seeded),
    calls
  }
}

const createAppointments = async (request, admin, professional, catalog, today) => {
  const rows = []
  const attempts = []
  const start = Date.now()
  let floor = 0

  for (let index = 0; index < 3; index += 1) {
    const requested = REQUESTED_OFFSETS[index]
    const candidates = [requested, ...FALLBACK_OFFSETS.filter(offset => offset > requested)]
    let created = null

    for (const offset of candidates) {
      if (offset <= floor) {
        if (offset === requested) {
          attempts.push({
            index,
            offset,
            status: null,
            reason: 'overlaps the previous fixture slot (same professional)'
          })
        }
        continue
      }

      const at = new Date(start + offset * 60000)

      if (localDay(at) !== today.value) {
        attempts.push({ index, offset, status: null, reason: 'slot outside the server day' })
        break
      }

      const response = await call(request, admin, 'POST', '/api/appointments', {
        patient_id: catalog.patients[index].id,
        user_id: professional.user.id,
        dental_chair_id: catalog.chairs[index].id,
        appointment_type_id: catalog.type.id,
        scheduled_at: at.toISOString(),
        duration_minutes: DURATION_MINUTES,
        notes: `${MARKER} ${['scheduled', 'arrived', 'in-consultation'][index]}`
      })

      attempts.push({ index, offset, status: response.status, error: response.error })

      if (response.ok && response.body?.data?.id) {
        created = {
          id: response.body.data.id,
          status: response.body.data.status,
          patient: {
            id: catalog.patients[index].id,
            name: catalog.patients[index].name
          },
          chairId: catalog.chairs[index].id,
          chairName: catalog.chairs[index].name,
          offsetMinutes: offset,
          requestedOffsetMinutes: requested,
          scheduledAt: at.toISOString(),
          finalAmount: response.body.data.final_amount ?? null
        }
        floor = offset + DURATION_MINUTES
        break
      }

      // Only a refused slot is worth re-planning; a server failure would repeat.
      if (response.status !== 422) break
    }

    if (!created) {
      return {
        ok: false,
        reason: `appointment ${index + 1} of 3 refused: ${
          attempts.at(-1)?.error ?? 'no slot left today'
        }`,
        rows,
        attempts
      }
    }

    rows.push(created)
  }

  return { ok: true, rows, attempts }
}

// The plan's three states, reached through the real custody doors so the rows
// measure the app's own transitions instead of a database write.
const stageAppointments = async (request, receptionist, professional, rows) => {
  const steps = []

  if (rows[1]) {
    const arrival = await call(
      request,
      receptionist,
      'POST',
      `/api/appointments/${rows[1].id}/check-in`
    )
    steps.push({ action: 'check-in', appointment: rows[1].id, ...pick(arrival) })
    rows[1].status = arrival.body?.data?.status ?? rows[1].status
  }

  if (rows[2]) {
    const arrival = await call(
      request,
      receptionist,
      'POST',
      `/api/appointments/${rows[2].id}/check-in`
    )
    steps.push({ action: 'check-in', appointment: rows[2].id, ...pick(arrival) })
    rows[2].status = arrival.body?.data?.status ?? rows[2].status

    if (arrival.ok) {
      const attention = await call(
        request,
        professional,
        'POST',
        `/api/appointments/${rows[2].id}/start-attention`
      )
      steps.push({ action: 'start-attention', appointment: rows[2].id, ...pick(attention) })
      rows[2].status = attention.body?.data?.status ?? rows[2].status
    }
  }

  return steps
}

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    createdAt: new Date().toISOString(),
    actor: 'administrador',
    appointments: { ok: false, reason: 'not built', rows: [], attempts: [] },
    staging: [],
    lifecycle: null,
    teardown: []
  }

  const admin = await loginAs(request, 'administrador')
  const professional = await loginAs(request, 'odontologo')
  const receptionist = await loginAs(request, 'recepcionista')
  const today = await serverToday(request, admin)

  built.sessions = {
    administrador: { id: admin.user.id, name: admin.user.name, role: admin.user.role },
    odontologo: {
      id: professional.user.id,
      name: professional.user.name,
      role: professional.user.role
    },
    recepcionista: {
      id: receptionist.user.id,
      name: receptionist.user.name,
      role: receptionist.user.role
    }
  }
  built.professional = {
    id: professional.user.id,
    name: professional.user.name,
    username: professional.user.username
  }
  built.receptionist = {
    id: receptionist.user.id,
    name: receptionist.user.name,
    username: receptionist.user.username
  }
  built.today = { ...today, localToday: localDay(new Date()) }
  built.timezoneMismatch = today.value !== localDay(new Date())

  const catalog = await resolveCatalog(request, admin, today)

  if (!catalog.ok) {
    built.appointments = { ok: false, reason: catalog.reason, rows: [], attempts: [] }
    built.catalogRequests = (catalog.calls ?? []).map(pick)
    return built
  }

  built.patients = catalog.patients.map(patient => ({ id: patient.id, name: patient.name }))
  built.appointmentType = {
    id: catalog.type.id,
    name: catalog.type.name,
    price: catalog.type.price ?? null
  }
  built.chairs = catalog.chairs.map(chair => ({ id: chair.id, name: chair.name, code: chair.code }))

  const appointments = await createAppointments(request, admin, professional, catalog, today)
  built.appointments = appointments
  built.catalogRequests = catalog.calls.map(pick)
  built.spec = {
    requestedOffsetsMinutes: REQUESTED_OFFSETS,
    durationMinutes: DURATION_MINUTES,
    conflictPolicy: 'a refused or overlapping slot falls forward to a later one on the same day',
    chairPolicy: 'chairs without appointments today are preferred; the seeded day sits on chair 1'
  }

  if (!appointments.ok) return built

  built.staging = await stageAppointments(request, receptionist, professional, appointments.rows)

  return built
}

const publishFixtures = fixtures =>
  writeEvidence(
    'fixtures.json',
    {
      route: '/calendar',
      role: fixtures.professional?.username ?? null,
      pageErrors: [],
      consoleErrors: [],
      failedRequests: { expected: [], unexpected: [] },
      shots: [],
      ...fixtures
    },
    { dir: U3_EVIDENCE_DIR }
  )

// Playwright re-evaluates the test modules for each test, and a failed test can
// recycle the worker, so module locals are not enough to hand the UI-created
// lifecycle appointment from the calendar rows to the wizard rows. The registry
// lives on globalThis (fast path) and mirrors to a state file (worker-restart
// path); the afterAll hooks of every instance see the same fixtures and the
// first one retires them.
const STATE_FILE = resolve(process.cwd(), U3_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U3_FIXTURES__ ??= {
  promise: null,
  fixtures: null,
  lifecycle: null,
  state: null
})

const readState = () => {
  if (REGISTRY.state) return REGISTRY.state

  try {
    if (!existsSync(STATE_FILE)) return null
    REGISTRY.state = JSON.parse(readFileSync(STATE_FILE, 'utf8'))
    return REGISTRY.state
  } catch {
    return null
  }
}

const writeState = patch => {
  const next = {
    out: U3_EVIDENCE_DIR,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }
  REGISTRY.state = next
  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

/**
 * Builds the U3 fixtures once per worker and returns them to every row that
 * needs them. A build failure is a reason in the evidence, never an exception.
 */
const reuseStoredFixtures = async (request, stored) => {
  if (!stored?.fixtures?.appointments?.ok) return null
  if (stored.retired === true) return null
  if (stored.day && stored.day !== localDay(new Date())) return null

  const admin = await loginAs(request, 'administrador')
  const rows = stored.fixtures.appointments.rows ?? []

  for (const row of rows) {
    const current = await call(request, admin, 'GET', `/api/appointments/${row.id}`)
    if (current.status !== 200) return null
    row.status = current.body?.data?.status ?? row.status
  }

  REGISTRY.lifecycle = stored.lifecycle ?? REGISTRY.lifecycle
  REGISTRY.fixtures = { ...stored.fixtures, lifecycle: REGISTRY.lifecycle }
  publishFixtures(REGISTRY.fixtures)
  return REGISTRY.fixtures
}

export const ensureFixtures = request => {
  if (!REGISTRY.promise) {
    REGISTRY.promise = (async () => {
      const stored = readState()
      const reused = stored ? await reuseStoredFixtures(request, stored) : null
      if (reused) return reused

      const fixtures = await buildFixtures(request).catch(thrown => ({
        marker: MARKER,
        createdAt: new Date().toISOString(),
        appointments: { ok: false, reason: `fixture build failed: ${thrown.message}` },
        staging: [],
        lifecycle: null,
        teardown: []
      }))

      fixtures.lifecycle = stored?.lifecycle ?? REGISTRY.lifecycle ?? null
      REGISTRY.fixtures = fixtures
      REGISTRY.lifecycle = fixtures.lifecycle
      writeState({
        createdAt: fixtures.createdAt,
        day: fixtures.today?.value ?? localDay(new Date()),
        retired: false,
        fixtures,
        lifecycle: fixtures.lifecycle
      })
      publishFixtures(fixtures)
      return fixtures
    })()
  }

  return REGISTRY.promise
}

/**
 * Registers the appointment the calendar row created through the UI. The
 * wizard rows run from another module and close this one, not the fixture.
 */
export const registerLifecycle = (id, patientName, meta = {}) => {
  REGISTRY.lifecycle = {
    ...(REGISTRY.lifecycle ?? {}),
    id,
    patientName,
    source: 'ui',
    registeredAt: new Date().toISOString(),
    ...meta
  }

  if (REGISTRY.fixtures) {
    REGISTRY.fixtures.lifecycle = REGISTRY.lifecycle
    publishFixtures(REGISTRY.fixtures)
  }
  writeState({ lifecycle: REGISTRY.lifecycle })

  return REGISTRY.lifecycle
}

export const updateLifecycle = patch => {
  if (!REGISTRY.lifecycle) return null
  REGISTRY.lifecycle = {
    ...REGISTRY.lifecycle,
    ...patch,
    updatedAt: new Date().toISOString()
  }

  if (REGISTRY.fixtures) {
    REGISTRY.fixtures.lifecycle = REGISTRY.lifecycle
    publishFixtures(REGISTRY.fixtures)
  }
  writeState({ lifecycle: REGISTRY.lifecycle })

  return REGISTRY.lifecycle
}

export const lifecycleTarget = () => {
  if (REGISTRY.lifecycle) return REGISTRY.lifecycle
  REGISTRY.lifecycle = readState()?.lifecycle ?? null
  return REGISTRY.lifecycle
}

/**
 * The appointment the wizard closed, mirrored to the state file so the
 * settlement row can find it when the session runs as a separate `--grep`.
 */
export const registerClosure = closure => {
  writeState({ closure })
  return closure
}

export const closureTarget = () => readState()?.closure ?? null

const retire = async (request, fixtures) => {
  const admin = await loginAs(request, 'administrador')
  const steps = []
  const seen = new Set()

  // The UI-created lifecycle appointment belongs to the calendar/wizard rows:
  // this hook never deletes it, so a worker recycled by a failed row cannot
  // take the session's closure evidence away from the next row.
  const targets = (fixtures.appointments?.rows ?? []).map(row => ({
    id: row.id,
    label: `appointment:${row.id}`
  }))

  if (fixtures.lifecycle?.id) {
    steps.push({
      target: `lifecycle:${fixtures.lifecycle.id}`,
      action: 'skipped',
      reason: 'owned by the calendar/wizard rows, not by the fixtures hook',
      status: fixtures.lifecycle.status ?? null
    })
  }

  for (const target of targets) {
    if (seen.has(target.id)) continue
    seen.add(target.id)

    const current = await call(request, admin, 'GET', `/api/appointments/${target.id}`)
    const status = current.body?.data?.status ?? null

    if (status === 'completed') {
      steps.push({
        target: target.label,
        action: 'skipped',
        reason: 'completed: the close is the session evidence',
        status
      })
      continue
    }

    if (current.status === 404) {
      steps.push({ target: target.label, action: 'skipped', reason: 'already gone', status })
      continue
    }

    const response = await call(request, admin, 'DELETE', `/api/appointments/${target.id}`)
    steps.push({
      target: target.label,
      action: 'delete',
      observedStatus: status,
      ...pick(response)
    })
  }

  fixtures.teardown = steps
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps })
  return steps
}

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run. A completed
// appointment is never deleted: its close is this session's evidence.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/**
 * The slice of the fixtures every evidence row carries: ids, statuses and the
 * reason a probe was skipped. The full payload stays in `fixtures.json`.
 */
export const fixtureSummary = fixtures => ({
  marker: fixtures.marker,
  today: fixtures.today ?? null,
  timezoneMismatch: fixtures.timezoneMismatch ?? null,
  professional: fixtures.professional ?? null,
  receptionist: fixtures.receptionist ?? null,
  sessions: fixtures.sessions ?? null,
  patients: fixtures.patients ?? [],
  appointmentType: fixtures.appointmentType ?? null,
  chairs: fixtures.chairs ?? [],
  spec: fixtures.spec ?? null,
  appointments: {
    ok: fixtures.appointments?.ok === true,
    reason: fixtures.appointments?.reason ?? null,
    ids: (fixtures.appointments?.rows ?? []).map(row => row.id),
    statuses: (fixtures.appointments?.rows ?? []).map(row => row.status),
    offsets: (fixtures.appointments?.rows ?? []).map(row => row.offsetMinutes)
  },
  staging: fixtures.staging ?? [],
  lifecycle: fixtures.lifecycle ?? REGISTRY.lifecycle ?? null,
  teardown: fixtures.teardown ?? []
})
