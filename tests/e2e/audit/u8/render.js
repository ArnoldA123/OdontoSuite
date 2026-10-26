// Plan #13 (issue #371, U8) — layer 2: the five catalogue surfaces, by role.
//
// `/procedure-catalog` (index), `/procedure-catalog/:id` (detail), the usage
// statistics, the clinical favourites desk and the reception consultation. Each
// row renders the surface for the role the navigation offers it to, plus the
// role #371 names as the negative gate of that surface.
//
// Desktop is measured at 1440×900 and then the page reloads at 390×844 (never
// resize-then-measure). The negative row is a render and gate check — `/proc
// edure-catalog` reads are 200 for every role, so a recepcionista renders the
// whole list while the write doors answer 403: that gap is the measurement.
//
// This is measurement code: a defect is recorded in the JSON, never thrown.
//
// A note on the index reader: the thin `[data-procedure-row]` census is
// deliberately tolerant of the empty state, because a filtered row (the search
// steps of the walkthrough) has no rows at all and the reader must still answer.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  contentLength,
  hasHorizontalOverflow,
  loginAs,
  primeSession,
  readTabLabels,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  CATALOG_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  MY_PROCEDURES_ROUTE,
  RECEPTION_ROUTE,
  STATS_ROUTE,
  THRESHOLD,
  audit,
  classifyConsole,
  readCatalog,
  readDetail,
  readFavorites,
  readReception,
  readShell,
  readStats,
  settleCatalog,
  settleCatalogDetail,
  settleFavorites,
  settleReception,
  settleStats,
  shot,
  stamp,
  sweepTabs,
  watchRequests
} from './helpers.js'

const SURFACES = Object.freeze([
  {
    slug: 'procedure-catalog',
    route: CATALOG_ROUTE,
    label: 'Catálogo de procedimientos',
    entry: CATALOG_ROUTE,
    href: () => CATALOG_ROUTE,
    settle: page => settleCatalog(page),
    read: readCatalog,
    sweep: false
  },
  {
    slug: 'procedure-catalog-detail',
    route: `${CATALOG_ROUTE}/:id`,
    label: 'Detalle del procedimiento',
    entry: CATALOG_ROUTE,
    href: fixtures => `${CATALOG_ROUTE}/${fixtures.procedures.surface.id}`,
    settle: page => settleCatalogDetail(page),
    read: readDetail,
    sweep: true
  },
  {
    slug: 'procedure-stats',
    route: STATS_ROUTE,
    label: 'Estadísticas del catálogo',
    entry: STATS_ROUTE,
    href: () => STATS_ROUTE,
    settle: page => settleStats(page),
    read: readStats,
    sweep: false
  },
  {
    slug: 'my-procedures',
    route: MY_PROCEDURES_ROUTE,
    label: 'Mis procedimientos',
    entry: MY_PROCEDURES_ROUTE,
    href: () => MY_PROCEDURES_ROUTE,
    settle: page => settleFavorites(page),
    read: readFavorites,
    sweep: false
  },
  {
    slug: 'reception-procedures',
    route: RECEPTION_ROUTE,
    label: 'Catálogo de recepción',
    entry: RECEPTION_ROUTE,
    href: () => RECEPTION_ROUTE,
    settle: page => settleReception(page),
    read: readReception,
    sweep: false
  }
])

// Who the manifest's navigation offers each surface to, and who is excluded:
// the negative role is named by #371 (recepción for the catalog write gate,
// odontología for the statistics).
const PLANS = Object.freeze({
  'procedure-catalog': [
    { role: 'administrador', expectation: 'admin' },
    { role: 'recepcionista', expectation: 'negative' }
  ],
  'procedure-catalog-detail': [
    { role: 'administrador', expectation: 'admin' },
    { role: 'recepcionista', expectation: 'negative' }
  ],
  'procedure-stats': [
    { role: 'administrador', expectation: 'admin' },
    { role: 'finanzas', expectation: 'owner' },
    { role: 'odontologo', expectation: 'negative' }
  ],
  'my-procedures': [
    { role: 'odontologo', expectation: 'owner' },
    { role: 'recepcionista', expectation: 'negative' }
  ],
  'reception-procedures': [
    { role: 'recepcionista', expectation: 'owner' },
    { role: 'finanzas', expectation: 'member' },
    { role: 'odontologo', expectation: 'negative' }
  ]
})

const NAV_ENTRIES = Object.freeze([
  CATALOG_ROUTE,
  STATS_ROUTE,
  MY_PROCEDURES_ROUTE,
  RECEPTION_ROUTE
])

const analyzeRequests = (entries, role) => {
  const relevant = entries.filter(entry => entry.path !== '/api/broadcasting/auth')

  return {
    total: relevant.length,
    role,
    statuses: relevant.reduce((accumulator, entry) => {
      const key = `${entry.method} ${entry.path} → ${entry.status ?? 'pending'}`
      accumulator[key] = (accumulator[key] ?? 0) + 1
      return accumulator
    }, {})
  }
}

