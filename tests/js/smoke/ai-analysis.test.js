import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL as NodeURL } from 'node:url'

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
import Page from '../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue'
import AiAnalysisModal from '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'
import { useToast, globalToasts } from '../../../resources/js/composables/useToast'

const STATS = {
  total_analyses: 12,
  completed_analyses: 9,
  reviewed_analyses: 4,
  pending_review: 5,
  completion_rate: 75,
  review_rate: 44.44
}

const row = (overrides = {}) => ({
  id: 1,
  patient: { id: 7, full_name: 'Ana Torres' },
  clinical_attachment: {
    id: 3,
    file_path: 'clinical-attachments/rx.jpg',
    original_name: 'rx.jpg',
    category: 'radiografia'
  },
  status: 'completed',
  reviewed: false,
  confidence_score: 82.5,
  created_at: '2026-10-01T10:00:00.000000Z',
  findings: [],
  recommendations: [],
  ...overrides
})

const listResponse = (items = [row()], meta = {}) => ({
  data: items,
  meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length, ...meta }
})

const requestedUrls = () => getMock.mock.calls.map(([url]) => String(url))

const errorToasts = () => globalToasts.value.filter(toast => toast.type === 'error')

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/ai-analysis')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  useToast().clearAll()
  getMock.mockReset()
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/ai-analysis/stats')) {
      return { data: STATS }
    }
    return listResponse()
  })
})

