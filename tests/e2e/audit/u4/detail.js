// Plan #13 (issue #367, U4) — the patient file: `/patients/:id`.
//
// Imported by `surface.spec.js`; `--grep "@U4 detail"` selects these rows and
// `--grep @U4` the whole session. Render rows land in
// `render/patients-id-<role>.json` with the shape `ui-pass.mjs` aggregates; the
// walkthrough rows keep their named files at the top of the session directory.
//
// The issue text counted four tabs: the page publishes the clinical tabs plus
// Historial, and inserts Laboratorio (`view.lab_case`) and Cuenta
// (`view.patient_statement`) before Historial. This module records what each
// role actually sees instead of re-stating the issue.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  capture,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  hasHorizontalOverflow,
  loginAs,
  primeSession,
  readTabLabels,
  settleSurface,
  writeEvidence
} from '../harness.js'

import {
  U4_EVIDENCE_DIR,
  ensureFixtures,
  fixtureSummary,
  patientBySlot,
  registerCharge
} from './fixtures.js'

import {
  ACTION_TIMEOUT,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  PATIENTS_ROUTE,
  THRESHOLD,
  call,
  delayRoute,
  localDay,
  markExpected,
  monthStart,
  parseCurrency,
  readDetailSnapshot,
  readPanel,
  readStatement,
  requestTracker,
  requestsTo,
  round2,
  settleDetail,
  statementMath,
  waitForCall,
  waitForToast
} from './helpers.js'

const ROLES = Object.freeze(['administrador', 'recepcionista', 'odontologo', 'finanzas'])
const EXPECTATIONS = Object.freeze({
  administrador: 'admin',
  recepcionista: 'owner',
  odontologo: 'owner',
  finanzas: 'owner'
})

const ALL_TABS = Object.freeze([
  'data',
  'treatment-plans',
  'quotations',
  'medical-records',
  'specialties',
  'laboratorio',
  'cuenta',
  'audit'
])

// #399: the drawer only offers the reads the authenticated desk can perform,
// so the probe names the gate behind every hidden tab.
const TAB_GATES = Object.freeze({
  'treatment-plans': 'manage.treatment_plan',
  quotations: 'view.quotation',
  'medical-records': 'manage.medical_record',
  specialties: 'manage.specialty_record',
  laboratorio: 'view.lab_case',
  cuenta: 'view.patient_statement',
  audit: 'view.audit_log'
})

const TAB_ENDPOINTS = Object.freeze({
  data: id => ({ path: `/api/patients/${id}` }),
  'treatment-plans': id => ({ path: '/api/treatment-plans', query: `patient_id=${id}` }),
  quotations: id => ({ path: '/api/quotations', query: `patient_id=${id}` }),
  'medical-records': id => ({ path: '/api/medical-records', query: `patient_id=${id}` }),
  specialties: id => ({ path: `/api/specialty-records/patient/${id}/all` }),
  laboratorio: id => ({ path: '/api/lab-cases', query: `patient_id=${id}` }),
  cuenta: id => ({
    path: `/api/patients/${id}/statement`,
    query: `date_from=${monthStart()}&date_to=${localDay()}`
  }),
  audit: id => ({ path: `/api/audit-logs/patient/${id}` })
})

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U4_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U4_EVIDENCE_DIR })

const detailRoute = patientId => `${PATIENTS_ROUTE}/${patientId}`
const patientResponse = (page, patientId, timeout = LOAD_TIMEOUT) =>
  waitForCall(page, 'GET', `/api/patients/${patientId}`, () => true, timeout)

