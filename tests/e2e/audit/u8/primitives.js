// Plan #13 (issue #371, U8) — layer 3: the dialogs of the catalogue module.
//
// The four dialogs the manifest declares — the CSV importer, the procedure form,
// the duplicate modal and the branch-price form — plus the two confirmations the
// pages own (deactivate a procedure, retire a branch price). Every one is opened
// through its own hook and measured with the harness contract: centered or
// docked, one `h1` on the page, focus trapped, scroll locked, an accessible name
// and Escape closes.
//
// A dialog that cannot be opened is `NO PROBADO` with its reason — never a
// thrown failure. A contract violation stays in the evidence as the measured
// fact it is: the two confirmations and the four modals share the primitive, so
// one failing row is the class "primitiva mal usada" the issue groups.

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

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  CATALOG_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  THRESHOLD,
  UI,
  audit,
  classifyConsole,
  closeAllDialogs,
  dialogStack,
  dismissToasts,
  settleCatalog,
  settleCatalogDetail,
  shot,
  stamp
} from './helpers.js'

const closeDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
}

/**
 * Opens the trigger and measures the dialog contract. Returns the evidence row
 * even when nothing opens; the caller never has to catch.
 */
const contractFor = async (
  page,
  { route, slug, label, kind, locator, note, readiness = 350, dialog: overrideDialog = null }
) => {
  const row = {
    route,
    slug,
    label,
    kind,
    surface: page.url(),
    expected: 'un diálogo que cumple el contrato',
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
      .waitFor({ state: 'visible', timeout: 10000 })
      .then(() => true)
      .catch(() => false)

    if (!visible) {
      const present = await locator.count().catch(() => 0)
      row.outcome = 'trigger-not-visible'
      row.present = present
      row.reason = present
        ? 'the trigger is rendered but hidden in this state'
        : 'the trigger is not rendered for this role in this state'

      return row
    }

    if (!(await locator.isEnabled().catch(() => true))) {
      row.outcome = 'trigger-disabled'
      row.reason = 'the trigger is disabled for this role in this state'

      return row
    }

    await locator.click({ timeout: 12000 })

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

    if (readiness) await page.waitForTimeout(readiness)

    row.opened = true
    row.outcome = 'measured'
    row.dialogsBeforeContract = await dialogStack(page)
    row.shot = await shot(page, `primitives/${slug}.png`)

    const contract = await assertDialogContract(page, { dialog })
    row.dialog = contract.dialog
    row.checks = contract.checks
    row.contractFailures = contract.checks.filter(check => check.ok === false)
    row.afterEscape = {
      dialogs: await page
        .locator(DIALOG_SELECTOR)
        .count()
        .catch(() => -1),
      stack: await dialogStack(page)
    }

    return row
  } catch (thrown) {
    row.outcome = 'probe-error'
    row.reason = String(thrown?.message ?? thrown).slice(0, 300)

    return row
  } finally {
    await closeDialog(page)
    await dismissToasts(page)
  }
}

const PRIMITIVES = (globalThis.__AUDIT_U8_PRIMITIVES__ ??= { entries: [], aggregates: [] })

const write = (entries, extra) => {
  PRIMITIVES.entries.push(...entries)
  PRIMITIVES.aggregates.push(extra)

  for (const entry of entries) {
    audit(`primitives/${entry.slug}.json`, {
      ...stamp(),
      ...entry,
      // `dialogs` is the shape `scripts/audit/ui-pass.mjs` aggregates.
      dialogs: [
        {
          trigger: { label: entry.label, kind: entry.kind },
          dialog: entry.dialog,
          checks: entry.checks,
          outcome: entry.outcome
        }
      ],
      pageErrors: []
    })
  }

  audit('primitives.json', { ...stamp(), stage: 'layer-3', ...extra })
}

