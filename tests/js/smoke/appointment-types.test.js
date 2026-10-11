import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Appointment-types list redesign contract: hero band, KPI strip derived from
// the SAME full catalog the table renders, client-side status + search
// filtering over a single `status=all` load, the `default_duration_minutes`
// duration fix, the removed View modal (Ver Detalle navigates instead), the
// inline error surface with retry, and a coherent color pair in both modals.
const { getMock, makeType } = vi.hoisted(() => {
  const makeType = (id, overrides = {}) => ({
    id,
    name: `Tipo ${id}`,
    description: `Descripción del tipo ${id}`,
    default_duration_minutes: 30,
    price: 100,
    color: 'steelblue',
    is_active: true,
    ...overrides
  })

  const getMock = vi.fn(async () => ({ data: [] }))

  return { getMock, makeType }
})

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
import Page from '../../../resources/js/modules/appointment-types/AppointmentTypesPage.vue'
import Modal from '../../../resources/js/components/ui/Modal.vue'
import Select from '../../../resources/js/components/ui/Select.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const CATALOG = [
  makeType(1, {
    name: 'Limpieza dental',
    description: 'Profilaxis dental completa',
    default_duration_minutes: 30,
    is_active: true
  }),
  makeType(2, {
    name: 'Ortodoncia',
    description: 'Control mensual de brackets',
    default_duration_minutes: 60,
    is_active: true
  }),
  makeType(3, {
    name: 'Blanqueamiento',
    description: 'Blanqueamiento ambulatorio',
    default_duration_minutes: 45,
    is_active: false
  })
]

const mountPage = async (catalog = []) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/appointment-types')) {
      return { data: catalog }
    }
    return { data: [] }
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const rows = wrapper => wrapper.findAll('[data-type-row]')

// The duration input of the OPEN modal, resolved through its floating label
// so the assertion targets the field itself, not any numeric-looking input.
const editDurationInput = () => {
  const label = [...document.body.querySelectorAll('label')].find(
    l => l.textContent.trim() === 'Duración (minutos)'
  )
  expect(label).toBeTruthy()
  const input = document.getElementById(label.getAttribute('for'))
  expect(input).toBeTruthy()
  return input
}

const pickStatusFilter = wrapper =>
  wrapper.findAllComponents(Select).find(c => c.attributes('data-status-filter') !== undefined)

