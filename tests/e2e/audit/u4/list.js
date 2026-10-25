// Plan #13 (issue #367, U4) — the patients directory: `/patients`.
//
// Imported by `surface.spec.js`, so `--grep "@U4 list"` selects these rows and
// `--grep @U4` selects the whole session. Render rows land in
// `render/patients-<role>.json` with the shape `ui-pass.mjs` aggregates; the
// walkthrough rows keep their named files at the top of the session directory.
//
// This is measurement code: a product defect is recorded in the JSON with a
// verdict, never as a suite failure. Asserted here only that each probe ran to
// completion, that both captures exist and that no page error was raised.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
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

import {
  MARKER,
  U4_EVIDENCE_DIR,
  ensureFixtures,
  fixtureSummary,
  patientBySlot,
  registerCreatedPatient
} from './fixtures.js'

import {
  ACTION_TIMEOUT,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  PATIENTS_ROUTE,
  THRESHOLD,
  call,
  chooseSelect,
  clickResetFilters,
  delayRoute,
  markExpected,
  paramsOf,
  readPatientsSnapshot,
  requestTracker,
  requestsTo,
  rowFor,
  settleList,
  waitForCall,
  waitForToast
} from './helpers.js'

const ROLES = Object.freeze(['administrador', 'recepcionista', 'odontologo', 'finanzas'])
const EXPECTATIONS = Object.freeze({
  administrador: 'admin',
  recepcionista: 'owner',
  odontologo: 'owner',
  finanzas: 'owner'
})

const SEARCH_INPUT = 'input[placeholder^="Buscar por nombre"]'
const PATIENT_FORM = '[data-patient-form]'
const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U4_EVIDENCE_DIR })
const shot = (page, filePath) => capture(page, filePath, { dir: U4_EVIDENCE_DIR })

const listResponse = (page, predicate = () => true, timeout = ACTION_TIMEOUT) =>
  waitForCall(page, 'GET', '/api/patients', predicate, timeout)

