// Plan #13 (issue #367, U4) — API-only fixtures for the patients audit rows.
//
// The audit database is disposable but the seeded patients are not ours: every
// write this session makes is a row whose `first_name` is `AUDIT-U4`, created
// through the public API as administrador and retired by the final hook. The
// session needs five patients because each probe owns one population:
//
//   P1 empty        render + tab walkthrough (no relations, no ledger)
//   P2 editable     the edit row (PUT through the list modal)
//   P3 deletable    the delete row (confirmation through the list)
//   P4 charge       the manual-charge and statement rows
//   P5 quotation    the quotations tab (drafted here, retired here)
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.
//
// Playwright re-evaluates the modules per test, so the fixtures travel through
// `globalThis` (fast path) and mirror to `fixtures-state.json` (worker-restart
// path), the same registry U3 uses.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { test } from 'playwright/test'

import { loginAs, writeEvidence } from '../harness.js'

import { call, localDay } from './helpers.js'

export const U4_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U4'

export const MARKER = 'AUDIT-U4'

const RUN_TOKEN = String(Date.now()).slice(-6)

// The permission slice the rows read: who may write the patient and which tab
// gate each detail surface publishes.
const PATIENT_PERMISSIONS = Object.freeze([
  'create.patient',
  'update.patient',
  'delete.patient',
  'export.patient',
  'view.patient_statement',
  'manage.patient_charge',
  'view.lab_case',
  'manage.lab_case',
  'manage.medical_record',
  'manage.specialty_record',
  'manage.treatment_plan',
  'view.quotation',
  'view.audit_log'
])

const SLOTS = Object.freeze([
  { key: 'p1', slot: 'Uno', purpose: 'render-empty' },
  { key: 'p2', slot: 'Dos', purpose: 'editable' },
  { key: 'p3', slot: 'Tres', purpose: 'deletable' },
  { key: 'p4', slot: 'Cuatro', purpose: 'charge-and-statement' },
  { key: 'p5', slot: 'Cinco', purpose: 'quotation' }
])

const patientBody = (slot, index) => ({
  first_name: MARKER,
  last_name: `${MARKER} ${slot} ${RUN_TOKEN}`,
  document_number: `U4-${RUN_TOKEN}-${index + 1}`,
  birth_date: '1992-03-04',
  gender: 'female',
  is_active: true
})

const permissionSlice = user =>
  Object.fromEntries(PATIENT_PERMISSIONS.map(key => [key, (user?.permissions ?? []).includes(key)]))

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    createdAt: new Date().toISOString(),
    day: localDay(),
    actor: 'administrador',
    ok: false,
    reason: 'not built',
    patients: [],
    quotation: { ok: false, reason: 'not requested' },
    charge: null,
    createdRows: [],
    sessions: {},
    teardown: [],
    leftovers: []
  }

  const admin = await loginAs(request, 'administrador')
  const roles = ['recepcionista', 'odontologo', 'finanzas']

  built.sessions.administrador = {
    id: admin.user.id,
    name: admin.user.name,
    username: admin.user.username,
    permissions: permissionSlice(admin.user)
  }

  for (const role of roles) {
    const session = await loginAs(request, role)
    built.sessions[role] = {
      id: session.user.id,
      name: session.user.name,
      username: session.user.username,
      permissions: permissionSlice(session.user)
    }
  }

  for (let index = 0; index < SLOTS.length; index += 1) {
    const slot = SLOTS[index]
    const body = patientBody(slot.slot, index)
    const response = await call(request, admin, 'POST', '/api/patients', body)

    if (!response.ok) {
      built.reason = `patient ${slot.key} refused: ${response.message ?? response.status}`
      return built
    }

    built.patients.push({
      key: slot.key,
      id: response.body?.data?.id ?? null,
      slot: slot.slot,
      purpose: slot.purpose,
      lastName: body.last_name,
      documentNumber: body.document_number,
      status: response.status
    })
  }

  const quotationTarget = built.patients.find(patient => patient.key === 'p5')
  const quotation = await call(request, admin, 'POST', '/api/quotations', {
    patient_id: quotationTarget.id,
    quotation_date: localDay(),
    subtotal: 150,
    notes: `${MARKER} presupuesto de auditoría`,
    items: [
      {
        description: `${MARKER} consulta de auditoría`,
        quantity: 1,
        unit_price: 150
      }
    ]
  })

  built.quotation = quotation.ok
    ? {
        ok: true,
        id: quotation.body?.data?.id ?? null,
        status: quotation.body?.data?.status ?? null
      }
    : {
        ok: false,
        reason: quotation.message ?? `HTTP ${quotation.status}`,
        status: quotation.status
      }

  built.ok = true
  built.reason = null

  return built
}

const publishFixtures = fixtures =>
  writeEvidence(
    'fixtures.json',
    {
      route: '/patients',
      slug: 'patients',
      role: 'administrador',
      pageErrors: [],
      consoleErrors: [],
      failedRequests: { expected: [], unexpected: [] },
      shots: [],
      ...fixtures
    },
    { dir: U4_EVIDENCE_DIR }
  )

