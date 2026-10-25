// Plan #13 (issue #365, U2) — the professional's desk: `/my-day`.
//
// Imported by `surface.spec.js`, so `--grep "@U2 my-day"` selects these rows and
// `--grep @U2` (the issue command) selects both U2 modules. Evidence lands under
// the session directory; the render rows go to `render/` for `ui-pass`.
//
// Measurement code: the custody and laboratory effects are recorded as
// before/after facts with a verdict, never as suite failures. Only the probe's
// own completion and the session's zero-pageerror criterion are asserted.

import { test, expect } from 'playwright/test'

import {
  DIALOG_SELECTOR,
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

import { U2_EVIDENCE_DIR, advanceLabCase, ensureFixtures, fixtureSummary } from './fixtures.js'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ROUTE = '/my-day'
const THRESHOLD = 80
const LOAD_TIMEOUT = 20000
const SETTLE_TIMEOUT = 15000
const ACTION_TIMEOUT = 15000
const LIVE_TIMEOUT = 10000
const WIZARD_TITLE = 'Expediente de Cita'

const ROLES = Object.freeze(['odontologo', 'tecnico_dental', 'asistente'])
const MY_DAY_ENDPOINT = /\/api\/my-day(\?.*)?$/

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U2_EVIDENCE_DIR })

// The queue probe cuts its own endpoint: that `net::ERR_FAILED` is the
// experiment, not an unexpected surface failure.
const classifyQueueFailures = failures => {
  const classified = classifyFailures(failures, 'owner')
  const probeAborts = classified.unexpected.filter(
    entry => entry.status == null && MY_DAY_ENDPOINT.test(entry.url ?? '')
  )

  return {
    expected: [
      ...classified.expected,
      ...probeAborts.map(entry => ({ ...entry, reason: 'aborted by the queue probe' }))
    ],
    unexpected: classified.unexpected.filter(entry => !probeAborts.includes(entry))
  }
}
const shot = (page, filePath) => capture(page, filePath, { dir: U2_EVIDENCE_DIR })

const readMyDay = page =>
  page.evaluate(() => {
    const text = node => (node?.textContent ?? '').trim()
    const counts = {}

    for (const node of document.querySelectorAll('[data-my-day-count]')) {
      counts[node.getAttribute('data-my-day-count')] = text(node.querySelector('dd'))
    }

    const sections = Array.from(document.querySelectorAll('[data-my-day-section]')).map(node => ({
      key: node.getAttribute('data-my-day-section'),
      rows: node.querySelectorAll('[data-my-day-row], [data-my-day-lab-case]').length,
      patients: Array.from(node.querySelectorAll('[data-my-day-patient]')).map(patient =>
        text(patient)
      ),
      works: Array.from(node.querySelectorAll('[data-my-day-lab-case-work]')).map(work =>
        text(work)
      ),
      actions: Array.from(node.querySelectorAll('[data-my-day-action]')).map(action =>
        action.getAttribute('data-my-day-action')
      ),
      empties: Array.from(node.querySelectorAll('[data-my-day-empty]')).map(empty =>
        empty.getAttribute('data-my-day-empty')
      )
    }))

    const headings = Array.from(document.querySelectorAll('h1')).map(node => {
      const box = node.getBoundingClientRect()
      return { text: text(node), visible: box.width > 0 && box.height > 0 }
    })

    const error = document.querySelector('[data-my-day-error]')

    return {
      h1: {
        count: headings.length,
        visibleCount: headings.filter(heading => heading.visible).length,
        texts: headings.map(heading => heading.text)
      },
      counts,
      numericCounts: Object.fromEntries(
        Object.entries(counts).map(([key, value]) => [key, Number(value)])
      ),
      sections,
      sectionKeys: sections.map(section => section.key),
      emptyStates: sections.flatMap(section => section.empties),
      error: Boolean(error),
      errorMessage: text(document.querySelector('[data-my-day-error-message]')),
      errorRetry: Boolean(document.querySelector('[data-my-day-retry]'))
    }
  })

const settled = async page => {
  await page
    .locator('[data-my-day]')
    .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    .catch(() => {})
  await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)
}

const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live]'))
      .map(node => node.innerText.trim())
      .filter(Boolean)
      .join(' | ')
  )

// The toast is a bounded content wait, not a sleep: the action either raises the
// message or it does not, and the absence is recorded in the row.
const waitForToast = async (page, expected) => {
  await page
    .waitForFunction(
      message =>
        Array.from(document.querySelectorAll('[aria-live]')).some(node =>
          node.innerText.includes(message)
        ),
      expected,
      { timeout: 5000, polling: 100 }
    )
    .catch(() => {})

  return toastText(page)
}

