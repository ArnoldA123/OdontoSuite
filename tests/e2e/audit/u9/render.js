// Plan #13 (issue #372, U9) — layer 2: the twelve administration surfaces, by role.
//
// The eight ABMs of the administration desk: professionals, environments,
// appointment types, specialties, branches, payment methods, the accounting lock
// and the branch transfers, each index and its three detail surfaces. Every
// surface renders for the role the navigation offers it to plus the negative
// role `rolePlans` derives from the layer-1 manifest (maximum three roles).
//
// Desktop is measured at 1440×900 and then the page reloads at 390×844 (never
// resize-then-measure). The negative row is a render and gate check: the page
// paints, the API refuses, and `denial` records which write affordances stayed
// visible and what the surface's own write door answered with a real token.
//
// This is measurement code: a defect is recorded in the JSON, never thrown.

import { test, expect } from 'playwright/test'

import {
  applyScope,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  primeSession,
  readTabLabels,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary, sampleIdFor } from './fixtures.js'

import {
  DESKTOP,
  MOBILE,
  SESSION,
  THRESHOLD,
  api,
  audit,
  classifyConsole,
  gotoSurface,
  headingCount,
  headings,
  inlineError,
  loadSessionManifest,
  login,
  overflow,
  rolePlanFor,
  rowCount,
  settle,
  shot,
  stamp,
  tableHeaders,
  visibleTexts,
  writeAffordances
} from './helpers.js'

const SCOPE = process.env.AUDIT_SCOPE || 'all'

const ALL_ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

const SURFACES = Object.freeze([
  {
    route: '/professionals',
    slug: 'professionals',
    page: 'resources/js/modules/professionals/ProfessionalsPage.vue',
    label: 'Profesionales',
    href: () => '/professionals'
  },
  {
    route: '/professionals/:id',
    slug: 'professionals-detail',
    page: 'resources/js/modules/professionals/ProfessionalDetailPage.vue',
    label: 'Detalle del profesional',
    href: fixtures => `/professionals/${sampleIdFor(fixtures, '/professionals/:id')}`
  },
  {
    route: '/environments',
    slug: 'environments',
    page: 'resources/js/modules/environments/EnvironmentsPage.vue',
    label: 'Ambientes',
    href: () => '/environments'
  },
  {
    route: '/environments/:id',
    slug: 'environments-detail',
    page: 'resources/js/modules/environments/EnvironmentDetailPage.vue',
    label: 'Detalle del ambiente',
    href: fixtures => `/environments/${sampleIdFor(fixtures, '/environments/:id')}`
  },
  {
    route: '/appointment-types',
    slug: 'appointment-types',
    page: 'resources/js/modules/appointment-types/AppointmentTypesPage.vue',
    label: 'Tipos de cita',
    href: () => '/appointment-types'
  },
  {
    route: '/appointment-types/:id',
    slug: 'appointment-types-detail',
    page: 'resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue',
    label: 'Detalle del tipo de cita',
    href: fixtures => `/appointment-types/${sampleIdFor(fixtures, '/appointment-types/:id')}`
  },
  {
    route: '/specialties',
    slug: 'specialties',
    page: 'resources/js/modules/specialties/SpecialtiesPage.vue',
    label: 'Especialidades',
    href: () => '/specialties'
  },
  {
    route: '/settings/branches',
    slug: 'settings-branches',
    page: 'resources/js/modules/settings/branches/BranchesPage.vue',
    label: 'Sucursales',
    href: () => '/settings/branches'
  },
  {
    route: '/settings/branches/:id',
    slug: 'settings-branches-detail',
    page: 'resources/js/modules/settings/branches/BranchDetailPage.vue',
    label: 'Detalle de la sucursal',
    href: fixtures => `/settings/branches/${sampleIdFor(fixtures, '/settings/branches/:id')}`
  },
  {
    route: '/settings/payment-methods',
    slug: 'settings-payment-methods',
    page: 'resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue',
    label: 'Métodos de pago',
    href: () => '/settings/payment-methods'
  },
  {
    route: '/settings/accounting-locks',
    slug: 'settings-accounting-locks',
    page: 'resources/js/modules/accounting-locks/AccountingLocksPage.vue',
    label: 'Bloqueo contable',
    href: () => '/settings/accounting-locks'
  },
  {
    route: '/branch-transfers',
    slug: 'branch-transfers',
    page: 'resources/js/modules/branch-transfers/BranchTransfersPage.vue',
    label: 'Traslados de sede',
    href: () => '/branch-transfers'
  }
])

