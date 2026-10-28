// Plan #13 (issue #367, U4) — layer 3: the dialogs the patients surfaces open.
//
// Imported by `surface.spec.js`; `--grep "@U4 primitives"` selects the row.
// Each trigger is opened through its own hook and measured with
// `assertDialogContract`; a contract violation stays in the evidence as a
// measured fact (that is the deliverable), never as a suite failure.
//
// Asserted here only that the probe ran to completion and that no page error
// was raised while opening the four dialogs.

import { test, expect } from 'playwright/test'

import {
  DIALOG_SELECTOR,
  assertDialogContract,
  capture,
  loginAs,
  primeSession,
  settleSurface,
  writeEvidence
} from '../harness.js'

import {
  MARKER,
  U4_EVIDENCE_DIR,
  ensureFixtures,
  fixtureSummary,
  patientBySlot
} from './fixtures.js'

import {
  DESKTOP,
  LOAD_TIMEOUT,
  PATIENTS_ROUTE,
  THRESHOLD,
  settleDetail,
  settleList,
  waitForCall
} from './helpers.js'

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U4_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U4_EVIDENCE_DIR })

const closeDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
}

const contractFor = async (page, evidence, trigger) => {
  const row = { trigger, dialog: null, checks: [], outcome: 'no-dialog' }

  try {
    const element = trigger.locate ? trigger.locate() : page.locator(trigger.selector).first()
    await element.click({ timeout: 8000 })
    const dialog = page.locator(DIALOG_SELECTOR).last()
    const opened = await dialog
      .waitFor({ state: 'visible', timeout: 8000 })
      .then(() => true)
      .catch(() => false)

    if (!opened) return row

    evidence.opened += 1
    evidence.shots.push(await shot(page, `shots/u4-primitive-${trigger.slug}.png`))
    const contract = await assertDialogContract(page, { dialog })

    row.outcome = 'measured'
    row.dialog = contract.dialog
    row.checks = contract.checks
  } catch (thrown) {
    row.outcome = 'probe-error'
    row.error = thrown.message
  } finally {
    await closeDialog(page)
  }

  return row
}

