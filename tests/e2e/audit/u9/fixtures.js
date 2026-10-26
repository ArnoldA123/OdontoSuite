// Plan #13 (issue #372, U9) — the administration session's own rows.
//
// Everything the surfaces need is created through the product's own doors, with
// the `AUDIT-U9-` prefix: two professionals, one dental chair (the detail
// sample), one appointment type, one specialty, one branch, one payment method,
// the accounting lock of `quotations` and the transfer requests of the session's
// own requesters. No seeded row is written.
//
// The layer-5 matrix needs destructive probes (DELETE /api/users, /dental-chairs,
// /appointment-types, /payment-methods, /branches), so it gets its own rows: a
// contract row never consumes the fixture a render or a walkthrough reads.
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped measurement with its reason is evidence, an exception is not. The
// teardown decides or cancels every request it opened, deletes what it created,
// retires the accounting lock, and asserts the final state in
// `fixtures-state.json`. The specialty has no DELETE door (405), so it is
// deactivated and declared as a residue instead of being hidden.

import { existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { expect, test } from 'playwright/test'

import { PASSWORD } from '../harness.js'

import { EVIDENCE, ROLES, api, audit, hasEvidence, login, readEvidence, stamp } from './helpers.js'

export const MARKER = 'AUDIT-U9'

// Every row the session creates carries this prefix so a residue is searchable.
const NAME_PREFIX = 'AUDIT-U9-'

const RUN_TOKEN = String(Date.now()).slice(-6)

const LOCK_MODULE = 'quotations'

const localDay = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** `api()` returns `{ status, body }`; success is the 2xx range. */
const isSuccess = response =>
  response.status !== null && response.status >= 200 && response.status < 300

/**
 * A row that is already gone is retired, not a residue.
 *
 * `DELETE /api/users/{id}` answers 500 with the model-not-found message when the
 * account was deleted by an earlier step (the matrix consumes its own account),
 * and the other doors answer 404.
 */
const isGone = response =>
  response.status === 404 ||
  (response.status === 500 &&
    /no query results for model/i.test(String(response.body?.error ?? '')))

const sessionSummary = session => ({
  id: session.user.id,
  name: session.user.name,
  username: session.user.username,
  role: session.user.role,
  branchId: session.user.branch_id ?? null
})

const userPayload = ({ name, username, email, role, branchId = null, license = null }) => ({
  name,
  username,
  email,
  password: PASSWORD,
  password_confirmation: PASSWORD,
  phone: '+51999000111',
  document_number: `AUD9${RUN_TOKEN}`,
  role,
  specialty: 'general',
  professional_license: license,
  branch_id: branchId,
  is_active: true
})

const buildFixtures = async () => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day: localDay(),
    lockModule: LOCK_MODULE,
    ok: false,
    reason: 'not built',
    sessions: {},
    permissions: {},
    branches: {},
    specialties: {},
    professionals: {},
    chairs: {},
    appointmentTypes: {},
    paymentMethods: {},
    accountingLock: null,
    requesters: {},
    transfers: {},
    matrix: { user: null, chairs: {}, appointmentTypes: {}, paymentMethods: {}, branch: null },
    baseline: {},
    reach: {},
    declarations: {},
    teardown: [],
    leftovers: []
  }

  const sessions = await roleSessionsForEvidence()

  for (const [role, session] of Object.entries(sessions)) {
    built.sessions[role] = sessionSummary(session)
    built.permissions[role] = [...(session.user.permissions ?? [])]
  }

  const admin = sessions.administrador

  // --- baseline the run compares its own writes against --------------------
  const usersBefore = await api(admin, 'GET', '/api/users')
  const branchesBefore = await api(admin, 'GET', '/api/branches')
  built.baseline = {
    users: (usersBefore.body?.data ?? []).length,
    branches: (branchesBefore.body?.data ?? []).length,
    usersStatus: usersBefore.status,
    branchesStatus: branchesBefore.status
  }

  // A run that died before its teardown leaves fixed usernames behind; the
  // session claims them by retiring the stale rows, never by reusing them.
  const stale = (usersBefore.body?.data ?? []).filter(entry =>
    String(entry.username ?? '').startsWith('audit_u9_')
  )
  built.userClaim = { staleFound: stale.length, staleIds: stale.map(entry => entry.id) }

  for (const entry of stale) {
    await api(admin, 'DELETE', `/api/users/${entry.id}`)
  }

  // --- the branch the surfaces read and the transfers target --------------
  const createdBranch = await api(admin, 'POST', '/api/branches', {
    name: `${NAME_PREFIX}${RUN_TOKEN} sede de auditoría`,
    code: `U9${RUN_TOKEN}`,
    address: 'Av. Auditoría 900',
    city: 'Lima',
    is_active: true,
    description: `${MARKER} sede creada por la sesión de auditoría`
  })

  built.branches.target = {
    id: createdBranch.body?.data?.id ?? null,
    name: `${NAME_PREFIX}${RUN_TOKEN} sede de auditoría`,
    code: `U9${RUN_TOKEN}`,
    status: createdBranch.status,
    message: createdBranch.body?.message ?? null,
    errors: createdBranch.body?.errors ?? null,
    source: 'POST /api/branches (the run owns the row)'
  }

  const matrixBranch = await api(admin, 'POST', '/api/branches', {
    name: `${NAME_PREFIX}${RUN_TOKEN} sede de la matriz`,
    code: `M9${RUN_TOKEN}`,
    address: 'Av. Auditoría 901',
    city: 'Lima',
    is_active: true
  })

  built.matrix.branch = {
    id: matrixBranch.body?.data?.id ?? null,
    name: `${NAME_PREFIX}${RUN_TOKEN} sede de la matriz`,
    code: `M9${RUN_TOKEN}`,
    status: matrixBranch.status,
    source: 'POST /api/branches (the matrix deletes it)'
  }

  if (!built.branches.target.id) {
    built.reason = `the branch was refused: HTTP ${createdBranch.status} ${createdBranch.body?.message ?? ''}`
    return built
  }

  // --- the specialty (no DELETE door: PUT is the only lifecycle) ----------
  const createdSpecialty = await api(admin, 'POST', '/api/specialties', {
    code: `U9-${RUN_TOKEN}`,
    name: `${NAME_PREFIX}${RUN_TOKEN} especialidad de auditoría`,
    description: `${MARKER} especialidad creada por la sesión`,
    is_active: true
  })

  built.specialties.fixture = {
    id: createdSpecialty.body?.data?.id ?? null,
    code: `U9-${RUN_TOKEN}`,
    name: `${NAME_PREFIX}${RUN_TOKEN} especialidad de auditoría`,
    status: createdSpecialty.status,
    message: createdSpecialty.body?.message ?? null,
    source: 'POST /api/specialties (the run owns the row)'
  }

  if (!built.specialties.fixture.id) {
    built.reason = `the specialty was refused: HTTP ${createdSpecialty.status} ${createdSpecialty.body?.message ?? ''}`
    return built
  }

  // --- two professionals: the deletable sample and the one the walk edits -
  const professionalSeeds = [
    {
      key: 'plain',
      name: `${NAME_PREFIX}${RUN_TOKEN} odontólogo de detalle`,
      username: 'audit_u9_detail',
      email: `audit-u9-detail-${RUN_TOKEN}@audit.local`,
      license: `AUD9-${RUN_TOKEN}-D`
    },
    {
      key: 'edit',
      name: `${NAME_PREFIX}${RUN_TOKEN} odontólogo editable`,
      username: 'audit_u9_edit',
      email: `audit-u9-edit-${RUN_TOKEN}@audit.local`,
      license: `AUD9-${RUN_TOKEN}-E`
    }
  ]

  for (const seed of professionalSeeds) {
    const response = await api(
      admin,
      'POST',
      '/api/users',
      userPayload({ ...seed, role: 'odontologo' })
    )

    built.professionals[seed.key] = {
      id: response.body?.data?.id ?? null,
      name: seed.name,
      username: seed.username,
      email: seed.email,
      role: response.body?.data?.role ?? 'odontologo',
      status: response.status,
      message: response.body?.message ?? null,
      errors: response.body?.errors ?? null,
      source: 'POST /api/users (the run owns the row)'
    }
  }

  const refusedProfessional = Object.entries(built.professionals).find(([, entry]) => !entry.id)

  if (refusedProfessional) {
    built.reason = `the professional "${refusedProfessional[0]}" was refused: HTTP ${refusedProfessional[1].status} ${refusedProfessional[1].message ?? ''}`
    return built
  }

  // --- dental chairs: the detail sample plus the two matrix rows ----------
  const chairSeeds = [
    { key: 'detail', name: `${NAME_PREFIX}${RUN_TOKEN} consultorio de detalle`, status: 'active' },
    {
      key: 'matrixPut',
      name: `${NAME_PREFIX}${RUN_TOKEN} consultorio de la matriz (PUT)`,
      status: 'active'
    },
    {
      key: 'matrixDelete',
      name: `${NAME_PREFIX}${RUN_TOKEN} consultorio de la matriz (DELETE)`,
      status: 'inactive'
    }
  ]

  for (const seed of chairSeeds) {
    const response = await api(admin, 'POST', '/api/dental-chairs', {
      name: seed.name,
      status: seed.status,
      description: `${MARKER} ambiente creado por la sesión de auditoría`,
      equipment: 'Unidad dental, lámpara, compresor'
    })
    const target = seed.key === 'detail' ? built.chairs : built.matrix.chairs

    target[seed.key] = {
      id: response.body?.data?.id ?? null,
      name: seed.name,
      status: response.body?.data?.status ?? seed.status,
      httpStatus: response.status,
      message: response.body?.message ?? null,
      source: 'POST /api/dental-chairs (the run owns the row)'
    }
  }

  const refusedChair = [
    ...Object.entries(built.chairs),
    ...Object.entries(built.matrix.chairs)
  ].find(([, entry]) => !entry.id)

  if (refusedChair) {
    built.reason = `the dental chair "${refusedChair[0]}" was refused: HTTP ${refusedChair[1].httpStatus} ${refusedChair[1].message ?? ''}`
    return built
  }

  // --- appointment types: the detail sample plus the two matrix rows ------
  const typeSeeds = [
    { key: 'detail', duration: 30, color: '#2F80ED' },
    { key: 'matrixPut', duration: 45, color: '#27AE60' },
    { key: 'matrixDelete', duration: 60, color: '#EB5757' }
  ]

  for (const seed of typeSeeds) {
    const response = await api(admin, 'POST', '/api/appointment-types', {
      name: `${NAME_PREFIX}${RUN_TOKEN} tipo ${seed.key}`,
      description: `${MARKER} tipo de cita creado por la sesión`,
      duration_minutes: seed.duration,
      color: seed.color,
      requires_confirmation: true,
      requires_materials: false,
      is_active: true
    })
    const target = seed.key === 'detail' ? built.appointmentTypes : built.matrix.appointmentTypes

    target[seed.key] = {
      id: response.body?.data?.id ?? null,
      name: `${NAME_PREFIX}${RUN_TOKEN} tipo ${seed.key}`,
      duration: seed.duration,
      color: seed.color,
      httpStatus: response.status,
      message: response.body?.message ?? null,
      source: 'POST /api/appointment-types (the run owns the row)'
    }
  }

  const refusedType = [
    ...Object.entries(built.appointmentTypes),
    ...Object.entries(built.matrix.appointmentTypes)
  ].find(([, entry]) => !entry.id)

  if (refusedType) {
    built.reason = `the appointment type "${refusedType[0]}" was refused: HTTP ${refusedType[1].httpStatus} ${refusedType[1].message ?? ''}`
    return built
  }

  // --- payment methods: the human-readable family and the two matrix rows -
  const methodSeeds = [
    { key: 'fixture', code: `U9${RUN_TOKEN}`, type: 'cash' },
    { key: 'matrixPut', code: `M9P${RUN_TOKEN}`, type: 'card' },
    { key: 'matrixDelete', code: `M9D${RUN_TOKEN}`, type: 'wallet' }
  ]

  for (const seed of methodSeeds) {
    const response = await api(admin, 'POST', '/api/payment-methods', {
      name: `${NAME_PREFIX}${RUN_TOKEN} método ${seed.key}`,
      code: seed.code,
      type: seed.type,
      description: `${MARKER} método de pago creado por la sesión`,
      requires_reference: seed.type !== 'cash',
      gateway_type: 'manual',
      commission_percentage: 0,
      is_active: true
    })
    const target = seed.key === 'fixture' ? built.paymentMethods : built.matrix.paymentMethods

    target[seed.key] = {
      id: response.body?.data?.id ?? null,
      name: `${NAME_PREFIX}${RUN_TOKEN} método ${seed.key}`,
      code: seed.code,
      type: response.body?.data?.type ?? seed.type,
      httpStatus: response.status,
      message: response.body?.message ?? null,
      source: 'POST /api/payment-methods (the run owns the row)'
    }
  }

  const refusedMethod = [
    ...Object.entries(built.paymentMethods),
    ...Object.entries(built.matrix.paymentMethods)
  ].find(([, entry]) => !entry.id)

  if (refusedMethod) {
    built.reason = `the payment method "${refusedMethod[0]}" was refused: HTTP ${refusedMethod[1].httpStatus} ${refusedMethod[1].message ?? ''}`
    return built
  }

  // --- the matrix account: the DELETE /api/users probe consumes it --------
  const matrixUser = await api(
    admin,
    'POST',
    '/api/users',
    userPayload({
      name: `${NAME_PREFIX}${RUN_TOKEN} cuenta de la matriz`,
      username: 'audit_u9_matrix',
      email: `audit-u9-matrix-${RUN_TOKEN}@audit.local`,
      role: 'odontologo',
      license: `AUD9-${RUN_TOKEN}-M`
    })
  )

  built.matrix.user = {
    id: matrixUser.body?.data?.id ?? null,
    name: `${NAME_PREFIX}${RUN_TOKEN} cuenta de la matriz`,
    username: 'audit_u9_matrix',
    status: matrixUser.status,
    message: matrixUser.body?.message ?? null,
    source: 'POST /api/users (the matrix deletes it)'
  }

  // --- the transfer requesters -------------------------------------------
  const requesterSeeds = [
    { key: 'cancel', role: 'recepcionista', decision: 'cancel' },
    { key: 'approve', role: 'recepcionista', decision: 'approve' },
    { key: 'reject', role: 'odontologo', decision: 'reject' },
    { key: 'matrixApprove', role: 'recepcionista', decision: 'approve' },
    { key: 'matrixReject', role: 'odontologo', decision: 'reject' },
    { key: 'self', role: 'administrador', decision: 'self' }
  ]

  for (const seed of requesterSeeds) {
    const username = `audit_u9_${seed.key.toLowerCase()}`

    const response = await api(
      admin,
      'POST',
      '/api/users',
      userPayload({
        name: `${NAME_PREFIX}${RUN_TOKEN} solicitante ${seed.key}`,
        username,
        email: `audit-u9-${seed.key.toLowerCase()}-${RUN_TOKEN}@audit.local`,
        role: seed.role
      })
    )

    built.requesters[seed.key] = {
      id: response.body?.data?.id ?? null,
      username,
      role: seed.role,
      decision: seed.decision,
      httpStatus: response.status,
      message: response.body?.message ?? null,
      source: 'POST /api/users (the run owns the row)'
    }
  }

  const refusedRequester = Object.entries(built.requesters).find(([, entry]) => !entry.id)

  if (refusedRequester) {
    built.reason = `the requester "${refusedRequester[0]}" was refused: HTTP ${refusedRequester[1].httpStatus} ${refusedRequester[1].message ?? ''}`
    return built
  }

  const requesterSessions = {}

  for (const [key, entry] of Object.entries(built.requesters)) {
    requesterSessions[key] = await loginCredential(entry.username)
  }

  // --- one open request per requester (the API allows exactly one) --------
  for (const [key, entry] of Object.entries(built.requesters)) {
    const session = requesterSessions[key]
    const response = await api(session, 'POST', '/api/branch-transfers', {
      to_branch_id: built.branches.target.id,
      reason: `${NAME_PREFIX}${RUN_TOKEN} solicitud ${key} de la sesión de auditoría`
    })

    built.transfers[key] = {
      id: response.body?.data?.id ?? null,
      requester: entry.username,
      requesterId: entry.id,
      status: response.body?.data?.status ?? null,
      httpStatus: response.status,
      message: response.body?.message ?? null,
      source: 'POST /api/branch-transfers (the run owns the row)'
    }
  }

  const refusedTransfer = Object.entries(built.transfers).find(([, entry]) => !entry.id)

  if (refusedTransfer) {
    built.reason = `the transfer request "${refusedTransfer[0]}" was refused: HTTP ${refusedTransfer[1].httpStatus} ${refusedTransfer[1].message ?? ''}`
    return built
  }

  // --- the two self-service rules #305 measures ---------------------------
  const selfSession = requesterSessions.self
  const secondOpen = await api(selfSession, 'POST', '/api/branch-transfers', {
    to_branch_id: built.branches.target.id,
    reason: `${MARKER} segunda solicitud abierta`
  })
  const selfApprove = await api(
    selfSession,
    'POST',
    `/api/branch-transfers/${built.transfers.self.id}/approve`,
    { decision_notes: `${MARKER} intento de decisión propia` }
  )
  const selfCancel = await api(
    selfSession,
    'POST',
    `/api/branch-transfers/${built.transfers.self.id}/cancel`
  )

  built.transfers.secondOpen = {
    requestId: built.transfers.self.id,
    status: secondOpen.status,
    message: secondOpen.body?.message ?? null,
    note: 'una sola solicitud abierta por usuario: la segunda responde 409'
  }

  built.transfers.selfDecision = {
    id: built.transfers.self.id,
    approve: { status: selfApprove.status, message: selfApprove.body?.message ?? null },
    cancel: { status: selfCancel.status, message: selfCancel.body?.message ?? null },
    finalStatus: selfCancel.body?.data?.status ?? null
  }

  // --- the accounting lock the walk fixes and retires ---------------------
  const lockResponse = await api(admin, 'PUT', `/api/accounting-locks/${LOCK_MODULE}`, {
    locked_through: built.day
  })
  const lockList = await api(admin, 'GET', '/api/accounting-locks')
  const lockRow = (lockList.body?.data ?? []).find(entry => entry.module === LOCK_MODULE) ?? null

  built.accountingLock = {
    module: LOCK_MODULE,
    lockedThrough: lockResponse.body?.data?.locked_through ?? built.day,
    httpStatus: lockResponse.status,
    message: lockResponse.body?.message ?? null,
    source: lockRow?.source ?? null,
    branchId: lockRow?.branch_id ?? null,
    setAt: lockRow?.set_at ?? null,
    listStatus: lockList.status,
    origin: 'PUT /api/accounting-locks/quotations (the run owns the row)'
  }

  // --- what each read door answers for this run's rows -------------------
  const usersList = await api(admin, 'GET', '/api/users')
  const chairsList = await api(admin, 'GET', '/api/dental-chairs?archived=include')
  const typesList = await api(admin, 'GET', '/api/appointment-types')
  const specialtiesList = await api(admin, 'GET', '/api/specialties')
  const branchesList = await api(admin, 'GET', '/api/branches')
  const methodsList = await api(admin, 'GET', '/api/payment-methods')
  const transfersAsAdmin = await api(admin, 'GET', '/api/branch-transfers?per_page=100')
  const transfersAsReception = await api(
    sessions.recepcionista,
    'GET',
    '/api/branch-transfers?per_page=100'
  )
  const destinations = await api(admin, 'GET', '/api/branch-transfers/destinations')

  const ids = rows => (rows.body?.data ?? []).map(row => row.id)

  built.reach = {
    users: {
      status: usersList.status,
      total: usersList.body?.meta?.total ?? null,
      hasDetail: ids(usersList).includes(built.professionals.plain.id),
      hasEdit: ids(usersList).includes(built.professionals.edit.id)
    },
    chairs: {
      status: chairsList.status,
      hasDetail: ids(chairsList).includes(built.chairs.detail.id)
    },
    appointmentTypes: {
      status: typesList.status,
      total: typesList.body?.meta?.total ?? null,
      hasDetail: ids(typesList).includes(built.appointmentTypes.detail.id)
    },
    specialties: {
      status: specialtiesList.status,
      hasFixture: ids(specialtiesList).includes(built.specialties.fixture.id)
    },
    branches: {
      status: branchesList.status,
      hasTarget: ids(branchesList).includes(built.branches.target.id)
    },
    paymentMethods: {
      status: methodsList.status,
      hasFixture: ids(methodsList).includes(built.paymentMethods.fixture.id),
      fixtureTransactions: (methodsList.body?.data ?? []).find(
        row => row.id === built.paymentMethods.fixture.id
      )?.transactions_count
    },
    branchTransfers: {
      adminStatus: transfersAsAdmin.status,
      adminPending: (transfersAsAdmin.body?.data ?? []).filter(row => row.status === 'pending')
        .length,
      receptionStatus: transfersAsReception.status,
      receptionTotal: transfersAsReception.body?.meta?.total ?? null,
      destinationsStatus: destinations.status,
      destinationsHasTarget: ids(destinations).includes(built.branches.target.id)
    }
  }

  if (!built.reach.users.hasDetail) {
    built.reason = 'the created professional did not appear in GET /api/users'
    return built
  }

  if (!built.reach.branches.hasTarget) {
    built.reason = 'the created branch did not appear in GET /api/branches'
    return built
  }

  built.ok = true
  built.reason = null

  built.declarations = {
    users:
      'created through POST /api/users; every fixture account is deleted with DELETE /api/users/{id} at teardown',
    chairs:
      'created through POST /api/dental-chairs; the walk archives and reactivates the detail row; the matrix deletes its own rows',
    appointmentTypes: 'created through POST /api/appointment-types; deleted at teardown',
    specialties:
      'created through POST /api/specialties; there is no DELETE door (405), so teardown deactivates it with PUT and declares the row',
    branches:
      'created through POST /api/branches; deleted at teardown once the transfer rows that reference them are gone',
    paymentMethods: 'created through POST /api/payment-methods; deleted at teardown',
    accountingLock:
      'set through PUT /api/accounting-locks/quotations and retired with DELETE at teardown',
    transfers:
      'one open request per requester through POST /api/branch-transfers; teardown cancels the ones still pending and the rest are decided by the matrix',
    noBridge:
      'no tinker and no direct database access: every row was written and is retired through the API'
  }

  return built
}

