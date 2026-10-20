import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// procedure-stats-ui-redesign / T2 — behaviour of the redesigned
// /procedure-stats surface: hero + KPI strip, period presets, guarded range,
// complete load states and the exact query the page issues.
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureStatsPage.vue'

const payload = {
  period: { from: '2026-07-22', to: '2026-10-19' },
  catalog: { total: 42, active: 30, inactive: 12 },
  top_procedures: [
    {
      procedure_id: 7,
      code: 'P-007',
      name: 'Limpieza dental',
      usage_count: 9,
      total_quantity: 12,
      total_revenue: 1200.5
    },
    {
      procedure_id: 8,
      code: 'P-008',
      name: 'Extracción simple',
      usage_count: 4,
      total_quantity: 4,
      total_revenue: 800
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
      specialty_id: null,
      specialty_code: null,
      specialty_name: null,
      usage_count: 1,
      total_revenue: 100
    }
  ]
}

const emptyPayload = {
  ...payload,
  top_procedures: [],
  by_specialty: []
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/procedure-stats')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

const queries = () =>
  getMock.mock.calls.map(([url]) => new URLSearchParams(String(url).split('?')[1] ?? ''))

const lastQuery = () => queries().at(-1)

describe('procedure stats page (T2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockResolvedValue({ data: payload })
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the hero, the KPI strip and both data surfaces from the payload', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-procedure-stats-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toBe('Estadísticas del Catálogo')
    // One <h1> per document: the shell topbar owns it, so the hero band is a
    // section heading instead of a second document title.
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('[data-hero-title]').element.tagName).toBe('H2')
    expect(wrapper.find('[data-role-disclosure]').text()).toContain('Administrador')
    expect(wrapper.find('[data-role-disclosure]').text()).toContain('Finanzas')

    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('42')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('30')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('12')
    expect(wrapper.find('[data-kpi-value="period-usage"]').text()).toBe('10')
    expect(wrapper.find('[data-kpi-caption="period-usage"]').text()).toContain('1,300.50')

    expect(wrapper.text()).toContain('P-007')
    expect(wrapper.text()).toContain('Limpieza dental')
    expect(wrapper.text()).toContain('1,200.50')

    const specialtyRows = wrapper.findAll('[data-specialty-row]')
    expect(specialtyRows).toHaveLength(2)
    expect(wrapper.text()).toContain('Odontología general')
    expect(wrapper.text()).toContain('Sin especialidad')
    const shareBadge = row => Number(row.find('[data-specialty-share]').text().replace('%', ''))
    const barPercent = row =>
      Number(row.find('[data-specialty-bar]').attributes('data-specialty-bar-percent'))
    // The bar and the badge must express the same number: a bar normalised to
    // the largest specialty would read 100 next to a 90 share badge.
    expect(shareBadge(specialtyRows[0])).toBe(90)
    expect(barPercent(specialtyRows[0])).toBe(90)
    expect(shareBadge(specialtyRows[1])).toBe(10)
    expect(barPercent(specialtyRows[1])).toBe(10)

    wrapper.unmount()
  })

  it('never shows a 0% badge next to a drawn bar for a sub-1% share', async () => {
    getMock.mockResolvedValue({
      data: {
        ...payload,
        by_specialty: [
          {
            specialty_id: 1,
            specialty_code: 'ODON',
            specialty_name: 'Odontología general',
            usage_count: 1,
            total_revenue: 50
          },
          {
            specialty_id: 2,
            specialty_code: 'ORT',
            specialty_name: 'Ortodoncia',
            usage_count: 249,
            total_revenue: 1000
          }
        ]
      }
    })

    const { wrapper } = await mountPage()
    const rows = wrapper.findAll('[data-specialty-row]')

    // 1 of 250 uses is 0.4 %: the bar keeps its 2 % visible floor, so the
    // badge must report a sub-1 % share instead of rounding down to 0 %.
    expect(rows[0].find('[data-specialty-share]').text()).toBe('<1%')
    expect(rows[0].find('[data-specialty-bar]').attributes('data-specialty-bar-percent')).toBe('2')
    expect(rows[1].find('[data-specialty-share]').text()).toBe('100%')
    expect(rows[1].find('[data-specialty-bar]').attributes('data-specialty-bar-percent')).toBe(
      '100'
    )

    wrapper.unmount()
  })

  it('queries the default 90-day window with the default limit', async () => {
    const { wrapper } = await mountPage()

    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock.mock.calls[0][0]).toContain('/api/admin/procedure-stats?')
    expect(lastQuery().get('from')).toBe('2026-07-22')
    expect(lastQuery().get('to')).toBe('2026-10-19')
    expect(lastQuery().get('limit')).toBe('10')
    expect(wrapper.find('[data-period-applied]').text()).toContain('90 días')

    wrapper.unmount()
  })

  it('re-queries a seven-day window when the preset is clicked', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-preset="7d"]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(lastQuery().get('from')).toBe('2026-10-13')
    expect(lastQuery().get('to')).toBe('2026-10-19')
    expect(lastQuery().get('limit')).toBe('10')
    expect(wrapper.find('[data-period-applied]').text()).toContain('7 días')

    wrapper.unmount()
  })

  it('applies a changed Top N through the select', async () => {
    const { wrapper } = await mountPage()

    const limitSelect = wrapper.find('[data-filters-limit]')
    await limitSelect.find('button').trigger('click')
    await flushPromises()
    const fifty = limitSelect.findAll('button').find(option => option.text() === '50')
    expect(fifty).toBeTruthy()
    await fifty.trigger('click')
    await flushPromises()

    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(lastQuery().get('limit')).toBe('50')

    wrapper.unmount()
  })

  it('blocks an inverted range without issuing a request', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('#procedure-stats-from').setValue('2026-10-31')
    await wrapper.find('#procedure-stats-to').setValue('2026-10-01')
    await flushPromises()

    expect(wrapper.text()).toContain('La fecha final no puede ser anterior a la inicial.')
    expect(wrapper.find('[data-filters-apply]').attributes('disabled')).toBeDefined()

    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  it('renders a retryable error state instead of stale data', async () => {
    let attempt = 0
    getMock.mockImplementation(async () => {
      attempt += 1
      if (attempt === 1) {
        throw { status: 500, response: { data: { message: 'boom' } } }
      }
      return { data: payload }
    })

    const { wrapper } = await mountPage()

    const alert = wrapper.find('[data-state="error"]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No pudimos cargar las estadísticas')
    expect(alert.text()).toContain('boom')
    expect(wrapper.find('[data-kpi-card="total"]').exists()).toBe(false)
    expect(wrapper.find('[data-top-procedures]').exists()).toBe(false)

    await wrapper.find('[data-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-state="error"]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('42')
    expect(getMock).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it('renders the period empty state and widens the window to 90 days', async () => {
    getMock.mockResolvedValue({ data: emptyPayload })

    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-period-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sin uso registrado en el período')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('42')
    expect(wrapper.find('[data-top-procedures]').exists()).toBe(false)
    expect(wrapper.find('[data-specialty-distribution]').exists()).toBe(false)

    await wrapper.find('[data-widen-period]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(lastQuery().get('from')).toBe('2026-07-22')

    wrapper.unmount()
  })
})
