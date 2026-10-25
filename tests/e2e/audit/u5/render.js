// Plan #13 (issue #368, U5) — layer 2: the two money surfaces, by role.
//
// `/cash-register` and `/cash-register/ready-to-bill` render for the three
// roles that hold the desk (administrador, finanzas, recepcionista). Every row
// records the shell, the hero, the KPIs, the tabs, the session the API answers,
// the loading affordance of the first paint, both captures and the classified
// diagnostics. Desktop is measured at 1440x900 and then the page reloads at
// 390x844 (never resize-then-measure).
//
// This is measurement code: a defect is recorded in the JSON, never thrown. The
// suite only asserts that each probe ran to completion, that both captures
// exist and that no page error was raised.

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
  CASH_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  RTB_ROUTE,
  THRESHOLD,
  audit,
  delayRoute,
  forceStatus,
  hero,
  kpi,
  loadingAffordance,
  markExpected,
  readRtbTable,
  readTransactionRows,
  sessionSnapshot,
  settleCash,
  shot,
  stamp,
  waitForCall
} from './helpers.js'

const ROWS = (globalThis.__AUDIT_U5_RENDER__ ??= { rows: [], loading: null, errors: [] })

const ROLES = Object.freeze(['administrador', 'finanzas', 'recepcionista'])
const EXPECTATIONS = Object.freeze({
  administrador: 'admin',
  finanzas: 'owner',
  recepcionista: 'owner'
})

const SURFACES = Object.freeze([
  {
    slug: 'cash-register',
    route: CASH_ROUTE,
    label: 'caja',
    primary: /\/api\/cash-register\/current$/,
    settle: page => settleCash(page)
  },
  {
    slug: 'cash-register-ready-to-bill',
    route: RTB_ROUTE,
    label: 'listos para facturar',
    primary: /\/api\/appointments\/ready-to-bill/,
    settle: page => settleCash(page, '[data-rtb-hero]')
  }
])

const ledger = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim()
    const cells = selector => document.querySelectorAll(selector).length

    return {
      tables: {
        transactions: Boolean(document.querySelector('[data-transaction-table]')),
        movements: Boolean(document.querySelector('[data-movement-table]')),
        sessions: Boolean(document.querySelector('[data-session-table]')),
        pending: Boolean(document.querySelector('[data-pending-payments-table]')),
        readyToBill: Boolean(document.querySelector('[data-rtb-table]'))
      },
      loadingCells: cells('.data-table-cell-loading'),
      emptyCells: cells('.data-table-cell-empty'),
      emptyText: read(document.querySelector('.data-table-cell-empty')),
      filters: {
        transactions: Boolean(document.querySelector('[data-transaction-filters]')),
        pending: Boolean(document.querySelector('[data-pending-payments-list]')),
        readyToBill: Boolean(document.querySelector('[data-rtb-filters]'))
      },
      reportTab: Boolean(document.querySelector('[data-report-start]')),
      toast: Array.from(document.querySelectorAll('[aria-live]'))
        .map(read)
        .filter(Boolean)
        .join(' | ')
    }
  })

