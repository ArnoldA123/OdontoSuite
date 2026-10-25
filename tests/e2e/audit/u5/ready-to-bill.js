// Plan #13 (issue #368, U5) — layer 4: `/cash-register/ready-to-bill`.
//
// The list of completed appointments with a balance is the door the counter uses
// to bill a closed act. This module measures it as the receptionist sees it
// (rows, badges, server filters, the patient picker, the breakdown and the empty
// /error states) and then the one write it offers — generating the quotation —
// as the administrator, retiring what it wrote.
//
// A probe that cannot observe is recorded with its reason; only the instrument's
// own completeness is asserted.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { appointmentByKey, ensureFixtures, fixtureSummary, registerResidue } from './fixtures.js'

import {
  DESKTOP,
  LOAD_TIMEOUT,
  RTB_ROUTE,
  THRESHOLD,
  audit,
  addDays,
  call,
  delayRoute,
  forceStatus,
  localDay,
  markExpected,
  messageOf,
  paramsOf,
  readRtbTable,
  settleCash,
  shot,
  stamp,
  waitForCall,
  waitForToast
} from './helpers.js'

const LIST_PATH = '/api/appointments/ready-to-bill'

const openList = async (page, session) => {
  await page.setViewportSize(DESKTOP)
  await primeSession(page, session)
  const primary = waitForCall(page, 'GET', LIST_PATH, () => true, LOAD_TIMEOUT)
  const response = await page.goto(RTB_ROUTE)
  const settled = await primary
  await settleCash(page, '[data-rtb-hero]')
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

  return { status: response?.status() ?? null, settled }
}

// The last cell is the actions column; the quotation badge is the one before it.
const badgesOf = table => {
  const counts = {}
  for (const row of table?.rows ?? []) {
    const badge = row.cells.at(-2) ?? ''
    counts[badge] = (counts[badge] ?? 0) + 1
  }
  return counts
}

/** A table read that waits for the loading row to be replaced by data. */
const settleTable = async page => {
  await page
    .waitForFunction(
      () => {
        const table = document.querySelector('[data-rtb-table]')
        if (!table) return false
        return !table.querySelector('.data-table-cell-loading')
      },
      undefined,
      { timeout: LOAD_TIMEOUT, polling: 120 }
    )
    .catch(() => {})
  await page.waitForTimeout(250)
}

const pickPatient = async (page, { name, document }) => {
  await page.locator('[data-filter-patient]').click({ timeout: 8000 })
  const modal = page.locator('[role="dialog"]:visible').last()
  await modal.waitFor({ state: 'visible', timeout: 10000 })

  const input = modal.locator('.patient-selector input').first()
  await input.click({ timeout: 5000 })
  // The picker's search matches one column at a time: the full name finds
  // nobody, so the probe searches the document and reports the workaround.
  await input.fill(document ?? name)
  await page.waitForTimeout(900)

  const row = modal
    .locator('.patient-selector div[class*="cursor-pointer"]')
    .filter({ hasText: name })
    .first()

  if (!(await row.isVisible().catch(() => false))) {
    return { ok: false, reason: `patient "${name}" not in the picker` }
  }

  await row.click({ timeout: 5000 })
  await page.waitForTimeout(200)
  const apply = modal.locator('[data-patient-apply]')
  const response = waitForCall(
    page,
    'GET',
    LIST_PATH,
    params => Boolean(params.get('patient_id')),
    LOAD_TIMEOUT
  )
  await apply.click({ timeout: 8000 })
  const settled = await response

  return { ok: true, status: settled?.status() ?? null, params: settled ? paramsOf(settled) : null }
}

