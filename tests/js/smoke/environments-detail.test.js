import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const { getMock } = vi.hoisted(() => ({
  getMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
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
import Page from '../../../resources/js/modules/environments/EnvironmentDetailPage.vue'

const detailPayload = {
  id: 1,
  code: 'AMB-01',
  name: 'Consultorio Norte',
  description: 'Box 1',
  equipment: 'Unidad dental',
  status: 'maintenance',
  is_active: true
}

const auditEntries = [
  {
    id: 11,
    action: 'dental_chair_created',
    user: { name: 'Ana Torres' },
    created_at: '2026-10-01T09:00:00Z',
    old_values: null,
    new_values: null
  },
  {
    id: 12,
    action: 'dental_chair_updated',
    user: { name: 'Ana Torres' },
    created_at: '2026-10-02T10:00:00Z',
    old_values: { name: 'Consultorio Viejo' },
    new_values: { name: 'Consultorio Norte' }
  },
  {
    id: 13,
    action: 'dental_chair_deleted',
    user: null,
    created_at: '2026-10-03T11:00:00Z',
    old_values: null,
    new_values: null
  }
]

const apiRoutes = {
  detail: '/api/dental-chairs/1',
  audit: '/api/audit-logs/dental-chair/1'
}

const mockApi = handler => {
  getMock.mockImplementation(url => handler(url))
}

const mountDetail = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/environments/:id', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/environments/1')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const openAuditTab = async wrapper => {
  const tab = wrapper.find('#tab-audit')
  expect(tab.exists(), 'audit tab button must exist').toBe(true)
  await tab.trigger('click')
  await flushPromises()
}

const detailPageSource = () =>
  readFileSync(
    resolve(process.cwd(), 'resources/js/modules/environments/EnvironmentDetailPage.vue'),
    'utf8'
  )

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  mockApi(url => {
    if (url === apiRoutes.detail) return Promise.resolve({ data: detailPayload })
    return Promise.resolve({ data: [] })
  })
})

describe('environment detail rendering (T4)', () => {
  it('renders code, status badge and equipment from the detail payload', async () => {
    const wrapper = await mountDetail()

    expect(wrapper.text()).toContain('AMB-01')
    expect(wrapper.text()).toContain('Unidad dental')

    const statusBadge = wrapper.find('[role="status"][data-variant="warning"]')
    expect(statusBadge.exists(), 'status must render through the shared vocabulary badge').toBe(
      true
    )
    expect(statusBadge.text()).toContain('Mantenimiento')
    wrapper.unmount()
  })

  it('renders the Informacion / Auditoria tab labels from the tab bindings', async () => {
    const wrapper = await mountDetail()
    const labels = wrapper.findAll('[role="tab"]').map(tab => tab.text().trim())
    expect(labels).toEqual(['Información', 'Auditoría'])
    wrapper.unmount()
  })
})

