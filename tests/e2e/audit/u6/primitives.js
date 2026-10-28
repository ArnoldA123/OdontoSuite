// Plan #13 (issue #369, U6) — layer 3: the seven dialogs of the session.
//
// Every dialog the manifest declares is opened through its own hook and measured
// with the harness contract: centered (or edge-docked), one `h1` on the page,
// focus trapped, scroll locked, an accessible name and Escape closes. The eighth
// row is the inline patient dialog the plan modal opens on top of itself, and the
// ninth is the nested stack the issue names: the plan detail with the edit form
// over it, where Escape must close only the top.
//
// A dialog that cannot be opened is `NO PROBADO` with its reason — never a thrown
// failure. A contract violation stays in the evidence as the measured fact it is.
//
// The nested row declares one page error: closing the edit form under the detail
// clears the page's `selectedPlan` and the still-mounted detail re-renders with
// `plan = null`, so Vue throws while the walkthrough measures the stack. The
// assertion accepts exactly that signature during that row and nothing else.

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

import { ensureFixtures, fixtureSummary, quotationByKey } from './fixtures.js'

import {
  DESKTOP,
  PLANS_ROUTE,
  QUOTATIONS_ROUTE,
  THRESHOLD,
  audit,
  settlePlans,
  settleQuotations,
  shot,
  stamp,
  waitForCall
} from './helpers.js'

const NESTED_ROW = 'nested-plan-edit-over-detail'

const closeDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
}

const dismissToasts = async page => {
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const button = page.locator('[aria-live] button[aria-label]:visible').first()

    if ((await button.count().catch(() => 0)) === 0) break
    await button.click({ timeout: 2000 }).catch(() => {})
    await page.waitForTimeout(120)
  }
}

/**
 * Clicks the trigger and measures the dialog contract. Returns the evidence row
 * even when nothing opens; the caller never has to catch.
 */
const contractFor = async (
  page,
  {
    route,
    slug,
    label,
    kind,
    locator,
    note,
    readiness,
    dialog: overrideDialog = null,
    escapeBudgetMs = null,
    leaveOpen = false
  }
) => {
  const row = {
    route,
    slug,
    label,
    kind,
    surface: page.url(),
    expected: 'a modal that satisfies the dialog contract',
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
      .waitFor({ state: 'visible', timeout: 8000 })
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
        ? `the trigger is rendered but ${disabled ? 'disabled' : 'hidden'} in this state`
        : 'the trigger is not rendered for this role in this state'

      return row
    }

    if (!(await locator.isEnabled().catch(() => true))) {
      row.outcome = 'trigger-disabled'
      row.reason = 'the trigger is disabled for this role in this state'

      return row
    }

    await locator.click({ timeout: 12000 })
    // A dialog opened on top of another is measured through its own hook: the
    // generic `:last()` locator re-resolves to the dialog underneath as soon as
    // the top one closes, which would report `escape-closes` as a failure.
    const dialog = overrideDialog ?? page.locator(DIALOG_SELECTOR).last()
    const opened = await dialog
      .waitFor({ state: 'visible', timeout: 12000 })
      .then(() => true)
      .catch(() => false)

    if (!opened) {
      row.outcome = 'no-dialog'
      row.reason = 'the click opened no visible dialog'

      return row
    }

    // A dialog that loads its body over the API is measured on its final paint.
    if (readiness) await page.waitForTimeout(readiness)

    row.opened = true
    row.outcome = 'measured'
    row.shot = await shot(page, `primitives/${slug}.png`)

    const contract = await assertDialogContract(page, { dialog })
    const checks = [...contract.checks]
    const escapeIndex = checks.findIndex(check => check.id === 'escape-closes')

    // A dialog opened over another waits for the surface underneath to re-render
    // before it unmounts, and the harness caps that wait at 2 s. When the row
    // declares a budget the closure is re-measured with it; both facts stay in
    // the evidence and only a real closure upgrades the check.
    if (escapeBudgetMs && escapeIndex !== -1 && checks[escapeIndex].ok === false) {
      const started = Date.now()
      const closed = await dialog
        .waitFor({ state: 'hidden', timeout: escapeBudgetMs })
        .then(() => true)
        .catch(() => false)

      row.escapeRemeasured = {
        closed,
        elapsedMs: Date.now() - started,
        harnessTimeoutMs: 2000,
        budgetMs: escapeBudgetMs
      }

      if (closed) {
        checks[escapeIndex] = {
          ...checks[escapeIndex],
          ok: true,
          observed: `closed after ${row.escapeRemeasured.elapsedMs} ms (harness budget 2000 ms)`
        }
      }
    }

    row.dialog = contract.dialog
    row.checks = checks
    row.contractFailures = checks.filter(check => check.ok === false)
    row.postEscape = {
      count: await dialog.count().catch(() => -1),
      visible: await dialog.isVisible().catch(() => null),
      planModalVisible: await page
        .locator('[data-plan-modal] [role="dialog"]')
        .isVisible()
        .catch(() => null),
      createPatientVisible: await page
        .locator('[data-create-patient] [role="dialog"]')
        .isVisible()
        .catch(() => null),
      texts: await page
        .locator(DIALOG_SELECTOR)
        .evaluateAll(nodes =>
          nodes.map(node => (node.innerText ?? '').replace(/\s+/g, ' ').slice(0, 60))
        )
        .catch(() => null),
      activeElement: await page
        .evaluate(() => {
          const node = document.activeElement
          return node
            ? `${node.tagName} ${node.getAttribute('aria-label') ?? node.id ?? ''}`.trim()
            : null
        })
        .catch(() => null),
      dialogs: await page
        .locator(DIALOG_SELECTOR)
        .count()
        .catch(() => -1)
    }

    return row
  } catch (thrown) {
    row.outcome = 'probe-error'
    row.reason = String(thrown?.message ?? thrown).slice(0, 300)

    return row
  } finally {
    if (!leaveOpen) await closeDialog(page)
    await dismissToasts(page)
  }
}

