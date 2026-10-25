// Plan #13 (issue #364, U1) — shell surface: sidebar matrix per role, the
// catch-all inside the shell, the per-entry navigation walkthrough, the user
// menu, the mobile navigation sheet and the global confirm.
//
// Imported by `surface.spec.js`, so `--grep "@U1 shell"` selects this file's
// rows and `--grep @U1` (the issue command) still selects them. Every row writes
// its evidence under SESSION_EVIDENCE_DIR.
//
// The audit measures and reports; it does not fail the suite because the product
// has a defect. Asserted here: the sidebar matrix (the session's exit criterion),
// that each probe ran to completion, and zero pageerror. Not-OK verdicts travel
// in the evidence (`findings`, check rows, item verdicts).

import { test, expect } from 'playwright/test'

import {
  DIALOG_SELECTOR,
  SESSION_EVIDENCE_DIR,
  assertDialogContract,
  capture,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  hasHorizontalOverflow,
  loadManifest,
  loginAs,
  primeSession,
  settleSurface,
  writeEvidence
} from '../harness.js'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

const ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

const SHELL_ROUTE = '(shell)'
const SHELL_HREF = '/dashboard'
const SHELL_SLUG = 'shell'
const CATCH_ALL_ROUTE = '/:pathMatch(.*)*'
const CATCH_ALL_PROBE = '/audit-catch-all'

const SIDEBAR = '[data-app-chrome="sidebar"]'
const TOPBAR = '[data-app-chrome="topbar"]'
const SIDEBAR_LINKS = '#primary-sidebar nav a'
const WS_INDICATOR = '[aria-label^="Estado de WebSocket:"]'
const WS_STATES = Object.freeze(['connecting', 'connected', 'disconnected', 'unavailable'])
const USER_MENU_TRIGGER = '[aria-haspopup="menu"]'
const USER_MENU = '#user-menu-dropdown'
const MOBILE_MENU_TRIGGER = '[aria-label="Abrir menú"]'

const NAV_THRESHOLD = 80
const LOAD_TIMEOUT = 20000
const LOAD_SETTLE_TIMEOUT = 15000
const STEP_SETTLE_TIMEOUT = 8000
const STEP_LIMIT = 360000

const MENU_LABELS = Object.freeze(['Perfil', 'Configuración', 'Apariencia', 'Cerrar Sesión'])
const SHEET_ROLE = 'recepcionista'
const SHEET_TITLE = 'Navegación'

const CONFIRM_ROLE = 'administrador'
const CONFIRM_SURFACE = '/environments'
const CONFIRM_TABLE = '[data-environments-table]'
const CONFIRM_ROW_ACTION = 'Eliminar'
const CONFIRM_TITLE = 'Eliminar ambiente'
const CONFIRM_CHECKS = Object.freeze([
  'centered',
  'focus-trapped',
  'scroll-locked',
  'accessible-name',
  'escape-closes'
])
const DIALOG_CHECK_IDS = Object.freeze([
  'centered',
  'docked',
  'single-h1',
  'focus-trapped',
  'scroll-locked',
  'accessible-name',
  'escape-closes'
])

const manifest = loadManifest()

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: SESSION_EVIDENCE_DIR })

const shot = (page, filePath) => capture(page, filePath, { dir: SESSION_EVIDENCE_DIR })

// AppLayout filters `allItems` by role list and then by the permission map the
// login payload publishes; the manifest expands `all` and resolves each
// `can.*` key to its `verbo.recurso` string so both sides speak the same
// vocabulary.
const expectedEntries = (role, permissions) =>
  manifest.navigation.filter(
    item =>
      (item.roles.includes('all') || item.roles.includes(role)) &&
      item.permissionStrings.every(permission => permissions.includes(permission))
  )

const readNavLinks = (page, selector) =>
  page.evaluate(
    source =>
      Array.from(document.querySelectorAll(source)).map(anchor => ({
        href: anchor.getAttribute('href') ?? '',
        label: anchor.innerText.trim()
      })),
    selector
  )

