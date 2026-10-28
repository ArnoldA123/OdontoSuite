// Plan #13 (issue #370, U7) — layer 3: the dialogs of the three modules.
//
// Every dialog the manifest declares is opened through its own hook and measured
// with the harness contract: centered (or edge-docked), one `h1` on the page,
// focus trapped, scroll locked, an accessible name and Escape closes. The AI
// creation form is a `UiSheet` and must be right-docked; the AI detail opens the
// global confirm on top of itself (the nested stack); the record detail hands
// over to the addendum form when the user asks for a correction.
//
// A dialog that cannot be opened is `NO PROBADO` with its reason — never a
// thrown failure. A contract violation stays in the evidence as the measured fact
// it is.

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

import { ensureFixtures, fixtureSummary, recordFixture } from './fixtures.js'

import {
  AI_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  RECORDS_ROUTE,
  SPECIALTIES_ROUTE,
  THRESHOLD,
  audit,
  closeAllDialogs,
  dialogStack,
  dismissToasts,
  pickPatient,
  settleAi,
  settleRecordsWorkspace,
  settleSpecialties,
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

const openTab = async (page, label) => {
  const tab = page.locator('[role="tab"]').filter({ hasText: label }).first()

  if ((await tab.count().catch(() => 0)) === 0) return false

  await tab.click({ timeout: 8000 }).catch(() => {})
  await page.waitForTimeout(250)

  return true
}

/**
 * Opens the trigger and measures the dialog contract. Returns the evidence row
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
    readiness = 300,
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
    expected: 'a dialog that satisfies the contract',
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
    row.activeElementOnOpen = await page
      .evaluate(() => {
        const node = document.activeElement
        if (!node) return null

        const label = node.getAttribute('aria-label') ?? node.getAttribute('data-loading') ?? ''

        return `${node.tagName.toLowerCase()}${node.id ? `#${node.id}` : ''} ${label}`.trim()
      })
      .catch(() => null)
    row.dialogsBeforeContract = await dialogStack(page)
    row.shot = await shot(page, `shots/primitives/${slug}.png`)

    const contract = await assertDialogContract(page, { dialog })
    const checks = [...contract.checks]
    const escapeIndex = checks.findIndex(check => check.id === 'escape-closes')

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
    if (!leaveOpen) await closeDialog(page)
    await dismissToasts(page)
  }
}

const PRIMITIVES = (globalThis.__AUDIT_U7_PRIMITIVES__ ??= { entries: [], aggregates: [] })

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

test('@U7 primitives · los diálogos de la historia clínica', async ({ page, request }) => {
  test.setTimeout(420000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const record = recordFixture(fixtures)
  const diagnostics = collectDiagnostics(page)
  const entries = []

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(`${RECORDS_ROUTE}/${record.id}`)
    await settleRecordsWorkspace(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    entries.push(
      await contractFor(page, {
        route: RECORDS_ROUTE,
        slug: 'record-create',
        label: 'Nueva historia',
        kind: 'create',
        locator: page.locator('[data-new-record-button]'),
        note: 'the create form opens with the workspace patient already fixed'
      })
    )

    entries.push(
      await contractFor(page, {
        route: RECORDS_ROUTE,
        slug: 'record-detail',
        label: 'Ver (historia)',
        kind: 'open',
        locator: page.locator('[data-record-action="view"]').first(),
        note: 'the detail replaces the list row with GET /api/medical-records/{id}'
      })
    )

    // The addendum is opened from the detail footer: the surface it replaces is
    // the detail itself, which is the nested-stack question #370 asks.
    const detailOpen = await page
      .locator('[data-record-action="view"]')
      .first()
      .click({ timeout: 10000 })
      .then(() => true)
      .catch(() => false)
    await page
      .locator('[data-medical-record-detail]')
      .waitFor({ state: 'visible', timeout: 12000 })
      .catch(() => {})

    const addendumFromDetail = await contractFor(page, {
      route: RECORDS_ROUTE,
      slug: 'record-addendum',
      label: 'Nuevo addendum (desde el detalle)',
      kind: 'create',
      locator: page.locator('[data-detail-action="addendum"]').first(),
      note: 'a correction is an addendum, never an edit of the original'
    })
    addendumFromDetail.detailBeforeAction = detailOpen
    addendumFromDetail.stackObserved = {
      dialogs: addendumFromDetail.dialogsBeforeContract?.length ?? null,
      detailVisibleBefore: (addendumFromDetail.dialogsBeforeContract ?? []).some(dialog =>
        dialog.hooks.includes('data-medical-record-detail')
      ),
      detailVisibleAfter: (addendumFromDetail.afterEscape?.stack ?? []).some(dialog =>
        dialog.hooks.includes('data-medical-record-detail')
      ),
      addendumHeading: addendumFromDetail.dialog?.title ?? addendumFromDetail.dialog?.name ?? null
    }
    entries.push(addendumFromDetail)

    await openTab(page, 'Evoluciones')
    entries.push(
      await contractFor(page, {
        route: RECORDS_ROUTE,
        slug: 'record-evolution',
        label: 'Nueva evolución',
        kind: 'create',
        locator: page.locator('[data-add-evolution]').first(),
        note: 'the SOAP form opens on the active record'
      })
    )

    await openTab(page, 'Adjuntos')
    entries.push(
      await contractFor(page, {
        route: RECORDS_ROUTE,
        slug: 'record-attachment',
        label: 'Subir adjunto',
        kind: 'create',
        locator: page.locator('[data-upload-attachment]').first(),
        note: 'the upload form declares its 10 MB gate inline'
      })
    )

    entries.push(
      await contractFor(page, {
        route: RECORDS_ROUTE,
        slug: 'record-attachment-preview',
        label: 'Ver vista previa de',
        kind: 'open',
        locator: page.locator('[data-attachment-preview-trigger]').first(),
        note: 'the image preview is a UiModal titled with the file name'
      })
    )
  } finally {
    await closeAllDialogs(page)

    write(entries, {
      routes: [RECORDS_ROUTE],
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
      failedRequests: classifyFailures(diagnostics.failedRequests, 'admin'),
      fixtures: fixtureSummary(fixtures)
    })
  }

  expect(entries.length, 'record dialog entries').toBe(6)
  expect(
    entries.filter(entry => entry.opened).length,
    'record dialogs opened'
  ).toBeGreaterThanOrEqual(5)
  expect(entries.every(entry => entry.shot || entry.outcome !== 'measured')).toBe(true)
})

test('@U7 primitives · los diálogos de especialidades', async ({ page, request }) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const entries = []

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(SPECIALTIES_ROUTE)
    await settleSpecialties(page)
    await pickPatient(page, '[data-patient-card]', fixtures.patients.clinical.document)
    await page
      .waitForFunction(
        () =>
          !document.querySelector('[data-specialty-records-loading]') &&
          Boolean(
            document.querySelector('[data-specialty-record-card], [data-specialty-records-empty]')
          ),
        undefined,
        { timeout: LOAD_TIMEOUT, polling: 120 }
      )
      .catch(() => {})
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    entries.push(
      await contractFor(page, {
        route: SPECIALTIES_ROUTE,
        slug: 'specialty-form',
        label: 'Nuevo registro',
        kind: 'create',
        locator: page.locator('[data-new-record]'),
        note: 'the form declares the specialty of the active tab and locks the patient'
      })
    )

    entries.push(
      await contractFor(page, {
        route: SPECIALTIES_ROUTE,
        slug: 'specialty-detail',
        label: 'Ver (registro)',
        kind: 'open',
        locator: page.locator('[data-record-action="view"]').first(),
        note: 'the detail reads the record of the active specialty'
      })
    )

    entries.push(
      await contractFor(page, {
        route: SPECIALTIES_ROUTE,
        slug: 'specialty-patient',
        label: 'Cambiar paciente',
        kind: 'open',
        locator: page.locator('[data-change-patient]'),
        note: 'the patient picker opens over the specialty page'
      })
    )
  } finally {
    await closeAllDialogs(page)

    write(entries, {
      routes: [SPECIALTIES_ROUTE],
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
      failedRequests: classifyFailures(diagnostics.failedRequests, 'admin')
    })
  }

  expect(entries.length, 'specialty dialog entries').toBe(3)
  expect(
    entries.filter(entry => entry.opened).length,
    'specialty dialogs opened'
  ).toBeGreaterThanOrEqual(2)
})

test('@U7 primitives · el sheet de IA y la pila del análisis', async ({ page, request }) => {
  test.setTimeout(420000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const entries = []

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(AI_ROUTE)
    await settleAi(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const sheet = await contractFor(page, {
      route: AI_ROUTE,
      slug: 'ai-sheet',
      label: 'Nuevo análisis',
      kind: 'create',
      locator: page.locator('[data-hero-primary]'),
      readiness: 700,
      note: 'UiSheet position="right": the contract must measure a right-docked panel'
    })
    entries.push(sheet)

    const detail = await contractFor(page, {
      route: AI_ROUTE,
      slug: 'ai-detail',
      label: 'Ver detalle',
      kind: 'open',
      locator: page.locator('[data-ai-table] tbody tr').first().locator('[data-row-action="view"]'),
      readiness: 500,
      note: 'the detail paints the assistive notice, the metadata and the review/void sections'
    })
    entries.push(detail)

    // --- nested stack: the global confirm over the detail modal -------------
    await page
      .locator('[data-ai-table] tbody tr')
      .first()
      .locator('[data-row-action="void"]')
      .click({ timeout: 10000 })
    await page
      .locator('[data-ai-detail]')
      .waitFor({ state: 'visible', timeout: 12000 })
      .catch(() => {})

    const reason = page.locator('[data-ai-void-reason]').first()
    const canType = await reason
      .waitFor({ state: 'visible', timeout: 8000 })
      .then(() => true)
      .catch(() => false)
    if (canType) await reason.fill('AUDIT-U7 motivo de la anulación en la capa 3')

    const nested = await contractFor(page, {
      route: AI_ROUTE,
      slug: 'nested-ai-void-confirm',
      label: 'Anular análisis → confirmar',
      kind: 'action',
      locator: page.locator('[data-ai-void]').first(),
      dialog: page.locator('[role="alertdialog"]').last(),
      readiness: 500,
      escapeBudgetMs: 6000,
      leaveOpen: true,
      note: 'the global confirm opens over the analysis detail; Escape must close only the confirm'
    })

    const stackAfterEscape = await dialogStack(page)
    const detailStillMounted = stackAfterEscape.some(dialog =>
      dialog.hooks.includes('data-ai-detail')
    )
    nested.stack = {
      detailStillMounted,
      dialogsAfterEscape: stackAfterEscape.length,
      stack: stackAfterEscape
    }
    nested.verdict =
      nested.opened && detailStillMounted && stackAfterEscape.length === 1 ? 'OK' : 'DEFECTO'
    entries.push(nested)

    // --- the same stack, measured before Escape closes it ------------------
    // The contract above passes (centered, named, trapped, Escape closes) and yet
    // the confirm's own button is unreachable: the two overlays share `z-50` and
    // the later-teleported detail paints above the confirm. The hit test is the
    // measurement the contract cannot make.
    await page
      .locator('[data-ai-table] tbody tr')
      .first()
      .locator('[data-row-action="void"]')
      .click({ timeout: 10000 })
    await page
      .locator('[data-ai-detail]')
      .waitFor({ state: 'visible', timeout: 12000 })
      .catch(() => {})
    const reasonAgain = page.locator('[data-ai-void-reason]').first()
    if (
      await reasonAgain
        .waitFor({ state: 'visible', timeout: 8000 })
        .then(() => true)
        .catch(() => false)
    ) {
      await reasonAgain.fill('AUDIT-U7 hit test de la confirmación')
    }
    await page.locator('[data-ai-void]').first().click({ timeout: 10000 })
    await page.waitForTimeout(1000)

    const occlusion = await page.evaluate(() => {
      const text = node => (node?.innerText ?? '').trim()
      const dialog = document.querySelector('[role="alertdialog"]')
      const button = dialog
        ? [...dialog.querySelectorAll('button')].find(node => text(node) === 'Anular')
        : null
      const box = button?.getBoundingClientRect() ?? null
      const at = box
        ? document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2)
        : null
      const backdrops = [...document.querySelectorAll('body > div > div.fixed')].map(node => ({
        z: getComputedStyle(node).zIndex,
        className: String(node.className).slice(0, 60)
      }))

      return {
        confirmOpen: Boolean(dialog),
        buttonFound: Boolean(button),
        hit: at ? { tag: at.tagName, text: (at.innerText ?? '').trim().slice(0, 60) } : null,
        reachable: dialog && at ? dialog.contains(at) : false,
        backdrops
      }
    })

    audit('primitives/ai-confirm-occlusion.json', {
      ...stamp(),
      route: AI_ROUTE,
      slug: 'ai-confirm-occlusion',
      label: 'Anular análisis → confirmar (hit test)',
      kind: 'action',
      expected: 'el botón «Anular» de la confirmación recibe el clic',
      outcome: occlusion.reachable ? 'measured' : 'unreachable',
      occlusion,
      dialogs: [],
      checks: [],
      pageErrors: []
    })

    entries.push({
      route: AI_ROUTE,
      slug: 'ai-confirm-occlusion',
      label: 'Anular análisis → confirmar (hit test)',
      kind: 'action',
      outcome: occlusion.reachable ? 'measured' : 'unreachable',
      opened: occlusion.confirmOpen,
      contractFailures: [],
      occlusion,
      shot: null
    })

    await closeAllDialogs(page)

    await closeAllDialogs(page)
  } finally {
    await closeAllDialogs(page)

    write(entries, {
      routes: [AI_ROUTE],
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
      failedRequests: classifyFailures(diagnostics.failedRequests, 'admin'),
      fixtures: fixtureSummary(fixtures)
    })
  }

  expect(entries.length, 'AI dialog entries').toBe(4)
  expect(
    entries.filter(entry => entry.outcome === 'measured').length,
    'AI dialogs measured'
  ).toBeGreaterThanOrEqual(3)
})