test('@U8 primitives · los diálogos del catálogo', async ({ page, request }) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const entries = []

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(CATALOG_ROUTE)
    await settleCatalog(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    entries.push(
      await contractFor(page, {
        route: CATALOG_ROUTE,
        slug: 'catalog-form',
        label: 'Nuevo procedimiento',
        kind: 'create',
        locator: page.locator(UI.catalog.create),
        note: 'el formulario declara aplicabilidad, equivalencias y estado en secciones'
      })
    )

    entries.push(
      await contractFor(page, {
        route: CATALOG_ROUTE,
        slug: 'catalog-import',
        label: 'Importar CSV',
        kind: 'export',
        locator: page.locator(UI.catalog.import),
        note: 'el importador declara el formato esperado y la zona de arrastre'
      })
    )

    entries.push(
      await contractFor(page, {
        route: CATALOG_ROUTE,
        slug: 'catalog-duplicate',
        label: 'Duplicar procedimiento',
        kind: 'action',
        locator: page.locator(UI.catalog.rowAction('duplicate')).first(),
        note: 'el duplicado propone código y nombre derivados del origen'
      })
    )

    entries.push(
      await contractFor(page, {
        route: CATALOG_ROUTE,
        slug: 'catalog-deactivate',
        label: 'Desactivar procedimiento → confirmar',
        kind: 'delete',
        locator: page.locator(UI.catalog.rowAction('deactivate')).first(),
        dialog: page.locator('[role="alertdialog"]').last(),
        note: 'la confirmación global (UiConfirmDialog, role=alertdialog) sobre la lista'
      })
    )

    // The detail page: the tariff tab mounts BranchTariffPanel lazily, so the
    // tab is opened before the two tariff dialogs are measured.
    await page.goto(`${CATALOG_ROUTE}/${fixtures.procedures.surface.id}`)
    await settleCatalogDetail(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await page
      .locator('[role="tab"]')
      .filter({ hasText: 'Tarifario' })
      .first()
      .click({ timeout: 10000 })
      .catch(() => {})
    await page
      .locator(UI.detail.tariff)
      .waitFor({ state: 'visible', timeout: 12000 })
      .catch(() => {})
    await page
      .locator(UI.detail.tariffRows)
      .first()
      .waitFor({ state: 'visible', timeout: 12000 })
      .catch(() => {})

    entries.push(
      await contractFor(page, {
        route: `${CATALOG_ROUTE}/:id`,
        slug: 'tariff-form',
        label: 'Nuevo precio (tarifario de la sede)',
        kind: 'create',
        locator: page.locator(UI.detail.tariffAdd),
        note: 'el precio de sede declara importe, ventana de vigencia y estado'
      })
    )

    entries.push(
      await contractFor(page, {
        route: `${CATALOG_ROUTE}/:id`,
        slug: 'tariff-remove',
        label: 'Retirar precio de sede → confirmar',
        kind: 'delete',
        locator: page.locator(UI.detail.tariffRemove).first(),
        dialog: page.locator('[role="alertdialog"]').last(),
        note: 'la confirmación de retiro es un UiConfirmDialog con el importe en el mensaje'
      })
    )

    entries[entries.length - 1].fixturePrice = fixtures.branchPrice
      ? {
          id: fixtures.branchPrice.id,
          price: fixtures.branchPrice.price,
          validFrom: fixtures.branchPrice.validFrom,
          validTo: fixtures.branchPrice.validTo
        }
      : null
  } finally {
    await closeAllDialogs(page)

    write(entries, {
      routes: [CATALOG_ROUTE, `${CATALOG_ROUTE}/:id`],
      role: 'administrador',
      declared: entries.length,
      opened: entries.filter(entry => entry.opened).length,
      notProbed: entries
        .filter(entry => entry.outcome !== 'measured')
        .map(entry => ({ slug: entry.slug, outcome: entry.outcome, reason: entry.reason })),
      contractFailures: entries.reduce(
        (total, entry) => total + (entry.contractFailures ?? []).length,
        0
      ),
      entries,
      pageErrors: diagnostics.pageErrors.map(error => String(error)),
      consoleErrors: diagnostics.consoleErrors,
      consoleClassified: classifyConsole(diagnostics.consoleErrors),
      failedRequests: classifyFailures(diagnostics.failedRequests, 'admin'),
      fixtures: fixtureSummary(fixtures)
    })
  }

  // The instrument asserts that it ran, not that the product is perfect: the
  // contract failures above are the session's evidence, not a red suite.
  expect(entries.length, 'catalogue dialog entries').toBe(6)
  expect(
    entries.filter(entry => entry.opened).length,
    'catalogue dialogs opened'
  ).toBeGreaterThanOrEqual(5)
  expect(
    entries.every(entry => entry.shot || entry.outcome !== 'measured'),
    'every measured dialog has its capture'
  ).toBe(true)
})
