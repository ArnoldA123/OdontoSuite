// Plan #13 (issue #365, U2) — API-only fixtures for the dashboard and Mi día rows.
//
// The audit database is disposable and the seeded rows are not this suite's
// (all of today's appointments belong to the administrator), so the custody and
// laboratory probes create what they need through the public API and retire it
// best-effort at the end. Nothing here writes product code or the database
// directly: every row is a response of a door the app already exposes.
//
// Fixture facts travel in the evidence (`fixtures.json`, plus the `fixtures`
// summary of every row) and a creation that the API refuses returns
// `{ ok: false, reason }` instead of throwing: a skipped probe with its reason is
// a measurement, an exception is not.

import { test } from 'playwright/test'

import { BASE_URL, loginAs, writeEvidence } from '../harness.js'

export const U2_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U2'

export const MARKER = 'AUDIT-U2'

const API_TIMEOUT = 20000
const DURATION_MINUTES = 15
// The plan's three slots. The professional and the chair cannot host two
// overlapping appointments (AppointmentService::findConflicts), so a refused
// slot falls forward to a later one on the same day instead of aborting the
// probe; the evidence keeps both the requested and the accepted offsets.
const REQUESTED_OFFSETS = [15, 20, 25]
const FALLBACK_OFFSETS = [45, 60, 75, 90, 120, 150, 180, 210, 240, 300, 360, 420]
const PROMISED_DAYS = 7

