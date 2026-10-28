import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { test, expect } from 'playwright/test'
import { freshLogin, primeSession } from './audit/harness.js'

// #387 (S2) — real browser pass over the first frame of the two desks that
// painted their empty state before the data landed. Both rows hold a source
// open with a Playwright route gate, observe the frame while it is in flight,
// and then release it to prove the page recovers into its content.
//
// It is manual on purpose, like the rest of tests/e2e: the app and a database
// with seeded appointments must exist beforehand. Prepare a dedicated database
// (never the shared one) and run:
//
//   DB_DATABASE=odontosuite_u2_resilience_e2e php artisan migrate --seed --force
//   DB_DATABASE=odontosuite_u2_resilience_e2e php artisan serve --port=8014
//
//   VITE_APP_URL=http://127.0.0.1:8014 pnpm build
//   U2_RESILIENCE_BROWSER=1 BROWSER_PASS_BASE=http://127.0.0.1:8014 \
//     pnpm exec playwright test tests/e2e/u2-resilience.spec.js --grep @387
//
// The `VITE_APP_URL` rebuild matters: the bundle bakes its API origin, and a
// token issued by the measured database is refused by another app.
const enabled = process.env.U2_RESILIENCE_BROWSER === '1'

test.skip(!enabled, 'needs a dedicated database and a running app (see header)')

const BASE = process.env.BROWSER_PASS_BASE || 'http://127.0.0.1:8014'
const EVIDENCE = resolve(process.cwd(), '.atl/qa-evidence/audit/u2-resilience')

// Holds a route open until `release()` runs, so the test can inspect the frame
// while that source is still in flight. One deferred per gate keeps the two
// held endpoints independent.
const holdRoute = async (page, pattern) => {
  let release
  const gate = new Promise(resolveGate => {
    release = resolveGate
  })

  await page.route(pattern, async route => {
    await gate
    await route.continue()
  })

  return { release: () => release() }
}

test('@387 dashboard first frame never publishes an empty state', async ({ page, request }) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'administrador')
  await primeSession(page, session)

  // The day sources resolve; stats and the cash session stay in flight.
  const statsGate = await holdRoute(page, '**/api/dashboard/stats')
  const cashGate = await holdRoute(page, '**/api/cash-register/current')

  await page.goto(`${BASE}/dashboard`)

  await expect(page.locator('[aria-label="Cargando resumen"]')).toBeVisible()
  await expect(page.locator('[data-state^="empty-"]')).toHaveCount(0)
  await expect(page.locator('[data-dashboard-content]')).toHaveCount(0)
  await page.screenshot({ path: `${EVIDENCE}/01-dashboard-primer-frame.png`, fullPage: true })

  statsGate.release()
  cashGate.release()

  await expect(page.locator('[data-dashboard-content]')).toBeVisible()
})

test('@387 my-day first frame is the skeleton and its counters are not zero', async ({
  page,
  request
}) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'odontologo')
  await primeSession(page, session)

  const queueGate = await holdRoute(page, '**/api/my-day')

  await page.goto(`${BASE}/my-day`)

  await expect(page.locator('[data-my-day-loading]')).toBeVisible()
  await expect(page.locator('[data-my-day-section]')).toHaveCount(0)
  await expect(page.locator('[data-my-day-count="waiting"] dd')).toHaveText('—')
  await expect(page.locator('[data-my-day-count="attending"] dd')).toHaveText('—')
  await page.screenshot({ path: `${EVIDENCE}/02-my-day-primer-frame.png`, fullPage: true })

  queueGate.release()

  await expect(page.locator('[data-my-day-section]').first()).toBeVisible()
  await expect(page.locator('[data-my-day-count="waiting"] dd')).toHaveText(/^\d+$/)
  await expect(page.locator('[data-my-day-count="attending"] dd')).toHaveText(/^\d+$/)
})

// #388 (S3) - the summary is one source among four. The row measures the
// healthy block list on a first pass and then compares it against the broken
// pass, so a summary failure can only pass if it keeps hoy, attention and
// upcoming exactly as they were.
const dashboardBlocks = page =>
  page
    .locator('[data-dashboard-block]')
    .evaluateAll(nodes => nodes.map(node => node.getAttribute('data-dashboard-block')))

test('@388 a failed summary leaves the other blocks mounted', async ({ page, request }) => {
  mkdirSync(EVIDENCE, { recursive: true })

  const session = await freshLogin(request, 'administrador')
  await primeSession(page, session)

  // Healthy reference. The list is measured instead of hard-coded because the
  // pending block only renders when the seeded role has pending groups.
  await page.goto(`${BASE}/dashboard`)
  await expect(page.locator('[data-dashboard-content]')).toBeVisible()
  await expect(page.locator('[data-appointment-row]').first()).toBeVisible()

  const healthyBlocks = await dashboardBlocks(page)
  expect(healthyBlocks).toContain('hoy')
  expect(healthyBlocks).toContain('upcoming')

  // Cut only the summary: the agenda, the pending rows and the week preview
  // must keep rendering from their own answers.
  await page.route('**/api/dashboard/stats', route => route.abort())
  await page.reload()

  await expect(page.locator('[data-state="error-stats"]')).toBeVisible()
  await expect(page.locator('[data-appointment-row]').first()).toBeVisible()
  await expect.poll(() => dashboardBlocks(page)).toEqual(healthyBlocks)
  await page.screenshot({ path: `${EVIDENCE}/03-dashboard-resumen-caido.png`, fullPage: true })

  // The retry recovers the summary alone: nothing else is re-fetched nor
  // unmounted, so the block list survives it unchanged.
  await page.unroute('**/api/dashboard/stats')
  await page.locator('[data-retry-stats]').click()

  await expect(page.locator('[data-state="error-stats"]')).toBeHidden()
  await expect.poll(() => dashboardBlocks(page)).toEqual(healthyBlocks)
})