const readDialogLinks = dialog =>
  dialog.evaluate(element =>
    Array.from(element.querySelectorAll('nav a')).map(anchor => ({
      href: anchor.getAttribute('href') ?? '',
      label: anchor.innerText.trim()
    }))
  )

const linkDiff = (observed, expected) => JSON.stringify(observed) === JSON.stringify(expected)

const lastLine = text =>
  text
    .split('\n')
    .map(line => line.trim())
    .filter(Boolean)
    .at(-1) ?? ''

const wsStateOf = label => (label ?? '').replace('Estado de WebSocket:', '').trim()

const headingInMain = page =>
  page.evaluate(() => document.querySelector('main h1')?.innerText.trim() ?? '')

const checkOf = (checks, id) => checks?.find(check => check.id === id)

// --- sidebar matrix per role ------------------------------------------------

for (const role of ROLES) {
  test(`@U1 shell · sidebar matrix for ${role}`, async ({ page, request }) => {
    const session = await loginAs(request, role)
    const expected = expectedEntries(role, session.user.permissions)
    const expectedHrefs = expected.map(item => item.to)
    const expectedLabels = expected.map(item => item.name)
    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: SHELL_ROUTE,
      slug: SHELL_SLUG,
      role,
      expectation: 'owner',
      href: SHELL_HREF,
      url: null,
      httpStatus: null,
      rendered: false,
      contentLength: 0,
      horizontalOverflow: null,
      viewports: { desktop: { ...DESKTOP } },
      shots: [],
      consoleErrors: diagnostics.consoleErrors,
      pageErrors: diagnostics.pageErrors,
      failedRequests: { expected: [], unexpected: [] },
      sidebar: {
        expected: expectedHrefs,
        observed: [],
        labels: [],
        match: false,
        labelMatch: false
      },
      wsStatus: null,
      topbarVisible: false,
      userName: '',
      sidebarHiddenMobile: false
    }

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)

      const response = await page.goto(SHELL_HREF)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator(SIDEBAR_LINKS)
        .first()
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, NAV_THRESHOLD, LOAD_SETTLE_TIMEOUT)

      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > NAV_THRESHOLD

      const observed = await readNavLinks(page, SIDEBAR_LINKS)
      evidence.sidebar = {
        expected: expectedHrefs,
        observed: observed.map(link => link.href),
        labels: observed.map(link => link.label),
        match: linkDiff(
          observed.map(link => link.href),
          expectedHrefs
        ),
        labelMatch: linkDiff(
          observed.map(link => link.label),
          expectedLabels
        )
      }

      evidence.wsStatus = wsStateOf(await page.getAttribute(WS_INDICATOR, 'aria-label'))
      evidence.topbarVisible = await page.locator(TOPBAR).isVisible()
      evidence.userName = lastLine(await page.locator(USER_MENU_TRIGGER).innerText())

      evidence.shots.push(await shot(page, `shots/${SHELL_SLUG}-${role}.png`))

      await page.setViewportSize(MOBILE)
      // A viewport resized after the desktop paint keeps desktop widths and
      // invents an overflow; the honest mobile shell is a document loaded at 390.
      await page.reload()
      await settleSurface(page, NAV_THRESHOLD, STEP_SETTLE_TIMEOUT)

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: overflow.scrollWidth,
        innerWidth: overflow.innerWidth
      }
      evidence.sidebarHiddenMobile = await page.locator(SIDEBAR).isHidden()

      evidence.shots.push(await shot(page, `shots/${SHELL_SLUG}-${role}-mobile.png`))
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    } finally {
      audit(`render/${SHELL_SLUG}-${role}.json`, evidence)
    }

    expect(evidence.sidebar.observed, `sidebar hrefs for ${role}`).toEqual(expectedHrefs)
    expect(evidence.sidebar.labels, `sidebar labels for ${role}`).toEqual(expectedLabels)
    expect(WS_STATES.includes(evidence.wsStatus), `WS indicator state for ${role}`).toBe(true)
    expect(evidence.topbarVisible, `topbar rendered for ${role}`).toBe(true)
    expect(evidence.userName, `user name in the menu trigger for ${role}`).toBe(session.user.name)
    expect(evidence.sidebarHiddenMobile, `mobile sidebar hidden for ${role}`).toBe(true)
    expect(evidence.shots, `captures for ${role}`).toHaveLength(2)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- catch-all inside the shell -------------------------------------------