const membership = (snapshot, name) =>
  snapshot.sections.filter(section => section.patients.includes(name)).map(section => section.key)

const countersChanged = (before, after) => JSON.stringify(before) !== JSON.stringify(after)

const waitForPost = (page, pattern) =>
  page
    .waitForResponse(
      response =>
        response.request().method() === 'POST' && pattern.test(new URL(response.url()).pathname),
      { timeout: ACTION_TIMEOUT }
    )
    .catch(() => null)

const statusOf = response => response?.status() ?? null

// --- render matrix ---------------------------------------------------------

for (const role of ROLES) {
  test(`@U2 my-day · render for ${role}`, async ({ page, request }) => {
    test.setTimeout(90000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: ROUTE,
      slug: 'my-day',
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
      snapshot: null,
      firstPaint: null,
      fixtures: fixtureSummary(fixtures)
    }

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)

      // `onMounted` awaits the queue, so the first frame paints the hero with
      // zero counters and no sections (a real first-paint state). The row keeps
      // that frame and waits for the payload and the skeleton before reading
      // the settled state.
      const payloadWait = page
        .waitForResponse(response => MY_DAY_ENDPOINT.test(new URL(response.url()).pathname), {
          timeout: LOAD_TIMEOUT
        })
        .catch(() => null)

      const response = await page.goto(ROUTE)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator('[data-my-day]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      evidence.firstPaint = await readMyDay(page)

      await payloadWait
      await page
        .locator('[data-my-day-loading]')
        .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settled(page)
      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD
      evidence.snapshot = await readMyDay(page)

      evidence.shots.push(await shot(page, `shots/u2-my-day-${role}-desktop.png`))

      await page.setViewportSize(MOBILE)
      const mobilePayload = page
        .waitForResponse(response => MY_DAY_ENDPOINT.test(new URL(response.url()).pathname), {
          timeout: LOAD_TIMEOUT
        })
        .catch(() => null)
      await page.reload()
      await mobilePayload
      await page
        .locator('[data-my-day-loading]')
        .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settled(page)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }

      evidence.shots.push(await shot(page, `shots/u2-my-day-${role}-mobile.png`))
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    } finally {
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
      audit(`render/u2-my-day-${role}.json`, evidence)
    }

    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- custody actions -------------------------------------------------------

test('@U2 my-day · custody actions move the row and open the wizard', async ({ page, request }) => {
  test.setTimeout(120000)

  const role = 'odontologo'
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const posts = []
  page.on('response', response => {
    if (response.request().method() !== 'POST') return
    const { pathname } = new URL(response.url())
    if (/\/api\/appointments\/\d+\/(check-in|start-attention)$/.test(pathname)) {
      posts.push({ pathname, status: response.status() })
    }
  })

  const base = {
    route: ROUTE,
    role,
    fixtures: fixtureSummary(fixtures),
    steps: [],
    wizard: null,
    snapshots: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  if (!fixtures.appointments?.ok || !fixtures.appointments.rows?.[0]) {
    const reason = fixtures.appointments?.reason ?? 'appointment fixtures unavailable'
    audit('my-day-actions.json', { ...base, reason, verdict: 'skipped', shots: [] })
    test.skip(true, reason)
    return
  }

  const appointment = fixtures.appointments.rows[0]
  const patient = appointment.patient.name
  const rowFor = (section, name) =>
    page
      .locator(`[data-my-day-section="${section}"] [data-my-day-row]`)
      .filter({ hasText: name })
      .first()

  const evidence = { ...base, shots: [] }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const payloadWait = page
      .waitForResponse(response => MY_DAY_ENDPOINT.test(new URL(response.url()).pathname), {
        timeout: LOAD_TIMEOUT
      })
      .catch(() => null)
    await page.goto(ROUTE)
    await payloadWait
    await page
      .locator('[data-my-day-loading]')
      .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settled(page)

    evidence.snapshots = { before: await readMyDay(page) }

    // 1. Arrival: upcoming -> waiting.
    const arrival = {
      step: 'arrive',
      patient,
      appointmentId: appointment.id,
      expectedSection: 'waiting'
    }
    arrival.membershipBefore = membership(evidence.snapshots.before, patient)
    arrival.countersBefore = evidence.snapshots.before.numericCounts

    const arrivalPost = waitForPost(page, /\/check-in$/)
    await rowFor('upcoming', patient)
      .locator('[data-my-day-action="arrive"]')
      .click({ timeout: 5000 })
      .catch(() => {})
    arrival.postStatus = statusOf(await arrivalPost)
    arrival.toast = await waitForToast(page, 'Llegada registrada.')
    await rowFor('waiting', patient)
      .waitFor({ state: 'visible', timeout: ACTION_TIMEOUT })
      .catch(() => {})

    evidence.snapshots.afterArrive = await readMyDay(page)
    arrival.membershipAfter = membership(evidence.snapshots.afterArrive, patient)
    arrival.countersAfter = evidence.snapshots.afterArrive.numericCounts
    arrival.countersChanged = countersChanged(arrival.countersBefore, arrival.countersAfter)
    arrival.ok =
      arrival.postStatus === 200 &&
      arrival.membershipAfter.includes('waiting') &&
      !arrival.membershipAfter.includes('upcoming')
    evidence.steps.push(arrival)

    // 2. Start: waiting -> attending.
    const start = {
      step: 'start',
      patient,
      appointmentId: appointment.id,
      expectedSection: 'attending'
    }
    start.membershipBefore = arrival.membershipAfter
    start.countersBefore = arrival.countersAfter

    const startPost = waitForPost(page, /\/start-attention$/)
    await rowFor('waiting', patient)
      .locator('[data-my-day-action="start"]')
      .click({ timeout: 5000 })
      .catch(() => {})
    start.postStatus = statusOf(await startPost)
    start.toast = await waitForToast(page, 'Atención iniciada.')
    await rowFor('attending', patient)
      .waitFor({ state: 'visible', timeout: ACTION_TIMEOUT })
      .catch(() => {})

    evidence.snapshots.afterStart = await readMyDay(page)
    start.membershipAfter = membership(evidence.snapshots.afterStart, patient)
    start.countersAfter = evidence.snapshots.afterStart.numericCounts
    start.countersChanged = countersChanged(start.countersBefore, start.countersAfter)
    start.ok =
      start.postStatus === 200 &&
      start.membershipAfter.includes('attending') &&
      !start.membershipAfter.includes('waiting')
    evidence.steps.push(start)

    // 3. Continue: the consultation wizard opens for the attending row.
    const continuation = {
      step: 'continue',
      patient,
      appointmentId: appointment.id,
      expected: 'wizard'
    }
    await rowFor('attending', patient)
      .locator('[data-my-day-action="continue"]')
      .click({ timeout: 5000 })
      .catch(() => {})

    const wizardHeading = page.getByRole('heading', { name: WIZARD_TITLE })
    const opened = await wizardHeading
      .waitFor({ state: 'visible', timeout: 10000 })
      .then(() => true)
      .catch(() => false)

    continuation.dialogRoleCount = await page.locator(DIALOG_SELECTOR).count()
    continuation.wizardOpened = opened
    continuation.wizardUsesDialogPrimitive = continuation.dialogRoleCount > 0
    evidence.wizard = {
      title: WIZARD_TITLE,
      opened,
      dialogRoleCount: continuation.dialogRoleCount,
      usesDialogPrimitive: continuation.wizardUsesDialogPrimitive
    }

    if (opened) {
      evidence.shots.push(await shot(page, 'shots/u2-my-day-wizard.png'))
      await page
        .getByRole('button', { name: 'Cerrar', exact: true })
        .first()
        .click({ timeout: 5000 })
        .catch(() => {})
      continuation.closed = await wizardHeading
        .waitFor({ state: 'hidden', timeout: 5000 })
        .then(() => true)
        .catch(() => false)
    }

    continuation.ok = opened
    evidence.steps.push(continuation)
    evidence.snapshots.afterContinue = await readMyDay(page)
    evidence.posts = posts
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('my-day-actions.json', evidence)
  }

  expect(evidence.steps, 'custody steps attempted').toHaveLength(3)
  expect(evidence.pageErrors, 'page errors during the custody probe').toEqual([])
})

// --- laboratory readiness arrives live ------------------------------------

test('@U2 my-day · laboratory case arrives without a reload', async ({ page, request }) => {
  test.setTimeout(90000)

  const role = 'odontologo'
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)

  const base = {
    route: ROUTE,
    role,
    fixtures: fixtureSummary(fixtures),
    live: false,
    fallback: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  if (!fixtures.lab?.ok) {
    const reason = fixtures.lab?.reason ?? 'lab fixture unavailable'
    audit('my-day-lab.json', { ...base, reason, verdict: 'skipped', shots: [] })
    test.skip(true, reason)
    return
  }

  const { work } = fixtures.lab
  const evidence = { ...base, shots: [] }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const payloadWait = page
      .waitForResponse(response => MY_DAY_ENDPOINT.test(new URL(response.url()).pathname), {
        timeout: LOAD_TIMEOUT
      })
      .catch(() => null)
    await page.goto(ROUTE)
    await payloadWait
    await page
      .locator('[data-my-day-loading]')
      .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settled(page)

    evidence.before = await readMyDay(page)
    evidence.sectionBefore = evidence.before.sectionKeys.includes('lab-cases')
    const alreadyReady = evidence.before.sections.some(section => section.works.includes(work))
    evidence.markerBefore = alreadyReady

    evidence.transition = await advanceLabCase(request, fixtures.lab.id, 'delivered')

    const appeared = await page
      .waitForFunction(
        marker =>
          Array.from(document.querySelectorAll('[data-my-day-lab-case-work]')).some(node =>
            node.innerText.includes(marker)
          ),
        work,
        { timeout: LIVE_TIMEOUT, polling: 150 }
      )
      .then(() => true)
      .catch(() => false)

    evidence.live = appeared && !alreadyReady

    if (alreadyReady) {
      evidence.reason = 'the fixture case was already in the ready block before the transition'
    }

    evidence.toast = await toastText(page)

    if (appeared && !alreadyReady) {
      evidence.toast = await waitForToast(page, 'Caso de laboratorio listo')
    }

    evidence.after = await readMyDay(page)
    evidence.shots.push(await shot(page, 'shots/u2-my-day-lab.png'))

    if (!appeared) {
      evidence.reason = `no [data-my-day-lab-case-work] with the fixture marker within ${LIVE_TIMEOUT} ms`
      // The honest fallback: the API published the ready case but the desk did
      // not receive the event. A reload shows whether only the transport failed.
      evidence.fallback = 'reload'
      const reloadPayload = page
        .waitForResponse(response => MY_DAY_ENDPOINT.test(new URL(response.url()).pathname), {
          timeout: LOAD_TIMEOUT
        })
        .catch(() => null)
      await page.reload()
      await reloadPayload
      await page
        .locator('[data-my-day-loading]')
        .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settled(page)
      evidence.afterReload = await readMyDay(page)
      evidence.shots.push(await shot(page, 'shots/u2-my-day-lab-reload.png'))
      evidence.reloadShowsCase = evidence.afterReload.sections.some(section =>
        section.works.includes(work)
      )
    }

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('my-day-lab.json', evidence)
  }

  expect(typeof evidence.transition?.status, 'phase transition observed').toBe('number')
  expect(evidence.shots.length, 'captures of the laboratory probe').toBeGreaterThanOrEqual(1)
  expect(evidence.pageErrors, 'page errors during the laboratory probe').toEqual([])
})

