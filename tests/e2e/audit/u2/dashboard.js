// Plan #13 (issue #365, U2) — the landing surface: `/dashboard`.
//
// Imported by `surface.spec.js`, so `--grep "@U2 dashboard"` selects these rows
// and `--grep @U2` (the issue command) selects both U2 modules. Each row writes
// its evidence under the session directory and the render rows go to `render/`
// so `ui-pass` aggregates them with the rest of the run.
//
// This is measurement code: a product defect is recorded in the evidence and
// never fails the suite. Asserted here only that a probe ran to completion and
// that no page error was raised (the session's exit criterion).

import { test, expect } from 'playwright/test'

import {
  DIALOG_SELECTOR,
  assertDialogContract,
  capture,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  hasHorizontalOverflow,
  loginAs,
  primeSession,
  settleSurface,
  writeEvidence
} from '../harness.js'

import { U2_EVIDENCE_DIR, ensureFixtures, fixtureSummary } from './fixtures.js'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ROUTE = '/dashboard'
const THRESHOLD = 80
const LOAD_TIMEOUT = 20000
const SETTLE_TIMEOUT = 15000
const RETRY_TIMEOUT = 15000

const ROLES = Object.freeze(['administrador', 'odontologo', 'recepcionista', 'finanzas'])
const CASH_PERMISSION = 'view.cash_register'
const CASH_ENDPOINT = /\/api\/cash-register\//
const WS_INDICATOR = '[aria-label^="Estado de WebSocket:"]'
const REVERB_PORTS = ['8081', '8080']

// One row per resource: aborting it measures which blocks survive, and the
// retry hook is the one the page publishes for exactly that section.
const SECTIONS = Object.freeze([
  {
    key: 'stats',
    pattern: /\/api\/dashboard\/stats(\?.*)?$/,
    error: 'error-stats',
    retry: '[data-retry-stats]'
  },
  {
    key: 'appointments-today',
    pattern: /\/api\/dashboard\/appointments-today(\?.*)?$/,
    error: 'error-appointments',
    retry: '[data-retry-appointments]'
  },
  {
    key: 'upcoming',
    pattern: /\/api\/dashboard\/upcoming(\?.*)?$/,
    error: 'error-upcoming',
    retry: '[data-retry-upcoming]'
  },
  {
    key: 'pending',
    pattern: /\/api\/dashboard\/pending(\?.*)?$/,
    error: 'error-pending',
    retry: '[data-retry-pending]'
  }
])

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U2_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U2_EVIDENCE_DIR })

// The isolation probe cuts its own endpoint: that `net::ERR_FAILED` is the
// experiment, not an unexpected surface failure. Everything else keeps the
// harness classification.
const classifySectionFailures = (failures, section) => {
  const classified = classifyFailures(failures, 'owner')
  const probeAborts = classified.unexpected.filter(
    entry => entry.status == null && section.pattern.test(entry.url ?? '')
  )

  return {
    expected: [
      ...classified.expected,
      ...probeAborts.map(entry => ({ ...entry, reason: 'aborted by the isolation probe' }))
    ],
    unexpected: classified.unexpected.filter(entry => !probeAborts.includes(entry))
  }
}

const readDashboard = page =>
  page.evaluate(() => {
    const text = node => (node?.textContent ?? '').trim()
    const attributeList = (selector, attribute) =>
      Array.from(document.querySelectorAll(selector))
        .map(node => node.getAttribute(attribute))
        .filter(value => value !== null)

    const headings = Array.from(document.querySelectorAll('h1')).map(node => {
      const box = node.getBoundingClientRect()
      return {
        text: text(node),
        visible: box.width > 0 && box.height > 0
      }
    })

    const cash = document.querySelector('[data-stat-card="cash-balance"]')
    const states = attributeList('[data-state]', 'data-state')
    const agendaRows = document.querySelectorAll('[data-appointment-row]').length
    const pendingRows = document.querySelectorAll('[data-pending-row]').length
    const upcomingRows = document.querySelectorAll('[data-upcoming-row]').length
    const modeOf = (rows, empty, error) =>
      rows > 0
        ? 'rows'
        : states.includes(empty)
          ? 'empty'
          : states.includes(error)
            ? 'error'
            : 'absent'

    return {
      h1: {
        count: headings.length,
        visibleCount: headings.filter(heading => heading.visible).length,
        texts: headings.map(heading => heading.text)
      },
      blocks: attributeList('[data-dashboard-block]', 'data-dashboard-block'),
      statCards: Array.from(document.querySelectorAll('[data-stat-card]')).map(card => ({
        key: card.getAttribute('data-stat-card'),
        value: text(card.querySelector('[data-stat]')),
        cashState: card.querySelector('[data-cash-state]')?.getAttribute('data-cash-state') ?? null,
        cashLabel: text(card.querySelector('[data-cash-state]'))
      })),
      cashCardVisible: Boolean(cash),
      cashCtaVisible: Array.from(cash?.querySelectorAll('button') ?? []).some(button =>
        /ir a caja/i.test(text(button))
      ),
      agendaRows,
      agendaMode: modeOf(agendaRows, 'empty-appointments', 'error-appointments'),
      pendingRows,
      pendingMode: modeOf(pendingRows, 'empty-pending', 'error-pending'),
      upcomingRows,
      upcomingMode: modeOf(upcomingRows, 'empty-upcoming', 'error-upcoming'),
      states,
      emptyStates: states.filter(state => String(state).startsWith('empty-')),
      errorStates: states.filter(state => String(state).startsWith('error-')),
      url: window.location.pathname
    }
  })