for (const role of ROLES) {
  test(`@U1 shell · catch-all stays inside the shell for ${role}`, async ({ page, request }) => {
    const session = await loginAs(request, role)
    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: CATCH_ALL_ROUTE,
      slug: 'catch-all',
      role,
      expectation: 'owner',
      href: CATCH_ALL_PROBE,
      url: null,
      httpStatus: null,
      rendered: false,
      contentLength: 0,
      notFound: false,
      shellRendered: false,
      heading: '',
      horizontalOverflow: null,
      viewports: { desktop: { ...DESKTOP } },
      shots: [],
      consoleErrors: diagnostics.consoleErrors,
      pageErrors: diagnostics.pageErrors,
      failedRequests: { expected: [], unexpected: [] }
    }

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)

      const response = await page.goto(CATCH_ALL_PROBE)
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      await page
        .locator('main')
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, NAV_THRESHOLD, STEP_SETTLE_TIMEOUT)

      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > NAV_THRESHOLD
      // The topbar owns the first h1 of the document; the page heading is the
      // one inside <main>.
      evidence.heading = await headingInMain(page)
      evidence.notFound = /no encontrada/i.test(evidence.heading)
      evidence.shellRendered =
        (await page.locator(SIDEBAR).isVisible()) && (await page.locator(TOPBAR).isVisible())

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow

      evidence.shots.push(await shot(page, `shots/catch-all-${role}.png`))
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    } finally {
      audit(`render/catch-all-${role}.json`, evidence)
    }

    expect(evidence.shellRendered, `shell around the 404 for ${role}`).toBe(true)
    expect(evidence.notFound, `page heading for ${role}`).toBe(true)
    expect(evidence.shots, `captures for ${role}`).toHaveLength(1)
    expect(evidence.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- every sidebar entry navigates ----------------------------------------

for (const role of ROLES) {
  test(`@U1 shell · every sidebar entry navigates for ${role}`, async ({ page, request }) => {
    test.setTimeout(STEP_LIMIT)

    const session = await loginAs(request, role)
    const expected = expectedEntries(role, session.user.permissions)
    const diagnostics = collectDiagnostics(page)
    const rows = []

    try {
      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)

      await page.goto(SHELL_HREF)
      await page
        .locator(SIDEBAR_LINKS)
        .first()
        .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, NAV_THRESHOLD, LOAD_SETTLE_TIMEOUT)

      for (const item of expected) {
        const row = {
          to: item.to,
          label: item.name,
          pathname: null,
          contentLength: 0,
          pageErrors: [],
          unexpectedRequests: [],
          outcome: 'pending'
        }
        const failuresBefore = diagnostics.failedRequests.length
        const errorsBefore = diagnostics.pageErrors.length

        try {
          // Scoped to the nav: the sidebar logo is also an `a[href="/dashboard"]`.
          await page.locator(`${SIDEBAR_LINKS}[href="${item.to}"]`).click({ timeout: 5000 })
          await page.waitForFunction(path => window.location.pathname === path, item.to, {
            timeout: 15000
          })
          await settleSurface(page, NAV_THRESHOLD, STEP_SETTLE_TIMEOUT)
          row.outcome = 'navigated'
        } catch (error) {
          const message = String(error?.message ?? error).split('\n')[0]
          row.outcome = /timeout/i.test(message) ? 'timeout' : 'error'
          row.error = message
        }

        row.pathname = await page.evaluate(() => window.location.pathname).catch(() => null)
        row.contentLength = await contentLength(page).catch(() => 0)
        row.pageErrors = diagnostics.pageErrors.slice(errorsBefore)
        row.unexpectedRequests = classifyFailures(
          diagnostics.failedRequests.slice(failuresBefore),
          'owner'
        ).unexpected
        rows.push(row)
      }
    } finally {
      audit(`session/navigation-${role}.json`, {
        route: SHELL_ROUTE,
        slug: 'shell-navigation',
        role,
        href: SHELL_HREF,
        entries: rows.length,
        rows,
        consoleErrors: diagnostics.consoleErrors,
        pageErrors: diagnostics.pageErrors
      })
    }

    const missed = rows.filter(row => row.pathname !== row.to)
    const broken = rows.filter(row => row.outcome === 'error')
    expect(rows, `entries probed for ${role}`).toHaveLength(expected.length)
    expect(broken, `probe errors for ${role}`).toEqual([])
    expect(missed, `entries that did not navigate for ${role}`).toEqual([])
    expect(diagnostics.pageErrors, `page errors for ${role}`).toEqual([])
  })
}