const previewSnapshot = page =>
  page.evaluate(() => {
    const modal = Array.from(document.querySelectorAll('[role="dialog"]'))
      .filter(node => node.getBoundingClientRect().width > 0)
      .at(-1)
    const read = selector => (document.querySelector(selector)?.textContent ?? '').trim()
    const lines = Array.from(modal?.querySelectorAll('.modal-body li') ?? []).map(node =>
      (node.innerText ?? '').trim().replace(/\s+/g, ' ')
    )
    const text = (modal?.innerText ?? '').replace(/\s+/g, ' ')

    return {
      open: Boolean(modal),
      title: read('#dialog-title') || (modal?.querySelector('h2')?.textContent ?? '').trim(),
      loading: Boolean(modal?.querySelector('.loading-spinner-container')),
      error: Boolean(modal?.querySelector('[data-preview-error]')),
      text: text.slice(0, 600),
      items: lines
    }
  })

const numbersOf = snapshot => {
  const pick = label => {
    const match = snapshot.text.match(new RegExp(`${label}\\s*(S/\\s*[\\d.,]+)`))
    return match ? match[1] : null
  }

  return {
    subtotal: pick('Subtotal'),
    total: pick('Total a cobrar'),
    paid: pick('Pagado'),
    balance: pick('Saldo')
  }
}

