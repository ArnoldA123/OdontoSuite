// Plan #13 (issue #369, U6) — layer 2: the budget and plan surfaces, by role.
//
// `/quotations` renders for four roles (administrador, finanzas, odontologo and
// recepcionista — the last one can read and cannot write) and
// `/treatment-plans` for two of them plus two negative gates (finanzas and
// recepcionista), because the plan module's permission is clinical. Every row
// records the shell, the KPI strip, the rows or cards, the actions each row
// exposes, the status the write door answers for that role, both captures and
// the classified diagnostics. Desktop is measured at 1440x900 and then the page
// reloads at 390x844 (never resize-then-measure).
//
// This is measurement code: a defect is recorded in the JSON, never thrown. The
// suite asserts that each probe ran to completion, that both captures exist, that
// the surface painted its content for the roles that hold the desk, and that no
// page error was raised.

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
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  PLANS_ROUTE,
  QUOTATIONS_ROUTE,
  THRESHOLD,
  audit,
  call,
  delayRoute,
  forceStatus,
  markExpected,
  readPlanCards,
  readQuotationRows,
  settlePlans,
  settleQuotations,
  shot,
  stamp,
  waitForCall
} from './helpers.js'

const ROWS = (globalThis.__AUDIT_U6_RENDER__ ??= { rows: [], loading: null, errors: [] })

const ROLES = Object.freeze(['administrador', 'finanzas', 'recepcionista', 'odontologo'])

// The desk owner is the role the navigation entry exists for; the negative roles
// are the ones the plan module's permission excludes, and the render is their
// gate measurement (S12: the 403 is measured inside the render row).
const EXPECTATIONS = Object.freeze({
  quotations: Object.freeze({
    administrador: 'admin',
    finanzas: 'owner',
    odontologo: 'owner',
    recepcionista: 'owner'
  }),
  'treatment-plans': Object.freeze({
    administrador: 'admin',
    odontologo: 'owner',
    finanzas: 'negative',
    recepcionista: 'negative'
  })
})

// Who holds the write door of each surface, by product permission
// (`QUOTATION_WRITE_ROLES` / `manage.treatment_plan`). The row still measures the
// API answer; this map only words what the answer means.
const WRITE_ROLES = Object.freeze({
  quotations: ['administrador', 'finanzas', 'odontologo'],
  'treatment-plans': ['administrador', 'odontologo']
})

const SURFACES = Object.freeze([
  {
    slug: 'quotations',
    route: QUOTATIONS_ROUTE,
    href: fixtures => `${QUOTATIONS_ROUTE}?patient_id=${fixtures.patient.id}`,
    primary: /\/api\/quotations(\?|$)/,
    primaryPath: '/api/quotations',
    secondaryPath: '/api/quotations/summary',
    settle: page => settleQuotations(page),
    errorSelector: '[data-quotations-error]',
    retrySelector: '[data-quotations-retry]',
    loadingSelector: '[data-quotations-loading]',
    gate: { method: 'POST', path: '/api/quotations', body: {} },
    list: readQuotationRows
  },
  {
    slug: 'treatment-plans',
    route: PLANS_ROUTE,
    href: () => PLANS_ROUTE,
    primary: /\/api\/treatment-plans(\?|$)/,
    primaryPath: '/api/treatment-plans',
    secondaryPath: '/api/treatment-plans/summary',
    settle: page => settlePlans(page),
    errorSelector: '[data-state="error"]',
    retrySelector: '[data-retry]',
    loadingSelector: '[aria-busy="true"]',
    gate: { method: 'POST', path: '/api/treatment-plans', body: {} },
    list: readPlanCards
  }
])

