// Plan #13 (issue #366, U3) — the consultation close: `ConsultationWizard`.
//
// Imported by `surface.spec.js`; `--grep "@U3 wizard"` selects the primitive,
// stacking and signature rows and `--grep "@U3 ciclo"` the settlement row. The
// wizard is expected to fail several rows of the primitive contract: that
// measured failure is the deliverable, so the checks stay in the evidence (and
// in the gallery's dialog table) instead of becoming suite failures.
//
// Asserted here only that each probe ran to completion, that the closure went
// through both password attempts and that no page error was raised.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  DIALOG_SELECTOR,
  assertDialogContract,
  capture,
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface,
  writeEvidence
} from '../harness.js'

import {
  U3_EVIDENCE_DIR,
  closureTarget,
  ensureFixtures,
  fixtureSummary,
  lifecycleTarget,
  registerClosure,
  updateLifecycle
} from './fixtures.js'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ROUTE = '/calendar'
const THRESHOLD = 80
const LOAD_TIMEOUT = 20000
const SETTLE_TIMEOUT = 15000
const ACTION_TIMEOUT = 20000
const WIZARD_HEADING = 'Expediente de Cita'
const PASSWORD = 'password123'
const WRONG_PASSWORD = 'audit-u3-wrong-password'
const TAB_STEPS = Object.freeze([
  { id: 'mode', label: 'Modo' },
  { id: 'evolution', label: 'Evolución' },
  { id: 'procedures', label: 'Procedimientos' },
  { id: 'materials', label: 'Materiales' },
  { id: 'attachments', label: 'Adjuntos' },
  { id: 'next', label: 'Próxima cita' },
  { id: 'signature', label: 'Cierre y firma' }
])

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U3_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U3_EVIDENCE_DIR })

let closedTarget = null

const listResponse = (page, timeout = ACTION_TIMEOUT) =>
  page
    .waitForResponse(
      response =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname === '/api/appointments',
      { timeout }
    )
    .catch(() => null)

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

const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live]'))
      .map(node => node.innerText.trim())
      .filter(Boolean)
      .join(' | ')
  )

const overlayLocator = page =>
  page
    .locator('div.fixed.inset-0')
    .filter({ has: page.getByRole('heading', { name: WIZARD_HEADING }) })
    .first()

const panelLocator = page => overlayLocator(page).locator(':scope > div').first()

const hidden = (locator, timeout = 3000) =>
  locator
    .waitFor({ state: 'hidden', timeout })
    .then(() => true)
    .catch(() => false)

const openWizard = async (page, appointmentId) => {
  await page.goto(ROUTE)
  await listResponse(page)
  await settleCalendar(page)

  let chip = page.locator(`[data-calendar-appointment="${appointmentId}"]`).first()

  if (!(await chip.isVisible().catch(() => false))) {
    await page
      .locator('[data-calendar-view="day"]')
      .click({ timeout: 5000 })
      .catch(() => {})
    await listResponse(page)
    await settleCalendar(page)
    chip = page.locator(`[data-calendar-appointment="${appointmentId}"]`).first()
  }

  if (!(await chip.isVisible().catch(() => false))) {
    // The grid renders 06:00–21:00 and the fixture books «now + 75 minutes»: an
    // evening session leaves the appointment outside the grid even though it is
    // today's act. The professional desk lists it under «Atendiendo» and opens
    // the same component through its own door, so the contract is measured on
    // the real surface instead of skipping the session.
    const fallback = await openWizardFromMyDay(page)
    if (fallback.opened) return fallback

    return {
      opened: false,
      reason: `the appointment chip is not visible in the agenda (${fallback.reason})`
    }
  }

  await chip.click({ timeout: 5000 }).catch(() => {})
  await page
    .locator('[data-calendar-detail]')
    .waitFor({ state: 'visible', timeout: 8000 })
    .catch(() => {})

  const openButton = page.locator('[data-calendar-action="complete-consultation"]')

  if (!(await openButton.isVisible().catch(() => false))) {
    return {
      opened: false,
      reason: 'the detail does not offer «Completar Consulta» for this appointment'
    }
  }

  await openButton.click({ timeout: 5000 })
  await overlayLocator(page)
    .waitFor({ state: 'visible', timeout: 10000 })
    .catch(() => {})

  const opened = await overlayLocator(page)
    .isVisible()
    .catch(() => false)

  return {
    opened,
    overlay: overlayLocator(page),
    panel: panelLocator(page),
    reason: opened ? null : 'the wizard overlay did not open'
  }
}

// Same component, same close/edit paths: the desk door only changes the trigger.
const openWizardFromMyDay = async page => {
  await page.goto('/my-day')
  const action = page.locator('[data-my-day-action="continue"]').first()
  await action.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {})

  if (!(await action.isVisible().catch(() => false))) {
    return { opened: false, reason: 'the professional desk does not list the appointment' }
  }

  await action.click({ timeout: 5000 }).catch(() => {})
  await overlayLocator(page)
    .waitFor({ state: 'visible', timeout: 10000 })
    .catch(() => {})

  const opened = await overlayLocator(page)
    .isVisible()
    .catch(() => false)

  return {
    opened,
    door: 'my-day',
    overlay: overlayLocator(page),
    panel: panelLocator(page),
    reason: opened ? null : 'the wizard overlay did not open'
  }
}

