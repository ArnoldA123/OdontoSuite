// Plan #13 (issue #371, U8) — the catalogue session's own rows.
//
// Everything the surfaces need is created through the product's own doors: five
// procedures with the `AUD-U8-` code prefix (one of them with applicability, a
// public nomenclator code and requirements), one branch price with a validity
// window on branch 1, two treatment plans that feed `/api/admin/procedure-stats`
// and the favourites the clinical desks read. No seeded row is touched.
//
// The branch price needs an actor with a sede: `ProcedurePriceResolver` reads
// the branch of the authenticated user (`app/Services/ProcedurePriceResolver.php`
// `currentBranchId`), and every seeded user has `branch_id` NULL, so the cascade
// never reaches a branch row. The session therefore creates one account of its
// own — `audit_u8_sede`, role administrador, `branch_id` 1 — and deletes it with
// `DELETE /api/users/{id}` at teardown. Without that row the S5 criterion could
// not be measured at all; it says NO PROBADO, not "not reflected".
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.
//
// Playwright re-evaluates the modules per test, so the fixtures travel through
// `globalThis` (fast path) and mirror to `fixtures-state.json` (worker-restart
// path), the same registry U3–U7 use. Each run builds a fresh set: the walks
// deactivate and reactivate the rows they own, so reusing yesterday's rows would
// measure a different state.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { test } from 'playwright/test'

import { loginAs } from '../harness.js'

import {
  U8_EVIDENCE_DIR,
  addDaysLocal,
  audit,
  call,
  localDay,
  loginCredential,
  stamp
} from './helpers.js'

export const MARKER = 'AUDIT-U8'

const RUN_TOKEN = String(Date.now()).slice(-6)

/** The account the branch price needs; `password123` matches the seed. */
export const BRANCH_USER = Object.freeze({
  username: 'audit_u8_sede',
  password: 'password123',
  name: `${MARKER} administrador de sede`,
  email: `audit-u8-sede-${RUN_TOKEN}@audit.local`
})

export const FIXTURE_ROLES = Object.freeze([
  'administrador',
  'finanzas',
  'odontologo',
  'recepcionista',
  'implantologo'
])

const number = (value, fallback = 0) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const priceOf = value => number(value, 0)

const createProcedure = async (request, admin, payload) => {
  const response = await call(request, admin, 'POST', '/api/procedure-catalog', payload)

  return {
    id: response.body?.data?.id ?? null,
    code: payload.code,
    name: payload.name,
    siteType: payload.site_type ?? 'none',
    externalCodes: payload.external_codes ?? [],
    requirements: payload.requirements ?? null,
    defaultCost: payload.default_cost,
    specialtyId: payload.specialty_id ?? null,
    status: response.status,
    message: response.message,
    errors: response.errors,
    source: 'POST /api/procedure-catalog (the run owns the row)'
  }
}

const createPlan = async (request, admin, payload) => {
  const response = await call(request, admin, 'POST', '/api/treatment-plans', payload)

  return {
    id: response.body?.data?.id ?? null,
    title: payload.title,
    patientId: payload.patient_id,
    itemCount: payload.items?.length ?? 0,
    items: (payload.items ?? []).map(item => ({
      procedureId: item.procedure_catalog_id,
      description: item.description,
      quantity: item.quantity,
      unitCost: item.unit_cost
    })),
    status: response.status,
    message: response.message,
    errors: response.errors,
    source: 'POST /api/treatment-plans (the run owns the row)'
  }
}