const shellOf = (page, loadingSelector) =>
  page.evaluate(selector => {
    const read = value => (value?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }

    return {
      hero: {
        present: Boolean(document.querySelector('[data-hero-title]')),
        eyebrow: read(document.querySelector('[data-hero-eyebrow]')),
        title: read(document.querySelector('[data-hero-title]')),
        caption: read(document.querySelector('[data-hero-caption]'))
      },
      h1: {
        count: document.querySelectorAll('h1').length,
        texts: Array.from(document.querySelectorAll('h1')).map(node => read(node))
      },
      kpis: Object.fromEntries(
        Array.from(document.querySelectorAll('[data-kpi-value]')).map(node => [
          node.getAttribute('data-kpi-value'),
          read(node)
        ])
      ),
      filters: {
        search: Boolean(document.querySelector('[data-quotations-search] input')),
        status: Boolean(document.querySelector('[data-status-filter]')),
        patient: Boolean(document.querySelector('[data-filter-patient] input')),
        viewToggle: Boolean(document.querySelector('[data-view-toggle]'))
      },
      heroActions: Array.from(
        document.querySelectorAll('[data-hero-action], [data-create-plan], [data-refresh]')
      ).map(node => ({
        action:
          node.getAttribute('data-hero-action') ??
          (node.hasAttribute('data-create-plan') ? 'create-plan' : 'refresh'),
        label: read(node),
        visible: visible(node),
        disabled: node.disabled === true
      })),
      loading: Boolean(document.querySelector(selector)),
      skeletons: document.querySelectorAll('.skeleton-container').length,
      error: Boolean(document.querySelector('[data-quotations-error], [data-state="error"]')),
      empty: Boolean(document.querySelector('[data-quotations-empty], [data-state="empty"]')),
      toast: Array.from(document.querySelectorAll('[aria-live]'))
        .map(read)
        .filter(Boolean)
        .join(' | ')
    }
  }, loadingSelector)

/** The write door, measured for this role: `422` passes the gate and fails the
 * body; `403` is the permission refusing before validation. */
const writeGate = async (request, session, surface, role) => {
  const response = await call(
    request,
    session,
    surface.gate.method,
    surface.gate.path,
    surface.gate.body
  )
  const expected = WRITE_ROLES[surface.slug].includes(role)
    ? 'passes-validation'
    : 'permission-refused'

  return {
    method: surface.gate.method,
    path: surface.gate.path,
    body: surface.gate.body,
    role,
    status: response.status,
    message: response.message,
    errors: response.errors ? Object.keys(response.errors) : null,
    writeRole: WRITE_ROLES[surface.slug].includes(role),
    expected,
    ok:
      expected === 'permission-refused'
        ? response.status === 403
        : response.status === 422 || response.status === 201
  }
}

const actionsOf = list => {
  const seen = new Map()

  for (const row of list?.rows ?? list?.cards ?? []) {
    for (const action of row.actions ?? []) {
      seen.set(action.action, { action: action.action, visible: action.visible !== false })
    }
  }

  return [...seen.values()].map(entry => entry.action)
}

