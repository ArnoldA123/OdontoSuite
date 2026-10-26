// Plan #13 (issue #369, U6) — the drivers both walkthroughs share.
//
// The step recorder, the toast/dialog cleanup and the pickers of the two
// surfaces. No `test()` lives here: `--grep @U6` selects the rows
// `walk.js` (presupuestos) and `walk-plans.js` (planes) declare.

import { isNotOk, settleQuotations, shot, waitForCall } from './helpers.js'

export const MODAL = '[role="dialog"]:visible'
export const NESTED_STEP = 'nested-escape'

export const formLocator = page => page.locator('[data-plan-modal]')

/**
 * The step recorder: one entry per action, with the capture, the verdict, the
 * arithmetic and the request status. A probe that throws becomes `NO PROBADO`
 * with its reason instead of ending the walkthrough.
 */
export const recorder = (page, evidence, state) => {
  let index = 0

  return async (id, action, expected, run) => {
    index += 1
    const seq = String(index).padStart(2, '0')
    const entry = {
      step: `${seq}-${id}`,
      id,
      action,
      expected,
      obtained: null,
      verdict: 'NO PROBADO',
      reason: null,
      arithmetic: null,
      requestStatus: null,
      shot: null,
      detail: null
    }

    state.currentStep = id
    const before = state.pageErrors.length
    let broken = false

    try {
      const result = (await run(entry)) ?? {}
      Object.assign(entry, result)
    } catch (thrown) {
      broken = true
      entry.verdict = 'NO PROBADO'
      entry.reason = `probe error: ${String(thrown?.message ?? thrown).slice(0, 300)}`
    }

    entry.pageErrors = state.pageErrors.slice(before).map(error => error.message)

    // A probe that died mid-form leaves its dialog over the page; closing it here
    // keeps the next step measurable instead of cascading a timeout. A step that
    // merely observed a product defect leaves the surface as the user would have
    // it (the next step may still be inside the same form).
    if (broken) await closeTop(page).catch(() => {})

    try {
      entry.shot = await shot(page, `${evidence.shotDir}/${entry.step}.png`)
    } catch {
      entry.shot = null
    }

    evidence.steps.push(entry)

    if (isNotOk(entry.verdict)) {
      evidence.findings.push({
        step: entry.step,
        action: entry.action,
        expected: entry.expected,
        obtained: entry.obtained,
        verdict: entry.verdict,
        requestStatus: entry.requestStatus,
        detail: entry.detail ?? entry.reason ?? null,
        shot: entry.shot
      })
    }

    return entry
  }
}

/**
 * Dismisses the visible toasts. They are fixed over the bottom-right corner and
 * their own dismiss button is the user's door; a toast that covers the control
 * the next step needs would otherwise turn into a click timeout.
 */
export const dismissToasts = async page => {
  const dismissed = []

  for (let attempt = 0; attempt < 6; attempt += 1) {
    const button = page.locator('[aria-live] button[aria-label]:visible').first()

    if ((await button.count().catch(() => 0)) === 0) break

    const label = await button.getAttribute('aria-label').catch(() => null)
    await button.click({ timeout: 2000 }).catch(() => {})
    dismissed.push(label)
    await page.waitForTimeout(150)
  }

  return dismissed
}

export const closeTop = async page => {
  const dialog = page.locator(MODAL).last()

  if (!(await dialog.count().catch(() => 0))) return false
  if (!(await dialog.isVisible().catch(() => false))) return false

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 6000 }).catch(() => {})

  return !(await dialog.isVisible().catch(() => false))
}

// --- quotation drivers ------------------------------------------------------

export const searchQuotation = async (page, number) => {
  const input = page.locator('[data-quotations-search] input').first()
  const waited = waitForCall(
    page,
    'GET',
    '/api/quotations',
    params => (params.get('q') ?? '') === String(number),
    12000
  )

  await input.fill(String(number))
  const response = await waited
  await settleQuotations(page)

  return response?.status() ?? null
}

export const clearQuotationSearch = async page => {
  const input = page.locator('[data-quotations-search] input').first()
  const waited = waitForCall(
    page,
    'GET',
    '/api/quotations',
    params => (params.get('q') ?? '') === '',
    12000
  )

  await input.fill('')
  const response = await waited
  await settleQuotations(page)

  return response?.status() ?? null
}

export const rowFor = (page, number) =>
  page
    .locator('[data-quotations-table] tbody tr')
    .filter({ hasText: String(number) })
    .first()

export const pickPatient = async (page, scope, document) => {
  const input = page.locator(`${scope} .patient-selector input`).first()
  const searched = waitForCall(
    page,
    'GET',
    '/api/patients/search',
    params => String(params.get('q') ?? '').includes(String(document)),
    12000
  )

  await input.click({ timeout: 8000 })
  await input.fill(String(document))
  const response = await searched
  await page.waitForTimeout(250)

  const option = page.locator(`${scope} .patient-selector div[class*="cursor-pointer"]`).first()
  const visible = await option
    .waitFor({ state: 'visible', timeout: 8000 })
    .then(() => true)
    .catch(() => false)

  if (!visible)
    return { ok: false, status: response?.status() ?? null, reason: 'patient not found' }

  await option.click({ timeout: 8000 })
  await page.waitForTimeout(150)

  return { ok: true, status: response?.status() ?? null }
}

