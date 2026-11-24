import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5a — la barra de tres selects de Semana y Mes desaparece: el profesional se
// filtra con las mismas pastillas del tablero (sobre las filas ya leídas) y la
// sede y el ambiente quedan como selectores compactos en la banda de la
// página. Esta prueba fija el contrato medido: ninguna vista ofrece un select
// de profesional, las pastillas conservan su censo y sólo la sede/ambiente
// vuelven a pedir el rango.
const apiState = vi.hoisted(() => ({
  reads: [],
  failChairs: false,
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Silva' },
    { id: 9, name: 'Dr. Vega' }
  ],
  chairs: [
    { id: 4, name: 'Ambiente 1' },
    { id: 5, name: 'Ambiente 2' }
  ],
  branches: [
    { id: 1, name: 'Sede Lima' },
    { id: 2, name: 'Sede Surco' }
  ],
  appointments: [
    {
      id: 1,
      scheduled_at: '2026-05-13T10:00:00',
      ends_at: '2026-05-13T10:30:00',
      duration_minutes: 30,
      status: 'scheduled',
      notes: null,
      user_id: 2,
      dental_chair_id: 4,
      patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: { id: 4, name: 'Ambiente 1' },
      appointment_type: { id: 3, name: 'Limpieza' }
    },
    {
      id: 2,
      scheduled_at: '2026-05-14T09:00:00',
      ends_at: '2026-05-14T09:30:00',
      duration_minutes: 30,
      status: 'confirmed',
      notes: null,
      user_id: 3,
      dental_chair_id: 5,
      patient: { id: 8, first_name: 'Luis', last_name: 'Gómez' },
      user: { id: 3, name: 'Dr. Silva' },
      dental_chair: { id: 5, name: 'Ambiente 2' },
      appointment_type: { id: 3, name: 'Limpieza' }
    }
  ]
}))

const clone = value => JSON.parse(JSON.stringify(value))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async (url, config = {}) => {
      const params = config.params ?? null
      apiState.reads.push({ url, params })

      if (url === '/api/users/active') return { data: clone(apiState.users), meta: {} }
      if (url.startsWith('/api/dental-chairs/active')) {
        if (apiState.failChairs) {
          throw {
            response: { status: 500, data: { message: 'Los ambientes no están disponibles' } }
          }
        }
        return { data: clone(apiState.chairs), meta: {} }
      }
      if (url === '/api/branches/active') return { data: clone(apiState.branches), meta: {} }
      if (url === '/api/appointments') {
        const rows = apiState.appointments.filter(
          row =>
            (!params?.user_id || row.user_id === params.user_id) &&
            (!params?.dental_chair_id || row.dental_chair_id === params.dental_chair_id)
        )
        return { data: clone(rows), meta: {} }
      }
      return { data: [], meta: {} }
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    del: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: (err, fallback) => err?.response?.data?.message || fallback
  })
}))

const channelStub = { listen: () => channelStub }
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => channelStub,
    privateChannel: () => channelStub,
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({}),
  // T2: la agenda marca el eco de lo que escribe para no anunciarlo dos veces.
  markLocalAppointmentEcho: () => {},
  consumeLocalAppointmentEcho: () => false
}))

const authState = vi.hoisted(() => ({ permissions: null, branchId: null }))
vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Test User',
        role: 'administrador',
        branch_id: authState.branchId,
        permissions: authState.permissions ?? [
          'manage.appointment',
          'complete.appointment',
          'check_in.appointment',
          'start.consultation',
          'announce.patient',
          'view.user',
          'view.dental_chair',
          'view.branch'
        ]
      })),
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      isLoading: computed(() => false),
      login: async () => {},
      logout: async () => {},
      authLogout: async () => {},
      getCurrentUser: async () => {},
      hasRole: () => true,
      hasAnyRole: () => true
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = () => mount(Page, { global: { plugins: [router, uiComponents] } })

const appointmentReads = () => apiState.reads.filter(read => read.url === '/api/appointments')
const lastAppointmentRead = () => appointmentReads().at(-1)

const chipIds = wrapper =>
  wrapper
    .findAll('[data-calendar-appointment]')
    .map(chip => chip.attributes('data-calendar-appointment'))

// El filtro compacto es un `UiSelect`: el primer botón es su disparador y la
// opción es uno de los botones que pinta con su lista abierta.
const chooseFilter = async (wrapper, key, label) => {
  const container = wrapper.find(`[data-calendar-filter="${key}"]`)
  expect(container.exists(), `${key} filter is rendered`).toBe(true)

  await container.find('button').trigger('click')
  await nextTick()

  const option = container.findAll('button').find(button => button.text().trim() === label)
  expect(option, `option «${label}» in the ${key} filter`).toBeTruthy()

  await option.trigger('click')
  await flushPromises()
}

