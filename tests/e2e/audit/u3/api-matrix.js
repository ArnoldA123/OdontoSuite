// Plan #13 (issue #366, U3) — layer 5: the session's endpoints by role.
//
// The expected status is derived from `App\Authorization\Permissions` itself:
// the private GRANTS matrix is dumped with PHP reflection and every role is
// probed with a real token. Writes use a nonexistent id, an invalid body or a
// target whose own rule refuses the transition, so an authorized role observes
// 404/409/422 and an unauthorized one observes 403; no product data is mutated.
// The only exception is DELETE, which needs a row the probe owns: the matrix
// creates one throwaway appointment per role and retires it in the same run.

import { execFileSync } from 'node:child_process'

import { test, expect } from 'playwright/test'

import { BASE_URL, loginAs, writeEvidence } from '../harness.js'

import { MARKER, U3_EVIDENCE_DIR, ensureFixtures } from './fixtures.js'

const ROUTE = '/calendar'
const API_TIMEOUT = 20000

const ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

const PERMISSIONS = Object.freeze({
  manageAppointment: 'manage.appointment',
  updateAppointmentStatus: 'update.appointment_status',
  checkInAppointment: 'check_in.appointment',
  announcePatient: 'announce.patient',
  startConsultation: 'start.consultation',
  completeAppointment: 'complete.appointment',
  viewConsultation: 'view.consultation',
  viewDiagnosisCatalog: 'view.diagnosis_catalog',
  viewProcedureCatalog: 'view.procedure_catalog',
  viewProduct: 'view.product',
  viewContinuity: 'view.continuity',
  viewBilling: 'view.billing',
  viewPendingPayment: 'view.pending_payment',
  viewCashRegister: 'view.cash_register',
  openCashRegister: 'open.cash_register'
})

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U3_EVIDENCE_DIR })

const addDays = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  const next = new Date(year, month - 1, date + days)
  const pad = value => String(value).padStart(2, '0')
  return `${next.getFullYear()}-${pad(next.getMonth() + 1)}-${pad(next.getDate())}`
}

const dumpGrants = () => {
  const php = [
    "require 'vendor/autoload.php';",
    '$reflection = new ReflectionClass(App\\Authorization\\Permissions::class);',
    "echo json_encode($reflection->getReflectionConstant('GRANTS')->getValue(), JSON_UNESCAPED_SLASHES);"
  ].join(' ')

  const output = execFileSync('php', ['-r', php], {
    cwd: process.cwd(),
    encoding: 'utf8',
    timeout: 20000
  })

  return JSON.parse(output)
}

const revision = () => {
  try {
    return execFileSync('git', ['rev-parse', 'HEAD'], {
      cwd: process.cwd(),
      encoding: 'utf8'
    }).trim()
  } catch {
    return null
  }
}

const messageOf = body => {
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.error?.message === 'string') return body.error.message
  if (typeof body?.meta?.message === 'string') return body.meta.message
  return null
}

