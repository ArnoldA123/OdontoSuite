// Plan #13 (issue #369, U6) — the quotations session's own rows.
//
// Everything the surfaces need is created through the product's own doors
// (`POST /api/procedure-catalog`, `/api/payers`, `/api/patients`,
// `/api/treatment-plans`, `/api/quotations`, and the two tariff endpoints), and
// every row is marked `AUDIT-U6`.
//
// The one exception is the state the SPA cannot write (U6 L5): a quotation in
// `sent` is produced only by `BillingService` when a closed appointment issues
// one, and the SPA never offers that transition. The approve, reject, PDF and
// edit states need those rows, so this module places them with a guarded
// `php artisan tinker` child process:
//
//   * the first statement asserts the connection answers `odontosuite_audit`
//     and throws otherwise, so the development database can never be written by
//     a stray `.env`;
//   * the script re-reads every row and refuses to touch one whose `notes` do not
//     carry the `AUDIT-U6` marker, so a seeded quotation is never flipped;
//   * the flip is declared as a fixture in `fixtures.json` and reported as
//     `fixtures.statusWrites`, never as observed product behaviour.
//
// Deletion uses the API only. Everything the API refuses to delete (a quotation
// that is no longer a draft, a plan that is no longer a draft, the payer, the
// tariff rows and the patient behind them) is declared as residue.
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.
//
// Playwright re-evaluates the modules per test, so the fixtures travel through
// `globalThis` (fast path) and mirror to `fixtures-state.json` (worker-restart
// path), the same registry U3/U4/U5 use.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { test } from 'playwright/test'

import { loginAs } from '../harness.js'

import { pieceScript, php, statusScript } from './fixtures-guarded.js'

import { U6_EVIDENCE_DIR, addDaysLocal, audit, call, localDay, round2, stamp } from './helpers.js'

export const MARKER = 'AUDIT-U6'

const RUN_TOKEN = String(Date.now()).slice(-6)

export const PROCEDURE_COST = 120
export const PAYER_PRICE = 140
export const BRANCH_PRICE = 150
export const FREE_TEXT_LINE = { quantity: 2, unitCost: 30 }
export const MANUAL_TOTAL = 118

const number = value => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? round2(parsed) : null
}

const snapshotOf = payload => {
  if (!payload) return null

  return {
    id: payload.id ?? null,
    number: payload.quotation_number ?? null,
    status: payload.status ?? null,
    quotationDate: payload.quotation_date ?? null,
    validUntil: payload.valid_until ?? null,
    patientId: payload.patient_id ?? null,
    planId: payload.treatment_plan_id ?? null,
    payerId: payload.payer_id ?? null,
    subtotal: number(payload.subtotal),
    discountPercentage: number(payload.discount_percentage),
    discountAmount: number(payload.discount_amount),
    taxPercentage: number(payload.tax_percentage),
    taxAmount: number(payload.tax_amount),
    total: number(payload.total_amount),
    items: (Array.isArray(payload.items) ? payload.items : []).map(item => ({
      id: item.id,
      description: item.description,
      quantity: number(item.quantity),
      unitPrice: number(item.unit_price),
      subtotal: number(item.subtotal),
      procedureCatalogId: item.procedure_catalog_id ?? null,
      dentalPieceId: item.dental_piece_id ?? null,
      siteCode: item.site_code ?? null,
      priceSource: item.price_source ?? null,
      priceRowId: item.price_row_id ?? null
    })),
    approvals: (Array.isArray(payload.approvals) ? payload.approvals : []).length
  }
}

const planSnapshot = payload => {
  if (!payload) return null

  return {
    id: payload.id ?? null,
    number: payload.plan_number ?? null,
    title: payload.title ?? null,
    status: payload.status ?? null,
    totalCost: number(payload.total_cost),
    finalCost: number(payload.final_cost),
    patientId: payload.patient_id ?? null,
    items: (Array.isArray(payload.items) ? payload.items : []).map(item => ({
      id: item.id,
      procedureName: item.procedure_name,
      description: item.procedure_description,
      quantity: number(item.quantity),
      unitCost: number(item.unit_cost),
      totalCost: number(item.total_cost),
      phaseNumber: item.phase_number,
      sequence: item.sequence ?? null,
      priority: item.priority ?? null,
      procedureCatalogId: item.procedure_catalog_id ?? null,
      dentalPieceId: item.dental_piece_id ?? null,
      executedQuantity: item.executed_quantity ?? null,
      consumptions: Array.isArray(item.consumptions) ? item.consumptions.length : null
    })),
    currentVersion: payload.current_version ?? null,
    progress: payload.progress ?? null
  }
}

