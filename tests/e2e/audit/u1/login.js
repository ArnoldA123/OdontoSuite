// Plan #13 (issue #364, U1) — login surface and the router guards.
//
// Imported by `surface.spec.js`, so `--grep "@U1 login"` selects this file's
// rows. Every title starts with `@U1 login · ` and every row writes its evidence
// under SESSION_EVIDENCE_DIR.
//
// A row measures the observable end of the flow and compares it with the
// expected end. A mismatch is a product defect recorded in the evidence as
// `verdict: 'defect'`; it never turns the suite red. The only hard assertion is
// the session's zero-pageerror criterion (S5) and the instrument's own premise:
// the measure was observed at all.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  PASSWORD,
  ROLE_USERNAMES,
  SESSION_EVIDENCE_DIR,
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

const DESKTOP = { width: 1440, height: 900 }
const LOGIN = '/login'
const DASHBOARD = '/dashboard'
const MEDICAL_RECORDS = '/medical-records'

const CONTENT_THRESHOLD = 80
const NAVIGATION_TIMEOUT = 15000
// The login throttle keeps a one-minute window per username; a 62 s wait leaves
// the window behind while the five-failure counter (10 min) survives, which is
// exactly the sequence the 10-minute lockout is specified on.
const LOCKOUT_WAIT_MS = 62000

// Never the seeded password: a wrong-password row must not be mistaken for a
// failed login of a seeded user, and the seeded users keep their credentials.
const WRONG_PASSWORD = 'definitivamente-incorrecta'
const CREDENTIALS_PATTERN = /credenciales|incorrectas/i
const LIMIT_PATTERN = /l[ií]mite/i
const BLOCKED_PATTERN = /bloqueada temporalmente|n[uú]mero m[aá]ximo de intentos/i
const LEAK_PATTERN = /SQL|Exception|Illuminate|stack|Bearer|password123/i

const check = (id, ok, observed, expected) => ({ id, ok: Boolean(ok), observed, expected })

const verdictOf = checks => (checks.every(entry => entry.ok) ? 'ok' : 'defect')

const noteFor = (checks, okNote) => {
  const failed = checks.filter(entry => !entry.ok).map(entry => entry.id)
  return failed.length === 0 ? okNote : `defect: ${failed.join(', ')}`
}

// Each row owns its throttle key: the middleware keys on ip + username, so a
// disposable name isolates the row from the seeded users and from every other
// row of the run.
const disposableUsername = purpose =>
  `audit_u1_${purpose}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`

const readBody = async response => {
  try {
    return await response.json()
  } catch {
    return null
  }
}

const bodyText = body => (body == null ? '' : JSON.stringify(body))

const pathnameOf = page => {
  try {
    return new URL(page.url()).pathname
  } catch {
    return null
  }
}

const visible = async locator => locator.isVisible().catch(() => false)

const writeRow = (file, evidence, { checks, pageErrors = [], okNote = '' }) => {
  writeEvidence(
    file,
    {
      ...evidence,
      verdict: verdictOf(checks),
      checks,
      note: noteFor(checks, okNote)
    },
    { dir: SESSION_EVIDENCE_DIR }
  )

  expect(pageErrors, 'page errors').toEqual([])
}

// --- requireAuth ------------------------------------------------------------

test('@U1 login · requireAuth sends an anonymous visitor to /login', async ({ page }) => {
  await page.setViewportSize(DESKTOP)

  const diagnostics = collectDiagnostics(page)
  const evidence = {
    slug: 'login',
    role: 'anonymous',
    expectation: 'require-auth',
    routes: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] }
  }
  const checks = []

  try {
    for (const route of [DASHBOARD, MEDICAL_RECORDS]) {
      const response = await page.goto(route)
      const httpStatus = response?.status() ?? null

      // The guard runs once the SPA boots, so the redirect is client-side: wait
      // for it, bounded, and measure whatever end the visitor actually reached.
      await page
        .waitForURL(url => url.pathname === LOGIN, { timeout: NAVIGATION_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, CONTENT_THRESHOLD)

      const formVisible =
        (await visible(page.locator('#login-username'))) &&
        (await visible(page.locator('#login-password'))) &&
        (await visible(page.locator('button[type="submit"]')))

      const overflow = await hasHorizontalOverflow(page)
      const shot = await capture(page, `shots/login-requireauth-${route.slice(1)}.png`, {
        dir: SESSION_EVIDENCE_DIR
      })
      const landed = pathnameOf(page)

      evidence.routes.push({
        route,
        href: route,
        url: page.url(),
        httpStatus,
        redirectedTo: landed,
        formVisible,
        contentLength: await contentLength(page),
        horizontalOverflow: overflow.overflow,
        shots: [shot]
      })

      checks.push(check(`redirect:${route}`, landed === LOGIN, landed, LOGIN))
      checks.push(check(`form-visible:${route}`, formVisible, formVisible, true))
      checks.push(check(`document-status:${route}`, httpStatus === 200, httpStatus, 200))
    }

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
  } finally {
    writeRow('session/require-auth.json', evidence, {
      checks,
      pageErrors: diagnostics.pageErrors,
      okNote: 'anonymous visitor reached /login from /dashboard and /medical-records'
    })
  }
})