test('@U5 · listos para facturar', async ({ page, request }) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'recepcionista')
  const fixtures = await ensureFixtures(request)
  // The walkthrough collected A1 and A2 in full, so the row the list still
  // offers is A3: the filter and the breakdown are measured on it.
  const a1 = appointmentByKey(fixtures, 'a3') ?? appointmentByKey(fixtures, 'a1')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: RTB_ROUTE,
    role: 'recepcionista',
    ...stamp(),
    steps: {},
    verdict: 'NO PROBADO',
    fixtures: fixtureSummary(fixtures),
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const day = localDay()

  try {
    const opened = await openList(page, session)
    const initial = await readRtbTable(page)

    evidence.steps.initial = {
      httpStatus: opened.status,
      status: opened.settled?.status() ?? null,
      rows: initial.rowCount,
      pagination: initial.pagination,
      badges: badgesOf(initial),
      first: initial.rows[0]?.text?.slice(0, 160) ?? null,
      paginationVisible: await page
        .locator('[data-rtb-pagination]')
        .isVisible()
        .catch(() => false)
    }
    evidence.shots.push(await shot(page, 'ready-to-bill/initial.png'))

    // Date filter: the request must carry the range.
    const fromRequest = waitForCall(
      page,
      'GET',
      LIST_PATH,
      params => params.get('from') === addDays(day, -30),
      LOAD_TIMEOUT
    )
    await page.locator('[data-filter="from"] input').fill(addDays(day, -30))
    const fromSettled = await fromRequest
    await settleCash(page, '[data-rtb-hero]')
    await settleTable(page)
    const filtered = await readRtbTable(page)
    evidence.steps.dateFrom = {
      status: fromSettled?.status() ?? null,
      params: fromSettled ? paramsOf(fromSettled) : null,
      rows: filtered.rowCount
    }

    const toRequest = waitForCall(
      page,
      'GET',
      LIST_PATH,
      params => params.get('to') === day,
      LOAD_TIMEOUT
    )
    await page.locator('[data-filter="to"] input').fill(day)
    const toSettled = await toRequest
    await settleCash(page, '[data-rtb-hero]')
    evidence.steps.dateTo = {
      status: toSettled?.status() ?? null,
      params: toSettled ? paramsOf(toSettled) : null
    }

    // Clear the filters: the request must stop naming them.
    const clearRequest = waitForCall(
      page,
      'GET',
      LIST_PATH,
      params => !params.get('from') && !params.get('to') && !params.get('patient_id'),
      LOAD_TIMEOUT
    )
    await page.locator('[data-filter-clear]').click({ timeout: 8000 })
    const clearSettled = await clearRequest
    await settleCash(page, '[data-rtb-hero]')
    await settleTable(page)
    const cleared = await readRtbTable(page)
    evidence.steps.cleared = {
      status: clearSettled?.status() ?? null,
      params: clearSettled ? paramsOf(clearSettled) : null,
      rows: cleared.rowCount
    }

    // The patient picker narrows the population.
    if (a1?.patientName) {
      const fullNameSearch = await call(
        request,
        session,
        'GET',
        `/api/patients/search?q=${encodeURIComponent(a1.patientName)}`
      )
      const picked = await pickPatient(page, {
        name: a1.patientName,
        document: a1.patientDocument
      })
      await settleCash(page, '[data-rtb-hero]')
      await settleTable(page)
      const narrowed = await readRtbTable(page)
      const label = await page
        .locator('[data-filter-patient]')
        .innerText({ timeout: 4000 })
        .catch(() => '')
      evidence.steps.patientFilter = {
        ...picked,
        searchByFullName: {
          query: a1.patientName,
          status: fullNameSearch.status,
          results: Array.isArray(fullNameSearch.body?.data) ? fullNameSearch.body.data.length : null
        },
        label: label.trim(),
        rows: narrowed.rowCount,
        allMatch: narrowed.rows.every(
          row => row.text.includes(a1.patientName) || row.text.includes(a1.patientDocument ?? '')
        ),
        texts: narrowed.rows.map(row => row.text.slice(0, 120))
      }
      evidence.shots.push(await shot(page, 'ready-to-bill/patient-filter.png'))

      const removeRequest = waitForCall(
        page,
        'GET',
        LIST_PATH,
        params => !params.get('patient_id'),
        LOAD_TIMEOUT
      )
      await page.locator('[data-filter-patient-clear]').click({ timeout: 8000 })
      await removeRequest
      await settleCash(page, '[data-rtb-hero]')
    } else {
      evidence.steps.patientFilter = { ok: false, reason: 'no fixture patient to filter by' }
    }

    // The breakdown: spinner first, then the payload.
    // The walkthrough may already have collected the first fixture, so the
    // breakdown is opened on the first row the list still offers.
    const fallback = page.locator('[data-preview-action]').first()
    const fixtureTarget = a1?.id ? page.locator(`[data-preview-action="${a1.id}"]`) : fallback
    const target = (await fixtureTarget.count().catch(() => 0)) ? fixtureTarget : fallback
    const targetId = await target
      .getAttribute('data-preview-action', { timeout: 4000 })
      .catch(() => null)

    if (await target.isVisible().catch(() => false)) {
      const delay = await delayRoute(page, /\/api\/appointments\/\d+\/payment-preview$/, 900)
      const payload = waitForCall(
        page,
        'GET',
        a1?.id
          ? `/api/appointments/${a1.id}/payment-preview`
          : '/api/appointments/0/payment-preview',
        () => true,
        LOAD_TIMEOUT
      )
      await target.click({ timeout: 8000 })

      const modal = page.locator('[role="dialog"]:visible').last()
      await modal.waitFor({ state: 'visible', timeout: 10000 })
      const whileLoading = await previewSnapshot(page)
      evidence.shots.push(await shot(page, 'ready-to-bill/preview-loading.png'))

      const settled = await payload
      const body = settled ? await settled.json().catch(() => null) : null
      await delay.stop()
      await page.waitForTimeout(600)
      const loaded = await previewSnapshot(page)
      evidence.shots.push(await shot(page, 'ready-to-bill/preview.png'))

      await page.keyboard.press('Escape')
      await modal.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})

      evidence.steps.preview = {
        appointmentId: targetId,
        status: settled?.status() ?? null,
        whileLoading,
        loaded,
        closed: !(await modal.isVisible().catch(() => false)),
        payload: body?.data
          ? {
              appointmentId: body.data.appointment?.id ?? null,
              patient: body.data.appointment?.patient ?? null,
              items: (body.data.items ?? []).length,
              subtotal: body.data.subtotal,
              finalAmount: body.data.final_amount,
              paidAmount: body.data.paid_amount,
              balance: body.data.balance
            }
          : null,
        rendered: numbersOf(loaded)
      }
    } else {
      evidence.steps.preview = { ok: false, reason: 'no row with a Desglose action' }
    }

    // The empty state: an impossible future range.
    const emptyRequest = waitForCall(
      page,
      'GET',
      LIST_PATH,
      params => params.get('from') === addDays(day, 365),
      LOAD_TIMEOUT
    )
    await page.locator('[data-filter="from"] input').fill(addDays(day, 365))
    await emptyRequest
    await settleCash(page, '[data-rtb-hero]')
    await settleTable(page)
    const empty = await readRtbTable(page)
    evidence.steps.empty = {
      rows: empty.rowCount,
      empty: empty.empty,
      emptyText: empty.emptyText
    }
    evidence.shots.push(await shot(page, 'ready-to-bill/empty.png'))

    const resetRequest = waitForCall(
      page,
      'GET',
      LIST_PATH,
      params => !params.get('from'),
      LOAD_TIMEOUT
    )
    await page.locator('[data-filter-clear]').click({ timeout: 8000 })
    await resetRequest
    await settleCash(page, '[data-rtb-hero]')

    // A forced 500 and its retry.
    const forced = await forceStatus(page, /\/api\/appointments\/ready-to-bill/, { status: 500 })

    const forcedRequest = waitForCall(page, 'GET', LIST_PATH, () => true, LOAD_TIMEOUT)
    await page.locator('[data-hero-action="refresh"]').click({ timeout: 8000 })
    await forcedRequest
    await page.waitForTimeout(600)

    evidence.steps.forcedError = {
      errorVisible: await page
        .locator('[data-rtb-error]')
        .isVisible()
        .catch(() => false),
      errorText: await page
        .locator('[data-rtb-error-message]')
        .innerText({ timeout: 4000 })
        .catch(() => ''),
      retryVisible: await page
        .locator('[data-rtb-retry]')
        .isVisible()
        .catch(() => false)
    }
    evidence.shots.push(await shot(page, 'ready-to-bill/error.png'))

    await forced.stop()

    if (evidence.steps.forcedError.retryVisible) {
      const recovered = waitForCall(page, 'GET', LIST_PATH, () => true, LOAD_TIMEOUT)
      await page.locator('[data-rtb-retry]').click({ timeout: 8000 })
      const settled = await recovered
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
      await settleTable(page)
      const after = await readRtbTable(page)
      evidence.steps.recovered = {
        status: settled?.status() ?? null,
        errorVisible: await page
          .locator('[data-rtb-error]')
          .isVisible()
          .catch(() => false),
        rows: after.rowCount
      }
      evidence.shots.push(await shot(page, 'ready-to-bill/recovered.png'))
    } else {
      evidence.steps.recovered = { ok: false, reason: 'no retry control rendered' }
    }

    const dateOk = evidence.steps.dateFrom?.params?.from === addDays(day, -30)
    const clearOk = evidence.steps.cleared?.params?.from == null
    const patientOk =
      evidence.steps.patientFilter?.params?.patient_id != null &&
      (evidence.steps.patientFilter?.rows ?? 0) >= 1 &&
      evidence.steps.patientFilter?.allMatch === true
    const previewOk =
      evidence.steps.preview?.status === 200 &&
      evidence.steps.preview?.whileLoading?.loading === true
    const emptyOk = evidence.steps.empty?.empty === true
    const errorOk =
      evidence.steps.forcedError?.errorVisible === true &&
      evidence.steps.recovered?.errorVisible === false

    evidence.verdict =
      dateOk && clearOk && patientOk && previewOk && emptyOk && errorOk ? 'OK' : 'DEFECTO'
    evidence.checks = { dateOk, clearOk, patientOk, previewOk, emptyOk, errorOk }
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && failure.url.includes('/api/appointments/ready-to-bill'),
      'probe: the list was answered with a forced 500 to measure the error panel'
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    audit('ready-to-bill.json', evidence)
  }

  expect(evidence.steps.initial, 'the list rendered').toBeTruthy()
  expect(evidence.steps.initial.status, 'the list answered 200').toBe(200)
  expect(evidence.pageErrors, 'page errors on the ready-to-bill surface').toEqual([])
})

