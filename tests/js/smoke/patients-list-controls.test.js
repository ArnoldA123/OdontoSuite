import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'

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
import Page from '../../../resources/js/modules/patients/PatientsPage.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com',
  birth_date: '1990-04-12',
  age: 36,
  is_active: true
}

const branches = [
  { id: 3, name: 'Sede Lima' },
  { id: 4, name: 'Sede Arequipa' }
]

const defaultMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 1,
  active_count: 1,
  inactive_count: 0
}

const listCalls = () =>
  harness.apiGet.mock.calls.map(([url]) => url).filter(url => url.includes('/api/patients?'))

const listParams = url => new URLSearchParams(url.split('?')[1])

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const searchInput = wrapper =>
  wrapper.find('input[placeholder="Buscar por nombre, DNI, teléfono o email..."]')

const selectByOptionValue = (wrapper, value) =>
  wrapper
    .findAllComponents(UiSelect)
    .find(select =>
      (select.props('options') || []).some(option => String(option.value) === String(value))
    )

const chooseOption = async (wrapper, optionValue, label) => {
  const select = selectByOptionValue(wrapper, optionValue)
  expect(select).toBeTruthy()
  await select.find('button').trigger('click')
  const option = select.findAll('button').find(button => button.text().trim() === label)
  expect(option).toBeTruthy()
  await option.trigger('click')
}

describe('patients list controls', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: branches }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    localStorage.clear()
  })

  it('debounces the search input: one reload after the timer with the search param', async () => {
    const wrapper = await mountPage()
    const baseline = listCalls().length
    expect(baseline).toBe(1)

    vi.useFakeTimers()
    const input = searchInput(wrapper)
    expect(input.exists()).toBe(true)

    await input.setValue('a')
    await input.setValue('an')
    await input.setValue('ana')
    expect(listCalls()).toHaveLength(baseline)

    vi.advanceTimersByTime(299)
    expect(listCalls()).toHaveLength(baseline)

    vi.advanceTimersByTime(1)
    expect(listCalls()).toHaveLength(baseline + 1)
    expect(listParams(listCalls().at(-1)).get('search')).toBe('ana')
    expect(listParams(listCalls().at(-1)).get('page')).toBe('1')

    vi.useRealTimers()
    await flushPromises()
    wrapper.unmount()
  })

  it('sends per_page, branch_id, sort and direction with the list request', async () => {
    const wrapper = await mountPage()
    harness.apiGet.mockClear()

    await chooseOption(wrapper, '3', 'Sede Lima')
    await chooseOption(wrapper, '30', '30 por página')
    await chooseOption(wrapper, 'created_at-desc', 'Más recientes')
    await flushPromises()

    expect(listCalls()).toHaveLength(3)

    const branchParams = listParams(listCalls()[0])
    expect(branchParams.get('branch_id')).toBe('3')
    expect(branchParams.get('sort')).toBe('last_name')
    expect(branchParams.get('direction')).toBe('asc')

    const params = listParams(listCalls().at(-1))
    expect(params.get('branch_id')).toBe('3')
    expect(params.get('per_page')).toBe('30')
    expect(params.get('sort')).toBe('created_at')
    expect(params.get('direction')).toBe('desc')
    expect(params.get('page')).toBe('1')

    wrapper.unmount()
  })

  it('keeps the legacy defaults on the initial request (per_page 15, last_name asc, no branch)', async () => {
    const wrapper = await mountPage()

    expect(listCalls()).toHaveLength(1)
    const params = listParams(listCalls()[0])
    expect(params.get('per_page')).toBe('15')
    expect(params.get('sort')).toBe('last_name')
    expect(params.get('direction')).toBe('asc')
    expect(params.has('branch_id')).toBe(false)
    expect(params.has('search')).toBe(false)
    expect(params.has('active')).toBe(false)

    wrapper.unmount()
  })

  it('resets pagination to page 1 when a filter changes', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: branches }
      }
      return {
        data: [patient],
        meta: {
          current_page: 1,
          last_page: 2,
          per_page: 15,
          total: 30,
          active_count: 30,
          inactive_count: 0
        }
      }
    })

    const wrapper = await mountPage()

    const pageTwo = wrapper.find('button[aria-label="Ir a la página 2"]')
    expect(pageTwo.exists()).toBe(true)
    await pageTwo.trigger('click')
    await flushPromises()
    expect(listParams(listCalls().at(-1)).get('page')).toBe('2')

    await chooseOption(wrapper, 'first_name-asc', 'Nombre A-Z')
    await flushPromises()
    expect(listParams(listCalls().at(-1)).get('page')).toBe('1')
    expect(listParams(listCalls().at(-1)).get('sort')).toBe('first_name')

    wrapper.unmount()
  })

  it('hides the branch filter when branches cannot be loaded', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        throw new Error('forbidden')
      }
      return { data: [patient], meta: defaultMeta }
    })

    const wrapper = await mountPage()

    expect(selectByOptionValue(wrapper, '3')).toBeUndefined()
    expect(wrapper.text()).not.toContain('Sede Lima')
    expect(listCalls()).toHaveLength(1)

    wrapper.unmount()
  })
})