// --- requireGuest × 7 -------------------------------------------------------

for (const role of Object.keys(ROLE_USERNAMES)) {
  test(`@U1 login · requireGuest sends ${role} from /login to /dashboard`, async ({
    page,
    request
  }) => {
    await page.setViewportSize(DESKTOP)

    const session = await loginAs(request, role)
    await primeSession(page, session)

    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: LOGIN,
      slug: 'login',
      role,
      expectation: 'require-guest',
      href: LOGIN,
      url: null,
      httpStatus: null,
      rendered: false,
      redirectedTo: null,
      contentLength: 0,
      horizontalOverflow: null,
      shots: [],
      consoleErrors: diagnostics.consoleErrors,
      pageErrors: diagnostics.pageErrors,
      failedRequests: { expected: [], unexpected: [] },
      username: ROLE_USERNAMES[role]
    }
    const checks = []

    try {
      const response = await page.goto(LOGIN)
      evidence.httpStatus = response?.status() ?? null

      await page
        .waitForURL(url => url.pathname === DASHBOARD, { timeout: NAVIGATION_TIMEOUT })
        .catch(() => {})
      await settleSurface(page, CONTENT_THRESHOLD)

      evidence.url = page.url()
      evidence.redirectedTo = pathnameOf(page)
      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > CONTENT_THRESHOLD

      const overflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = overflow.overflow

      evidence.shots.push(
        await capture(page, `shots/login-requireguest-${role}.png`, { dir: SESSION_EVIDENCE_DIR })
      )

      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')

      checks.push(
        check(
          'redirected-to-dashboard',
          evidence.redirectedTo === DASHBOARD,
          evidence.redirectedTo,
          DASHBOARD
        )
      )
      checks.push(check('document-status', evidence.httpStatus === 200, evidence.httpStatus, 200))
      checks.push(
        check('rendered', evidence.rendered, evidence.contentLength, `> ${CONTENT_THRESHOLD}`)
      )
    } finally {
      writeRow(`render/login-requireguest-${role}.json`, evidence, {
        checks,
        pageErrors: diagnostics.pageErrors,
        okNote: 'require-guest redirected to /dashboard from /login'
      })
    }
  })
}

// --- valid UI login ---------------------------------------------------------