const STATE_FILE = resolve(process.cwd(), U4_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U4_FIXTURES__ ??= {
  promise: null,
  fixtures: null,
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
    out: U4_EVIDENCE_DIR,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }
  REGISTRY.state = next
  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

const idsOf = fixtures => [
  ...(fixtures.patients ?? []).map(patient => patient.id),
  ...(fixtures.createdRows ?? []).map(row => row.id)
]

const reuseStoredFixtures = async (request, stored) => {
  const storedFixtures = stored?.fixtures

  if (!storedFixtures?.ok || stored.retired === true) return null
  if (stored.day && stored.day !== localDay()) return null

  const admin = await loginAs(request, 'administrador')

  for (const id of idsOf(storedFixtures)) {
    if (!id) return null
    const current = await call(request, admin, 'GET', `/api/patients/${id}`)
    if (current.status !== 200) return null
  }

  REGISTRY.fixtures = storedFixtures
  publishFixtures(storedFixtures)
  return storedFixtures
}

/**
 * Builds the U4 fixtures once per worker and hands them to every row that needs
 * them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = request => {
  if (!REGISTRY.promise) {
    REGISTRY.promise = (async () => {
      const stored = readState()
      const reused = stored ? await reuseStoredFixtures(request, stored) : null
      if (reused) return reused

      const fixtures = await buildFixtures(request).catch(thrown => ({
        marker: MARKER,
        token: RUN_TOKEN,
        createdAt: new Date().toISOString(),
        ok: false,
        reason: `fixture build failed: ${thrown.message}`,
        patients: [],
        quotation: { ok: false, reason: 'build failed' },
        charge: null,
        createdRows: [],
        sessions: {},
        teardown: [],
        leftovers: []
      }))

      REGISTRY.fixtures = fixtures
      writeState({
        createdAt: fixtures.createdAt,
        day: fixtures.day ?? localDay(),
        retired: false,
        fixtures
      })
      publishFixtures(fixtures)
      return fixtures
    })()
  }

  return REGISTRY.promise
}

export const patientBySlot = (fixtures, key) =>
  (fixtures?.patients ?? []).find(patient => patient.key === key) ?? null

/** The patient the list-create row wrote through the UI. */
export const registerCreatedPatient = row => {
  if (!REGISTRY.fixtures) return null
  REGISTRY.fixtures.createdRows = [...(REGISTRY.fixtures.createdRows ?? []), row]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return row
}

/** The charge the detail row wrote; the ledger is immutable, so it is reported. */
export const registerCharge = charge => {
  if (!REGISTRY.fixtures) return null
  REGISTRY.fixtures.charge = charge
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return charge
}

const retire = async (request, fixtures) => {
  const admin = await loginAs(request, 'administrador')
  const steps = []
  const leftovers = []

  if (fixtures.quotation?.ok && fixtures.quotation.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/quotations/${fixtures.quotation.id}`
    )
    steps.push({
      target: `quotation:${fixtures.quotation.id}`,
      action: 'delete',
      status: response.status,
      message: response.message
    })

    if (response.ok) {
      leftovers.push({
        type: 'quotation',
        id: fixtures.quotation.id,
        reason: 'soft-deleted row (Quotation uses SoftDeletes); the API has no hard delete'
      })
    }
  }

  for (const row of fixtures.createdRows ?? []) {
    const response = await call(request, admin, 'DELETE', `/api/patients/${row.id}`)
    steps.push({ target: `patient:${row.id}`, action: 'delete', status: response.status })
  }

  for (const patient of fixtures.patients ?? []) {
    const response = await call(request, admin, 'DELETE', `/api/patients/${patient.id}`)
    const alreadyGone = response.status === 404

    steps.push({
      target: `patient:${patient.id}`,
      label: patient.lastName,
      action: alreadyGone ? 'skipped-already-gone' : 'delete',
      status: response.status,
      message: response.message
    })

    // A 404 means the row no longer exists (P3 is deleted through the list UI);
    // only a refused delete is a leftover.
    if (!response.ok && !alreadyGone) {
      leftovers.push({
        type: 'patient',
        id: patient.id,
        label: patient.lastName,
        reason: response.message ?? `HTTP ${response.status}`
      })
    }
  }

  if (fixtures.charge?.id) {
    leftovers.push({
      type: 'patient_charge',
      id: fixtures.charge.id,
      patientId: fixtures.charge.patientId ?? null,
      amount: fixtures.charge.amount ?? null,
      reason:
        'the patient ledger is immutable (ADR-0015): patient_charges has no delete door, ' +
        'so the charge stays after the patient is retired'
    })
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps, leftovers })
  return steps
}

/**
 * Retires every row this session created. Exported because the brief names the
 * door; the hook below is what every run reaches.
 */
export const retireFixtures = request => retire(request, REGISTRY.fixtures)

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => ({
  marker: fixtures?.marker ?? MARKER,
  token: fixtures?.token ?? null,
  ok: fixtures?.ok === true,
  reason: fixtures?.reason ?? null,
  patients: (fixtures?.patients ?? []).map(patient => ({
    key: patient.key,
    id: patient.id,
    slot: patient.slot,
    lastName: patient.lastName,
    purpose: patient.purpose
  })),
  quotation: fixtures?.quotation ?? null,
  charge: fixtures?.charge ?? null,
  createdRows: (fixtures?.createdRows ?? []).map(row => ({ id: row.id, label: row.label })),
  sessions: fixtures?.sessions ?? {},
  teardown: fixtures?.teardown ?? [],
  leftovers: fixtures?.leftovers ?? []
})
