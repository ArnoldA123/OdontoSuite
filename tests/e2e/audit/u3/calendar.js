// Plan #13 (issue #366, U3) — the agenda surface: `/calendar`.
//
// Imported by `surface.spec.js`, so `--grep "@U3 calendar"` selects these rows
// and `--grep @U3` selects the whole session. Render rows go to `render/` with
// the shape `ui-pass.mjs` aggregates; the walkthrough rows keep their named
// files at the top of the session directory.
//
// This is measurement code: a product defect is recorded in the JSON with a
// verdict, never as a suite failure. Asserted here only that each probe ran to
// completion, that both captures exist and that no page error was raised.

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
  settleSurface,
  writeEvidence
} from '../harness.js'

import {
  MARKER,
  U3_EVIDENCE_DIR,
  ensureFixtures,
  fixtureSummary,
  lifecycleTarget
} from './fixtures.js'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ROUTE = '/calendar'
const THRESHOLD = 80
const LOAD_TIMEOUT = 20000
const SETTLE_TIMEOUT = 15000
const ACTION_TIMEOUT = 15000

const ROLES = Object.freeze(['administrador', 'odontologo', 'recepcionista', 'finanzas'])
const EXPECTATIONS = Object.freeze({
  administrador: 'admin',
  odontologo: 'owner',
  recepcionista: 'admin',
  finanzas: 'negative'
})

const APPOINTMENTS_PATH = '/api/appointments'
const APPOINTMENTS_QUERY = /\/api\/appointments\?/
const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U3_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U3_EVIDENCE_DIR })