test('@U1 login · a valid UI login reaches /dashboard with a stored token', async ({ page }) => {
  await page.setViewportSize(DESKTOP)

  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: LOGIN,
    slug: 'login',
    role: 'recepcionista',
    username: ROLE_USERNAMES.recepcionista,
    expectation: 'login-valid',
    href: LOGIN,
    url: null,
    documentStatus: null,
    httpStatus: null,
    redirectedTo: null,
    contentLength: 0,
    authToken: { present: false, length: 0 },
    horizontalOverflow: null,
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] }
  }
  const checks = []

  try {
    const document = await page.goto(LOGIN)
    evidence.documentStatus = document?.status() ?? null

    const formReady = await page
      .locator('#login-username')
      .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
      .then(() => true)
      .catch(() => false)

    checks.push(check('login-form-visible', formReady, formReady, true))

    let httpStatus = null

    if (formReady) {
      await page.locator('#login-username').fill(ROLE_USERNAMES.recepcionista)
      await page.locator('#login-password').fill(PASSWORD)

      // The request is the measured contract: the status is recorded even when
      // the redirect never happens.
      const response = await Promise.all([
        page
          .waitForResponse(
            candidate =>
              candidate.url().endsWith('/api/auth/login') &&
              candidate.request().method() === 'POST',
            { timeout: NAVIGATION_TIMEOUT }
          )
          .catch(() => null),
        page.locator('button[type="submit"]').click()
      ]).then(([resolved]) => resolved)

      httpStatus = response?.status() ?? null
    }

    evidence.httpStatus = httpStatus

    await page
      .waitForURL(url => url.pathname === DASHBOARD, { timeout: NAVIGATION_TIMEOUT })
      .catch(() => {})
    await settleSurface(page, CONTENT_THRESHOLD)

    evidence.url = page.url()
    evidence.redirectedTo = pathnameOf(page)
    evidence.contentLength = await contentLength(page)

    const token = await page.evaluate(() => localStorage.getItem('auth_token')).catch(() => null)
    evidence.authToken = { present: Boolean(token), length: token ? token.length : 0 }

    const overflow = await hasHorizontalOverflow(page)
    evidence.horizontalOverflow = overflow.overflow

    evidence.shots.push(await capture(page, 'shots/login-valid.png', { dir: SESSION_EVIDENCE_DIR }))

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')

    checks.push(check('login-status', httpStatus === 200, httpStatus, 200))
    checks.push(
      check(
        'redirected-to-dashboard',
        evidence.redirectedTo === DASHBOARD,
        evidence.redirectedTo,
        DASHBOARD
      )
    )
    checks.push(
      check('auth-token-stored', evidence.authToken.present, evidence.authToken.length, '> 0')
    )
  } finally {
    writeRow('session/login-valid.json', evidence, {
      checks,
      pageErrors: diagnostics.pageErrors,
      okNote: 'valid credentials reached /dashboard with a stored bearer token'
    })
  }
})

// --- invalid UI login -------------------------------------------------------

test('@U1 login · an invalid login shows a generic failure without internals', async ({ page }) => {
  await page.setViewportSize(DESKTOP)

  const username = disposableUsername('invalid')
  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: LOGIN,
    slug: 'login',
    role: 'anonymous',
    username,
    expectation: 'login-invalid',
    href: LOGIN,
    url: null,
    httpStatus: null,
    redirectedTo: null,
    alertVisible: false,
    message: '',
    leakedTokens: [],
    contentLength: 0,
    horizontalOverflow: null,
    shots: [],
    consoleErrors: diagnostics.consoleErrors,
    pageErrors: diagnostics.pageErrors,
    failedRequests: { expected: [], unexpected: [] }
  }
  const checks = []

  try {
    await page.goto(LOGIN)

    const formReady = await page
      .locator('#login-username')
      .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
      .then(() => true)
      .catch(() => false)

    checks.push(check('login-form-visible', formReady, formReady, true))

    let httpStatus = null
    let body = null

    if (formReady) {
      await page.locator('#login-username').fill(username)
      await page.locator('#login-password').fill(WRONG_PASSWORD)

      const response = await Promise.all([
        page
          .waitForResponse(
            candidate =>
              candidate.url().endsWith('/api/auth/login') &&
              candidate.request().method() === 'POST',
            { timeout: NAVIGATION_TIMEOUT }
          )
          .catch(() => null),
        page.locator('button[type="submit"]').click()
      ]).then(([resolved]) => resolved)

      httpStatus = response?.status() ?? null
      body = response ? await readBody(response) : null
    }

    evidence.httpStatus = httpStatus
    evidence.responseMessage = body?.message ?? null
    evidence.responseErrors = body?.errors ?? null

    // The failure surface is the alert; the probe is bounded so a missing alert
    // is a recorded defect, not an auto-wait timeout.
    const alert = page.locator('[role="alert"]').first()
    await alert.waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT }).catch(() => {})

    evidence.alertVisible = await visible(alert)
    evidence.message = ((await alert.innerText().catch(() => '')) || '').trim()
    evidence.leakedTokens = evidence.message.match(LEAK_PATTERN) ?? []

    await settleSurface(page, CONTENT_THRESHOLD)

    evidence.url = page.url()
    evidence.redirectedTo = pathnameOf(page)
    evidence.contentLength = await contentLength(page)

    const overflow = await hasHorizontalOverflow(page)
    evidence.horizontalOverflow = overflow.overflow

    evidence.shots.push(
      await capture(page, 'shots/login-invalid.png', { dir: SESSION_EVIDENCE_DIR })
    )

    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')

    checks.push(check('login-status', httpStatus === 422, httpStatus, 422))
    checks.push(check('alert-visible', evidence.alertVisible, evidence.alertVisible, true))
    checks.push(
      check(
        'alert-names-credentials',
        CREDENTIALS_PATTERN.test(evidence.message),
        evidence.message,
        CREDENTIALS_PATTERN.source
      )
    )
    checks.push(
      check('no-internals-leaked', evidence.leakedTokens.length === 0, evidence.leakedTokens, [])
    )
    checks.push(
      check('stayed-on-login', evidence.redirectedTo === LOGIN, evidence.redirectedTo, LOGIN)
    )
  } finally {
    writeRow('session/login-invalid.json', evidence, {
      checks,
      pageErrors: diagnostics.pageErrors,
      okNote:
        'invalid credentials answered 422 and the alert named the credentials without internals'
    })
  }
})

