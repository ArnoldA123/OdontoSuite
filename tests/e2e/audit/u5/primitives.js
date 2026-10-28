// Plan #13 (issue #368, U5) — layer 3: the money dialogs.
//
// Every dialog the manifest declares for the two surfaces is opened through its
// own hook and measured with the harness contract: centered (or edge-docked),
// single `h1` on the page, focus trapped, scroll locked, accessible name and
// Escape closes. A violation stays in the evidence as the measured fact it is.
//
// A dialog that cannot be opened is `NO PROBADO` with its reason — never a
// thrown failure. The walkthroughs leave no drawer open, and the seeded drawers
// belong to whichever users the seeder picked, so this module opens the drawer
// each dialog needs as a declared precondition (API, opening 50.00) and closes
// it again when the probe finishes; the session and its movement stay in the
// audit DB as declared residue. `Abrir caja` is still measured on the desk that
// has no drawer: the administrator's hero answers another user's session (the
// walkthrough records that defect), so the fallback is declared in the entry.
//
// Asserted here only that the probe ran to completion and that no page error was
// raised while opening the dialogs.

import { test, expect } from 'playwright/test'

import {
  DIALOG_SELECTOR,
  assertDialogContract,
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary, appointmentByKey } from './fixtures.js'

import {
  CASH_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  RTB_ROUTE,
  THRESHOLD,
  audit,
  call,
  openTab,
  settleCash,
  shot,
  stamp,
  waitForCall
} from './helpers.js'

const PRECONDITION_OPENING = 50

const preconditions = []

/**
 * Opens a drawer for this desk when it has none, through the documented API.
 *
 * The walkthroughs close their own sessions, so the dialog probe cannot assume
 * a drawer: it creates one (declared precondition) and the caller closes it
 * after measuring. A desk that already owns an open drawer is used as is.
 */
const ensureDrawer = async (request, session, reason) => {
  const current = await call(request, session, 'GET', '/api/cash-register/current')
  const own = current.body?.data?.session ?? null

  if (own && own.status === 'open' && Number(own.user_id) === Number(session.user.id)) {
    return {
      role: session.role,
      reason,
      source: 'existing',
      created: false,
      sessionId: own.id,
      branchId: own.branch_id
    }
  }

  const branches = await call(request, session, 'GET', '/api/branches/active')
  const ids = (branches.body?.data ?? [])
    .map(branch => Number(branch?.id))
    .filter(id => Number.isInteger(id) && id > 0)
  const attempts = []

  for (const branchId of ids) {
    const opened = await call(request, session, 'POST', '/api/cash-register/open', {
      branch_id: branchId,
      opening_amount: PRECONDITION_OPENING,
      opening_notes: 'U5 primitives precondition: dialog contract needs an open drawer'
    })

    attempts.push({
      branchId,
      status: opened.status,
      message: opened.message,
      sessionId: opened.body?.data?.id ?? null
    })

    if (opened.ok) {
      const entry = {
        role: session.role,
        reason,
        source: 'opened',
        created: true,
        sessionId: opened.body?.data?.id ?? null,
        branchId,
        opening: PRECONDITION_OPENING,
        attempts
      }

      preconditions.push(entry)
      return entry
    }
  }

  const entry = {
    role: session.role,
    reason,
    source: 'unavailable',
    created: false,
    sessionId: null,
    branchId: null,
    attempts
  }

  preconditions.push(entry)
  return entry
}

/**
 * Closes a drawer this module opened, counted exactly at its expected amount.
 * A drawer the seeder left open is never touched.
 */
const releaseDrawer = async (request, session, entry) => {
  if (!entry?.created || !entry.sessionId) return null

  const current = await call(request, session, 'GET', '/api/cash-register/current')
  const sessionId = current.body?.data?.session?.id ?? entry.sessionId
  const expected = Number(current.body?.data?.summary?.expected_amount ?? entry.opening)
  const closed = await call(request, session, 'POST', '/api/cash-register/close', {
    session_id: sessionId,
    closing_amount: expected,
    closing_notes: 'U5 primitives cleanup: dialog contract precondition',
    arqueo: null
  })

  return {
    sessionId,
    expected,
    status: closed.status,
    ok: closed.ok,
    message: closed.message
  }
}

const closeDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 4000 }).catch(() => {})
}