// The layer-1 manifest owns the roles; the fallback is the measured navigation
// of the session (#305 opens transfers to every role, the rest to administration).
const FALLBACK_ROLES = Object.freeze(
  Object.fromEntries(SURFACES.map(surface => [surface.route, ['administrador']]))
)

const manifest = loadSessionManifest()

const rolesFor = route => {
  if (route === '/branch-transfers') return ALL_ROLES

  const declared = manifest?.surfaces?.find(entry => entry.route === route)?.roles

  return declared?.length ? declared : (FALLBACK_ROLES[route] ?? ['administrador'])
}

const surfaces = applyScope(
  {
    surfaces: SURFACES.map(surface => ({ ...surface, roles: rolesFor(surface.route) }))
  },
  SCOPE
)

// The write door each surface owns, probed with the negative role's real token.
// The empty body never creates a row: a granted role answers 422, a denied one
// 403 before validation.
const WRITE_DOORS = Object.freeze({
  '/professionals': { method: 'POST', path: () => '/api/users', body: () => ({}) },
  '/professionals/:id': {
    method: 'PUT',
    path: fixtures => `/api/users/${fixtures.professionals.plain.id}`,
    body: () => ({})
  },
  '/environments': { method: 'POST', path: () => '/api/dental-chairs', body: () => ({}) },
  '/environments/:id': {
    method: 'PUT',
    path: fixtures => `/api/dental-chairs/${fixtures.chairs.detail.id}`,
    body: () => ({})
  },
  '/appointment-types': {
    method: 'POST',
    path: () => '/api/appointment-types',
    body: () => ({})
  },
  '/appointment-types/:id': {
    method: 'PUT',
    path: fixtures => `/api/appointment-types/${fixtures.appointmentTypes.detail.id}`,
    body: () => ({})
  },
  '/specialties': { method: 'POST', path: () => '/api/specialties', body: () => ({}) },
  '/settings/branches': { method: 'POST', path: () => '/api/branches', body: () => ({}) },
  '/settings/branches/:id': {
    method: 'PUT',
    path: fixtures => `/api/branches/${fixtures.branches.target.id}`,
    body: () => ({})
  },
  '/settings/payment-methods': {
    method: 'POST',
    path: () => '/api/payment-methods',
    body: () => ({})
  },
  '/settings/accounting-locks': {
    method: 'PUT',
    path: () => '/api/accounting-locks/quotations',
    body: fixtures => ({ locked_through: fixtures.day })
  },
  '/branch-transfers': {
    method: 'POST',
    path: () => '/api/branch-transfers',
    body: () => ({})
  }
})