const openDetail = async (page, session, patientId) => {
  await page.setViewportSize(DESKTOP)
  await primeSession(page, session)

  const payload = patientResponse(page, patientId)
  const response = await page.goto(detailRoute(patientId))
  const settled = await payload

  await settleDetail(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

  return { response, settled, snapshot: await readDetailSnapshot(page) }
}

const clickTab = async (page, id) => {
  await page
    .locator(`#tab-${id}`)
    .click({ timeout: 5000 })
    .catch(() => {})
  await page
    .waitForFunction(
      target => document.querySelector(`#tab-${target}`)?.getAttribute('aria-selected') === 'true',
      id,
      { timeout: 5000 }
    )
    .catch(() => {})
}

const closeDialog = async page => {
  const dialog = page.locator('[role="dialog"]:visible').last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
}

const queryObject = query => (query ? Object.fromEntries(new URLSearchParams(query)) : {})

const endpointCall = (request, session, patientId, tab) => {
  const descriptor = TAB_ENDPOINTS[tab](patientId)
  const path = descriptor.query ? `${descriptor.path}?${descriptor.query}` : descriptor.path
  return call(request, session, 'GET', path)
}

const requestLog = page => {
  const log = []
  page.on('response', response => {
    const request = response.request()
    if (request.method() !== 'GET') return

    try {
      const url = new URL(response.url())
      if (!url.pathname.startsWith('/api/')) return
      log.push({
        path: url.pathname,
        query: Object.fromEntries(url.searchParams.entries()),
        status: response.status()
      })
    } catch {
      // Not an HTTP URL; the log is for /api reads only.
    }
  })
  return log
}

const lastRequest = (log, path, query = {}) =>
  [...log].reverse().find(entry => {
    if (entry.path !== path) return false
    return Object.entries(query).every(([key, value]) => entry.query[key] === value)
  }) ?? null

// --- layer 2: render per role ---------------------------------------------

for (const role of ROLES) {
  test(`@U4 detail · render for ${role}`, async ({ page, request }) => {
    test.setTimeout(120000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const empty = patientBySlot(fixtures, 'p1')
    const diagnostics = collectDiagnostics(page)
    const expectation = EXPECTATIONS[role]
    const evidence = {
      route: '/patients/:id',
      slug: 'patients-id',
      role,
      expectation,
      href: empty?.id ? detailRoute(empty.id) : null,
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
      tabs: [],
      tabCount: 0,
      activeTab: null,
      snapshot: null,
      mobileSnapshot: null,
      fixtures: fixtureSummary(fixtures)
    }

    try {
      if (!empty?.id) {
        evidence.reason = 'P1 (empty) fixture unavailable'
        test.skip(true, evidence.reason)
      }

      const opened = await openDetail(page, session, empty.id)
      evidence.httpStatus = opened.response?.status() ?? null
      evidence.url = page.url()
      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD
      evidence.snapshot = await readDetailSnapshot(page)
      evidence.tabs = await readTabLabels(page)
      evidence.tabCount = evidence.tabs.length
      evidence.activeTab = evidence.snapshot.activeTab
      evidence.notFound = evidence.snapshot.h1.texts.some(text =>
        /página no encontrada/i.test(text)
      )
      evidence.shots.push(await shot(page, `shots/u4-patients-id-${role}-desktop.png`))

      await page.setViewportSize(MOBILE)
      const mobilePayload = patientResponse(page, empty.id)
      await page.reload()
      await mobilePayload
      await settleDetail(page)
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }
      evidence.mobileSnapshot = await readDetailSnapshot(page)
      evidence.shots.push(await shot(page, `shots/u4-patients-id-${role}-mobile.png`))
    } finally {
      evidence.pageErrors = diagnostics.pageErrors
      evidence.consoleErrors = diagnostics.consoleErrors
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
      audit(`render/patients-id-${role}.json`, evidence)
    }

    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
    expect(evidence.rendered, `detail rendered for ${role}`).toBe(true)
  })
}

// --- every visible tab, its request and its three states -------------------

test('@U4 detail · tabs', async ({ page, request }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures(request)
  const empty = patientBySlot(fixtures, 'p1')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: '/patients/:id',
    patientId: empty?.id ?? null,
    roles: {},
    findings: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!empty?.id) {
      evidence.reason = 'P1 (empty) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    for (const role of ROLES) {
      const session = await loginAs(request, role)
      const log = requestLog(page)
      const statementDelay = await delayRoute(page, /\/api\/patients\/\d+\/statement/, 700)
      const auditDelay = await delayRoute(page, /\/api\/audit-logs\/patient\//, 700)
      const labDelay = await delayRoute(page, /\/api\/lab-cases(\?|$)/, 700)
      const row = { role, tabs: [], hiddenTabs: [], toast: null, statementLoadingText: null }

      try {
        const opened = await openDetail(page, session, empty.id)

        row.visibleTabs = opened.snapshot.tabs.map(tab => tab.id)
        row.tabLabels = await readTabLabels(page)
        row.tabCount = row.tabLabels.length
        row.buttons = opened.snapshot.buttons
        row.identity = opened.snapshot.identity

        // The statement panel loads on mount; its loading affordance is
        // measured with a deliberate second read (Aplicar) while delayed.
        if (row.visibleTabs.includes('cuenta')) {
          await clickTab(page, 'cuenta')
          await page
            .locator('[data-statement-kpi-value="closing"]')
            .first()
            .waitFor({ state: 'visible', timeout: 8000 })
            .catch(() => {})

          const applyRequest = waitForCall(
            page,
            'GET',
            `/api/patients/${empty.id}/statement`,
            () => true,
            ACTION_TIMEOUT
          )
          await page.locator('[data-statement-apply]').click({ timeout: 5000 })
          const loading = page.locator('[data-statement-loading]')
          const loadingSeen = await loading
            .waitFor({ state: 'visible', timeout: 4000 })
            .then(() => true)
            .catch(() => false)

          row.statementLoadingText = loadingSeen
            ? (await loading.innerText().catch(() => '')).trim()
            : null
          await applyRequest
          await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        }

        await statementDelay.stop()

        for (const tabId of row.visibleTabs) {
          const descriptor = TAB_ENDPOINTS[tabId](empty.id)
          const query = queryObject(descriptor.query)
          const before = await readPanel(page, tabId)
          const lazy = tabId === 'audit' || tabId === 'laboratorio'
          const pending = lazy
            ? waitForCall(page, 'GET', descriptor.path, params =>
                Object.entries(query).every(([key, value]) => params.get(key) === value)
              )
            : null
          const spinner = page.locator(
            `[data-patient-tab-panel="${tabId}"] .loading-spinner-container`
          )

          await clickTab(page, tabId)

          let loadingText = null
          if (lazy) {
            const loadingSeen = await spinner
              .waitFor({ state: 'visible', timeout: 4000 })
              .then(() => true)
              .catch(() => false)
            loadingText = loadingSeen ? (await spinner.innerText().catch(() => '')).trim() : null
            await pending
            await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
          }

          const after = await readPanel(page, tabId)
          const logged = lastRequest(log, descriptor.path, query)

          row.tabs.push({
            id: tabId,
            request: {
              path: descriptor.path,
              query: descriptor.query ?? null,
              status: logged?.status ?? null
            },
            loadsOnActivation: lazy,
            loadingText,
            panelBefore: before,
            panelAfter: after,
            state:
              after?.errorState != null
                ? 'error'
                : after?.emptyState != null
                  ? 'empty'
                  : after?.rows > 0 || (after?.contentLength ?? 0) > 0
                    ? 'content'
                    : 'unknown'
          })
        }

        row.toast = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[aria-live="assertive"]'))
            .map(node => node.innerText.trim())
            .filter(Boolean)
            .join(' | ')
        )

        const missing = ALL_TABS.filter(tab => !row.visibleTabs.includes(tab))
        for (const tabId of missing) {
          const response = await endpointCall(request, session, empty.id, tabId)
          row.hiddenTabs.push({
            id: tabId,
            endpoint: TAB_ENDPOINTS[tabId](empty.id).path,
            status: response.status,
            gate: TAB_GATES[tabId] ?? null
          })
        }

        evidence.shots.push(await shot(page, `shots/u4-patients-id-tabs-${role}.png`))
      } finally {
        await statementDelay.stop().catch(() => {})
        await auditDelay.stop().catch(() => {})
        await labDelay.stop().catch(() => {})
      }

      evidence.roles[role] = row
    }

    const adminTabs = evidence.roles.administrador?.tabs ?? []
    const adminRequests = adminTabs.every(tab => tab.request.status === 200)
    const finanzasVisible = evidence.roles.finanzas?.visibleTabs ?? []
    const finanzasHidden = evidence.roles.finanzas?.hiddenTabs ?? []
    const finanzasHiddenIds = finanzasHidden.map(tab => tab.id)
    const finanzasDenied = finanzasHidden.every(tab => tab.status === 403)

    evidence.findings = {
      adminEveryTabLoaded: adminRequests,
      adminTabCount: adminTabs.length,
      // #399: finance reads quotations and the ledger; plans, records, their
      // specialty act and the audit trail are not its desk, so the endpoints
      // answer 403 and the tabs are not offered.
      finanzas: {
        visible: finanzasVisible,
        hidden: finanzasHidden,
        hiddenDenied: finanzasDenied
      },
      recepcionista: {
        visible: evidence.roles.recepcionista?.visibleTabs ?? [],
        hidden: evidence.roles.recepcionista?.hiddenTabs ?? []
      },
      odontologo: {
        visible: evidence.roles.odontologo?.visibleTabs ?? [],
        hidden: evidence.roles.odontologo?.hiddenTabs ?? []
      }
    }

    const financeContract =
      finanzasVisible.join(',') === 'data,quotations,cuenta' &&
      ['treatment-plans', 'medical-records', 'audit'].every(id => finanzasHiddenIds.includes(id)) &&
      finanzasDenied

    evidence.verdict = adminTabs.length === 8 && adminRequests && financeContract ? 'OK' : 'DEFECTO'
  } finally {
    await closeDialog(page)
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('detail-tabs.json', evidence)
  }

  expect(Object.keys(evidence.roles).length, 'roles walked').toBe(4)
  expect(evidence.roles.administrador?.tabs.length, 'admin tabs walked').toBeGreaterThan(0)
  expect(evidence.pageErrors, 'page errors during the tab walk').toEqual([])
})