const openList = async (page, session) => {
  await page.setViewportSize(DESKTOP)
  await primeSession(page, session)

  const payload = listResponse(page, params => params.get('page') === '1')
  const response = await page.goto(PATIENTS_ROUTE)
  const settled = await payload

  await settleList(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

  return { response, settled, snapshot: await readPatientsSnapshot(page) }
}

const searchFor = async (page, value) => {
  const input = page.locator(SEARCH_INPUT)
  const response = listResponse(page, params => params.get('search') === value)
  await input.fill(value)
  const settled = await response
  await settleList(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

  return { settled, params: settled ? paramsOf(settled) : null, status: settled?.status() ?? null }
}

const seededDocument = async (request, session) => {
  const response = await call(
    request,
    session,
    'GET',
    '/api/patients?per_page=100&sort=created_at&direction=asc'
  )
  const rows = Array.isArray(response.body?.data) ? response.body.data : []
  const seeded = rows.find(
    row => typeof row?.document_number === 'string' && !row.document_number.includes(MARKER)
  )

  return seeded?.document_number ?? null
}

const formValidity = page =>
  page.evaluate(selector => {
    const form = document.querySelector(selector)
    if (!form) return null

    const invalid = Array.from(form.querySelectorAll('input, select, textarea')).find(
      input => typeof input.checkValidity === 'function' && !input.checkValidity()
    )
    const field = invalid?.closest('[data-patient-field]')?.getAttribute('data-patient-field')

    return {
      checkValidity: form.checkValidity(),
      field,
      message: invalid?.validationMessage ?? null,
      label: invalid?.labels?.[0]?.innerText?.trim() ?? null
    }
  }, PATIENT_FORM)

const fillPatientField = async (page, field, value) => {
  const wrapper = page.locator(`[data-patient-field="${field}"]`).first()

  if (field === 'gender' || field === 'is_active' || field === 'branch_id') {
    const trigger = wrapper.locator('button[aria-haspopup="true"]').first()
    await trigger.click()
    const listbox = page.locator('[role="listbox"]:visible').first()
    await listbox.locator('button').filter({ hasText: value }).first().click()
    return
  }

  const input = wrapper.locator('input, textarea').first()
  await input.fill(value)
}

const submitPatientForm = page =>
  page.locator(`${PATIENT_FORM} button[type="submit"]`).first().click()

const dialogOpen = page =>
  page
    .locator('[role="dialog"]:visible')
    .last()
    .isVisible()
    .catch(() => false)

// The status filter's current value is not visible in its trigger when the
// model is the empty string; the option list still marks its selection.
const statusSelect = async page => {
  const trigger = page
    .locator('section[data-patients-block="directory"] button[aria-haspopup="true"]')
    .nth(1)
  const label = (await trigger.innerText().catch(() => '')).trim()
  await trigger.click({ timeout: 5000 }).catch(() => {})

  const listbox = page.locator('[role="listbox"]:visible').first()
  const opened = await listbox
    .waitFor({ state: 'visible', timeout: 3000 })
    .then(() => true)
    .catch(() => false)

  if (!opened) return { label, options: [] }

  const options = await listbox.locator('button').evaluateAll(nodes =>
    nodes.map(node => ({
      text: (node.innerText ?? '').trim(),
      selected: node.getAttribute('aria-selected')
    }))
  )

  await page.keyboard.press('Escape').catch(() => {})
  await listbox.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {})

  return { label, options }
}

const closeDialog = async page => {
  const dialog = page.locator('[role="dialog"]:visible').last()

  if (!(await dialog.count().catch(() => 0))) return
  if (!(await dialog.isVisible().catch(() => false))) return

  await page.keyboard.press('Escape').catch(() => {})
  await dialog.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
}

// --- layer 2: render per role ---------------------------------------------

for (const role of ROLES) {
  test(`@U4 list · render for ${role}`, async ({ page, request }) => {
    test.setTimeout(120000)

    const session = await loginAs(request, role)
    const fixtures = await ensureFixtures(request)
    const diagnostics = collectDiagnostics(page)
    const expectation = EXPECTATIONS[role]
    const evidence = {
      route: PATIENTS_ROUTE,
      slug: 'patients',
      role,
      expectation,
      href: PATIENTS_ROUTE,
      url: null,
      httpStatus: null,
      rendered: false,
      contentLength: 0,
      notFound: false,
      horizontalOverflow: null,
      viewports: { desktop: { ...DESKTOP } },
      shots: [],
      consoleErrors: diagnostics.consoleErrors,
      pageErrors: diagnostics.pageErrors,
      failedRequests: { expected: [], unexpected: [] },
      tabs: [],
      firstPaint: null,
      loadingAffordance: null,
      snapshot: null,
      mobileSnapshot: null,
      fixtures: fixtureSummary(fixtures)
    }

    let delay = null

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)
      delay = await delayRoute(page, /\/api\/patients(\?|$)/, 1200)

      const firstPayload = listResponse(page, params => params.get('page') === '1', LOAD_TIMEOUT)
      const response = await page.goto(PATIENTS_ROUTE)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator('[data-patients-hero]')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await page
        .locator('.loading-spinner')
        .waitFor({ state: 'visible', timeout: 8000 })
        .catch(() => {})

      evidence.firstPaint = await readPatientsSnapshot(page)
      evidence.loadingAffordance = {
        observed: evidence.firstPaint.loading ? 'LoadingSpinner' : 'not-captured',
        text: evidence.firstPaint.loadingText || null,
        skeleton: evidence.firstPaint.skeletons > 0,
        skeletonElements: evidence.firstPaint.skeletons,
        source: 'PatientsPage `<LoadingSpinner v-if="loading" text="Cargando pacientes...">`'
      }

      await firstPayload
      await delay.stop()
      delay = null
      await settleList(page)
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD
      evidence.snapshot = await readPatientsSnapshot(page)
      evidence.notFound = evidence.snapshot.h1.texts.some(text =>
        /página no encontrada/i.test(text)
      )
      evidence.shots.push(await shot(page, `shots/u4-patients-${role}-desktop.png`))

      await page.setViewportSize(MOBILE)
      const mobilePayload = listResponse(page, params => params.get('page') === '1', LOAD_TIMEOUT)
      await page.reload()
      await mobilePayload
      await settleList(page)
      await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }
      evidence.mobileSnapshot = await readPatientsSnapshot(page)
      evidence.shots.push(await shot(page, `shots/u4-patients-${role}-mobile.png`))
    } finally {
      await delay?.stop().catch(() => {})
      evidence.pageErrors = diagnostics.pageErrors
      evidence.consoleErrors = diagnostics.consoleErrors
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, expectation)
      audit(`render/patients-${role}.json`, evidence)
    }

    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
    expect(evidence.snapshot?.hero, `hero for ${role}`).toBe(true)
  })
}