const renderRow = async ({ page, session, surface, role, expectation, fixtureData }) => {
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const row = {
    route: surface.route,
    slug: surface.slug,
    label: surface.label,
    role,
    expectation,
    href: surface.href(fixtureData),
    ...stamp(),
    url: null,
    httpStatus: null,
    rendered: false,
    contentLength: 0,
    horizontalOverflow: null,
    viewports: { desktop: { ...DESKTOP } },
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] },
    shell: null,
    data: null,
    tabs: [],
    navigation: null,
    denial: null,
    mobile: null,
    fixtures: null
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const response = await page.goto(row.href)
    row.httpStatus = response?.status() ?? null
    row.url = page.url()

    await page
      .locator('main')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})

    await settleSurface(page, THRESHOLD, expectation === 'negative' ? 8000 : LOAD_TIMEOUT)
    await surface.settle(page)

    row.shell = await readShell(page)
    row.navigation = {
      entries: row.shell.sidebar.map(link => link.href),
      catalogEntries: NAV_ENTRIES.filter(route =>
        row.shell.sidebar.some(link => link.href === route)
      ),
      expectedEntry: surface.entry,
      expectedEntryVisible: row.shell.sidebar.some(link => link.href === surface.entry)
    }

    row.contentLength = await contentLength(page)
    row.rendered = row.contentLength > THRESHOLD
    // Layer 2 asks for one `h1` per page: the shell owns it and the page titles
    // are `h2` (the detail is the documented exception, and it keeps the count).
    row.headingCount = row.shell.h1.count
    row.tabs = await readTabLabels(page)
    row.data = await surface.read(page)

    if (surface.sweep) {
      const sweep = await sweepTabs(page, surface.read)
      row.tabSurfaces = sweep.surfaces.map(entry => ({
        id: entry.id,
        label: entry.label,
        clicked: entry.clicked,
        title: entry.data?.title ?? null,
        auditRows: entry.data?.audit?.rows ?? null,
        auditError: entry.data?.audit?.errorText ?? null,
        versions: entry.data?.versions?.rows ?? null,
        versionsError: entry.data?.versions?.errorText ?? null,
        tariff: entry.data?.tariff?.present ?? null
      }))
      row.sweptTabs = sweep.tabs.map(tab => tab.label)
      row.data = await surface.read(page)
    }

    if (expectation === 'negative') {
      row.denial = {
        requests: requests
          .filter(entry => entry.status === 403 || entry.status === 401)
          .map(entry => ({ method: entry.method, path: entry.path, status: entry.status })),
        errorText:
          row.data?.error?.text ?? row.data?.errorText ?? row.data?.errorRetry ?? null ?? null,
        // The write affordances the negative role should not see. The index
        // renders them today; the detail hides only the tariff tab.
        writeAffordances: [
          row.data?.createVisible ? 'Nuevo procedimiento' : null,
          row.data?.importVisible ? 'Importar CSV' : null,
          ...(row.data?.rowActions ?? []).map(action => `fila:${action}`),
          ...(row.data?.writeControls ?? [])
        ].filter(Boolean),
        tariffTabVisible: (row.tabs ?? []).some(label => /Tarifario/i.test(label)),
        editVisible: row.data?.editVisible ?? null
      }
    }

    row.shots.push(await shot(page, `render/${surface.slug}-${role}-desktop.png`))

    // Mobile: a fresh load, never a measured resize.
    await page.setViewportSize(MOBILE)
    await page.reload()
    await settleSurface(page, THRESHOLD, expectation === 'negative' ? 8000 : LOAD_TIMEOUT)
    await surface.settle(page)

    const overflow = await hasHorizontalOverflow(page)
    row.horizontalOverflow = overflow.overflow
    row.viewports.mobile = {
      ...MOBILE,
      scrollWidth: overflow.scrollWidth,
      innerWidth: overflow.innerWidth
    }
    row.mobile = {
      contentLength: await contentLength(page),
      shell: await readShell(page),
      data: await surface.read(page),
      tabs: await readTabLabels(page)
    }
    row.shots.push(await shot(page, `render/${surface.slug}-${role}-mobile.png`))
  } finally {
    row.pageErrors = diagnostics.pageErrors.map(error => String(error))
    row.consoleErrors = diagnostics.consoleErrors
    row.consoleClassified = classifyConsole(diagnostics.consoleErrors, expectation)
    row.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
    row.requests = analyzeRequests(requests, role)
    row.fixtures = fixtureSummary(fixtureData)
    audit(`render/${surface.slug}-${role}.json`, row)
  }

  return row
}

for (const surface of SURFACES) {
  for (const plan of PLANS[surface.slug]) {
    test(`@U8 · capa 2 · ${surface.label} como ${plan.role}`, async ({ page, request }) => {
      test.setTimeout(180000)

      const session = await loginAs(request, plan.role)
      const fixtures = await ensureFixtures(request)

      if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

      const row = await renderRow({
        page,
        session,
        surface,
        role: plan.role,
        expectation: plan.expectation,
        fixtureData: fixtures
      })

      expect(row.shots, `captures of ${surface.route} for ${plan.role}`).toHaveLength(2)
      expect(row.pageErrors, `page errors of ${surface.route} for ${plan.role}`).toEqual([])
      expect(row.contentLength, `content of ${surface.route} for ${plan.role}`).toBeGreaterThan(0)
      expect(
        row.horizontalOverflow,
        `horizontal document overflow of ${surface.route} at 390 px`
      ).toBe(false)
      expect(row.headingCount, `h1 count of ${surface.route} for ${plan.role}`).toBe(1)

      if (plan.expectation === 'negative') {
        // The gate: the navigation does not offer the surface to this role.
        expect(
          row.navigation.expectedEntryVisible,
          `navigation entry ${surface.entry} for ${plan.role}`
        ).toBe(false)
      } else {
        expect(
          row.navigation.expectedEntryVisible,
          `navigation entry ${surface.entry} for ${plan.role}`
        ).toBe(true)
        expect(row.contentLength, `content of ${surface.route} for ${plan.role}`).toBeGreaterThan(
          THRESHOLD
        )
      }
    })
  }
}

// The session aggregate (`render.json`) is written by `report.js` from the
// per-row files: the report is a test, and a hook here would run after it, so an
// afterAll artefact would leave the report reading the previous run's file.