const renderRow = async ({ page, request, session, surface, role, fixtureData }) => {
  const diagnostics = collectDiagnostics(page)
  const expectation = EXPECTATIONS[surface.slug][role]
  const row = {
    route: surface.route,
    slug: surface.slug,
    role,
    expectation,
    href: surface.href(fixtureData),
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
    shell: null,
    list: null,
    actions: [],
    gate: null,
    desktopRows: 0,
    mobile: null,
    fixtures: null
  }

  try {
    row.gate = await writeGate(request, session, surface, role)

    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const response = await page.goto(row.href)
    row.httpStatus = response?.status() ?? null
    row.url = page.url()

    await page
      .locator('main')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})

    await settleSurface(page, THRESHOLD, expectation === 'negative' ? 6000 : LOAD_TIMEOUT)
    await surface.settle(page)

    row.contentLength = await contentLength(page)
    row.rendered = row.contentLength > THRESHOLD
    row.shell = await shellOf(page, surface.loadingSelector)
    row.notFound = (row.shell.h1?.texts ?? []).some(text => /página no encontrada/i.test(text))
    row.tabs = await readTabLabels(page)
    row.list = await surface.list(page)
    row.actions = actionsOf(row.list)
    row.desktopRows = (row.list?.rows ?? row.list?.cards ?? []).length
    row.roleGates = {
      heroCreateVisible: (row.shell.heroActions ?? []).some(
        entry => entry.action === 'create' || entry.action === 'create-plan'
      ),
      rowWriteActions: row.actions.filter(
        action => !['view', 'pdf', 'status-toggle'].includes(action)
      ),
      apiWriteDoor: row.gate.status,
      // The dangerous direction is an affordance the API refuses: a control that
      // opens a form whose save answers 403. The opposite (a write the API allows
      // with no row in a writable state) is state, not a defect.
      verdict:
        row.gate.status === 403 &&
        (row.actions.some(action => !['view', 'pdf', 'status-toggle'].includes(action)) ||
          (row.shell.heroActions ?? []).some(
            entry => entry.action === 'create' || entry.action === 'create-plan'
          ))
          ? 'the UI offers a write the API refuses with 403'
          : null
    }
    row.shots.push(await shot(page, `render/${surface.slug}-${role}-desktop.png`))

    // Mobile: a fresh load, never a measured resize.
    await page.setViewportSize(MOBILE)
    await page.reload()
    await settleSurface(page, THRESHOLD, expectation === 'negative' ? 6000 : LOAD_TIMEOUT)
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
      shell: await shellOf(page, surface.loadingSelector),
      list: await surface.list(page),
      actions: [],
      shots: []
    }
    row.mobile.actions = actionsOf(row.mobile.list)
    row.shots.push(await shot(page, `render/${surface.slug}-${role}-mobile.png`))
  } finally {
    row.pageErrors = diagnostics.pageErrors
    row.consoleErrors = diagnostics.consoleErrors
    row.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)

    row.fixtures = fixtureSummary(fixtureData)
    audit(`render/${surface.slug}-${role}.json`, row)
  }

  return row
}

for (const surface of SURFACES) {
  for (const role of ROLES) {
    test(`@U6 · renders ${surface.route} as ${role}`, async ({ page, request }) => {
      test.setTimeout(180000)

      const session = await loginAs(request, role)
      const fixtures = await ensureFixtures(request)

      if (!fixtures.ok) {
        test.skip(true, `fixtures refused: ${fixtures.reason}`)
      }

      const row = await renderRow({ page, request, session, surface, role, fixtureData: fixtures })
      ROWS.rows.push(row)

      expect(row.shots, `captures of ${surface.route} for ${role}`).toHaveLength(2)
      expect(row.pageErrors, `page errors of ${surface.route} for ${role}`).toEqual([])

      if (row.expectation !== 'negative') {
        expect(row.contentLength, `content of ${surface.route} for ${role}`).toBeGreaterThan(
          THRESHOLD
        )
      }
    })
  }
}

// --- first paint: is the surface honest while the payload is in flight? ------