// --- user menu ------------------------------------------------------------

test('@U1 shell · user menu items for administrador', async ({ page, request }) => {
  const role = 'administrador'
  const session = await loginAs(request, role)
  const diagnostics = collectDiagnostics(page)
  const requests = []
  page.on('request', request_ => requests.push(`${request_.method()} ${request_.url()}`))

  const evidence = {
    role,
    dropdownVisible: false,
    menuName: null,
    items: [],
    escapeCloses: false,
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors
  }

  const readItems = () =>
    page.evaluate(() => {
      const menu = document.querySelector('#user-menu-dropdown')
      if (!menu) return { buttons: [], labels: [] }

      const buttons = Array.from(menu.querySelectorAll('button')).map(button =>
        button.innerText.trim()
      )
      const labels = Array.from(menu.querySelectorAll('p'))
        .map(paragraph => paragraph.innerText.trim())
        .filter(Boolean)

      return { buttons, labels }
    })

  // One click, one wait, one observation: whatever the item did (navigate, open a
  // dialog, request, toast) is recorded instead of inferred.
  const observe = async () => {
    const mark = requests.length
    const url = page.url()
    await page.waitForTimeout(1000)

    return {
      navigated: page.url() !== url,
      pathname: await page.evaluate(() => window.location.pathname),
      dialogs: await page.locator(DIALOG_SELECTOR).count(),
      menuOpen: (await page.locator(USER_MENU).count()) > 0,
      toast: await page.evaluate(() => {
        const live = document.querySelector('[aria-live="assertive"]')
        return Boolean(live?.innerText.trim())
      }),
      requests: requests.slice(mark)
    }
  }

  const verdictOf = observation =>
    observation.navigated || observation.dialogs > 0 || observation.toast ? 'OK' : 'NO-OP'

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    await page.goto(SHELL_HREF)
    await page
      .locator(SIDEBAR_LINKS)
      .first()
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, NAV_THRESHOLD, LOAD_SETTLE_TIMEOUT)

    await page.locator(USER_MENU_TRIGGER).click()
    await page.locator(USER_MENU).waitFor({ state: 'visible', timeout: 5000 })
    evidence.dropdownVisible = await page.locator(USER_MENU).isVisible()
    evidence.menuName = await page.getAttribute(USER_MENU, 'aria-label')
    evidence.shots.push(await shot(page, 'shots/user-menu.png'))

    const declared = await readItems()

    await page.keyboard.press('Escape')
    evidence.escapeCloses = await page
      .locator(USER_MENU)
      .waitFor({ state: 'hidden', timeout: 2000 })
      .then(() => true)
      .catch(() => false)

    for (const label of declared.buttons) {
      if (label === 'Cerrar Sesión') {
        // Clicking it ends the audited session and revokes the shared token.
        evidence.items.push({
          label,
          verdict: 'OK',
          observed: 'wired to handleLogout in AppLayout; not clicked — it ends the audited session'
        })
        continue
      }

      await page.locator(USER_MENU_TRIGGER).click()
      await page.locator(USER_MENU).waitFor({ state: 'visible', timeout: 5000 })
      await page.getByRole('button', { name: label, exact: true }).click()

      const observation = await observe()
      evidence.items.push({
        label,
        verdict: verdictOf(observation),
        observed: observation
      })
      await page.keyboard.press('Escape')
    }

    if (declared.labels.includes('Apariencia')) {
      evidence.items.push({
        label: 'Apariencia',
        verdict: 'CONFUSO',
        observed:
          'static paragraph inside the menu; no control follows it, so the menu announces an appearance section it does not offer'
      })
    }

    evidence.items.sort(
      (left, right) => MENU_LABELS.indexOf(left.label) - MENU_LABELS.indexOf(right.label)
    )
  } finally {
    audit('session/user-menu.json', evidence)
  }

  expect(evidence.dropdownVisible, 'user menu opened').toBe(true)
  expect(evidence.escapeCloses, 'Escape closes the user menu').toBe(true)
  expect(
    evidence.items.map(item => item.label),
    'menu items'
  ).toEqual([...MENU_LABELS])
  expect(evidence.pageErrors, 'page errors during the user menu probe').toEqual([])
})