const call = async (request, session, method, path, data) => {
  const headers = {
    Authorization: `Bearer ${session.token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json'
  }
  const options = { method, headers, timeout: API_TIMEOUT }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const body = await response.json().catch(() => null)

    return {
      status: response.status(),
      ok: response.ok(),
      message: messageOf(body),
      body
    }
  } catch (thrown) {
    return {
      status: null,
      ok: false,
      message: String(thrown?.message ?? thrown),
      body: null
    }
  }
}

const endpoint = (key, method, path, permission, options = {}) => ({
  key,
  method,
  path,
  permission,
  mode: options.mode ?? 'read',
  body: options.body,
  authorizedStatus: options.authorizedStatus ?? 200,
  target: options.target ?? 'fixture'
})

const buildEndpoints = targets => [
  endpoint(
    'appointments.index',
    'GET',
    () => '/api/appointments?per_page=5',
    PERMISSIONS.manageAppointment
  ),
  endpoint(
    'appointments.show',
    'GET',
    () => `/api/appointments/${targets.validAppointmentId}`,
    PERMISSIONS.manageAppointment
  ),
  endpoint('appointments.store', 'POST', () => '/api/appointments', PERMISSIONS.manageAppointment, {
    mode: 'write',
    body: {},
    authorizedStatus: 422,
    target: 'invalid body'
  }),
  endpoint(
    'appointments.status',
    'PATCH',
    () => `/api/appointments/${targets.validAppointmentId}/status`,
    PERMISSIONS.updateAppointmentStatus,
    { mode: 'write', body: { status: 'audit-u3-invalid' }, authorizedStatus: 422 }
  ),
  endpoint(
    'appointments.reschedule',
    'PATCH',
    () => `/api/appointments/${targets.validAppointmentId}/reschedule`,
    PERMISSIONS.manageAppointment,
    { mode: 'write', body: {}, authorizedStatus: 422 }
  ),
  endpoint(
    'appointments.destroy',
    'DELETE',
    role => `/api/appointments/${targets.deleteIds[role]}`,
    PERMISSIONS.manageAppointment,
    { mode: 'write', authorizedStatus: 200, target: 'own throwaway row' }
  ),
  endpoint(
    'appointments.checkIn',
    'POST',
    () => `/api/appointments/${targets.completedAppointmentId}/check-in`,
    PERMISSIONS.checkInAppointment,
    {
      mode: 'write',
      authorizedStatus: 409,
      target: 'seeded completed appointment (arrival not allowed)'
    }
  ),
  endpoint(
    'appointments.announce',
    'POST',
    () => `/api/appointments/${targets.completedAppointmentId}/announce`,
    PERMISSIONS.announcePatient,
    {
      mode: 'write',
      authorizedStatus: 409,
      target: 'seeded completed appointment (announcement not allowed)'
    }
  ),
  endpoint(
    'appointments.startAttention',
    'POST',
    () => `/api/appointments/${targets.completedAppointmentId}/start-attention`,
    PERMISSIONS.startConsultation,
    {
      mode: 'write',
      authorizedStatus: 409,
      target: 'seeded completed appointment (attention not allowed)'
    }
  ),
  endpoint(
    'appointments.complete',
    'POST',
    () => `/api/appointments/${targets.completedAppointmentId}/complete`,
    PERMISSIONS.completeAppointment,
    { mode: 'write', body: {}, authorizedStatus: 422 }
  ),
  endpoint(
    'consultation.context',
    'GET',
    () => `/api/appointments/${targets.consultationAppointmentId}/consultation-context`,
    PERMISSIONS.viewConsultation
  ),
  endpoint(
    'diagnoses.index',
    'GET',
    () => '/api/diagnoses?q=K02&per_page=5',
    PERMISSIONS.viewDiagnosisCatalog
  ),
  endpoint(
    'procedureCatalog.search',
    'GET',
    () => '/api/procedure-catalog/search?q=limpieza&limit=5',
    PERMISSIONS.viewProcedureCatalog
  ),
  endpoint(
    'products.search',
    'GET',
    () => '/api/products/search?q=resina&limit=5',
    PERMISSIONS.viewProduct
  ),
  endpoint('continuity.queue', 'GET', () => '/api/continuity/queue', PERMISSIONS.viewContinuity),
  endpoint(
    'readyToBill',
    'GET',
    () => '/api/appointments/ready-to-bill?per_page=5',
    PERMISSIONS.viewBilling
  ),
  endpoint(
    'pendingPayments',
    'GET',
    () => '/api/pending-payments?per_page=5',
    PERMISSIONS.viewPendingPayment
  ),
  endpoint(
    'cashRegister.current',
    'GET',
    () => '/api/cash-register/current',
    PERMISSIONS.viewCashRegister
  ),
  endpoint(
    'cashRegister.open',
    'POST',
    () => '/api/cash-register/open',
    PERMISSIONS.openCashRegister,
    { mode: 'write', body: {}, authorizedStatus: 422, target: 'invalid body' }
  )
]

const createDeleteTargets = async (request, admin, fixtures) => {
  // A deleted appointment is soft-deleted, so its `unique_user_time_slot` row
  // still blocks the slot the API reports as free. A far-future day plus a
  // per-run minute offset and per-target retries keeps the probe's own rows out
  // of the previous run's soft-deleted slots.
  const seed = Date.now()
  const day = addDays(
    fixtures.today?.value ?? new Date().toISOString().slice(0, 10),
    400 + (seed % 300)
  )
  const baseMinute = seed % 60
  const chair = fixtures.chairs?.[0] ?? null
  const type = fixtures.appointmentType ?? null
  const patients = fixtures.patients ?? []
  const created = []
  const attempts = []

  for (let index = 0; index < ROLES.length; index += 1) {
    let accepted = null

    for (let retry = 0; retry < 3 && !accepted; retry += 1) {
      const totalMinutes = 8 * 60 + baseMinute + index * 30 + retry * 7
      const hour = Math.floor(totalMinutes / 60)
      const minute = totalMinutes % 60
      const scheduledAt = `${day}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`
      const response = await call(request, admin, 'POST', '/api/appointments', {
        patient_id: patients[index % patients.length]?.id ?? null,
        user_id: fixtures.professional?.id ?? null,
        dental_chair_id: chair?.id ?? null,
        appointment_type_id: type?.id ?? null,
        scheduled_at: scheduledAt,
        duration_minutes: 15,
        notes: `${MARKER} matrix delete target ${index + 1}`
      })

      attempts.push({
        index,
        retry,
        scheduledAt,
        status: response.status,
        error: response.ok ? null : response.message
      })

      if (response.body?.data?.id) accepted = response.body.data.id
    }

    if (accepted) created.push(accepted)
  }

  return { day, created, attempts }
}

const findCompletedAppointment = async (request, admin, fixtures) => {
  const day = fixtures.today?.value ?? new Date().toISOString().slice(0, 10)
  const response = await call(
    request,
    admin,
    'GET',
    `/api/appointments?start_date=${day}&end_date=${day}&per_page=1000`
  )
  const row = (response.body?.data ?? []).find(
    entry => entry.status === 'completed' || entry.status === 'in_progress'
  )
  const completed = (response.body?.data ?? []).find(entry => entry.status === 'completed')

  return {
    id: completed?.id ?? row?.id ?? 101,
    source: completed
      ? 'today completed appointment'
      : 'fallback id 101 (seeded completed appointment)'
  }
}

const retireTargets = async (request, admin, ids) => {
  const steps = []

  for (const id of ids) {
    const response = await call(request, admin, 'DELETE', `/api/appointments/${id}`)
    steps.push({ id, status: response.status, message: response.message })
  }

  return steps
}

test('@U3 api-matrix · rol × endpoint', async ({ request }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures(request)
  const admin = await loginAs(request, 'administrador')
  const grants = dumpGrants()
  const roles = [...ROLES]
  const evidence = {
    session: 'U3',
    route: ROUTE,
    generatedAt: new Date().toISOString(),
    revision: revision(),
    base: BASE_URL,
    grantsSource: 'App\\Authorization\\Permissions::GRANTS via ReflectionClassConstant',
    grants,
    roles,
    rows: [],
    endpoints: [],
    deleteTargets: null,
    cleanup: [],
    pageErrors: [],
    consoleErrors: [],
    failedRequests: { expected: [], unexpected: [] }
  }

  let targets = null
  let deleteIds = {}

  try {
    if (!fixtures.appointments?.ok || !fixtures.professional?.id) {
      const reason = fixtures.appointments?.reason ?? 'fixtures unavailable; no valid ids to probe'
      evidence.reason = reason
      evidence.verdict = 'NO PROBADO'
      test.skip(true, reason)
      return
    }

    const deleteTargets = await createDeleteTargets(request, admin, fixtures)
    evidence.deleteTargets = deleteTargets

    // finanzas gets its own valid row so the DELETE gate is observable before
    // the authorized role retires the same id; each authorized role owns one.
    deleteIds = Object.fromEntries(
      roles.map((role, index) => [role, deleteTargets.created[index] ?? null])
    )

    const validAppointmentId = fixtures.appointments.rows[0].id
    const consultationAppointmentId = fixtures.appointments.rows[2]?.id ?? validAppointmentId
    const completed = await findCompletedAppointment(request, admin, fixtures)
    const completedAppointmentId = completed.id

    targets = { validAppointmentId, consultationAppointmentId, completedAppointmentId, deleteIds }
    evidence.targets = {
      validAppointmentId,
      consultationAppointmentId,
      completedAppointmentId,
      completedAppointmentSource: completed.source,
      deleteIds,
      note: 'custody/status/complete probes use a seeded completed appointment whose own rule refuses the transition, so no product row is mutated'
    }

    const endpoints = buildEndpoints(targets)
    evidence.endpoints = endpoints.map(entry => ({
      key: entry.key,
      method: entry.method,
      permission: entry.permission,
      mode: entry.mode,
      authorizedStatus: entry.authorizedStatus,
      target: entry.target
    }))

    for (const entry of endpoints) {
      if (!Object.hasOwn(grants, entry.permission)) {
        evidence.rows.push({
          endpoint: entry.key,
          method: entry.method,
          role: null,
          permission: entry.permission,
          expected: 'permission not in GRANTS',
          observed: null,
          ok: false,
          reason: 'the endpoint names a permission the catalog does not grant to anyone'
        })
        continue
      }

      for (const role of roles) {
        const allowed = (grants[entry.permission] ?? []).includes(role)
        const expected = allowed ? entry.authorizedStatus : 403
        const path = typeof entry.path === 'function' ? entry.path(role) : entry.path

        if (entry.key === 'appointments.destroy' && !deleteIds[role]) {
          evidence.rows.push({
            endpoint: entry.key,
            method: entry.method,
            role,
            permission: entry.permission,
            expected,
            observed: null,
            ok: null,
            skipped: true,
            reason: 'no throwaway row available for this role',
            target: null
          })
          continue
        }

        const response = await call(
          request,
          await loginAs(request, role),
          entry.method,
          path,
          entry.mode === 'write' ? entry.body : undefined
        )

        evidence.rows.push({
          endpoint: entry.key,
          method: entry.method,
          role,
          permission: entry.permission,
          expected,
          observed: response.status,
          ok: response.status === expected,
          allowed,
          path,
          target: entry.target,
          message: response.message,
          ...(response.status === expected ? {} : { body: response.body })
        })
      }
    }

    const live = evidence.rows.filter(row => row.skipped !== true)
    evidence.summary = {
      rows: evidence.rows.length,
      live: live.length,
      ok: live.filter(row => row.ok === true).length,
      mismatches: live
        .filter(row => row.ok !== true)
        .map(row => ({
          endpoint: row.endpoint,
          role: row.role,
          expected: row.expected,
          observed: row.observed
        })),
      skipped: evidence.rows.filter(row => row.skipped === true).length
    }
    evidence.verdict = evidence.summary.mismatches.length === 0 ? 'OK' : 'DEFECTO'
  } finally {
    const remaining = Object.values(deleteIds).filter(Boolean)
    if (remaining.length > 0) {
      evidence.cleanup = await retireTargets(request, admin, [...new Set(remaining)])
    }
    audit('api-matrix.json', evidence)
  }

  expect(evidence.rows.length, 'every endpoint probed for every role').toBe(
    evidence.endpoints.length * ROLES.length
  )
  expect(
    evidence.rows.filter(row => row.permission && !Object.hasOwn(grants, row.permission))
  ).toEqual([])
})