const buildFixtures = async request => {
  const day = localDay()
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day,
    ok: false,
    reason: 'not built',
    sessions: {},
    permissions: {},
    user: null,
    branch: null,
    specialties: [],
    toothSurface: null,
    patient: null,
    professional: null,
    procedures: {},
    branchPrice: null,
    plans: {},
    favorites: {},
    baseline: {},
    declarations: {},
    reach: {},
    createdByWalk: [],
    teardown: [],
    leftovers: []
  }

  for (const role of FIXTURE_ROLES) {
    const session = await loginAs(request, role)
    built.sessions[role] = {
      id: session.user.id,
      name: session.user.name,
      username: session.user.username,
      role: session.user.role,
      branchId: session.user.branch_id ?? null
    }
    built.permissions[role] = [...(session.user.permissions ?? [])]
  }

  const admin = await loginAs(request, 'administrador')
  const odontologo = await loginAs(request, 'odontologo')
  const implantologo = await loginAs(request, 'implantologo')

  // --- the catalogue baseline, before this run writes anything --------------
  const baseline = await call(request, admin, 'GET', '/api/procedure-catalog?per_page=1')
  built.baseline = {
    total: baseline.body?.meta?.total ?? null,
    status: baseline.status,
    inactive: null
  }

  const branches = await call(request, admin, 'GET', '/api/branches/active')
  const branch = (branches.body?.data ?? []).find(entry => entry.id === 1) ?? null
  if (!branch) {
    built.reason = `branch 1 is not available: HTTP ${branches.status}`
    return built
  }
  built.branch = { id: branch.id, name: branch.name }

  const specialties = await call(request, admin, 'GET', '/api/specialties/active')
  const specialtyList = (specialties.body?.data ?? []).slice(0, 2)
  built.specialties = specialtyList.map(entry => ({
    id: entry.id,
    code: entry.code,
    name: entry.name
  }))
  if (!built.specialties.length) {
    built.reason = `no active specialty available: HTTP ${specialties.status}`
    return built
  }

  const pieces = await call(request, admin, 'GET', '/api/dental-pieces')
  const piece = (pieces.body?.data ?? [])[0] ?? null
  const toothSurface = (piece?.surfaces ?? [])[0] ?? null
  if (!toothSurface) {
    built.reason = `no tooth surface available: HTTP ${pieces.status}`
    return built
  }
  built.toothSurface = {
    id: toothSurface.id,
    code: toothSurface.surface_code,
    pieceId: piece.id,
    fdi: piece.fdi_number
  }

  const patients = await call(request, admin, 'GET', '/api/patients?per_page=3')
  const patient = (patients.body?.data ?? [])[0] ?? null
  if (!patient) {
    built.reason = `no seeded patient available: HTTP ${patients.status}`
    return built
  }
  built.patient = { id: patient.id, name: `${patient.first_name} ${patient.last_name}` }

  const professionals = await call(
    request,
    admin,
    'GET',
    '/api/users/active?role=odontologo,implantologo'
  )
  const professional = (professionals.body?.data ?? [])[0] ?? null
  built.professional = professional ? { id: professional.id, name: professional.name } : null

  // --- five procedures ------------------------------------------------------
  const prefix = `${MARKER}-${RUN_TOKEN}`
  const baseProcedure = {
    description: `${MARKER} procedimiento creado por la sesión de auditoría`,
    default_duration_minutes: 30,
    is_active: true,
    requires_anesthesia: false,
    requires_radiographs: false
  }

  built.procedures.surface = await createProcedure(request, admin, {
    ...baseProcedure,
    code: `${prefix}-SIT`,
    name: `${MARKER} ${RUN_TOKEN} sello oclusal de superficie`,
    specialty_id: built.specialties[0].id,
    // #293 S1/S7: applicability and a public nomenclator code, the two fields a
    // duplicate must carry like every other field it copies.
    site_type: 'surface',
    external_codes: [
      {
        system: 'minsa_rm550_2023',
        code: '997301',
        description: `${MARKER} nomenclador público`
      }
    ],
    requirements: `${MARKER} ayuno no requerido; consentimiento firmado`,
    default_cost: 500,
    default_duration_minutes: 45
  })

  built.procedures.beta = await createProcedure(request, admin, {
    ...baseProcedure,
    code: `${prefix}-B`,
    name: `${MARKER} ${RUN_TOKEN} resina compuesta`,
    specialty_id: built.specialties[0].id,
    default_cost: 200,
    requirements: `${MARKER} requisito de la fila B`
  })

  built.procedures.gamma = await createProcedure(request, admin, {
    ...baseProcedure,
    code: `${prefix}-C`,
    name: `${MARKER} ${RUN_TOKEN} corona de zirconio`,
    specialty_id: built.specialties[1]?.id ?? built.specialties[0].id,
    default_cost: 300
  })

  built.procedures.delta = await createProcedure(request, admin, {
    ...baseProcedure,
    code: `${prefix}-D`,
    name: `${MARKER} ${RUN_TOKEN} endodoncia unirradicular`,
    specialty_id: built.specialties[1]?.id ?? built.specialties[0].id,
    default_cost: 400
  })

  // A dedicated row for the role matrix's favourite write/delete probe, so the
  // walkthrough's favourites are never consumed by the contract rows.
  built.procedures.matrix = await createProcedure(request, admin, {
    ...baseProcedure,
    code: `${prefix}-M`,
    name: `${MARKER} ${RUN_TOKEN} fila de la matriz de roles`,
    specialty_id: built.specialties[0].id,
    default_cost: 100
  })

  const refusedProcedure = Object.entries(built.procedures).find(([, entry]) => !entry.id)
  if (refusedProcedure) {
    built.reason = `the procedure "${refusedProcedure[0]}" was refused: HTTP ${refusedProcedure[1].status} ${refusedProcedure[1].message ?? ''}`
    return built
  }

  // --- the session-owned branch administrator ------------------------------
  // The username is fixed (the issue names the fixture), so a run that died
  // before its teardown can leave last night's account behind: the session
  // claims the name by retiring that stale row first, never by reusing it.
  const existingUsers = await call(request, admin, 'GET', '/api/users')
  const staleUser = (existingUsers.body?.data ?? []).find(
    entry => entry.username === BRANCH_USER.username
  )
  built.userClaim = { staleFound: Boolean(staleUser), staleId: staleUser?.id ?? null }

  if (staleUser) {
    const removal = await call(request, admin, 'DELETE', `/api/users/${staleUser.id}`)
    built.userClaim.staleStatus = removal.status
    built.userClaim.staleRemoved = removal.ok === true
  }

  const createdUser = await call(request, admin, 'POST', '/api/users', {
    name: BRANCH_USER.name,
    username: BRANCH_USER.username,
    email: BRANCH_USER.email,
    password: BRANCH_USER.password,
    role: 'administrador',
    branch_id: built.branch.id,
    is_active: true
  })

  built.user = {
    id: createdUser.body?.data?.id ?? null,
    username: BRANCH_USER.username,
    branchId: createdUser.body?.data?.branch_id ?? null,
    role: createdUser.body?.data?.role ?? null,
    status: createdUser.status,
    message: createdUser.message,
    errors: createdUser.errors,
    source: 'POST /api/users (the run owns the row)'
  }

  if (!built.user.id) {
    built.reason = `the branch administrator was refused: HTTP ${createdUser.status} ${createdUser.message ?? ''}`
    return built
  }

  const branchSession = await loginCredential(request, BRANCH_USER.username, BRANCH_USER.password)
    .then(session => session)
    .catch(thrown => ({ error: String(thrown?.message ?? thrown) }))

  if (branchSession.error) {
    built.reason = `the branch administrator could not sign in: ${branchSession.error}`
    return built
  }

  built.user.session = {
    id: branchSession.user.id,
    name: branchSession.user.name,
    role: branchSession.user.role,
    branchId: branchSession.user.branch_id ?? null,
    permissions: [...(branchSession.user.permissions ?? [])]
  }

  // --- the branch price, inside a validity window --------------------------
  const validFrom = day
  const validTo = addDaysLocal(day, 60)
  const savedPrice = await call(
    request,
    branchSession,
    'PUT',
    `/api/branches/${built.branch.id}/procedure-prices`,
    {
      items: [
        {
          procedure_catalog_id: built.procedures.surface.id,
          price: 375,
          valid_from: validFrom,
          valid_to: validTo,
          is_active: true
        }
      ]
    }
  )

  const priceRow = (savedPrice.body?.data ?? [])[0] ?? null
  built.branchPrice = {
    id: priceRow?.id ?? null,
    procedureId: built.procedures.surface.id,
    branchId: built.branch.id,
    price: priceRow ? priceOf(priceRow.price) : 375,
    listPrice: built.procedures.surface.defaultCost,
    validFrom: priceRow?.valid_from ?? validFrom,
    validTo: priceRow?.valid_to ?? validTo,
    status: savedPrice.status,
    message: savedPrice.message,
    errors: savedPrice.errors,
    author: BRANCH_USER.username,
    source: 'PUT /api/branches/{branch}/procedure-prices (the run owns the row)'
  }

  if (!built.branchPrice.id) {
    built.reason = `the branch price was refused: HTTP ${savedPrice.status} ${savedPrice.message ?? ''}`
    return built
  }

  // --- two treatment plans, the population the statistics read -------------
  const patientId = built.patient.id
  built.plans.first = await createPlan(request, admin, {
    patient_id: patientId,
    title: `${MARKER} ${RUN_TOKEN} plan de superficies`,
    branch_id: built.branch.id,
    description: `${MARKER} plan que alimenta /api/admin/procedure-stats`,
    items: [
      {
        description: built.procedures.surface.name,
        procedure_name: built.procedures.surface.name,
        procedure_catalog_id: built.procedures.surface.id,
        quantity: 2,
        unit_cost: 375,
        // #293 S1/S2: a line that names a surface procedure declares its
        // surface; the rule derives the piece from it.
        tooth_surface_id: built.toothSurface.id,
        phase_number: 1,
        sequence: 1,
        status: 'pending'
      },
      {
        description: built.procedures.beta.name,
        procedure_name: built.procedures.beta.name,
        procedure_catalog_id: built.procedures.beta.id,
        quantity: 1,
        unit_cost: 200,
        phase_number: 1,
        sequence: 2,
        status: 'pending'
      }
    ]
  })

  built.plans.second = await createPlan(request, admin, {
    patient_id: patientId,
    title: `${MARKER} ${RUN_TOKEN} plan de rehabilitación`,
    branch_id: built.branch.id,
    description: `${MARKER} segundo plan del período`,
    items: [
      {
        description: built.procedures.gamma.name,
        procedure_name: built.procedures.gamma.name,
        procedure_catalog_id: built.procedures.gamma.id,
        quantity: 1,
        unit_cost: 300,
        phase_number: 1,
        sequence: 1,
        status: 'pending'
      }
    ]
  })

  // --- favourites: the rows the clinical desks read ------------------------
  const favoriteSeeds = [
    { role: 'odontologo', session: odontologo, procedureId: built.procedures.surface.id },
    { role: 'odontologo', session: odontologo, procedureId: built.procedures.beta.id },
    { role: 'implantologo', session: implantologo, procedureId: built.procedures.gamma.id }
  ]

  for (const seed of favoriteSeeds) {
    const response = await call(
      request,
      seed.session,
      'POST',
      `/api/procedure-catalog/${seed.procedureId}/favorite`
    )

    built.favorites[`${seed.role}:${seed.procedureId}`] = {
      role: seed.role,
      procedureId: seed.procedureId,
      status: response.status,
      rows: (response.body?.data ?? null) ? 1 : 0,
      message: response.message
    }
  }

  // --- what each read door answers for this run's rows ---------------------
  // The catalogue is ordered by name and one page holds at most 100 rows, so the
  // reach check asks for the row by code instead of hoping it lands in page 1.
  const catalogTotal = await call(request, admin, 'GET', '/api/procedure-catalog?per_page=1')
  const catalogPage = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog?q=${encodeURIComponent(built.procedures.surface.code)}&per_page=100`
  )
  const searchByCode = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog?q=${encodeURIComponent(built.procedures.surface.code)}`
  )
  const searchParamIgnored = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog?search=${encodeURIComponent(built.procedures.surface.code)}`
  )
  const catalogAsReception = await call(
    request,
    await loginAs(request, 'recepcionista'),
    'GET',
    '/api/procedure-catalog?per_page=1'
  )
  const catalogAsOdontologo = await call(
    request,
    odontologo,
    'GET',
    '/api/procedure-catalog?per_page=1'
  )
  const quickSearch = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog/search?q=${encodeURIComponent(built.procedures.surface.code)}&limit=20`
  )
  const quickSearchSede = await call(
    request,
    branchSession,
    'GET',
    `/api/procedure-catalog/search?q=${encodeURIComponent(built.procedures.surface.code)}&limit=20`
  )
  const favoritesOdontologo = await call(
    request,
    odontologo,
    'GET',
    '/api/procedure-catalog-favorites'
  )
  const stats = await call(request, admin, 'GET', `/api/admin/procedure-stats?limit=50`)

  built.reach = {
    catalog: {
      status: catalogPage.status,
      total: catalogTotal.body?.meta?.total ?? null,
      filteredTotal: catalogPage.body?.meta?.total ?? null,
      hasSurface: (catalogPage.body?.data ?? []).some(row => row.id === built.procedures.surface.id)
    },
    searchByCode: {
      status: searchByCode.status,
      total: searchByCode.body?.meta?.total ?? null,
      codes: (searchByCode.body?.data ?? []).map(row => row.code)
    },
    searchParamIgnored: {
      status: searchParamIgnored.status,
      total: searchParamIgnored.body?.meta?.total ?? null,
      note: 'ProcedureCatalogController@index reads `q`; `search` is not a filter'
    },
    catalogAsReception: { status: catalogAsReception.status },
    catalogAsOdontologo: { status: catalogAsOdontologo.status },
    quickSearch: {
      status: quickSearch.status,
      price: (quickSearch.body?.data ?? [])[0]?.default_cost ?? null,
      source: (quickSearch.body?.data ?? [])[0]?.price_source ?? null
    },
    quickSearchSede: {
      status: quickSearchSede.status,
      price: (quickSearchSede.body?.data ?? [])[0]?.default_cost ?? null,
      listCost: (quickSearchSede.body?.data ?? [])[0]?.list_cost ?? null,
      source: (quickSearchSede.body?.data ?? [])[0]?.price_source ?? null,
      branchId: branchSession.user.branch_id ?? null
    },
    favoritesOdontologo: {
      status: favoritesOdontologo.status,
      ids: (favoritesOdontologo.body?.data ?? []).map(row => row.id)
    },
    stats: {
      status: stats.status,
      top: (stats.body?.data?.top_procedures ?? []).length,
      specialties: (stats.body?.data?.by_specialty ?? []).length
    }
  }

  if (!built.reach.catalog.hasSurface) {
    built.reason = 'the created procedure did not appear in GET /api/procedure-catalog'
    return built
  }

  if (!built.reach.searchByCode.codes.includes(built.procedures.surface.code)) {
    built.reason = 'the created procedure is not reachable through ?q='
    return built
  }

  built.ok = true
  built.reason = null

  built.declarations = {
    procedures:
      'created through POST /api/procedure-catalog; DELETE deactivates them (the catalogue never hard-deletes)',
    branchPrice:
      'created through PUT /api/branches/1/procedure-prices by the session-owned branch administrator; retired with DELETE at teardown',
    branchUser:
      'created through POST /api/users (role administrador, branch_id 1) because ProcedurePriceResolver reads the actor sede; deleted with DELETE /api/users/{id}',
    plans:
      'created through POST /api/treatment-plans on a seeded patient; the run deletes only its own draft rows',
    favorites:
      'created through POST /api/procedure-catalog/{id}/favorite by the clinical roles that hold manage.procedure_favorite; detached at teardown',
    noBridge:
      'no tinker and no direct database access: every row, including the fixture account, was written and is retired through the API'
  }

  return built
}