describe('calendar filters (T5a: pastillas compartidas + compactos)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    apiState.reads = []
    apiState.failChairs = false
    apiState.branches = [
      { id: 1, name: 'Sede Lima' },
      { id: 2, name: 'Sede Surco' }
    ]
    authState.permissions = null
    authState.branchId = null
    // T6 — la agenda abre en Día; esta prueba mide los compactos y el rango de
    // Semana/Mes, así que declara esa preferencia como estado de partida.
    window.localStorage.setItem('calendar-view', 'week')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('filters the professional with the same pills as the board, never with a select', async () => {
    const wrapper = mountPage()
    await flushPromises()

    // Semana es la vista inicial a 1024 px: no hay barra de tres selects.
    expect(wrapper.find('[data-calendar-filter="professional"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-filters]').text()).not.toContain('Profesional')

    const pills = wrapper.find('[data-calendar-professional-pills]')
    expect(pills.exists()).toBe(true)
    expect(pills.find('[data-calendar-professional-pill="all"]').text()).toContain('2')
    expect(pills.find('[data-calendar-professional-pill="2"]').text()).toContain('Dra. Ruiz')
    expect(pills.find('[data-calendar-professional-pill="3"]').text()).toContain('Dr. Silva')

    wrapper.unmount()
  })

  it('narrows the week with a pill without re-reading the range', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const before = appointmentReads().length
    expect(chipIds(wrapper).sort()).toEqual(['1', '2'])

    await wrapper.find('[data-calendar-professional-pill="2"]').trigger('click')
    await flushPromises()

    expect(chipIds(wrapper)).toEqual(['1'])
    expect(wrapper.find('[data-calendar-professional-pill="2"]').attributes('aria-pressed')).toBe(
      'true'
    )
    // Las pastillas conservan el censo completo: filtrar no recorta la lista.
    expect(wrapper.find('[data-calendar-professional-pill="3"]').exists()).toBe(true)
    expect(appointmentReads().length).toBe(before)

    await wrapper.find('[data-calendar-professional-pill="all"]').trigger('click')
    await flushPromises()
    expect(chipIds(wrapper).sort()).toEqual(['1', '2'])

    wrapper.unmount()
  })

  it('shows the filtered empty block — not the period one — when the filters converge on nothing', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const initial = lastAppointmentRead()

    // Dr. Silva sólo tiene la cita del día 14; al acotar el rango al ambiente 1
    // la lectura devuelve únicamente la cita de Dra. Ruiz y la selección queda
    // sin filas sin que el período esté vacío.
    await wrapper.find('[data-calendar-professional-pill="3"]').trigger('click')
    await flushPromises()
    await chooseFilter(wrapper, 'chair', 'Ambiente 1')

    const empty = wrapper.find('[data-calendar-filtered-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Sin citas para estos filtros')
    expect(empty.text()).toContain('Dr. Silva')
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)
    // El período no se movió: el filtro es una vista sobre la misma lectura.
    expect(lastAppointmentRead().params.start_date).toBe(initial.params.start_date)

    await empty.find('[data-calendar-clear-filters]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-filtered-empty]').exists()).toBe(false)
    expect(chipIds(wrapper).sort()).toEqual(['1', '2'])

    wrapper.unmount()
  })

  it('keeps the chair and branch as compact selectors that re-read the range', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const filters = wrapper.find('[data-calendar-filters]')
    expect(filters.attributes('aria-label')).toBe('Filtros de la agenda')
    expect(filters.find('[data-calendar-filter="chair"]').exists()).toBe(true)
    expect(filters.find('[data-calendar-filter="branch"]').exists()).toBe(true)
    // Sin etiquetas apiladas: los compactos viven en la banda, no en una barra.
    expect(filters.findAll('label')).toHaveLength(0)

    await chooseFilter(wrapper, 'chair', 'Ambiente 2')

    expect(lastAppointmentRead().params.dental_chair_id).toBe(5)
    expect(chipIds(wrapper)).toEqual(['2'])

    await chooseFilter(wrapper, 'branch', 'Sede Surco')

    const read = lastAppointmentRead()
    expect(read.params.branch_id).toBe(2)
    expect(read.params.dental_chair_id).toBe(5)

    wrapper.unmount()
  })

  it('offers «Limpiar filtros» once something is chosen and clears the read', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-clear-filters]').exists()).toBe(false)

    await chooseFilter(wrapper, 'chair', 'Ambiente 2')
    const clear = wrapper.get('[data-calendar-clear-filters]')
    expect(clear.text()).toBe('Limpiar filtros')

    await clear.trigger('click')
    await flushPromises()

    const cleared = lastAppointmentRead()
    expect(cleared.params).not.toHaveProperty('dental_chair_id')
    expect(cleared.params).not.toHaveProperty('user_id')
    expect(wrapper.find('[data-calendar-clear-filters]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('hides only the compact filter whose population cannot be read', async () => {
    apiState.failChairs = true

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filter="chair"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-filter="branch"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-professional-pills]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-error]').exists()).toBe(false)
    expect(chipIds(wrapper).sort()).toEqual(['1', '2'])

    wrapper.unmount()
  })

  it('does not offer the branch filter to a session that only reads one branch', async () => {
    apiState.branches = [{ id: 1, name: 'Sede Lima' }]
    authState.branchId = 1

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filter="branch"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-filter="chair"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('does not read populations the role cannot see and still offers the pills', async () => {
    authState.permissions = ['manage.appointment']

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filters]').exists()).toBe(false)
    expect(apiState.reads.some(read => read.url === '/api/users/active')).toBe(false)
    expect(
      apiState.reads.some(read => read.url === '/api/dental-chairs/active?status=active')
    ).toBe(false)
    expect(apiState.reads.some(read => read.url === '/api/branches/active')).toBe(false)
    // El censo de las pastillas sale de las filas de la agenda, no del lookup.
    expect(wrapper.find('[data-calendar-professional-pill="2"]').exists()).toBe(true)
    expect(chipIds(wrapper).sort()).toEqual(['1', '2'])

    wrapper.unmount()
  })

  it('does not offer the chair compact in Day, where the columns are the chairs', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-filter="chair"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-professional-pills]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-week-strip]').exists()).toBe(true)

    wrapper.unmount()
  })
})