const firstPaintProbe = async ({ page, request, surface, role }) => {
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: surface.route,
    slug: surface.slug,
    role,
    ...stamp(),
    scenario: 'the primary load is delayed 1200 ms, so the loading state is observable',
    delayMs: 1200,
    firstPaint: null,
    settled: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const delay = await delayRoute(page, surface.primary, evidence.delayMs)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const primary = waitForCall(page, 'GET', surface.primaryPath, () => true, LOAD_TIMEOUT)
    const response = await page.goto(surface.href(fixtures))
    evidence.httpStatus = response?.status() ?? null

    // The quotation surface paints the hero first and its skeleton under the KPI
    // strip; the plan surface replaces the hero with skeleton blocks while the
    // list is unknown. Both are measured without waiting for the settled hero.
    await page
      .locator('main')
      .waitFor({ state: 'visible', timeout: 10000 })
      .catch(() => {})
    await page
      .waitForFunction(
        () =>
          Boolean(
            document.querySelector('[data-hero-title]') ||
            document.querySelector('[aria-busy="true"]') ||
            document.querySelector('.skeleton-container')
          ),
        undefined,
        { timeout: 9000, polling: 50 }
      )
      .catch(() => {})
    await page.waitForTimeout(150)

    evidence.firstPaint = await shellOf(page, surface.loadingSelector)
    evidence.shots.push(await shot(page, `loading/${surface.slug}-loading-desktop.png`))

    await primary
    await delay.stop()
    await surface.settle(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    evidence.settled = {
      contentLength: await contentLength(page),
      shell: await shellOf(page, surface.loadingSelector),
      list: await surface.list(page)
    }
    evidence.shots.push(await shot(page, `loading/${surface.slug}-settled-desktop.png`))

    const affordance =
      evidence.firstPaint.loading === true ||
      evidence.firstPaint.skeletons > 0 ||
      Object.values(evidence.firstPaint.kpis ?? {}).every(value => /^(—|N\/D|)$/.test(value))

    evidence.verdict = affordance ? 'OK' : 'DEFECTO'
    evidence.finding = affordance
      ? null
      : `the first paint of ${surface.route} shows no loading affordance: the KPI strip reads ` +
        `${JSON.stringify(evidence.firstPaint.kpis)} while the payload is still in flight`
  } finally {
    await delay.stop().catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(
      diagnostics.failedRequests,
      surface.slug === 'quotations' ? 'owner' : 'admin'
    )
    audit(`loading/${surface.slug}.json`, evidence)

    ROWS.loading ??= {}
    ROWS.loading[surface.slug] = {
      verdict: evidence.verdict,
      firstPaintKpis: evidence.firstPaint?.kpis ?? null,
      firstPaintLoading: evidence.firstPaint?.loading ?? null,
      firstPaintSkeletons: evidence.firstPaint?.skeletons ?? null,
      settledLength: evidence.settled?.contentLength ?? null,
      shots: evidence.shots
    }
  }

  return evidence
}

for (const surface of SURFACES) {
  test(`@U6 · primer pintado de ${surface.slug}`, async ({ page, request }) => {
    test.setTimeout(180000)

    const fixtures = await ensureFixtures(request)
    if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

    const evidence = await firstPaintProbe({
      page,
      request,
      surface,
      role: surface.slug === 'quotations' ? 'administrador' : 'odontologo'
    })

    expect(evidence.firstPaint, 'first paint observed').toBeTruthy()
    expect(evidence.shots, 'captures of the loading probe').toHaveLength(2)
    expect(evidence.pageErrors, 'page errors during the loading probe').toEqual([])
  })
}

// --- error + retry ----------------------------------------------------------

const errorProbe = async ({ page, request, surface, role }) => {
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: surface.route,
    slug: surface.slug,
    role,
    ...stamp(),
    scenario: 'the primary load answers 500, then the surface retry control',
    forcedStatus: 500,
    steps: {},
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const forced = await forceStatus(page, surface.primary, { status: 500 })

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const response = await page.goto(surface.href(fixtures))
    evidence.httpStatus = response?.status() ?? null

    const painted = await page
      .locator(surface.errorSelector)
      .waitFor({ state: 'visible', timeout: 10000 })
      .then(() => true)
      .catch(() => false)

    await page.waitForTimeout(300)

    evidence.steps.forced = {
      errorVisible: painted,
      errorText: await page
        .locator(surface.errorSelector)
        .innerText({ timeout: 4000 })
        .catch(() => ''),
      retryVisible: await page
        .locator(surface.retrySelector)
        .isVisible()
        .catch(() => false),
      shell: await shellOf(page, surface.loadingSelector)
    }
    evidence.shots.push(
      await shot(page, `error/${surface.slug}-${painted ? 'error' : 'no-error'}-desktop.png`)
    )

    if (evidence.steps.forced.retryVisible) {
      await forced.stop()

      const recovery = waitForCall(page, 'GET', surface.primaryPath, () => true, LOAD_TIMEOUT)
      await page.locator(surface.retrySelector).click({ timeout: 8000 })
      const settled = await recovery
      await surface.settle(page)
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      evidence.steps.recovered = {
        status: settled?.status() ?? null,
        errorVisible: await page
          .locator(surface.errorSelector)
          .isVisible()
          .catch(() => false),
        contentLength: await contentLength(page),
        list: await surface.list(page)
      }
      evidence.shots.push(await shot(page, `error/${surface.slug}-recovered-desktop.png`))
    } else {
      evidence.steps.recovered = { ok: false, reason: 'the retry control was not rendered' }
    }

    const forcedOk =
      evidence.steps.forced.errorVisible === true && evidence.steps.forced.retryVisible === true
    const recoveredOk =
      evidence.steps.recovered.errorVisible === false &&
      (evidence.steps.recovered.contentLength ?? 0) > THRESHOLD

    evidence.verdict = forcedOk && recoveredOk ? 'OK' : 'DEFECTO'
    evidence.detail =
      forcedOk && !recoveredOk
        ? 'the retry control reloads the list but the error panel stays or the surface stays blank'
        : null
  } finally {
    await forced.stop().catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(
      diagnostics.failedRequests,
      surface.slug === 'quotations' ? 'owner' : 'admin'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500,
      'probe: the primary load was answered with a forced 500'
    )
    audit(`error/${surface.slug}.json`, evidence)

    ROWS.errors.push({
      route: surface.route,
      role,
      verdict: evidence.verdict,
      forced: evidence.steps.forced
        ? {
            errorVisible: evidence.steps.forced.errorVisible,
            retryVisible: evidence.steps.forced.retryVisible,
            errorText: evidence.steps.forced.errorText
          }
        : null,
      recovered: evidence.steps.recovered ?? null,
      shots: evidence.shots
    })
  }

  return evidence
}

