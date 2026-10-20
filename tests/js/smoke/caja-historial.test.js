import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
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
    echo: { leave: () => {} },
    channel: () => {
      const channel = { listen: () => channel }
      return channel
    },
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import UiSelect from '../../../resources/js/components/ui/Select.vue'
import { formatPENLabel } from '../../../resources/js/composables/useFormatters'
import SessionList from '../../../resources/js/modules/cash-register/components/SessionList.vue'

const COMPONENT_PATH = 'resources/js/modules/cash-register/components/SessionList.vue'

const sessions = [
  {
    id: 12,
    status: 'open',
    opened_at: '2026-10-01T09:00:00-05:00',
    closed_at: null,
    opening_amount: 150,
    closing_amount: null,
    difference_amount: null,
    user: { id: 5, name: 'Ana Torres' },
    branch: { id: 2, name: 'Sucursal Centro' }
  },
  {
    id: 11,
    status: 'closed',
    opened_at: '2026-09-30T08:30:00-05:00',
    closed_at: '2026-09-30T18:00:00-05:00',
    opening_amount: 100,
    closing_amount: 140.5,
    difference_amount: 2.5,
    user: { id: 6, name: 'Luis Ramos' },
    branch: { id: 2, name: 'Sucursal Centro' }
  }
]

const users = [
  { id: 5, name: 'Ana Torres' },
  { id: 6, name: 'Luis Ramos' }
]

const defaultPagination = {
  current_page: 1,
  last_page: 3,
  per_page: 15,
  total: 40
}

const mountList = async (props = {}) => {
  const wrapper = mount(SessionList, {
    props: {
      sessions,
      loading: false,
      pagination: defaultPagination,
      ...props
    },
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const selectWithOption = (wrapper, value) =>
  wrapper
    .findAllComponents(UiSelect)
    .find(select =>
      (select.props('options') || []).some(option => String(option.value) === String(value))
    )

const chooseOption = async (wrapper, optionValue, optionLabel) => {
  const select = selectWithOption(wrapper, optionValue)
  expect(select).toBeTruthy()
  await select.find('button').trigger('click')
  const option = select.findAll('button').find(button => button.text().trim() === optionLabel)
  expect(option).toBeTruthy()
  await option.trigger('click')
}

const lastRefresh = wrapper => wrapper.emitted('refresh').at(-1)[0]

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiGet.mockImplementation(async url => {
    if (url === '/api/users') return { data: users }
    return { data: [] }
  })
})

describe('caja historial', () => {
  it('builds the history on the design-system primitives', () => {
    const source = readFileSync(COMPONENT_PATH, 'utf8')

    expect(source).toContain('UiDataTable')
    expect(source).toContain('UiFilterBar')
    expect(source).toContain('UiPagination')
    expect(source).toContain('formatPENLabel')
    expect(source).not.toContain('<table')
    expect(source).not.toContain('<select')
    expect(source).not.toContain('<style')
    expect(source).not.toContain('Reabrir')
    expect(source).not.toContain('manageCashRegister')
    expect(source).not.toContain('exportToExcel')
    expect(source).not.toContain('exportToPDF')
  })

  it('renders one row per session in the sessions prop', async () => {
    const wrapper = await mountList()

    const rows = wrapper.findAll('[data-session-table] tbody tr')
    expect(rows).toHaveLength(sessions.length)

    const text = wrapper.text()
    expect(text).toContain('#12')
    expect(text).toContain('Ana Torres')
    expect(text).toContain('Luis Ramos')
    expect(text).toContain('Sucursal Centro')
    expect(text).toContain(formatPENLabel(150))
    expect(text).toContain(formatPENLabel(140.5))
    expect(text).toContain('Abierta')
    expect(text).toContain('Cerrada')
    expect(text).not.toContain('Reabrir')

    wrapper.unmount()
  })

  it('renders the empty state when there are no sessions', async () => {
    const wrapper = await mountList({ sessions: [], pagination: null })

    expect(wrapper.text()).toContain('Sin sesiones registradas')
    expect(wrapper.find('[data-session-pagination]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('emits refresh with the status filter and resets the page', async () => {
    const wrapper = await mountList()

    await chooseOption(wrapper, 'closed', 'Cerrada')

    expect(lastRefresh(wrapper)).toEqual({ status: 'closed', page: 1 })

    wrapper.unmount()
  })

  it('emits refresh with the whole date range', async () => {
    const wrapper = await mountList()

    const dateInputs = wrapper.findAll('input[type="date"]')
    expect(dateInputs).toHaveLength(2)
    await dateInputs[0].setValue('2026-10-01')
    await dateInputs[1].setValue('2026-10-31')

    expect(lastRefresh(wrapper)).toEqual({
      date_from: '2026-10-01',
      date_to: '2026-10-31',
      page: 1
    })

    wrapper.unmount()
  })

  it('keeps the active filters when the page changes', async () => {
    const wrapper = await mountList()

    await chooseOption(wrapper, 'closed', 'Cerrada')
    await wrapper.findAll('input[type="date"]')[0].setValue('2026-10-01')
    await wrapper.find('[aria-label="Ir a la página 2"]').trigger('click')

    expect(lastRefresh(wrapper)).toEqual({
      status: 'closed',
      date_from: '2026-10-01',
      page: 2
    })

    wrapper.unmount()
  })

  it('populates the user filter from /api/users', async () => {
    const wrapper = await mountList()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/users')

    await chooseOption(wrapper, '5', 'Ana Torres')

    expect(lastRefresh(wrapper)).toEqual({ user_id: '5', page: 1 })

    wrapper.unmount()
  })

  it('hides the user filter when the user list cannot be loaded', async () => {
    harness.apiGet.mockImplementation(async () => {
      throw new Error('forbidden')
    })
    const wrapper = await mountList()

    expect(selectWithOption(wrapper, '5')).toBeUndefined()
    expect(wrapper.find('[data-session-filters]').text()).not.toContain('Usuario')

    wrapper.unmount()
  })
})