const localDay = date => {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

const addDays = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  const next = new Date(year, month - 1, date + days)
  return localDay(next)
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

const serverToday = async (request, admin) => {
  const response = await call(request, admin, 'GET', '/api/dashboard/appointments-today')
  const date = response.body?.meta?.date

  return {
    value: typeof date === 'string' ? date : localDay(new Date()),
    source: typeof date === 'string' ? 'dashboard/appointments-today meta.date' : 'browser clock'
  }
}

const resolveCatalog = async (request, admin, calls) => {
  const [patientsResponse, typesResponse, chairsResponse] = await Promise.all([
    call(request, admin, 'GET', '/api/patients'),
    call(request, admin, 'GET', '/api/appointment-types/active'),
    call(request, admin, 'GET', '/api/dental-chairs/active')
  ])

  calls.push(patientsResponse, typesResponse, chairsResponse)

  const patients = rowsOf(patientsResponse.body).filter(patient => patient?.is_active !== false)
  const types = rowsOf(typesResponse.body)
  const chairs = rowsOf(chairsResponse.body).filter(chair => chair?.status !== 'maintenance')

  // Row targeting uses the patient name, so the three fixtures must be distinct.
  const distinct = []
  const seen = new Set()

  for (const patient of patients) {
    if (!patient?.full_name || seen.has(patient.full_name)) continue
    seen.add(patient.full_name)
    distinct.push(patient)
  }

  if (distinct.length < 3) {
    return { ok: false, reason: `patients unavailable: ${distinct.length} distinct active rows` }
  }
  if (types.length === 0) return { ok: false, reason: 'appointment types: no active row' }
  if (chairs.length < 3) return { ok: false, reason: `dental chairs unavailable: ${chairs.length}` }

  return {
    ok: true,
    patients: distinct.slice(0, 3),
    type: types[0],
    chairs: chairs.slice(0, 3)
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
        notes: `${MARKER} custody ${index + 1}`
      })

      attempts.push({ index, offset, status: response.status, error: response.error })

      if (response.ok && response.body?.data?.id) {
        created = {
          id: response.body.data.id,
          status: response.body.data.status,
          patient: {
            id: catalog.patients[index].id,
            name: catalog.patients[index].full_name
          },
          chairId: catalog.chairs[index].id,
          offsetMinutes: offset,
          requestedOffsetMinutes: requested,
          scheduledAt: at.toISOString()
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

const createUpcomingAppointment = async (request, admin, professional, catalog, today) => {
  const day = addDays(today.value, 1)
  const scheduledAt = new Date(`${day}T09:00:00`)
  const response = await call(request, admin, 'POST', '/api/appointments', {
    patient_id: catalog.patients[0].id,
    user_id: professional.user.id,
    dental_chair_id: catalog.chairs[0].id,
    appointment_type_id: catalog.type.id,
    scheduled_at: scheduledAt.toISOString(),
    duration_minutes: 30,
    notes: `${MARKER} upcoming`
  })

  if (!response.ok || !response.body?.data?.id) {
    return {
      ok: false,
      reason: `tomorrow's appointment refused: ${response.error ?? `HTTP ${response.status}`}`
    }
  }

  return {
    ok: true,
    id: response.body.data.id,
    status: response.body.data.status,
    scheduledAt: response.body.data.scheduled_at ?? scheduledAt.toISOString(),
    request: pick(response)
  }
}

const stageCustody = async (request, professional, rows) => {
  const steps = []

  if (rows[1]) {
    const arrival = await call(
      request,
      professional,
      'POST',
      `/api/appointments/${rows[1].id}/check-in`
    )
    steps.push({ action: 'check-in', appointment: rows[1].id, ...pick(arrival) })
    rows[1].status = arrival.body?.data?.status ?? rows[1].status
  }

  if (rows[2]) {
    const arrival = await call(
      request,
      professional,
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

// The evidence only needs the status of a door, never its payload.
const pick = response => ({ status: response.status, error: response.error })

const createLabCase = async (request, admin, professional, technician, patient, today) => {
  const stamp = Date.now().toString(36)
  const work = `${MARKER} laboratorio ${stamp}`
  const response = await call(request, admin, 'POST', '/api/lab-cases', {
    patient_id: patient.id,
    professional_id: professional.user.id,
    technician_id: technician.user.id,
    work,
    material: 'Circonio',
    sent_at: today.value,
    promised_at: addDays(today.value, PROMISED_DAYS),
    notes: `${MARKER} laboratorio`
  })

  if (!response.ok || !response.body?.data?.id) {
    return {
      ok: false,
      reason: `lab case refused: ${response.error ?? `HTTP ${response.status}`}`,
      request: pick(response)
    }
  }

  const labCase = response.body.data

  return {
    ok: true,
    id: labCase.id,
    work,
    status: labCase.status,
    professionalId: professional.user.id,
    technicianId: technician.user.id,
    request: pick(response)
  }
}

// A quotation reaches the dashboard pending list only in `sent`/`viewed`, and
// this build exposes no door that writes either: `POST /api/quotations` creates
// a draft, `POST .../approve` and `.../reject` require an already-sent document,
// and `PUT/PATCH` never accepts `status`. Reported, never invented.
const QUOTATION_REASON =
  'unavailable: no HTTP door writes quotation status "sent" (store creates draft; approve/reject require sent|viewed; update ignores status)'

const createPendingFixtures = async (request, admin, patient) => {
  const stamp = Date.now().toString(36)
  const planResponse = await call(request, admin, 'POST', '/api/treatment-plans', {
    patient_id: patient.id,
    title: `${MARKER} plan ${stamp}`,
    notes: `${MARKER} pending`,
    items: [
      {
        description: `${MARKER} línea`,
        procedure_name: `${MARKER} procedimiento`,
        quantity: 1,
        unit_cost: 120
      }
    ]
  })

  if (!planResponse.ok || !planResponse.body?.data?.id) {
    return {
      ok: false,
      reason: `treatment plan refused: ${planResponse.error ?? `HTTP ${planResponse.status}`}`,
      plan: pick(planResponse),
      quotations: QUOTATION_REASON
    }
  }

  const planId = planResponse.body.data.id
  const transition = await call(
    request,
    admin,
    'POST',
    `/api/treatment-plans/${planId}/change-status`,
    { status: 'proposed' }
  )

  return {
    ok: transition.ok,
    reason: transition.ok ? null : `change-status refused: ${transition.error}`,
    treatmentPlan: {
      id: planId,
      status: transition.body?.data?.status ?? 'draft',
      patientName: patient.full_name
    },
    requests: [pick(planResponse), pick(transition)],
    quotations: QUOTATION_REASON
  }
}

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    createdAt: new Date().toISOString(),
    actor: 'administrador',
    appointments: { ok: false, reason: 'not built', rows: [], attempts: [] },
    upcoming: { ok: false, reason: 'not built' },
    custody: [],
    lab: { ok: false, reason: 'not built' },
    pending: { ok: false, reason: 'not built', quotations: QUOTATION_REASON },
    pendingFixture: QUOTATION_REASON,
    teardown: []
  }

  const admin = await loginAs(request, 'administrador')
  const professional = await loginAs(request, 'odontologo')
  const technician = await loginAs(request, 'tecnico_dental')
  const calls = []
  const today = await serverToday(request, admin)

  built.professional = {
    id: professional.user.id,
    name: professional.user.name,
    username: professional.user.username
  }
  built.today = { ...today, localToday: localDay(new Date()) }
  built.timezoneMismatch = today.value !== localDay(new Date())

  const catalog = await resolveCatalog(request, admin, calls)

  if (!catalog.ok) {
    built.appointments = { ok: false, reason: catalog.reason, rows: [], attempts: [] }
    built.catalogRequests = calls.map(pick)
    return built
  }

  built.patients = catalog.patients.map(patient => ({ id: patient.id, name: patient.full_name }))
  built.appointmentType = { id: catalog.type.id, name: catalog.type.name }
  built.chairs = catalog.chairs.map(chair => ({ id: chair.id, name: chair.name }))

  const appointments = await createAppointments(request, admin, professional, catalog, today)
  built.appointments = appointments
  built.catalogRequests = calls.map(pick)
  built.spec = {
    requestedOffsetsMinutes: REQUESTED_OFFSETS,
    durationMinutes: DURATION_MINUTES,
    conflictPolicy: 'a refused or overlapping slot falls forward to a later one on the same day'
  }

  if (!appointments.ok) return built

  built.custody = await stageCustody(request, professional, appointments.rows)
  built.upcoming = await createUpcomingAppointment(request, admin, professional, catalog, today)
  built.lab = await createLabCase(
    request,
    admin,
    professional,
    technician,
    catalog.patients[0],
    today
  )
  built.pending = await createPendingFixtures(request, admin, catalog.patients[0])
  built.pendingFixture = built.pending.ok
    ? `treatment-plans:${built.pending.treatmentPlan.id}`
    : `unavailable: ${built.pending.reason}`

  return built
}

const publishFixtures = fixtures =>
  writeEvidence(
    'fixtures.json',
    {
      route: '/dashboard',
      role: fixtures.professional?.username ?? null,
      pageErrors: [],
      consoleErrors: [],
      failedRequests: { expected: [], unexpected: [] },
      shots: [],
      ...fixtures
    },
    { dir: U2_EVIDENCE_DIR }
  )

let fixturesPromise = null
let builtFixtures = null

/**
 * Builds the U2 fixtures once per worker and returns them to every row that
 * needs them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = request => {
  if (!fixturesPromise) {
    fixturesPromise = buildFixtures(request)
      .catch(thrown => ({
        marker: MARKER,
        createdAt: new Date().toISOString(),
        appointments: { ok: false, reason: `fixture build failed: ${thrown.message}` },
        lab: { ok: false, reason: `fixture build failed: ${thrown.message}` },
        pending: { ok: false, reason: `fixture build failed: ${thrown.message}` },
        pendingFixture: `unavailable: ${thrown.message}`
      }))
      .then(fixtures => {
        builtFixtures = fixtures
        publishFixtures(fixtures)
        return fixtures
      })
  }

  return fixturesPromise
}

/**
 * Moves the fixture case forward through the phase door. The laboratory row
 * asserts the readiness notification, so the transition happens while the desk
 * is open, through the API the app itself exposes.
 */
export const advanceLabCase = async (request, labCaseId, phase) => {
  const admin = await loginAs(request, 'administrador')
  const response = await call(request, admin, 'POST', `/api/lab-cases/${labCaseId}/phase`, {
    phase
  })

  if (response.ok && builtFixtures?.lab?.id === labCaseId) {
    builtFixtures.lab.status = response.body?.data?.status ?? phase
  }

  return pick(response)
}

const retire = async (request, fixtures) => {
  const admin = await loginAs(request, 'administrador')
  const steps = []

  if (fixtures.lab?.ok && fixtures.lab.status === 'delivered') {
    const response = await call(request, admin, 'POST', `/api/lab-cases/${fixtures.lab.id}/phase`, {
      phase: 'placed'
    })
    steps.push({ target: `lab-case:${fixtures.lab.id}`, action: 'placed', ...pick(response) })
    if (response.ok) fixtures.lab.status = 'placed'
  }

  // A proposed plan cannot be deleted (only a draft can), so the retirement is
  // the forward door that removes it from the pending list: cancel it.
  if (fixtures.pending?.treatmentPlan?.id) {
    const response = await call(
      request,
      admin,
      'POST',
      `/api/treatment-plans/${fixtures.pending.treatmentPlan.id}/change-status`,
      { status: 'cancelled' }
    )
    steps.push({
      target: `treatment-plan:${fixtures.pending.treatmentPlan.id}`,
      action: 'cancelled',
      ...pick(response)
    })
  }

  for (const appointment of fixtures.appointments?.rows ?? []) {
    const response = await call(request, admin, 'DELETE', `/api/appointments/${appointment.id}`)
    steps.push({ target: `appointment:${appointment.id}`, action: 'delete', ...pick(response) })
  }

  if (fixtures.upcoming?.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/appointments/${fixtures.upcoming.id}`
    )
    steps.push({
      target: `appointment:${fixtures.upcoming.id}`,
      action: 'delete',
      ...pick(response)
    })
  }

  fixtures.teardown = steps
  publishFixtures(fixtures)
  return steps
}

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!builtFixtures) return
  await retire(request, builtFixtures).catch(() => {})
})

/**
 * The slice of the fixtures every evidence row carries: ids, statuses and the
 * reason a probe was skipped. The full payload stays in `fixtures.json`.
 */
export const fixtureSummary = fixtures => ({
  marker: fixtures.marker,
  professional: fixtures.professional ?? null,
  spec: fixtures.spec ?? null,
  timezoneMismatch: fixtures.timezoneMismatch ?? null,
  appointments: {
    ok: fixtures.appointments?.ok === true,
    reason: fixtures.appointments?.reason ?? null,
    ids: (fixtures.appointments?.rows ?? []).map(row => row.id),
    statuses: (fixtures.appointments?.rows ?? []).map(row => row.status),
    offsets: (fixtures.appointments?.rows ?? []).map(row => row.offsetMinutes)
  },
  upcoming: {
    ok: fixtures.upcoming?.ok === true,
    reason: fixtures.upcoming?.reason ?? null,
    id: fixtures.upcoming?.id ?? null,
    scheduledAt: fixtures.upcoming?.scheduledAt ?? null
  },
  custody: fixtures.custody ?? [],
  lab: {
    ok: fixtures.lab?.ok === true,
    reason: fixtures.lab?.reason ?? null,
    id: fixtures.lab?.id ?? null,
    work: fixtures.lab?.work ?? null,
    status: fixtures.lab?.status ?? null
  },
  pending: {
    ok: fixtures.pending?.ok === true,
    reason: fixtures.pending?.reason ?? null,
    treatmentPlan: fixtures.pending?.treatmentPlan ?? null,
    quotations: fixtures.pending?.quotations ?? QUOTATION_REASON
  },
  pendingFixture: fixtures.pendingFixture ?? null
})