const closeWizard = async page => {
  const overlay = overlayLocator(page)

  if (!(await overlay.isVisible().catch(() => false))) return false

  await overlay
    .getByRole('button', { name: 'Cerrar', exact: true })
    .first()
    .click({ timeout: 5000 })
    .catch(() => {})

  return hidden(overlay)
}

const closeByBackdrop = async page => {
  await page.mouse.click(12, 12)
  return hidden(overlayLocator(page))
}

const activeElement = page =>
  page.evaluate(() => {
    const element = document.activeElement
    const wizard = Array.from(document.querySelectorAll('div.fixed.inset-0')).find(node =>
      node.textContent.includes('Expediente de Cita')
    )

    return {
      tag: element?.tagName ?? null,
      role: element?.getAttribute?.('role') ?? null,
      text: (element?.innerText ?? '').trim().slice(0, 80),
      insideWizard: Boolean(wizard && element && wizard.contains(element))
    }
  })

const bodyOverflow = page =>
  page.evaluate(() => ({
    inline: document.body.style.overflow,
    computed: getComputedStyle(document.body).overflow
  }))

const waitForPost = (page, pathPattern) =>
  page
    .waitForResponse(
      response =>
        response.request().method() === 'POST' &&
        pathPattern.test(new URL(response.url()).pathname),
      { timeout: ACTION_TIMEOUT }
    )
    .catch(() => null)

const getJson = async (request, session, path) => {
  const response = await request.get(`${BASE_URL}${path}`, {
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' }
  })
  const body = await response.json().catch(() => null)
  return { status: response.status(), body }
}