describe('ai-analysis page shell', () => {
  it('renders the hero band, the KPI strip and the filter bar', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-ai-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toBe('Análisis con IA')
    expect(wrapper.find('[data-hero-title]').element.tagName).toBe('H2')
    expect(wrapper.find('[data-hero-eyebrow]').text()).toBe('Inteligencia artificial')
    expect(wrapper.find('[data-hero-refresh]').exists()).toBe(true)

    const cards = wrapper.findAll('[data-kpi-card]')
    expect(cards).toHaveLength(4)
    expect(wrapper.find('[data-kpi-value="total_analyses"]').text()).toBe('12')
    expect(wrapper.find('[data-kpi-value="completed_analyses"]').text()).toBe('9')
    expect(wrapper.find('[data-kpi-value="pending_review"]').text()).toBe('5')
    expect(wrapper.find('[data-kpi-value="completion_rate"]').text()).toBe('75%')
    expect(wrapper.find('[data-kpi-caption="completed_analyses"]').text()).toContain('4 revisados')

    expect(wrapper.find('[data-ai-filters]').exists()).toBe(true)
    expect(wrapper.findAll('[data-preset]')).toHaveLength(4)
    expect(wrapper.find('[data-filters-search]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('asks the server for the first page with the default per_page', async () => {
    const wrapper = await mountPage()

    const urls = requestedUrls()
    expect(urls.some(url => url.includes('/api/ai-analysis?') && url.includes('page=1'))).toBe(true)
    expect(urls.some(url => url.includes('per_page=15'))).toBe(true)
    expect(urls.some(url => url.includes('search='))).toBe(false)

    wrapper.unmount()
  })

  it('renders rows from the server payload with the review state', async () => {
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/ai-analysis/stats')) {
        return { data: STATS }
      }
      return listResponse([
        row(),
        row({
          id: 2,
          reviewed: true,
          status: 'failed',
          patient: { id: 8, first_name: 'Beto', last_name: 'Ruiz' }
        })
      ])
    })

    const wrapper = await mountPage()

    const table = wrapper.find('[data-ai-table]')
    expect(table.exists()).toBe(true)
    expect(table.text()).toContain('Ana Torres')
    expect(table.text()).toContain('Beto Ruiz')
    expect(table.text()).toContain('Radiografía')
    expect(table.text()).toContain('Completado')
    expect(table.text()).toContain('Sin revisar')
    expect(table.text()).toContain('82.5%')
    expect(wrapper.findAll('[data-row-action="view"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-row-action="void"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-row-action="delete"]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('opens the detail surface from a row action', async () => {
    const wrapper = await mountPage()

    await wrapper.find('[data-row-action="view"]').trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('ai-analysis filters', () => {
  it('queries the pending preset with status and reviewed flags', async () => {
    const wrapper = await mountPage()
    getMock.mockClear()

    await wrapper.find('[data-preset="pending"]').trigger('click')
    await flushPromises()

    const urls = requestedUrls()
    expect(urls.some(url => url.includes('status=completed'))).toBe(true)
    expect(urls.some(url => url.includes('reviewed=false'))).toBe(true)
    expect(wrapper.find('[data-preset="pending"]').attributes('aria-pressed')).toBe('true')

    wrapper.unmount()
  })

  it('debounces the search term into the server query', async () => {
    const wrapper = await mountPage()
    getMock.mockClear()

    await wrapper.find('[data-filters-search] input').setValue('ana')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    const urls = requestedUrls()
    expect(urls.some(url => url.includes('search=ana'))).toBe(true)

    wrapper.unmount()
  })

  it('restarts pagination when the filters change', async () => {
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/ai-analysis/stats')) {
        return { data: STATS }
      }
      return listResponse([row()], { last_page: 3, total: 40 })
    })

    const wrapper = await mountPage()

    await wrapper.find('[aria-label="Página siguiente"]').trigger('click')
    await flushPromises()
    getMock.mockClear()

    await wrapper.find('[data-preset="failed"]').trigger('click')
    await flushPromises()

    expect(requestedUrls().some(url => url.includes('page=1'))).toBe(true)

    wrapper.unmount()
  })
})

describe('ai-analysis states', () => {
  it('renders the error state with a working retry', async () => {
    getMock.mockRejectedValue(new Error('network down'))

    const wrapper = await mountPage()

    const error = wrapper.find('[data-state="error"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No se pudieron cargar los análisis.')

    // One notice per context: the inline retry block is the only error surface.
    expect(errorToasts()).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Error al obtener análisis')
    expect(wrapper.text()).not.toContain('Error al obtener estadísticas')

    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/ai-analysis/stats')) {
        return { data: STATS }
      }
      return listResponse()
    })

    await wrapper.find('[data-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-state="error"]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-table]').exists()).toBe(true)
    expect(errorToasts()).toHaveLength(0)

    wrapper.unmount()
  })

  it('renders the empty state and the filtered empty state separately', async () => {
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/ai-analysis/stats')) {
        return { data: STATS }
      }
      return listResponse([])
    })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-ai-empty]').text()).toContain('Sin análisis registrados')

    await wrapper.find('[data-filters-search] input').setValue('zzz')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    const empty = wrapper.find('[data-ai-empty]')
    expect(empty.text()).toContain('Ningún análisis coincide con los filtros aplicados.')
    expect(wrapper.find('[data-clear-filters]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('ai-analysis surface hygiene', () => {
  it('keeps the page free of scoped styles, gradients and dead color aliases', () => {
    const path = fileURLToPath(
      new NodeURL('../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue', import.meta.url)
    )
    const source = readFileSync(path, 'utf8')

    expect(source).not.toContain('<style')
    expect(source).not.toContain('bg-gradient-')
    expect(source).not.toContain('text-ink-')
    expect(source).not.toContain('systemBlue')
    expect(source).not.toContain('text-[11px]')
    expect(source).not.toContain('border-theme')
  })

  it('offers annulment instead of deletion copy', () => {
    const files = [
      '../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue',
      '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'
    ]

    files.forEach(relative => {
      const source = readFileSync(fileURLToPath(new NodeURL(relative, import.meta.url)), 'utf8')

      expect(source).not.toContain('Eliminar')
      expect(source).not.toContain('data-ai-delete')
      expect(source).not.toContain('data-row-action="delete"')
    })
  })
})
