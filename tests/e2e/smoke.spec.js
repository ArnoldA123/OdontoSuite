import { test, expect } from 'playwright/test'
import { mkdirSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'

// Axis A7 (issue #31) · browser smoke pass.
//
// One real UI login, then one spec per page. Each page spec records every
// console.error, pageerror and failed request (status >= 400) into
// <evidence>/<slug>.json so the CLI wrapper can summarize findings without
// re-parsing Playwright output. Selectors are read from the page templates:
// authenticated pages render a PageHeader h1 (`h1.page-title`) and the
// dashboard renders a stable `aria-label` region instead.
const EVIDENCE_DIR = resolve(
  process.cwd(),
  process.env.BROWSER_PASS_OUT || '.atl/qa-evidence/audit/browser'
)
const BASE_URL = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'
const CREDENTIALS = { username: 'elizabet', password: 'password123' }
const DASHBOARD_LOCATOR = 'section[aria-label="Resumen del día"]'

const PAGES = [
  { slug: 'dashboard', path: '/dashboard', locator: DASHBOARD_LOCATOR },
  { slug: 'calendar', path: '/calendar', locator: 'h1.page-title:has-text("Agenda")' },
  { slug: 'patients', path: '/patients', locator: 'h1.page-title:has-text("Pacientes")' },
  {
    slug: 'cash-register',
    path: '/cash-register',
    locator: 'h1.page-title:has-text("Gestión de Caja")'
  },
  {
    slug: 'quotations',
    path: '/quotations',
    locator: 'h1.page-title:has-text("Presupuestos")'
  },
  {
    slug: 'business-intelligence',
    path: '/business-intelligence',
    locator: 'h1.page-title:has-text("Business Intelligence")'
  }
]

function collectDiagnostics(page) {
  const consoleErrors = []
  const pageErrors = []
  const failedRequests = []

  page.on('console', message => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text())
    }
  })
  page.on('pageerror', error => {
    pageErrors.push(error.message)
  })
  page.on('response', response => {
    if (response.status() >= 400) {
      failedRequests.push({ status: response.status(), url: response.url() })
    }
  })
  page.on('requestfailed', request => {
    failedRequests.push({
      status: null,
      url: request.url(),
      failure: request.failure()?.errorText || 'request failed'
    })
  })

  return { consoleErrors, pageErrors, failedRequests }
}

function writeEvidence(slug, payload) {
  mkdirSync(EVIDENCE_DIR, { recursive: true })
  writeFileSync(resolve(EVIDENCE_DIR, `${slug}.json`), `${JSON.stringify(payload, null, 2)}\n`)
}

// Auth bootstrap for the page specs. A single API login avoids hammering the
// per-username login throttle (3/min) with one UI login per page; the real UI
// login is covered by its own spec below. The token is injected into
// localStorage before the SPA boots so router guards see an authenticated
// session on first paint.
let authState = null

test.beforeAll(async ({ request }) => {
  const response = await request.post(`${BASE_URL}/api/auth/login`, { data: CREDENTIALS })
  expect(response.ok()).toBeTruthy()
  const body = await response.json()
  const token = body?.data?.token
  const user = body?.data?.user
  expect(token).toBeTruthy()
  expect(user).toBeTruthy()
  authState = { token, user }
})

test('login renders the dashboard for a real session', async ({ page }) => {
  const diagnostics = collectDiagnostics(page)
  let rendered = false

  try {
    await page.goto('/login')
    await expect(page.locator('#login-username')).toBeVisible()
    await page.fill('#login-username', CREDENTIALS.username)
    await page.fill('#login-password', CREDENTIALS.password)
    await page.locator('form.login-form button[type="submit"]').click()
    await expect(page.locator(DASHBOARD_LOCATOR)).toBeVisible({ timeout: 20000 })
    await expect(page).toHaveURL(/\/dashboard$/)
    rendered = true
  } finally {
    writeEvidence('login', {
      page: 'login',
      path: '/login',
      url: page.url(),
      rendered,
      ...diagnostics
    })
  }
})

for (const definition of PAGES) {
  test(`page renders: ${definition.path}`, async ({ page }) => {
    const diagnostics = collectDiagnostics(page)
    let rendered = false

    try {
      await page.addInitScript(state => {
        localStorage.setItem('auth_token', state.token)
        localStorage.setItem('user', JSON.stringify(state.user))
      }, authState)
      await page.goto(definition.path)
      await expect(page.locator(definition.locator)).toBeVisible()
      rendered = true
    } finally {
      writeEvidence(definition.slug, {
        page: definition.slug,
        path: definition.path,
        url: page.url(),
        rendered,
        ...diagnostics
      })
    }
  })
}