// #401 — the filters live in the URL too: the browser's back button returns to
// the same query, order, page size and page, and the reset control is named.
const mountAt = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push(path)
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

describe('patients list filter state (#401)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: branches }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    localStorage.clear()
    window.history.replaceState(null, '', '/')
  })

  it('restores search, status, order, page size and page from the URL', async () => {
    const wrapper = await mountAt(
      '/patients?search=ANA&active=true&sort=created_at&direction=desc&per_page=30&page=2'
    )

    const params = listParams(listCalls().at(-1))
    expect(params.get('search')).toBe('ANA')
    expect(params.get('active')).toBe('true')
    expect(params.get('sort')).toBe('created_at')
    expect(params.get('direction')).toBe('desc')
    expect(params.get('per_page')).toBe('30')
    expect(params.get('page')).toBe('2')

    expect(searchInput(wrapper).element.value).toBe('ANA')

    wrapper.unmount()
  })

  it('writes the applied filters into the route query', async () => {
    const wrapper = await mountAt('/patients')
    const router = wrapper.vm.$router

    vi.useFakeTimers()
    await searchInput(wrapper).setValue('AUDIT-U4')
    vi.advanceTimersByTime(300)
    vi.useRealTimers()
    await flushPromises()
    await chooseOption(wrapper, 'active', 'Activos')
    await flushPromises()

    expect(router.currentRoute.value.query.search).toBe('AUDIT-U4')
    expect(router.currentRoute.value.query.active).toBe('true')

    wrapper.unmount()
  })

  it('clears the query when the reset control runs', async () => {
    const wrapper = await mountAt('/patients?search=ANA&active=true&page=3')
    const router = wrapper.vm.$router

    await wrapper.get('[data-reset-filters]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.search).toBeUndefined()
    expect(router.currentRoute.value.query.active).toBeUndefined()
    expect(router.currentRoute.value.query.page).toBeUndefined()

    wrapper.unmount()
  })

  it('opts the route out of the query remount so the search keeps its focus', () => {
    const app = readFileSync('resources/js/app.js', 'utf8')

    expect(app).toContain('$route.meta.preserveOnQuery ? $route.path : $route.fullPath')
    expect(app).toContain('meta: { preserveOnQuery: true }')
  })

  it('mounts the filter bar primitive around the search and the toolbar', async () => {
    const wrapper = await mountPage()

    const bar = wrapper.get('[data-patients-filters]')

    expect(bar.attributes('role')).toBe('search')
    expect(searchInput(wrapper).exists()).toBe(true)
    expect(bar.find('[data-reset-filters]').exists()).toBe(true)
    expect(bar.findAllComponents(UiSelect).length).toBeGreaterThanOrEqual(3)

    wrapper.unmount()
  })

  it('names the reset control for assistive technology', async () => {
    const wrapper = await mountAt('/patients')

    const reset = wrapper.get('[data-reset-filters]')
    expect(reset.attributes('aria-label')).toBe('Limpiar filtros')
    expect(reset.attributes('title')).toBe('Limpiar filtros')

    wrapper.unmount()
  })
})
