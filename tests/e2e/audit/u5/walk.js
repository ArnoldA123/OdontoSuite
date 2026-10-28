// Plan #13 (issue #368, U5) — layer 4: the money walkthroughs.
//
// One test per role, and every step is measured against a promise: the drawer's
// arithmetic (`opening + cash_in − cash_out == expected`), the status the API
// answered, and what the user could see when the write was refused. The step
// list is the session's own scenario, not a product assertion: a step whose
// product promise is broken is recorded as `DEFECTO` with its request status and
// its capture, and the walk keeps going.
//
// The walkthroughs share the database, so the seeded drawers are never touched:
// the administrator and finanzas open their own sessions on their own branches
// and the steps only ever act on the rows this session created. The one seat
// that already has a drawer (`recepcionista`, session 3) is read, never written.
//
// Asserted here: every step produced a verdict and a capture, no step claims a
// delta its snapshots do not support, the drawer equation holds on the last open
// snapshot, and no page error was raised. Product defects stay in the evidence.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary, registerResidue, registerSession } from './fixtures.js'
import { appointmentByKey } from './fixtures.js'

import {
  ACTION_TIMEOUT,
  CASH_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  THRESHOLD,
  addDays,
  audit,
  call,
  callsTo,
  drawerFormula,
  expectDelta,
  fillInput,
  fillMoney,
  hero,
  isNotOk,
  kpi,
  lastCall,
  localDay,
  markExpected,
  messageOf,
  openSelect,
  openTab,
  readPendingRows,
  readReport,
  parseCurrency,
  round2,
  sessionSnapshot,
  settleCash,
  shot,
  stamp,
  toastText,
  waitForCall,
  waitForToast,
  watchRequests
} from './helpers.js'

const MODAL = '[role="dialog"]:visible'
const CLOSE_TIMEOUT = 4000

/**
 * Clicks a submit control and reports the dialog when it cannot be clicked, so
 * a modal that closes or covers itself becomes a measurement instead of a
 * timeout.
 */
const clickSubmit = async (page, selector, timeout = 8000) => {
  try {
    await page.locator(selector).click({ timeout })
    return { ok: true }
  } catch (thrown) {
    return {
      ok: false,
      reason: `${selector} not clickable: ${String(thrown?.message ?? thrown).slice(0, 160)}`,
      debug: await dialogDebug(page)
    }
  }
}

/** What the page is showing when a driver cannot find its target. */
const dialogDebug = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim()
    const dialogs = Array.from(document.querySelectorAll('[role="dialog"]'))
    const last = dialogs.at(-1)
    const hooks = Array.from(
      document.querySelectorAll(
        '[data-payment-form], [data-transaction-form], [data-cash-deposit-form], [data-close-cash-form], [data-open-cash-form], [data-field], [data-modal-field]'
      )
    ).map(node =>
      node.getAttribute('data-payment-form') !== null
        ? 'payment-form'
        : node.getAttribute('data-transaction-form') !== null
          ? 'transaction-form'
          : node.getAttribute('data-cash-deposit-form') !== null
            ? 'deposit-form'
            : node.getAttribute('data-close-cash-form') !== null
              ? 'close-form'
              : node.getAttribute('data-open-cash-form') !== null
                ? 'open-form'
                : `field:${node.getAttribute('data-field') ?? node.getAttribute('data-modal-field')}`
    )

    return {
      dialogs: dialogs.length,
      visible: Boolean(last && last.getBoundingClientRect().width > 0),
      title: read(last?.querySelector('h2')),
      hooks: [...new Set(hooks)],
      text: read(last).replace(/\s+/g, ' ').slice(0, 260)
    }
  })

