// Plan #13 (issue #370, U7) — layer 2: the three clinical surfaces, by role.
//
// `/medical-records` (landing), `/medical-records/:id` (workspace), and the two
// modules that share the clinical desk — `/specialty-records` and
// `/ai-analysis` — are rendered for the roles the navigation offers them to,
// plus `tecnico_dental` as the negative gate #370 names. Desktop is measured at
// 1440×900 and then the page reloads at 390×844 (never resize-then-measure).
//
// `/specialty-records` paints its tabs and cards only after a patient is
// selected, so every non-negative row picks the session patient first: the row
// measures the module, not its "select a patient" placeholder. The negative row
// picks her too, because that selection is what makes the API answer 403 and
// the surface paint the denial state.
//
// This is measurement code: a defect is recorded in the JSON, never thrown. The
// suite asserts that each probe ran to completion, that both captures exist and
// that no page error was raised.

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
  AI_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  RECORDS_ROUTE,
  SPECIALTIES_ROUTE,
  THRESHOLD,
  audit,
  pickPatient,
  readAi,
  readRecordsLanding,
  readShell,
  readSpecialties,
  readWorkspace,
  settleAi,
  settleRecordsLanding,
  settleRecordsWorkspace,
  settleSpecialties,
  shot,
  stamp,
  sweepTabs,
  watchRequests
} from './helpers.js'

const SURFACES = Object.freeze([
  {
    slug: 'medical-records',
    route: RECORDS_ROUTE,
    label: 'Historias clínicas (índice)',
    href: () => RECORDS_ROUTE,
    settle: page => settleRecordsLanding(page),
    read: readRecordsLanding,
    sweep: false
  },
  {
    slug: 'medical-records-id',
    route: `${RECORDS_ROUTE}/:id`,
    label: 'Historia clínica (workspace)',
    href: fixtures => `${RECORDS_ROUTE}/${fixtures.record.id}`,
    settle: page => settleRecordsWorkspace(page),
    read: readWorkspace,
    sweep: true
  },
  {
    slug: 'specialty-records',
    route: SPECIALTIES_ROUTE,
    label: 'Registros de especialidades',
    href: () => SPECIALTIES_ROUTE,
    settle: page => settleSpecialties(page),
    read: readSpecialties,
    selectPatient: true,
    sweep: true
  },
  {
    slug: 'ai-analysis',
    route: AI_ROUTE,
    label: 'Análisis con IA',
    href: () => AI_ROUTE,
    settle: page => settleAi(page),
    read: readAi,
    sweep: false
  }
])

const ROLES = Object.freeze({
  'medical-records': ['administrador', 'odontologo', 'implantologo', 'asistente', 'tecnico_dental'],
  'medical-records-id': [
    'administrador',
    'odontologo',
    'implantologo',
    'asistente',
    'tecnico_dental'
  ],
  'specialty-records': ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'],
  'ai-analysis': ['administrador', 'odontologo', 'implantologo', 'tecnico_dental']
})

// Who holds the module's permission (`App\Authorization\Permissions`): the desk
// owner is the clinical role, the negative is the laboratory desktop #370 names.
const EXPECTATIONS = Object.freeze({
  administrador: 'admin',
  odontologo: 'owner',
  implantologo: 'owner',
  asistente: 'member',
  tecnico_dental: 'negative'
})

const NAV_ENTRIES = Object.freeze([RECORDS_ROUTE, SPECIALTIES_ROUTE, AI_ROUTE])

const analyzeRequest = (entries, role, fixtureData) => {
  const relevant = entries.filter(entry => entry.path !== '/api/broadcasting/auth')

  return {
    total: relevant.length,
    statuses: relevant.reduce((accumulator, entry) => {
      const key = `${entry.method} ${entry.path} → ${entry.status ?? 'pending'}`
      accumulator[key] = (accumulator[key] ?? 0) + 1
      return accumulator
    }, {}),
    role,
    patient: fixtureData?.patients?.clinical?.id ?? null
  }
}