const chooseStatusOption = async (wrapper, label) => {
  const select = pickStatusFilter(wrapper)
  expect(select).toBeTruthy()
  await select.find('button').trigger('click')
  const option = select.findAll('button').find(b => b.text().includes(label))
  expect(option).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

describe('appointment-types smoke', () => {
  beforeEach(() => {
    getMock.mockReset()
    getMock.mockImplementation(async () => ({ data: [] }))
  })

  it('mounts AppointmentTypesPage.vue and renders content', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.text()).toContain('Tipos de Cita')
    wrapper.unmount()
  })

  it('renders the hero band with eyebrow, headline, caption and primary action', async () => {
    const wrapper = await mountPage(CATALOG)
    const hero = wrapper.find('[data-appointment-types-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Tipos de Cita')
    expect(hero.find('[data-hero-eyebrow]').text()).toBe('Catálogo')
    expect(hero.find('[data-hero-caption]').text().length).toBeGreaterThan(0)
    expect(hero.find('[data-hero-action]').text()).toContain('Nuevo tipo')
    wrapper.unmount()
  })

  it('derives the four KPI slots from the same full catalog', async () => {
    const wrapper = await mountPage(CATALOG)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="avg-duration"]').text()).toBe('45 min')
    expect(wrapper.find('[data-kpi-caption="total"]').text()).toMatch(/en el catálogo/i)
    wrapper.unmount()
  })

  it('renders default_duration_minutes as the duration cell', async () => {
    const wrapper = await mountPage(CATALOG)
    const row = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    expect(row.text()).toContain('30 min')
    expect(row.text()).not.toContain('undefined')
    wrapper.unmount()
  })

  it('filters the loaded catalog client-side through the status filter', async () => {
    const wrapper = await mountPage(CATALOG)
    expect(rows(wrapper)).toHaveLength(3)

    await chooseStatusOption(wrapper, 'Inactivos')
    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Blanqueamiento')
    expect(wrapper.find('[data-filter-count]').text()).toContain('Mostrando 1 de 3')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')

    await chooseStatusOption(wrapper, 'Activos')
    expect(rows(wrapper)).toHaveLength(2)
    await chooseStatusOption(wrapper, 'Todos')
    expect(rows(wrapper)).toHaveLength(3)
    wrapper.unmount()
  })

  it('filters the loaded catalog client-side through the search text', async () => {
    const wrapper = await mountPage(CATALOG)
    await wrapper.find('[data-type-search] input').setValue('ortodoncia')
    await flushPromises()
    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Ortodoncia')
    wrapper.unmount()
  })

  it('shows a contextual empty state for the active filter', async () => {
    const wrapper = await mountPage([CATALOG[0], CATALOG[1]])
    await chooseStatusOption(wrapper, 'Inactivos')
    expect(rows(wrapper)).toHaveLength(0)
    expect(wrapper.text()).toContain('No hay tipos de cita inactivos')
    wrapper.unmount()
  })

  it('renders the inline error surface with retry when loading fails', async () => {
    getMock.mockImplementationOnce(async () => {
      throw new Error('network down')
    })
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/appointment-types')) {
        return { data: CATALOG }
      }
      return { data: [] }
    })
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.find('[data-types-error]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')

    await wrapper.find('[data-retry-types]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-types-error]').exists()).toBe(false)
    expect(rows(wrapper)).toHaveLength(3)
    wrapper.unmount()
  })

  it('keeps only the New and Edit modals (the View modal is gone)', async () => {
    const wrapper = await mountPage(CATALOG)
    const titles = wrapper.findAllComponents(Modal).map(m => m.props('title'))
    expect(titles).toContain('Nuevo Tipo de Cita')
    expect(titles).toContain('Editar Tipo de Cita')
    expect(titles).not.toContain('Ver Tipo de Cita')
    expect(titles.filter(t => typeof t === 'string' && t.includes('Tipo de Cita'))).toEqual([
      'Nuevo Tipo de Cita',
      'Editar Tipo de Cita'
    ])
    wrapper.unmount()
  })

  it('presents a coherent color pair (swatch + hex input) in both modals', async () => {
    const wrapper = await mountPage(CATALOG)

    const bodyColorPair = () => {
      expect(document.body.querySelectorAll('input[type="color"]')).toHaveLength(1)
      expect(document.body.textContent).toContain('Código hex')
    }

    await wrapper.find('[data-hero-action]').trigger('click')
    await flushPromises()
    bodyColorPair()

    const cancel = [...document.body.querySelectorAll('button')].find(
      b => b.textContent.trim() === 'Cancelar'
    )
    expect(cancel).toBeTruthy()
    cancel.click()
    await flushPromises()

    const editRow = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    await editRow.find('[data-row-action="edit"]').trigger('click')
    await flushPromises()
    bodyColorPair()
    wrapper.unmount()
  })

  it('opens the shared edit modal from the ?edit=<id> deep link and clears the query', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '2' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    const nameInput = [...document.body.querySelectorAll('input')].find(
      i => i.value === 'Ortodoncia'
    )
    expect(nameInput).toBeTruthy()
    expect(router.currentRoute.value.query.edit).toBeUndefined()
    wrapper.unmount()
    await router.push('/appointment-types')
  })

  it('pre-fills the edit duration input from default_duration_minutes (row action)', async () => {
    const wrapper = await mountPage(CATALOG)
    const editRow = rows(wrapper).find(r => r.text().includes('Blanqueamiento'))
    await editRow.find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(editDurationInput().value).toBe('45')
    wrapper.unmount()
  })

  it('pre-fills the edit duration input from default_duration_minutes (?edit deep link)', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '2' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(editDurationInput().value).toBe('60')
    wrapper.unmount()
    await router.push('/appointment-types')
  })

  it('ignores an unknown ?edit id without opening the modal', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '999' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).not.toContain('Editar Tipo de Cita')
    expect(router.currentRoute.value.query.edit).toBeUndefined()
    wrapper.unmount()
    await router.push('/appointment-types')
  })
})