const renderRow = async ({ page, request, session, surface, role, expectation }) => {
  const diagnostics = collectDiagnostics(page)
  const row = {
    route: surface.route,
    slug: surface.slug,
    role,
    expectation,
    href: surface.route,
    ...stamp(),
    url: null,
    httpStatus: null,
    rendered: false,
    contentLength: 0,
    notFound: false,
    horizontalOverflow: null,
    viewports: { desktop: { ...DESKTOP } },
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] },
    h1: null,
    tabs: [],
    hero: null,
    kpis: null,
    firstPaint: null,
    loadingAffordance: null,
    ledger: null,
    session: null,
    mobile: null,
    fixtures: null
  }

  let delay = null

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    delay = await delayRoute(page, surface.primary, 900)

    const primary = waitForCall(
      page,
      'GET',
      surface.slug === 'cash-register'
        ? '/api/cash-register/current'
        : '/api/appointments/ready-to-bill',
      () => true,
      LOAD_TIMEOUT
    )

    const response = await page.goto(surface.route)
    row.httpStatus = response?.status() ?? null
    row.url = page.url()

    await page
      .locator(surface.slug === 'cash-register' ? '[data-caja-hero]' : '[data-rtb-hero]')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})

    row.firstPaint = {
      hero: await hero(page),
      kpis: await kpi(page),
      loading: await loadingAffordance(page),
      tabs: await readTabLabels(page)
    }

    await primary
    await delay.stop()
    delay = null
    await surface.settle(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    row.contentLength = await contentLength(page)
    row.rendered = row.contentLength > THRESHOLD
    row.h1 = await page.evaluate(() => ({
      count: document.querySelectorAll('h1').length,
      texts: Array.from(document.querySelectorAll('h1')).map(node => (node.innerText ?? '').trim())
    }))
    row.notFound = (row.h1.texts ?? []).some(text => /página no encontrada/i.test(text))
    row.tabs = await readTabLabels(page)
    row.hero = await hero(page)
    row.kpis = await kpi(page)
    row.ledger = await ledger(page)
    row.loadingAffordance = await loadingAffordance(page)
    row.session = await sessionSnapshot(request, session)
    row.shots.push(await shot(page, `render/${surface.slug}-${role}-desktop.png`))

    // Mobile: a fresh load, never a measured resize.
    await page.setViewportSize(MOBILE)
    const mobilePrimary = waitForCall(
      page,
      'GET',
      surface.slug === 'cash-register'
        ? '/api/cash-register/current'
        : '/api/appointments/ready-to-bill',
      () => true,
      LOAD_TIMEOUT
    )
    await page.reload()
    await mobilePrimary
    await surface.settle(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const overflow = await hasHorizontalOverflow(page)
    row.horizontalOverflow = overflow.overflow
    row.viewports.mobile = {
      ...MOBILE,
      scrollWidth: overflow.scrollWidth,
      innerWidth: overflow.innerWidth
    }
    row.mobile = {
      hero: await hero(page),
      kpis: await kpi(page),
      tabs: await readTabLabels(page),
      rows:
        surface.slug === 'cash-register'
          ? await readTransactionRows(page)
          : await readRtbTable(page)
    }
    row.shots.push(await shot(page, `render/${surface.slug}-${role}-mobile.png`))
  } finally {
    await delay?.stop().catch(() => {})
    row.pageErrors = diagnostics.pageErrors
    row.consoleErrors = diagnostics.consoleErrors
    row.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
    audit(`render/${surface.slug}-${role}.json`, row)
  }

  return row
}

for (const surface of SURFACES) {
  for (const role of ROLES) {
    test(`@U5 · renders ${surface.route} as ${role}`, async ({ page, request }) => {
      test.setTimeout(180000)

      const session = await loginAs(request, role)
      const fixtures = await ensureFixtures(request)
      const row = await renderRow({
        page,
        request,
        session,
        surface,
        role,
        expectation: EXPECTATIONS[role]
      })

      // The fixture summary is appended after the row is written so a fixture
      // failure never hides a measurement; the row file above already carries
      // the measurement, this pass only annotates it.
      row.fixtures = fixtureSummary(fixtures)
      audit(`render/${surface.slug}-${role}.json`, row)
      ROWS.rows.push(row)

      expect(row.shots, `captures of ${surface.route} for ${role}`).toHaveLength(2)
      expect(row.pageErrors, `page errors of ${surface.route} for ${role}`).toEqual([])
      expect(row.contentLength, `content of ${surface.route} for ${role}`).toBeGreaterThan(
        THRESHOLD
      )
    })
  }
}

// --- first paint: the S1 hypothesis ----------------------------------------