const browserDate = page =>
  page.evaluate(() => {
    const date = new Date()
    const pad = value => String(value).padStart(2, '0')
    return {
      day: `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
      hour: date.getHours(),
      minute: date.getMinutes()
    }
  })

const localDateTime = date => {
  const pad = value => String(value).padStart(2, '0')
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  )
}

const listResponse = (page, timeout = ACTION_TIMEOUT) =>
  page
    .waitForResponse(
      response =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname === APPOINTMENTS_PATH,
      { timeout }
    )
    .catch(() => null)

const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live]'))
      .map(node => node.innerText.trim())
      .filter(Boolean)
      .join(' | ')
  )

const waitForToast = async (page, expected, timeout = 6000) => {
  await page
    .waitForFunction(
      message =>
        Array.from(document.querySelectorAll('[aria-live]')).some(node =>
          node.innerText.includes(message)
        ),
      expected,
      { timeout, polling: 100 }
    )
    .catch(() => {})

  return toastText(page)
}

const readCalendar = page =>
  page.evaluate(() => {
    const text = node => (node?.textContent ?? '').trim()
    const visible = node => {
      if (!node) return false
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }

    const headings = Array.from(document.querySelectorAll('h1')).map(node => ({
      text: text(node),
      visible: visible(node)
    }))
    const hero = document.querySelector('[data-calendar-shell="hero"]')
    const period = hero?.querySelector('.text-4xl')
    const switcher = document.querySelector('[data-calendar-view-switcher]')
    const live = document.querySelector('[aria-label^="Actualizaciones en tiempo real"]')
    const spinner = document.querySelector('.loading-spinner')

    return {
      url: window.location.pathname,
      h1: {
        count: headings.length,
        visibleCount: headings.filter(heading => heading.visible).length,
        texts: headings.map(heading => heading.text)
      },
      heroPresent: Boolean(hero),
      periodTitle: text(period),
      heroTitleTag: period?.tagName?.toLowerCase() ?? null,
      caption: text(hero?.querySelector('p.text-base')),
      viewSwitcher: Boolean(switcher),
      views: Array.from(document.querySelectorAll('[data-calendar-view]')).map(node => ({
        view: node.getAttribute('data-calendar-view'),
        pressed: node.getAttribute('aria-pressed') === 'true',
        label: text(node)
      })),
      nav: Array.from(document.querySelectorAll('[data-calendar-nav]')).map(node =>
        node.getAttribute('data-calendar-nav')
      ),
      chips: document.querySelectorAll('[data-calendar-appointment]').length,
      chipIds: Array.from(document.querySelectorAll('[data-calendar-appointment]')).map(node =>
        node.getAttribute('data-calendar-appointment')
      ),
      slots: document.querySelectorAll('[data-calendar-slot]').length,
      dropTargets: document.querySelectorAll('[data-calendar-drop-target]').length,
      monthDays: document.querySelectorAll('[data-calendar-month-day]').length,
      monthWeekdays: document.querySelectorAll('[data-calendar-month-weekday]').length,
      monthMore: document.querySelectorAll('[data-calendar-more]').length,
      empty: Boolean(document.querySelector('[data-calendar-empty]')),
      legend: Boolean(document.querySelector('[data-calendar-legend]')),
      loading: Boolean(spinner),
      loadingText: text(document.querySelector('.loading-spinner-container')),
      skeletons: document.querySelectorAll('[class*="skeleton"]').length,
      liveBadge: {
        visible: visible(live),
        text: text(live),
        ariaLabel: live?.getAttribute('aria-label') ?? null
      },
      createVisible: Boolean(document.querySelector('[data-calendar-action="new-appointment"]')),
      detailVisible: Boolean(document.querySelector('[data-calendar-detail]')),
      statusBadges: Array.from(
        document.querySelectorAll('[data-calendar-shell="grid"] [data-status]')
      )
        .map(node => node.getAttribute('data-status'))
        .filter(Boolean)
    }
  })

const settleCalendar = async page => {
  await page
    .locator('[data-calendar-shell="hero"]')
    .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    .catch(() => {})
  await page
    .locator('.loading-spinner')
    .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
    .catch(() => {})
  await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)
}

const switchView = async (page, view) => {
  const response = listResponse(page)
  await page
    .locator(`[data-calendar-view="${view}"]`)
    .click({ timeout: 5000 })
    .catch(() => {})
  await response
  await page
    .waitForFunction(
      target =>
        document.querySelector(`[data-calendar-view="${target}"]`)?.getAttribute('aria-pressed') ===
        'true',
      view,
      { timeout: 10000 }
    )
    .catch(() => {})
  await settleCalendar(page)
}

const openAppointmentDetail = async (page, appointmentId) => {
  const chip = page.locator(`[data-calendar-appointment="${appointmentId}"]`).first()

  if (!(await chip.isVisible().catch(() => false))) return false

  await chip.click({ timeout: 5000 }).catch(() => {})
  await page
    .locator('[data-calendar-detail]')
    .waitFor({ state: 'visible', timeout: 8000 })
    .catch(() => {})

  return page
    .locator('[data-calendar-detail]')
    .isVisible()
    .catch(() => false)
}

// The calendar paints `LoadingSpinner` + "Cargando agenda..." while the range
// loads (it uses no Skeleton). The probe delays the first range request so the
// state is observable instead of inferred from the source.
const installLoadingDelay = async (page, delayMs = 900) => {
  let delay = delayMs
  const handler = async route => {
    if (delay > 0) await new Promise(resolve => setTimeout(resolve, delay))
    await route.continue()
  }

  await page.route(APPOINTMENTS_QUERY, handler)

  return {
    stop: async () => {
      delay = 0
      await page.unroute(APPOINTMENTS_QUERY, handler).catch(() => {})
    }
  }
}

// --- layer 2: render per role ---------------------------------------------

for (const role of ROLES) {
  test(`@U3 calendar · render for ${role}`, async ({ page, request }) => {
    test.setTimeout(120000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const expectation = EXPECTATIONS[role]
    const evidence = {
      route: ROUTE,
      slug: 'calendar',
      role,
      expectation,
      href: ROUTE,
      url: null,
      httpStatus: null,
      rendered: false,
      contentLength: 0,
      loadingAffordance: null,
      firstPaint: null,
      snapshot: null,
      chipsPerView: {},
      horizontalOverflow: null,
      viewports: { desktop: { ...DESKTOP } },
      shots: [],
      consoleErrors: diagnostics.consoleErrors,
      pageErrors: diagnostics.pageErrors,
      failedRequests: { expected: [], unexpected: [] },
      fixtures: fixtureSummary(fixtures)
    }

    let delay = null

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)
      delay = await installLoadingDelay(page)

      const firstPayload = listResponse(page, LOAD_TIMEOUT)
      const response = await page.goto(ROUTE)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator('[data-calendar-shell="hero"]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await page
        .locator('.loading-spinner')
        .waitFor({ state: 'visible', timeout: 8000 })
        .catch(() => {})

      evidence.firstPaint = await readCalendar(page)
      evidence.loadingAffordance = {
        observed: evidence.firstPaint.loading ? 'LoadingSpinner' : 'not-captured',
        text: evidence.firstPaint.loadingText || null,
        skeleton: evidence.firstPaint.skeletons > 0,
        source: 'CalendarPage `<div v-if="loading">` + ui/LoadingSpinner'
      }

      await firstPayload
      await delay.stop()
      await settleCalendar(page)

      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD
      evidence.snapshot = await readCalendar(page)
      evidence.chipsPerView.week = evidence.snapshot.chips

      for (const view of ['day', 'month']) {
        await switchView(page, view)
        const snapshot = await readCalendar(page)
        evidence.chipsPerView[view] = snapshot.chips
        if (view === 'month') evidence.monthCells = snapshot.monthDays
      }

      await switchView(page, 'week')
      evidence.snapshotAfterViews = await readCalendar(page)
      evidence.shots.push(await shot(page, `shots/u3-calendar-${role}-desktop.png`))

      await page.setViewportSize(MOBILE)
      const mobilePayload = listResponse(page, LOAD_TIMEOUT)
      await page.reload()
      await mobilePayload
      await settleCalendar(page)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }
      evidence.mobileSnapshot = await readCalendar(page)
      evidence.mobileContentLength = await contentLength(page)

      // #397: at 390px the agenda now opens on a readable view (Day). Measure
      // what the default view actually is, then switch to Week on purpose: the
      // grid is still a choice, and the acceptance measures its column width and
      // the chip text when it is chosen.
      const measureMobileLayout = () =>
        page.evaluate(() => {
          const pressed = document.querySelector('[data-calendar-view][aria-pressed="true"]')
          const chips = Array.from(document.querySelectorAll('[data-calendar-appointment]'))
          const dayCells = Array.from(document.querySelectorAll('[data-calendar-slot-day]'))
          const scroll = document.querySelector('[data-calendar-week-scroll]')
          const chipTime = document.querySelector('[data-calendar-chip-time]')
          const round = value => Math.round(value)

          return {
            view: pressed?.textContent.trim() ?? null,
            viewportWidth: window.innerWidth,
            dayColumnWidth: dayCells[0] ? round(dayCells[0].getBoundingClientRect().width) : null,
            weekScroll: scroll
              ? {
                  scrollWidth: round(scroll.scrollWidth),
                  clientWidth: round(scroll.clientWidth),
                  scrolls: scroll.scrollWidth > scroll.clientWidth + 1
                }
              : null,
            chipTime: chipTime
              ? {
                  text: (chipTime.innerText ?? '').replace(/\s+/g, ' ').trim(),
                  width: round(chipTime.getBoundingClientRect().width),
                  clipped: chipTime.scrollWidth > chipTime.clientWidth + 1
                }
              : null,
            chips: chips.slice(0, 4).map(chip => {
              const full = chip.getAttribute('title') ?? ''
              const visible = (chip.innerText ?? '').replace(/\s+/g, ' ').trim()
              const box = chip.getBoundingClientRect()
              return {
                visibleText: visible,
                title: full,
                width: round(box.width),
                truncated: Boolean(full) && !full.startsWith(visible)
              }
            })
          }
        })

      evidence.mobileLayout = await measureMobileLayout()

      const weekButton = page.locator('[data-calendar-view="week"]')
      if ((await weekButton.count().catch(() => 0)) > 0) {
        await weekButton.first().click({ force: true })
        await settleCalendar(page)
        evidence.mobileWeekLayout = await measureMobileLayout()
      }

      evidence.shots.push(await shot(page, `shots/u3-calendar-${role}-mobile.png`))
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
    } finally {
      await delay?.stop().catch(() => {})
      evidence.pageErrors = diagnostics.pageErrors
      evidence.consoleErrors = diagnostics.consoleErrors
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
      audit(`render/calendar-${role}.json`, evidence)
    }

    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
    expect(evidence.horizontalOverflow, `document overflows at 390px for ${role}`).toBe(false)

    // #397 acceptance: at 390px the week grid keeps a readable column and the
    // chip text is not reduced to a single character. Measured, not assumed.
    if (evidence.mobileWeekLayout) {
      expect(
        evidence.mobileWeekLayout.dayColumnWidth,
        `week column width at 390px for ${role}`
      ).toBeGreaterThanOrEqual(96)
      expect(
        evidence.mobileWeekLayout.chipTime?.text?.length ?? 0,
        `chip time text at 390px for ${role}`
      ).toBeGreaterThan(1)
      expect(
        evidence.mobileWeekLayout.chipTime?.clipped,
        `chip time clipped at 390px for ${role}`
      ).toBe(false)
      expect(
        evidence.mobileWeekLayout.weekScroll?.scrolls,
        `week grid scrolls instead of squeezing for ${role}`
      ).toBe(true)
    }
  })
}

// --- view switcher and date navigation ------------------------------------

test('@U3 calendar · vistas y navegación', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    steps: [],
    titles: {},
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  const record = (step, verdict, observed) => {
    evidence.steps.push({ step, verdict, ...observed })
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    const initial = await readCalendar(page)
    evidence.titles.initial = initial.periodTitle
    evidence.initial = initial
    record('initial', initial.viewSwitcher ? 'OK' : 'DEFECTO', {
      view: initial.views.find(view => view.pressed)?.view ?? null,
      title: initial.periodTitle,
      chips: initial.chips,
      switcher: initial.viewSwitcher
    })

    await switchView(page, 'day')
    const day = await readCalendar(page)
    evidence.titles.day = day.periodTitle
    evidence.shots.push(await shot(page, 'shots/u3-calendar-view-day.png'))
    record(
      'vista-día',
      day.slots === 16 && day.periodTitle !== initial.periodTitle ? 'OK' : 'DEFECTO',
      {
        slots: day.slots,
        chips: day.chips,
        dropTargets: day.dropTargets,
        title: day.periodTitle
      }
    )

    await switchView(page, 'week')
    const week = await readCalendar(page)
    evidence.titles.week = week.periodTitle
    evidence.shots.push(await shot(page, 'shots/u3-calendar-view-week.png'))
    record('vista-semana', week.slots === 112 ? 'OK' : 'DEFECTO', {
      slots: week.slots,
      chips: week.chips,
      dropTargets: week.dropTargets,
      title: week.periodTitle
    })

    await switchView(page, 'month')
    const month = await readCalendar(page)
    evidence.titles.month = month.periodTitle
    evidence.shots.push(await shot(page, 'shots/u3-calendar-view-month.png'))
    record('vista-mes', month.monthDays === 42 && month.monthWeekdays === 7 ? 'OK' : 'DEFECTO', {
      monthDays: month.monthDays,
      monthWeekdays: month.monthWeekdays,
      chips: month.chips,
      more: month.monthMore,
      title: month.periodTitle
    })

    const prevPayload = listResponse(page)
    await page.locator('[data-calendar-nav="prev"]').click({ timeout: 5000 })
    await prevPayload
    await settleCalendar(page)
    const previous = await readCalendar(page)
    evidence.titles.previous = previous.periodTitle
    record('nav-prev', previous.periodTitle !== month.periodTitle ? 'OK' : 'DEFECTO', {
      title: previous.periodTitle,
      from: month.periodTitle
    })

    const nextPayload = listResponse(page)
    await page.locator('[data-calendar-nav="next"]').click({ timeout: 5000 })
    await nextPayload
    await settleCalendar(page)
    const next = await readCalendar(page)
    evidence.titles.next = next.periodTitle
    record('nav-next', next.periodTitle === month.periodTitle ? 'OK' : 'DEFECTO', {
      title: next.periodTitle,
      expected: month.periodTitle
    })

    const todayPayload = listResponse(page)
    await page.locator('[data-calendar-nav="today"]').click({ timeout: 5000 })
    await todayPayload
    await settleCalendar(page)
    const today = await readCalendar(page)
    evidence.titles.today = today.periodTitle
    record('nav-hoy', today.periodTitle === month.periodTitle ? 'OK' : 'DEFECTO', {
      title: today.periodTitle,
      expected: month.periodTitle
    })

    evidence.shots.push(await shot(page, 'shots/u3-calendar-view-today.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-views.json', evidence)
  }

  expect(evidence.steps, 'navigation steps attempted').toHaveLength(7)
  expect(evidence.shots, 'captures of the views').toHaveLength(4)
  expect(evidence.pageErrors, 'page errors during the views probe').toEqual([])
})

// --- filters: the issue expects them, the page has none -------------------

test('@U3 calendar · filtros', async ({ page, request }) => {
  test.setTimeout(60000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    expected: 'filtros por profesional, sillón y sede (issue #366 S3)',
    selectors: [],
    controlTexts: [],
    observed: 'absent',
    verdict: 'DEFECTO',
    severity: 'crit:media',
    vocabulary: ['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO', 'absent'],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  const selectors = [
    '[data-calendar-filter]',
    '[data-calendar-filters]',
    '[data-calendar-filter="professional"]',
    '[data-calendar-filter="chair"]',
    '[data-calendar-filter="branch"]',
    'main select',
    'main input[type="search"]',
    'main [role="combobox"]',
    'main [data-filter]'
  ]

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    for (const selector of selectors) {
      evidence.selectors.push({
        selector,
        count: await page
          .locator(selector)
          .count()
          .catch(() => 0)
      })
    }

    // The appointment chips print the professional and the chair (that is the
    // chip's own meta line), so a text search over every control would read the
    // agenda itself as a filter. Exclude the chip subtree and keep only
    // controls a person could operate as a filter.
    evidence.controlTexts = await page.evaluate(() =>
      Array.from(document.querySelectorAll('main button, main label, main select, main input'))
        .filter(node => !node.closest('[data-calendar-appointment], [data-calendar-detail]'))
        .map(node => (node.innerText ?? node.getAttribute('placeholder') ?? '').trim())
        .filter(text => /profesional|sill[oó]n|sede/i.test(text))
    )

    evidence.present =
      evidence.selectors.some(row => row.count > 0) || evidence.controlTexts.length > 0
    evidence.observed = evidence.present ? 'present' : 'absent'
    evidence.verdict = evidence.present ? 'OK' : 'DEFECTO'
    evidence.shots.push(await shot(page, 'shots/u3-calendar-filters.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-filters.json', evidence)
  }

  expect(evidence.selectors, 'selectors checked').toHaveLength(selectors.length)
  expect(evidence.pageErrors, 'page errors during the filters probe').toEqual([])
})

// --- load failure: what the agenda says when the list cannot be read ------

test('@U3 calendar · error de carga', async ({ page, request }) => {
  test.setTimeout(90000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    aborted: false,
    toast: '',
    errorAffordance: null,
    emptyState: null,
    verdict: null,
    recovered: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.route(APPOINTMENTS_QUERY, route => route.abort())
    evidence.aborted = true

    await page.goto(ROUTE)
    await page
      .locator('[data-calendar-shell="hero"]')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await page
      .locator('.loading-spinner')
      .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

    evidence.toast = await toastText(page)
    evidence.errorAffordance = await page.evaluate(() => {
      const nodes = Array.from(
        document.querySelectorAll(
          '[data-calendar-error], [data-calendar-retry], [data-calendar-load-error], main [role="alert"]'
        )
      ).filter(node => !node.closest('[data-calendar-detail]'))
      return {
        count: nodes.length,
        retry: document.querySelectorAll('[data-calendar-retry]').length,
        texts: nodes.map(node => (node.innerText ?? '').trim()).filter(Boolean)
      }
    })
    evidence.emptyState = await page.evaluate(() => {
      const empty = document.querySelector('[data-calendar-empty]')
      return empty ? { visible: true, text: (empty.innerText ?? '').trim() } : { visible: false }
    })
    evidence.shots.push(await shot(page, 'shots/u3-calendar-error.png'))

    evidence.verdict =
      evidence.errorAffordance.count > 0 && evidence.errorAffordance.retry > 0 ? 'OK' : 'DEFECTO'

    await page.unroute(APPOINTMENTS_QUERY)
    const retry = page.locator('[data-calendar-retry]').first()

    if (await retry.isVisible().catch(() => false)) {
      await retry.click({ timeout: 5000 }).catch(() => {})
    } else {
      // No retry to press: the only way back is to ask for another period.
      await page
        .locator('[data-calendar-view="day"]')
        .click({ timeout: 5000 })
        .catch(() => {})
    }

    await listResponse(page)
    await settleSurface(page, THRESHOLD, 8000)
    const chips = await page.locator('[data-calendar-appointment]').count()
    evidence.recovered = {
      chipsAfter: chips,
      emptyStateAfter: (await page.locator('[data-calendar-empty]').count()) > 0
    }
    evidence.shots.push(await shot(page, 'shots/u3-calendar-error-after.png'))

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    const probeAborts = evidence.failedRequests.unexpected.filter(
      entry => entry.status == null && APPOINTMENTS_QUERY.test(entry.url ?? '')
    )
    evidence.failedRequests = {
      expected: [
        ...evidence.failedRequests.expected,
        ...probeAborts.map(entry => ({ ...entry, reason: 'aborted by the load-failure probe' }))
      ],
      unexpected: evidence.failedRequests.unexpected.filter(entry => !probeAborts.includes(entry))
    }
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    audit('calendar-error.json', evidence)
  }

  expect(evidence.aborted, 'appointments list blocked').toBe(true)
  expect(evidence.toast, 'the failure spoke').toContain('Error al cargar las citas')
  expect(evidence.shots, 'captures of the load-failure probe').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the load-failure probe').toEqual([])
})

// --- create through the interface -----------------------------------------

const chooseSelect = async (page, dialog, index, term, expectedText) => {
  const trigger = dialog.locator('button[aria-haspopup="true"]').nth(index)
  await trigger.click({ timeout: 5000 })

  const listbox = page.locator('[role="listbox"]:visible').last()
  await listbox.waitFor({ state: 'visible', timeout: 5000 })

  const search = listbox.locator('input').first()
  if ((await search.count()) > 0) {
    await search.fill(term)
    await page
      .waitForFunction(
        text =>
          Array.from(document.querySelectorAll('[role="listbox"] button')).some(node =>
            node.innerText.includes(text)
          ),
        expectedText,
        { timeout: 5000, polling: 100 }
      )
      .catch(() => {})
  }

  await listbox.locator('button').filter({ hasText: expectedText }).first().click({ timeout: 5000 })
}

const localDay = date => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const futureSlot = (fixtures, appointmentMinutes = 15) => {
  const now = new Date()
  const offsets = [165, 135, 105, 75, 45, 30]

  for (const offset of offsets) {
    const candidate = new Date(now.getTime() + offset * 60000)
    if (localDay(candidate) !== fixtures.today.value) continue
    if (candidate.getHours() > 20) continue

    const start = candidate.getTime()
    const end = start + appointmentMinutes * 60000
    const overlapsFixture = (fixtures.appointments?.rows ?? []).some(row => {
      const rowStart = new Date(row.scheduledAt).getTime()
      const rowEnd = rowStart + (row.durationMinutes ?? 15) * 60000
      return start < rowEnd && rowStart < end
    })

    if (!overlapsFixture) return candidate
  }

  return null
}

test('@U3 calendar · crear cita desde la interfaz', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const pageErrorDetails = []
  page.on('pageerror', error => {
    pageErrorDetails.push(error.message || error.stack || String(error))
  })
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    validation: [],
    creation: null,
    registered: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!fixtures.appointments?.ok) {
    const reason = fixtures.appointments?.reason ?? 'appointment fixtures unavailable'
    audit('calendar-create.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const slot = futureSlot(fixtures)

  if (!slot) {
    const reason = 'no free future slot left today for the professional'
    audit('calendar-create.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const [patient] = fixtures.patients
  const { professional, appointmentType: type } = fixtures
  const [chair] = fixtures.chairs

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    await page.locator('[data-calendar-action="new-appointment"]').click({ timeout: 5000 })
    const dialog = page.locator('[role="dialog"]:visible').last()
    await dialog.waitFor({ state: 'visible', timeout: 8000 })
    await dialog
      .locator('text=Cargando datos...')
      .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    evidence.shots.push(await shot(page, 'shots/u3-calendar-create-empty.png'))

    const submit = () => dialog.getByRole('button', { name: 'Crear Cita', exact: true })
    const recordValidation = async step => {
      await submit().click({ timeout: 5000 })
      const observed = await waitForToast(page, 'Debes', 3000)
      evidence.validation.push({ step, toast: observed })
    }

    // The form validates one missing field per submit, so the sequence records
    // the whole chain instead of only the first message.
    await recordValidation('empty')
    await chooseSelect(page, dialog, 0, patient.name.split(' ')[0], patient.name)
    await recordValidation('after-patient')
    await chooseSelect(page, dialog, 1, professional.name.split(' ')[0], professional.name)
    await recordValidation('after-professional')

    await dialog.locator('input[type="datetime-local"]').first().fill(localDateTime(slot))
    await recordValidation('after-date')

    await dialog.locator('input[type="number"]').first().fill('15')
    await recordValidation('after-duration')

    await chooseSelect(page, dialog, 2, type.name, type.name)
    await recordValidation('after-type')

    await chooseSelect(page, dialog, 3, chair.name, chair.name)

    const postResponse = page
      .waitForResponse(
        response =>
          response.request().method() === 'POST' &&
          new URL(response.url()).pathname === APPOINTMENTS_PATH,
        { timeout: ACTION_TIMEOUT }
      )
      .catch(() => null)
    const reload = listResponse(page, ACTION_TIMEOUT)

    await submit().click({ timeout: 5000 })
    const created = await postResponse
    const createdBody = await created?.json().catch(() => null)
    const appointmentId = createdBody?.data?.id ?? null
    const createdToast = await waitForToast(page, 'Cita creada exitosamente', 6000)
    const closed = await dialog
      .waitFor({ state: 'hidden', timeout: 8000 })
      .then(() => true)
      .catch(() => false)

    await reload

    let chipVisible = await page
      .waitForFunction(
        id => Boolean(document.querySelector(`[data-calendar-appointment="${id}"]`)),
        appointmentId,
        { timeout: 12000, polling: 150 }
      )
      .then(() => true)
      .catch(() => false)

    if (!chipVisible) {
      await switchView(page, 'day')
      chipVisible = await page
        .waitForFunction(
          id => Boolean(document.querySelector(`[data-calendar-appointment="${id}"]`)),
          appointmentId,
          { timeout: 8000, polling: 150 }
        )
        .then(() => true)
        .catch(() => false)
    }

    evidence.creation = {
      appointmentId,
      patient: patient.name,
      professional: professional.name,
      type: type.name,
      chair: chair.name,
      scheduledAt: localDateTime(slot),
      postStatus: created?.status() ?? null,
      responseStatus: createdBody?.data?.status ?? null,
      toast: createdToast,
      modalClosed: closed,
      chipVisible,
      verdict:
        created?.status() === 201 && closed && chipVisible && appointmentId ? 'OK' : 'DEFECTO'
    }

    if (appointmentId) {
      const registered = (await import('./fixtures.js')).registerLifecycle(
        appointmentId,
        patient.name,
        {
          patientId: patient.id,
          professionalId: professional.id,
          chairId: chair.id,
          scheduledAt: localDateTime(slot),
          status: createdBody?.data?.status ?? 'scheduled'
        }
      )

      evidence.registered = { id: registered.id, patientName: registered.patientName }
    }

    evidence.shots.push(await shot(page, 'shots/u3-calendar-create-chip.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.pageErrorDetails = pageErrorDetails
    if (evidence.pageErrors.length > 0) {
      evidence.pageErrorCause =
        'unhandled rejections while NewAppointmentModal mounts ProcedureQuickPicker; the favorites request answers 403 for administrador (manage.procedure_favorite is clinical-only)'
      evidence.sessionCriterion = { id: 'zero-pageerror', ok: false }
    }
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-create.json', evidence)
  }

  expect(evidence.validation.length, 'validation steps recorded').toBe(6)
  expect(evidence.creation?.appointmentId, 'appointment created through the UI').toBeTruthy()
  // The two unhandled rejections this flow produces are a measured product
  // defect (published issue), not an instrument failure: the row records them
  // with their cause so the session's zero-pageerror criterion can be scored.
  expect(evidence.shots.length, 'captures of the create flow').toBeGreaterThanOrEqual(2)
})

// --- reschedule by drag ---------------------------------------------------

const apiCall = async (request, session, method, path, data) => {
  const options = {
    method,
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
    timeout: ACTION_TIMEOUT
  }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const body = await response.json().catch(() => null)
    return { status: response.status(), ok: response.ok(), body }
  } catch (thrown) {
    return { status: null, ok: false, body: null, error: String(thrown?.message ?? thrown) }
  }
}

const dragBetween = async (page, sourceBox, targetBox) => {
  await page.mouse.move(sourceBox.x + sourceBox.width / 2, sourceBox.y + sourceBox.height / 2)
  await page.mouse.down()
  // Above DRAG_THRESHOLD_PX (6) so pointerdown becomes a drag, not a click.
  await page.mouse.move(
    sourceBox.x + sourceBox.width / 2 + 20,
    sourceBox.y + sourceBox.height / 2 + 20
  )
  await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height / 2, {
    steps: 8
  })
  await page.mouse.up()
}

test('@U3 calendar · reprogramar por drag', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const appointment = fixtures.appointments?.rows?.[0]
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    appointmentId: appointment?.id ?? null,
    target: null,
    first: null,
    sameSlot: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!fixtures.appointments?.ok || !appointment) {
    const reason = fixtures.appointments?.reason ?? 'scheduled fixture unavailable'
    audit('calendar-drag.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const today = await browserDate(page)
  const lifecycle = lifecycleTarget()
  const booked = [
    ...(fixtures.appointments?.rows ?? []).map(row => {
      const start = new Date(row.scheduledAt).getTime()
      return { start, end: start + 15 * 60000 }
    }),
    ...(lifecycle?.scheduledAt
      ? [
          {
            start: new Date(lifecycle.scheduledAt).getTime(),
            end: new Date(lifecycle.scheduledAt).getTime() + 15 * 60000
          }
        ]
      : [])
  ]
  const candidateHours = []

  for (let hour = today.hour + 2; hour <= 20; hour += 1) {
    const start = new Date(`${today.day}T${String(hour).padStart(2, '0')}:00:00`).getTime()
    const end = start + 15 * 60000
    if (!booked.some(interval => start < interval.end && interval.start < end)) {
      candidateHours.push(hour)
    }
  }

  if (candidateHours.length === 0) {
    const reason = `no free future slot left in the day view (now ${today.hour}:${today.minute})`
    audit('calendar-drag.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  evidence.target = { day: today.day, key: null, candidates: candidateHours }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)
    await switchView(page, 'day')

    const chip = page.locator(`[data-calendar-draggable="${appointment.id}"]`).first()

    if (!(await chip.isVisible().catch(() => false))) {
      const reason = 'the scheduled fixture is not on the browser day, so the drag has no source'
      audit('calendar-drag.json', { ...evidence, reason, verdict: 'NO PROBADO' })
      test.skip(true, reason)
      return
    }

    const sourceSlotBefore = await chip
      .evaluate(node =>
        node.closest('[data-calendar-drop-target]')?.getAttribute('data-calendar-drop-target')
      )
      .catch(() => null)
    evidence.shots.push(await shot(page, 'shots/u3-calendar-drag-before.png'))

    const attempts = []
    let success = null

    // A soft-deleted appointment still occupies its unique slot, so the API
    // reports the slot as free while the DB refuses the move with 500. The
    // probe falls forward through the rendered hours until one accepts, and
    // records every rejected candidate with its status and toast.
    for (const hour of candidateHours) {
      const targetKey = `${today.day}-${hour}`
      const source = page.locator(`[data-calendar-draggable="${appointment.id}"]`).first()
      const target = page.locator(`[data-calendar-drop-target="${targetKey}"]`)

      if (!(await target.isVisible().catch(() => false))) {
        attempts.push({ targetKey, skipped: 'drop target not rendered' })
        continue
      }

      const sourceBox = await source.boundingBox()
      const targetBox = await target.boundingBox()

      if (!sourceBox || !targetBox) {
        attempts.push({ targetKey, skipped: 'source or target without a bounding box' })
        continue
      }

      const patch = page
        .waitForResponse(
          response =>
            response.request().method() === 'PATCH' &&
            new URL(response.url()).pathname.endsWith(
              `/api/appointments/${appointment.id}/reschedule`
            ),
          { timeout: ACTION_TIMEOUT }
        )
        .catch(() => null)

      await dragBetween(page, sourceBox, targetBox)
      const response = await patch
      const body = await response?.json().catch(() => null)

      await page
        .waitForFunction(
          () =>
            Array.from(document.querySelectorAll('[aria-live]')).some(node =>
              /Cita reprogramada|Error al reprogramar|conflicto|No se puede mover/i.test(
                node.innerText
              )
            ),
          null,
          { timeout: 4000, polling: 100 }
        )
        .catch(() => {})
      const toast = await toastText(page)

      const landed = await page
        .waitForFunction(
          ({ id, key }) =>
            document
              .querySelector(`[data-calendar-appointment="${id}"]`)
              ?.closest('[data-calendar-drop-target]')
              ?.getAttribute('data-calendar-drop-target') === key,
          { id: appointment.id, key: targetKey },
          { timeout: 8000, polling: 150 }
        )
        .then(() => true)
        .catch(() => false)

      attempts.push({
        targetKey,
        patchStatus: response?.status() ?? null,
        scheduledAt: body?.data?.scheduled_at ?? null,
        toast,
        landed
      })

      if (response?.status() === 200 && landed) {
        success = {
          sourceSlot: sourceSlotBefore,
          targetSlot: targetKey,
          patchStatus: response.status(),
          scheduledAt: body?.data?.scheduled_at ?? null,
          toast,
          landed,
          landedSlot: targetKey,
          verdict: 'OK'
        }
        break
      }

      // The component rolls the optimistic move back; wait for the chip before
      // the next candidate.
      await source.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})
    }

    evidence.attempts = attempts
    evidence.target.key = success?.targetSlot ?? null
    evidence.first = success ?? {
      sourceSlot: sourceSlotBefore,
      targetSlot: null,
      patchStatus: attempts.at(-1)?.patchStatus ?? null,
      toast: attempts.at(-1)?.toast ?? '',
      landed: false,
      landedSlot: null,
      verdict: 'NO PROBADO',
      reason: 'every rendered candidate slot was rejected'
    }

    const sourceAfter = await page
      .locator(`[data-calendar-appointment="${appointment.id}"]`)
      .first()
      .boundingBox()
    const currentSlot = await page.evaluate(
      id =>
        document
          .querySelector(`[data-calendar-appointment="${id}"]`)
          ?.closest('[data-calendar-drop-target]')
          ?.getAttribute('data-calendar-drop-target') ?? null,
      appointment.id
    )
    const sameTarget = currentSlot
      ? page.locator(`[data-calendar-drop-target="${currentSlot}"]`)
      : null
    const targetAfter = sameTarget ? await sameTarget.boundingBox() : null

    if (sourceAfter && targetAfter) {
      const sameSlot = page
        .waitForResponse(
          response =>
            response.request().method() === 'PATCH' &&
            new URL(response.url()).pathname.endsWith(
              `/api/appointments/${appointment.id}/reschedule`
            ),
          { timeout: 1500 }
        )
        .catch(() => null)

      await dragBetween(page, sourceAfter, targetAfter)
      const sameResponse = await sameSlot
      const sameToast = await toastText(page)

      evidence.sameSlot = {
        key: currentSlot,
        requestStatus: sameResponse?.status() ?? null,
        toast: sameToast,
        verdict: sameResponse === null ? 'OK' : 'DEFECTO',
        note: 'own-slot drop is a declared silent no-op (T5 contract)'
      }
    }

    evidence.shots.push(await shot(page, 'shots/u3-calendar-drag-after.png'))

    // --- released slot: a cancelled appointment keeps its unique slot -------
    // `AppointmentRepository::findConflicts` filters out cancelled rows, so the
    // API reports the slot as free, but `unique_user_time_slot` still covers
    // them and the write fails at the database. The happy path above already
    // proved the drag sends this exact PATCH; here the probe owns both rows
    // (it creates and cancels its own appointment) and calls the same door with
    // the same payload, so the measurement does not depend on a previous run.
    const currentSlotAfter = await page.evaluate(
      id =>
        document
          .querySelector(`[data-calendar-appointment="${id}"]`)
          ?.closest('[data-calendar-drop-target]')
          ?.getAttribute('data-calendar-drop-target') ?? null,
      appointment.id
    )
    const releasedHour = candidateHours.find(hour => `${today.day}-${hour}` !== currentSlotAfter)

    if (releasedHour != null) {
      const key = `${today.day}-${releasedHour}`
      const scheduledAt = `${today.day}T${String(releasedHour).padStart(2, '0')}:00:00`
      const [patient] = fixtures.patients ?? []
      const created = await apiCall(request, session, 'POST', '/api/appointments', {
        patient_id: patient?.id ?? null,
        user_id: fixtures.professional?.id ?? null,
        dental_chair_id: fixtures.chairs?.[0]?.id ?? null,
        appointment_type_id: fixtures.appointmentType?.id ?? null,
        scheduled_at: scheduledAt,
        duration_minutes: 15,
        notes: `${MARKER} released slot`
      })
      const releasedId = created.body?.data?.id ?? null

      if (created.ok && releasedId) {
        const cancelled = await apiCall(
          request,
          session,
          'PATCH',
          `/api/appointments/${releasedId}/status`,
          { status: 'cancelled' }
        )
        const reschedule = await apiCall(
          request,
          session,
          'PATCH',
          `/api/appointments/${appointment.id}/reschedule`,
          { scheduled_at: scheduledAt, duration_minutes: 15 }
        )

        evidence.releasedSlot = {
          probeAppointmentId: releasedId,
          slot: key,
          createStatus: created.status,
          cancelStatus: cancelled.status,
          patchStatus: reschedule.status,
          patchMessage: reschedule.body?.message ?? null,
          patchConflict: reschedule.body?.conflict ?? null,
          verdict: reschedule.status === 200 ? 'OK' : 'DEFECTO',
          note: 'el horario liberado por una cita cancelada debería aceptar la reprogramación; el pre-chequeo excluye las canceladas y el índice único no'
        }

        await apiCall(request, session, 'DELETE', `/api/appointments/${releasedId}`)
      } else {
        evidence.releasedSlot = {
          slot: key,
          createStatus: created.status,
          skipped: 'the probe could not own a slot for the released-slot measurement'
        }
      }
    }

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-drag.json', evidence)
  }

  expect(evidence.first, 'first drag recorded').not.toBeNull()
  expect(evidence.shots, 'captures of the drag').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the drag probe').toEqual([])
})

// --- cancel without confirmation or motive --------------------------------

test('@U3 calendar · cancelar cita', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const appointment = fixtures.appointments?.rows?.[0]
  const evidence = {
    route: ROUTE,
    role: 'administrador',
    appointmentId: appointment?.id ?? null,
    confirmation: null,
    motiveField: null,
    patch: null,
    statusAfter: null,
    chipStatus: null,
    reminders: null,
    verdict: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!fixtures.appointments?.ok || !appointment) {
    const reason = fixtures.appointments?.reason ?? 'scheduled fixture unavailable'
    audit('calendar-cancel.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const reminders = async () => {
    const response = await request.get(
      `${BASE_URL}/api/reminders?appointment_id=${appointment.id}&per_page=100`,
      {
        headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' }
      }
    )
    const body = await response.json().catch(() => null)
    return {
      status: response.status(),
      rows: (body?.data ?? []).map(row => ({ id: row.id, status: row.status }))
    }
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    evidence.reminders = { before: await reminders() }

    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    const opened = await openAppointmentDetail(page, appointment.id)

    if (!opened) {
      const reason = 'the fixture chip did not open the detail modal'
      audit('calendar-cancel.json', { ...evidence, reason, verdict: 'NO PROBADO' })
      test.skip(true, reason)
      return
    }

    const detailBefore = await page.evaluate(() => ({
      status: document
        .querySelector('[data-calendar-detail] [data-status]')
        ?.getAttribute('data-status'),
      buttons: Array.from(document.querySelectorAll('[data-calendar-detail] button')).map(button =>
        button.innerText.trim()
      )
    }))
    evidence.shots.push(await shot(page, 'shots/u3-calendar-cancel-detail.png'))

    const beforeMotive = page
      .waitForResponse(
        response =>
          response.request().method() === 'PATCH' &&
          new URL(response.url()).pathname.endsWith(`/api/appointments/${appointment.id}/status`),
        { timeout: 2500 }
      )
      .catch(() => null)

    await page.locator('[data-calendar-action="status-cancelled"]').click({ timeout: 5000 })

    // #394: cancelar abre una confirmación con motivo obligatorio; el PATCH no
    // sale hasta que el motivo está escrito y confirmado.
    const dialog = page.locator('[data-calendar-cancel]')
    await dialog.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {})
    const confirmation = await dialog.isVisible().catch(() => false)

    const earlyPatch = await beforeMotive
    const motiveField = (await page.locator('[data-calendar-cancel-reason] textarea').count()) > 0

    // The motive field is `required`, so Chromium enforces the empty submit with
    // its own validation before the component's message renders. Both paths
    // block the write; what the probe guarantees is that nothing was sent and
    // the dialog stayed open with the act intact.
    await page
      .locator('[data-calendar-cancel-confirm]')
      .click({ timeout: 5000 })
      .catch(() => {})
    const nativeValidation = await page
      .locator('[data-calendar-cancel-reason] textarea')
      .evaluate(field => ({
        valueMissing: Boolean(field.validity?.valueMissing),
        message: field.validationMessage ?? ''
      }))
      .catch(() => null)
    const dialogAfterEmptySubmit = await dialog.isVisible().catch(() => false)

    const reasonText = 'El paciente no puede asistir; recepción reprograma.'
    await page
      .locator('[data-calendar-cancel-reason] textarea')
      .fill(reasonText, { timeout: 5000 })
      .catch(() => {})

    const confirmed = page
      .waitForResponse(
        response =>
          response.request().method() === 'PATCH' &&
          new URL(response.url()).pathname.endsWith(`/api/appointments/${appointment.id}/status`),
        { timeout: ACTION_TIMEOUT }
      )
      .catch(() => null)

    await page.locator('[data-calendar-cancel-confirm]').click({ timeout: 5000 })

    const response = await confirmed
    const body = await response?.json().catch(() => null)
    const requestBody = response?.request().postDataJSON?.() ?? null
    const toast = await waitForToast(page, 'Cita cancelada', 8000)

    evidence.confirmation = confirmation
    evidence.motiveField = motiveField
    evidence.nativeValidation = nativeValidation
    evidence.dialogKeptOnEmptySubmit = dialogAfterEmptySubmit
    evidence.patchBeforeMotive = earlyPatch?.status() ?? null
    evidence.patch = {
      status: response?.status() ?? null,
      responseStatus: body?.data?.status ?? null,
      reason: requestBody?.cancellation_reason ?? null,
      remindersCancelled: body?.meta?.reminders_cancelled ?? null,
      metaKeys: Object.keys(body?.meta ?? {}),
      toast
    }
    evidence.detailBefore = detailBefore
    evidence.dialogClosed = await dialog
      .waitFor({ state: 'hidden', timeout: 5000 })
      .then(() => true)
      .catch(() => false)

    const statusResponse = await request.get(`${BASE_URL}/api/appointments/${appointment.id}`, {
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' }
    })
    const statusBody = await statusResponse.json().catch(() => null)
    evidence.statusAfter = statusBody?.data?.status ?? null
    evidence.cancellationReason = statusBody?.data?.cancellation_reason ?? null

    await page
      .waitForFunction(
        id => Boolean(document.querySelector(`[data-calendar-appointment="${id}"]`)),
        appointment.id,
        { timeout: 10000, polling: 150 }
      )
      .catch(() => {})
    evidence.chip = await page.evaluate(id => {
      const chip = document.querySelector(`[data-calendar-appointment="${id}"]`)
      if (!chip) return null
      return {
        text: chip.innerText.trim(),
        classes: chip.className,
        statusHook: Boolean(chip.querySelector('[data-status]'))
      }
    }, appointment.id)
    // The chip's status badge does not forward the `data-status` hook (the
    // legend's badges do); the measured status is the API one below.
    evidence.chipStatus = evidence.chip?.statusHook
      ? await page.evaluate(
          id =>
            document
              .querySelector(`[data-calendar-appointment="${id}"] [data-status]`)
              ?.getAttribute('data-status') ?? null,
          appointment.id
        )
      : null

    evidence.reminders.after = await reminders()
    evidence.reminders.beforeCount = evidence.reminders.before.rows.length
    evidence.reminders.afterCount = evidence.reminders.after.rows.length

    evidence.verdict =
      evidence.patch.status === 200 &&
      evidence.statusAfter === 'cancelled' &&
      evidence.cancellationReason === reasonText &&
      confirmation &&
      motiveField &&
      evidence.dialogKeptOnEmptySubmit &&
      evidence.patchBeforeMotive === null
        ? 'OK'
        : 'DEFECTO'
    evidence.reason =
      evidence.verdict === 'OK'
        ? null
        : 'la cancelación no pidió el motivo, no lo guardó o lo envió antes de confirmarlo'

    evidence.shots.push(await shot(page, 'shots/u3-calendar-cancel-after.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-cancel.json', evidence)
  }

  expect(evidence.patch, 'cancel request observed').not.toBeNull()
  expect(evidence.shots, 'captures of the cancel probe').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the cancel probe').toEqual([])
})

// --- custody: check-in, announce, start attention -------------------------

test('@U3 calendar · custodia', async ({ page, request }) => {
  test.setTimeout(180000)

  const receptionist = await loginAs(request, 'recepcionista')
  const professional = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const lifecycle = (await import('./fixtures.js')).lifecycleTarget()
  const fallback = fixtures.appointments?.rows?.[1]
  const target = lifecycle?.id
    ? { id: lifecycle.id, patient: lifecycle.patientName, source: 'ui' }
    : fallback
      ? { id: fallback.id, patient: fallback.patient.name, source: 'fixture' }
      : null

  const evidence = {
    route: ROUTE,
    role: 'recepcionista+odontologo',
    target,
    checkIn: null,
    announce: null,
    startAttention: null,
    transitions: [],
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!target) {
    const reason = fixtures.appointments?.reason ?? 'no custody target available'
    audit('calendar-custody.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const statusOf = async id => {
    const response = await request.get(`${BASE_URL}/api/appointments/${id}`, {
      headers: { Authorization: `Bearer ${professional.token}`, Accept: 'application/json' }
    })
    const body = await response.json().catch(() => null)
    return body?.data?.status ?? null
  }

  const clickCustody = async (action, expectedPath) => {
    const opened = await openAppointmentDetail(page, target.id)
    if (!opened) return { opened: false }

    const response = page
      .waitForResponse(
        entry =>
          entry.request().method() === 'POST' &&
          new URL(entry.url()).pathname.endsWith(`/api/appointments/${target.id}/${expectedPath}`),
        { timeout: ACTION_TIMEOUT }
      )
      .catch(() => null)
    const button = page.locator(`[data-calendar-action="${action}"]`)

    if (!(await button.isVisible().catch(() => false))) {
      return { opened: true, offered: false }
    }

    await button.click({ timeout: 5000 })
    const result = await response
    const body = await result?.json().catch(() => null)
    const toast = await waitForToast(
      page,
      expectedPath === 'check-in'
        ? 'Llegada registrada.'
        : expectedPath === 'announce'
          ? 'Paciente anunciado.'
          : 'Atención iniciada.',
      6000
    )
    await page
      .locator('[data-calendar-detail]')
      .waitFor({ state: 'hidden', timeout: 8000 })
      .catch(() => {})

    return {
      opened: true,
      offered: true,
      status: result?.status() ?? null,
      responseStatus: body?.data?.status ?? null,
      toast,
      observedStatus: await statusOf(target.id)
    }
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, receptionist)
    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    evidence.transitions.push({ step: 'before', status: await statusOf(target.id) })
    evidence.shots.push(await shot(page, 'shots/u3-calendar-custody-before.png'))

    const checkIn = await clickCustody('check-in', 'check-in')
    const stagedCheckIn = (fixtures.staging ?? []).find(
      step => step.action === 'check-in' && step.appointment === target.id
    )
    evidence.checkIn =
      checkIn.offered === false
        ? {
            ...checkIn,
            reason: 'the fixture is already arrived; its check-in was staged by the real door',
            staged: stagedCheckIn ?? null
          }
        : checkIn
    evidence.transitions.push({
      step: 'check-in',
      status: evidence.checkIn?.observedStatus ?? null
    })

    evidence.announce = await clickCustody('announce', 'announce')
    evidence.transitions.push({
      step: 'announce',
      status: evidence.announce?.observedStatus ?? null
    })

    // Starting the attention belongs to the professional's desk: the SPA hides
    // the action for reception, so the next probe signs in as the professional.
    await primeSession(page, professional)
    await page.reload()
    await listResponse(page)
    await settleCalendar(page)

    evidence.startAttention = await clickCustody('start-attention', 'start-attention')
    evidence.transitions.push({
      step: 'start-attention',
      status: evidence.startAttention?.observedStatus ?? null
    })

    const finalStatus = await statusOf(target.id)
    evidence.finalStatus = finalStatus

    if (target.source === 'ui' && finalStatus === 'in_consultation') {
      const { updateLifecycle } = await import('./fixtures.js')
      updateLifecycle({ status: finalStatus })
    }

    evidence.verdict =
      (evidence.checkIn?.status === 200 || evidence.checkIn?.staged?.status === 200) &&
      evidence.announce?.status === 200 &&
      evidence.startAttention?.status === 200 &&
      finalStatus === 'in_consultation'
        ? 'OK'
        : 'DEFECTO'

    evidence.shots.push(await shot(page, 'shots/u3-calendar-custody-after.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('calendar-custody.json', evidence)
  }

  expect(evidence.transitions.length, 'custody transitions recorded').toBe(4)
  expect(evidence.shots, 'captures of the custody probe').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the custody probe').toEqual([])
})

// --- live badge without a socket ------------------------------------------

test('@U3 calendar · indicador en vivo sin socket', async ({ page, request }) => {
  test.setTimeout(90000)

  const session = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: ROUTE,
    role: 'odontologo',
    aborted: false,
    badge: null,
    subscription: null,
    rendered: false,
    contentLength: 0,
    honest: null,
    verdict: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.route('**/api/broadcasting/auth', route => route.abort())
    evidence.aborted = true

    await page.goto(ROUTE)
    await listResponse(page)
    await settleCalendar(page)

    evidence.contentLength = await contentLength(page)
    evidence.rendered = evidence.contentLength > THRESHOLD
    const snapshot = await readCalendar(page)
    evidence.badge = snapshot.liveBadge

    evidence.subscription = diagnostics.failedRequests.filter(entry =>
      /\/api\/broadcasting\/auth$/.test(entry.url ?? '')
    )
    evidence.subscriptionFailed = evidence.subscription.length > 0
    evidence.echoErrors = diagnostics.consoleErrors.filter(error =>
      /\[Echo\]|broadcasting/i.test(error)
    )
    evidence.honest = !(evidence.badge.visible && evidence.subscriptionFailed)
    evidence.verdict = evidence.honest ? 'OK' : 'DEFECTO'
    evidence.reason =
      'the badge claims live updates («En vivo», aria-label «Actualizaciones en tiempo real activas») even when the private channel subscription fails'

    evidence.shots.push(await shot(page, 'shots/u3-calendar-no-socket.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    // The aborted authorizer is the experiment, not an unexpected surface error.
    evidence.failedRequests.expected = [
      ...evidence.failedRequests.expected,
      ...evidence.failedRequests.unexpected
        .filter(entry => /\/api\/broadcasting\/auth$/.test(entry.url ?? ''))
        .map(entry => ({ ...entry, reason: 'aborted by the live-badge probe' }))
    ]
    evidence.failedRequests.unexpected = evidence.failedRequests.unexpected.filter(
      entry => !/\/api\/broadcasting\/auth$/.test(entry.url ?? '')
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    audit('calendar-live.json', evidence)
  }

  expect(evidence.aborted, 'broadcasting authorizer aborted').toBe(true)
  expect(evidence.badge, 'live badge observed').not.toBeNull()
  expect(evidence.shots, 'capture of the badge').toHaveLength(1)
  expect(evidence.pageErrors, 'page errors without the socket').toEqual([])
})