// --- mobile navigation sheet ---------------------------------------------

test('@U1 shell · mobile navigation sheet contract', async ({ page, request }) => {
  const role = SHEET_ROLE
  const session = await loginAs(request, role)
  const expected = expectedEntries(role, session.user.permissions)
  const expectedHrefs = expected.map(item => item.to)
  const target = expected.at(-1)
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: SHELL_ROUTE,
    slug: 'shell-navigation-sheet',
    role,
    opened: 0,
    dialogs: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    mobileSidebarHidden: false,
    sidebar: { expected: expectedHrefs, observed: [], labels: [], match: false },
    entry: null,
    findings: [],
    shots: []
  }

  const openSheet = async () => {
    await page.locator(MOBILE_MENU_TRIGGER).click()
    const dialog = page.locator(DIALOG_SELECTOR).last()
    await dialog.waitFor({ state: 'visible', timeout: 5000 })
    return dialog
  }

  try {
    await page.setViewportSize(MOBILE)
    await primeSession(page, session)

    await page.goto(SHELL_HREF)
    await page.locator(MOBILE_MENU_TRIGGER).waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    await settleSurface(page, NAV_THRESHOLD, LOAD_SETTLE_TIMEOUT)
    evidence.mobileSidebarHidden = await page.locator(SIDEBAR).isHidden()

    const dialog = await openSheet()
    evidence.opened = 1

    const links = await readDialogLinks(dialog)
    evidence.sidebar = {
      expected: expectedHrefs,
      observed: links.map(link => link.href),
      labels: links.map(link => link.label),
      match: linkDiff(
        links.map(link => link.href),
        expectedHrefs
      )
    }
    evidence.shots.push(await shot(page, 'shots/shell-navigation-sheet.png'))

    // The contract presses Escape last: it closes the sheet.
    const contract = await assertDialogContract(page, { dialog })
    evidence.dialogs.push({
      trigger: { label: 'Abrir menú', selector: MOBILE_MENU_TRIGGER },
      dialog: { ...contract.dialog, declaredTitle: SHEET_TITLE },
      checks: contract.checks
    })
    evidence.findings = contract.checks
      .filter(check => !check.ok)
      .map(check => `${check.id}: ${check.observed}`)

    // A second open, because the contract already dismissed the first one.
    const reopened = await openSheet()
    await reopened.locator(`nav a[href="${target.to}"]`).click()
    await page.waitForFunction(path => window.location.pathname === path, target.to, {
      timeout: 15000
    })
    await reopened.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

    evidence.entry = {
      to: target.to,
      label: target.name,
      pathname: await page.evaluate(() => window.location.pathname),
      sheetClosed: (await page.locator(DIALOG_SELECTOR).count()) === 0
    }
  } finally {
    audit('primitives/navigation-sheet.json', evidence)
  }

  const checks = evidence.dialogs[0]?.checks ?? []
  const check = id => checkOf(checks, id)

  expect(evidence.opened, 'sheet opened').toBe(1)
  expect(
    checks.map(row => row.id),
    'contract checks ran'
  ).toEqual([...DIALOG_CHECK_IDS])
  expect(evidence.sidebar.observed, `sheet entries for ${role}`).toEqual(expectedHrefs)
  expect(evidence.sidebar.labels, `sheet entry labels for ${role}`).toEqual(
    expected.map(item => item.name)
  )
  expect(check('docked')?.observed, 'sheet docking').toBe('right')
  expect(check('docked')?.ok, 'sheet docked right').toBe(true)
  expect(check('scroll-locked')?.ok, 'sheet locks scroll').toBe(true)
  expect(check('escape-closes')?.ok, 'Escape closes the sheet').toBe(true)
  expect(evidence.mobileSidebarHidden, 'desktop sidebar hidden at 390').toBe(true)
  expect(evidence.entry?.pathname, 'sheet entry navigates').toBe(target.to)
  expect(evidence.entry?.sheetClosed, 'sheet closes after a navigation').toBe(true)
  expect(evidence.pageErrors, 'page errors during the sheet probe').toEqual([])
})