const loginCredential = async (username, password = PASSWORD) => {
  const { apiClient } = await import('./helpers.js')
  const context = await apiClient()
  const response = await context.post('/api/auth/login', { data: { username, password } })

  if (!response.ok()) {
    const body = await response.text().catch(() => '')
    throw new Error(`login failed for ${username}: HTTP ${response.status()} ${body}`.trim())
  }

  const body = await response.json()
  const token = body?.data?.token
  const user = body?.data?.user

  if (!token || !user) {
    throw new Error(`login for ${username} returned no token/user: ${JSON.stringify(body)}`)
  }

  return { role: user.role, token, user, username }
}

const roleSessionsForEvidence = async () => {
  const sessions = {}

  for (const role of ROLES) sessions[role] = await login(role)

  return sessions
}

const fixtureSummaryOf = fixtures => ({
  marker: fixtures.marker ?? MARKER,
  token: fixtures.token ?? RUN_TOKEN,
  day: fixtures.day ?? localDay(),
  lockModule: fixtures.lockModule ?? LOCK_MODULE,
  ok: fixtures.ok === true,
  reason: fixtures.reason ?? null,
  sessions: fixtures.sessions ?? {},
  permissions: fixtures.permissions ?? {},
  userClaim: fixtures.userClaim ?? null,
  branches: fixtures.branches ?? {},
  specialties: fixtures.specialties ?? {},
  professionals: fixtures.professionals ?? {},
  chairs: fixtures.chairs ?? {},
  appointmentTypes: fixtures.appointmentTypes ?? {},
  paymentMethods: fixtures.paymentMethods ?? {},
  accountingLock: fixtures.accountingLock ?? null,
  requesters: fixtures.requesters ?? {},
  transfers: fixtures.transfers ?? {},
  matrix: fixtures.matrix ?? {},
  baseline: fixtures.baseline ?? {},
  reach: fixtures.reach ?? {},
  declarations: fixtures.declarations ?? {},
  teardown: fixtures.teardown ?? [],
  leftovers: fixtures.leftovers ?? []
})