const entries = []
let pageErrors = []
let consoleErrors = []

const openLedger = async (page, session) => {
  await page.setViewportSize(DESKTOP)
  await primeSession(page, session)
  const primary = waitForCall(page, 'GET', '/api/cash-register/current', () => true, LOAD_TIMEOUT)
  await page.goto(CASH_ROUTE)
  await primary
  await settleCash(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
  // The hero's buttons are gated by `sessionLoading`, so the first paint after
  // the drawer answers still hides them for a frame.
  await page.waitForTimeout(600)
}

/**
 * Clicks the trigger and measures the dialog contract. Returns the evidence row
 * even when nothing opens; the caller never has to catch.
 */
const contractFor = async (page, { slug, label, kind, locator, note, expectation }) => {
  const row = {
    route: page.url().includes('ready-to-bill') ? RTB_ROUTE : CASH_ROUTE,
    slug,
    label,
    kind,
    surface: page.url(),
    expected: expectation ?? 'a modal that satisfies the dialog contract',
    opened: false,
    dialog: null,
    checks: [],
    contractFailures: [],
    outcome: 'no-dialog',
    note: note ?? null,
    shot: null
  }

  try {
    const visible = await locator
      .waitFor({ state: 'visible', timeout: 6000 })
      .then(() => true)
      .catch(() => false)

    if (!visible) {
      const present = await locator.count().catch(() => 0)
      const disabled = present
        ? await locator
            .first()
            .isDisabled()
            .catch(() => null)
        : null

      row.outcome = 'trigger-not-visible'
      row.present = present
      row.disabled = disabled
      row.reason = present
        ? `the trigger is rendered but ${disabled ? 'disabled' : 'hidden'}: the drawer this dialog needs is not open`
        : 'the trigger is not rendered for this role in this state'
      return row
    }

    if (!(await locator.isEnabled().catch(() => true))) {
      row.outcome = 'trigger-disabled'
      row.reason = 'the trigger is disabled for this role in this state'
      return row
    }

    await locator.click({ timeout: 10000 })
    const dialog = page.locator(DIALOG_SELECTOR).last()
    const opened = await dialog
      .waitFor({ state: 'visible', timeout: 10000 })
      .then(() => true)
      .catch(() => false)

    if (!opened) {
      row.outcome = 'no-dialog'
      row.reason = 'the click opened no visible dialog'
      return row
    }

    row.opened = true
    row.outcome = 'measured'
    row.shot = await shot(page, `primitives/${slug}.png`)

    const contract = await assertDialogContract(page, { dialog })
    row.dialog = contract.dialog
    row.checks = contract.checks
    row.contractFailures = contract.checks.filter(check => check.ok === false)

    return row
  } catch (thrown) {
    row.outcome = 'probe-error'
    row.reason = String(thrown?.message ?? thrown)
    return row
  } finally {
    await closeDialog(page)
  }
}

test('@U5 primitives · dialogs', async ({ page, request }) => {
  test.setTimeout(420000)

  const fixtures = await ensureFixtures(request)
  const finanzas = await loginAs(request, 'finanzas')
  const recepcion = await loginAs(request, 'recepcionista')
  const diagnostics = collectDiagnostics(page)
  const a1 = appointmentByKey(fixtures, 'a1')
  const cleanup = []
  let recepcionDrawer = null
  let finanzasDrawer = null

  try {
    // --- abrir caja: the desk that still has no drawer ---------------------
    await openLedger(page, finanzas)
    entries.push(
      await contractFor(page, {
        slug: 'cash-open',
        label: 'Abrir caja',
        kind: 'open',
        locator: page.locator('[data-hero-action="open"]'),
        note:
          'measured on finanzas: the administrator hero is hidden because the API answers the open session of ' +
          'another user, which is the defect the walkthrough records'
      })
    )

    // --- recepción: close, deposit and transaction need her own drawer ----
    recepcionDrawer = await ensureDrawer(
      request,
      recepcion,
      'Cerrar caja, Nuevo depósito and Nueva transacción need an open drawer'
    )
    await openLedger(page, recepcion)

    entries.push(
      await contractFor(page, {
        slug: 'cash-close',
        label: 'Cerrar caja',
        kind: 'action',
        locator: page.locator('[data-hero-action="close"]')
      })
    )

    entries.push(
      await contractFor(page, {
        slug: 'cash-deposit',
        label: 'Nuevo depósito',
        kind: 'create',
        locator: page.locator('[data-hero-action="deposit"]')
      })
    )

    await openTab(page, 'Transacciones')
    entries.push(
      await contractFor(page, {
        slug: 'cash-transaction',
        label: 'Nueva transacción',
        kind: 'create',
        locator: page.locator('[data-section-action="transaction"]')
      })
    )

    await openTab(page, 'Cobros')
    entries.push(
      await contractFor(page, {
        slug: 'cash-payment',
        label: 'Nuevo cobro',
        kind: 'create',
        locator: page.locator('[data-section-action="payment"]')
      })
    )

    // --- nuevo movimiento: finance-only door, so finanzas needs its drawer --
    finanzasDrawer = await ensureDrawer(
      request,
      finanzas,
      'Nuevo movimiento is finance-only and the walkthrough closed its drawer'
    )
    await openLedger(page, finanzas)
    await openTab(page, 'Movimientos')
    entries.push(
      await contractFor(page, {
        slug: 'cash-movement',
        label: 'Nuevo movimiento',
        kind: 'create',
        locator: page.locator('[data-section-action="movement"]'),
        note:
          'measured on finanzas (the only desk with `manage.cash_movement`); the precondition drawer is declared ' +
          'in `preconditions` and released in `cleanup`'
      })
    )

    // --- listos para facturar ---------------------------------------------
    const rtbPrimary = waitForCall(
      page,
      'GET',
      '/api/appointments/ready-to-bill',
      () => true,
      LOAD_TIMEOUT
    )
    await page.goto(RTB_ROUTE)
    await rtbPrimary
    await settleCash(page, '[data-rtb-hero]')
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    // The walkthrough already collected A1, so the list offers the row that is
    // still billable: the first one the table renders.
    const previewFallback = page.locator('[data-preview-action]').first()
    const previewTarget = a1?.id
      ? page.locator(`[data-preview-action="${a1.id}"]`)
      : previewFallback
    const previewLocator = (await previewTarget.count().catch(() => 0))
      ? previewTarget
      : previewFallback
    const previewAppointment = await previewLocator
      .getAttribute('data-preview-action', { timeout: 4000 })
      .catch(() => null)

    entries.push(
      await contractFor(page, {
        slug: 'rtb-preview',
        label: 'Desglose',
        kind: 'action',
        locator: previewLocator,
        note:
          'the breakdown loads over the API while the modal is open, so the contract is measured on its final paint; ' +
          `row: ${previewAppointment ?? 'none'}`
      })
    )

    entries.push(
      await contractFor(page, {
        slug: 'rtb-patient-filter',
        label: 'Filtrar por paciente',
        kind: 'action',
        locator: page.locator('[data-filter-patient]')
      })
    )
  } finally {
    await closeDialog(page)
    ;({ pageErrors, consoleErrors } = diagnostics)

    for (const entry of [recepcionDrawer, finanzasDrawer]) {
      const session = entry?.role === 'finanzas' ? finanzas : recepcion
      const released = entry ? await releaseDrawer(request, session, entry) : null
      if (released) cleanup.push({ role: entry.role, ...released })
    }

    const failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    const contractFailures = entries.reduce(
      (total, entry) => total + (entry.contractFailures ?? []).length,
      0
    )

    for (const entry of entries) {
      audit(`primitives/${entry.slug}.json`, { ...stamp(), ...entry })
    }

    audit('primitives.json', {
      ...stamp(),
      stage: 'layer-3',
      routes: [CASH_ROUTE, RTB_ROUTE],
      declared: entries.length,
      opened: entries.filter(entry => entry.opened).length,
      notProbed: entries
        .filter(entry => entry.outcome !== 'measured')
        .map(entry => ({ slug: entry.slug, outcome: entry.outcome, reason: entry.reason })),
      contractFailures,
      entries,
      preconditions,
      cleanup,
      fixtures: fixtureSummary(fixtures),
      pageErrors,
      consoleErrors,
      failedRequests
    })
  }

  expect(entries.length, 'dialog entries measured').toBe(8)
  expect(entries.filter(entry => entry.opened).length, 'dialogs opened').toBeGreaterThanOrEqual(6)
  expect(pageErrors, 'page errors while opening the dialogs').toEqual([])
})