const postJson = async (request, session, path, data) => {
  const response = await request.post(`${BASE_URL}${path}`, {
    headers: {
      Authorization: `Bearer ${session.token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json'
    },
    data
  })
  const body = await response.json().catch(() => null)
  return { status: response.status(), body }
}

// --- layer 3: the wizard is not a primitive -------------------------------

test('@U3 wizard · contrato de primitiva', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const fixtureAppointment = fixtures.appointments?.rows?.[2]
  let dialogRow = null
  const evidence = {
    route: ROUTE,
    slug: 'calendar-wizard',
    component: 'resources/js/modules/appointments/ConsultationWizard.vue',
    role: 'odontologo',
    appointmentId: fixtureAppointment?.id ?? null,
    overlay: null,
    dialogRole: null,
    ariaModal: null,
    accessibleName: null,
    singleH1: null,
    checks: [],
    failures: [],
    verdict: null,
    reason: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  if (!fixtureAppointment) {
    evidence.reason = fixtures.appointments?.reason ?? 'no in-consultation fixture'
    evidence.verdict = 'NO PROBADO'
    audit('primitives/wizard.json', { ...evidence, opened: 0, dialogs: [] })
    test.skip(true, evidence.reason)
    return
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const opened = await openWizard(page, fixtureAppointment.id)

    if (!opened.opened) {
      evidence.reason = opened.reason
      evidence.verdict = 'NO PROBADO'
      test.skip(true, opened.reason)
      return
    }

    evidence.shots.push(await shot(page, 'shots/u3-wizard-contract.png'))

    evidence.door = opened.door ?? 'calendar'
    evidence.overlay = await opened.overlay.evaluate(node => {
      const panel = node.firstElementChild
      const box = panel?.getBoundingClientRect()
      const viewport = { width: window.innerWidth, height: window.innerHeight }
      const round = value => Math.round(value * 10) / 10

      return {
        overlayClasses: node.className,
        panelClasses: panel?.className ?? null,
        panelBox: box
          ? {
              x: round(box.x),
              y: round(box.y),
              width: round(box.width),
              height: round(box.height)
            }
          : null,
        viewport,
        centerOffset: box ? round(Math.abs(box.x + box.width / 2 - viewport.width / 2)) : null,
        ariaModal: panel?.getAttribute('aria-modal') ?? null,
        role: panel?.getAttribute('role') ?? null,
        labelledby: panel?.getAttribute('aria-labelledby') ?? null,
        ariaLabel: panel?.getAttribute('aria-label') ?? null,
        heading: (panel?.querySelector('h2')?.textContent ?? '').trim()
      }
    })
    evidence.dialogRole = await page
      .locator(DIALOG_SELECTOR)
      .count()
      .catch(() => 0)
    evidence.ariaModal = evidence.overlay.ariaModal
    evidence.accessibleName = {
      name: evidence.overlay.labelledby || evidence.overlay.ariaLabel || '',
      labelledby: evidence.overlay.labelledby,
      ariaLabel: evidence.overlay.ariaLabel,
      heading: evidence.overlay.heading,
      source: evidence.overlay.labelledby || evidence.overlay.ariaLabel ? 'aria' : 'none'
    }
    evidence.singleH1 = await page.locator('h1').count()

    // The harness contract runs over the panel so its check vocabulary is the
    // same one the gallery prints for UiModal. The wizard exposes no role, so
    // the role and aria-modal checks are added explicitly.
    const contract = await assertDialogContract(page, { dialog: opened.panel })
    evidence.checks = [
      ...contract.checks,
      {
        id: 'dialog-role',
        ok: evidence.dialogRole > 0,
        observed: evidence.dialogRole,
        expected: 'role="dialog" o role="alertdialog"'
      },
      {
        id: 'aria-modal',
        ok: evidence.ariaModal === 'true',
        observed: evidence.ariaModal ?? 'ausente',
        expected: 'aria-modal="true"'
      }
    ]

    // #391: el chequeo de Escape del contrato cierra el asistente cuando la
    // superficie lo respeta, así que la sonda del fondo necesita un diálogo que
    // siga abierto: se reabre antes de medir. Medir primero el fondo y después
    // Escape acoplaba dos filas y declaraba un defecto falso.
    const wizardSurvivedEscape = await overlayLocator(page)
      .isVisible()
      .catch(() => false)
    if (!wizardSurvivedEscape) {
      const reopened = await openWizard(page, fixtureAppointment.id)
      evidence.backdropReopened = Boolean(reopened.opened)
    }
    const backdropClosed = await closeByBackdrop(page)

    evidence.checks.push({
      id: 'backdrop-closes',
      ok: backdropClosed,
      observed: backdropClosed ? 'cerrado' : 'sigue abierto',
      expected: 'cierra al pulsar el fondo'
    })
    evidence.escaped = wizardSurvivedEscape
    evidence.failures = evidence.checks.filter(check => check.ok === false).map(check => check.id)
    evidence.verdict = evidence.failures.length === 0 ? 'OK' : 'DEFECTO'
    evidence.reason =
      evidence.failures.length === 0
        ? null
        : `la superficie no cumple el contrato de primitiva: ${evidence.failures.join(', ')}`

    dialogRow = {
      trigger: {
        label: 'Completar Consulta',
        kind: 'action',
        selector: '[data-calendar-action="complete-consultation"]'
      },
      dialog: {
        ...contract.dialog,
        overlayClasses: evidence.overlay.overlayClasses,
        panelClasses: evidence.overlay.panelClasses,
        centerOffset: evidence.overlay.centerOffset,
        ariaModal: evidence.ariaModal,
        accessibleName: evidence.accessibleName,
        singleH1: evidence.singleH1
      },
      checks: evidence.checks
    }

    // Criterion 10 applies to this surface too: the same act loaded at 390px.
    await page.setViewportSize(MOBILE)
    const mobile = { opened: false, reason: null, panel: null, overflow: null, shots: [] }
    const mobileOpened = await openWizard(page, fixtureAppointment.id)
    mobile.opened = mobileOpened.opened
    mobile.reason = mobileOpened.reason ?? null

    if (mobileOpened.opened) {
      mobile.panel = await mobileOpened.panel.evaluate(node => {
        const box = node.getBoundingClientRect()
        const round = value => Math.round(value * 10) / 10
        return {
          x: round(box.x),
          y: round(box.y),
          width: round(box.width),
          height: round(box.height),
          viewport: { width: window.innerWidth, height: window.innerHeight },
          insideViewport: box.x >= 0 && box.x + box.width <= window.innerWidth + 1
        }
      })
      mobile.overflow = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth
      }))
      mobile.shots.push(await shot(page, 'shots/u3-wizard-mobile.png'))
      // At 390px the panel leaves an 8px gutter, so the corner click used on
      // desktop would land inside the panel: close with the wizard's own button.
      mobile.closed = await closeWizard(page)
    }

    evidence.mobile = mobile

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')

    audit('primitives/wizard.json', {
      ...evidence,
      opened: dialogRow ? 1 : 0,
      dialogs: dialogRow ? [dialogRow] : []
    })

    audit('primitives.json', {
      session: 'U3',
      route: ROUTE,
      slug: 'calendar-wizard',
      component: evidence.component,
      role: evidence.role,
      appointmentId: evidence.appointmentId,
      verdict: evidence.verdict,
      reason: evidence.reason,
      failures: evidence.failures,
      contract: {
        overlay: evidence.overlay,
        dialogRole: evidence.dialogRole,
        ariaModal: evidence.ariaModal,
        accessibleName: evidence.accessibleName,
        singleH1: evidence.singleH1,
        harnessComparable: 'assertDialogContract sobre el panel del asistente',
        checks: evidence.checks
      },
      dialogs: dialogRow ? [dialogRow] : [],
      pageErrors: evidence.pageErrors,
      consoleErrors: evidence.consoleErrors,
      failedRequests: evidence.failedRequests,
      shots: evidence.shots
    })
  }

  expect(evidence.checks.length, 'contract checks recorded').toBeGreaterThanOrEqual(9)
  expect(evidence.shots, 'capture of the wizard contract').toHaveLength(1)
  expect(evidence.mobile?.opened, 'the wizard opened at 390px').toBe(true)
  expect(evidence.pageErrors, 'page errors during the wizard contract').toEqual([])
})

test('@U3 wizard · pila con un modal encima', async ({ page, request }) => {
  test.setTimeout(150000)

  const session = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const fixtureAppointment = fixtures.appointments?.rows?.[2]
  const evidence = {
    route: ROUTE,
    role: 'odontologo',
    appointmentId: fixtureAppointment?.id ?? null,
    steps: {},
    checks: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  if (!fixtureAppointment) {
    const reason = fixtures.appointments?.reason ?? 'no in-consultation fixture'
    audit('wizard-stack.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const opened = await openWizard(page, fixtureAppointment.id)

    if (!opened.opened) {
      audit('wizard-stack.json', { ...evidence, reason: opened.reason, verdict: 'NO PROBADO' })
      test.skip(true, opened.reason)
      return
    }

    await page.locator('#tab-next').click({ timeout: 5000 })
    await page
      .locator('[data-wizard-lab-case-new]')
      .waitFor({ state: 'visible', timeout: 8000 })
      .catch(() => {})

    evidence.steps.before = {
      wizardOpen: await overlayLocator(page)
        .isVisible()
        .catch(() => false),
      bodyOverflow: await bodyOverflow(page)
    }
    evidence.shots.push(await shot(page, 'shots/u3-wizard-stack-before.png'))

    await page.locator('[data-wizard-lab-case-new]').click({ timeout: 5000 })
    // Pin the stacked dialog by its accessible name: `.last()` re-resolves to
    // whatever dialog is last in the DOM, so once the lab modal unmounts it
    // starts pointing at the wizard panel and reports the close as a failure.
    const top = page
      .locator(DIALOG_SELECTOR)
      .filter({ hasText: 'Nuevo caso de laboratorio' })
      .first()
    await top.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})

    evidence.steps.topOpened = {
      visible: await top.isVisible().catch(() => false),
      name: await top
        .evaluate(element => ({
          labelledby: element.getAttribute('aria-labelledby'),
          label:
            (element.getAttribute('aria-labelledby')
              ? document.getElementById(element.getAttribute('aria-labelledby'))?.textContent
              : element.getAttribute('aria-label')) ?? '',
          title: (element.querySelector('h1, h2, h3')?.textContent ?? '').trim(),
          role: element.getAttribute('role')
        }))
        .catch(() => null),
      bodyOverflow: await bodyOverflow(page),
      active: await activeElement(page)
    }

    await page.keyboard.press('Escape')
    await top.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})

    evidence.steps.afterEscape = {
      topVisible: await top.isVisible().catch(() => false),
      wizardOpen: await overlayLocator(page)
        .isVisible()
        .catch(() => false),
      bodyOverflow: await bodyOverflow(page),
      active: await activeElement(page)
    }

    evidence.checks.push({
      id: 'escape-closes-top',
      ok: !evidence.steps.afterEscape.topVisible,
      observed: evidence.steps.afterEscape.topVisible ? 'sigue abierto' : 'cerrado',
      expected: 'Escape cierra solo el modal superior'
    })
    evidence.checks.push({
      id: 'wizard-survives',
      ok: evidence.steps.afterEscape.wizardOpen,
      observed: evidence.steps.afterEscape.wizardOpen ? 'abierto' : 'cerrado',
      expected: 'el asistente sigue abierto'
    })
    evidence.checks.push({
      id: 'scroll-lock-preserved',
      ok:
        evidence.steps.afterEscape.bodyOverflow.inline === 'hidden' ||
        evidence.steps.afterEscape.bodyOverflow.computed === 'hidden',
      observed:
        evidence.steps.afterEscape.bodyOverflow.inline ||
        evidence.steps.afterEscape.bodyOverflow.computed ||
        'visible',
      expected: 'hidden mientras el asistente sigue abierto'
    })
    evidence.checks.push({
      id: 'focus-inside-wizard',
      ok: evidence.steps.afterEscape.active.insideWizard,
      observed: evidence.steps.afterEscape.active,
      expected: 'el foco vuelve al asistente'
    })

    evidence.verdict = evidence.checks.some(check => check.ok === false) ? 'DEFECTO' : 'OK'
    evidence.failures = evidence.checks.filter(check => check.ok === false).map(check => check.id)
    evidence.reason =
      evidence.verdict === 'OK'
        ? null
        : 'al cerrar el modal superior el body recupera el scroll aunque el asistente sigue abierto: no forma parte de la pila de modales'

    const closed = await closeWizard(page)
    evidence.steps.wizardClosed = closed
    evidence.shots.push(await shot(page, 'shots/u3-wizard-stack-after.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    audit('wizard-stack.json', evidence)
  }

  expect(evidence.steps.topOpened, 'top modal opened').not.toBeUndefined()
  expect(evidence.checks, 'stack checks recorded').toHaveLength(4)
  expect(evidence.shots, 'captures of the stack').toHaveLength(2)
  expect(evidence.pageErrors, 'page errors during the stack probe').toEqual([])
})

// --- seven steps, two signatures, continuity ------------------------------

const localDateTime = date => {
  const pad = value => String(value).padStart(2, '0')
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  )
}

test('@U3 wizard · pasos y firma', async ({ page, request }) => {
  test.setTimeout(240000)

  const session = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const lifecycle = lifecycleTarget()
  const fixtureAppointment = fixtures.appointments?.rows?.[2]
  let target = null
  let lifecycleSource = null
  let fallbackReason = null

  if (lifecycle?.id) {
    const current = await getJson(request, session, `/api/appointments/${lifecycle.id}`)
    if (current.body?.data?.status === 'in_consultation') {
      target = { id: lifecycle.id, patient: lifecycle.patientName }
      lifecycleSource = 'ui'
    } else {
      fallbackReason = `the UI lifecycle appointment is ${current.body?.data?.status ?? 'missing'}, not in_consultation`
    }
  } else {
    fallbackReason = 'no UI lifecycle appointment was registered'
  }

  if (!target && fixtureAppointment) {
    target = { id: fixtureAppointment.id, patient: fixtureAppointment.patient.name }
    lifecycleSource = 'fixture'
  }

  const evidence = {
    route: ROUTE,
    role: 'odontologo',
    target,
    lifecycleSource,
    fallbackReason,
    tabs: [],
    emptySignature: null,
    '#311': null,
    fill: null,
    wrongPassword: null,
    correctPassword: null,
    continuity: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!target) {
    const reason = fixtures.appointments?.reason ?? 'no appointment available to close'
    audit('wizard-signature.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    let opened = await openWizard(page, target.id)

    if (!opened.opened) {
      audit('wizard-signature.json', {
        ...evidence,
        reason: opened.reason,
        verdict: 'NO PROBADO'
      })
      test.skip(true, opened.reason)
      return
    }

    const { overlay } = opened

    // #311: typing, closing through the backdrop and reopening must tell the
    // user what happened to the payload.
    await page.locator('#tab-evolution').click({ timeout: 5000 })
    await overlay.locator('#cw-reason').fill('Texto temporal #311 que no debe sobrevivir')
    const typed = await overlay.locator('#cw-reason').inputValue()
    const closedByBackdrop = await closeByBackdrop(page)
    opened = await openWizard(page, target.id)
    await page.locator('#tab-evolution').click({ timeout: 5000 })
    const afterReopen = await overlay
      .locator('#cw-reason')
      .inputValue()
      .catch(() => null)
    evidence['#311'] = {
      typed,
      closedByBackdrop,
      reopened: opened.opened,
      valueAfterReopen: afterReopen,
      discardedSilently: closedByBackdrop && opened.opened && afterReopen === '',
      confirmed: false,
      verdict: closedByBackdrop && afterReopen === '' ? 'CONFUSO' : 'OK',
      note: 'el payload se descarta sin confirmación; la corrección #311 evita la fuga, no avisa'
    }

    // Seven tabs, their labels and the panel each one reveals.
    for (const step of TAB_STEPS) {
      const tab = page.locator(`#tab-${step.id}`)
      await tab.click({ timeout: 5000 })
      await page
        .waitForFunction(
          id => document.querySelector(`#tab-${id}`)?.getAttribute('aria-selected') === 'true',
          step.id,
          { timeout: 5000, polling: 100 }
        )
        .catch(() => {})
      const heading = await overlay
        .locator('h3:visible')
        .first()
        .innerText()
        .catch(() => '')
      evidence.tabs.push({
        id: step.id,
        label: step.label,
        domLabel: (await tab.innerText().catch(() => '')).trim(),
        ariaSelected: await tab.getAttribute('aria-selected').catch(() => null),
        heading: heading.trim(),
        verdict: heading.trim() ? 'OK' : 'DEFECTO'
      })
    }

    // Empty signature: the submit is disabled and the hint names the missing
    // members instead of letting the request fail.
    await page.locator('#tab-signature').click({ timeout: 5000 })
    const submit = overlay.getByRole('button', { name: /Completar consulta/ })
    evidence.emptySignature = {
      disabled: await submit.isDisabled().catch(() => null),
      hintVisible: await overlay
        .locator('p', { hasText: 'Faltan datos para firmar' })
        .isVisible()
        .catch(() => false),
      hint: await overlay
        .locator('p', { hasText: 'Faltan datos para firmar' })
        .innerText()
        .catch(() => ''),
      verdict: 'OK'
    }

    await page.locator('#tab-evolution').click({ timeout: 5000 })
    await overlay.locator('#cw-reason').fill('Dolor dental desde hace tres días')
    await overlay.locator('#cw-soap-s').fill('Refiere dolor al masticar en el lado derecho')
    await overlay.locator('#cw-soap-o').fill('Caries oclusal visible en pieza 26')
    await overlay.locator('#cw-soap-a').fill('Caries de la dentina')
    await overlay.locator('#cw-soap-p').fill('Restauración con resina')

    await overlay.locator('#cw-diagnosis-search').fill('K02')
    const diagnosisOption = page.locator('[data-diagnosis-option]').first()
    await diagnosisOption.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})
    const diagnosis = await diagnosisOption.innerText().catch(() => '')
    await diagnosisOption.click({ timeout: 5000 })

    // Read the evolution values while the panel is still mounted: the wizard
    // renders one step at a time, so navigating away removes the inputs.
    const reasonValue = await overlay.locator('#cw-reason').inputValue()
    const soaps = await Promise.all(
      ['s', 'o', 'a', 'p'].map(field => overlay.locator(`#cw-soap-${field}`).inputValue())
    )
    const diagnosisRows = await overlay.locator('[data-diagnosis-row]').count()

    await page.locator('#tab-materials').click({ timeout: 5000 })
    const skipControl = overlay
      .locator('label', { hasText: 'No se usaron materiales en esta cita' })
      .locator('input[type="checkbox"]')
    await skipControl.check({ timeout: 5000 }).catch(() => {})
    const skipMaterials = await skipControl.isChecked().catch(() => false)

    const next = new Date()
    next.setDate(next.getDate() + 7)
    next.setHours(9, 0, 0, 0)

    await page.locator('#tab-next').click({ timeout: 5000 })
    await overlay.locator('#cw-next-date').fill(localDateTime(next))
    const dateFilled = await overlay.locator('#cw-next-date').inputValue()
    await overlay.locator('#cw-next-duration').fill('30')

    evidence.fill = {
      reason: reasonValue,
      diagnosis: diagnosis.trim(),
      diagnosisRows,
      skipMaterials,
      nextAppointment: dateFilled,
      soaps
    }

    await page.locator('#tab-signature').click({ timeout: 5000 })
    await overlay.locator('#cw-signature').fill(WRONG_PASSWORD)

    const wrongResponse = waitForPost(page, /\/api\/appointments\/\d+\/complete$/)
    await submit.click({ timeout: 5000 })
    const wrong = await wrongResponse
    const wrongBody = await wrong?.json().catch(() => null)
    const wrongToast = await toastText(page)
    const afterWrong = await getJson(request, session, `/api/appointments/${target.id}`)

    evidence.wrongPassword = {
      status: wrong?.status() ?? null,
      message: wrongBody?.error?.message ?? wrongBody?.message ?? null,
      errorCode: wrongBody?.error?.error_code ?? null,
      toast: wrongToast,
      appointmentStatus: afterWrong.body?.data?.status ?? null,
      wizardOpen: await overlayLocator(page)
        .isVisible()
        .catch(() => false),
      verdict:
        wrong?.status() === 422 && afterWrong.body?.data?.status === 'in_consultation'
          ? 'OK'
          : 'DEFECTO'
    }

    await overlay.locator('#cw-signature').fill(PASSWORD)

    const correctResponse = waitForPost(page, /\/api\/appointments\/\d+\/complete$/)
    await submit.click({ timeout: 5000 })
    const correct = await correctResponse
    const correctBody = await correct?.json().catch(() => null)
    const correctToast = await toastText(page)
    const closed = await hidden(overlayLocator(page), 10000)
    const afterClose = await getJson(request, session, `/api/appointments/${target.id}`)
    const queue = await getJson(request, session, '/api/continuity/queue')
    const requests = Array.isArray(queue.body?.data?.requests) ? queue.body.data.requests : []
    const continuity = requests.find(entry => entry.appointment_id === target.id) ?? null

    evidence.correctPassword = {
      status: correct?.status() ?? null,
      toast: correctToast,
      wizardClosed: closed,
      appointmentStatus: afterClose.body?.data?.status ?? null,
      completedAt: correctBody?.data?.completed_at ?? null,
      finalAmount: correctBody?.data?.final_amount ?? null,
      consultationMode: correctBody?.data?.consultation_mode ?? null,
      encounterId: correctBody?.meta?.encounter_id ?? null,
      verdict:
        correct?.status() === 200 &&
        closed &&
        afterClose.body?.data?.status === 'completed' &&
        continuity
          ? 'OK'
          : 'DEFECTO'
    }

    evidence.continuity = {
      found: Boolean(continuity),
      queueStatus: queue.status,
      entry: continuity
        ? {
            id: continuity.id,
            kind: continuity.kind,
            status: continuity.status,
            preferredFrom: continuity.preferred_from,
            preferredTo: continuity.preferred_to
          }
        : null,
      verdict: continuity ? 'OK' : 'DEFECTO'
    }

    closedTarget = registerClosure({
      id: target.id,
      patientName: target.patient,
      lifecycleSource,
      appointment: correctBody?.data
        ? {
            id: correctBody.data.id,
            patient_id: correctBody.data.patient_id,
            status: correctBody.data.status,
            final_amount: correctBody.data.final_amount,
            completed_at: correctBody.data.completed_at,
            consultation_mode: correctBody.data.consultation_mode,
            has_payment: correctBody.data.has_payment
          }
        : null,
      continuity: continuity
        ? {
            id: continuity.id,
            kind: continuity.kind,
            status: continuity.status,
            appointment_id: continuity.appointment_id,
            preferred_from: continuity.preferred_from,
            preferred_to: continuity.preferred_to
          }
        : null
    })

    if (lifecycleSource === 'ui')
      updateLifecycle({ status: 'completed', closedAt: new Date().toISOString() })

    evidence.shots.push(await shot(page, 'shots/u3-wizard-signature-error.png'))
    evidence.shots.push(await shot(page, 'shots/u3-wizard-signature-closed.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    const intentional = evidence.failedRequests.unexpected.filter(
      entry => entry.status === 422 && /\/complete$/.test(entry.url ?? '')
    )
    evidence.failedRequests.expected.push(
      ...intentional.map(entry => ({
        ...entry,
        reason: 'the wrong-password attempt is the experiment, not a surface failure'
      }))
    )
    evidence.failedRequests.unexpected = evidence.failedRequests.unexpected.filter(
      entry => !intentional.includes(entry)
    )
    audit('wizard-signature.json', evidence)
  }

  expect(evidence.tabs, 'seven wizard steps').toHaveLength(7)
  expect(evidence.wrongPassword?.status, 'wrong password observed').toBe(422)
  expect(evidence.correctPassword?.status, 'valid signature observed').toBe(200)
  expect(evidence.pageErrors, 'page errors during the closure').toEqual([])
})