// --- manual charge: modal, validation, write, statement reload -------------

test('@U4 detail · cargo', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const target = patientBySlot(fixtures, 'p4')
  const diagnostics = collectDiagnostics(page)
  const tracker = requestTracker(page)
  const evidence = {
    route: '/patients/:id',
    patientId: target?.id ?? null,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  const record = (key, value) => {
    evidence.steps[key] = value
  }

  try {
    if (!target?.id) {
      evidence.reason = 'P4 (charge) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    const before = await call(
      request,
      session,
      'GET',
      `/api/patients/${target.id}/statement?date_from=${monthStart()}&date_to=${localDay()}`
    )
    record('statementBefore', {
      status: before.status,
      opening: before.body?.data?.opening_balance ?? null,
      closing: before.body?.data?.closing_balance ?? null,
      entries: before.body?.data?.entries?.length ?? 0
    })

    await openDetail(page, session, target.id)

    await page.locator('[data-charge-patient-button]').click({ timeout: 5000 })
    const dialog = page.locator('[role="dialog"]:visible').last()
    await dialog.waitFor({ state: 'visible', timeout: 8000 })

    record('openedFromHero', {
      title: await dialog
        .locator('h2')
        .first()
        .innerText()
        .catch(() => ''),
      fields: await dialog.locator('input').count(),
      submitVisible: await page
        .locator('[data-patient-charge-submit]')
        .isVisible()
        .catch(() => false)
    })

    // Empty submit: the modal validates locally and never calls the API.
    await page.locator('[data-patient-charge-submit]').click({ timeout: 5000 })
    await page.evaluate(
      () =>
        new Promise(resolve =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve(null)))
        )
    )
    const errors = await page.evaluate(() =>
      Object.fromEntries(
        Array.from(document.querySelectorAll('[data-patient-charge-error]')).map(node => [
          node.getAttribute('data-patient-charge-error'),
          node.innerText.trim()
        ])
      )
    )
    record('emptySubmit', {
      errors,
      postAttempts: requestsTo(tracker, 'POST', '/api/patient-charges').length,
      dialogStillOpen: await dialog.isVisible().catch(() => false)
    })

    const amount = 240.5
    await page.locator('[data-patient-charge-amount] input').fill(String(amount))
    await page.locator('[data-patient-charge-description] input').fill(`Cargo ${fixtures.marker}`)
    await page.locator('[data-patient-charge-charged-at] input').fill(localDay())

    const statementReload = waitForCall(
      page,
      'GET',
      `/api/patients/${target.id}/statement`,
      () => true,
      ACTION_TIMEOUT
    )
    const post = waitForCall(page, 'POST', '/api/patient-charges')
    await page.locator('[data-patient-charge-submit]').click({ timeout: 5000 })
    const postSettled = await post
    const postBody = postSettled ? await postSettled.json().catch(() => null) : null
    const toast = await waitForToast(page, 'Cargo registrado', 8000)
    await statementReload
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    // The modal closes on a 200 ms transition; record the settled state, not
    // the frame the leave animation starts on.
    const closed = await dialog
      .waitFor({ state: 'hidden', timeout: 5000 })
      .then(() => true)
      .catch(() => false)

    record('created', {
      status: postSettled?.status() ?? null,
      id: postBody?.data?.id ?? null,
      amount: postBody?.data?.amount ?? null,
      toast,
      dialogClosed: closed
    })

    if (postBody?.data?.id) {
      evidence.charge = registerCharge({
        id: postBody.data.id,
        patientId: target.id,
        amount: postBody.data.amount ?? amount
      })
    }

    await clickTab(page, 'cuenta')
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const dom = await readStatement(page)
    const after = await call(
      request,
      session,
      'GET',
      `/api/patients/${target.id}/statement?date_from=${monthStart()}&date_to=${localDay()}`
    )

    record('statementAfter', {
      status: after.status,
      opening: after.body?.data?.opening_balance ?? null,
      closing: after.body?.data?.closing_balance ?? null,
      entries: after.body?.data?.entries?.length ?? 0,
      delta: round2(
        Number(after.body?.data?.closing_balance ?? 0) -
          Number(before.body?.data?.closing_balance ?? 0)
      ),
      entryDescriptions: (after.body?.data?.entries ?? []).map(entry => entry.description),
      domKpis: dom.kpis,
      domEntryCount: dom.entries.length,
      domDescriptionFound: dom.entries.some(entry =>
        String(entry.description ?? '').includes(`Cargo ${fixtures.marker}`)
      ),
      domClosing: dom.kpis.closing ?? null
    })

    // The second door: the Cuenta tab's own "Registrar cargo" button.
    await page.locator('[data-statement-charge]').click({ timeout: 5000 })
    const secondDialog = page.locator('[role="dialog"]:visible').last()
    const secondOpen = await secondDialog.isVisible().catch(() => false)

    record('openedFromCuenta', { open: secondOpen })
    evidence.shots.push(await shot(page, 'shots/u4-patients-id-charge.png'))
    await page
      .locator('[data-patient-charge-cancel]')
      .click({ timeout: 5000 })
      .catch(() => {})
    await secondDialog.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

    const createdOk =
      evidence.steps.created.status === 201 &&
      evidence.steps.statementAfter.delta === amount &&
      evidence.steps.statementAfter.domDescriptionFound === true &&
      evidence.steps.openedFromCuenta.open === true

    evidence.verdict = evidence.steps.emptySubmit.postAttempts === 0 && createdOk ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    await closeDialog(page)
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('detail-charge.json', evidence)
  }

  expect(evidence.steps.emptySubmit, 'empty charge submit measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the charge probe').toEqual([])
})