const renderRow = async ({ page, session, fixtureData, surface, plan }) => {
  const diagnostics = collectDiagnostics(page)
  const row = {
    ...stamp(),
    sessionWorker: SESSION,
    route: surface.route,
    slug: surface.slug,
    label: surface.label,
    role: plan.role,
    expectation: plan.expectation,
    href: surface.href(fixtureData),
    url: null,
    httpStatus: null,
    rendered: false,
    contentLength: 0,
    threshold: THRESHOLD,
    headingCount: 0,
    headings: null,
    tabs: [],
    tableHeaders: [],
    rows: 0,
    rowTexts: [],
    inlineError: null,
    horizontalOverflow: null,
    viewports: { desktop: { ...DESKTOP } },
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] },
    denial: null,
    fixtures: null
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const navigation = await gotoSurface(page, row.href)
    row.httpStatus = navigation.status
    row.url = navigation.url

    await settleSurface(page, THRESHOLD, plan.expectation === 'negative' ? 6000 : 15000)
    await settle(page)

    row.contentLength = await contentLength(page)
    row.rendered = row.contentLength > THRESHOLD
    row.headings = await headings(page)
    row.headingCount = row.headings.count
    row.tabs = await readTabLabels(page)
    row.tableHeaders = await tableHeaders(page)
    row.rows = await rowCount(page, 'main tbody tr')
    row.rowTexts = (await visibleTexts(page, 'main tbody tr')).slice(0, 10)
    row.inlineError = await inlineError(page)

    if (plan.expectation === 'negative') {
      const door = WRITE_DOORS[surface.route]
      const probe = door
        ? await api(session, door.method, door.path(fixtureData), door.body(fixtureData))
        : null

      row.denial = {
        writeAffordances: await writeAffordances(page),
        checked: {
          role: plan.role,
          surface: surface.route,
          writeDoor: door
            ? { method: door.method, path: door.path(fixtureData), status: probe?.status ?? null }
            : null
        }
      }
    }

    row.shots.push(await shot(page, `shots/${surface.slug}-${plan.role}-desktop.png`))

    // Mobile: a fresh document at 390, never a measured resize.
    await page.setViewportSize(MOBILE)
    await page.reload({ waitUntil: 'domcontentloaded' })
    await settle(page)
    await settleSurface(page, THRESHOLD, plan.expectation === 'negative' ? 6000 : 15000)
    await settle(page)

    const measured = await overflow(page)
    row.horizontalOverflow = measured.overflow
    row.viewports.mobile = {
      ...MOBILE,
      scrollWidth: measured.scrollWidth,
      innerWidth: measured.innerWidth
    }

    row.shots.push(await shot(page, `shots/${surface.slug}-${plan.role}-mobile.png`))
  } finally {
    row.headingCount = row.headings?.count ?? (await headingCount(page).catch(() => 0))
    row.pageErrors = diagnostics.pageErrors.map(error => String(error))
    row.consoleErrors = diagnostics.consoleErrors
    row.consoleClassified = classifyConsole(diagnostics.consoleErrors, plan.expectation)
    row.failedRequests = classifyFailures(diagnostics.failedRequests, plan.expectation)
    row.fixtures = fixtureSummary(fixtureData)
    audit(`render/render-${surface.slug}-${plan.role}`, row)
  }

  return row
}

for (const surface of surfaces) {
  for (const plan of rolePlanFor(surface)) {
    test(`@U9 · capa 2 · ${surface.label} como ${plan.role}`, async ({ page }) => {
      test.setTimeout(180000)

      const fixtures = await ensureFixtures()

      if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

      const session = await login(plan.role)
      const row = await renderRow({ page, session, fixtureData: fixtures, surface, plan })

      expect(row.shots, `captures of ${surface.route} for ${plan.role}`).toHaveLength(2)
      expect(row.pageErrors, `page errors of ${surface.route} for ${plan.role}`).toEqual([])
      expect(row.contentLength, `content of ${surface.route} for ${plan.role}`).toBeGreaterThan(0)
      expect(
        row.horizontalOverflow,
        `horizontal document overflow of ${surface.route} at 390 px`
      ).toBe(false)
      // Layer 2's allowlist: an environmental console error (Reverb down) or a
      // negative row's own 401/403 is classified; anything else is unexpected.
      expect(
        row.consoleClassified.unexpected,
        `unexpected console errors of ${surface.route} for ${plan.role}`
      ).toEqual([])
      expect(
        row.failedRequests.unexpected,
        `unexpected HTTP failures of ${surface.route} for ${plan.role}`
      ).toEqual([])

      if (plan.expectation !== 'negative') {
        expect(row.rendered, `rendered content of ${surface.route} for ${plan.role}`).toBe(true)
      }
    })
  }
}