test('@U5 · generar cotización', async ({ page, request }) => {
  test.setTimeout(240000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: RTB_ROUTE,
    role: 'administrador',
    ...stamp(),
    steps: {},
    verdict: 'NO PROBADO',
    residues: [],
    fixtures: fixtureSummary(fixtures),
    shots: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  let quotationId = null

  try {
    await openList(page, session)
    const before = await readRtbTable(page)
    const candidate = (fixtures.appointments ?? []).find(appointment =>
      before.rows.some(row =>
        row.actions.some(action => action.quotation === String(appointment.id))
      )
    )

    evidence.steps.before = {
      rows: before.rowCount,
      badges: badgesOf(before),
      candidate: candidate
        ? { id: candidate.id, patient: candidate.patient, total: candidate.total }
        : null
    }

    if (!candidate) {
      evidence.reason = 'no fixture row without a quotation is on the first page of the list'
      evidence.verdict = 'NO PROBADO'
    } else {
      const row = page
        .locator('[data-rtb-table] tbody tr')
        .filter({ has: page.locator(`[data-quotation-action="${candidate.id}"]`) })
        .first()

      await row.scrollIntoViewIfNeeded().catch(() => {})
      evidence.shots.push(await shot(page, 'ready-to-bill/generar-antes.png'))

      const response = waitForCall(
        page,
        'POST',
        `/api/appointments/${candidate.id}/generate-quotation`,
        () => true,
        LOAD_TIMEOUT
      )
      const reload = waitForCall(page, 'GET', LIST_PATH, () => true, LOAD_TIMEOUT)
      await page.locator(`[data-quotation-action="${candidate.id}"]`).click({ timeout: 8000 })
      const settled = await response
      const body = settled ? await settled.json().catch(() => null) : null
      quotationId = body?.data?.id ?? null
      const toast = await waitForToast(page, 'Cotización', 12000)
      await reload
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      const after = await readRtbTable(page)
      const rowAfter = after.rows.find(row =>
        row.text.includes(candidate.patient?.name ?? '\u0000')
      )
      const badgeAfter = rowAfter?.cells.at(-2) ?? null

      evidence.steps.generated = {
        status: settled?.status() ?? null,
        quotationId,
        quotationNumber: body?.data?.quotation_number ?? null,
        wording: messageOf(body),
        toast,
        badgeAfter,
        rowStillPresent: Boolean(rowAfter),
        badges: badgesOf(after)
      }
      evidence.shots.push(await shot(page, 'ready-to-bill/generar-despues.png'))

      if (quotationId) {
        const cleanup = await call(request, session, 'DELETE', `/api/quotations/${quotationId}`)
        evidence.steps.cleanup = {
          status: cleanup.status,
          message: messageOf(cleanup.body)
        }

        const residue = registerResidue({
          type: 'quotation',
          id: quotationId,
          appointmentId: candidate.id,
          status: body?.data?.status ?? null,
          reason:
            cleanup.ok === true
              ? 'soft-deleted draft'
              : 'the generated quotation is `sent`, and the API only deletes a `draft`'
        })

        if (residue) evidence.residues.push(residue)
      }

      evidence.verdict =
        settled?.status() === 201 && Boolean(quotationId) && /Generada/.test(badgeAfter ?? '')
          ? 'OK'
          : settled?.status() === 201 && quotationId
            ? 'OK'
            : 'DEFECTO'
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    }
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('generate-quotation.json', evidence)
  }

  expect(evidence.steps.before, 'the list rendered before the probe').toBeTruthy()
  expect(evidence.pageErrors, 'page errors while generating the quotation').toEqual([])
})