// --- error state and retry ------------------------------------------------

test('@U2 my-day · failed queue shows its error and the retry recovers it', async ({
  page,
  request
}) => {
  test.setTimeout(90000)

  const role = 'odontologo'
  const session = await loginAs(request, role)
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: ROUTE,
    role,
    aborted: false,
    errorObserved: false,
    errorMessage: '',
    retryVisible: false,
    retryClicked: false,
    recovered: false,
    after: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.route(MY_DAY_ENDPOINT, route => route.abort())
    evidence.aborted = true

    await page.goto(ROUTE)

    const error = page.locator('[data-my-day-error]')
    evidence.errorObserved = await error
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .then(() => true)
      .catch(() => false)
    evidence.errorMessage = (
      await page
        .locator('[data-my-day-error-message]')
        .innerText()
        .catch(() => '')
    ).trim()
    evidence.retryVisible = await page
      .locator('[data-my-day-retry]')
      .isVisible()
      .catch(() => false)
    evidence.shots.push(await shot(page, 'shots/u2-my-day-error.png'))

    await page.unroute(MY_DAY_ENDPOINT)

    if (evidence.retryVisible) {
      await page
        .locator('[data-my-day-retry]')
        .click({ timeout: 5000 })
        .catch(() => {})
      evidence.retryClicked = true
      audit('my-day-error.json', evidence)

      await error.waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {})
      await page
        .locator('[data-my-day-section]')
        .first()
        .waitFor({ state: 'visible', timeout: 15000 })
        .catch(() => {})
      await settleSurface(page, THRESHOLD, 5000)
    }

    evidence.after = await readMyDay(page)
    evidence.recovered = !evidence.after.error && evidence.after.sections.length > 0
    evidence.shots.push(await shot(page, 'shots/u2-my-day-error-recovered.png'))
    evidence.failedRequests = classifyQueueFailures(diagnostics.failedRequests)
  } finally {
    evidence.failedRequests = classifyQueueFailures(diagnostics.failedRequests)
    audit('my-day-error.json', evidence)
  }

  expect(evidence.aborted, 'queue endpoint blocked').toBe(true)
  expect(evidence.errorObserved, 'error state observed').toBe(true)
  expect(evidence.retryVisible, 'retry affordance observed').toBe(true)
  expect(evidence.shots, 'captures of the error probe').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the error probe').toEqual([])
})
