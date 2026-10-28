import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { test, expect } from 'playwright/test'
import { freshLogin, primeSession } from './audit/harness.js'

// #407 (S7) — real browser pass over the three states of the /cash-register
// hero: a drawer-less actor, their open drawer, and their closed drawer with
// the closing counts.
//
// It is manual on purpose, like the rest of tests/e2e: the app and a database
// with a drawer-less actor must exist beforehand. Prepare a dedicated database
// (never the shared one) and run:
//
//   DB_DATABASE=odontosuite_cash_current_e2e php artisan migrate --seed --force
//   DB_DATABASE=odontosuite_cash_current_e2e php artisan tinker --execute \
//     "\$u = App\Models\User::where('username','elizabet')->first(); \
//      \$u->branch_id = 1; \$u->save(); \
//      App\Models\CashRegisterSession::where('user_id', \$u->id)->delete();"
//   DB_DATABASE=odontosuite_cash_current_e2e php artisan serve --port=8013
//
//   VITE_APP_URL=http://127.0.0.1:8013 pnpm build
//   CASH_CURRENT_BROWSER=1 BROWSER_PASS_BASE=http://127.0.0.1:8013 \
//     pnpm exec playwright test tests/e2e/cash-current.spec.js
//
// The `VITE_APP_URL` rebuild matters: the bundle bakes its API origin, and a
// token issued by the measured database is refused by another app.
const enabled = process.env.CASH_CURRENT_BROWSER === '1'

test.skip(!enabled, 'needs a dedicated database with a drawer-less actor (see header)')

const BASE = process.env.BROWSER_PASS_BASE || 'http://127.0.0.1:8013'
const EVIDENCE = resolve(process.cwd(), '.atl/qa-evidence/audit/money-current')

test('#407 the cash hero covers the three drawer states', async ({ page, request }) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'administrador')
  await primeSession(page, session)

  // (a) The actor has no drawer at all: "Sin sesión", KPIs in N/D, and the
  // door to open one. Another user's open drawer must not appear here.
  await page.goto(`${BASE}/cash-register`)
  await page.locator('[data-hero-state]').waitFor()
  await expect(page.locator('[data-hero-state]')).toContainText('Sin sesión')
  await expect(page.locator('[data-kpi-value="opening"]')).toHaveText('N/D')
  await expect(page.locator('[data-kpi-value="expected"]')).toHaveText('N/D')
  await expect(page.locator('[data-hero-action="open"]')).toBeVisible()
  await expect(page.locator('[data-hero-action="close"]')).toHaveCount(0)
  await page.screenshot({ path: `${EVIDENCE}/01-sin-sesion.png`, fullPage: true })

  // (b) The actor opens their own drawer: the hero is open and operable.
  const opened = await request.post(`${BASE}/api/cash-register/open`, {
    headers: { Authorization: `Bearer ${session.token}` },
    data: { branch_id: 1, opening_amount: 100 }
  })
  expect(opened.status()).toBe(201)
  const sessionId = (await opened.json())?.data?.id
  expect(sessionId).toBeTruthy()

  await page.reload()
  await expect(page.locator('[data-hero-state]')).toContainText('Sesión abierta')
  await expect(page.locator('[data-kpi-value="opening"]')).toContainText('100')
  await expect(page.locator('[data-hero-action="close"]')).toBeVisible()
  await expect(page.locator('[data-hero-action="open"]')).toHaveCount(0)
  await page.screenshot({ path: `${EVIDENCE}/02-sesion-abierta.png`, fullPage: true })

  // (c) After closing it, the hero keeps the closing counts and reopens.
  const closed = await request.post(`${BASE}/api/cash-register/close`, {
    headers: { Authorization: `Bearer ${session.token}` },
    data: { session_id: sessionId, closing_amount: 100 }
  })
  expect(closed.status()).toBe(200)

  await page.reload()
  await expect(page.locator('[data-hero-state]')).toContainText('Sesión cerrada')
  await expect(page.locator('[data-caja-closing]')).toBeVisible()
  await expect(page.locator('[data-caja-closing-amount]')).toContainText('100')
  await expect(page.locator('[data-hero-action="open"]')).toBeVisible()
  await expect(page.locator('[data-hero-action="close"]')).toHaveCount(0)
  await expect(page.locator('[data-hero-action="deposit"]')).toHaveCount(0)
  await page.screenshot({ path: `${EVIDENCE}/03-sesion-cerrada.png`, fullPage: true })
})