// --- global confirm -------------------------------------------------------

test('@U1 shell · global confirm contract', async ({ page, request }) => {
  const role = CONFIRM_ROLE
  const session = await loginAs(request, role)
  const diagnostics = collectDiagnostics(page)
  const deletes = []
  page.on('request', request_ => {
    if (request_.method() === 'DELETE') deletes.push({ url: request_.url() })
  })

  const evidence = {
    route: CONFIRM_SURFACE,
    slug: 'global-confirm',
    role,
    opened: 0,
    dialogs: [],
    deleteRequests: [],
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    shots: []
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    await page.goto(CONFIRM_SURFACE)
    await page
      .locator('main')
      .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, NAV_THRESHOLD, LOAD_SETTLE_TIMEOUT)

    const table = page.locator(CONFIRM_TABLE)
    await table.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {})

    const trigger = table.getByRole('button', { name: CONFIRM_ROW_ACTION, exact: true }).first()

    if ((await trigger.count()) === 0) {
      evidence.reason = 'no-rows'
      test.skip(true, 'no environment row to open the global confirm')
    }

    const mark = deletes.length
    await trigger.click()

    const dialog = page.locator(DIALOG_SELECTOR).last()
    await dialog.waitFor({ state: 'visible', timeout: 5000 })
    evidence.opened = 1
    evidence.shots.push(await shot(page, 'shots/global-confirm.png'))

    const contract = await assertDialogContract(page, { dialog })
    evidence.dialogs.push({
      trigger: { label: CONFIRM_ROW_ACTION, selector: `${CONFIRM_TABLE} button` },
      dialog: contract.dialog,
      checks: contract.checks
    })
    evidence.deleteRequests = deletes.slice(mark)
  } finally {
    audit('primitives/global-confirm.json', evidence)
  }

  const dialogRow = evidence.dialogs[0]
  const checks = dialogRow?.checks ?? []
  const check = id => checkOf(checks, id)

  expect(evidence.opened, 'confirm opened').toBe(1)
  expect(dialogRow?.dialog.role, 'confirm role').toBe('alertdialog')
  expect(dialogRow?.dialog.name, 'confirm accessible name').toBe(CONFIRM_TITLE)
  for (const id of CONFIRM_CHECKS) {
    expect(check(id)?.ok, `confirm ${id}`).toBe(true)
  }
  expect(
    evidence.deleteRequests.filter(request_ => /\/api\/environments/.test(request_.url)),
    'DELETE during Escape cancel'
  ).toEqual([])
  expect(evidence.pageErrors, 'page errors during the confirm probe').toEqual([])
})
