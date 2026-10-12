import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5 (D10/D11): the detail page mirrors the list page design language. useApi,
// useRoute and useRouter are the mocked boundaries; the facts block renders
// label/value rows fed by the shared vocabulary and the branch dictionary
// (GET /api/branches/active lookup). commission_rate is a finance-sensitive
// figure and must never reach the DOM, even when the payload carries it.
const { getMock, pushMock, routeState, installDetail, makeProfessional } = vi.hoisted(() => {
  const routeState = { path: '/professionals/1', params: { id: '1' } }
  const state = { professional: null, branches: [], auditLogs: [] }

  const getMock = vi.fn(async url => {
    if (typeof url === 'string' && url.startsWith('/api/audit-logs/user/')) {
      return { data: state.auditLogs }
    }
    if (typeof url === 'string' && url.startsWith('/api/users/')) {
      return { data: state.professional }
    }
    if (typeof url === 'string' && url.startsWith('/api/branches')) {
      return { data: state.branches }
    }
    return { data: [] }
  })

  const makeProfessional = (overrides = {}) => ({
    id: 1,
    name: 'Ana Torres',
    username: 'atorres',
    email: 'ana@test.com',
    phone: '999999999',
    specialty: 'orthodontics',
    role: 'odontologo',
    professional_license: '',
    branch_id: null,
    is_active: true,
    ...overrides
  })

  const installDetail = ({
    professional = makeProfessional(),
    branches = [],
    auditLogs = []
  } = {}) => {
    state.professional = professional
    state.branches = branches
    state.auditLogs = auditLogs
  }

  return { getMock, pushMock: vi.fn(), routeState, installDetail, makeProfessional }
})

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRoute: () => routeState,
    useRouter: () => ({ push: pushMock })
  }
})
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    patch: async () => ({ data: {} }),
    delete: vi.fn(async () => ({ data: {} })),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useConfirm', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useConfirm: () => ({
      confirm: vi.fn(async () => true),
      isOpen: actual.confirmIsOpen,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})
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
import Page from '../../../resources/js/modules/professionals/ProfessionalDetailPage.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async () => {
  const wrapper = mount(Page, {
    global: { plugins: [router, uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const factsText = wrapper => wrapper.find('[data-professional-facts]').text()

describe('professionals detail smoke', () => {
  beforeEach(() => {
    getMock.mockClear()
    pushMock.mockClear()
    routeState.path = '/professionals/1'
    routeState.params = { id: '1' }
    installDetail()
  })

  it('mounts ProfessionalDetailPage.vue and renders the professional name', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Ana Torres')
    wrapper.unmount()
  })

  it('renders the role from ROLE_LABELS and the specialty from SPECIALTY_LABELS', async () => {
    installDetail({
      professional: makeProfessional({ role: 'tecnico_dental', specialty: 'endodontics' })
    })
    const wrapper = await mountPage()
    const facts = factsText(wrapper)

    expect(facts).toContain('Técnico Dental')
    expect(facts).not.toContain('tecnico_dental')
    expect(facts).toContain('Endodoncia')
    expect(facts).not.toContain('endodontics')

    wrapper.unmount()
  })

  it('renders N/D for an empty professional license', async () => {
    installDetail({ professional: makeProfessional({ professional_license: '' }) })
    const wrapper = await mountPage()

    expect(factsText(wrapper)).toContain('N/D')

    wrapper.unmount()
  })

  it('maps the branch through the /api/branches/active dictionary with both fallbacks', async () => {
    installDetail({
      professional: makeProfessional({ branch_id: 7 }),
      branches: [{ id: 7, name: 'Sede Miraflores' }]
    })
    const withBranch = await mountPage()
    expect(factsText(withBranch)).toContain('Sede Miraflores')
    withBranch.unmount()

    installDetail({ professional: makeProfessional({ branch_id: null }), branches: [] })
    const withoutBranch = await mountPage()
    expect(factsText(withoutBranch)).toContain('Sin sucursal')
    withoutBranch.unmount()

    installDetail({ professional: makeProfessional({ branch_id: 99 }), branches: [] })
    const unknownBranch = await mountPage()
    expect(factsText(unknownBranch)).toContain('N/D')
    unknownBranch.unmount()
  })

  it('shows the shared empty state when the audit history is empty', async () => {
    installDetail({ auditLogs: [] })
    const wrapper = await mountPage()

    const historialTab = wrapper
      .findAll('button')
      .find(button => button.text().includes('Historial'))
    await historialTab.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-section-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No hay historial de auditoría')
    expect(wrapper.text()).toContain('Este profesional no tiene registros de auditoría.')

    expect(getMock.mock.calls.map(call => call[0])).toContain('/api/audit-logs/user/1')

    wrapper.unmount()
  })

  it('never renders commission_rate even when the payload carries it', async () => {
    installDetail({ professional: makeProfessional({ commission_rate: 0.15 }) })
    const wrapper = await mountPage()

    expect(wrapper.text()).not.toContain('0.15')
    expect(wrapper.text()).not.toContain('commission')
    expect(wrapper.text()).not.toContain('Comisión')

    wrapper.unmount()
  })
})