/** Creates one quotation through `POST /api/quotations` and reads it back. */
const createQuotation = async (request, session, payload, key) => {
  const created = await call(request, session, 'POST', '/api/quotations', payload)
  const id = created.body?.data?.id ?? null
  const detail = id ? await call(request, session, 'GET', `/api/quotations/${id}`) : null

  return {
    key,
    request: { method: 'POST', path: '/api/quotations', status: created.status },
    ok: created.ok && id !== null,
    message: created.message,
    errors: created.errors,
    id,
    snapshot: snapshotOf(detail?.body?.data)
  }
}

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day: localDay(),
    ok: false,
    reason: 'not built',
    database: null,
    sessions: {},
    procedure: null,
    payer: null,
    tariffs: {},
    patient: null,
    piece: null,
    plan: null,
    quotations: {},
    statusWrites: [],
    declarations: {},
    reach: {},
    createdByWalk: [],
    residues: [],
    teardown: [],
    leftovers: []
  }

  const admin = await loginAs(request, 'administrador')
  built.sessions.administrador = {
    id: admin.user.id,
    name: admin.user.name,
    username: admin.user.username,
    branchId: admin.user.branch_id ?? null
  }

  for (const role of ['finanzas', 'recepcionista', 'odontologo']) {
    const session = await loginAs(request, role)
    built.sessions[role] = {
      id: session.user.id,
      name: session.user.name,
      username: session.user.username,
      branchId: session.user.branch_id ?? null
    }
  }

  let scripted

  try {
    scripted = php(pieceScript(MARKER))
  } catch (thrown) {
    built.reason = `fixture build refused: ${String(thrown.message).slice(0, 400)}`
    return built
  }

  built.database = scripted.database
  built.piece = { id: scripted.piece.id, fdi: scripted.piece.fdi, name: scripted.piece.name }

  // The patient belongs to the run, not to the seed: the logged surfaces are
  // filtered by it (`/quotations?patient_id=`), so the evidence of one run never
  // reads the residue of another. It is created through its own door.
  const patient = await call(request, admin, 'POST', '/api/patients', {
    first_name: MARKER,
    last_name: `paciente ${RUN_TOKEN}`,
    document_number: `AU6${RUN_TOKEN}`,
    email: `audit-u6-${RUN_TOKEN}@audit.local`,
    notes: `${MARKER} patient ${RUN_TOKEN}`
  })

  built.patient = {
    id: patient.body?.data?.id ?? null,
    name: `${MARKER} paciente ${RUN_TOKEN}`,
    lastName: `paciente ${RUN_TOKEN}`,
    document: `AU6${RUN_TOKEN}`,
    status: patient.status,
    message: patient.message,
    source: 'POST /api/patients (the run owns the row)'
  }

  if (!built.patient.id) {
    built.reason = `the patient was refused: HTTP ${patient.status} ${patient.message ?? ''}`
    return built
  }

  // The audit database seeds 41 procedures, all with `site_type = none`, 0
  // payers and 0 tariff rows (U6 L4), so the "procedure + site + convenio" line
  // cannot be exercised with the seed: the session creates what is missing.
  const code = `AUDITU6-${RUN_TOKEN}`

  const procedure = await call(request, admin, 'POST', '/api/procedure-catalog', {
    code,
    name: `${MARKER} corona de prueba ${RUN_TOKEN}`,
    description: `${MARKER} procedimiento de la sesión de auditoría`,
    site_type: 'tooth',
    default_cost: PROCEDURE_COST,
    default_duration_minutes: 30,
    is_active: true
  })

  built.procedure = {
    id: procedure.body?.data?.id ?? null,
    code,
    name: `${MARKER} corona de prueba ${RUN_TOKEN}`,
    defaultCost: PROCEDURE_COST,
    siteType: 'tooth',
    status: procedure.status,
    message: procedure.message
  }

  if (!built.procedure.id) {
    built.reason = `the catalog procedure was refused: HTTP ${procedure.status} ${procedure.message ?? ''}`
    return built
  }

  const payerCode = `auditu6-${RUN_TOKEN}`
  const payer = await call(request, admin, 'POST', '/api/payers', {
    code: payerCode,
    name: `${MARKER} convenio ${RUN_TOKEN}`,
    is_active: true
  })

  built.payer = {
    id: payer.body?.data?.id ?? null,
    code: payerCode,
    name: `${MARKER} convenio ${RUN_TOKEN}`,
    status: payer.status,
    message: payer.message
  }

  const branches = await call(request, admin, 'GET', '/api/branches/active')
  const branchId = (branches.body?.data ?? [])[0]?.id ?? null

  const payerPrice = built.payer.id
    ? await call(request, admin, 'PUT', `/api/payers/${built.payer.id}/procedure-prices`, {
        items: [
          {
            procedure_catalog_id: built.procedure.id,
            price: PAYER_PRICE,
            is_active: true
          }
        ]
      })
    : { status: null, ok: false, message: 'no payer row' }

  const branchPrice =
    branchId && built.procedure.id
      ? await call(request, admin, 'PUT', `/api/branches/${branchId}/procedure-prices`, {
          items: [
            {
              procedure_catalog_id: built.procedure.id,
              price: BRANCH_PRICE,
              is_active: true
            }
          ]
        })
      : { status: null, ok: false, message: 'no active branch to price' }

  // The tariff doors are measured, never forced: U6 records the status each one
  // answered so the walk can say which price the cascade could resolve.
  built.tariffs = {
    payer: {
      door: `/api/payers/${built.payer.id}/procedure-prices`,
      price: PAYER_PRICE,
      status: payerPrice.status,
      ok: payerPrice.ok === true,
      message: payerPrice.message,
      errors: payerPrice.errors
    },
    branch: {
      door: `/api/branches/${branchId}/procedure-prices`,
      branchId,
      price: BRANCH_PRICE,
      status: branchPrice.status,
      ok: branchPrice.ok === true,
      message: branchPrice.message,
      errors: branchPrice.errors
    }
  }

  const plan = await call(request, admin, 'POST', '/api/treatment-plans', {
    patient_id: built.patient.id,
    title: `${MARKER} plan ${RUN_TOKEN}`,
    description: `${MARKER} plan de dos líneas: catálogo con sitio y texto libre`,
    notes: `${MARKER} plan fixture`,
    items: [
      {
        procedure_name: built.procedure.name,
        description: `${MARKER} línea de catálogo`,
        quantity: 1,
        unit_cost: PROCEDURE_COST,
        procedure_catalog_id: built.procedure.id,
        dental_piece_id: built.piece.id,
        phase_number: 1
      },
      {
        procedure_name: `${MARKER} línea libre ${RUN_TOKEN}`,
        description: `${MARKER} línea sin catálogo`,
        quantity: FREE_TEXT_LINE.quantity,
        unit_cost: FREE_TEXT_LINE.unitCost,
        phase_number: 2
      }
    ]
  })

  const planId = plan.body?.data?.id ?? null
  const planDetail = planId
    ? await call(request, admin, 'GET', `/api/treatment-plans/${planId}`)
    : null

  built.plan = {
    request: { method: 'POST', path: '/api/treatment-plans', status: plan.status },
    ok: plan.ok === true && planId !== null,
    message: plan.message,
    errors: plan.errors,
    id: planId,
    title: `${MARKER} plan ${RUN_TOKEN}`,
    snapshot: planSnapshot(planDetail?.body?.data),
    expected: {
      catalogLine: PAYER_PRICE,
      freeTextLine: round2(FREE_TEXT_LINE.quantity * FREE_TEXT_LINE.unitCost),
      gross: round2(PAYER_PRICE + FREE_TEXT_LINE.quantity * FREE_TEXT_LINE.unitCost)
    }
  }

  if (!built.plan.ok) {
    built.reason = `the treatment plan was refused: HTTP ${plan.status} ${plan.message ?? ''}`
    return built
  }

  const today = localDay()
  const validUntil = addDaysLocal(today, 30)
  const base = {
    patient_id: built.patient.id,
    quotation_date: today,
    valid_until: validUntil
  }

  const catalogue = [
    {
      key: 'fromPlan',
      payload: {
        ...base,
        treatment_plan_id: built.plan.id,
        payer_id: built.payer.id,
        notes: `${MARKER} fixture fromPlan`,
        subtotal: built.plan.expected.gross,
        discount_percentage: 0,
        discount_amount: 0
      }
    },
    {
      key: 'manual',
      payload: {
        ...base,
        notes: `${MARKER} fixture manual`,
        subtotal: MANUAL_TOTAL,
        discount_percentage: 0,
        discount_amount: 0,
        items: [
          {
            description: `${MARKER} línea manual ${RUN_TOKEN}`,
            quantity: 1,
            unit_price: MANUAL_TOTAL
          }
        ]
      }
    },
    {
      key: 'approved',
      payload: {
        ...base,
        notes: `${MARKER} fixture approved`,
        subtotal: MANUAL_TOTAL,
        items: [{ description: `${MARKER} línea aprobada`, quantity: 1, unit_price: MANUAL_TOTAL }]
      }
    },
    {
      key: 'sentForReject',
      payload: {
        ...base,
        notes: `${MARKER} fixture sentForReject`,
        subtotal: MANUAL_TOTAL,
        items: [
          { description: `${MARKER} línea a rechazar`, quantity: 1, unit_price: MANUAL_TOTAL }
        ]
      }
    },
    {
      key: 'sentForApprove',
      payload: {
        ...base,
        notes: `${MARKER} fixture sentForApprove`,
        subtotal: MANUAL_TOTAL,
        items: [{ description: `${MARKER} línea a aprobar`, quantity: 1, unit_price: MANUAL_TOTAL }]
      }
    },
    {
      key: 'sentForStale',
      payload: {
        ...base,
        notes: `${MARKER} fixture sentForStale`,
        subtotal: MANUAL_TOTAL,
        items: [
          {
            description: `${MARKER} línea para la decisión obsoleta`,
            quantity: 1,
            unit_price: MANUAL_TOTAL
          }
        ]
      }
    },
    {
      key: 'sentForDialogs',
      payload: {
        ...base,
        notes: `${MARKER} fixture sentForDialogs`,
        subtotal: MANUAL_TOTAL,
        items: [
          {
            description: `${MARKER} línea para los diálogos`,
            quantity: 1,
            unit_price: MANUAL_TOTAL
          }
        ]
      }
    }
  ]

  for (const entry of catalogue) {
    const created = await createQuotation(request, admin, entry.payload, entry.key)
    built.quotations[entry.key] = created

    if (!created.ok) {
      built.reason = `the quotation "${entry.key}" was refused: HTTP ${created.request.status} ${created.message ?? ''}`
      return built
    }
  }

  const writes = [
    { key: 'approved', id: built.quotations.approved.id, status: 'approved' },
    { key: 'sentForReject', id: built.quotations.sentForReject.id, status: 'sent' },
    { key: 'sentForApprove', id: built.quotations.sentForApprove.id, status: 'sent' },
    { key: 'sentForStale', id: built.quotations.sentForStale.id, status: 'sent' },
    { key: 'sentForDialogs', id: built.quotations.sentForDialogs.id, status: 'sent' }
  ]

  try {
    const applied = php(statusScript(MARKER, writes))
    built.statusWrites = applied.applied.map(row => ({
      ...row,
      source: 'guarded tinker (the SPA has no door to sent/approved)',
      declaration: 'fixture state, not observed product behaviour'
    }))
  } catch (thrown) {
    built.reason = `the state flip was refused: ${String(thrown.message).slice(0, 400)}`
    return built
  }

  // Re-read every row after the flip so the session measures the state the API
  // actually persists.
  for (const entry of Object.values(built.quotations)) {
    const detail = await call(request, admin, 'GET', `/api/quotations/${entry.id}`)
    entry.request.readBack = detail.status
    entry.snapshot = snapshotOf(detail.body?.data)
  }

  const list = await call(request, admin, 'GET', '/api/quotations?per_page=100')
  const listRows = Array.isArray(list.body?.data) ? list.body.data : []
  const ours = listRows.filter(row => String(row.notes ?? '').startsWith(MARKER))

  built.reach = {
    list: {
      status: list.status,
      rows: listRows.length,
      found: ours.length,
      expected: Object.keys(built.quotations).length,
      ids: ours.map(row => row.id)
    },
    plan: await call(request, admin, 'GET', `/api/treatment-plans/${built.plan.id}`).then(
      response => ({
        status: response.status,
        found: response.body?.data?.id === built.plan.id
      })
    )
  }

  built.declarations = {
    patient:
      'created through POST /api/patients so the run owns the row; the API refuses to delete it once it carries plans and estimates',
    tariffs:
      'the payer and branch tariff rows exist only if the doors answered 2xx; each status is recorded',
    statuses:
      'approved/sent rows are fixtures placed with the guarded tinker because the SPA has no door to them (U6 L5)'
  }

  built.ok = true
  built.reason = null

  return built
}

