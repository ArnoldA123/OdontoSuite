// Plan #13 (issue #373, U10) — layer 2: the three desks of the session, by role.
//
// Business Intelligence, the continuity queue and the prosthetic laboratory, each
// index only: the session declares no detail route (the compliance window and the
// laboratory case detail live in the dialogs the walks measure, never here).
//
// Every surface renders for the role the layer-1 manifest offers it to plus the
// negative role `rolePlans` derives from that same manifest (maximum three
// roles). Desktop is measured at 1440×900 and then the page reloads at 390×844
// (never resize-then-measure). The negative row is a render and gate check: the
// page paints, the surface's own read/write door refuses with the role's real
// token, and `denial` records which affordances stayed visible.
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

import { ensureFixtures, fixtureSummary } from './fixtures.js'

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

const SURFACES = Object.freeze([
  {
    route: '/business-intelligence',
    slug: 'business-intelligence',
    page: 'resources/js/modules/business-intelligence/BusinessIntelligencePage.vue',
    label: 'Business Intelligence',
    href: () => '/business-intelligence'
  },
  {
    route: '/continuity',
    slug: 'continuity',
    page: 'resources/js/modules/continuity/ContinuityPage.vue',
    label: 'Continuidad',
    href: () => '/continuity'
  },
  {
    route: '/lab-cases',
    slug: 'lab-cases',
    page: 'resources/js/modules/lab-cases/LabCasesPage.vue',
    label: 'Laboratorio protésico',
    href: () => '/lab-cases'
  }
])

// The layer-1 manifest owns the roles; the fallback is the measured navigation
// (BI belongs to administration and finance, the other two to the appointment
// desks, which leaves finance as the negative role of both).
const FALLBACK_ROLES = Object.freeze({
  '/business-intelligence': ['administrador', 'finanzas'],
  '/continuity': [
    'administrador',
    'recepcionista',
    'odontologo',
    'implantologo',
    'tecnico_dental',
    'asistente'
  ],
  '/lab-cases': [
    'administrador',
    'recepcionista',
    'odontologo',
    'implantologo',
    'tecnico_dental',
    'asistente'
  ]
})

const manifest = loadSessionManifest()

const rolesFor = route => {
  const declared = manifest?.surfaces?.find(entry => entry.route === route)?.roles

  return declared?.length ? declared : (FALLBACK_ROLES[route] ?? ['administrador'])
}

const surfaces = applyScope(
  {
    surfaces: SURFACES.map(surface => ({ ...surface, roles: rolesFor(surface.route) }))
  },
  SCOPE
)

// The door each surface owns, probed with the negative role's real token. BI is
// measured on its read door (a role without `view.report` is refused the report
// itself); the other two are measured on their write door with an empty body,
// which never creates a row: a granted role answers 422 before validation, a
// denied one 403 before it.
const DOORS = Object.freeze({
  '/business-intelligence': { method: 'GET', path: () => '/api/reports/dashboard' },
  '/continuity': {
    method: 'POST',
    path: () => '/api/patient-recalls',
    body: () => ({})
  },
  '/lab-cases': { method: 'POST', path: () => '/api/lab-cases', body: () => ({}) }
})

// The BI page paints its shell before the report arrives, so a content threshold
// alone measures the filters: the report itself announces its state through one
// of these hooks (KPIs, table, empty card, error block).
const REPORT_PAINTED =
  '[data-bi-kpis], [data-report-table-section], [data-report-empty], [data-state="error"]'

const settleReport = async (page, surface) => {
  if (surface.route !== '/business-intelligence') return

  await page
    .waitForSelector(REPORT_PAINTED, { timeout: 20000 })
    .catch(() => {})
  await settle(page)
}

const renderRow = async ({ page, session, fixtureData, surface, plan }) => {
  const diagnostics = collectDiagnostics(page)
  const door = DOORS[surface.route]
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
    await settleReport(page, surface)
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
      const probe = door
        ? await api(session, door.method, door.path(fixtureData), door.body?.(fixtureData))
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
    await settleReport(page, surface)
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
    test(`@U10 · capa 2 · ${surface.label} como ${plan.role}`, async ({ page }) => {
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