/** The detail id a `/:id` surface is audited against; never created in a row. */
export const sampleIdFor = (fixtures, route) => {
  const samples = {
    '/professionals/:id': fixtures?.professionals?.plain?.id ?? null,
    '/environments/:id': fixtures?.chairs?.detail?.id ?? null,
    '/appointment-types/:id': fixtures?.appointmentTypes?.detail?.id ?? null,
    '/settings/branches/:id': fixtures?.branches?.target?.id ?? null
  }

  return samples[route] ?? null
}

const publishFixtures = fixtures =>
  audit('fixtures', { stage: 'layer-0', ...fixtureSummaryOf(fixtures) })

const STATE_FILE = resolve(process.cwd(), EVIDENCE, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U9_FIXTURES__ ??= { promise: null, fixtures: null })

const readState = () => readEvidence('fixtures-state.json')

const writeState = patch => {
  const next = {
    ...stamp(),
    out: EVIDENCE,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }

  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

/**
 * Builds the U9 fixtures once per worker and hands them to every row that needs
 * them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = () => {
  REGISTRY.promise ??= (async () => {
    const fixtures = await buildFixtures().catch(thrown => ({
      marker: MARKER,
      token: RUN_TOKEN,
      day: localDay(),
      ok: false,
      reason: `fixture build failed: ${String(thrown?.message ?? thrown).slice(0, 400)}`,
      sessions: {},
      permissions: {},
      branches: {},
      specialties: {},
      professionals: {},
      chairs: {},
      appointmentTypes: {},
      paymentMethods: {},
      requesters: {},
      transfers: {},
      matrix: {},
      reach: {},
      declarations: {},
      teardown: [],
      leftovers: []
    }))

    REGISTRY.fixtures = fixtures
    writeState({
      createdAt: new Date().toISOString(),
      retired: false,
      fixtures: fixtureSummaryOf(fixtures)
    })
    publishFixtures(fixtures)

    return fixtures
  })()

  return REGISTRY.promise
}

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => fixtureSummaryOf(fixtures ?? {})

const settleTransfer = async (fixtures, key) => {
  const entry = fixtures.transfers?.[key]
  if (!entry?.id) return null

  const requester = fixtures.requesters?.[key]
  if (!requester?.username) return { key, id: entry.id, skipped: 'no requester credential' }

  const session = await loginCredential(requester.username).catch(() => null)
  if (!session) return { key, id: entry.id, skipped: 'requester can no longer sign in' }

  const response = await api(session, 'POST', `/api/branch-transfers/${entry.id}/cancel`)
  const row = response.body?.data ?? null

  return {
    key,
    id: entry.id,
    status: response.status,
    finalStatus: row?.status ?? null,
    message: response.body?.message ?? null
  }
}

const retire = async fixtures => {
  const admin = await login('administrador')
  const steps = []
  const leftovers = []
  const declared = []

  const record = (type, id, outcome) => {
    const entry = { type, id, ...outcome }
    steps.push(entry)
    if (outcome.deleted === true || outcome.alreadyGone === true) return
    if (outcome.declared === true) {
      declared.push(entry)
      return
    }
    leftovers.push(entry)
  }

  // --- every request still open is closed before its owner is removed -----
  const transferSteps = []

  for (const key of ['cancel', 'approve', 'reject', 'matrixApprove', 'matrixReject']) {
    const step = await settleTransfer(fixtures, key)
    if (!step) continue
    transferSteps.push(step)
    steps.push({ type: `transfer:${key}`, id: step.id, ...step })
  }

  // --- the accounting lock -------------------------------------------------
  const lock = await api(admin, 'DELETE', `/api/accounting-locks/${LOCK_MODULE}`)
  record('accounting-lock', LOCK_MODULE, {
    status: lock.status,
    deleted: lock.status === 200 || lock.status === 404,
    message: lock.body?.message ?? null
  })

  // --- the rows the matrix deletes, in case the matrix never ran -----------
  for (const [key, entry] of Object.entries(fixtures.matrix?.chairs ?? {})) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/dental-chairs/${entry.id}`)
    record(`dental-chair:matrix:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  for (const [key, entry] of Object.entries(fixtures.matrix?.appointmentTypes ?? {})) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/appointment-types/${entry.id}`)
    record(`appointment-type:matrix:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  for (const [key, entry] of Object.entries(fixtures.matrix?.paymentMethods ?? {})) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/payment-methods/${entry.id}`)
    record(`payment-method:matrix:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  // --- the matrix branch ---------------------------------------------------
  if (fixtures.matrix?.branch?.id) {
    const response = await api(admin, 'DELETE', `/api/branches/${fixtures.matrix.branch.id}`)
    record('branch:matrix', fixtures.matrix.branch.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  // --- the fixture rows ----------------------------------------------------
  for (const [key, entry] of Object.entries(fixtures.chairs ?? {})) {
    if (!entry?.id) continue
    // The walk archives the row; a DELETE on an archived chair is still valid.
    const response = await api(admin, 'DELETE', `/api/dental-chairs/${entry.id}`)
    record(`dental-chair:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  for (const [key, entry] of Object.entries(fixtures.appointmentTypes ?? {})) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/appointment-types/${entry.id}`)
    record(`appointment-type:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  for (const [key, entry] of Object.entries(fixtures.paymentMethods ?? {})) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/payment-methods/${entry.id}`)
    record(`payment-method:${key}`, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  // --- the fixture accounts, after their requests are closed ---------------
  const userTargets = [
    ...Object.entries(fixtures.professionals ?? {}).map(([key, entry]) => [
      `professional:${key}`,
      entry
    ]),
    ...Object.entries(fixtures.requesters ?? {}).map(([key, entry]) => [`requester:${key}`, entry]),
    ['matrix:user', fixtures.matrix?.user]
  ]

  for (const [key, entry] of userTargets) {
    if (!entry?.id) continue
    const response = await api(admin, 'DELETE', `/api/users/${entry.id}`)
    record(key, entry.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  // --- the branch, once nothing references it ------------------------------
  if (fixtures.branches?.target?.id) {
    const response = await api(admin, 'DELETE', `/api/branches/${fixtures.branches.target.id}`)
    record('branch:target', fixtures.branches.target.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: isGone(response),
      message: response.body?.message ?? null
    })
  }

  // --- the specialty: no DELETE door, so it is deactivated ----------------
  if (fixtures.specialties?.fixture?.id) {
    const response = await api(
      admin,
      'PUT',
      `/api/specialties/${fixtures.specialties.fixture.id}`,
      { is_active: false }
    )
    record('specialty:fixture', fixtures.specialties.fixture.id, {
      status: response.status,
      deleted: false,
      declared: isSuccess(response),
      deactivated: isSuccess(response),
      message: isSuccess(response)
        ? 'PUT /api/specialties/{id} deactivates it: the catalogue has no DELETE door (405)'
        : (response.body?.message ?? null)
    })
  }

  // --- the final state, read back -----------------------------------------
  const finalUsers = await api(admin, 'GET', '/api/users')
  const finalBranches = await api(admin, 'GET', '/api/branches')
  const finalTransfers = await api(admin, 'GET', '/api/branch-transfers?per_page=100')
  const finalLocks = await api(admin, 'GET', '/api/accounting-locks')
  const finalSpecialties = await api(admin, 'GET', '/api/specialties')

  const ourUsernames = new Set([
    ...Object.values(fixtures.professionals ?? {}).map(entry => entry.username),
    ...Object.values(fixtures.requesters ?? {}).map(entry => entry.username),
    fixtures.matrix?.user?.username
  ])

  const remainingUsers = (finalUsers.body?.data ?? []).filter(entry =>
    ourUsernames.has(entry.username)
  )
  const ourRequestIds = new Set(
    Object.values(fixtures.transfers ?? {})
      .map(entry => entry?.id)
      .filter(Boolean)
  )
  const pendingRequests = (finalTransfers.body?.data ?? []).filter(
    row => ourRequestIds.has(row.id) && row.status === 'pending'
  )
  const lockRow = (finalLocks.body?.data ?? []).find(entry => entry.module === LOCK_MODULE) ?? null
  const remainingBranches = (finalBranches.body?.data ?? []).filter(entry =>
    [fixtures.branches?.target?.code, fixtures.matrix?.branch?.code]
      .filter(Boolean)
      .includes(entry.code)
  )
  const specialtyRow =
    (finalSpecialties.body?.data ?? []).find(
      entry => entry.id === fixtures.specialties?.fixture?.id
    ) ?? null

  const finalState = {
    usersRemaining: remainingUsers.map(entry => ({ id: entry.id, username: entry.username })),
    pendingRequests: pendingRequests.map(entry => ({ id: entry.id, status: entry.status })),
    lockSource: lockRow?.source ?? null,
    branchesRemaining: remainingBranches.map(entry => ({
      id: entry.id,
      code: entry.code,
      isActive: entry.is_active
    })),
    specialtyRemaining: specialtyRow
      ? { id: specialtyRow.id, code: specialtyRow.code, isActive: specialtyRow.is_active }
      : null,
    statuses: {
      users: finalUsers.status,
      branches: finalBranches.status,
      transfers: finalTransfers.status,
      locks: finalLocks.status,
      specialties: finalSpecialties.status
    }
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  fixtures.declaredResidues = declared
  fixtures.transferSettlement = transferSteps
  fixtures.finalState = finalState

  publishFixtures(fixtures)
  writeState({
    retired: true,
    teardown: steps,
    leftovers,
    declaredResidues: declared,
    transferSettlement: transferSteps,
    finalState
  })

  return { steps, leftovers, declared, finalState }
}

export const retireFixtures = () => {
  if (!REGISTRY.fixtures) return Promise.resolve(null)
  if (REGISTRY.fixtures.teardownDone) return Promise.resolve(REGISTRY.fixtures.finalState ?? null)
  REGISTRY.fixtures.teardownDone = true

  return retire(REGISTRY.fixtures)
}

test(`@U9 · capa 0 — fixtures de la sesión`, async () => {
  test.setTimeout(240000)

  const fixtures = await ensureFixtures()

  expect(fixtures.ok, `fixtures: ${fixtures.reason ?? 'built'}`).toBe(true)
  expect(fixtures.transfers.selfDecision?.approve?.status, 'decidir la propia solicitud').toBe(403)
  expect(fixtures.transfers.selfDecision?.cancel?.status, 'cancelar la propia solicitud').toBe(200)
  expect(fixtures.transfers.secondOpen?.status, 'segunda solicitud abierta').toBe(409)
  expect(fixtures.accountingLock?.httpStatus, 'fijar el bloqueo contable').toBe(200)
  expect(hasEvidence('fixtures.json'), 'fixtures.json evidence').toBe(true)
})

test.afterAll(async () => {
  const { fixtures } = REGISTRY

  if (!fixtures || fixtures.teardownDone) return

  fixtures.teardownDone = true
  const result = await retire(fixtures)

  expect(result.finalState.pendingRequests, 'ninguna solicitud abierta al cerrar').toEqual([])
  expect(result.finalState.usersRemaining, 'ninguna cuenta de la sesión al cerrar').toEqual([])
  expect(result.finalState.lockSource, 'bloqueo contable retirado').toBe('none')
  expect(result.leftovers, 'residuos no declarados').toEqual([])

  expect(existsSync(STATE_FILE), 'fixtures-state.json evidence').toBe(true)
})

// Exported for the rows that need a non-role credential (the requesters).
export { loginCredential }