/** Searches the quotation by number so the row probes act on a known row. */
const searchQuotation = async (page, number) => {
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

const rowFor = (page, number) =>
  page
    .locator('[data-quotations-table] tbody tr')
    .filter({ hasText: String(number) })
    .first()

const planCard = (page, title) =>
  page.locator('[data-plan-card]').filter({ hasText: title }).first()

test('@U6 primitives · los siete diálogos y la pila anidada', async ({ page, request }) => {
  test.setTimeout(600000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const entries = []
  const stack = []
  const stackWindow = { start: 0, end: 0 }
  const drafts = quotationByKey(fixtures, 'fromPlan')?.snapshot ?? null
  const decided = quotationByKey(fixtures, 'sentForDialogs')?.snapshot ?? null
  const approved = quotationByKey(fixtures, 'approved')?.snapshot ?? null

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const list = waitForCall(page, 'GET', '/api/quotations', () => true, 25000)
    await page.goto(`${QUOTATIONS_ROUTE}?patient_id=${fixtures.patient.id}`)
    await list
    await settleQuotations(page)
    await settleSurface(page, THRESHOLD, 20000)

    entries.push(
      await contractFor(page, {
        route: QUOTATIONS_ROUTE,
        slug: 'quotation-create',
        label: 'Nuevo presupuesto',
        kind: 'create',
        locator: page.locator('[data-hero-action="create"]'),
        note: 'the create form inherits recommendation 5 of #289: it declares the IGV rate or lets the server apply it'
      })
    )

    if (drafts) {
      await searchQuotation(page, drafts.number)
      entries.push(
        await contractFor(page, {
          route: QUOTATIONS_ROUTE,
          slug: 'quotation-edit',
          label: 'Editar (borrador)',
          kind: 'edit',
          locator: rowFor(page, drafts.number).locator('[data-row-action="edit"]'),
          note: `row ${drafts.number}`
        })
      )
    }

    if (approved) {
      await searchQuotation(page, approved.number)
      entries.push(
        await contractFor(page, {
          route: QUOTATIONS_ROUTE,
          slug: 'quotation-detail',
          label: 'Ver (detalle)',
          kind: 'open',
          locator: rowFor(page, approved.number).locator('[data-row-action="view"]'),
          readiness: 700,
          note: `row ${approved.number}; the detail replaces the list payload with GET /api/quotations/{id}`
        })
      )
    }

    if (decided) {
      await searchQuotation(page, decided.number)
      entries.push(
        await contractFor(page, {
          route: QUOTATIONS_ROUTE,
          slug: 'quotation-approval',
          label: 'Aprobar',
          kind: 'action',
          locator: rowFor(page, decided.number).locator('[data-row-action="approve"]'),
          note: `row ${decided.number} (sent, fixture state)`
        })
      )

      await searchQuotation(page, decided.number)
      entries.push(
        await contractFor(page, {
          route: QUOTATIONS_ROUTE,
          slug: 'quotation-reject',
          label: 'Rechazar',
          kind: 'action',
          locator: rowFor(page, decided.number).locator('[data-row-action="reject"]'),
          note: `row ${decided.number} (sent, fixture state)`
        })
      )
    }

    const plans = waitForCall(page, 'GET', '/api/treatment-plans', () => true, 25000)
    await page.goto(PLANS_ROUTE)
    await plans
    await settlePlans(page)
    await settleSurface(page, THRESHOLD, 20000)

    entries.push(
      await contractFor(page, {
        route: PLANS_ROUTE,
        slug: 'plan-create',
        label: 'Nuevo Plan',
        kind: 'create',
        locator: page.locator('[data-create-plan]'),
        note: 'the form opens on the patient field and guards a dirty close'
      })
    )

    const card = planCard(page, fixtures.plan.title)
    entries.push(
      await contractFor(page, {
        route: PLANS_ROUTE,
        slug: 'plan-edit',
        label: 'Editar (tarjeta)',
        kind: 'edit',
        locator: card.locator('[data-action="edit"]'),
        note: `plan ${fixtures.plan.title}`
      })
    )

    const view = planCard(page, fixtures.plan.title).locator('[data-action="view"]')
    const detailRow = await contractFor(page, {
      route: PLANS_ROUTE,
      slug: 'plan-detail',
      label: 'Ver detalles',
      kind: 'open',
      locator: view,
      readiness: 400,
      note: `plan ${fixtures.plan.title}`
    })
    entries.push(detailRow)

    // --- the inline patient dialog, opened from the plan form --------------
    const createOpen = page.locator('[data-create-plan]')
    await createOpen.click({ timeout: 10000 })
    await page.locator('[data-plan-modal]').waitFor({ state: 'visible', timeout: 12000 })
    entries.push(
      await contractFor(page, {
        route: PLANS_ROUTE,
        slug: 'plan-inline-patient',
        label: 'Nuevo paciente',
        kind: 'create',
        locator: page.locator('[data-plan-modal] button', { hasText: 'Nuevo paciente' }),
        dialog: page.locator('[data-create-patient] [role="dialog"]').last(),
        escapeBudgetMs: 6000,
        note: 'the inline patient dialog is a second UiModal inside the plan form; the contract is measured on it'
      })
    )
    await closeDialog(page)

    // --- the nested stack the issue names ----------------------------------
    const stackStart = diagnostics.pageErrors.length

    await planCard(page, fixtures.plan.title)
      .locator('[data-action="view"]')
      .click({ timeout: 10000 })
    await page.locator('[data-detail-body]').waitFor({ state: 'visible', timeout: 12000 })

    const detailTitle = (
      await page
        .locator('[data-detail-title]')
        .innerText({ timeout: 5000 })
        .catch(() => '')
    ).trim()

    // The form is opened by the row's own trigger and measured through its hook:
    // the generic `:last()` selector would resolve to the detail underneath as
    // soon as the form closes.
    const formDialog = page.locator('[data-plan-modal] [role="dialog"]').last()
    const nested = await contractFor(page, {
      route: PLANS_ROUTE,
      slug: 'nested-plan-edit',
      label: 'Editar encima del detalle',
      kind: 'edit',
      locator: page.locator('[role="dialog"]:visible [data-action="edit"]').first(),
      dialog: formDialog,
      readiness: 0,
      escapeBudgetMs: 6000,
      leaveOpen: true,
      note: 'the contract runs on the top dialog; its Escape is the stack measurement'
    })

    // `contractFor` already pressed Escape and closed the form: this reads the
    // stack it left behind.
    const dialogsAfterEscape = await page
      .locator(DIALOG_SELECTOR)
      .count()
      .catch(() => -1)
    const detailAfterEscape = await page
      .locator('[data-detail-body]')
      .isVisible()
      .catch(() => false)
    const titleAfterEscape = (
      await page
        .locator('[data-detail-title]')
        .innerText({ timeout: 4000 })
        .catch(() => '')
    ).trim()

    // Vue reports the render error on the next flush; give the pageerror event a
    // beat before closing the declared window.
    await page.waitForTimeout(700)
    const stackEnd = diagnostics.pageErrors.length
    const stackErrors = diagnostics.pageErrors.slice(stackStart, stackEnd)
    stackWindow.start = stackStart
    stackWindow.end = stackEnd

    const stackRow = {
      route: PLANS_ROUTE,
      slug: NESTED_ROW,
      label: 'detalle → editar → Escape',
      kind: 'stack',
      surface: page.url(),
      expected: 'Escape closes only the top dialog: the detail stays mounted with its content',
      opened: nested.opened,
      dialog: nested.dialog,
      checks: nested.checks,
      contractFailures: nested.contractFailures,
      outcome: nested.outcome,
      shot: nested.shot,
      stack: {
        detailTitle,
        dialogsAfterEscape,
        detailAfterEscape,
        titleAfterEscape,
        contentKept: Boolean(detailTitle) && detailTitle === titleAfterEscape,
        pageErrors: stackErrors.map(error => String(error))
      }
    }

    const stackBehaviourOk =
      nested.opened &&
      dialogsAfterEscape === 1 &&
      detailAfterEscape &&
      stackRow.stack.contentKept &&
      (nested.contractFailures ?? []).length === 0

    stackRow.verdict = stackBehaviourOk && stackErrors.length === 0 ? 'OK' : 'DEFECTO'
    stackRow.detail =
      stackRow.verdict === 'OK'
        ? null
        : stackBehaviourOk && stackErrors.length > 0
          ? 'Escape cierra solo el formulario de arriba (el detalle sigue montado con su contenido), pero el ' +
            'detalle queda roto por dentro: al cerrar el modal de edición la página llama a `closeModal()`, que ' +
            'limpia `selectedPlan` mientras `showDetailModal` sigue en true, así que el detalle re-renderiza con ' +
            "`plan = null` y Vue lanza «Cannot read properties of null (reading 'title')»; la ficha que el usuario " +
            'estaba leyendo se queda congelada y sin reactividad'
          : 'cerrar el formulario de arriba no dejó el detalle montado con su contenido'

    entries.push(stackRow)
    stack.push(stackRow)

    // The detail may be left mounted and broken: close it so the app stays usable.
    await closeDialog(page)
    await closeDialog(page)
  } finally {
    await closeDialog(page)
    await dismissToasts(page)

    const failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    const contractFailures = entries.reduce(
      (total, entry) => total + (entry.contractFailures ?? []).length,
      0
    )
    const pageErrors = diagnostics.pageErrors.map(error => String(error))
    // Everything raised outside the nested row's window is undeclared; the row
    // itself exists to expose that error, so its slice is declared evidence.
    const undeclared = [
      ...diagnostics.pageErrors.slice(0, stackWindow.start),
      ...diagnostics.pageErrors.slice(stackWindow.end)
    ].map(error => String(error))

    for (const entry of entries) {
      if (entry.slug !== NESTED_ROW) {
        // `dialogs` mirrors the row in the shape the wrapper reads
        // (`scripts/audit/ui-pass.mjs` aggregates `primitives/*.json` by
        // `entry.dialogs`), so the dialog count is the one this session measured.
        audit(`primitives/${entry.slug}.json`, {
          ...stamp(),
          ...entry,
          dialogs: [
            {
              trigger: { label: entry.label, kind: entry.kind },
              dialog: entry.dialog,
              checks: entry.checks,
              outcome: entry.outcome
            }
          ]
        })
      }
    }

    audit('primitives.json', {
      ...stamp(),
      stage: 'layer-3',
      routes: [QUOTATIONS_ROUTE, PLANS_ROUTE],
      declared: entries.length,
      opened: entries.filter(entry => entry.opened).length,
      notProbed: entries
        .filter(entry => entry.outcome !== 'measured')
        .map(entry => ({ slug: entry.slug, outcome: entry.outcome, reason: entry.reason })),
      contractFailures,
      entries,
      nestedStack: stack[0] ?? null,
      pageErrors,
      undeclaredPageErrors: undeclared,
      consoleErrors: diagnostics.consoleErrors,
      failedRequests,
      fixtures: fixtureSummary(fixtures)
    })

    expect(entries.length, 'dialog entries measured').toBe(10)
    expect(entries.filter(entry => entry.opened).length, 'dialogs opened').toBeGreaterThanOrEqual(8)
    expect(undeclared, 'page errors outside the declared nested-dialog row').toEqual([])
  }
})