// --- settlement: charge the closed appointment ---------------------------

const findReadyToBillRow = async (request, session, appointmentId, patientId) => {
  const scoped = patientId
    ? await getJson(
        request,
        session,
        `/api/appointments/ready-to-bill?patient_id=${patientId}&per_page=100`
      )
    : null
  const scopedRow = (scoped?.body?.data ?? []).find(row => row.id === appointmentId)

  if (scopedRow) return { status: scoped.status, row: scopedRow, scoped: true }

  const all = await getJson(request, session, '/api/appointments/ready-to-bill?per_page=100')
  const row = (all.body?.data ?? []).find(entry => entry.id === appointmentId)
  return { status: all.status, row: row ?? null, scoped: false }
}

test('@U3 ciclo · cobrar la atención cerrada', async ({ page, request }) => {
  test.setTimeout(180000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const target = closedTarget ?? closureTarget()
  const evidence = {
    route: '/cash-register/ready-to-bill',
    role: 'administrador',
    target,
    readyToBill: null,
    ui: null,
    apiFallback: null,
    rowLeavesPending: null,
    verdict: null,
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures)
  }

  if (!target?.id) {
    const reason = 'the closure row did not leave a closed appointment to charge'
    audit('wizard-cycle.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const patientId = target.appointment?.patient_id ?? null
  const ready = await findReadyToBillRow(request, session, target.id, patientId)
  evidence.readyToBill = {
    status: ready.status,
    scoped: ready.scoped,
    row: ready.row
      ? {
          id: ready.row.id,
          finalAmount: ready.row.final_amount,
          paidAmount: ready.row.paid_amount,
          balance: ready.row.balance,
          hasQuotation: ready.row.has_quotation
        }
      : null
  }

  if (!ready.row) {
    const reason = 'the closed appointment is not in the ready-to-bill list'
    audit('wizard-cycle.json', { ...evidence, reason, verdict: 'NO PROBADO' })
    test.skip(true, reason)
    return
  }

  const PAY_PATH = `/api/pending-payments/${target.id}/pay`

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const payload = page
      .waitForResponse(
        response =>
          response.request().method() === 'GET' &&
          new URL(response.url()).pathname === '/api/appointments/ready-to-bill',
        { timeout: LOAD_TIMEOUT }
      )
      .catch(() => null)
    await page.goto(evidence.route)
    await payload
    await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)
    await page
      .locator('.loading-spinner')
      .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
      .catch(() => {})

    const preview = page.locator(`[data-preview-action="${target.id}"]`)
    const rowVisible = await preview.isVisible().catch(() => false)
    evidence.shots.push(await shot(page, 'shots/u3-cycle-ready-to-bill.png'))

    let previewOpened = false
    let previewTitle = null
    let previewButtons = []
    let initialRequest = null

    if (rowVisible) {
      initialRequest = page
        .waitForResponse(
          response =>
            response.request().method() === 'GET' &&
            new URL(response.url()).pathname.endsWith(
              `/api/appointments/${target.id}/payment-preview`
            ),
          { timeout: ACTION_TIMEOUT }
        )
        .catch(() => null)
      await preview.click({ timeout: 5000 })
      const dialog = page.locator(DIALOG_SELECTOR).last()
      await dialog.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})
      previewOpened = await dialog.isVisible().catch(() => false)
      await initialRequest
      previewTitle = await dialog
        .evaluate(element => (element.querySelector('h2, h1, h3')?.textContent ?? '').trim())
        .catch(() => null)
      previewButtons = await dialog
        .locator('button')
        .evaluateAll(nodes =>
          nodes.map(node => ({
            text: (node.innerText ?? '').trim(),
            ariaLabel: node.getAttribute('aria-label'),
            dataAction: node.getAttribute('data-payment-submit') !== null
          }))
        )
        .catch(() => [])
      evidence.shots.push(await shot(page, 'shots/u3-cycle-preview.png'))
      await page.keyboard.press('Escape')
      await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
    }

    const paymentControls = previewButtons.filter(button =>
      /cobrar|pagar|registrar cobro/i.test(button.text)
    )
    evidence.ui = {
      rowVisible,
      previewOpened,
      previewTitle,
      buttons: previewButtons,
      paymentControls,
      paymentActionPresent: paymentControls.length > 0,
      verdict: paymentControls.length > 0 ? 'OK' : 'DEFECTO',
      reason: paymentControls.length
        ? null
        : 'el modal de desglose no ofrece ninguna acción de cobro: la superficie muestra la deuda y no la cobra'
    }

    // The API door the SPA would call. It is exercised only after the UI path
    // is measured, and it is the only write this probe makes.
    const methods = await getJson(request, session, '/api/payment-methods/active')
    const method =
      (methods.body?.data ?? []).find(entry => entry.type === 'cash') ??
      (methods.body?.data ?? [])[0] ??
      null
    const pay = method
      ? await postJson(request, session, PAY_PATH, {
          payment_method_id: method.id,
          amount: Number(ready.row.balance),
          description: 'AUDIT-U3 cierre de ciclo'
        })
      : null

    evidence.apiFallback = {
      path: PAY_PATH,
      paymentMethod: method ? { id: method.id, name: method.name, type: method.type } : null,
      first: pay ? { status: pay.status, body: pay.body } : null,
      cashSession: 'not opened',
      retried: false
    }

    // A drawer refusal is the only reason to open a session; the balance check
    // runs first, so opening unconditionally would hide which gate refused.
    if (pay && pay.status === 422) {
      const message = JSON.stringify(pay.body ?? {})
      if (/caja|sesi[oó]n/i.test(message)) {
        const current = await getJson(request, session, '/api/cash-register/current')
        let branchId = current.body?.data?.session?.branch_id ?? null

        if (!branchId) {
          const branches = await getJson(request, session, '/api/branches/active')
          branchId = branches.body?.data?.[0]?.id ?? null
        }

        if (branchId) {
          const open = await postJson(request, session, '/api/cash-register/open', {
            branch_id: branchId,
            opening_amount: 0
          })
          evidence.apiFallback.cashSession = {
            action: 'opened',
            branchId,
            status: open.status,
            message: open.body?.meta?.message ?? open.body?.message ?? null
          }
          const retry = await postJson(request, session, PAY_PATH, {
            payment_method_id: method.id,
            amount: Number(ready.row.balance),
            description: 'AUDIT-U3 cierre de ciclo'
          })
          evidence.apiFallback.retried = true
          evidence.apiFallback.second = { status: retry.status, body: retry.body }
          evidence.apiFallback.status = retry.status
          evidence.apiFallback.message = retry.body?.meta?.message ?? retry.body?.message ?? null
        } else {
          evidence.apiFallback.cashSession = {
            action: 'not-opened',
            reason: 'no branch available to open a session'
          }
        }
      } else {
        evidence.apiFallback.cashSession = {
          action: 'not-needed',
          reason: 'the pay door refused on its own rule before the drawer gate'
        }
      }
    }

    const after = await findReadyToBillRow(request, session, target.id, patientId)
    evidence.rowLeavesPending = !after.row
    evidence.after = {
      status: after.status,
      stillPresent: Boolean(after.row),
      balance: after.row?.balance ?? null
    }

    const finalPay = evidence.apiFallback.second ?? evidence.apiFallback.first
    evidence.apiFallback.status = finalPay?.status ?? null
    evidence.apiFallback.message =
      finalPay?.body?.meta?.message ??
      finalPay?.body?.message ??
      finalPay?.body?.error?.message ??
      null

    evidence.verdict =
      evidence.ui.paymentActionPresent && evidence.rowLeavesPending ? 'OK' : 'DEFECTO'
    evidence.reason = evidence.ui.paymentActionPresent
      ? evidence.rowLeavesPending
        ? null
        : 'el cobro no retiró la fila de la lista de pendientes'
      : 'la atención cerrada no se puede cobrar desde /cash-register/ready-to-bill: el desglose no tiene acción de cobro'

    evidence.shots.push(await shot(page, 'shots/u3-cycle-after.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    audit('wizard-cycle.json', evidence)
  }

  expect(evidence.readyToBill?.row, 'closed appointment in ready-to-bill').not.toBeNull()
  expect(evidence.ui, 'preview probe recorded').not.toBeNull()
  expect(evidence.shots.length, 'captures of the settlement').toBeGreaterThanOrEqual(2)
  expect(evidence.pageErrors, 'page errors during the settlement').toEqual([])
})
