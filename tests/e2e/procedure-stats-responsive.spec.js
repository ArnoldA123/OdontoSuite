import { test, expect } from 'playwright/test'

// issue #157 / item 6 · responsive guard for the redesigned /procedure-stats.
//
// The 390 px defects fixed in #155 (axis labels cut by the canvas edge, hero
// buttons clipped) were found with a throwaway browser script that never
// reached the repo. jsdom has no layout, so the component tests cannot pin
// them: this spec keeps the guard in the repository.
const BASE_URL = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'
const CREDENTIALS = { username: 'elizabet', password: 'password123' }
const HERO_BUTTONS = ['[data-hero-back]', '[data-export-csv]', '[data-hero-refresh]']

// The layout guard needs a period with usage; the seed data has none, and jsdom
// cannot measure a real canvas. A fixed payload keeps the check independent of
// whatever the local database happens to hold.
const STATS_FIXTURE = {
  period: { from: '2026-07-22', to: '2026-10-19' },
  catalog: { total: 42, active: 30, inactive: 12 },
  top_procedures: [
    {
      procedure_id: 1,
      code: 'P-001',
      name: 'Brackets metálicos, control mensual',
      usage_count: 9,
      total_quantity: 9,
      total_revenue: 900
    },
    {
      procedure_id: 2,
      code: 'P-002',
      name: 'Extracción quirúrgica de tercer molar',
      usage_count: 4,
      total_quantity: 4,
      total_revenue: 800
    },
    {
      procedure_id: 3,
      code: 'P-003',
      name: 'Limpieza dental profunda con ultrasonido',
      usage_count: 2,
      total_quantity: 2,
      total_revenue: 300
    }
  ],
  by_specialty: [
    {
      specialty_id: 1,
      specialty_code: 'ODON',
      specialty_name: 'Odontología general',
      usage_count: 9,
      total_revenue: 1200.5
    },
    {
      specialty_id: 2,
      specialty_code: 'ORT',
      specialty_name: 'Ortodoncia',
      usage_count: 4,
      total_revenue: 800
    },
    {
      specialty_id: null,
      specialty_code: null,
      specialty_name: null,
      usage_count: 2,
      total_revenue: 100
    }
  ]
}

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

test.use({ viewport: { width: 390, height: 844 } })

test.beforeEach(async ({ page }) => {
  await page.addInitScript(state => {
    localStorage.setItem('auth_token', state.token)
    localStorage.setItem('user', JSON.stringify(state.user))
  }, authState)
  await page.route('**/api/admin/procedure-stats*', route =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: STATS_FIXTURE })
    })
  )
})

// Reads the canvas pixels directly: jsdom reports clientWidth/scrollWidth as 0
// and a DOM assertion cannot tell a drawn axis label from one the browser cut.
const readCanvas = locator =>
  locator.evaluate(canvas => {
    const context = canvas.getContext('2d')
    const { width, height } = canvas
    const { data } = context.getImageData(0, 0, width, height)
    let painted = 0
    let paintedAtLeftEdge = 0
    for (let y = 0; y < height; y += 1) {
      for (let x = 0; x < width; x += 1) {
        if (data[(y * width + x) * 4 + 3] === 0) {
          continue
        }
        painted += 1
        if (x < 2) {
          paintedAtLeftEdge += 1
        }
      }
    }
    return { width, height, painted, paintedAtLeftEdge }
  })

test('renders the 390px layout without clipped axis labels or hero buttons', async ({ page }) => {
  await page.goto('/procedure-stats')
  await expect(page.locator('[data-procedure-stats-hero]')).toBeVisible()

  const usageCanvas = page.locator('canvas[data-chart="usage"]')
  const specialtyCanvas = page.locator('canvas[data-chart="specialty"]')
  await expect(usageCanvas).toBeVisible()
  await expect(specialtyCanvas).toBeVisible()
  await expect(page.locator('[data-top-procedures]')).toBeVisible()

  for (const selector of HERO_BUTTONS) {
    const button = page.locator(selector)
    await expect(button).toBeVisible()
    const metrics = await button.evaluate(element => ({
      clientWidth: element.clientWidth,
      scrollWidth: element.scrollWidth
    }))
    expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 1)
  }

  // A horizontal bar chart draws every tick label left of the plot area, so a
  // label that does not fit is cut by the canvas edge: clipping shows up as
  // painted pixels in the leftmost columns.
  const usage = await readCanvas(usageCanvas)
  expect(usage.width).toBeGreaterThan(0)
  expect(usage.painted).toBeGreaterThan(0)
  expect(usage.paintedAtLeftEdge).toBe(0)

  const specialty = await readCanvas(specialtyCanvas)
  expect(specialty.width).toBeGreaterThan(0)
  expect(specialty.painted).toBeGreaterThan(0)
})
