import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #303 S3 / #304 S3 — sede scope of the /business-intelligence page (selector
// for group scope only) and the consolidated report with its double breakdown,
// which has no export endpoint.
const { getMock, downloadMock, authState } = vi.hoisted(() => ({
  getMock: vi.fn(),
  downloadMock: vi.fn(),
  authState: { user: null }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    download: downloadMock,
    setToken: () => {},
    normalizeError: err => err?.response?.data?.message || ''
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
vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => authState.user),
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      isLoading: computed(() => false),
      login: async () => ({}),
      logout: async () => {},
      authLogout: async () => {},
      getCurrentUser: async () => ({}),
      hasRole: role => authState.user?.role === role,
      hasAnyRole: roles => roles.includes(authState.user?.role)
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import rolePermissions from '../support/role-permissions.json'
import Page from '../../../resources/js/modules/business-intelligence/BusinessIntelligencePage.vue'

const dashboardPayload = {
  totalAppointments: 42,
  totalPatients: 27,
  newPatients: 5,
  totalRevenue: 5320.5,
  totalRefunds: 200,
  occupancyRate: 73.4,
  usedHours: 120,
  availableHours: 160,
  appointmentsByDay: [],
  revenueByMonth: [],
  professionalPerformance: []
}

const consolidatedPayload = {
  period: {
    start_date: '2026-09-20',
    end_date: '2026-10-19',
    branch_id: null,
    label: '20/09/2026 - 19/10/2026'
  },
  summary: { net: 1900.5, refunds: 200, paid_appointments: 3 },
  by_branch: [
    { branch_id: 3, branch: 'Sede Norte', net: 1600.5, refunds: 200, paid_appointments: 2 },
    { branch_id: null, branch: 'Sin sede', net: 300, refunds: 0, paid_appointments: 1 }
  ],
  by_professional: [
    {
      professional_id: 1,
      professional: 'Dra. Ana Torres',
      net: 1600.5,
      refunds: 200,
      paid_appointments: 2
    },
    {
      professional_id: null,
      professional: 'Sin cita asociada',
      net: 300,
      refunds: 0,
      paid_appointments: 1
    }
  ]
}

const branchesPayload = {
  data: [
    { id: 3, name: 'Sede Norte' },
    { id: 4, name: 'Sede Sur' }
  ]
}

const signInAs = ({ branchId } = {}) => {
  const user = {
    id: 1,
    name: 'Ana Admin',
    role: 'administrador',
    branch_id: branchId,
    permissions: rolePermissions.administrador
  }

  authState.user = user
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify(user))
}

const respondByUrl = url => {
  if (url === '/api/users/active') {
    return Promise.resolve({ data: [{ id: 1, name: 'Dra. Ana Torres' }] })
  }
  if (url === '/api/dental-chairs/active') {
    return Promise.resolve({ data: [{ id: 4, name: 'Sillón 1' }] })
  }
  if (url === '/api/branches/active') {
    return Promise.resolve(branchesPayload)
  }
  if (url === '/api/reports/dashboard') {
    return Promise.resolve({ data: dashboardPayload })
  }
  if (url === '/api/reports/consolidated') {
    return Promise.resolve({ data: consolidatedPayload })
  }
  return Promise.resolve({ data: [] })
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/business-intelligence')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return wrapper
}

const selectOption = async (wrapper, containerSelector, label) => {
  const select = wrapper.find(containerSelector)
  await select.find('button').trigger('click')
  await flushPromises()
  const option = select.findAll('button').find(candidate => candidate.text() === label)
  expect(option).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

const lastReportCall = () =>
  getMock.mock.calls.filter(([url]) => String(url).startsWith('/api/reports/')).at(-1)

describe('business intelligence sede scope (#303 S3)', () => {
  beforeEach(() => {
    signInAs({ branchId: null })
    getMock.mockReset()
    getMock.mockImplementation(respondByUrl)
    downloadMock.mockReset()
    downloadMock.mockResolvedValue({ blob: {}, filename: 'report.csv' })
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
    localStorage.clear()
  })

  it('lets group scope pick a sede and applies it only when the filters are applied', async () => {
    const wrapper = await mountPage()

    const branchFilter = wrapper.find('[data-filters-branch]')
    expect(branchFilter.exists()).toBe(true)
    expect(branchFilter.text()).toContain('Todas las sedes')

    const beforeDraft = lastReportCall()
    await selectOption(wrapper, '[data-filters-branch]', 'Sede Norte')

    expect(lastReportCall()).toBe(beforeDraft)
    expect('branch_id' in lastReportCall()[1].params).toBe(false)

    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    expect(lastReportCall()[0]).toBe('/api/reports/dashboard')
    expect(lastReportCall()[1].params.branch_id).toBe(3)
    expect(wrapper.find('[data-branch-applied]').text()).toContain('Sede Norte')

    await wrapper.find('[data-export-csv]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenCalledTimes(1)
    expect(downloadMock.mock.calls[0][0]).toBe('/api/reports/dashboard/export')
    expect(downloadMock.mock.calls[0][1].params.branch_id).toBe(3)

    wrapper.unmount()
  })

  it('drops the sede filter when every sede is chosen or the filters are cleared', async () => {
    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-branch]', 'Sede Sur')
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()
    expect(lastReportCall()[1].params.branch_id).toBe(4)

    await selectOption(wrapper, '[data-filters-branch]', 'Todas las sedes')
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()
    expect('branch_id' in lastReportCall()[1].params).toBe(false)
    expect(wrapper.find('[data-branch-applied]').exists()).toBe(false)

    await selectOption(wrapper, '[data-filters-branch]', 'Sede Norte')
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()
    expect(lastReportCall()[1].params.branch_id).toBe(3)

    await wrapper.find('[data-filters-reset]').trigger('click')
    await flushPromises()
    expect('branch_id' in lastReportCall()[1].params).toBe(false)
    expect(wrapper.find('[data-filters-branch]').text()).toContain('Todas las sedes')
    expect(wrapper.find('[data-branch-applied]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('hides the sede selector from a user assigned to a sede and never sends branch_id', async () => {
    signInAs({ branchId: 3 })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-filters-branch]').exists()).toBe(false)
    expect(getMock.mock.calls.map(([url]) => url)).not.toContain('/api/branches/active')
    expect('branch_id' in lastReportCall()[1].params).toBe(false)

    await wrapper.find('[data-export-pdf]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenCalledTimes(1)
    expect('branch_id' in downloadMock.mock.calls[0][1].params).toBe(false)

    wrapper.unmount()
  })

  it('offers the sede cut right after the appointment-type cut in the revenue report', async () => {
    getMock.mockImplementation(url => {
      if (url === '/api/reports/revenue') {
        return Promise.resolve({
          data: [
            {
              type: 'Por Tipo de Cita',
              category: 'Limpieza dental',
              appointments_count: 2,
              total_revenue: 1200.5,
              refunds: 150,
              average_per_appointment: 600.25
            },
            {
              type: 'Por Sede',
              category: 'Sede Norte',
              appointments_count: 2,
              total_revenue: 1200.5,
              refunds: 150,
              average_per_appointment: 600.25
            },
            {
              type: 'Por Profesional',
              category: 'Dra. Ana Torres',
              appointments_count: 2,
              total_revenue: 1200.5,
              refunds: 150,
              average_per_appointment: 600.25
            }
          ],
          columns: [
            { key: 'type', label: 'Tipo' },
            { key: 'category', label: 'Categoría' },
            { key: 'total_revenue', label: 'Ingresos netos (S/)' },
            { key: 'refunds', label: 'Devoluciones (S/)' }
          ]
        })
      }
      return respondByUrl(url)
    })

    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Ingresos')

    const dimensions = wrapper
      .findAll('[data-revenue-dimension]')
      .map(button => button.attributes('data-revenue-dimension'))
    expect(dimensions).toEqual(['Por Tipo de Cita', 'Por Sede', 'Por Profesional'])

    await wrapper.find('[data-revenue-dimension="Por Sede"]').trigger('click')
    await flushPromises()

    const rows = wrapper.findAll('[data-report-table] tbody tr')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('Sede Norte')

    wrapper.unmount()
  })
})

describe('business intelligence consolidated report (#304 S3)', () => {
  beforeEach(() => {
    signInAs({ branchId: null })
    getMock.mockReset()
    getMock.mockImplementation(respondByUrl)
    downloadMock.mockReset()
    downloadMock.mockResolvedValue({ blob: {}, filename: 'report.csv' })
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
    localStorage.clear()
  })

  it('renders the summary and both cuts, and disables the export', async () => {
    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Consolidado por Sede')

    expect(lastReportCall()[0]).toBe('/api/reports/consolidated')
    expect(lastReportCall()[1].params.start_date).toBe('2026-09-20')
    expect(lastReportCall()[1].params.end_date).toBe('2026-10-19')

    expect(wrapper.find('[data-consolidated-summary="net"]').text()).toMatch(/S\/\s1,900\.50/)
    expect(wrapper.find('[data-consolidated-summary="refunds"]').text()).toMatch(/S\/\s200\.00/)
    expect(wrapper.find('[data-consolidated-summary="paid-appointments"]').text()).toBe('3')

    const byBranch = wrapper.find('[data-consolidated-branch-table]')
    expect(byBranch.text()).toContain('Sede Norte')
    expect(byBranch.text()).toContain('Sin sede')

    const byProfessional = wrapper.find('[data-consolidated-professional-table]')
    expect(byProfessional.text()).toContain('Dra. Ana Torres')
    expect(byProfessional.text()).toContain('Sin cita asociada')

    expect(wrapper.find('[data-report-table]').exists()).toBe(false)
    expect(wrapper.find('[data-export-unavailable]').text()).toContain('no se exporta')

    for (const format of ['excel', 'csv', 'pdf']) {
      const button = wrapper.find(`[data-export-${format}]`)
      expect(button.attributes('disabled')).toBeDefined()
      expect(button.attributes('title')).toContain('no se exporta')
    }

    await wrapper.find('[data-export-csv]').trigger('click')
    await flushPromises()
    expect(downloadMock).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('narrows the consolidated report to the applied sede', async () => {
    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Consolidado por Sede')
    await selectOption(wrapper, '[data-filters-branch]', 'Sede Sur')
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    expect(lastReportCall()[0]).toBe('/api/reports/consolidated')
    expect(lastReportCall()[1].params.branch_id).toBe(4)

    wrapper.unmount()
  })

  it('shows the shared empty state when the consolidated period has no money', async () => {
    getMock.mockImplementation(url => {
      if (url === '/api/reports/consolidated') {
        return Promise.resolve({
          data: {
            ...consolidatedPayload,
            summary: { net: 0, refunds: 0, paid_appointments: 0 },
            by_branch: [],
            by_professional: []
          }
        })
      }
      return respondByUrl(url)
    })

    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Consolidado por Sede')

    expect(wrapper.find('[data-report-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sin datos en el período')
    expect(wrapper.find('[data-consolidated]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('restores the export when leaving the consolidated report', async () => {
    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Consolidado por Sede')
    expect(wrapper.find('[data-export-csv]').attributes('disabled')).toBeDefined()

    await selectOption(wrapper, '[data-filters-report]', 'Dashboard General')

    expect(wrapper.find('[data-export-csv]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-export-unavailable]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="appointments"]').text()).toBe('42')

    wrapper.unmount()
  })
})
