// Plan #13 (issue #364, U1) — account recovery, logout and the reset surface.
//
// Imported by `surface.spec.js`, so `--grep "@U1 recovery"` selects these rows.
// Every row writes its evidence under `SESSION_EVIDENCE_DIR`. A product defect
// is recorded there as `verdict: 'defect'`; the test itself only fails when the
// instrument cannot observe the flow (a request without a status, a page error).

import { randomBytes } from 'node:crypto'
import { closeSync, existsSync, openSync, readSync, statSync } from 'node:fs'
import { resolve } from 'node:path'

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  PASSWORD,
  SESSION_EVIDENCE_DIR,
  capture,
  collectDiagnostics,
  freshLogin,
  loginAs,
  primeSession,
  writeEvidence
} from '../harness.js'

const DESKTOP = { width: 1440, height: 900 }
const NARROW = { width: 390, height: 844 }
const LOG_PATH = resolve(process.cwd(), 'storage/logs/laravel.log')
const NEW_PASSWORD = 'Audit-Pass-2026'
const TOKEN_POLL_MS = 5000
const TOKEN_POLL_INTERVAL = 200

const EXPECTED = {
  'create-user': 201,
  'forgot-password': 200,
  'reset-password': 200,
  'login-new-password': 200,
  'delete-user': 200
}

const mask = value => (value ? `${value.slice(0, 6)}…(${value.length})` : null)

const logSize = () => (existsSync(LOG_PATH) ? statSync(LOG_PATH).size : 0)

// Only the bytes written after the forgot-password call are read: the log holds
// unrelated traffic and the evidence stores counts, never the raw tail.
const readAppended = offset => {
  const size = logSize()

  if (size <= offset) return { bytes: 0, text: '' }

  const descriptor = openSync(LOG_PATH, 'r')

  try {
    const buffer = Buffer.alloc(size - offset)
    readSync(descriptor, buffer, 0, buffer.length, offset)
    // The log mailer writes MIME, which soft-wraps long URLs as `=\r\n`.
    return { bytes: buffer.length, text: buffer.toString('utf8').replace(/=\r?\n/g, '') }
  } finally {
    closeSync(descriptor)
  }
}

const lastResetToken = text => {
  const matches = [...text.matchAll(/token=([A-Za-z0-9]{64})/g)]
  return matches.length ? matches[matches.length - 1][1] : null
}

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))

// The reset link reaches the log while the request is being answered. Poll
// briefly so a slow log flush is not recorded as a missing token.
const readResetToken = async offset => {
  const deadline = Date.now() + TOKEN_POLL_MS
  let appended = { bytes: 0, text: '' }

  do {
    appended = readAppended(offset)
    const token = lastResetToken(appended.text)
    if (token) return { token, appended }
    await sleep(TOKEN_POLL_INTERVAL)
  } while (Date.now() <= deadline)

  return { token: null, appended }
}

const bearer = token => ({
  Authorization: `Bearer ${token}`,
  Accept: 'application/json'
})

const statusOf = response => response?.status() ?? null

// --- forgot password + reset with the mailed token --------------------------