// --- statement: arithmetic, DOM, empty period, failing request -------------

test('@U4 detail · estado de cuenta', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const target = patientBySlot(fixtures, 'p4')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: '/patients/:id',
    patientId: target?.id ?? null,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  const record = (key, value) => {
    evidence.steps[key] = value
  }

  try {
    if (!target?.id) {
      evidence.reason = 'P4 (charge) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    await openDetail(page, session, target.id)
    await clickTab(page, 'cuenta')
    await page
      .locator('[data-statement-kpi-value="closing"]')
      .first()
      .waitFor({ state: 'visible', timeout: 10000 })
      .catch(() => {})
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const payload = await call(
      request,
      session,
      'GET',
      `/api/patients/${target.id}/statement?date_from=${monthStart()}&date_to=${localDay()}`
    )
    const data = payload.body?.data ?? {}
    const math = statementMath(data)
    const dom = await readStatement(page)

    const domNumbers = {
      opening: parseCurrency(dom.kpis.opening),
      charges: parseCurrency(dom.kpis.charges),
      payments: parseCurrency(dom.kpis.payments),
      closing: parseCurrency(dom.kpis.closing)
    }

    record('payload', {
      status: payload.status,
      period: data.period ?? null,
      opening: math.opening,
      closing: math.closing,
      walk: math.walk,
      formula: math.formula,
      summary: data.summary ?? null,
      entries: math.entries,
      matches: math.matches
    })

    record('dom', {
      period: dom.period,
      kpis: dom.kpis,
      numbers: domNumbers,
      summary: dom.summary,
      entryCount: dom.entries.length,
      balanceLast: dom.entries.at(-1)?.balance ?? null,
      matchesPayload:
        domNumbers.opening === math.opening &&
        domNumbers.closing === math.closing &&
        domNumbers.charges === round2(data.summary?.charges ?? 0) &&
        domNumbers.payments === round2(data.summary?.payments ?? 0)
    })

    // Empty period
    await page.locator('[data-statement-date-from] input').fill('2000-01-01')
    await page.locator('[data-statement-date-to] input').fill('2000-01-31')
    const emptyRequest = waitForCall(
      page,
      'GET',
      `/api/patients/${target.id}/statement`,
      params => params.get('date_from') === '2000-01-01'
    )
    await page.locator('[data-statement-apply]').click({ timeout: 5000 })
    const emptySettled = await emptyRequest
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
    const emptyState = await readStatement(page)

    record('emptyPeriod', {
      status: emptySettled?.status() ?? null,
      period: emptyState.period,
      emptyState: emptyState.emptyState,
      entries: emptyState.entries.length,
      kpis: emptyState.kpis
    })
    evidence.shots.push(await shot(page, 'shots/u4-patients-id-statement-empty.png'))

    // Failing request → error state → retry recovers
    const failing = route =>
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'AUDIT-U4 forced statement failure' })
      })

    await page.route(/\/api\/patients\/\d+\/statement/, failing)
    const forced = waitForCall(page, 'GET', `/api/patients/${target.id}/statement`, () => true)
    await page.locator('[data-statement-apply]').click({ timeout: 5000 })
    const forcedSettled = await forced
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
    const errorState = await readStatement(page)

    record('failure', {
      status: forcedSettled?.status() ?? null,
      errorState: errorState.errorState,
      retry: errorState.retry
    })
    evidence.shots.push(await shot(page, 'shots/u4-patients-id-statement-error.png'))

    await page.unroute(/\/api\/patients\/\d+\/statement/, failing).catch(() => {})
    const recovered = waitForCall(page, 'GET', `/api/patients/${target.id}/statement`, () => true)
    await page.locator('[data-statement-retry]').click({ timeout: 5000 })
    const recoveredSettled = await recovered
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
    const recoveredState = await readStatement(page)

    record('retry', {
      status: recoveredSettled?.status() ?? null,
      errorState: recoveredState.errorState,
      emptyState: recoveredState.emptyState,
      entries: recoveredState.entries.length
    })

    evidence.verdict =
      math.matches &&
      evidence.steps.dom.matchesPayload &&
      emptyState.emptyState === true &&
      errorState.errorState === true &&
      recoveredState.errorState === false
        ? 'OK'
        : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && failure.url?.includes('/statement'),
      'probe: the 500 is forced by the instrument to measure the error state'
    )
    audit('detail-statement.json', evidence)
  }

  expect(evidence.steps.payload, 'statement payload read').toBeTruthy()
  expect(evidence.steps.retry, 'retry measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the statement probe').toEqual([])
})