const renderRow = async ({ page, session, surface, role, fixtureData }) => {
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const expectation = EXPECTATIONS[role]
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
      clinicalEntries: NAV_ENTRIES.filter(route =>
        row.shell.sidebar.some(link => link.href === route)
      )
    }

    if (surface.selectPatient && fixtureData?.patients?.clinical?.document) {
      const picked = await pickPatient(
        page,
        surface.slug === 'specialty-records' ? '[data-patient-card]' : 'main',
        fixtureData.patients.clinical.document
      )
      row.patientPick = picked
      await page
        .waitForFunction(
          () =>
            !document.querySelector('[data-specialty-records-loading]') &&
            Boolean(
              document.querySelector(
                '[data-specialty-records-panel] [data-specialty-record-card]'
              ) ||
              document.querySelector('[data-specialty-records-error]') ||
              document.querySelector('[data-specialty-records-empty]') ||
              document.querySelector('[data-specialty-records-no-specialties]')
            ),
          undefined,
          { timeout: LOAD_TIMEOUT, polling: 120 }
        )
        .catch(() => {})
      await surface.settle(page)
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
    }

    row.contentLength = await contentLength(page)
    row.rendered = row.contentLength > THRESHOLD
    row.tabs = await readTabLabels(page)
    row.data = await surface.read(page)

    if (surface.sweep) {
      const sweep = await sweepTabs(page, surface.read)
      row.tabSurfaces = sweep.surfaces
      row.sweptTabs = sweep.tabs.map(tab => tab.label)
      row.data = await surface.read(page)
    }

    if (expectation === 'negative') {
      row.denial = {
        requests: requests
          .filter(entry => entry.status === 403 || entry.status === 401)
          .map(entry => ({ method: entry.method, path: entry.path, status: entry.status })),
        errorVisible: Boolean(
          row.data?.error?.present ??
          row.data?.panel?.error?.present ??
          row.data?.fallback?.error?.present
        ),
        errorText:
          row.data?.error?.text ??
          row.data?.panel?.error?.text ??
          row.data?.fallback?.error?.text ??
          row.data?.empty?.text ??
          row.data?.fallback?.empty?.text ??
          row.data?.noSpecialties?.text ??
          row.data?.noPatient?.text ??
          null,
        retryVisible: Boolean(
          row.data?.errorRetry ??
          row.data?.panel?.retry ??
          row.data?.retry ??
          row.data?.fallback?.retry ??
          false
        ),
        createVisible: Boolean(
          row.data?.newRecordDisabled !== undefined || row.data?.rows !== undefined
        )
      }
    }

    row.shots.push(await shot(page, `render/${surface.slug}-${role}-desktop.png`))

    // Mobile: a fresh load, never a measured resize.
    await page.setViewportSize(MOBILE)
    await page.reload()
    await settleSurface(page, THRESHOLD, expectation === 'negative' ? 8000 : LOAD_TIMEOUT)
    await surface.settle(page)

    if (surface.selectPatient && fixtureData?.patients?.clinical?.document) {
      await pickPatient(
        page,
        surface.slug === 'specialty-records' ? '[data-patient-card]' : 'main',
        fixtureData.patients.clinical.document
      )
      await page
        .waitForFunction(
          () =>
            !document.querySelector('[data-specialty-records-loading]') &&
            Boolean(
              document.querySelector(
                '[data-specialty-records-panel] [data-specialty-record-card]'
              ) ||
              document.querySelector('[data-specialty-records-error]') ||
              document.querySelector('[data-specialty-records-empty]') ||
              document.querySelector('[data-specialty-records-no-specialties]')
            ),
          undefined,
          { timeout: LOAD_TIMEOUT, polling: 120 }
        )
        .catch(() => {})
      await surface.settle(page)
    }

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
    row.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
    row.requests = analyzeRequest(requests, role, fixtureData)
    row.fixtures = fixtureSummary(fixtureData)
    audit(`render/${surface.slug}-${role}.json`, row)
  }

  return row
}

for (const surface of SURFACES) {
  for (const role of ROLES[surface.slug]) {
    test(`@U7 · capa 2 · ${surface.label} como ${role}`, async ({ page, request }) => {
      test.setTimeout(240000)

      const session = await loginAs(request, role)
      const fixtures = await ensureFixtures(request)

      if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

      const row = await renderRow({ page, session, surface, role, fixtureData: fixtures })

      expect(row.shots, `captures of ${surface.route} for ${role}`).toHaveLength(2)
      expect(row.pageErrors, `page errors of ${surface.route} for ${role}`).toEqual([])

      if (row.expectation !== 'negative') {
        expect(row.contentLength, `content of ${surface.route} for ${role}`).toBeGreaterThan(
          THRESHOLD
        )
        // The clinical entries must be in the sidebar for the roles that hold
        // the module; the negative row asserts the opposite below.
        const expectedEntry =
          surface.slug === 'ai-analysis'
            ? AI_ROUTE
            : surface.slug === 'specialty-records'
              ? SPECIALTIES_ROUTE
              : RECORDS_ROUTE
        expect(
          row.navigation.clinicalEntries,
          `navigation entry ${expectedEntry} for ${role}`
        ).toContain(expectedEntry)
      } else {
        // #370 criterion: the laboratory desktop does not see the clinical
        // navigation. Its absence is measured, not inferred.
        expect(row.navigation.clinicalEntries, `clinical navigation for ${role}`).toEqual([])
      }
    })
  }
}

// The tecnico row for `/medical-records` is the one that proves the absence of
// all three entries: the other two surfaces repeat the same shell.
test('@U7 · capa 2 · la navegación clínica del técnico dental', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'tecnico_dental')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const evidence = {
    role: 'tecnico_dental',
    ...stamp(),
    href: RECORDS_ROUTE,
    shots: [],
    sidebar: null,
    clinicalEntries: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(RECORDS_ROUTE)
    await page
      .locator('main')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, THRESHOLD, 8000)
    await settleRecordsLanding(page)

    const shell = await readShell(page)
    evidence.sidebar = shell.sidebar.map(link => link.href)
    evidence.clinicalEntries = NAV_ENTRIES.filter(route => evidence.sidebar.includes(route))
    evidence.denialState = await readRecordsLanding(page).then(data => data.error)
    evidence.shots.push(await shot(page, 'render/navigation-tecnico-desktop.png'))

    await page.setViewportSize(MOBILE)
    await page.reload()
    await settleSurface(page, THRESHOLD, 8000)
    evidence.shots.push(await shot(page, 'render/navigation-tecnico-mobile.png'))
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'negative')
    audit('render/navigation-tecnico.json', evidence)
  }

  expect(evidence.shots, 'captures of the tecnico navigation').toHaveLength(2)
  expect(evidence.clinicalEntries, 'clinical entries in the tecnico sidebar').toEqual([])
})

// The session aggregate (`render.json`) is written by `report.js` from the
// per-row files: the report is a test, and a hook here would run after it, so an
// afterAll artefact would leave the report reading the previous run's file.
