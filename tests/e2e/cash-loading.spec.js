import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { test, expect } from 'playwright/test'
import { freshLogin, primeSession } from './audit/harness.js'

// #411 (S8) — real browser pass over the first paint of /cash-register: the
// hero owns its loading state while GET /api/cash-register/current is in
// flight, so it never claims "Sin sesión" nor publishes N/D balances it cannot
// know; and a failed reading paints its own panel, whose Reintentar recovers
// without reloading the document.
//
// It is manual on purpose, like the rest of tests/e2e: the app and a database
// seeded with the demo roles must exist beforehand. Prepare a dedicated
// database (never the shared one) and run:
//
//   DB_DATABASE=odontosuite_cash_loading_e2e php artisan migrate --seed --force
//   DB_DATABASE=odontosuite_cash_loading_e2e php artisan serve --port=8014
//
//   VITE_APP_URL=http://127.0.0.1:8014 pnpm build
//   CASH_LOADING_BROWSER=1 BROWSER_PASS_BASE=http://127.0.0.1:8014 \
//     pnpm exec playwright test tests/e2e/cash-loading.spec.js
//
// The `VITE_APP_URL` rebuild matters: the bundle bakes its API origin, and a
// token issued by the measured database is refused by another app.
const enabled = process.env.CASH_LOADING_BROWSER === '1'

test.skip(!enabled, 'needs a dedicated database and the served app of the #411 pass (see header)')

const BASE = process.env.BROWSER_PASS_BASE || 'http://127.0.0.1:8014'
const EVIDENCE = resolve(process.cwd(), '.atl/qa-evidence/audit/money-loading')
const CURRENT_URL = '**/api/cash-register/current'
// The three session labels of the hero: a failed reading must paint none of
// them, and a recovered one must paint exactly one.
const HERO_STATES = /^(Sin sesión|Sesión abierta|Sesión cerrada)$/

test('#411 the hero represents the first reading', async ({ page, request }) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'administrador')
  await primeSession(page, session)

  // The reading stays in flight long enough for the first paint to be observed.
  await page.route(CURRENT_URL, async route => {
    await new Promise(done => setTimeout(done, 900))
    await route.continue()
  })

  await page.goto(`${BASE}/cash-register`)

  const hero = page.locator('[data-caja-hero]')
  await expect(page.locator('[data-caja-hero-loading]')).toBeVisible()
  await expect(page.locator('[data-hero-state]')).toHaveCount(0)
  await expect(hero).not.toContainText('Sin sesión')
  await expect(hero).not.toContainText('N/D')
  // The balances live in the KPI strip, below the header: while the reading is
  // in flight they are skeletons, never a false N/D.
  for (const kpi of ['opening', 'income', 'expenses', 'expected']) {
    await expect(page.locator(`[data-kpi-value="${kpi}"]`)).not.toContainText('N/D')
  }
  await page.screenshot({ path: `${EVIDENCE}/01-primer-pintado.png`, fullPage: true })

  await expect(page.locator('[data-hero-state]')).toBeVisible()
  await expect(page.locator('[data-caja-hero-loading]')).toHaveCount(0)

  await page.unroute(CURRENT_URL)
})

test('#411 a failed reading paints the panel and the retry recovers', async ({ page, request }) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'administrador')
  await primeSession(page, session)

  // Only the first reading fails; Reintentar must reach the real API, so the
  // route never decides by request order beyond that first call.
  let readings = 0
  await page.route(CURRENT_URL, async route => {
    readings += 1

    if (readings === 1) {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Caja no disponible' })
      })
      return
    }

    await route.continue()
  })

  await page.goto(`${BASE}/cash-register`)

  const panel = page.locator('[data-caja-error]')
  const heroState = page.locator('[data-hero-state]')
  await expect(panel).toBeVisible()
  await expect(panel).toContainText('No se pudo cargar la caja')
  await expect(page.locator('[data-caja-retry]')).toBeVisible()
  // A failure is not a session: the hero owns its own state instead of one of
  // the three labels the drawer can have.
  await expect(heroState).toHaveText('Estado no disponible')
  await page.screenshot({ path: `${EVIDENCE}/02-error-antes-de-reintentar.png`, fullPage: true })

  const readingsBeforeRetry = readings
  await page.locator('[data-caja-retry]').click()

  await expect(panel).toHaveCount(0)
  await expect(page.locator('[data-caja-hero-loading]')).toHaveCount(0)
  // The drawer state is the seeded actor's business: recovery only owes one of
  // the three known labels, never a specific one.
  await expect(heroState).toBeVisible()
  await expect(heroState).toHaveText(HERO_STATES)
  expect(readings).toBeGreaterThan(readingsBeforeRetry)

  await page.unroute(CURRENT_URL)
})