describe('environment detail error states (T4)', () => {
  it('renders the detail error state and refetches on Reintentar', async () => {
    let detailCalls = 0
    mockApi(url => {
      if (url === apiRoutes.detail) {
        detailCalls += 1
        return detailCalls === 1
          ? Promise.reject(new Error('network down'))
          : Promise.resolve({ data: detailPayload })
      }
      return Promise.resolve({ data: [] })
    })
    const wrapper = await mountDetail()

    expect(wrapper.find('[data-environment-detail-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No pudimos cargar el ambiente')
    expect(wrapper.text()).not.toContain('AMB-01')

    const retry = wrapper.find('[data-environment-detail-error] button')
    expect(retry.text()).toBe('Reintentar')
    await retry.trigger('click')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe(apiRoutes.detail)
    expect(wrapper.find('[data-environment-detail-error]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Consultorio Norte')
    expect(wrapper.find('[role="banner"] h1').text()).toBe('Consultorio Norte')
    wrapper.unmount()
  })

  it('renders its own error state and retries when the audit fetch fails', async () => {
    let auditCalls = 0
    mockApi(url => {
      if (url === apiRoutes.detail) return Promise.resolve({ data: detailPayload })
      if (url === apiRoutes.audit) {
        auditCalls += 1
        return auditCalls === 1
          ? Promise.reject(new Error('audit down'))
          : Promise.resolve({ data: [] })
      }
      return Promise.resolve({ data: [] })
    })
    const wrapper = await mountDetail()
    await openAuditTab(wrapper)

    expect(wrapper.find('[data-environment-audit-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No pudimos cargar el historial de auditoría')
    expect(wrapper.find('[data-environment-audit-empty]').exists()).toBe(false)

    const retry = wrapper.find('[data-environment-audit-error] button')
    expect(retry.text()).toBe('Reintentar')
    await retry.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-environment-audit-error]').exists()).toBe(false)
    expect(auditCalls).toBe(2)
    wrapper.unmount()
  })
})

describe('environment detail states (T4)', () => {
  it('shows the detail skeleton while the fetch is in flight and no bare Cargando title', async () => {
    let resolveDetail
    mockApi(url => {
      if (url === apiRoutes.detail) {
        return new Promise(resolve => {
          resolveDetail = resolve
        })
      }
      return Promise.resolve({ data: [] })
    })
    const wrapper = await mountDetail()

    expect(wrapper.find('[data-environment-detail-loading]').exists()).toBe(true)
    expect(wrapper.find('[role="banner"] h1').text()).toBe('Detalle del ambiente')

    resolveDetail({ data: detailPayload })
    await flushPromises()

    expect(wrapper.find('[data-environment-detail-loading]').exists()).toBe(false)
    expect(wrapper.find('[role="banner"] h1').text()).toBe('Consultorio Norte')
    wrapper.unmount()
  })

  it('shows the audit skeleton while loading and the contextual empty state after', async () => {
    let resolveAudit
    mockApi(url => {
      if (url === apiRoutes.detail) return Promise.resolve({ data: detailPayload })
      if (url === apiRoutes.audit) {
        return new Promise(resolve => {
          resolveAudit = resolve
        })
      }
      return Promise.resolve({ data: [] })
    })
    const wrapper = await mountDetail()
    await openAuditTab(wrapper)

    expect(wrapper.find('[data-environment-audit-loading]').exists()).toBe(true)

    resolveAudit({ data: [] })
    await flushPromises()

    expect(wrapper.find('[data-environment-audit-loading]').exists()).toBe(false)
    expect(wrapper.find('[data-environment-audit-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No hay historial de auditoría')
    wrapper.unmount()
  })
})

describe('environment detail audit entries (T4)', () => {
  it('renders audit entries with action badge variants and the old/new diff', async () => {
    mockApi(url => {
      if (url === apiRoutes.detail) return Promise.resolve({ data: detailPayload })
      if (url === apiRoutes.audit) return Promise.resolve({ data: auditEntries })
      return Promise.resolve({ data: [] })
    })
    const wrapper = await mountDetail()
    await openAuditTab(wrapper)

    const badgeFor = label =>
      wrapper.findAll('[data-variant]').find(candidate => candidate.text().trim() === label)

    expect(badgeFor('Ambiente Creado').attributes('data-variant')).toBe('success')
    expect(badgeFor('Ambiente Actualizado').attributes('data-variant')).toBe('warning')
    expect(badgeFor('Ambiente Eliminado').attributes('data-variant')).toBe('error')

    expect(wrapper.text()).toContain('por Ana Torres')
    expect(wrapper.text()).toContain('por Sistema')
    expect(wrapper.text()).toContain('Consultorio Viejo')
    expect(wrapper.text()).toContain('Consultorio Norte')
    wrapper.unmount()
  })
})

describe('environment detail chrome (T4, structural)', () => {
  it('consumes the shared status vocabulary and drops the ad-hoc spinner and inline SVGs', () => {
    const source = detailPageSource()
    expect(source).toContain('<script setup>')
    expect(source).toContain("from './environmentStatus'")
    expect(source).not.toMatch(/const getStatusText\s*=/)
    expect(source).not.toMatch(/const getStatusVariant\s*=/)
    expect(source).toContain('@heroicons/vue/24/outline')
    expect(source).not.toContain('animate-spin')
    expect(source).not.toContain('<svg')
    expect(source).not.toContain('border-primary-')
    expect(source).not.toContain('\u2014')
  })
})