// --- search ----------------------------------------------------------------

test('@U4 list · búsqueda', async ({ page, request }) => {
  test.setTimeout(90000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    marker: MARKER,
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    const opened = await openList(page, session)
    evidence.steps.initial = {
      rows: opened.snapshot.rows.length,
      kpiFiltered: opened.snapshot.stats['filtered-patients'] ?? null
    }

    const search = await searchFor(page, MARKER)
    const afterSearch = await readPatientsSnapshot(page)
    evidence.steps.search = {
      request: search.params,
      status: search.status,
      rows: afterSearch.rows.length,
      kpiFiltered: afterSearch.stats['filtered-patients'] ?? null,
      allMatch: afterSearch.rows.every(row => row.text.includes(MARKER)),
      names: afterSearch.rows.map(row => row.text.slice(0, 90))
    }

    const resetRequest = listResponse(page, params => !params.get('search'))
    const reset = await clickResetFilters(page)
    const resetSettled = await resetRequest
    await settleList(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
    const afterReset = await readPatientsSnapshot(page)

    evidence.steps.reset = {
      control: reset,
      request: resetSettled ? paramsOf(resetSettled) : null,
      status: resetSettled?.status() ?? null,
      input: afterReset.search,
      rows: afterReset.rows.length,
      kpiFiltered: afterReset.stats['filtered-patients'] ?? null
    }

    evidence.shots.push(await shot(page, 'shots/u4-patients-search.png'))

    const searchedWithParam = search.params?.search === MARKER
    const allRowsMatch = evidence.steps.search.allMatch === true
    const resetRestored = afterReset.search === '' && afterReset.rows.length > 1

    evidence.verdict = searchedWithParam && allRowsMatch && resetRestored ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-search.json', evidence)
  }

  expect(evidence.steps.search?.request ?? null, 'search request observed').not.toBeNull()
  expect(evidence.steps.reset?.status, 'reset request observed').toBe(200)
  expect(evidence.pageErrors, 'page errors during the search probe').toEqual([])
})

// --- filters: status, sort, per-page, branch -------------------------------

test('@U4 list · filtros', async ({ page, request }) => {
  test.setTimeout(150000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  const record = (key, value) => {
    evidence.steps[key] = value
  }

  try {
    const opened = await openList(page, session)
    record('initial', {
      selects: opened.snapshot.selects,
      rows: opened.snapshot.rows.length,
      branchSelectPresent: opened.snapshot.selects.some(label => /sucursal|Todas/i.test(label)),
      statusSelect: await statusSelect(page)
    })

    const branches = await call(request, session, 'GET', '/api/branches/active')
    const branchRows = Array.isArray(branches.body?.data) ? branches.body.data : []
    record('branches', {
      status: branches.status,
      count: branchRows.length,
      names: branchRows.map(branch => branch.name)
    })

    // Status: Activos
    const activeRequest = listResponse(page, params => params.get('active') === 'true')
    const activeChoice = await chooseSelect(page, 'Activos')
    const activeSettled = await activeRequest
    await settleList(page)
    const activeSnapshot = await readPatientsSnapshot(page)
    record('statusActive', {
      choice: activeChoice,
      request: activeSettled ? paramsOf(activeSettled) : null,
      status: activeSettled?.status() ?? null,
      rows: activeSnapshot.rows.length,
      inactiveRows: activeSnapshot.rows.filter(row => /\bInactivo\b/.test(row.text)).length,
      empty: activeSnapshot.empty
    })

    // Status: Inactivos (the audit DB seeds none)
    const inactiveRequest = listResponse(page, params => params.get('active') === 'false')
    const inactiveChoice = await chooseSelect(page, 'Inactivos')
    const inactiveSettled = await inactiveRequest
    await settleList(page)
    const inactiveSnapshot = await readPatientsSnapshot(page)
    record('statusInactive', {
      choice: inactiveChoice,
      request: inactiveSettled ? paramsOf(inactiveSettled) : null,
      status: inactiveSettled?.status() ?? null,
      rows: inactiveSnapshot.rows.length,
      empty: inactiveSnapshot.empty,
      emptyText: inactiveSnapshot.emptyText
    })
    evidence.shots.push(await shot(page, 'shots/u4-patients-filter-inactive.png'))

    // Back to all statuses before the sort/per-page probes.
    const allRequest = listResponse(page, params => !params.get('active'))
    await chooseSelect(page, 'Todos')
    await allRequest
    await settleList(page)
    record('allStatuses', { statusSelect: await statusSelect(page) })

    // Sort: last_name desc vs asc
    const descRequest = listResponse(
      page,
      params => params.get('sort') === 'last_name' && params.get('direction') === 'desc'
    )
    await chooseSelect(page, 'Apellidos Z-A')
    const descSettled = await descRequest
    await settleList(page)
    const descSnapshot = await readPatientsSnapshot(page)
    record('sortDesc', {
      request: descSettled ? paramsOf(descSettled) : null,
      status: descSettled?.status() ?? null,
      first: descSnapshot.rows[0]?.text?.slice(0, 90) ?? null
    })

    const ascRequest = listResponse(
      page,
      params => params.get('sort') === 'last_name' && params.get('direction') === 'asc'
    )
    await chooseSelect(page, 'Apellidos A-Z')
    const ascSettled = await ascRequest
    await settleList(page)
    const ascSnapshot = await readPatientsSnapshot(page)
    record('sortAsc', {
      request: ascSettled ? paramsOf(ascSettled) : null,
      status: ascSettled?.status() ?? null,
      first: ascSnapshot.rows[0]?.text?.slice(0, 90) ?? null,
      orderDiffers: ascSnapshot.rows[0]?.text !== descSnapshot.rows[0]?.text
    })

    // Per page: 30
    const perPageRequest = listResponse(page, params => params.get('per_page') === '30')
    await chooseSelect(page, '30 por página')
    const perPageSettled = await perPageRequest
    await settleList(page)
    const perPageSnapshot = await readPatientsSnapshot(page)
    record('perPage', {
      request: perPageSettled ? paramsOf(perPageSettled) : null,
      status: perPageSettled?.status() ?? null,
      rows: perPageSnapshot.rows.length,
      pagination: perPageSnapshot.pagination
    })

    // Branch: the filter exists only when /api/branches/active has rows.
    if (branchRows.length > 0) {
      const branch = branchRows[0]
      const branchRequest = listResponse(
        page,
        params => params.get('branch_id') === String(branch.id)
      )
      const branchChoice = await chooseSelect(page, branch.name)
      const branchSettled = await branchRequest
      await settleList(page)
      const branchSnapshot = await readPatientsSnapshot(page)
      record('branch', {
        choice: branchChoice,
        request: branchSettled ? paramsOf(branchSettled) : null,
        status: branchSettled?.status() ?? null,
        rows: branchSnapshot.rows.length
      })
    } else {
      record('branch', { ok: false, reason: 'no active branch to filter by' })
    }

    const resetRequest = listResponse(page, params => params.get('page') === '1')
    await clickResetFilters(page)
    await resetRequest
    await settleList(page)
    const resetSnapshot = await readPatientsSnapshot(page)
    record('reset', {
      selects: resetSnapshot.selects,
      rows: resetSnapshot.rows.length,
      pagination: resetSnapshot.pagination
    })
    evidence.shots.push(await shot(page, 'shots/u4-patients-filters.png'))

    const activeOk =
      evidence.steps.statusActive.rows > 0 && evidence.steps.statusActive.inactiveRows === 0
    const inactiveOk = evidence.steps.statusInactive.empty === true
    const sortOk = evidence.steps.sortAsc.orderDiffers === true
    const perPageOk = evidence.steps.perPage.rows === 30
    const branchOk = evidence.steps.branch?.request?.branch_id != null

    evidence.verdict = activeOk && inactiveOk && sortOk && perPageOk && branchOk ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-filters.json', evidence)
  }

  expect(Object.keys(evidence.steps).length, 'filter steps attempted').toBeGreaterThanOrEqual(7)
  expect(evidence.pageErrors, 'page errors during the filters probe').toEqual([])
})

// --- pagination ------------------------------------------------------------

test('@U4 list · paginación', async ({ page, request }) => {
  test.setTimeout(90000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    const opened = await openList(page, session)
    const pageOne = opened.snapshot
    evidence.steps.page1 = {
      first: pageOne.rows[0]?.text?.slice(0, 90) ?? null,
      rows: pageOne.rows.length,
      pagination: pageOne.pagination
    }

    const pageTwoRequest = listResponse(page, params => params.get('page') === '2')
    const pageTwoButton = page
      .locator('button.pagination-page-button')
      .filter({ hasText: /^2$/ })
      .first()

    if (!(await pageTwoButton.isVisible().catch(() => false))) {
      evidence.steps.page2 = { ok: false, reason: 'page 2 control not visible' }
    } else {
      await pageTwoButton.click({ timeout: 5000 })
      const pageTwoSettled = await pageTwoRequest
      await settleList(page)
      const pageTwo = await readPatientsSnapshot(page)
      evidence.steps.page2 = {
        request: pageTwoSettled ? paramsOf(pageTwoSettled) : null,
        status: pageTwoSettled?.status() ?? null,
        first: pageTwo.rows[0]?.text?.slice(0, 90) ?? null,
        rows: pageTwo.rows.length,
        pagination: pageTwo.pagination,
        firstDiffers: pageTwo.rows[0]?.text !== pageOne.rows[0]?.text
      }
      evidence.shots.push(await shot(page, 'shots/u4-patients-page-2.png'))
    }

    const requestedSecondPage = evidence.steps.page2?.request?.page === '2'
    evidence.verdict =
      requestedSecondPage && evidence.steps.page2.firstDiffers === true ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-pagination.json', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the pagination probe').toEqual([])
})

// --- create ----------------------------------------------------------------

test('@U4 list · alta', async ({ page, request }) => {
  test.setTimeout(150000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const tracker = requestTracker(page)
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  const record = (key, value) => {
    evidence.steps[key] = value
  }

  try {
    const opened = await openList(page, session)
    record('before', {
      total: opened.snapshot.stats['total-patients'] ?? null,
      filtered: opened.snapshot.stats['filtered-patients'] ?? null,
      rows: opened.snapshot.rows.length
    })

    await page.locator('[data-new-patient-button]').click({ timeout: 5000 })
    const form = page.locator(PATIENT_FORM)
    await form.waitFor({ state: 'visible', timeout: 8000 })
    record('opened', { dialog: await page.locator('[role="dialog"]:visible').last().isVisible() })

    // 1) Empty submit: the form is `novalidate`? No: the form has native
    // `required` inputs and no `novalidate`, so the browser blocks the submit.
    await submitPatientForm(page)
    await page.evaluate(
      () =>
        new Promise(resolve =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve(null)))
        )
    )
    record('emptySubmit', {
      validity: await formValidity(page),
      postAttempts: requestsTo(tracker, 'POST', '/api/patients').length,
      dialogStillOpen: await dialogOpen(page)
    })

    // 2) Duplicate document of a seeded patient.
    const duplicateDocument = await seededDocument(request, session)
    record('seededDocument', { value: duplicateDocument })

    await fillPatientField(page, 'first_name', `${MARKER} Duplicado`)
    await fillPatientField(page, 'last_name', `${MARKER} Duplicado ${fixtures.token}`)
    await fillPatientField(page, 'birth_date', '1990-01-01')

    if (duplicateDocument) {
      await fillPatientField(page, 'document_number', duplicateDocument)
      const duplicateResponse = waitForCall(page, 'POST', '/api/patients')
      await submitPatientForm(page)
      const duplicateSettled = await duplicateResponse
      const duplicateBody = duplicateSettled
        ? await duplicateSettled.json().catch(() => null)
        : null
      const toast = await waitForToast(page, 'document', 6000)

      record('duplicateDocument', {
        status: duplicateSettled?.status() ?? null,
        errorFields: duplicateBody?.errors ? Object.keys(duplicateBody.errors) : [],
        message: duplicateBody?.message ?? null,
        toast,
        dialogStillOpen: await dialogOpen(page),
        inlineFieldError: Boolean(
          await page.locator('[data-patient-field="document_number"] [aria-invalid="true"]').count()
        )
      })
      evidence.shots.push(await shot(page, 'shots/u4-patients-create-duplicate.png'))
    } else {
      record('duplicateDocument', { ok: false, reason: 'no seeded document number available' })
    }

    // 3) Future birth date.
    await fillPatientField(page, 'document_number', `U4-${fixtures.token}-UI`)
    await fillPatientField(page, 'birth_date', '2099-01-01')
    const futureResponse = waitForCall(page, 'POST', '/api/patients')
    await submitPatientForm(page)
    const futureSettled = await futureResponse
    const futureBody = futureSettled ? await futureSettled.json().catch(() => null) : null

    record('futureBirthDate', {
      status: futureSettled?.status() ?? null,
      errorFields: futureBody?.errors ? Object.keys(futureBody.errors) : [],
      message: futureBody?.message ?? null,
      fieldMessage: futureBody?.errors?.birth_date?.[0] ?? null,
      toast: await waitForToast(page, 'fecha', 6000),
      dialogStillOpen: await dialogOpen(page)
    })

    // 4) Happy path.
    await fillPatientField(page, 'first_name', MARKER)
    await fillPatientField(page, 'birth_date', '1990-05-05')
    const happyLastName = `${MARKER} Creado ${fixtures.token}`
    await fillPatientField(page, 'last_name', happyLastName)
    const happyResponse = waitForCall(page, 'POST', '/api/patients')
    await submitPatientForm(page)
    const happySettled = await happyResponse
    const happyBody = happySettled ? await happySettled.json().catch(() => null) : null
    const createdId = happyBody?.data?.id ?? null

    const toast = await waitForToast(page, 'Paciente registrado', 8000)
    await settleList(page)
    const after = await readPatientsSnapshot(page)
    const createdRow = page.locator('[data-patient-row]:visible').filter({ hasText: happyLastName })

    record('happyPath', {
      status: happySettled?.status() ?? null,
      id: createdId,
      toast,
      dialogClosed: !(await dialogOpen(page)),
      rowInserted: (await createdRow.count()) > 0,
      rows: after.rows.length,
      total: after.stats['total-patients'] ?? null,
      filtered: after.stats['filtered-patients'] ?? null
    })

    if (createdId) {
      evidence.created = registerCreatedPatient({ id: createdId, label: happyLastName })
    }

    evidence.shots.push(await shot(page, 'shots/u4-patients-create.png'))

    const duplicateStatus = evidence.steps.duplicateDocument?.status ?? null
    const futureStatus = evidence.steps.futureBirthDate?.status ?? null
    const createdStatus = evidence.steps.happyPath?.status ?? null

    evidence.verdict =
      evidence.steps.emptySubmit.postAttempts === 0 &&
      duplicateStatus === 422 &&
      futureStatus === 422 &&
      createdStatus === 201 &&
      evidence.steps.happyPath.rowInserted === true
        ? 'OK'
        : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    await closeDialog(page)
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 422 && failure.url?.includes('/api/patients'),
      'probe: the duplicate-document and future-date bodies are expected to be rejected'
    )
    audit('list-create.json', evidence)
  }

  expect(evidence.steps.emptySubmit, 'empty submit measured').toBeTruthy()
  expect(evidence.steps.happyPath, 'happy path measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the create probe').toEqual([])
})