// --- quotations: Ver → /quotations?open=, PDF download ---------------------

test('@U4 detail · presupuestos', async ({ page, request }) => {
  test.setTimeout(150000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const target = patientBySlot(fixtures, 'p5')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: '/patients/:id',
    patientId: target?.id ?? null,
    quotationId: fixtures.quotation?.id ?? null,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!target?.id || !fixtures.quotation?.ok) {
      evidence.reason = fixtures.quotation?.reason ?? 'P5 (quotation) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    const quotationsPayload = waitForCall(
      page,
      'GET',
      '/api/quotations',
      params => params.get('patient_id') === String(target.id)
    )
    await openDetail(page, session, target.id)
    await quotationsPayload
    await clickTab(page, 'quotations')
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const panel = await readPanel(page, 'quotations')
    evidence.steps.panel = { ...panel, quotationId: fixtures.quotation.id }

    const verButton = page
      .locator('[data-patient-tab-panel="quotations"]')
      .getByRole('button', { name: 'Ver', exact: true })
      .first()

    if (!(await verButton.isVisible().catch(() => false))) {
      evidence.steps.open = { ok: false, reason: 'the quotation row is not visible' }
    } else {
      await verButton.click({ timeout: 5000 })
      await page.waitForURL(/\/quotations\?open=\d+/, { timeout: 15000 }).catch(() => {})
      evidence.steps.open = {
        ok: true,
        url: page.url().replace(BASE_URL, ''),
        expected: `/quotations?open=${fixtures.quotation.id}`,
        matches: page.url().endsWith(`/quotations?open=${fixtures.quotation.id}`)
      }
      evidence.shots.push(await shot(page, 'shots/u4-patients-id-quotation-open.png'))
    }

    const detailReturn = patientResponse(page, target.id)
    await page.goBack({ waitUntil: 'commit' }).catch(() => {})
    await detailReturn
    await settleDetail(page)
    await clickTab(page, 'quotations')
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const pdfRequest = waitForCall(
      page,
      'GET',
      `/api/quotations/${fixtures.quotation.id}/pdf`,
      () => true
    )
    await page
      .locator('[data-patient-tab-panel="quotations"]')
      .getByRole('button', { name: 'PDF', exact: true })
      .first()
      .click({ timeout: 5000 })
    const pdfSettled = await pdfRequest

    evidence.steps.pdf = {
      status: pdfSettled?.status() ?? null,
      contentType: pdfSettled?.headers()['content-type'] ?? null,
      contentDisposition: pdfSettled?.headers()['content-disposition'] ?? null
    }

    evidence.verdict =
      evidence.steps.open?.matches === true && evidence.steps.pdf?.status === 200 ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('detail-quotations.json', evidence)
  }

  expect(evidence.steps.panel, 'quotation panel read').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the quotations probe').toEqual([])
})