// The two throttle rows observe the endpoint contract itself, so they use the
// API context instead of a rendered surface: no viewport applies because no page
// is loaded, and the measured value is the status sequence the middleware emits.

test('@U1 login · the login endpoint throttles the fourth attempt', async ({ request }) => {
  const username = disposableUsername('throttle')
  const attempts = []

  for (let attempt = 1; attempt <= 4; attempt += 1) {
    const startedAt = Date.now()
    const response = await request.post(`${BASE_URL}/api/auth/login`, {
      data: { username, password: WRONG_PASSWORD }
    })
    const body = await readBody(response)

    attempts.push({
      attempt,
      status: response.status(),
      message: body?.message ?? null,
      errors: body?.errors ?? null,
      elapsedMs: Date.now() - startedAt
    })
  }

  const statuses = attempts.map(attempt => attempt.status)
  const expectedStatuses = [422, 422, 422, 429]
  const limitNamed = LIMIT_PATTERN.test(bodyText(attempts[3]?.errors ?? attempts[3]?.message))

  const checks = [
    check(
      'status-sequence',
      statuses.length === expectedStatuses.length &&
        statuses.every((status, index) => status === expectedStatuses[index]),
      statuses,
      expectedStatuses
    ),
    check(
      'limit-named',
      limitNamed,
      attempts[3]?.errors ?? attempts[3]?.message,
      LIMIT_PATTERN.source
    )
  ]

  writeRow(
    'session/throttle.json',
    { username, expectedStatuses, statuses, attempts },
    {
      checks,
      okNote: 'the fourth attempt for one username was refused with 429 and the limit was named'
    }
  )
})

test('@U1 login · five failures lock the account for ten minutes', async ({ request }) => {
  test.setTimeout(240000)

  const username = disposableUsername('lockout')
  const attempts = []
  const startedAt = Date.now()

  const attempt = async phase => {
    const requestStartedAt = Date.now()
    const response = await request.post(`${BASE_URL}/api/auth/login`, {
      data: { username, password: WRONG_PASSWORD }
    })
    const body = await readBody(response)

    attempts.push({
      phase,
      status: response.status(),
      message: body?.message ?? null,
      errors: body?.errors ?? null,
      atMs: requestStartedAt - startedAt,
      elapsedMs: Date.now() - requestStartedAt
    })
  }

  for (let round = 0; round < 3; round += 1) await attempt('before-wait')

  await new Promise(resolve => setTimeout(resolve, LOCKOUT_WAIT_MS))

  for (let round = 0; round < 2; round += 1) await attempt('after-wait')

  await attempt('blocked')

  const elapsedMs = Date.now() - startedAt
  const statuses = attempts.map(entry => entry.status)
  const blocked = attempts[attempts.length - 1]
  const failedBeforeBlock = statuses.slice(0, 5)
  const blockedNamed = BLOCKED_PATTERN.test(bodyText(blocked?.errors ?? blocked?.message))

  const checks = [
    check(
      'failures-accepted',
      failedBeforeBlock.length === 5 && failedBeforeBlock.every(status => status === 422),
      failedBeforeBlock,
      [422, 422, 422, 422, 422]
    ),
    check('blocked-status', blocked?.status === 429, blocked?.status ?? null, 429),
    check(
      'blocked-named',
      blockedNamed,
      blocked?.errors ?? blocked?.message,
      BLOCKED_PATTERN.source
    ),
    check('waited-a-minute', elapsedMs >= LOCKOUT_WAIT_MS, elapsedMs, `>= ${LOCKOUT_WAIT_MS}`)
  ]

  writeRow(
    'session/lockout.json',
    { username, waitMs: LOCKOUT_WAIT_MS, statuses, attempts, elapsedMs },
    {
      checks,
      okNote: 'five failures locked the disposable account and the temporary block was named'
    }
  )
})