const fixtureSummaryOf = fixtures => ({
  marker: fixtures.marker,
  token: fixtures.token,
  day: fixtures.day,
  ok: fixtures.ok === true,
  reason: fixtures.reason ?? null,
  sessions: fixtures.sessions ?? {},
  permissions: fixtures.permissions ?? {},
  user: fixtures.user ?? null,
  userClaim: fixtures.userClaim ?? null,
  branch: fixtures.branch ?? null,
  specialties: fixtures.specialties ?? [],
  toothSurface: fixtures.toothSurface ?? null,
  patient: fixtures.patient ?? null,
  professional: fixtures.professional ?? null,
  procedures: Object.fromEntries(
    Object.entries(fixtures.procedures ?? {}).map(([key, entry]) => [
      key,
      {
        id: entry.id,
        code: entry.code,
        name: entry.name,
        siteType: entry.siteType,
        externalCodes: entry.externalCodes,
        requirements: entry.requirements,
        defaultCost: entry.defaultCost,
        status: entry.status
      }
    ])
  ),
  branchPrice: fixtures.branchPrice ?? null,
  plans: fixtures.plans ?? {},
  favorites: fixtures.favorites ?? {},
  baseline: fixtures.baseline ?? {},
  declarations: fixtures.declarations ?? {},
  reach: fixtures.reach ?? {},
  createdByWalk: fixtures.createdByWalk ?? [],
  teardown: fixtures.teardown ?? [],
  leftovers: fixtures.leftovers ?? []
})

