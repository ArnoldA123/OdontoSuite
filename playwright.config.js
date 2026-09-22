import { defineConfig } from 'playwright/test'

// Axis A7 (issue #31) · real browser pass over the running app.
//
// The config is deliberately thin: the app (php artisan serve on :8000) and
// Vite (:5173) are expected to be already up, so there is no `webServer`
// block starting anything. `browserName: 'chromium'` with the default
// `headless: true` resolves to the Chromium headless shell installed by
// `pnpm exec playwright install chromium --only-shell`.
const EVIDENCE_DIR = process.env.BROWSER_PASS_OUT || '.atl/qa-evidence/audit/browser'

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  timeout: 30000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: !!process.env.CI,
  reporter: [['list']],
  outputDir: EVIDENCE_DIR,
  use: {
    baseURL: process.env.BROWSER_PASS_BASE || 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure'
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }]
})