// --- laboratory: presence per role, empty state, the modal -----------------

test('@U4 detail · laboratorio', async ({ page, request }) => {
  test.setTimeout(240000)

  const fixtures = await ensureFixtures(request)
  const empty = patientBySlot(fixtures, 'p1')
  const admin = await loginAs(request, 'administrador')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: '/patients/:id',
    patientId: empty?.id ?? null,
    roles: {},
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!empty?.id) {
      evidence.reason = 'P1 (empty) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    for (const role of ROLES) {
      const session = await loginAs(request, role)
      const opened = await openDetail(page, session, empty.id)
      const ids = opened.snapshot.tabs.map(tab => tab.id)
      evidence.roles[role] = {
        tabs: ids,
        laboratorioPresent: ids.includes('laboratorio'),
        laboratorioLabel: opened.snapshot.tabs.find(tab => tab.id === 'laboratorio')?.label ?? null,
        cuentaPresent: ids.includes('cuenta')
      }
    }

    await openDetail(page, admin, empty.id)
    const labRequest = waitForCall(page, 'GET', '/api/lab-cases')
    await clickTab(page, 'laboratorio')
    await labRequest
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const panel = await readPanel(page, 'laboratorio')
    const emptyMarker = await page
      .locator('[data-patient-lab-cases-empty]')
      .isVisible()
      .catch(() => false)

    evidence.steps.empty = { panel, emptyMarker }

    await page.locator('[data-patient-lab-case-new]').click({ timeout: 5000 })
    const dialog = page.locator('[role="dialog"]:visible').last()
    await dialog.waitFor({ state: 'visible', timeout: 8000 })

    evidence.steps.modal = {
      open: true,
      title: await dialog
        .locator('h2')
        .first()
        .innerText()
        .catch(() => ''),
      fixedPatient: await page
        .locator('[data-lab-case-form-patient-fixed]')
        .isVisible()
        .catch(() => false),
      fields: {
        work: await page
          .locator('[data-lab-case-form-work]')
          .isVisible()
          .catch(() => false),
        material: await page
          .locator('[data-lab-case-form-material]')
          .isVisible()
          .catch(() => false),
        sentAt: await page
          .locator('[data-lab-case-form-sent-at]')
          .isVisible()
          .catch(() => false),
        promisedAt: await page
          .locator('[data-lab-case-form-promised-at]')
          .isVisible()
          .catch(() => false),
        professional: await page
          .locator('[data-lab-case-form-professional]')
          .isVisible()
          .catch(() => false),
        technician: await page
          .locator('[data-lab-case-form-technician]')
          .isVisible()
          .catch(() => false),
        piece: await page
          .locator('[data-lab-case-form-piece]')
          .isVisible()
          .catch(() => false)
      }
    }
    evidence.shots.push(await shot(page, 'shots/u4-patients-id-lab-modal.png'))
    await closeDialog(page)

    // The write door exists; an invalid body writes nothing. The read door has
    // no DELETE at all, which is why this probe does not create a case: it could
    // not be retired and the session's exit criterion is zero residue.
    const invalidPost = await call(request, admin, 'POST', '/api/lab-cases', {})
    const deleteless = await call(request, admin, 'DELETE', '/api/lab-cases/0')

    evidence.steps.writeDoor = {
      invalidPostStatus: invalidPost.status,
      invalidPostMessage: invalidPost.message,
      deleteStatus: deleteless.status,
      deleteMessage: deleteless.message
    }
    evidence.steps.create = {
      created: false,
      verdict: 'NO PROBADO',
      reason:
        'POST /api/lab-cases has no DELETE door (measured: ' +
        `${deleteless.status}); a case created here would stay in the audit database forever`
    }

    evidence.verdict =
      evidence.roles.administrador?.laboratorioPresent === true &&
      evidence.roles.finanzas?.laboratorioPresent === false &&
      panel?.emptyState === 'empty-laboratorio' &&
      evidence.steps.modal.open === true
        ? 'OK'
        : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    await closeDialog(page)
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('detail-lab.json', evidence)
  }

  expect(evidence.steps.modal, 'lab modal measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the lab probe').toEqual([])
})