const fixtureSummaryOf = fixtures => ({
  marker: fixtures.marker,
  token: fixtures.token,
  day: fixtures.day,
  ok: fixtures.ok === true,
  reason: fixtures.reason ?? null,
  database: fixtures.database,
  procedure: fixtures.procedure,
  payer: fixtures.payer,
  tariffs: fixtures.tariffs,
  patient: fixtures.patient,
  piece: fixtures.piece,
  plan: fixtures.plan,
  quotations: Object.fromEntries(
    Object.entries(fixtures.quotations ?? {}).map(([key, entry]) => [
      key,
      {
        key,
        id: entry.id,
        request: entry.request,
        snapshot: entry.snapshot
      }
    ])
  ),
  statusWrites: fixtures.statusWrites ?? [],
  declarations: fixtures.declarations ?? {},
  reach: fixtures.reach ?? {},
  createdByWalk: fixtures.createdByWalk ?? [],
  residues: fixtures.residues ?? [],
  teardown: fixtures.teardown ?? [],
  leftovers: fixtures.leftovers ?? []
})

const publishFixtures = fixtures =>
  audit('fixtures.json', { ...stamp(), stage: 'layer-0', ...fixtureSummaryOf(fixtures) })

const STATE_FILE = resolve(process.cwd(), U6_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U6_FIXTURES__ ??= {
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
    session: 'U6',
    out: U6_EVIDENCE_DIR,
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
 * A previous run of the same session left its rows behind (the audit database is
 * disposable). Reusing them would measure the old state, so the day guard is the
 * only reuse: a stored fixture set from today is reused when its reference row
 * still resolves, otherwise a fresh one is built.
 */
const reuseStoredFixtures = async (request, stored) => {
  const fixtures = stored?.fixtures

  if (!fixtures?.ok || stored.retired === true) return null
  if (stored.day && stored.day !== localDay()) return null

  const admin = await loginAs(request, 'administrador')
  const procedureId = fixtures.procedure?.id
  const quotationId = fixtures.quotations?.sentForDialogs?.id ?? null

  if (!procedureId || !quotationId) return null

  const [procedure, quotation] = await Promise.all([
    call(request, admin, 'GET', `/api/procedure-catalog/${procedureId}`),
    call(request, admin, 'GET', `/api/quotations/${quotationId}`)
  ])

  if (procedure.status !== 200 || quotation.status !== 200) return null

  REGISTRY.fixtures = fixtures
  publishFixtures(fixtures)
  return fixtures
}

/**
 * Builds the U6 fixtures once per worker and hands them to every row that needs
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
        day: localDay(),
        ok: false,
        reason: `fixture build failed: ${String(thrown.message).slice(0, 400)}`,
        database: null,
        sessions: {},
        quotations: {},
        statusWrites: [],
        createdByWalk: [],
        residues: [],
        teardown: [],
        leftovers: []
      }))

      REGISTRY.fixtures = fixtures
      writeState({
        createdAt: new Date().toISOString(),
        day: fixtures.day,
        retired: false,
        fixtures
      })
      publishFixtures(fixtures)
      return fixtures
    })()
  }

  return REGISTRY.promise
}

export const quotationByKey = (fixtures, key) => fixtures?.quotations?.[key] ?? null

/** Declares a row this session created that the API does not let it retire. */
export const registerResidue = residue => {
  if (!REGISTRY.fixtures) return null

  const entry = { declaredAt: new Date().toISOString(), ...residue }
  REGISTRY.fixtures.residues = [...(REGISTRY.fixtures.residues ?? []), entry]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return entry
}

/** A row a walkthrough created through the UI; teardown tries to delete it. */
export const registerCreated = (type, entry) => {
  if (!REGISTRY.fixtures) return null

  const row = { type, declaredAt: new Date().toISOString(), ...entry }
  REGISTRY.fixtures.createdByWalk = [...(REGISTRY.fixtures.createdByWalk ?? []), row]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return row
}

const retire = async (request, fixtures) => {
  const admin = await loginAs(request, 'administrador')
  const steps = []
  const leftovers = [...(fixtures.residues ?? [])]

  const record = (type, id, outcome, reason) => {
    steps.push({ type, id, ...outcome })

    if (outcome.deleted !== true) {
      leftovers.push({
        type,
        id,
        reason: reason ?? outcome.message ?? 'the API refused the delete'
      })
    }
  }

  if (fixtures.database !== 'odontosuite_audit') {
    steps.push({ action: 'refused', reason: 'fixtures did not confirm the audit database' })
    fixtures.teardown = steps
    fixtures.leftovers = leftovers
    publishFixtures(fixtures)
    writeState({ retired: true, teardown: steps, leftovers })
    return steps
  }

  for (const entry of fixtures.createdByWalk ?? []) {
    const path =
      entry.type === 'quotation'
        ? `/api/quotations/${entry.id}`
        : `/api/treatment-plans/${entry.id}`
    const response = await call(request, admin, 'DELETE', path)
    record(entry.type, entry.id, {
      status: response.status,
      deleted: response.ok === true,
      message: response.message
    })
  }

  for (const entry of Object.values(fixtures.quotations ?? {})) {
    const response = await call(request, admin, 'DELETE', `/api/quotations/${entry.id}`)
    record(
      'quotation',
      entry.id,
      {
        status: response.status,
        deleted: response.ok === true,
        message: response.message
      },
      'the API refused the delete (build-time status ' +
        `${entry.snapshot?.status ?? 'unknown'}): only a draft quotation is deletable`
    )
  }

  if (fixtures.plan?.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/treatment-plans/${fixtures.plan.id}`
    )
    record(
      'treatment-plan',
      fixtures.plan.id,
      {
        status: response.status,
        deleted: response.ok === true,
        message: response.message
      },
      'the API refused the delete (build-time status ' +
        `${fixtures.plan.snapshot?.status ?? 'unknown'}): only a draft plan is deletable`
    )
  }

  if (fixtures.procedure?.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/procedure-catalog/${fixtures.procedure.id}`
    )
    record('procedure', fixtures.procedure.id, {
      status: response.status,
      deleted: response.ok === true,
      message: response.message
    })
  }

  if (fixtures.patient?.id) {
    const response = await call(request, admin, 'DELETE', `/api/patients/${fixtures.patient.id}`)
    record(
      'patient',
      fixtures.patient.id,
      {
        status: response.status,
        deleted: response.ok === true,
        message: response.message
      },
      'the patient carries the plan and the estimates of the run: the delete door refuses it'
    )
  }

  leftovers.push({
    type: 'payer',
    id: fixtures.payer?.id ?? null,
    reason:
      'the convenio catalogue has no delete door (routes/api.php declares index/store/update only)'
  })

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps, leftovers })
  return steps
}

export const retireFixtures = request => retire(request, REGISTRY.fixtures)

/**
 * Places one audit row in a status the SPA cannot write (`sent`, `approved`).
 *
 * Same guarded child process as the fixtures: the script refuses any row whose
 * `notes` do not carry the marker, so a seeded quotation is never flipped. The
 * caller declares the result as fixture state, never as product behaviour.
 */
export const placeQuotationStatus = (id, status) => {
  const applied = php(statusScript(MARKER, [{ id, status }]))

  return applied.applied[0] ?? null
}

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => fixtureSummaryOf(fixtures ?? {})