const publishFixtures = fixtures =>
  audit('fixtures.json', { ...stamp(), stage: 'layer-0', ...fixtureSummaryOf(fixtures) })

const STATE_FILE = resolve(process.cwd(), U8_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U8_FIXTURES__ ??= {
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
    ...stamp(),
    session: 'U8',
    out: U8_EVIDENCE_DIR,
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
 * Builds the U8 fixtures once per worker and hands them to every row that needs
 * them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = request => {
  if (!REGISTRY.promise) {
    REGISTRY.promise = (async () => {
      const fixtures = await buildFixtures(request).catch(thrown => ({
        marker: MARKER,
        token: RUN_TOKEN,
        day: localDay(),
        ok: false,
        reason: `fixture build failed: ${String(thrown?.message ?? thrown).slice(0, 400)}`,
        sessions: {},
        permissions: {},
        procedures: {},
        plans: {},
        favorites: {},
        createdByWalk: [],
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

export const procedureFixture = (fixtures, key) => fixtures?.procedures?.[key] ?? null

export const branchPriceFixture = fixtures => fixtures?.branchPrice ?? null

/** The credential the branch price needs; the walk signs in with it once. */
export const branchCredential = () => ({ ...BRANCH_USER })

/** A row a walkthrough created through the UI; teardown tries to retire it. */
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
  const leftovers = []

  const record = (type, id, outcome, reason) => {
    // A 404 means the walkthrough already retired the row: it is not a residue.
    const alreadyGone = outcome.status === 404
    const entry = { type, id, ...outcome }

    if (alreadyGone) entry.alreadyGone = true
    steps.push(entry)

    if (outcome.deleted !== true && !alreadyGone) {
      leftovers.push({
        type,
        id,
        reason: reason ?? outcome.message ?? 'the API refused the delete'
      })
    }
  }

  let branchSession = null

  if (fixtures.user?.username) {
    branchSession = await loginCredential(request, fixtures.user.username, BRANCH_USER.password)
      .then(session => session)
      .catch(() => null)
  }

  // --- branch prices, with the actor that created them ---------------------
  const priceTargets = [
    ...(fixtures.branchPrice?.id
      ? [
          {
            id: fixtures.branchPrice.id,
            branchId: fixtures.branchPrice.branchId,
            origin: 'fixture'
          }
        ]
      : []),
    ...(fixtures.createdByWalk ?? [])
      .filter(entry => entry.type === 'branch-price' && entry.deleted !== true)
      .map(entry => ({ id: entry.id, branchId: entry.branchId, origin: 'walkthrough' }))
  ]

  for (const target of priceTargets) {
    if (!target.id || !branchSession) {
      leftovers.push({ type: 'branch-price', id: target.id ?? null, reason: 'no branch actor' })
      continue
    }

    const response = await call(
      request,
      branchSession,
      'DELETE',
      `/api/branches/${target.branchId}/procedure-prices/${target.id}`
    )
    record(
      `branch-price:${target.origin}`,
      target.id,
      { branchId: target.branchId, status: response.status, deleted: response.ok === true },
      'the API refused the delete (the row may already be gone)'
    )
  }

  // --- favourites ----------------------------------------------------------
  const detachFavorite = async (session, role, procedureId, origin) => {
    if (!procedureId) return

    const response = await call(
      request,
      session,
      'DELETE',
      `/api/procedure-catalog/${procedureId}/favorite`
    )
    record(
      `favorite:${role}:${origin}`,
      procedureId,
      { status: response.status, deleted: response.ok === true },
      'the API refused the detach'
    )
  }

  for (const [key, entry] of Object.entries(fixtures.favorites ?? {})) {
    const session = await loginAs(request, entry.role)
    await detachFavorite(session, entry.role, entry.procedureId, key)
  }

  for (const entry of fixtures.createdByWalk ?? []) {
    if (entry.type !== 'favorite' || entry.deleted === true) continue
    const session = await loginAs(request, entry.role)
    await detachFavorite(session, entry.role, entry.procedureId, 'walkthrough')
  }

  // --- treatment plans -----------------------------------------------------
  for (const [key, plan] of Object.entries(fixtures.plans ?? {})) {
    if (!plan?.id) continue

    const response = await call(request, admin, 'DELETE', `/api/treatment-plans/${plan.id}`)
    record(
      `treatment-plan:${key}`,
      plan.id,
      { status: response.status, deleted: response.ok === true, message: response.message },
      'the plan was no longer a draft when the run finished'
    )
  }

  // --- the procedures (DELETE deactivates: the catalogue never hard-deletes)
  const procedureTargets = [
    ...Object.entries(fixtures.procedures ?? {}).map(([key, entry]) => ({
      key,
      id: entry.id,
      origin: 'fixture'
    })),
    ...(fixtures.createdByWalk ?? [])
      .filter(entry => entry.type === 'procedure' && entry.deleted !== true)
      .map(entry => ({ key: entry.code, id: entry.id, origin: 'walkthrough' }))
  ]

  for (const target of procedureTargets) {
    if (!target.id) continue

    const response = await call(request, admin, 'DELETE', `/api/procedure-catalog/${target.id}`)
    record(
      `procedure:${target.origin}`,
      target.id,
      {
        key: target.key,
        status: response.status,
        deleted: response.ok === true,
        deactivated: response.ok === true,
        message: response.message
      },
      'DELETE deactivates the procedure instead of removing it; the row stays inactive'
    )
  }

  // --- the session-owned account, last -------------------------------------
  if (fixtures.user?.id) {
    const response = await call(request, admin, 'DELETE', `/api/users/${fixtures.user.id}`)
    record(
      'user',
      fixtures.user.id,
      {
        username: fixtures.user.username,
        status: response.status,
        deleted: response.ok === true,
        message: response.message
      },
      'the API refused the delete: the account stays as a declared residue'
    )
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps, leftovers })
  return steps
}

export const retireFixtures = request => retire(request, REGISTRY.fixtures)

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => fixtureSummaryOf(fixtures ?? {})