const readToast = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live]'))
      .map(node => node.innerText.trim())
      .filter(Boolean)
      .join(' | ')
  )

// The modal primitive fades and scales in; the contract reads geometry, so the
// probe waits for the transition to finish instead of measuring a moving box.
const settleDialog = async (page, dialog) => {
  const handle = await dialog.elementHandle().catch(() => null)
  if (!handle) return

  await page
    .waitForFunction(node => getComputedStyle(node).opacity === '1', handle, {
      timeout: 5000,
      polling: 50
    })
    .catch(() => {})
}

// --- render matrix ---------------------------------------------------------

for (const role of ROLES) {
  test(`@U2 dashboard · render for ${role}`, async ({ page, request }) => {
    test.setTimeout(90000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const cashRequests = []
    page.on('request', entry => {
      if (CASH_ENDPOINT.test(entry.url())) cashRequests.push(`${entry.method()} ${entry.url()}`)
    })

    const permitted = (session.user.permissions ?? []).includes(CASH_PERMISSION)
    const payloads = []
    page.on('response', async entry => {
      if (!entry.url().includes('/api/dashboard/')) return
      const path = entry.url().replace(/^https?:\/\/[^/]+/, '')
      let body = null
      try {
        body = await entry.json()
      } catch {
        body = null
      }
      const data = body?.data
      payloads.push({
        path,
        status: entry.status(),
        rows: Array.isArray(data) ? data.length : null,
        keys: data && !Array.isArray(data) ? Object.keys(data) : null,
        metaCount: body?.meta?.count ?? null,
        appointmentsToday: data?.appointments_today ?? null
      })
    })
    const evidence = {
      route: ROUTE,
      slug: 'dashboard',
      role,
      expectation: 'owner',
      href: ROUTE,
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
      cash: { permitted, requests: cashRequests, cardVisible: null, ctaVisible: null },
      snapshot: null,
      firstPaint: null,
      payloads,
      reducedMotion: 'reduce',
      fixtures: fixtureSummary(fixtures)
    }

    try {
      await page.setViewportSize(DESKTOP)
      await page.emulateMedia({ reducedMotion: 'reduce' })
      await primeSession(page, session)

      // The first frame paints the content branch with the empty arrays before
      // `loadDashboardData` sets `loading` (a real first-paint state worth
      // measuring), so the row waits for the four payloads before reading the
      // settled snapshot. Without this wait the probe measures the pre-load
      // frame and calls it the page's data state.
      const payloadWaits = [
        /\/api\/dashboard\/stats$/,
        /\/api\/dashboard\/appointments-today$/,
        /\/api\/dashboard\/upcoming$/,
        /\/api\/dashboard\/pending$/
      ].map(pattern =>
        page
          .waitForResponse(response => pattern.test(new URL(response.url()).pathname), {
            timeout: LOAD_TIMEOUT
          })
          .catch(() => null)
      )

      const response = await page.goto(ROUTE)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      evidence.firstPaint = await readDashboard(page)

      await Promise.all(payloadWaits)
      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD
      evidence.snapshot = await readDashboard(page)

      evidence.cash.cardVisible = evidence.snapshot.cashCardVisible
      evidence.cash.ctaVisible = evidence.snapshot.cashCtaVisible
      evidence.cash.requests = [...cashRequests]
      evidence.cash.compliant = permitted
        ? cashRequests.length > 0 && evidence.snapshot.cashCardVisible
        : cashRequests.length === 0 && !evidence.snapshot.cashCardVisible

      evidence.shots.push(await shot(page, `shots/u2-dashboard-${role}-desktop.png`))

      await page.setViewportSize(MOBILE)
      const mobilePayloads = [
        /\/api\/dashboard\/stats$/,
        /\/api\/dashboard\/appointments-today$/,
        /\/api\/dashboard\/upcoming$/,
        /\/api\/dashboard\/pending$/
      ].map(pattern =>
        page
          .waitForResponse(response => pattern.test(new URL(response.url()).pathname), {
            timeout: LOAD_TIMEOUT
          })
          .catch(() => null)
      )
      await page.reload()
      await Promise.all(mobilePayloads)
      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }

      evidence.shots.push(await shot(page, `shots/u2-dashboard-${role}-mobile.png`))
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    } finally {
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
      audit(`render/u2-dashboard-${role}.json`, evidence)
    }

    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- section isolation and retry ------------------------------------------

for (const section of SECTIONS) {
  test(`@U2 dashboard · section ${section.key} fails alone and recovers`, async ({
    page,
    request
  }) => {
    test.setTimeout(90000)

    const session = await loginAs(request, 'administrador')
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: ROUTE,
      role: 'administrador',
      endpoint: section.key,
      aborted: false,
      retryVisible: null,
      retryClicked: false,
      recovered: false,
      restOperative: null,
      reducedMotion: 'reduce',
      before: null,
      after: null,
      pageErrors: diagnostics.pageErrors,
      consoleErrors: diagnostics.consoleErrors,
      failedRequests: { expected: [], unexpected: [] },
      fixtures: fixtureSummary(fixtures),
      shots: []
    }

    const waitForContent = async () => {
      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)
    }

    // The page paints the content branch with empty arrays before the load
    // starts, so the probe waits for the three payloads that were not cut
    // before it reads the settled state. The cut endpoint never answers.
    const payloadWaits = [
      { key: 'stats', pattern: /\/api\/dashboard\/stats$/ },
      { key: 'appointments-today', pattern: /\/api\/dashboard\/appointments-today$/ },
      { key: 'upcoming', pattern: /\/api\/dashboard\/upcoming$/ },
      { key: 'pending', pattern: /\/api\/dashboard\/pending$/ }
    ]
      .filter(entry => entry.key !== section.key)
      .map(entry =>
        page
          .waitForResponse(response => entry.pattern.test(new URL(response.url()).pathname), {
            timeout: LOAD_TIMEOUT
          })
          .catch(() => null)
      )

    try {
      await page.setViewportSize(DESKTOP)
      await page.emulateMedia({ reducedMotion: 'reduce' })
      await primeSession(page, session)
      await page.route(section.pattern, route => route.abort())
      evidence.aborted = true

      const response = await page.goto(ROUTE)
      await Promise.all(payloadWaits)
      await waitForContent()
      evidence.before = {
        httpStatus: response?.status() ?? null,
        ...(await readDashboard(page)),
        contentLength: await contentLength(page)
      }
      evidence.shots.push(await shot(page, `shots/u2-section-${section.key}-before.png`))

      await page.unroute(section.pattern)

      const retry = page.locator(section.retry)
      const retryVisible = await retry.isVisible().catch(() => false)
      evidence.retryVisible = retryVisible

      if (retryVisible) {
        const retryResponse = page
          .waitForResponse(entry => section.pattern.test(new URL(entry.url()).pathname), {
            timeout: RETRY_TIMEOUT
          })
          .catch(() => null)
        await retry.click({ timeout: 5000 }).catch(() => {})
        evidence.retryClicked = true
        evidence.retryStatus = (await retryResponse)?.status() ?? null
        audit(`sections-${section.key}.json`, evidence)
        await page
          .waitForFunction(
            key => !document.querySelector(`[data-state="error-${key}"]`),
            section.error.replace(/^error-/, ''),
            { timeout: RETRY_TIMEOUT, polling: 150 }
          )
          .catch(() => {})
        await settleSurface(page, THRESHOLD, 5000)
      }

      evidence.after = {
        ...(await readDashboard(page)),
        contentLength: await contentLength(page),
        toast: await readToast(page).catch(() => '')
      }
      evidence.recovered = !evidence.after.errorStates.includes(section.error)
      const restWasOperativeDuringFailure =
        section.key === 'stats'
          ? evidence.before.blocks.length > 0
          : evidence.before.blocks.includes('hoy')
      evidence.restOperative = restWasOperativeDuringFailure
      evidence.restOperativeAfterRetry =
        section.key === 'stats'
          ? evidence.after.blocks.length > 0
          : evidence.after.blocks.includes('hoy')
      evidence.shots.push(await shot(page, `shots/u2-section-${section.key}-after.png`))
      evidence.failedRequests = classifySectionFailures(diagnostics.failedRequests, section)
    } finally {
      evidence.failedRequests = classifySectionFailures(diagnostics.failedRequests, section)
      audit(`sections-${section.key}.json`, evidence)
    }

    expect(evidence.aborted, `abort installed for ${section.key}`).toBe(true)
    expect(evidence.before, `before snapshot for ${section.key}`).not.toBeNull()
    expect(evidence.shots, `captures for ${section.key}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${section.key}`).toEqual([])
  })
}

// --- CTAs -----------------------------------------------------------------

const actionRows = []

for (const role of ['administrador', 'recepcionista']) {
  test(`@U2 dashboard · CTAs for ${role}`, async ({ page, request }) => {
    test.setTimeout(90000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const requests = []
    page.on('request', entry => requests.push(`${entry.method()} ${entry.url()}`))

    const row = {
      route: ROUTE,
      role,
      steps: [],
      pageErrors: diagnostics.pageErrors,
      consoleErrors: diagnostics.consoleErrors,
      failedRequests: { expected: [], unexpected: [] },
      reducedMotion: 'reduce',
      fixtures: fixtureSummary(fixtures),
      shots: []
    }

    const mark = () => requests.length

    const navigate = async (path, timeout = 12000) => {
      await page
        .waitForFunction(target => window.location.pathname === target, path, { timeout })
        .then(() => true)
        .catch(() => false)
      return page.evaluate(() => window.location.pathname).catch(() => null)
    }

    try {
      await page.setViewportSize(DESKTOP)
      await page.emulateMedia({ reducedMotion: 'reduce' })
      await primeSession(page, session)
      const dashboardPayloads = [
        /\/api\/dashboard\/stats$/,
        /\/api\/dashboard\/appointments-today$/,
        /\/api\/dashboard\/upcoming$/,
        /\/api\/dashboard\/pending$/
      ].map(pattern =>
        page
          .waitForResponse(response => pattern.test(new URL(response.url()).pathname), {
            timeout: LOAD_TIMEOUT
          })
          .catch(() => null)
      )
      await page.goto(ROUTE)
      await Promise.all(dashboardPayloads)
      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

      // 1. "Nueva cita" opens the appointment dialog.
      const newAppointment = page.getByRole('button', { name: 'Nueva cita', exact: true }).first()
      const createStep = {
        step: 'nueva-cita',
        visible: await newAppointment.isVisible().catch(() => false),
        pathname: await page.evaluate(() => window.location.pathname).catch(() => null),
        dialog: null,
        checks: [],
        closed: false,
        requests: []
      }
      const createMark = mark()

      if (createStep.visible) {
        await newAppointment.click({ timeout: 5000 }).catch(() => {})
        const dialog = page.locator(DIALOG_SELECTOR).last()

        if ((await dialog.count()) > 0) {
          await settleDialog(page, dialog)

          try {
            const contract = await assertDialogContract(page, { dialog })
            createStep.dialog = contract.dialog
            createStep.checks = contract.checks
          } catch (thrown) {
            createStep.probeError = String(thrown?.message ?? thrown)
          }

          await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
          createStep.closed = (await page.locator(DIALOG_SELECTOR).count()) === 0
        }
        createStep.requests = requests.slice(createMark)
        row.shots.push(await shot(page, `shots/u2-dashboard-actions-${role}-dialog.png`))
      }

      row.steps.push(createStep)

      // 2. The pending row's "Ver" leads to the module the row promises.
      const pendingRow = page.locator('[data-pending-row]').first()
      const pendingStep = {
        step: 'pending-ver',
        rows: await page.locator('[data-pending-row]').count(),
        expectedPath: null,
        pathname: null,
        requests: []
      }

      if (pendingStep.rows > 0) {
        pendingStep.expectedPath =
          (await pendingRow.getAttribute('data-pending-row')) === 'treatment-plans'
            ? '/treatment-plans'
            : '/quotations'
        const pendingMark = mark()
        await pendingRow
          .getByRole('button', { name: /^Ver/ })
          .first()
          .click({ timeout: 5000 })
          .catch(() => {})
        pendingStep.pathname = await navigate(pendingStep.expectedPath)
        pendingStep.requests = requests.slice(pendingMark)
      }

      row.steps.push(pendingStep)

      // 3. The cash card's CTA navigates to the cash desk.
      await page.goto(ROUTE)
      await page
        .locator('[data-dashboard-content]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

      const cashCard = page.locator('[data-stat-card="cash-balance"]').first()
      const cashButton = cashCard.getByRole('button', { name: 'Ir a Caja', exact: true })
      const cashStep = {
        step: 'ir-a-caja',
        cardVisible: await cashCard.isVisible().catch(() => false),
        pathname: null,
        requests: []
      }

      if (cashStep.cardVisible && (await cashButton.isVisible().catch(() => false))) {
        const cashMark = mark()
        await cashButton.click({ timeout: 5000 }).catch(() => {})
        cashStep.pathname = await navigate('/cash-register')
        cashStep.requests = requests.slice(cashMark)
      }

      row.steps.push(cashStep)
      row.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    } finally {
      row.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
      actionRows.push(row)
      audit('dashboard-actions.json', {
        route: ROUTE,
        slug: 'dashboard-actions',
        rows: actionRows,
        pageErrors: diagnostics.pageErrors,
        consoleErrors: diagnostics.consoleErrors
      })
    }

    expect(row.steps, `CTA steps for ${role}`).toHaveLength(3)
    expect(row.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- realtime transport unavailable --------------------------------------

test('@U2 dashboard · realtime unavailable keeps the dashboard usable', async ({
  page,
  request
}) => {
  test.setTimeout(60000)

  const role = 'odontologo'
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const messages = []
  page.on('console', message => messages.push({ type: message.type(), text: message.text() }))

  const evidence = {
    route: ROUTE,
    role,
    href: ROUTE,
    blocked: { supported: typeof page.routeWebSocket === 'function', ports: REVERB_PORTS },
    url: null,
    httpStatus: null,
    rendered: false,
    contentLength: 0,
    wsStatus: null,
    echoWarnings: { count: 0, samples: [] },
    snapshot: null,
    reducedMotion: 'reduce',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    await page.setViewportSize(DESKTOP)
    await page.emulateMedia({ reducedMotion: 'reduce' })

    if (evidence.blocked.supported) {
      await page
        .routeWebSocket(
          url => /^wss?:$/.test(url.protocol) && REVERB_PORTS.includes(url.port),
          socket => socket.close()
        )
        .catch(() => {
          evidence.blocked.supported = false
        })
    }

    await primeSession(page, session)
    const dashboardPayloads = [
      /\/api\/dashboard\/stats$/,
      /\/api\/dashboard\/appointments-today$/,
      /\/api\/dashboard\/upcoming$/,
      /\/api\/dashboard\/pending$/
    ].map(pattern =>
      page
        .waitForResponse(response => pattern.test(new URL(response.url()).pathname), {
          timeout: LOAD_TIMEOUT
        })
        .catch(() => null)
    )
    const response = await page.goto(ROUTE)
    evidence.httpStatus = response?.status() ?? null
    evidence.url = page.url()

    await Promise.all(dashboardPayloads)
    await page
      .locator('[data-dashboard-content]')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

    evidence.contentLength = await contentLength(page)
    evidence.rendered = evidence.contentLength > THRESHOLD
    evidence.snapshot = await readDashboard(page)
    evidence.wsStatus = (await page.getAttribute(WS_INDICATOR, 'aria-label')) ?? null

    const echo = messages.filter(message => /\[Echo\]/.test(message.text))
    evidence.echoWarnings = {
      count: echo.length,
      samples: echo.slice(0, 3).map(message => message.text)
    }

    evidence.shots.push(await shot(page, 'shots/u2-dashboard-no-reverb.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('dashboard-no-reverb.json', evidence)
  }

  expect(evidence.rendered, 'dashboard renders without the realtime transport').toBe(true)
  expect(evidence.wsStatus, 'WebSocket indicator present').not.toBeNull()
  expect(evidence.shots, 'capture of the degraded dashboard').toHaveLength(1)
  expect(evidence.pageErrors, 'page errors without the realtime transport').toEqual([])
})