test('@U5 · primer pintado de caja', async ({ page, request }) => {
  test.setTimeout(150000)

  const session = await loginAs(request, 'administrador')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: CASH_ROUTE,
    slug: 'cash-register',
    role: 'administrador',
    ...stamp(),
    hypothesis:
      'S1: CashRegisterPage paints no Skeleton and no LoadingSpinner although its first load ' +
      'carries the session and the balances',
    delayMs: 900,
    firstPaint: null,
    settled: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const delay = await delayRoute(page, /\/api\/cash-register\/current$/, 900)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const primary = waitForCall(page, 'GET', '/api/cash-register/current', () => true, LOAD_TIMEOUT)

    const response = await page.goto(CASH_ROUTE)
    evidence.httpStatus = response?.status() ?? null

    await page
      .locator('[data-caja-hero]')
      .waitFor({ state: 'visible', timeout: 8000 })
      .catch(() => {})

    const beforeResponse = await page
      .evaluate(() => {
        const read = node => (node?.innerText ?? '').trim()
        const isVisible = node => {
          const box = node?.getBoundingClientRect()
          return Boolean(box && box.width > 0 && box.height > 0)
        }

        return {
          heroState: read(document.querySelector('[data-hero-state]')),
          heroActions: Array.from(document.querySelectorAll('[data-hero-action]'))
            .filter(isVisible)
            .map(node => node.getAttribute('data-hero-action')),
          kpis: Object.fromEntries(
            Array.from(document.querySelectorAll('[data-kpi-value]')).map(node => [
              node.getAttribute('data-kpi-value'),
              read(node)
            ])
          ),
          skeletons: document.querySelectorAll('[class*="skeleton"], [data-skeleton]').length,
          spinners: Array.from(
            document.querySelectorAll('.loading-spinner-container, .spinner-ring')
          ).filter(node => !node.closest('.data-table-container, table')).length,
          tableSpinners: document.querySelectorAll('.data-table-cell-loading').length,
          loadingCells: document.querySelectorAll('.data-table-cell-loading').length,
          blank: (document.querySelector('main')?.innerText.trim().length ?? 0) < 40
        }
      })
      .catch(() => null)

    evidence.firstPaint = beforeResponse
    evidence.shots.push(await shot(page, 'loading/cash-register-loading-desktop.png'))

    await primary
    await delay.stop()
    await settleCash(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const settled = {
      hero: await hero(page),
      kpis: await kpi(page),
      loading: await loadingAffordance(page),
      session: await sessionSnapshot(request, session)
    }
    evidence.settled = settled
    evidence.shots.push(await shot(page, 'loading/cash-register-settled-desktop.png'))

    evidence.verdict =
      (beforeResponse?.skeletons ?? 0) > 0 || (beforeResponse?.spinners ?? 0) > 0 ? 'OK' : 'DEFECTO'
    evidence.finding =
      evidence.verdict === 'DEFECTO'
        ? 'the first paint shows no loading affordance: the hero reads "' +
          `${beforeResponse?.heroState ?? 'n/d'}" with the actions ` +
          `${JSON.stringify(beforeResponse?.heroActions ?? [])} while the balances are still N/D`
        : null
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    await delay.stop().catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('loading.json', evidence)
    ROWS.loading = {
      verdict: evidence.verdict,
      firstPaintHeroState: evidence.firstPaint?.heroState ?? null,
      firstPaintActions: evidence.firstPaint?.heroActions ?? [],
      firstPaintSkeletons: evidence.firstPaint?.skeletons ?? null,
      settledHeroState: evidence.settled?.hero?.state ?? null,
      shots: evidence.shots
    }
  }

  expect(evidence.firstPaint, 'first paint observed').toBeTruthy()
  expect(evidence.shots, 'captures of the loading probe').toHaveLength(2)
})

// --- error + retry ----------------------------------------------------------

const writeErrorRecovery = () =>
  audit('error-recovery.json', {
    ...stamp(),
    stage: 'layer-2',
    scenario: 'forced 500 on the primary load, then the surface retry control',
    verdict: ROWS.errors.every(row => row.verdict === 'OK') ? 'OK' : 'DEFECTO',
    rows: ROWS.errors.map(row => ({
      route: row.route,
      role: row.role,
      forcedStatus: row.forcedStatus,
      verdict: row.verdict,
      steps: row.steps,
      shots: row.shots,
      pageErrors: row.pageErrors,
      failedRequests: row.failedRequests
    }))
  })