for (const surface of SURFACES) {
  test(`@U6 · ${surface.slug} con error y reintento`, async ({ page, request }) => {
    test.setTimeout(180000)

    const fixtures = await ensureFixtures(request)
    if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

    const evidence = await errorProbe({
      page,
      request,
      surface,
      role: surface.slug === 'quotations' ? 'administrador' : 'odontologo'
    })

    expect(evidence.steps.forced, 'the forced failure was painted').toBeTruthy()
    expect(evidence.pageErrors, 'page errors during the error probe').toEqual([])
  })
}

// --- aggregate --------------------------------------------------------------

test.afterAll(async () => {
  const rows = ROWS.rows.map(row => ({
    route: row.route,
    slug: row.slug,
    role: row.role,
    expectation: row.expectation,
    httpStatus: row.httpStatus,
    rendered: row.rendered,
    contentLength: row.contentLength,
    h1: row.shell?.h1?.count ?? null,
    heroTitle: row.shell?.hero?.title ?? null,
    kpis: row.shell?.kpis ?? null,
    tabs: row.tabs ?? [],
    rows: row.desktopRows,
    actions: row.actions,
    gate: row.gate
      ? { status: row.gate.status, writeRole: row.gate.writeRole, ok: row.gate.ok }
      : null,
    roleGates: row.roleGates ?? null,
    horizontalOverflow: row.horizontalOverflow,
    mobileRows: (row.mobile?.list?.rows ?? row.mobile?.list?.cards ?? []).length,
    shots: row.shots,
    pageErrors: row.pageErrors?.length ?? null,
    consoleErrors: row.consoleErrors?.length ?? null,
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
    loading: ROWS.loading ?? {},
    errors: ROWS.errors.map(row => ({
      route: row.route,
      verdict: row.verdict,
      forced: row.forced,
      recovered: row.recovered
        ? { status: row.recovered.status, errorVisible: row.recovered.errorVisible }
        : null
    })),
    totals: {
      rows: rows.length,
      rendered: rows.filter(row => row.rendered).length,
      overflow: rows.filter(row => row.horizontalOverflow === true).length,
      pageErrors: rows.reduce((sum, row) => sum + (row.pageErrors ?? 0), 0),
      unexpectedRequests: rows.reduce((sum, row) => sum + (row.unexpectedRequests ?? 0), 0),
      writeGateMismatches: rows.filter(row => row.roleGates?.verdict).length
    },
    rows
  })
})