test('@U1 recovery · forgot-password mails a token and reset-password accepts it', async ({
  request
}) => {
  const admin = await loginAs(request, 'administrador')
  const stamp = Date.now()
  const username = `audit_reset_${stamp}`
  const email = `audit-reset-${stamp}@test.com`

  const evidence = {
    flow: 'forgot-password + reset-password',
    actor: { role: admin.role, username: admin.user.username },
    user: { id: null, username, email, role: 'recepcionista' },
    log: { path: 'storage/logs/laravel.log', sizeBefore: null, sizeAfter: null, appendedBytes: 0 },
    token: null,
    statuses: [],
    loginTokenIssued: false,
    outcome: null,
    verdict: null,
    defects: []
  }

  let userId = null

  const record = (step, response) => {
    const status = statusOf(response)
    evidence.statuses.push({ step, status, expected: EXPECTED[step] ?? null })
    return status
  }

  const defect = (message, outcome = 'defect') => {
    evidence.defects.push(message)
    evidence.outcome = outcome
  }

  try {
    const created = await request.post(`${BASE_URL}/api/users`, {
      headers: bearer(admin.token),
      data: {
        name: 'Audit Recovery',
        username,
        email,
        password: PASSWORD,
        role: 'recepcionista'
      }
    })
    record('create-user', created)
    const createdBody = await created.json().catch(() => null)
    userId = createdBody?.data?.id ?? null
    evidence.user.id = userId

    if (statusOf(created) !== EXPECTED['create-user']) {
      defect(`create-user answered ${statusOf(created)}`)
      return
    }

    evidence.log.sizeBefore = logSize()

    const forgot = await request.post(`${BASE_URL}/api/auth/forgot-password`, {
      headers: { Accept: 'application/json' },
      data: { email }
    })
    record('forgot-password', forgot)

    if (statusOf(forgot) === 429) {
      evidence.outcome = 'throttled'
      test.skip(true, 'throttled 3/10 min')
    }

    if (statusOf(forgot) !== EXPECTED['forgot-password']) {
      defect(`forgot-password answered ${statusOf(forgot)}`)
      return
    }

    const { token, appended } = await readResetToken(evidence.log.sizeBefore)
    evidence.log.sizeAfter = logSize()
    evidence.log.appendedBytes = appended.bytes
    evidence.token = mask(token)

    if (!token) {
      defect('the mailed reset token was not present in the appended log bytes')
      return
    }

    const reset = await request.post(`${BASE_URL}/api/auth/reset-password`, {
      headers: { Accept: 'application/json' },
      data: {
        token,
        email,
        password: NEW_PASSWORD,
        password_confirmation: NEW_PASSWORD
      }
    })
    record('reset-password', reset)

    if (statusOf(reset) !== EXPECTED['reset-password']) {
      defect(`reset-password answered ${statusOf(reset)}`)
      return
    }

    const login = await request.post(`${BASE_URL}/api/auth/login`, {
      headers: { Accept: 'application/json' },
      data: { username, password: NEW_PASSWORD }
    })
    record('login-new-password', login)
    const loginBody = await login.json().catch(() => null)
    evidence.loginTokenIssued = Boolean(loginBody?.data?.token)

    if (statusOf(login) !== EXPECTED['login-new-password'] || !evidence.loginTokenIssued) {
      defect(`login with the new password answered ${statusOf(login)}`)
      return
    }

    evidence.outcome = 'ok'
  } finally {
    if (userId) {
      try {
        const cleanup = await request.delete(`${BASE_URL}/api/users/${userId}`, {
          headers: bearer(admin.token)
        })
        record('delete-user', cleanup)
        if (![200, 204].includes(statusOf(cleanup))) {
          defect(`delete-user answered ${statusOf(cleanup)}`)
        }
      } catch (error) {
        evidence.defects.push(`delete-user failed: ${error.message}`)
        evidence.statuses.push({ step: 'delete-user', status: null, expected: [200, 204] })
      }
    }

    if (evidence.outcome === 'throttled') evidence.verdict = 'skipped'
    else if (evidence.defects.length > 0) evidence.verdict = 'defect'
    else if (evidence.statuses.some(row => row.status === null)) evidence.verdict = 'defect'
    else evidence.verdict = 'ok'

    writeEvidence('session/recovery.json', evidence, { dir: SESSION_EVIDENCE_DIR })
  }

  expect(evidence.statuses.length).toBeGreaterThan(0)
  expect(evidence.statuses.every(row => row.status !== null)).toBe(true)
})

// --- logout from the user menu ----------------------------------------------

test('@U1 recovery · logout from the user menu returns to login and clears the session', async ({
  page,
  request
}) => {
  await page.setViewportSize(DESKTOP)

  const diagnostics = collectDiagnostics(page)
  // The flow revokes the token it uses, so it takes its own: the memoized one
  // keeps serving the rows that run after this point of the session.
  const session = await freshLogin(request, 'recepcionista')
  const evidence = {
    flow: 'logout',
    role: session.role,
    urlBefore: null,
    urlAfter: null,
    menu: { trigger: false, dropdown: false, logoutItem: false },
    storage: { authToken: null, user: null },
    statuses: [],
    shots: [],
    errors: [],
    pageErrors: [],
    consoleErrors: [],
    verdict: null
  }

  try {
    await primeSession(page, session)
    await page.goto('/dashboard')
    evidence.urlBefore = page.url()

    const trigger = page.locator('[data-app-chrome="topbar"] [aria-haspopup="menu"]').first()
    await trigger.waitFor({ state: 'visible', timeout: 15000 })
    evidence.menu.trigger = true
    await trigger.click()

    const dropdown = page.locator('#user-menu-dropdown')
    await dropdown.waitFor({ state: 'visible', timeout: 5000 })
    evidence.menu.dropdown = true

    const logoutItem = dropdown.getByRole('button', { name: 'Cerrar Sesión' })
    await logoutItem.waitFor({ state: 'visible', timeout: 5000 })
    evidence.menu.logoutItem = true

    const logoutResponse = page
      .waitForResponse(response => response.url().endsWith('/api/auth/logout'), {
        timeout: 10000
      })
      .catch(() => null)

    await logoutItem.click()
    evidence.statuses.push({ step: 'logout', status: statusOf(await logoutResponse) })

    await page.waitForURL(/\/login$/, { timeout: 10000 }).catch(() => {})
    await page.locator('main, #app').first().waitFor({ state: 'visible', timeout: 10000 })
  } catch (error) {
    evidence.errors.push(error.message)
  } finally {
    evidence.urlAfter = page.url()

    evidence.storage = await page
      .evaluate(() => {
        // The token is a live credential: the row records its presence and
        // length, never the value (R1 of the U1 review).
        const token = localStorage.getItem('auth_token')
        return {
          authToken: token ? `present(${token.length})` : null,
          user: localStorage.getItem('user')
        }
      })
      .catch(error => ({ authToken: null, user: null, error: error.message }))

    evidence.pageErrors = [...diagnostics.pageErrors]
    evidence.consoleErrors = [...diagnostics.consoleErrors]

    try {
      evidence.shots.push(await capture(page, 'session/logout.png', { dir: SESSION_EVIDENCE_DIR }))
    } catch (error) {
      evidence.errors.push(`capture: ${error.message}`)
    }

    const reachedLogin = /\/login$/.test(evidence.urlAfter)
    const cleared = evidence.storage.authToken === null && evidence.storage.user === null

    if (!reachedLogin) evidence.errors.push(`logout landed on ${evidence.urlAfter}`)
    if (!cleared) evidence.errors.push('localStorage kept the session after logout')

    evidence.verdict = evidence.errors.length === 0 ? 'ok' : 'defect'

    writeEvidence('session/logout.json', evidence, { dir: SESSION_EVIDENCE_DIR })
  }

  expect(evidence.urlAfter).toMatch(/^https?:/)
  expect(evidence.pageErrors).toEqual([])
})