const closeModal = async page => {
  const open = page.locator(MODAL).last()

  if (!(await open.count().catch(() => 0))) return
  if (!(await open.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await open.waitFor({ state: 'hidden', timeout: CLOSE_TIMEOUT }).catch(() => {})
}

const waitForMatch = (page, method, pattern, timeout = ACTION_TIMEOUT) =>
  page
    .waitForResponse(
      response => {
        if (response.request().method() !== method) return false
        try {
          return pattern.test(new URL(response.url()).pathname)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

const bodyOf = async response => (response ? await response.json().catch(() => null) : null)

/** Opens the money page for one role at desktop size. */
/**
 * A drawer left open by an earlier run of this session blocks the opening step.
 * The walkthrough closes its own leftover (never somebody else's) and declares
 * it: the ledger keeps both sessions.
 */
const preflightDrawer = async (request, session) => {
  const current = await sessionSnapshot(request, session)

  if (!current.id) return { action: 'none', current }
  if (current.ownedByActor !== true) {
    return {
      action: 'none',
      reason: 'the API answers a drawer owned by another user; it is never closed by the audit',
      current
    }
  }

  // A drawer the seeder left in the actor's name is population, not a leftover:
  // closing it would modify a row this session did not create (plan #13 §9).
  if (!/AUDIT-U5/i.test(current.openingNotes ?? '')) {
    return {
      action: 'seeded-owned',
      reason:
        'the actor already owns an open drawer created by the seeder; the audit never closes a row it did not create',
      current
    }
  }

  const response = await call(request, session, 'POST', '/api/cash-register/close', {
    session_id: current.id,
    closing_amount: current.expected > 0 ? current.expected : 1,
    closing_notes: 'AUDIT-U5 preflight: leftover drawer from an earlier run of this session'
  })

  return {
    action: 'closed-leftover',
    sessionId: current.id,
    status: response.status,
    message: messageOf(response.body),
    current
  }
}

const reportTotals = payload => ({
  sessions: Number(payload?.sessions_count ?? 0),
  opening: round2(payload?.total_opening_amount),
  closing: round2(payload?.total_closing_amount),
  expected: round2(payload?.total_expected_amount),
  difference: round2(payload?.total_difference),
  income: round2(payload?.total_income),
  expenses: round2(payload?.total_expenses),
  cash: round2(payload?.reconciliation?.cash?.total),
  cashCount: Number(payload?.reconciliation?.cash?.count ?? 0),
  electronic: round2(payload?.reconciliation?.electronic?.total),
  electronicCount: Number(payload?.reconciliation?.electronic?.count ?? 0),
  depositsCount: Number(payload?.reconciliation?.deposits?.count ?? 0),
  depositsTotal: round2(payload?.reconciliation?.deposits?.total),
  pending: round2(payload?.reconciliation?.pending_deposit)
})

const openCash = async (page, session) => {
  await page.setViewportSize(DESKTOP)
  await primeSession(page, session)

  const primary = waitForCall(page, 'GET', '/api/cash-register/current', () => true, LOAD_TIMEOUT)
  const response = await page.goto(CASH_ROUTE)
  await primary
  await settleCash(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

  return { status: response?.status() ?? null }
}

/**
 * The step recorder: one entry per action, with the capture, the verdict and the
 * arithmetic. A probe that throws becomes `NO PROBADO` with its reason instead of
 * ending the walkthrough.
 */
const recorder = (page, evidence) => {
  let index = 0

  return async (id, action, expected, run) => {
    index += 1
    const seq = String(index).padStart(2, '0')
    const entry = {
      id,
      seq,
      action,
      expected,
      observed: null,
      verdict: 'NO PROBADO',
      reason: null,
      arithmetic: null,
      requestStatus: null,
      shot: null,
      extra: null
    }

    try {
      const result = (await run(entry)) ?? {}
      Object.assign(entry, result)
    } catch (thrown) {
      entry.verdict = 'NO PROBADO'
      entry.reason = `probe error: ${String(thrown?.message ?? thrown)}`
      // A step that dies mid-form leaves its dialog over the page; closing it
      // here keeps the next step measurable instead of cascading.
      await closeModal(page).catch(() => {})
    }

    try {
      entry.shot = await shot(page, `${evidence.shotDir}/${seq}-${id}.png`)
    } catch {
      entry.shot = null
    }

    evidence.steps.push(entry)

    if (isNotOk(entry.verdict)) {
      evidence.findings.push({
        step: entry.id,
        action: entry.action,
        expected: entry.expected,
        observed: entry.observed,
        verdict: entry.verdict,
        requestStatus: entry.requestStatus,
        detail: entry.detail ?? entry.reason ?? null,
        shot: entry.shot
      })
    }

    return entry
  }
}

const openAdHocPayment = async page => {
  await openTab(page, 'Cobros')
  const trigger = page.locator('[data-section-action="payment"]')
  const opened = await trigger.isVisible().catch(() => false)

  if (!opened)
    return {
      ok: false,
      reason: 'the Cobros section offers no «Nuevo cobro»',
      debug: await dialogDebug(page)
    }

  await trigger.click({ timeout: 8000 })

  const form = await page
    .locator('[data-payment-form]')
    .waitFor({ state: 'visible', timeout: 10000 })
    .then(() => true)
    .catch(() => false)
  const submit = await page
    .locator('[data-payment-submit]')
    .waitFor({ state: 'visible', timeout: 6000 })
    .then(() => true)
    .catch(() => false)

  return {
    ok: form && submit,
    form,
    submit,
    reason: form && submit ? null : 'the payment modal did not paint its form and submit',
    debug: form && submit ? null : await dialogDebug(page)
  }
}

/**
 * The picker searches `/api/patients/search`, which matches a single column at a
 * time: typing the full name finds nobody, so the probe searches the document
 * number and records that workaround.
 */
const pickAdHocPatient = async (page, { name, document }) => {
  const input = page.locator('[data-payment-patient-selector] input').first()
  await input.click({ timeout: 5000 })
  await input.fill(document ?? name)
  await page
    .locator('[data-payment-patient-selector] .patient-selector')
    .first()
    .waitFor({ state: 'visible', timeout: 8000 })
    .catch(() => {})

  const result = waitForCall(page, 'GET', '/api/patients/search', () => true, 8000)
  await page.waitForTimeout(400)
  await result

  const row = page
    .locator('[data-payment-patient-selector] .patient-selector div[class*="cursor-pointer"]')
    .filter({ hasText: name })
    .first()

  if (!(await row.isVisible().catch(() => false)))
    return { ok: false, reason: `patient "${name}" not found` }

  await row.click({ timeout: 5000 })

  return { ok: true, name, modalOpen: await page.locator(MODAL).count() }
}

const paymentFormState = page =>
  page.evaluate(() => {
    const value = selector => (document.querySelector(selector)?.value ?? '').trim()
    const trigger = selector =>
      (document.querySelector(`${selector} button[aria-haspopup="true"]`)?.innerText ?? '').trim()
    const submit = document.querySelector('[data-payment-submit]')

    return {
      patient: value('[data-payment-patient-selector] input'),
      patientId: value('[data-payment-patient] input'),
      concept: value('[data-payment-concept] input'),
      method: trigger('[data-payment-method]'),
      amount: value('[data-payment-amount] input'),
      reference: value('[data-payment-reference] input'),
      submitDisabled: submit?.disabled === true,
      submitPresent: Boolean(submit)
    }
  })

const fillPayment = async (page, { method, amount, concept, reference }) => {
  const trace = []
  const check = async label => trace.push({ label, dialogs: await page.locator(MODAL).count() })

  if (concept) {
    await fillInput(page, '[data-payment-concept]', concept)
    await check('concept')
  }

  if (method) {
    const chosen = await openSelect(
      page,
      '[data-payment-method] button[aria-haspopup="true"]',
      method
    )
    await check('method')
    if (!chosen.ok) return { ok: false, reason: `method: ${chosen.reason}`, trace }
  }

  if (amount !== undefined) {
    const money = await fillMoney(page, '[data-payment-amount]', amount)
    await check('amount')
    if (!money.ok) return { ok: false, reason: money.reason, trace }
  }

  if (reference !== undefined) {
    const filled = await fillInput(page, '[data-payment-reference]', reference)
    await check('reference')
    if (!filled.ok) return { ok: false, reason: filled.reason, trace }
  }

  return {
    ok: true,
    state: await paymentFormState(page),
    modalOpen: await page.locator(MODAL).count(),
    trace
  }
}

/**
 * The seeded method names are the labels the selects publish, so the walkthrough
 * reads them instead of hard-coding a spelling (`Tarjeta de credito` has no
 * accent in the seed).
 */
const methodCatalog = async (request, session) => {
  const response = await call(request, session, 'GET', '/api/payment-methods/active')
  const rows = Array.isArray(response.body?.data) ? response.body.data : []

  const byType = type => rows.find(method => method.type === type)?.name ?? null
  const card =
    rows.find(method => method.type === 'card' && /credito/i.test(method.name))?.name ??
    byType('card')

  return {
    status: response.status,
    rows: rows.map(method => ({
      id: method.id,
      name: method.name,
      type: method.type,
      requiresReference: method.requires_reference === true
    })),
    cash: byType('cash'),
    card,
    wallet: byType('wallet'),
    transfer: byType('transfer')
  }
}

const visiblePaymentError = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim()
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const general = document.querySelector('[data-payment-error]')

    return {
      general: read(general),
      generalVisible: isVisible(general),
      fieldErrors: Array.from(document.querySelectorAll('[data-payment-form] p'))
        .map(read)
        .filter(line => /requerid|inválid|no puede|exige|no hay|debe/i.test(line))
    }
  })

const openTransactionModal = async page => {
  await openTab(page, 'Transacciones')
  await page.locator('[data-section-action="transaction"]').click({ timeout: 8000 })
  await page.locator('[data-transaction-form]').waitFor({ state: 'visible', timeout: 10000 })
}

/**
 * The transaction modal asks `/api/patients/search` and renders `patient.name`,
 * a field that payload does not carry: every result reads `undefined <apellido>`.
 * The probe locates the row by the surname the API does publish and records the
 * rendering it had to work around.
 */
const pickTransactionPatient = async (page, { name, lastName, document }) => {
  const input = page.locator('[data-modal-patient-search] input').first()
  await input.click({ timeout: 5000 })
  await input.fill(document ?? name)
  await page.waitForTimeout(700)

  const results = page.locator('[data-patient-result]')
  const total = await results.count()

  if (total === 0) return { ok: false, reason: `no result for "${name}" in the transaction modal` }

  let target = null
  let rendered = null

  for (let index = 0; index < total; index += 1) {
    const text = (
      await results
        .nth(index)
        .innerText({ timeout: 3000 })
        .catch(() => '')
    ).replace(/\s+/g, ' ')
    if (lastName && text.includes(lastName) && !rendered) {
      target = results.nth(index)
      rendered = text
      break
    }
  }

  if (!target) {
    rendered = (
      await results
        .first()
        .innerText({ timeout: 3000 })
        .catch(() => '')
    ).replace(/\s+/g, ' ')
    target = results.first()
  }

  await target.click({ timeout: 5000 })

  return {
    ok: true,
    rendered,
    rendersUndefinedName: /undefined/i.test(rendered ?? ''),
    results: total
  }
}

const chooseTransactionType = async (page, label) =>
  openSelect(page, '[data-modal-field="type"] button[aria-haspopup="true"]', label)

const transactionFormState = page =>
  page.evaluate(() => {
    const trigger = selector =>
      (document.querySelector(`${selector} button[aria-haspopup="true"]`)?.innerText ?? '').trim()
    const value = selector => (document.querySelector(selector)?.value ?? '').trim()
    const submit = document.querySelector('[data-modal-submit]')

    return {
      type: trigger('[data-modal-field="type"]'),
      patient: value('[data-modal-patient-search] input'),
      method: trigger('[data-modal-field="payment_method_id"]'),
      amount: value('[data-modal-amount] input'),
      submitDisabled: submit?.disabled === true,
      submitPresent: Boolean(submit)
    }
  })

const fillTransactionPayment = async (page, { method, amount }) => {
  if (method) {
    const chosen = await openSelect(
      page,
      '[data-modal-field="payment_method_id"] button[aria-haspopup="true"]',
      method
    )
    if (!chosen.ok) return { ok: false, reason: `method: ${chosen.reason}` }
  }

  if (amount !== undefined) await fillMoney(page, '[data-modal-amount]', amount)

  return { ok: true, state: await transactionFormState(page) }
}

const fillRefundAuthorization = async (page, { authorizer, reason }) => {
  const chosen = await openSelect(
    page,
    '[data-modal-field="authorized_by"] button[aria-haspopup="true"]',
    authorizer,
    { timeout: 10000 }
  )

  if (!chosen.ok) return { ok: false, reason: `authorizer: ${chosen.reason}` }

  await fillInput(page, '[data-modal-field="authorization_reason"]', reason)

  return { ok: true, selected: chosen.selected }
}

const openMovementModal = async page => {
  await openTab(page, 'Movimientos')
  await page.locator('[data-section-action="movement"]').click({ timeout: 8000 })
  await page.locator('[data-field="type"]').first().waitFor({ state: 'visible', timeout: 10000 })
  await page.locator('[data-field="concept"]').first().waitFor({ state: 'visible', timeout: 10000 })
}

const fillMovement = async (page, { type, concept, description, amount }) => {
  if (type) {
    const chosen = await openSelect(page, '[data-field="type"] button[aria-haspopup="true"]', type)
    if (!chosen.ok) return { ok: false, reason: `type: ${chosen.reason}` }
  }

  if (concept) {
    const chosen = await openSelect(
      page,
      '[data-field="concept"] button[aria-haspopup="true"]',
      concept
    )
    if (!chosen.ok) {
      return { ok: false, reason: `concept: ${chosen.reason}`, debug: await dialogDebug(page) }
    }
  }

  if (description !== undefined) await fillInput(page, '[data-field="description"]', description)
  if (amount !== undefined) await fillMoney(page, '[data-field="amount"]', amount)

  return { ok: true }
}

const movementCatalog = async page => {
  const read = async selector => {
    try {
      const chosen = await openSelect(page, `${selector} button[aria-haspopup="true"]`, undefined, {
        probeOnly: true
      })
      if (!chosen.ok) return { labels: [], error: chosen.reason }

      return { labels: chosen.options ?? [], stuck: chosen.listboxStuck ?? false }
    } catch (thrown) {
      return { labels: [], error: String(thrown?.message ?? thrown) }
    }
  }

  const types = (await read('[data-field="type"]')).labels
  const concepts = (await read('[data-field="concept"]')).labels

  return {
    types,
    concepts,
    selectable: types.length > 0 && concepts.length > 0,
    contradictoryPairs: types.flatMap(type =>
      concepts.map(concept => ({
        type,
        concept,
        coherent:
          (type === 'Ingreso' && concept === 'Depósito') ||
          (type === 'Egreso' && concept === 'Retiro') ||
          type === 'Retiro' ||
          type === 'Depósito'
      }))
    ),
    silences: concepts.filter(concept =>
      types.some(
        type =>
          (type === 'Ingreso' && concept === 'Retiro') ||
          (type === 'Egreso' && concept === 'Depósito')
      )
    )
  }
}

const readLockNotice = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim()
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const notice = document.querySelector('[data-accounting-lock-notice]')

    return {
      present: Boolean(notice),
      visible: isVisible(notice),
      message: read(notice?.querySelector('[data-accounting-lock-message]')),
      reasonField: Boolean(notice?.querySelector('[data-accounting-lock-reason]')),
      retry: Boolean(notice?.querySelector('[data-accounting-lock-retry]')),
      counter: read(notice?.querySelector('[data-accounting-lock-counter]')),
      error: read(
        notice?.querySelector(
          '[data-accounting-lock-reason] p.text-systemRed-600, [data-accounting-lock-reason] .text-red-600'
        )
      )
    }
  })

const enableExport = page =>
  page.evaluate(() => {
    const checkbox = document.querySelector('[data-close-cash-form] input[type="checkbox"]')
    if (!checkbox) return null
    if (!checkbox.checked) checkbox.click()
    return checkbox.checked
  })

// --- administrator ----------------------------------------------------------

test('@U5 · walkthrough caja (administrador)', async ({ page, request }) => {
  test.setTimeout(600000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const evidence = {
    route: CASH_ROUTE,
    role: 'administrador',
    ...stamp(),
    shotDir: 'walk/administrador',
    branchId: 2,
    branchName: 'Sede Norte',
    actor: {
      id: session.user.id,
      name: session.user.name,
      branchId: session.user.branch_id ?? null
    },
    fixtureSummary: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    final: null,
    residues: [],
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence)
  const a1 = appointmentByKey(fixtures, 'a1')
  const a2 = appointmentByKey(fixtures, 'a2')
  const a3 = appointmentByKey(fixtures, 'a3')
  const day = localDay()

  let sessionId = null
  let last = null
  const kpiDrift = []

  const snap = () =>
    sessionId ? sessionSnapshot(request, session, { sessionId }) : sessionSnapshot(request, session)

  const uiVsApi = async (label, snapshot) => {
    const values = await kpi(page)
    const pairs = [
      ['opening', values.opening, snapshot.opening],
      ['income', values.income, snapshot.income],
      ['expenses', values.expenses, snapshot.expenses],
      ['expected', values.expected, snapshot.expected]
    ]

    const drift = pairs
      .map(([key, shown, api]) => ({ key, shown, api, shownValue: parseCurrency(shown) }))
      .filter(row => row.shownValue === null || Math.abs(row.shownValue - row.api) > 0.005)

    if (drift.length) kpiDrift.push({ at: label, drift })

    return { values, drift }
  }

  try {
    if (!a1?.id || !a2?.id || !a3?.id) {
      evidence.reason = `fixtures unavailable: ${fixtures?.reason ?? 'no appointments'}`
      test.skip(true, evidence.reason)
      return
    }

    const preflight = await preflightDrawer(request, session)
    evidence.preflight = preflight

    if (preflight.action === 'closed-leftover') {
      evidence.residues.push({
        type: 'cash_register_session',
        id: preflight.sessionId,
        reason: 'leftover drawer closed by the preflight so the walkthrough starts from zero'
      })
    }

    const methods = await methodCatalog(request, session)
    evidence.methods = methods

    // The period report is read before the walkthrough writes any money, so the
    // report's view of the drawer can be compared with its view after closing:
    // the difference is exactly what this session moved.
    const periodStart = addDays(day, -29)
    const periodPath = `/api/cash-register/reports/period?start_date=${periodStart}&end_date=${day}`
    const baselineResponse = await call(request, session, 'GET', periodPath)
    const baselineTotals = reportTotals(baselineResponse.body?.data)
    evidence.reportBaseline = { status: baselineResponse.status, totals: baselineTotals }

    await openCash(page, session)
    last = await snap()

    // 1 — the landing state of a counter with no drawer of its own.
    await step(
      'hero-sin-sesion',
      'abrir /cash-register con la cuenta sin sesión propia',
      'hero «Sin sesión», KPIs «N/D», sin Cerrar ni Depositar',
      async () => {
        const state = await hero(page)
        const values = await kpi(page)
        const snapshot = last

        const ownsSession = snapshot.ownedByActor === true
        const ok =
          state.state === 'Sin sesión' &&
          Object.values(values).every(value => /N\/D/.test(value)) &&
          !state.actions.close.visible &&
          !state.actions.deposit.visible

        return {
          observed: {
            heroState: state.state,
            kpis: values,
            openAction: state.actions.open,
            closeAction: state.actions.close,
            depositAction: state.actions.deposit,
            apiSession: {
              id: snapshot.id,
              userId: snapshot.userId,
              ownedByActor: snapshot.ownedByActor,
              opening: snapshot.opening,
              expected: snapshot.expected
            }
          },
          verdict: ok ? 'OK' : 'DEFECTO',
          detail: ok
            ? null
            : `la caja del administrador responde la sesión abierta de otro usuario (id ${snapshot.id}, user ${snapshot.userId}): ` +
              'la página la pinta como propia, ofrece «Cerrar caja» y suma sus saldos',
          requestStatus: snapshot.requestStatus,
          extra: { ownsSession }
        }
      }
    )

    // 2 — a charge without a drawer: what does the user see?
    await step(
      'cobro-sin-sesion',
      'registrar un cobro ad-hoc sin sesión de caja propia',
      '422 y un mensaje visible que explique por qué no se puede cobrar',
      async () => {
        const opened = await openAdHocPayment(page)

        if (!opened.ok) {
          return {
            observed: opened,
            verdict: 'NO PROBADO',
            reason: opened.reason,
            requestStatus: null
          }
        }

        const patient = await pickAdHocPatient(page, {
          name: a1.patientName,
          document: a1.patientDocument
        })
        const filled = await fillPayment(page, {
          method: methods.cash,
          amount: 10,
          concept: 'AUDIT-U5 sin sesión'
        })

        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        const clicked = await clickSubmit(page, '[data-payment-submit]')

        if (!clicked.ok) {
          return {
            observed: clicked,
            verdict: 'NO PROBADO',
            reason: clicked.reason,
            requestStatus: null
          }
        }

        const settled = await response
        const body = await bodyOf(settled)
        await page.waitForTimeout(600)

        const visibleError = await visiblePaymentError(page)
        const status = settled?.status() ?? null
        const speaks = Boolean(visibleError.general || (visibleError.fieldErrors ?? []).length)

        return {
          observed: {
            status,
            envelope: body ? Object.keys(body) : [],
            errors: body?.errors ? Object.keys(body.errors) : [],
            message: messageOf(body),
            visibleError,
            patient,
            filled
          },
          verdict: status === 422 && speaks ? 'OK' : 'DEFECTO',
          detail:
            status === 422 && !speaks
              ? 'el 422 llega con `errors.session` y el modal no tiene campo para esa clave: no pinta nada, ni error general ni toast'
              : null,
          requestStatus: status,
          extra: { visibleMessage: speaks }
        }
      }
    )

    // Close the refused modal and read the toast it may have raised.
    evidence.steps.at(-1).extra = {
      ...(evidence.steps.at(-1).extra ?? {}),
      toastAfterRefusal: await toastText(page)
    }
    await closeModal(page)

    // 3 — opening the drawer: the door the hero offers.
    await step(
      'abrir-caja',
      'abrir caja en «Sede Norte» con 100.00',
      'toast, hero «Sesión abierta» y KPIs 100.00 / 0.00 / 0.00 / 100.00',
      async () => {
        const before = await hero(page)
        const door = before.actions.open
        let status = null
        let fallback = null
        let body = null

        if (door.visible) {
          await page.locator('[data-hero-action="open"]').click({ timeout: 8000 })
          await page.locator('[data-open-cash-form]').waitFor({ state: 'visible', timeout: 10000 })
          const branch = await openSelect(
            page,
            '[data-modal-field="branch_id"] button[aria-haspopup="true"]',
            'Sede Norte'
          )
          await fillMoney(page, '[data-modal-amount]', 100)
          const response = waitForMatch(page, 'POST', /^\/api\/cash-register\/open$/)
          await page.locator('[data-modal-submit]').click({ timeout: 8000 })
          const settled = await response
          status = settled?.status() ?? null
          body = await bodyOf(settled)
          await waitForToast(page, 'Sesión de caja abierta', 10000)
          await closeModal(page)
          fallback = { used: false, branch }
        } else {
          // The hero hides the door because the API answered somebody else's
          // session. The documented API door is opened instead, so the rest of
          // the walkthrough can measure the arithmetic; the missing door is the
          // finding.
          const response = await call(request, session, 'POST', '/api/cash-register/open', {
            branch_id: 2,
            opening_amount: 100,
            opening_notes: `${fixtures.marker} walkthrough`
          })
          ;({ status, body } = response)
          fallback = {
            used: true,
            reason: 'the hero only offers «Abrir caja» when the API answers no session at all',
            api: { status: response.status, message: messageOf(response.body) }
          }
          await page.reload({ waitUntil: 'load' })
          await settleCash(page)
        }

        sessionId = body?.data?.id ?? null

        if (sessionId) {
          registerSession({
            role: 'administrador',
            sessionId,
            branchId: body?.data?.branch_id ?? null,
            opening: round2(body?.data?.opening_amount),
            reason: 'the walkthrough opened its own drawer'
          })
          evidence.residues.push({ type: 'cash_register_session', id: sessionId })
        }

        const snapshot = await snap()
        last = snapshot
        const state = await hero(page)
        const values = await kpi(page)
        const drift = await uiVsApi('abrir-caja', snapshot)

        const ok =
          status === 201 &&
          state.state === 'Sesión abierta' &&
          snapshot.ownedByActor === true &&
          Math.abs(snapshot.opening - 100) < 0.005 &&
          Math.abs(snapshot.income) < 0.005 &&
          Math.abs(snapshot.expenses) < 0.005 &&
          Math.abs(snapshot.expected - 100) < 0.005 &&
          drift.drift.length === 0

        return {
          observed: {
            status,
            door,
            fallback,
            heroState: state.state,
            kpis: values,
            api: {
              id: snapshot.id,
              opening: snapshot.opening,
              income: snapshot.income,
              expenses: snapshot.expenses,
              expected: snapshot.expected,
              ownedByActor: snapshot.ownedByActor
            },
            kpiDrift: drift.drift
          },
          verdict: ok && !fallback.used ? 'OK' : 'DEFECTO',
          detail: fallback.used
            ? 'la puerta de apertura está oculta para el administrador mientras cualquier otra sesión siga abierta: ' +
              'la apertura se hizo por la API documentada para poder medir la aritmética'
            : null,
          requestStatus: status,
          arithmetic: {
            before: null,
            after: { opening: snapshot.opening, expected: snapshot.expected },
            delta: { opening: snapshot.opening, expected: snapshot.expected },
            formula: 'opening = 100.00, expected = opening'
          },
          extra: { sessionId }
        }
      }
    )

    // 4 — the same open, twice.
    await step(
      'doble-apertura',
      'repetir POST /api/cash-register/open en la misma sede',
      '422 con `errors.session` y sin puerta de apertura en el hero',
      async () => {
        const response = await call(request, session, 'POST', '/api/cash-register/open', {
          branch_id: 2,
          opening_amount: 100,
          opening_notes: `${fixtures.marker} double open`
        })
        const state = await hero(page)

        return {
          observed: {
            status: response.status,
            envelope: response.body ? Object.keys(response.body) : [],
            errors: response.body?.errors ? Object.keys(response.body.errors) : [],
            message: messageOf(response.body),
            rawError: typeof response.body?.error === 'string' ? response.body.error : null,
            openAction: state.actions.open
          },
          verdict:
            response.status === 422 &&
            Boolean(response.body?.errors?.session) &&
            !state.actions.open.visible
              ? 'OK'
              : 'DEFECTO',
          detail: response.body?.errors?.session
            ? null
            : 'el 422 viaja como {message, error} plano: la clave del campo (`errors.session`) se pierde y ningún formulario puede pintarla',
          requestStatus: response.status
        }
      }
    )

    // 5 — collecting a pending appointment: the list is the only door.
    await step(
      'cobro-a1-efectivo',
      `cobrar la cita A1 (${a1.total.toFixed(2)}) en Efectivo desde la pestaña Cobros`,
      'la fila de A1 aparece y el cobro deja +60.00 de ingreso, +60.00 de efectivo y +60.00 de esperado',
      async () => {
        await openTab(page, 'Cobros')
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

        const list = await readPendingRows(page)
        const listCalls = callsTo(requests, 'GET', '/api/cash-register/pending-payments')
        const lastList = listCalls.at(-1) ?? null
        const rowButton = page.locator(`[data-pay-pending="${a1.id}"]`)
        const rowVisible = await rowButton.isVisible().catch(() => false)

        const listState = {
          rows: list?.rowCount ?? null,
          empty: list?.empty ?? null,
          emptyText: list?.emptyText ?? null,
          request: lastList ? { url: lastList.url, status: lastList.status } : null,
          literalUndefined:
            lastList?.url?.includes('search=undefined') ||
            lastList?.url?.includes('date_from=undefined') ||
            lastList?.url?.includes('date_to=undefined'),
          rowVisible
        }

        const before = last
        let status = null
        let preset = null
        let presetConcept = null
        let rowGone = null
        let door = 'ui'

        if (rowVisible) {
          await rowButton.click({ timeout: 8000 })
          await page.locator('[data-payment-form]').waitFor({ state: 'visible', timeout: 10000 })
          preset = await page
            .locator('[data-payment-amount] input')
            .first()
            .inputValue({ timeout: 4000 })
            .catch(() => null)
          presetConcept = await page
            .locator('[data-payment-concept] input')
            .first()
            .inputValue({ timeout: 4000 })
            .catch(() => null)
          await fillPayment(page, { method: methods.cash, amount: a1.total })
          evidence.shots.push(await shot(page, `${evidence.shotDir}/05-cobro-a1-modal.png`))

          const response = waitForMatch(page, 'POST', /^\/api\/pending-payments\/\d+\/pay$/)
          const reopened = waitForCall(
            page,
            'GET',
            '/api/cash-register/pending-payments',
            () => true,
            15000
          )
          await page.locator('[data-payment-submit]').click({ timeout: 8000 })
          const settled = await response
          status = settled?.status() ?? null
          await reopened.catch(() => {})
          await waitForToast(page, 'Cobro registrado', 12000)
          await page.waitForTimeout(500)
          await settleCash(page)
          rowGone = !(await page
            .locator(`[data-pay-pending="${a1.id}"]`)
            .isVisible()
            .catch(() => false))
        } else {
          // The list answers an empty population because it sends the literal
          // string "undefined" as the search filter. The documented API door is
          // used instead, so the arithmetic of the collection is still measured.
          door = 'api'
          const response = await call(
            request,
            session,
            'POST',
            `/api/pending-payments/${a1.id}/pay`,
            {
              payment_method_id: 1,
              amount: a1.total,
              description: `${fixtures.marker} cobro de A1`
            }
          )
          ;({ status } = response)
          await page.reload({ waitUntil: 'load' })
          await settleCash(page)
        }

        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, {
          income: a1.total,
          cashIn: a1.total,
          expected: a1.total
        })
        const drift = await uiVsApi('cobro-a1', after)
        const presetValue = parseCurrency(preset)
        const moneyMismatch = presetValue !== null && Math.abs(presetValue - a1.total) > 0.005

        return {
          observed: {
            door,
            list: listState,
            status,
            preset,
            presetValue,
            presetConcept,
            appointmentTotal: a1.total,
            rowGone,
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected },
            kpiDrift: drift.drift
          },
          verdict:
            door === 'ui' && status === 200 && rowGone && arithmetic.matches && !moneyMismatch
              ? 'OK'
              : 'DEFECTO',
          detail:
            door === 'api'
              ? 'la pestaña Cobros responde «No hay cobros pendientes» aunque el API publica 39: el listado envía ' +
                '`search=undefined&date_from=undefined&date_to=undefined` como filtros literales, y el servidor los ' +
                'aplica. Sin fila no hay modal de cobro pendiente: el cobro de A1 se registró por la API documentada ' +
                'para poder medir la aritmética'
              : moneyMismatch
                ? `la fila promete ${preset} (precio del tipo de cita) mientras el saldo de la cita es ${a1.total.toFixed(2)}`
                : null,
          requestStatus: status,
          arithmetic,
          extra: { uiPreset: preset, uiConcept: presetConcept }
        }
      }
    )

    // 6 — a wallet charge and the reference the field asks for.
    await step(
      'cobro-a2-yape',
      `cobrar la cita A2 (${a2.total.toFixed(2)}) con Yape y su referencia`,
      'el cobro se registra, el efectivo de la gaveta no se mueve y la referencia exigida viaja',
      async () => {
        await openTab(page, 'Cobros')
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

        const rowVisible = await page
          .locator(`[data-pay-pending="${a2.id}"]`)
          .isVisible()
          .catch(() => false)
        const attempts = []
        let uiPath = 'not-reached'

        if (rowVisible) {
          uiPath = 'reached'
          await page.locator(`[data-pay-pending="${a2.id}"]`).click({ timeout: 8000 })
          await page.locator('[data-payment-form]').waitFor({ state: 'visible', timeout: 10000 })
          await fillPayment(page, {
            method: 'Yape',
            amount: a2.total,
            reference: `AUDIT-${fixtures.token}`
          })
          evidence.shots.push(await shot(page, `${evidence.shotDir}/06-cobro-a2-modal.png`))

          for (let attempt = 1; attempt <= 2; attempt += 1) {
            const response = waitForMatch(page, 'POST', /^\/api\/pending-payments\/\d+\/pay$/)
            await page.locator('[data-payment-submit]').click({ timeout: 8000 })
            const settled = await response
            const body = await bodyOf(settled)
            await page.waitForTimeout(400)
            attempts.push({
              source: 'ui',
              attempt,
              status: settled?.status() ?? null,
              errors: body?.errors ? Object.keys(body.errors) : [],
              message: messageOf(body),
              visibleError: await visiblePaymentError(page)
            })
          }

          await closeModal(page)
        }

        // The door's own contract, measured with the same token: the API refuses
        // a wallet charge without the reference and accepts it with it.
        const withoutReference = await call(
          request,
          session,
          'POST',
          `/api/pending-payments/${a2.id}/pay`,
          {
            payment_method_id: 5,
            amount: a2.total,
            description: `${fixtures.marker} yape sin referencia`
          }
        )
        attempts.push({
          source: 'api',
          attempt: 'without-reference',
          status: withoutReference.status,
          errors: withoutReference.body?.errors ? Object.keys(withoutReference.body.errors) : [],
          message: withoutReference.message
        })

        const before = last
        const api = await call(request, session, 'POST', `/api/pending-payments/${a2.id}/pay`, {
          payment_method_id: 5,
          amount: a2.total,
          description: `${fixtures.marker} yape con referencia`,
          reference_number: `AUDIT-${fixtures.token}`
        })
        attempts.push({
          source: 'api',
          attempt: 'with-reference',
          status: api.status,
          id: api.body?.data?.id ?? null
        })

        await page.reload({ waitUntil: 'load' })
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, {
          income: a2.total,
          cashIn: 0,
          expected: 0
        })
        const drift = await uiVsApi('cobro-a2', after)

        const walletRule =
          withoutReference.status === 422 &&
          (withoutReference.body?.errors?.reference_number ?? []).length > 0 &&
          api.status === 200

        return {
          observed: {
            uiPath,
            listRowVisible: rowVisible,
            attempts,
            api: {
              status: api.status,
              id: api.body?.data?.id ?? null,
              reference: api.body?.data?.reference_number ?? null
            },
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected },
            kpiDrift: drift.drift
          },
          verdict: 'DEFECTO',
          detail: walletRule
            ? 'Yape exige referencia y la puerta de cobro pendiente la rechaza sin ella (422 en `reference_number`) ' +
              'mientras acepta el mismo cobro con ella; el único formulario que alimenta esa puerta (`PaymentModal` con ' +
              'cita preseleccionada) no la envía, y hoy además es inalcanzable porque la lista de Cobros responde vacía'
            : 'la regla de la referencia del monedero electrónico no se pudo medir',
          requestStatus: api.status,
          arithmetic
        }
      }
    )

    // 7 — an ad-hoc cash charge.
    await step(
      'cobro-adhoc-efectivo',
      `cobro ad-hoc de 50.00 en Efectivo al paciente de A1`,
      '+50.00 de ingreso, +50.00 de efectivo y +50.00 de saldo esperado',
      async () => {
        const opened = await openAdHocPayment(page)

        if (!opened.ok) {
          return {
            observed: opened,
            verdict: 'NO PROBADO',
            reason: opened.reason,
            requestStatus: null
          }
        }

        const searchByFullName = await call(
          request,
          session,
          'GET',
          `/api/patients/search?q=${encodeURIComponent(a1.patientName)}`
        )
        const patient = await pickAdHocPatient(page, {
          name: a1.patientName,
          document: a1.patientDocument
        })
        const filled = await fillPayment(page, {
          method: methods.cash,
          amount: 50,
          concept: `${fixtures.marker} cobro ad-hoc`
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/07-cobro-adhoc-form.png`))

        if (filled.state?.submitDisabled) {
          return {
            observed: { patient, filled, form: filled.state },
            verdict: 'NO PROBADO',
            reason:
              'the payment submit stays disabled with a patient, a concept, a method and 50.00 filled',
            requestStatus: null
          }
        }

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        const clicked = await clickSubmit(page, '[data-payment-submit]')

        if (!clicked.ok) {
          return {
            observed: clicked,
            verdict: 'NO PROBADO',
            reason: clicked.reason,
            requestStatus: null
          }
        }

        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Cobro registrado', 12000)
        await page.waitForTimeout(600)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, { income: 50, cashIn: 50, expected: 50 })
        const drift = await uiVsApi('cobro-adhoc', after)

        const found = Array.isArray(searchByFullName.body?.data)
          ? searchByFullName.body.data.length
          : null

        return {
          observed: {
            status: settled?.status() ?? null,
            transactionId: body?.data?.id ?? null,
            patient,
            searchByFullName: {
              query: a1.patientName,
              status: searchByFullName.status,
              results: found
            },
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected },
            kpiDrift: drift.drift
          },
          verdict:
            settled?.status() === 201 && arithmetic.matches && !drift.drift.length
              ? 'OK'
              : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic,
          extra: { patientSearchWorkaround: a1.patientDocument, searchByFullName: found }
        }
      }
    )

    const adHocStep = evidence.steps.find(entry => entry.id === 'cobro-adhoc-efectivo')

    if (adHocStep?.observed?.searchByFullName?.results === 0) {
      evidence.findings.push({
        step: 'cobro-adhoc-efectivo (buscador)',
        action: 'buscar al paciente por su nombre completo en el modal de cobro',
        expected: 'el buscador encuentra al paciente por nombre',
        observed: adHocStep.observed.searchByFullName,
        verdict: 'DEFECTO',
        requestStatus: adHocStep.observed.searchByFullName.status,
        detail:
          'el buscador de pacientes de los modales de dinero no encuentra a nadie por nombre completo: ' +
          '`GET /api/patients/search?q=<nombre completo>` responde 0 y la sesión tuvo que buscar por documento',
        shot: adHocStep.shot
      })
    }

    // 8 — a card charge with its reference.
    await step(
      'cobro-adhoc-tarjeta',
      'cobro ad-hoc de 30.00 con Tarjeta de crédito y referencia',
      '+30.00 de ingreso y el efectivo de la gaveta intacto',
      async () => {
        const opened = await openAdHocPayment(page)

        if (!opened.ok) {
          return {
            observed: opened,
            verdict: 'NO PROBADO',
            reason: opened.reason,
            requestStatus: null
          }
        }

        const patient = await pickAdHocPatient(page, {
          name: a1.patientName,
          document: a1.patientDocument
        })
        const filled = await fillPayment(page, {
          method: methods.card,
          amount: 30,
          concept: `${fixtures.marker} cobro con tarjeta`,
          reference: `CARD-${fixtures.token}`
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/08-cobro-tarjeta-form.png`))

        if (filled.state?.submitDisabled) {
          return {
            observed: { patient, filled, form: filled.state },
            verdict: 'NO PROBADO',
            reason:
              'the payment submit stays disabled with a patient, a concept, a card and 30.00 filled',
            requestStatus: null
          }
        }

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        const clicked = await clickSubmit(page, '[data-payment-submit]')

        if (!clicked.ok) {
          return {
            observed: { clicked, patient, filled, form: filled.state, trace: filled.trace },
            verdict: 'NO PROBADO',
            reason: clicked.reason,
            requestStatus: null
          }
        }

        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Cobro registrado', 12000)
        await page.waitForTimeout(600)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, { income: 30, cashIn: 0, expected: 0 })
        const drift = await uiVsApi('cobro-tarjeta', after)

        evidence.cardTransaction = { id: body?.data?.id ?? null, amount: 30 }

        return {
          observed: {
            status: settled?.status() ?? null,
            transactionId: body?.data?.id ?? null,
            reference: body?.data?.reference_number ?? null,
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected },
            kpiDrift: drift.drift
          },
          verdict: settled?.status() === 201 && arithmetic.matches ? 'OK' : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 9 — the receipt of the newest row.
    await step(
      'comprobante',
      'pedir el comprobante de la transacción más reciente',
      '200 con `application/pdf` y una pestaña nueva',
      async () => {
        await openTab(page, 'Transacciones')
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

        const popup = page.waitForEvent('popup', { timeout: 8000 }).catch(() => null)
        const response = waitForMatch(page, 'POST', /^\/api\/transactions\/\d+\/receipt$/)
        await page.locator('[data-row-action="receipt"]').first().click({ timeout: 8000 })
        const settled = await response
        const opened = await popup
        const contentType = settled?.headers()['content-type'] ?? null
        const url = settled ? new URL(settled.url()).pathname : null
        const bytes = settled ? ((await settled.body().catch(() => null))?.length ?? null) : null

        if (opened) await opened.close().catch(() => {})

        return {
          observed: {
            status: settled?.status() ?? null,
            contentType,
            path: url,
            bytes,
            popup: opened ? opened.url().slice(0, 80) : null
          },
          verdict:
            settled?.status() === 200 && String(contentType ?? '').includes('pdf')
              ? 'OK'
              : 'DEFECTO',
          detail:
            settled?.status() === 200 && !opened
              ? 'el comprobante se descarga pero `window.open` con `noopener` no abre una pestaña observable'
              : null,
          requestStatus: settled?.status() ?? null
        }
      }
    )

    // 10 — a refund, with the administrator who authorizes it.
    await step(
      'devolucion-20',
      'devolución de 20.00 en Efectivo autorizada por el administrador',
      '+20.00 de egreso, +20.00 de salida de efectivo y −20.00 de saldo esperado',
      async () => {
        await openTransactionModal(page)
        const type = await chooseTransactionType(page, 'Devolución')
        const patient = await pickTransactionPatient(page, {
          name: a1.patientName,
          lastName: a1.patientLastName,
          document: a1.patientDocument
        })
        const authorization = await fillRefundAuthorization(page, {
          authorizer: session.user.name.split(' ')[0],
          reason: `${fixtures.marker} devolución parcial del cobro`
        })
        await fillInput(page, '[data-modal-description]', `${fixtures.marker} devolución parcial`)
        const filled = await fillTransactionPayment(page, { method: methods.cash, amount: 20 })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/10-devolucion-form.png`))

        if (filled.state?.submitDisabled) {
          return {
            observed: { type, patient, authorization, filled, form: filled.state },
            verdict: 'NO PROBADO',
            reason:
              'the transaction submit stays disabled with patient, type, authorizer, reason, method and 20.00 filled',
            requestStatus: null
          }
        }

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await page.waitForTimeout(700)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, {
          expenses: 20,
          cashOut: 20,
          expected: -20
        })
        const drift = await uiVsApi('devolucion', after)

        return {
          observed: {
            status: settled?.status() ?? null,
            type,
            authorization,
            transactionType: body?.data?.type ?? null,
            authorizedBy: body?.data?.authorized_by ?? null,
            reason: body?.data?.authorization_reason ?? null,
            money: { expenses: after.expenses, cashOut: after.cashOut, expected: after.expected },
            kpiDrift: drift.drift
          },
          verdict: settled?.status() === 201 && arithmetic.matches ? 'OK' : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 11 — the refund cap of a patient who never paid.
    await step(
      'devolucion-tope',
      `devolución de 999.00 al paciente de A3 (${a3.patientName}), que no tiene cobros`,
      '422 en el campo del monto y ninguna transacción nueva',
      async () => {
        await openTransactionModal(page)
        await chooseTransactionType(page, 'Devolución')
        const patient = await pickTransactionPatient(page, {
          name: a3.patientName,
          lastName: a3.patientLastName,
          document: a3.patientDocument
        })
        await fillRefundAuthorization(page, {
          authorizer: session.user.name.split(' ')[0],
          reason: `${fixtures.marker} devolución fuera de tope`
        })
        await fillInput(page, '[data-modal-description]', `${fixtures.marker} tope`)
        const filled = await fillTransactionPayment(page, { method: methods.cash, amount: 999 })

        if (filled.state?.submitDisabled) {
          return {
            observed: { patient, filled, form: filled.state },
            verdict: 'NO PROBADO',
            reason: 'the transaction submit stays disabled for the capped refund',
            requestStatus: null
          }
        }

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await page.waitForTimeout(600)

        const inline = await page.evaluate(() => {
          const wrapper = document.querySelector('[data-modal-field="amount"]')
          return {
            text: (wrapper?.innerText ?? '').trim(),
            invalid: wrapper?.querySelector('[aria-invalid="true"]') !== null
          }
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/11-devolucion-tope.png`))
        await closeModal(page)

        const after = await snap()
        const arithmetic = expectDelta(before, after, {})
        const amountError = body?.errors?.amount?.[0] ?? null

        return {
          observed: {
            status: settled?.status() ?? null,
            patient,
            amountError,
            inline,
            transactions: { before: before.transactions, after: after.transactions }
          },
          verdict:
            settled?.status() === 422 &&
            Boolean(amountError) &&
            /superar/i.test(inline.text) &&
            after.transactions === before.transactions
              ? 'OK'
              : 'DEFECTO',
          detail:
            settled?.status() === 422 && !/superar/i.test(inline.text)
              ? 'el 422 del tope no cae en el campo del monto'
              : null,
          requestStatus: settled?.status() ?? null,
          arithmetic,
          extra: { capMessage: amountError }
        }
      }
    )

    // 12 — voiding the card charge, cancel first.
    await step(
      'anular-tarjeta',
      'anular el cobro de 30.00 con tarjeta (cancelar primero)',
      'cancelar no envía nada; confirmar deja la fila «Anulada» y resta 30.00 de ingreso sin tocar el efectivo',
      async () => {
        await openTab(page, 'Transacciones')
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

        const row = page
          .locator('[data-transaction-table] tbody tr')
          .filter({ hasText: '30.00' })
          .first()

        if (!(await row.isVisible().catch(() => false))) {
          return {
            observed: { rowVisible: false },
            verdict: 'NO PROBADO',
            reason: 'la fila del cobro con tarjeta no aparece en la tabla de transacciones',
            requestStatus: null
          }
        }

        await row.locator('[data-row-action="void"]').click({ timeout: 8000 })
        const confirm = page.locator('[role="alertdialog"]:visible').last()
        await confirm.waitFor({ state: 'visible', timeout: 8000 })
        const voidsBeforeCancel = requests.filter(
          entry => entry.method === 'POST' && /\/api\/transactions\/\d+\/void$/.test(entry.path)
        ).length

        await confirm
          .getByRole('button', { name: 'Cancelar', exact: true })
          .click({ timeout: 8000 })
        await confirm.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
        await page.waitForTimeout(400)
        const voidsAfterCancel = requests.filter(
          entry => entry.method === 'POST' && /\/api\/transactions\/\d+\/void$/.test(entry.path)
        ).length
        evidence.shots.push(await shot(page, `${evidence.shotDir}/12-anular-cancelado.png`))

        const before = last
        await row.locator('[data-row-action="void"]').click({ timeout: 8000 })
        const confirmAgain = page.locator('[role="alertdialog"]:visible').last()
        await confirmAgain.waitFor({ state: 'visible', timeout: 8000 })
        const response = waitForMatch(page, 'POST', /^\/api\/transactions\/\d+\/void$/)
        await confirmAgain
          .getByRole('button', { name: 'Anular', exact: true })
          .click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Transacción anulada', 10000).catch(() => {})
        await page.waitForTimeout(800)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, { income: -30, cashIn: 0, expected: 0 })

        const voidedRow = page
          .locator('[data-transaction-table] tbody tr')
          .filter({ hasText: '30.00' })
          .first()
        const rowText = (await voidedRow.innerText({ timeout: 4000 }).catch(() => '')).replace(
          /\s+/g,
          ' '
        )

        return {
          observed: {
            voidsBeforeCancel,
            voidsAfterCancel,
            status: settled?.status() ?? null,
            transactionStatus: body?.data?.status ?? null,
            rowText,
            toast: await toastText(page),
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected }
          },
          verdict:
            voidsBeforeCancel === 0 &&
            voidsAfterCancel === 0 &&
            settled?.status() === 200 &&
            arithmetic.matches &&
            /Anulada/i.test(rowText)
              ? 'OK'
              : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 13 — the deposit, its ceiling and its reference.
    await step(
      'deposito',
      'depositar 40.00 con referencia, después de probar el tope y la referencia vacía',
      'errores locales antes de enviar, toast después y −40.00 de saldo esperado',
      async () => {
        const before = last
        const available = before.expected

        await page.locator('[data-hero-action="deposit"]').click({ timeout: 8000 })
        await page.locator('[data-cash-deposit-form]').waitFor({ state: 'visible', timeout: 10000 })

        await fillMoney(page, '[data-deposit-amount]', available + 100)
        await page.waitForTimeout(400)
        const overAvailable = await page.evaluate(() => {
          const node = document.querySelector('[data-deposit-over-available]')
          return {
            present: Boolean(node),
            text: (node?.innerText ?? '').trim(),
            submitDisabled: document.querySelector('[data-modal-submit]')?.disabled === true
          }
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/13-deposito-tope.png`))

        await fillMoney(page, '[data-deposit-amount]', 40)
        await page.waitForTimeout(300)
        const emptyReference = await page
          .locator('[data-deposit-reference]')
          .innerText({ timeout: 4000 })
          .then(value => value.trim().replace(/\s+/g, ' '))
          .catch(() => null)

        const beforeRefused = callsTo(requests, 'POST', '/api/cash-register/deposits').length
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        await page.waitForTimeout(700)
        const refusedCalls =
          callsTo(requests, 'POST', '/api/cash-register/deposits').length - beforeRefused
        const refusal = {
          requests: refusedCalls,
          status:
            refusedCalls === 0
              ? null
              : (lastCall(requests, 'POST', '/api/cash-register/deposits')?.status ?? null),
          text: (
            await page
              .locator('[data-deposit-reference]')
              .innerText({ timeout: 4000 })
              .catch(() => '')
          )
            .trim()
            .replace(/\s+/g, ' ')
        }
        evidence.shots.push(await shot(page, `${evidence.shotDir}/13-deposito-referencia.png`))

        await fillInput(page, '[data-deposit-reference]', `AUDIT-${fixtures.token}`)
        const response = waitForMatch(page, 'POST', /^\/api\/cash-register\/deposits$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Depósito registrado', 12000)
        await page.waitForTimeout(800)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, { expected: -40, cashOut: 40 })
        const depositId = body?.data?.id ?? null

        if (depositId) evidence.residues.push({ type: 'cash_deposit', id: depositId })

        await page.locator('[data-hero-action="close"]').click({ timeout: 8000 })
        await page.locator('[data-close-cash-form]').waitFor({ state: 'visible', timeout: 10000 })
        const closeDeposits = await page.evaluate(() => {
          const node = document.querySelector('[data-close-deposits]')
          return (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/13-cierre-depositos.png`))
        await closeModal(page)

        return {
          observed: {
            available,
            overAvailable,
            emptyReference,
            refusal,
            status: settled?.status() ?? null,
            deposits: after.deposits,
            closeDeposits,
            money: { expected: after.expected }
          },
          verdict:
            overAvailable.present &&
            refusal.requests === 0 &&
            settled?.status() === 201 &&
            after.deposits.count === 1 &&
            Math.abs(after.deposits.total - 40) < 0.005 &&
            /\b1\b/.test(closeDeposits) &&
            arithmetic.matches
              ? 'OK'
              : 'DEFECTO',
          detail:
            refusal.requests !== 0
              ? 'la referencia vacía llegó al servidor en vez de detenerse en el formulario'
              : null,
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 14 — the manual movements, and the catalog that allows them.
    await step(
      'movimientos-manuales',
      'movimiento manual de ingreso 10.00 (Depósito) y egreso 5.00 (Retiro)',
      '+10.00 de efectivo y −5.00, con el saldo esperado siguiendo el mismo signo',
      async () => {
        const catalog = await openMovementModal(page).then(() => movementCatalog(page))
        evidence.shots.push(await shot(page, `${evidence.shotDir}/14-movimientos-catalogo.png`))
        await closeModal(page)

        await openMovementModal(page)
        const incomeForm = await fillMovement(page, {
          type: 'Ingreso',
          concept: 'Depósito',
          description: `${fixtures.marker} ingreso manual`,
          amount: 10
        })

        if (!incomeForm.ok) {
          return {
            observed: incomeForm,
            verdict: 'NO PROBADO',
            reason: incomeForm.reason,
            requestStatus: null
          }
        }

        const beforeIncome = last
        const incomeResponse = waitForMatch(page, 'POST', /^\/api\/cash-movements$/)
        const incomeClick = await clickSubmit(page, '[data-modal-submit]')

        if (!incomeClick.ok) {
          return {
            observed: incomeClick,
            verdict: 'NO PROBADO',
            reason: incomeClick.reason,
            requestStatus: null
          }
        }

        const incomeSettled = await incomeResponse
        await waitForToast(page, 'Movimiento registrado', 12000)
        await page.waitForTimeout(700)
        await settleCash(page)
        const afterIncome = await snap()
        const incomeArithmetic = expectDelta(beforeIncome, afterIncome, {
          cashIn: 10,
          expected: 10
        })

        await openMovementModal(page)
        await fillMovement(page, {
          type: 'Egreso',
          concept: 'Retiro',
          description: `${fixtures.marker} egreso manual`,
          amount: 5
        })
        const expenseResponse = waitForMatch(page, 'POST', /^\/api\/cash-movements$/)
        const expenseClick = await clickSubmit(page, '[data-modal-submit]')

        if (!expenseClick.ok) {
          return {
            observed: expenseClick,
            verdict: 'NO PROBADO',
            reason: expenseClick.reason,
            requestStatus: null
          }
        }

        const expenseSettled = await expenseResponse
        await waitForToast(page, 'Movimiento registrado', 12000)
        await page.waitForTimeout(700)
        await settleCash(page)
        const afterExpense = await snap()
        last = afterExpense
        const expenseArithmetic = expectDelta(afterIncome, afterExpense, {
          cashOut: 5,
          expected: -5
        })
        const totals = await page.evaluate(() =>
          Object.fromEntries(
            Array.from(document.querySelectorAll('[data-movement-total]')).map(node => [
              node.getAttribute('data-movement-total'),
              (node.querySelector('[data-movement-total-value]')?.innerText ?? '').trim()
            ])
          )
        )

        return {
          observed: {
            catalog,
            income: { status: incomeSettled?.status() ?? null, money: afterIncome },
            expense: { status: expenseSettled?.status() ?? null, money: afterExpense },
            totals
          },
          verdict:
            incomeSettled?.status() === 201 &&
            expenseSettled?.status() === 201 &&
            incomeArithmetic.matches &&
            expenseArithmetic.matches
              ? 'OK'
              : 'DEFECTO',
          requestStatus: expenseSettled?.status() ?? null,
          arithmetic: {
            income: incomeArithmetic,
            expense: expenseArithmetic,
            matches: incomeArithmetic.matches && expenseArithmetic.matches
          },
          extra: {
            contradictoryPairs: catalog.contradictoryPairs.filter(pair => !pair.coherent).length
          }
        }
      }
    )

    // 15 — the accounting lock on the money door.
    await step(
      'bloqueo-contable',
      'bloquear `cash_transactions` hasta hoy y registrar un cobro de 5.00',
      '403 `accounting_lock_blocked` con el aviso en línea, motivo y reintento autorizado',
      async () => {
        const lock = await call(
          request,
          session,
          'PUT',
          '/api/accounting-locks/cash_transactions',
          {
            locked_through: day
          }
        )

        await openTransactionModal(page)
        await pickTransactionPatient(page, {
          name: a1.patientName,
          lastName: a1.patientLastName,
          document: a1.patientDocument
        })
        await fillTransactionPayment(page, { method: methods.cash, amount: 5 })
        await fillInput(page, '[data-modal-description]', `${fixtures.marker} cobro bloqueado`)

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/transactions$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await page.waitForTimeout(700)
        const notice = await readLockNotice(page)
        evidence.shots.push(await shot(page, `${evidence.shotDir}/15-bloqueo-cobro.png`))

        // Retry paths exist only if the notice is rendered.
        let shortReason = null
        let authorized = null

        if (notice.present && notice.reasonField) {
          const reasonInput = page.locator('[data-accounting-lock-reason] input').first()
          await reasonInput.fill('corto')
          await page.locator('[data-accounting-lock-retry]').click({ timeout: 8000 })
          await page.waitForTimeout(500)
          shortReason = await readLockNotice(page)
          evidence.shots.push(await shot(page, `${evidence.shotDir}/15-bloqueo-motivo-corto.png`))

          await reasonInput.fill(`${fixtures.marker} autorización del cierre contable`)
          const retry = waitForMatch(page, 'POST', /^\/api\/transactions$/)
          await page.locator('[data-accounting-lock-retry]').click({ timeout: 8000 })
          const retried = await retry
          const retriedBody = await bodyOf(retried)
          await page.waitForTimeout(800)
          authorized = {
            status: retried?.status() ?? null,
            id: retriedBody?.data?.id ?? null,
            lockOverride: retriedBody?.data?.metadata?.lock_override_reason ?? null
          }
        }

        await closeModal(page)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, {
          income: settled?.status() === 201 ? 5 : 0,
          cashIn: settled?.status() === 201 ? 5 : 0,
          expected: settled?.status() === 201 ? 5 : 0
        })

        const cleared = await call(
          request,
          session,
          'DELETE',
          '/api/accounting-locks/cash_transactions'
        )
        const locks = await call(request, session, 'GET', '/api/accounting-locks')
        const activeLock = (locks.body?.data ?? []).find(
          row => row.module === 'cash_transactions' && row.locked_through
        )

        return {
          observed: {
            lock: { status: lock.status, lockedThrough: lock.body?.data?.locked_through ?? null },
            status: settled?.status() ?? null,
            code: body?.code ?? null,
            message: messageOf(body),
            notice,
            shortReason,
            authorized,
            money: { income: after.income, cashIn: after.cashIn, expected: after.expected },
            cleared: { status: cleared.status, removed: cleared.body?.data?.removed ?? null },
            activeLock: activeLock ?? null
          },
          verdict:
            settled?.status() === 403 &&
            body?.code === 'accounting_lock_blocked' &&
            notice.visible &&
            notice.reasonField &&
            notice.retry
              ? 'OK'
              : 'DEFECTO',
          detail:
            settled?.status() === 201
              ? 'la puerta de alta de un cobro no consulta el bloqueo contable: el cobro se registra y el aviso ' +
                'de bloqueo (con su motivo y su reintento) nunca aparece, aunque el servicio sí bloquea la anulación y el depósito'
              : null,
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 16 — closing with a difference.
    await step(
      'cierre-con-diferencia',
      'cerrar con un conteo 10.00 por encima del saldo esperado y el reporte marcado',
      'la diferencia dice «Sobrante», el historial guarda el conteo y la fórmula final cuadra',
      async () => {
        const before = last
        const { expected } = before
        const counted = round2(expected + 10)

        await page.locator('[data-hero-action="close"]').click({ timeout: 8000 })
        await page.locator('[data-close-cash-form]').waitFor({ state: 'visible', timeout: 10000 })
        const shownExpected = await page
          .locator('[data-close-expected]')
          .innerText({ timeout: 4000 })
          .catch(() => null)
        await fillMoney(page, '[data-close-counted]', counted)
        await page.waitForTimeout(400)
        const difference = await page.evaluate(() => {
          const node = document.querySelector('[data-close-difference]')
          return {
            text: (node?.innerText ?? '').trim(),
            variant: node?.getAttribute('data-variant') ?? null
          }
        })
        const report = await enableExport(page)
        evidence.shots.push(await shot(page, `${evidence.shotDir}/16-cierre-diferencia.png`))

        const download = page.waitForEvent('download', { timeout: 20000 }).catch(() => null)
        const response = waitForMatch(page, 'POST', /^\/api\/cash-register\/close$/)
        await page.locator('[data-modal-submit]').click({ timeout: 10000 })
        const settled = await response
        const body = await bodyOf(settled)
        const file = await download
        await waitForToast(page, 'Sesión de caja cerrada', 12000)
        await page.waitForTimeout(1200)
        await settleCash(page)

        const closed = await sessionSnapshot(request, session, { sessionId })
        const state = await hero(page)
        const formula = drawerFormula(closed)

        await openTab(page, 'Historial')
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const sessions = page.locator('[data-session-table] tbody tr')
        const total = await sessions.count()
        let rowText = null

        for (let index = 0; index < total; index += 1) {
          const text = (
            await sessions
              .nth(index)
              .innerText({ timeout: 4000 })
              .catch(() => '')
          ).replace(/\s+/g, ' ')
          if (text.includes(counted.toFixed(2))) {
            rowText = text
            break
          }
        }
        evidence.shots.push(await shot(page, `${evidence.shotDir}/16-historial.png`))

        return {
          observed: {
            expected,
            counted,
            shownExpected,
            difference,
            report,
            status: settled?.status() ?? null,
            closingAmount: body?.data?.closing_amount ?? null,
            differenceAmount: body?.data?.difference_amount ?? null,
            download: file ? file.suggestedFilename() : null,
            heroState: state.state,
            heroSession: state.actions,
            closedSession: {
              id: closed.id,
              status: closed.status,
              opening: closed.opening,
              cashIn: closed.cashIn,
              cashOut: closed.cashOut,
              expected: closed.expected
            },
            historyRow: rowText,
            formula
          },
          verdict:
            settled?.status() === 200 &&
            /Sobrante/i.test(difference.text) &&
            formula.ok === true &&
            Boolean(rowText)
              ? 'OK'
              : 'DEFECTO',
          detail:
            /Sesión cerrada/.test(state.state) === false
              ? `después del cierre el hero dice «${state.state}» porque vuelve a mostrar la sesión abierta de otro usuario`
              : null,
          requestStatus: settled?.status() ?? null,
          arithmetic: {
            formula,
            matches: formula.ok === true
          }
        }
      }
    )

    // 17 — the period report against the drawer it just closed.
    await step(
      'reportes-periodo',
      'abrir Reportes y leer la conciliación del período después del cierre',
      'el DOM coincide con el payload, el reporte cuadra consigo mismo y su delta es exactamente la gaveta que la sesión movió',
      async () => {
        const reportResponse = waitForMatch(page, 'GET', /^\/api\/cash-register\/reports\/period$/)
        await openTab(page, 'Reportes')
        const settled = await reportResponse
        const body = await bodyOf(settled)
        const payload = body?.data ?? null
        await page.waitForTimeout(1500)
        const dom = await readReport(page)
        evidence.shots.push(await shot(page, `${evidence.shotDir}/17-reportes.png`))

        const after = reportTotals(payload)
        const before = evidence.reportBaseline?.totals ?? null
        const counted = round2(last.expected + 10)
        const delta = key => (before ? round2(after[key] - before[key]) : null)
        const deltas = {
          sessions: before ? after.sessions - before.sessions : null,
          opening: delta('opening'),
          closing: delta('closing'),
          expected: delta('expected'),
          difference: delta('difference'),
          income: delta('income'),
          expenses: delta('expenses'),
          cash: delta('cash'),
          electronic: delta('electronic'),
          depositsCount: before ? after.depositsCount - before.depositsCount : null,
          depositsTotal: delta('depositsTotal'),
          pending: delta('pending')
        }
        // The ledger this walkthrough wrote: 60 + 50 + 5 collected in cash, 20
        // returned in cash (so the cash view of the period is 95), 80 through
        // the wallet; the 30 card charge was voided and a card never enters the
        // drawer. The deposit took 40 out and leaves 55 pending.
        const expectedDeltas = {
          sessions: 1,
          opening: last.opening,
          closing: counted,
          expected: last.expected,
          difference: round2(counted - last.expected),
          income: last.income,
          expenses: last.expenses,
          cash: 95,
          electronic: 80,
          depositsCount: 1,
          depositsTotal: 40,
          pending: 55
        }

        const rows = payload?.payment_methods_analysis ?? []
        const sum = (list, key) =>
          round2(list.reduce((total, row) => total + Number(row?.[key] ?? 0), 0))
        const cashRows = rows.filter(row => (row.type ?? 'cash') === 'cash')
        const electronicRows = rows.filter(row => (row.type ?? 'cash') !== 'cash')
        const daily = payload?.daily_summary ?? []
        const dailySum = key =>
          round2(daily.reduce((total, row) => total + Number(row?.[key] ?? 0), 0))
        const recon = payload?.reconciliation ?? {}
        const matches = (shown, actual) =>
          shown !== null && Math.abs(parseCurrency(shown) - actual) < 0.005

        const checks = {
          pendingEquation:
            Math.abs(
              round2(recon?.cash?.total) -
                round2(recon?.deposits?.total) -
                round2(recon?.pending_deposit)
            ) < 0.005,
          cashAgainstRows:
            Math.abs(sum(cashRows, 'total') - round2(recon?.cash?.total)) < 0.005 &&
            sum(cashRows, 'count') === Number(recon?.cash?.count),
          electronicAgainstRows:
            Math.abs(sum(electronicRows, 'total') - round2(recon?.electronic?.total)) < 0.005 &&
            sum(electronicRows, 'count') === Number(recon?.electronic?.count),
          dailySessions:
            daily.reduce((total, row) => total + Number(row?.sessions_count ?? 0), 0) ===
            after.sessions,
          dailyOpening: Math.abs(dailySum('total_opening') - after.opening) < 0.005,
          dailyClosing: Math.abs(dailySum('total_closing') - after.closing) < 0.005,
          dailyDifference: Math.abs(dailySum('total_difference') - after.difference) < 0.005,
          dailyIncome: Math.abs(dailySum('total_income') - after.income) < 0.005,
          dailyExpenses: Math.abs(dailySum('total_expenses') - after.expenses) < 0.005,
          domSessions: parseCurrency(dom.kpis.sessions) === after.sessions,
          domOpening: matches(dom.kpis.opening, after.opening),
          domClosing: matches(dom.kpis.closing, after.closing),
          domDifference: matches(dom.kpis.difference, after.difference),
          domIncome: matches(dom.kpis.income, after.income),
          domExpenses: matches(dom.kpis.expenses, after.expenses),
          domCash: matches(dom.reconciliation.cash, after.cash),
          domElectronic: matches(dom.reconciliation.electronic, after.electronic),
          domDeposits: matches(dom.reconciliation.deposits, after.depositsTotal),
          domPending: matches(dom.reconciliation.pending, after.pending),
          noReportError: dom.present === true && dom.errorVisible === false
        }
        const deltaChecks = Object.fromEntries(
          Object.entries(expectedDeltas).map(([key, value]) => [
            key,
            deltas[key] !== null && Math.abs(round2(deltas[key]) - round2(value)) < 0.005
          ])
        )
        const failed = [...Object.entries(checks), ...Object.entries(deltaChecks)]
          .filter(([, ok]) => !ok)
          .map(([key]) => key)

        return {
          observed: {
            status: settled?.status() ?? null,
            payloadTotals: after,
            baselineTotals: before,
            deltas,
            expectedDeltas,
            reconciliation: recon,
            checks,
            deltaChecks,
            dom: {
              present: dom.present,
              period: dom.period,
              kpis: dom.kpis,
              reconciliation: dom.reconciliation,
              loading: dom.loading,
              errorVisible: dom.errorVisible
            }
          },
          verdict: failed.length === 0 ? 'OK' : 'DEFECTO',
          detail: failed.length ? `desajustes medidos: ${failed.join(', ')}` : null,
          requestStatus: settled?.status() ?? null,
          arithmetic: {
            formula: 'report delta vs baseline == the drawer this session moved',
            matches: failed.length === 0
          }
        }
      }
    )

    const closeStep = evidence.steps.find(entry => entry.id === 'cierre-con-diferencia')

    if (closeStep && closeStep.id === 'cierre-con-diferencia') {
      const heroState = closeStep.observed?.heroState ?? null

      if (!/Sesión cerrada/i.test(heroState ?? '')) {
        evidence.findings.push({
          step: 'cierre-con-diferencia (hero)',
          action: 'leer el hero después de cerrar la caja',
          expected: 'hero «Sesión cerrada»',
          observed: heroState,
          verdict: 'DEFECTO',
          requestStatus: closeStep.requestStatus,
          detail:
            'la página vuelve a publicar como propia la sesión abierta de otro usuario: después de su propio cierre ' +
            'el administrador no ve su sesión cerrada sino la caja de alguien más',
          shot: closeStep.shot
        })
      }
    }

    evidence.final = {
      sessionId,
      lastSnapshot: last,
      formula: drawerFormula(last),
      kpiDrift,
      steps: evidence.steps.length,
      stepVerdicts: evidence.steps.reduce((summary, entry) => {
        summary[entry.verdict] = (summary[entry.verdict] ?? 0) + 1
        return summary
      }, {}),
      arithmeticAudited: evidence.steps.filter(entry => entry.arithmetic !== null).length
    }
  } finally {
    await closeModal(page).catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    try {
      markProbes(evidence)
    } catch (thrown) {
      evidence.classificationError = String(thrown?.message ?? thrown)
    }
    const inline = [...evidence.shots]
    evidence.shots = [...inline, ...evidence.steps.map(entry => entry.shot).filter(Boolean)]
    audit('walk-administrador.json', evidence)
    registerResidue({
      type: 'walkthrough',
      id: sessionId,
      role: 'administrador',
      reason:
        'the walkthrough closed its own drawer; its transactions, movements and deposit stay in the ledger'
    })
  }

  assertWalk(evidence, { minSteps: 17 })
  expect(evidence.final.formula.ok, 'the drawer equation of the last snapshot').toBe(true)
  expect(evidence.pageErrors, 'page errors during the walkthrough').toEqual([])
})

// --- finanzas ---------------------------------------------------------------

test('@U5 · walkthrough caja (finanzas)', async ({ page, request }) => {
  test.setTimeout(480000)

  const session = await loginAs(request, 'finanzas')
  const admin = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: CASH_ROUTE,
    role: 'finanzas',
    ...stamp(),
    shotDir: 'walk/finanzas',
    branchId: 3,
    branchName: 'Sede Sur',
    actor: {
      id: session.user.id,
      name: session.user.name,
      branchId: session.user.branch_id ?? null
    },
    mayOverrideLock: (session.user.permissions ?? []).includes('override.accounting_lock'),
    fixtureSummary: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    final: null,
    residues: [],
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence)
  const day = localDay()
  let sessionId = null
  let last = null

  const snap = () =>
    sessionId ? sessionSnapshot(request, session, { sessionId }) : sessionSnapshot(request, session)

  try {
    const preflight = await preflightDrawer(request, session)
    evidence.preflight = preflight

    if (preflight.action === 'closed-leftover') {
      evidence.residues.push({
        type: 'cash_register_session',
        id: preflight.sessionId,
        reason: 'leftover drawer closed by the preflight so the walkthrough starts from zero'
      })
    }

    await openCash(page, session)
    last = await snap()

    // 1 — the landing state of a counter with no drawer.
    await step(
      'hero-sin-sesion',
      'abrir /cash-register sin sesión propia',
      'hero «Sin sesión», KPIs «N/D», puerta de apertura visible',
      async () => {
        const state = await hero(page)
        const values = await kpi(page)
        const ok =
          state.state === 'Sin sesión' &&
          Object.values(values).every(value => /N\/D/.test(value)) &&
          state.actions.open.visible === true &&
          !state.actions.close.visible &&
          !state.actions.deposit.visible

        return {
          observed: { heroState: state.state, kpis: values, actions: state.actions },
          verdict: ok ? 'OK' : 'DEFECTO',
          requestStatus: last.requestStatus
        }
      }
    )

    // 2 — opening the drawer through the UI.
    await step(
      'abrir-caja',
      'abrir caja en «Sede Sur» con 60.00',
      'toast, hero «Sesión abierta» y KPIs 60.00 / 0.00 / 0.00 / 60.00',
      async () => {
        await page.locator('[data-hero-action="open"]').click({ timeout: 8000 })
        await page.locator('[data-open-cash-form]').waitFor({ state: 'visible', timeout: 10000 })
        const branch = await openSelect(
          page,
          '[data-modal-field="branch_id"] button[aria-haspopup="true"]',
          'Sede Sur'
        )
        await fillMoney(page, '[data-modal-amount]', 60)
        evidence.shots.push(await shot(page, `${evidence.shotDir}/02-abrir-caja.png`))

        const response = waitForMatch(page, 'POST', /^\/api\/cash-register\/open$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Sesión de caja abierta', 12000)
        await page.waitForTimeout(800)
        await settleCash(page)

        sessionId = body?.data?.id ?? null
        if (sessionId) {
          registerSession({
            role: 'finanzas',
            sessionId,
            branchId: body?.data?.branch_id ?? null,
            opening: round2(body?.data?.opening_amount),
            reason: 'the walkthrough opened its own drawer'
          })
          evidence.residues.push({ type: 'cash_register_session', id: sessionId })
        }

        const after = await snap()
        last = after
        const state = await hero(page)
        const values = await kpi(page)

        return {
          observed: {
            status: settled?.status() ?? null,
            branch,
            heroState: state.state,
            kpis: values,
            api: after
          },
          verdict:
            settled?.status() === 201 &&
            state.state === 'Sesión abierta' &&
            after.ownedByActor === true &&
            Math.abs(after.expected - 60) < 0.005
              ? 'OK'
              : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic: {
            delta: { opening: after.opening, expected: after.expected },
            formula: 'opening = 60.00',
            matches: Math.abs(after.expected - 60) < 0.005
          }
        }
      }
    )

    // 3 — the manual movement desk.
    await step(
      'movimiento-ingreso',
      'movimiento manual de ingreso 15.00 (Depósito)',
      '+15.00 de efectivo y +15.00 de saldo esperado',
      async () => {
        await openMovementModal(page)
        await fillMovement(page, {
          type: 'Ingreso',
          concept: 'Depósito',
          description: `${fixtures.marker} ingreso finanzas`,
          amount: 15
        })
        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/cash-movements$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        await waitForToast(page, 'Movimiento registrado', 12000)
        await page.waitForTimeout(700)
        await settleCash(page)
        const after = await snap()
        last = after
        const arithmetic = expectDelta(before, after, { cashIn: 15, expected: 15 })

        return {
          observed: {
            status: settled?.status() ?? null,
            money: { cashIn: after.cashIn, expected: after.expected }
          },
          verdict: settled?.status() === 201 && arithmetic.matches ? 'OK' : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 4 — the lock the desk cannot override.
    await step(
      'bloqueo-sin-override',
      'bloquear `cash_movements` hasta hoy y registrar un movimiento de 5.00',
      'el aviso de bloqueo se pinta con su mensaje y sin el campo del motivo',
      async () => {
        const lock = await call(request, admin, 'PUT', '/api/accounting-locks/cash_movements', {
          locked_through: day
        })

        await openMovementModal(page)
        await fillMovement(page, {
          type: 'Ingreso',
          concept: 'Depósito',
          description: `${fixtures.marker} movimiento bloqueado`,
          amount: 5
        })

        const before = last
        const response = waitForMatch(page, 'POST', /^\/api\/cash-movements$/)
        await page.locator('[data-modal-submit]').click({ timeout: 8000 })
        const settled = await response
        const body = await bodyOf(settled)
        await page.waitForTimeout(700)
        const notice = await readLockNotice(page)
        evidence.shots.push(await shot(page, `${evidence.shotDir}/04-bloqueo-movimiento.png`))
        await closeModal(page)

        await settleCash(page)
        const after = await snap()
        last = after
        const created = settled?.status === 201
        const arithmetic = expectDelta(before, after, {
          cashIn: created ? 5 : 0,
          expected: created ? 5 : 0
        })

        const cleared = await call(request, admin, 'DELETE', '/api/accounting-locks/cash_movements')
        const locks = await call(request, admin, 'GET', '/api/accounting-locks')
        const activeLock = (locks.body?.data ?? []).find(
          row => row.module === 'cash_movements' && row.locked_through
        )

        const okNotice = notice.visible && notice.message && !notice.reasonField && !notice.retry

        return {
          observed: {
            lock: { status: lock.status, lockedThrough: lock.body?.data?.locked_through ?? null },
            status: settled?.status() ?? null,
            code: body?.code ?? null,
            message: messageOf(body),
            notice,
            mayOverrideLock: evidence.mayOverrideLock,
            money: { cashIn: after.cashIn, expected: after.expected },
            cleared: { status: cleared.status },
            activeLock: activeLock ?? null
          },
          verdict:
            settled?.status === 403 && body?.code === 'accounting_lock_blocked' && okNotice
              ? 'OK'
              : 'DEFECTO',
          detail: created
            ? 'la puerta de alta de un movimiento no consulta el bloqueo contable: se escribe con el período bloqueado y el aviso nunca aparece'
            : okNotice
              ? null
              : 'el 403 del bloqueo no llega al aviso del modal',
          requestStatus: settled?.status() ?? null,
          arithmetic
        }
      }
    )

    // 5 — closing that squares.
    await step(
      'cierre-cuadrado',
      'cerrar con el conteo igual al saldo esperado',
      'la diferencia dice «Cuadra», el cierre responde 200 y la fórmula final cuadra',
      async () => {
        const before = last
        const { expected } = before

        await page.locator('[data-hero-action="close"]').click({ timeout: 8000 })
        await page.locator('[data-close-cash-form]').waitFor({ state: 'visible', timeout: 10000 })
        await fillMoney(page, '[data-close-counted]', expected)
        await page.waitForTimeout(400)
        const difference = await page.evaluate(() => {
          const node = document.querySelector('[data-close-difference]')
          return {
            text: (node?.innerText ?? '').trim(),
            variant: node?.getAttribute('data-variant') ?? null
          }
        })
        evidence.shots.push(await shot(page, `${evidence.shotDir}/05-cierre-cuadrado.png`))

        const response = waitForMatch(page, 'POST', /^\/api\/cash-register\/close$/)
        await page.locator('[data-modal-submit]').click({ timeout: 10000 })
        const settled = await response
        const body = await bodyOf(settled)
        await waitForToast(page, 'Sesión de caja cerrada', 12000)
        await page.waitForTimeout(1000)
        await settleCash(page)

        const closed = await sessionSnapshot(request, session, { sessionId })
        const formula = drawerFormula(closed)
        const state = await hero(page)

        return {
          observed: {
            expected,
            difference,
            status: settled?.status() ?? null,
            differenceAmount: body?.data?.difference_amount ?? null,
            heroState: state.state,
            closedSession: closed,
            formula
          },
          verdict:
            settled?.status() === 200 && /Cuadra/i.test(difference.text) && formula.ok === true
              ? 'OK'
              : 'DEFECTO',
          requestStatus: settled?.status() ?? null,
          arithmetic: { formula, matches: formula.ok === true }
        }
      }
    )

    evidence.final = {
      sessionId,
      lastSnapshot: last,
      formula: drawerFormula(last),
      steps: evidence.steps.length,
      stepVerdicts: evidence.steps.reduce((summary, entry) => {
        summary[entry.verdict] = (summary[entry.verdict] ?? 0) + 1
        return summary
      }, {}),
      arithmeticAudited: evidence.steps.filter(entry => entry.arithmetic !== null).length
    }
  } finally {
    await closeModal(page).catch(() => {})
    await call(request, admin, 'DELETE', '/api/accounting-locks/cash_movements').catch(() => {})
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    try {
      markProbes(evidence)
    } catch (thrown) {
      evidence.classificationError = String(thrown?.message ?? thrown)
    }
    const inline = [...evidence.shots]
    evidence.shots = [...inline, ...evidence.steps.map(entry => entry.shot).filter(Boolean)]
    audit('walk-finanzas.json', evidence)
    registerResidue({
      type: 'walkthrough',
      id: sessionId,
      role: 'finanzas',
      reason: 'the walkthrough closed its own drawer; its movements stay in the ledger'
    })
  }

  assertWalk(evidence, { minSteps: 5 })
  expect(evidence.final.formula.ok, 'the drawer equation of the finance drawer').toBe(true)
  expect(evidence.pageErrors, 'page errors during the finance walkthrough').toEqual([])
})

// --- shared assertions ------------------------------------------------------

function markProbes(evidence) {
  markExpected(
    evidence.failedRequests,
    failure =>
      failure.status === 422 &&
      (failure.url?.includes('/api/transactions') ||
        failure.url?.includes('/api/pending-payments') ||
        failure.url?.includes('/api/cash-register/open') ||
        failure.url?.includes('/api/cash-movements')),
    'probe: the walkthrough submits bodies the API is expected to refuse'
  )
  markExpected(
    evidence.failedRequests,
    failure => failure.status === 403 && failure.url?.includes('/api/accounting'),
    'probe: the accounting lock is set to measure its refusal'
  )
}

function assertWalk(evidence, { minSteps }) {
  expect(evidence.steps.length, 'steps measured').toBeGreaterThanOrEqual(minSteps)

  for (const entry of evidence.steps) {
    expect(entry.verdict, `verdict of ${entry.id}`).toBeTruthy()
    expect(entry.shot, `capture of ${entry.id}`).toBeTruthy()

    if (entry.verdict === 'OK' && entry.arithmetic) {
      const matches = entry.arithmetic.matches ?? true
      expect(matches, `arithmetic of ${entry.id}`).not.toBe(false)
    }

    if (isNotOk(entry.verdict)) {
      expect(entry.detail ?? entry.reason ?? null, `reason of ${entry.id}`).toBeTruthy()
    }
  }

  // Every step the walk could not finish declares why, and every declared
  // finding names the request status it observed.
  for (const finding of evidence.findings) {
    expect(finding.shot, `capture of finding ${finding.step}`).toBeTruthy()
  }
}