test('@U4 primitives · dialogs', async ({ page, request }) => {
  test.setTimeout(240000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const empty = patientBySlot(fixtures, 'p1')
  const editable = patientBySlot(fixtures, 'p2')
  const listEvidence = {
    route: PATIENTS_ROUTE,
    slug: 'patients',
    role: 'administrador',
    opened: 0,
    dialogs: [],
    pageErrors: [],
    consoleErrors: [],
    failedRequests: { expected: [], unexpected: [] },
    shots: [],
    fixtures: fixtureSummary(fixtures)
  }
  const detailEvidence = {
    route: '/patients/:id',
    slug: 'patients-id',
    role: 'administrador',
    patientId: empty?.id ?? null,
    opened: 0,
    dialogs: [],
    pageErrors: [],
    consoleErrors: [],
    failedRequests: { expected: [], unexpected: [] },
    shots: [],
    fixtures: fixtureSummary(fixtures)
  }

  page.on('console', message => {
    if (message.type() !== 'error') return
    listEvidence.consoleErrors.push(message.text())
    detailEvidence.consoleErrors.push(message.text())
  })
  page.on('pageerror', error => {
    listEvidence.pageErrors.push(error.message)
    detailEvidence.pageErrors.push(error.message)
  })

  try {
    if (!empty?.id || !editable?.id) {
      const reason = 'P1/P2 fixtures unavailable'
      listEvidence.reason = reason
      detailEvidence.reason = reason
      test.skip(true, reason)
    }

    // --- /patients: create and edit -------------------------------------
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    const listPayload = waitForCall(page, 'GET', '/api/patients')
    await page.goto(PATIENTS_ROUTE)
    await listPayload
    await settleList(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    listEvidence.dialogs.push(
      await contractFor(page, listEvidence, {
        slug: 'patients-create',
        label: 'Nuevo paciente',
        kind: 'create',
        selector: '[data-new-patient-button]'
      })
    )

    const searchInput = page.locator('input[placeholder^="Buscar por nombre"]')
    const searchPayload = waitForCall(
      page,
      'GET',
      '/api/patients',
      params => params.get('search') === MARKER
    )
    await searchInput.fill(MARKER)
    await searchPayload
    await settleList(page)

    const editRow = page
      .locator('[data-patient-row]:visible')
      .filter({ hasText: editable.lastName })
      .first()

    if (await editRow.isVisible().catch(() => false)) {
      listEvidence.dialogs.push(
        await contractFor(page, listEvidence, {
          slug: 'patients-edit',
          label: 'Editar (fila)',
          kind: 'edit',
          selector: `[data-patient-row]:has-text("${editable.lastName}") >> text=Editar`,
          locate: () => editRow.getByRole('button', { name: 'Editar', exact: true })
        })
      )
    } else {
      listEvidence.dialogs.push({
        trigger: { label: 'Editar (fila)', kind: 'edit', selector: null },
        dialog: null,
        checks: [],
        outcome: 'skipped',
        reason: 'the editable fixture is not on the filtered list'
      })
    }

    // --- /patients/:id: manual charge and laboratory case ----------------
    await primeSession(page, session)
    const detailPayload = waitForCall(page, 'GET', `/api/patients/${empty.id}`)
    await page.goto(`${PATIENTS_ROUTE}/${empty.id}`)
    await detailPayload
    await settleDetail(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    detailEvidence.dialogs.push(
      await contractFor(page, detailEvidence, {
        slug: 'patients-charge',
        label: 'Nuevo cargo',
        kind: 'create',
        selector: '[data-charge-patient-button]'
      })
    )

    const labRequest = waitForCall(page, 'GET', '/api/lab-cases')
    await page
      .locator('#tab-laboratorio')
      .click({ timeout: 5000 })
      .catch(() => {})
    await labRequest
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    detailEvidence.dialogs.push(
      await contractFor(page, detailEvidence, {
        slug: 'patients-lab-case',
        label: 'Nuevo caso',
        kind: 'create',
        selector: '[data-patient-lab-case-new]'
      })
    )
  } finally {
    await closeDialog(page)

    const all = [...listEvidence.dialogs, ...detailEvidence.dialogs]
    const failures = all.reduce(
      (total, row) => total + (row.checks ?? []).filter(check => check.ok === false).length,
      0
    )

    audit('primitives/patients.json', {
      ...listEvidence,
      verdict: listEvidence.opened === 2 ? 'OK' : 'DEFECTO'
    })
    audit('primitives/patients-id.json', {
      ...detailEvidence,
      verdict: detailEvidence.opened === 2 ? 'OK' : 'DEFECTO'
    })
    audit('primitives.json', {
      session: 'U4',
      route: PATIENTS_ROUTE,
      slug: 'patients',
      role: 'administrador',
      opened: listEvidence.opened + detailEvidence.opened,
      dialogs: all,
      contractFailures: failures,
      patients: { route: '/patients', opened: listEvidence.opened, dialogs: listEvidence.dialogs },
      patient: {
        route: '/patients/:id',
        opened: detailEvidence.opened,
        dialogs: detailEvidence.dialogs
      },
      pageErrors: [...new Set([...listEvidence.pageErrors, ...detailEvidence.pageErrors])],
      consoleErrors: [...new Set([...listEvidence.consoleErrors, ...detailEvidence.consoleErrors])],
      failedRequests: { expected: [], unexpected: [] },
      shots: [...listEvidence.shots, ...detailEvidence.shots]
    })
  }

  expect(listEvidence.opened + detailEvidence.opened, 'dialogs opened').toBe(4)
  expect([...listEvidence.pageErrors, ...detailEvidence.pageErrors], 'page errors').toEqual([])
})