// --- export ----------------------------------------------------------------

test('@U4 detail · exportación', async ({ page, request }) => {
  test.setTimeout(180000)

  const fixtures = await ensureFixtures(request)
  const empty = patientBySlot(fixtures, 'p1')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: '/patients/:id',
    patientId: empty?.id ?? null,
    roles: {},
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!empty?.id) {
      evidence.reason = 'P1 (empty) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    for (const role of ROLES) {
      const session = await loginAs(request, role)
      const opened = await openDetail(page, session, empty.id)
      const visible = opened.snapshot.buttons.export

      evidence.roles[role] = {
        buttonVisible: visible,
        grant: fixtures.sessions?.[role]?.permissions?.['export.patient'] ?? null
      }

      if (role !== 'administrador' && role !== 'finanzas') continue
      if (!visible) continue

      const exportRequest = waitForCall(page, 'GET', `/api/patients/${empty.id}/export`, () => true)
      await page.locator('[data-export-button]').click({ timeout: 5000 })
      const settled = await exportRequest
      const toast = await waitForToast(page, 'export', 10000)

      evidence.roles[role] = {
        ...evidence.roles[role],
        click: {
          status: settled?.status() ?? null,
          contentType: settled?.headers()['content-type'] ?? null,
          contentLength: settled?.headers()['content-length'] ?? null,
          toast
        }
      }
      evidence.shots.push(await shot(page, `shots/u4-patients-id-export-${role}.png`))
    }

    evidence.steps.gate = {
      auditedRoles: [...ROLES],
      rolesWithoutExportAmongAudited: ROLES.filter(
        role => fixtures.sessions?.[role]?.permissions?.['export.patient'] !== true
      ),
      note:
        'all four audited roles hold export.patient; finanzas holds the grant, ' +
        'PatientPolicy::export() refuses it, and the surface follows the capability ' +
        'the payload publishes, so finanzas sees no button and no 403'
    }

    const adminExport = evidence.roles.administrador?.click?.status ?? null
    const finanzasButton = evidence.roles.finanzas?.buttonVisible === true

    evidence.verdict =
      adminExport === 200 &&
      evidence.roles.administrador?.click?.contentType?.includes('pdf') &&
      !finanzasButton
        ? 'OK'
        : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('detail-export.json', evidence)
  }

  expect(Object.keys(evidence.roles).length, 'roles measured').toBe(4)
  expect(evidence.pageErrors, 'page errors during the export probe').toEqual([])
})