// --- edit ------------------------------------------------------------------

test('@U4 list · edición', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const target = patientBySlot(fixtures, 'p2')
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!target?.id) {
      evidence.reason = 'P2 (editable) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    await openList(page, session)
    await searchFor(page, MARKER)

    const row = rowFor(page, target.lastName)
    const visible = await row.isVisible().catch(() => false)

    evidence.steps.row = {
      visible,
      last_name: target.lastName,
      text: visible ? (await row.innerText()).replace(/\s+/g, ' ').slice(0, 160) : null
    }

    if (!visible) {
      evidence.reason = 'the editable fixture is not on page 1 of the filtered list'
      test.skip(true, evidence.reason)
    }

    await row.getByRole('button', { name: 'Editar', exact: true }).click({ timeout: 5000 })
    const form = page.locator(PATIENT_FORM)
    await form.waitFor({ state: 'visible', timeout: 8000 })

    const prefilled = await page
      .locator('[data-patient-field="last_name"] input')
      .first()
      .inputValue()
    evidence.steps.opened = { prefilled, matchesFixture: prefilled === target.lastName }

    const updatedLastName = `${target.lastName} Editado`
    await fillPatientField(page, 'last_name', updatedLastName)

    const putRequest = waitForCall(
      page,
      'PUT',
      `/api/patients/${target.id}`,
      () => true,
      ACTION_TIMEOUT
    )
    const reload = listResponse(page, params => params.get('search') === MARKER)
    await submitPatientForm(page)
    const putSettled = await putRequest
    const putBody = putSettled ? await putSettled.json().catch(() => null) : null
    await reload
    await settleList(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const toast = await waitForToast(page, 'Paciente actualizado', 8000)
    const updatedRow = page
      .locator('[data-patient-row]:visible')
      .filter({ hasText: updatedLastName })

    evidence.steps.saved = {
      method: 'PUT',
      status: putSettled?.status() ?? null,
      id: putBody?.data?.id ?? null,
      toast,
      dialogClosed: !(await dialogOpen(page)),
      rowUpdated: (await updatedRow.count()) > 0
    }
    evidence.verdict =
      evidence.steps.saved.status === 200 && evidence.steps.saved.rowUpdated === true
        ? 'OK'
        : 'DEFECTO'
    evidence.shots.push(await shot(page, 'shots/u4-patients-edit.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    await closeDialog(page)
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-edit.json', evidence)
  }

  expect(evidence.steps.opened, 'edit form opened').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the edit probe').toEqual([])
})

// --- delete through the confirmation --------------------------------------

test('@U4 list · borrado', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const tracker = requestTracker(page)
  const target = patientBySlot(fixtures, 'p3')
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    if (!target?.id) {
      evidence.reason = 'P3 (deletable) fixture unavailable'
      test.skip(true, evidence.reason)
    }

    await openList(page, session)
    await searchFor(page, MARKER)

    const row = rowFor(page, target.lastName)
    if (!(await row.isVisible().catch(() => false))) {
      evidence.reason = 'the deletable fixture is not on page 1 of the filtered list'
      test.skip(true, evidence.reason)
    }

    await row.getByRole('button', { name: 'Eliminar', exact: true }).click({ timeout: 5000 })
    const dialog = page.locator('[role="alertdialog"]:visible').last()
    await dialog.waitFor({ state: 'visible', timeout: 8000 })

    const descriptor = await dialog.evaluate(node => {
      const labelledby = node.getAttribute('aria-labelledby')
      const labelTarget = labelledby ? document.getElementById(labelledby) : null
      return {
        role: node.getAttribute('role'),
        title: (node.querySelector('h2, h3')?.textContent ?? '').trim(),
        name: (labelTarget?.textContent ?? '').trim(),
        message: (node.querySelector('.confirm-message')?.textContent ?? '').trim(),
        buttons: Array.from(node.querySelectorAll('button')).map(button => ({
          text: (button.innerText ?? '').trim(),
          ariaLabel: button.getAttribute('aria-label')
        }))
      }
    })

    evidence.steps.confirm = {
      descriptor,
      deletesBeforeCancel: requestsTo(tracker, 'DELETE', `/api/patients/${target.id}`).length
    }
    evidence.shots.push(await shot(page, 'shots/u4-patients-delete-confirm.png'))

    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click({ timeout: 5000 })
    await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})

    const afterCancel = await readPatientsSnapshot(page)
    const cancelledRow = page
      .locator('[data-patient-row]:visible')
      .filter({ hasText: target.lastName })

    evidence.steps.cancelled = {
      dialogClosed: !(await dialog.isVisible().catch(() => false)),
      rowStillPresent: (await cancelledRow.count()) > 0,
      rows: afterCancel.rows.length,
      deletes: requestsTo(tracker, 'DELETE', `/api/patients/${target.id}`).length
    }

    if (!(await cancelledRow.count())) {
      evidence.reason = 'the row disappeared after cancelling the confirmation'
      test.skip(true, evidence.reason)
    }

    const deleteRequest = waitForCall(page, 'DELETE', `/api/patients/${target.id}`)
    const reload = listResponse(page, params => params.get('search') === MARKER)
    await cancelledRow
      .getByRole('button', { name: 'Eliminar', exact: true })
      .click({ timeout: 5000 })
    await dialog.waitFor({ state: 'visible', timeout: 8000 })
    await dialog.getByRole('button', { name: 'Eliminar', exact: true }).click({ timeout: 5000 })
    const deleteSettled = await deleteRequest
    await reload
    await settleList(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const toast = await waitForToast(page, 'Paciente eliminado', 8000)
    const afterDelete = await readPatientsSnapshot(page)
    const goneRow = page.locator('[data-patient-row]:visible').filter({ hasText: target.lastName })

    evidence.steps.confirmed = {
      status: deleteSettled?.status() ?? null,
      toast,
      rowGone: (await goneRow.count()) === 0,
      rows: afterDelete.rows.length,
      kpiFiltered: afterDelete.stats['filtered-patients'] ?? null
    }
    evidence.verdict =
      evidence.steps.cancelled.deletes === 0 &&
      evidence.steps.confirmed.status === 200 &&
      evidence.steps.confirmed.rowGone === true
        ? 'OK'
        : 'DEFECTO'
    evidence.shots.push(await shot(page, 'shots/u4-patients-delete.png'))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-delete.json', evidence)
  }

  expect(evidence.steps.confirm, 'confirmation dialog measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the delete probe').toEqual([])
})