/**
 * Picks the fixture's procedure from a `ProcedureCatalogPicker`.
 *
 * The picker filters the 12 rows it already loaded and reloads page 1 when the
 * term changes, so the code is typed first (measured: it does not reach the
 * server) and the list is walked page by page until the row appears.
 */
/**
 * Opens the "Elegir del catálogo" panel, retrying the click: the toggle is one
 * boolean and a click that lands while the item re-renders closes it again.
 */
export const openCatalogPanel = async (page, toggleSelector, panelSelector, attempts = 3) => {
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    if (
      await page
        .locator(panelSelector)
        .isVisible()
        .catch(() => false)
    ) {
      return { ok: true, attempts: attempt - 1 }
    }

    await page
      .locator(toggleSelector)
      .first()
      .click({ timeout: 8000 })
      .catch(() => {})

    const opened = await page
      .locator(panelSelector)
      .waitFor({ state: 'visible', timeout: 4000 })
      .then(() => true)
      .catch(() => false)

    if (opened) return { ok: true, attempts: attempt }
  }

  return { ok: false, attempts, reason: 'the catalog panel never opened' }
}

export const pickCatalogProcedure = async (page, panel, code) => {
  const search = page.locator(`${panel} input`).first()

  // The panel is a wrapper that first paints the picker's own spinner; the
  // search field only exists once `/api/procedure-catalog` answered, so the
  // reader waits for it instead of inferring readiness from the panel.
  const searched = await search
    .waitFor({ state: 'visible', timeout: 15000 })
    .then(() => true)
    .catch(() => false)

  let resultsAfterSearch = null

  if (searched) {
    await search.fill(code)
    await page.waitForTimeout(1200)
    resultsAfterSearch = await page.locator(`${panel} button`, { hasText: code }).count()
    await search.fill('')
    await page.waitForTimeout(400)
  }

  // The picker pages 12 rows at a time. The desktop pagination names its
  // buttons `Ir a la página N`; the mobile one carries `Página siguiente`. Both
  // are tried, and every jump waits for the reload it triggers.
  for (let pageIndex = 1; pageIndex <= 8; pageIndex += 1) {
    const target = page.locator(`${panel} button`, { hasText: code }).first()

    if ((await target.count().catch(() => 0)) > 0) {
      await target.scrollIntoViewIfNeeded().catch(() => {})
      await target.click({ timeout: 8000 })
      return { ok: true, pagesWalked: pageIndex, resultsAfterSearch }
    }

    const jump = page
      .locator(`${panel} [aria-label="Ir a la página ${pageIndex + 1}"]:visible`)
      .first()
    const next = page.locator(`${panel} [aria-label="Página siguiente"]:visible`).first()
    const control = (await jump.count().catch(() => 0)) > 0 ? jump : next

    if ((await control.count().catch(() => 0)) === 0) break
    if (await control.isDisabled().catch(() => true)) break

    const loaded = waitForCall(page, 'GET', '/api/procedure-catalog', () => true, 10000)
    await control.click({ timeout: 8000 }).catch(() => {})
    await loaded
    await page.waitForTimeout(150)
  }

  return {
    ok: false,
    pagesWalked: 8,
    resultsAfterSearch,
    reason: `the procedure "${code}" never appeared in the picker`
  }
}

export const quotationRowStatus = (rows, number) =>
  (rows?.rows ?? []).find(row => row.number === String(number))?.status ?? null

export const quotationRowTotal = (rows, number) =>
  (rows?.rows ?? []).find(row => row.number === String(number))?.totalCents ?? null

export const planCard = (page, title) =>
  page.locator('[data-plan-card]').filter({ hasText: title }).first()

export const changePlanStatus = async (page, title, status) => {
  const card = planCard(page, title)
  await card.locator('[data-status-toggle]').click({ timeout: 10000 })
  const menu = page.locator('[data-status-menu]').first()
  await menu.waitFor({ state: 'visible', timeout: 8000 })
  const posted = waitForCall(
    page,
    'POST',
    /\/api\/treatment-plans\/\d+\/change-status/,
    () => true,
    30000
  )
  await menu.locator(`[data-status-option="${status}"]`).click({ timeout: 8000 })
  const response = await posted

  return { status: response?.status() ?? null }
}

export const planBadge = async (page, title) => {
  const header = await planCard(page, title)
    .locator('header')
    .innerText({ timeout: 6000 })
    .catch(() => '')

  const labels = ['Borrador', 'Propuesto', 'Aprobado', 'En Progreso', 'Completado', 'Cancelado']
  return labels.find(label => header.includes(label)) ?? null
}