const errorProbe = async ({
  page,
  request,
  role,
  route,
  primary,
  errorSelector,
  retrySelector,
  wipe
}) => {
  const session = await loginAs(request, role)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route,
    role,
    ...stamp(),
    forcedStatus: 500,
    wipeProbe: wipe === true,
    shots: [],
    steps: {},
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const forced = await forceStatus(page, primary, { status: 500 })
  // The cash page fetches the sessions list right after the drawer and both
  // writes share one `error` ref, so the panel can be measured only while that
  // second load is still in flight. The delay is the instrument's window, not a
  // product promise: the wipe itself is recorded below.
  const delayed = wipe ? await delayRoute(page, /\/api\/cash-register\/sessions(\?|$)/, 4000) : null

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const response = await page.goto(route)
    evidence.httpStatus = response?.status() ?? null
    await page
      .locator(errorSelector)
      .waitFor({ state: 'visible', timeout: 8000 })
      .catch(() => {})
    await page.waitForTimeout(400)

    evidence.steps.forced = {
      errorVisible: await page
        .locator(errorSelector)
        .isVisible()
        .catch(() => false),
      errorText: await page
        .locator(errorSelector)
        .innerText({ timeout: 4000 })
        .catch(() => ''),
      retryVisible: await page
        .locator(retrySelector)
        .isVisible()
        .catch(() => false),
      hero: await hero(page)
    }
    evidence.shots.push(
      await shot(
        page,
        `error/${evidence.steps.forced.errorVisible ? 'error' : 'no-error'}-${role}-${
          route.includes('ready') ? 'rtb' : 'cash'
        }.png`
      )
    )

    if (evidence.steps.forced.retryVisible) {
      // Recovery is measured with the failure removed, while the second load
      // still holds the window open.
      await forced.stop()

      const recovery = waitForCall(
        page,
        'GET',
        route.includes('ready-to-bill')
          ? '/api/appointments/ready-to-bill'
          : '/api/cash-register/current',
        () => true,
        LOAD_TIMEOUT
      )
      await page.locator(retrySelector).click({ timeout: 8000 })
      const settled = await recovery
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      evidence.steps.recovered = {
        status: settled?.status() ?? null,
        errorVisible: await page
          .locator(errorSelector)
          .isVisible()
          .catch(() => false),
        hero: await hero(page),
        kpi: await kpi(page)
      }
      evidence.shots.push(
        await shot(page, `error/recovered-${role}-${route.includes('ready') ? 'rtb' : 'cash'}.png`)
      )
    } else {
      evidence.steps.recovered = { ok: false, reason: 'the retry control was not rendered' }
    }

    if (wipe) {
      await delayed?.stop()
      await page.waitForTimeout(1200)
      evidence.steps.afterSecondLoad = {
        errorVisible: await page
          .locator(errorSelector)
          .isVisible()
          .catch(() => false),
        note:
          'getSessions() resets the shared error ref synchronously, so the panel is never painted ' +
          'in the natural flow; the delayed second load does not change it'
      }

      // A clean reload with only the forced failure: no window, no panel.
      await forced.stop()
      const second = await forceStatus(page, primary, { status: 500 })
      const reload = waitForCall(
        page,
        'GET',
        route.includes('ready-to-bill')
          ? '/api/appointments/ready-to-bill'
          : '/api/cash-register/current',
        () => true,
        LOAD_TIMEOUT
      )
      await page.reload({ waitUntil: 'load' })
      await reload
      await page.waitForTimeout(2500)
      evidence.steps.naturalFlow = {
        errorVisible: await page
          .locator(errorSelector)
          .isVisible()
          .catch(() => false),
        retryVisible: await page
          .locator(retrySelector)
          .isVisible()
          .catch(() => false),
        sessionsLoaded: true
      }
      evidence.shots.push(await shot(page, `error/natural-${role}-cash.png`))
      await second.stop()
    }

    const forcedOk =
      evidence.steps.forced.errorVisible === true && evidence.steps.forced.retryVisible === true
    const recoveredOk = evidence.steps.recovered.errorVisible === false
    const naturalOk = wipe ? evidence.steps.naturalFlow.errorVisible === true : true

    evidence.verdict = forcedOk && recoveredOk && naturalOk ? 'OK' : 'DEFECTO'
    evidence.detail =
      wipe && naturalOk === false
        ? 'el panel de error de la caja no sobrevive a la carga de sesiones que la misma página dispara después: ' +
          'en el flujo real el usuario nunca ve el aviso ni el reintento (solo aparecen si la segunda carga se retrasa)'
        : null
  } catch (thrown) {
    evidence.probeError = String(thrown?.message ?? thrown)
    evidence.steps.probeError = { message: evidence.probeError }
  } finally {
    await delayed?.stop().catch(() => {})
    await forced.stop().catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500,
      'probe: the primary load was answered with a forced 500'
    )
    audit(`error/${route.includes('ready') ? 'rtb' : 'cash'}-${role}.json`, {
      ...stamp(),
      ...evidence
    })
  }

  return { evidence, diagnostics }
}