// --- /reset-password surface ------------------------------------------------

test('@U1 recovery · the mailed link opens the reset form with the email filled', async ({
  page
}) => {
  await page.setViewportSize(NARROW)

  const diagnostics = collectDiagnostics(page)
  const token = randomBytes(32).toString('hex')
  const email = 'audit-reset-surface@test.com'
  const href = `/reset-password?token=${token}&email=${encodeURIComponent(email)}`

  const evidence = {
    flow: 'reset-password surface',
    href: `/reset-password?token=${mask(token)}&email=${encodeURIComponent(email)}`,
    httpStatus: null,
    form: null,
    shots: [],
    pageErrors: [],
    consoleErrors: [],
    verdict: null
  }

  try {
    const response = await page.goto(href)
    evidence.httpStatus = statusOf(response)

    const dialog = page.locator('[role="dialog"]').first()
    await dialog.waitFor({ state: 'visible', timeout: 15000 })

    const emailInput = dialog.locator('input[type="email"]').first()
    const tokenInput = dialog.locator('#reset-password-token')
    const labels = await dialog
      .locator('label')
      .allInnerTexts()
      .catch(() => [])

    // #382: the link already carries the code, so the form must not ask for it.
    // `count()` answers immediately; `inputValue()` auto-waits 30 s for a field
    // that is deliberately absent, so it runs only when the field exists.
    const tokenFieldPresent = (await tokenInput.count().catch(() => 0)) > 0
    const tokenValueLength = tokenFieldPresent
      ? (await tokenInput.inputValue().catch(() => '')).length
      : 0

    evidence.form = {
      title: (
        await dialog
          .locator('h2')
          .first()
          .innerText()
          .catch(() => '')
      ).trim(),
      labels: labels.map(label => label.trim()).filter(Boolean),
      emailValue: await emailInput.inputValue().catch(() => null),
      emailDisabled: await emailInput.isDisabled().catch(() => null),
      emailPrefilled: (await emailInput.inputValue().catch(() => null)) === email,
      tokenFieldPresent,
      tokenValueLength,
      tokenApplied:
        (await dialog
          .locator('[data-reset-token-applied]')
          .count()
          .catch(() => 0)) > 0,
      submitLabel: (
        await dialog
          .getByRole('button', { name: 'Restablecer contraseña' })
          .innerText()
          .catch(() => '')
      ).trim()
    }
  } catch (error) {
    evidence.error = error.message
  } finally {
    evidence.pageErrors = [...diagnostics.pageErrors]
    evidence.consoleErrors = [...diagnostics.consoleErrors]

    try {
      evidence.shots.push(
        await capture(page, 'session/reset-surface.png', { dir: SESSION_EVIDENCE_DIR })
      )
    } catch (error) {
      evidence.error = evidence.error ?? `capture: ${error.message}`
    }

    evidence.verdict =
      evidence.httpStatus === 200 &&
      evidence.form?.emailPrefilled === true &&
      evidence.form?.tokenFieldPresent === false &&
      evidence.form?.tokenApplied === true
        ? 'ok'
        : 'defect'

    writeEvidence('session/reset-surface.json', evidence, { dir: SESSION_EVIDENCE_DIR })
  }

  expect(evidence.httpStatus, 'document response observed').not.toBeNull()
  expect(evidence.pageErrors).toEqual([])
})