// --- list → file → back: do the filters survive? ---------------------------

test('@U4 list · vuelta', async ({ page, request }) => {
  test.setTimeout(120000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: PATIENTS_ROUTE,
    role: 'administrador',
    steps: {},
    verdict: 'NO PROBADO',
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] },
    fixtures: fixtureSummary(fixtures),
    shots: []
  }

  try {
    await openList(page, session)

    const activeRequest = listResponse(page, params => params.get('active') === 'true')
    await chooseSelect(page, 'Activos')
    await activeRequest
    await settleList(page)

    const search = await searchFor(page, MARKER)
    const filtered = await readPatientsSnapshot(page)

    evidence.steps.filtered = {
      request: search.params,
      rows: filtered.rows.length,
      search: filtered.search,
      selects: filtered.selects
    }

    const firstRow = page.locator('[data-patient-row]:visible').first()
    const firstText = (await firstRow.innerText()).replace(/\s+/g, ' ').slice(0, 120)
    await firstRow.getByRole('button', { name: 'Ver', exact: true }).click({ timeout: 5000 })
    await page.waitForURL(/\/patients\/\d+/, { timeout: 15000 }).catch(() => {})
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    evidence.steps.detail = {
      url: page.url().replace(BASE_URL, ''),
      hero: Boolean(await page.locator('[data-patient-detail-hero]').count()),
      sourceRow: firstText
    }

    const returnRequest = listResponse(page, params => params.get('page') === '1')
    await page.goBack({ waitUntil: 'commit' }).catch(() => {})
    const returned = await returnRequest
    await settleList(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    const after = await readPatientsSnapshot(page)

    evidence.steps.returned = {
      url: page.url().replace(BASE_URL, ''),
      request: returned ? paramsOf(returned) : null,
      status: returned?.status() ?? null,
      search: after.search,
      selects: after.selects,
      rows: after.rows.length,
      total: after.stats['total-patients'] ?? null
    }

    evidence.shots.push(await shot(page, 'shots/u4-patients-return.png'))

    const filtersSurvived =
      after.search === MARKER && (evidence.steps.returned.request?.active ?? null) === 'true'
    evidence.verdict = filtersSurvived ? 'OK' : 'DEFECTO'
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
  } finally {
    evidence.pageErrors = diagnostics.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    audit('list-return.json', evidence)
  }

  expect(evidence.steps.returned, 'return to the list measured').toBeTruthy()
  expect(evidence.pageErrors, 'page errors during the return probe').toEqual([])
})