test('@U5 · caja con error y reintento', async ({ page, request }) => {
  test.setTimeout(150000)

  const { evidence } = await errorProbe({
    page,
    request,
    role: 'administrador',
    route: CASH_ROUTE,
    primary: /\/api\/cash-register\/current$/,
    errorSelector: '[data-caja-error]',
    retrySelector: '[data-caja-retry]',
    wipe: true
  })

  ROWS.errors.push(evidence)
  writeErrorRecovery()

  expect(evidence.steps.forced, 'the forced failure was painted').toBeTruthy()
})

test('@U5 · listos para facturar con error y reintento', async ({ page, request }) => {
  test.setTimeout(150000)

  const { evidence } = await errorProbe({
    page,
    request,
    role: 'recepcionista',
    route: RTB_ROUTE,
    primary: /\/api\/appointments\/ready-to-bill/,
    errorSelector: '[data-rtb-error]',
    retrySelector: '[data-rtb-retry]'
  })

  ROWS.errors.push(evidence)
  writeErrorRecovery()

  expect(evidence.steps.forced, 'the forced failure was painted').toBeTruthy()
})

// --- aggregate --------------------------------------------------------------

test.afterAll(async () => {
  const rows = ROWS.rows.map(row => ({
    route: row.route,
    slug: row.slug,
    role: row.role,
    url: row.url,
    httpStatus: row.httpStatus,
    rendered: row.rendered,
    contentLength: row.contentLength,
    h1: row.h1?.count ?? null,
    heroTitle: row.hero?.title ?? null,
    heroState: row.hero?.state ?? null,
    kpis: row.kpis,
    tabs: row.tabs,
    sessionId: row.session?.id ?? null,
    sessionOwnedByActor: row.session?.ownedByActor ?? null,
    loadingAffordance: row.loadingAffordance,
    emptyCells: row.ledger?.emptyCells ?? null,
    horizontalOverflow: row.horizontalOverflow,
    shots: row.shots,
    pageErrors: row.pageErrors?.length ?? null,
    unexpectedRequests: row.failedRequests?.unexpected?.length ?? null
  }))

  audit('render.json', {
    ...stamp(),
    stage: 'layer-2',
    routes: SURFACES.map(surface => surface.route),
    roles: [...ROLES],
    coverage: Object.fromEntries(
      SURFACES.map(surface => [
        surface.slug,
        Object.fromEntries(
          ROLES.map(role => [
            role,
            rows.some(row => row.slug === surface.slug && row.role === role)
          ])
        )
      ])
    ),
    loading: ROWS.loading,
    errors: ROWS.errors.map(row => ({
      route: row.route,
      role: row.role,
      verdict: row.verdict,
      forced: row.steps?.forced
        ? {
            errorVisible: row.steps.forced.errorVisible,
            retryVisible: row.steps.forced.retryVisible
          }
        : null,
      recovered: row.steps?.recovered
        ? { status: row.steps.recovered.status, errorVisible: row.steps.recovered.errorVisible }
        : null,
      naturalFlow: row.steps?.naturalFlow ?? null
    })),
    totals: {
      rows: rows.length,
      rendered: rows.filter(row => row.rendered).length,
      overflow: rows.filter(row => row.horizontalOverflow === true).length,
      pageErrors: rows.reduce((sum, row) => sum + (row.pageErrors ?? 0), 0),
      unexpectedRequests: rows.reduce((sum, row) => sum + (row.unexpectedRequests ?? 0), 0),
      sessionsNotOwnedByActor: rows.filter(row => row.sessionOwnedByActor === false).length
    },
    rows
  })
})
