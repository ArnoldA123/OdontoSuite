import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// #395 S5: the agenda offers filters by professional, chair and branch, drawn
// from the populations the session can read, and applies them to the same
// range read. The mock filters the appointment rows by the params it receives,
// so the surface is proven against a faithful `GET /api/appointments`.
const apiState = vi.hoisted(() => ({
  reads: [],
  failChairs: false,
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Silva' },
    { id: 9, name: 'Dr. Vega' }
  ],
  chairs: [
    { id: 4, name: 'Sillón 1' },
    { id: 5, name: 'Sillón 2' }
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
      dental_chair: { id: 4, name: 'Sillón 1' },
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
      dental_chair: { id: 5, name: 'Sillón 2' },
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
            response: { status: 500, data: { message: 'Los sillones no están disponibles' } }
          }
        }
        return { data: clone(apiState.chairs), meta: {} }
      }
      if (url === '/api/branches/active') return { data: clone(apiState.branches), meta: {} }
      if (url === '/api/appointments') {
        const rows = apiState.appointments.filter(
          row =>
            (!params?.user_id || row.user.id === params.user_id) &&
            (!params?.dental_chair_id || row.dental_chair.id === params.dental_chair_id)
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
  useWebSocketNotifications: () => ({})
}))

const authState = vi.hoisted(() => ({ permissions: null }))
vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Test User',
        role: 'administrador',
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

// The filter is a `UiSelect`: the first button is its trigger, the option is
// one of the buttons it renders while its listbox is open.
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

describe('calendar filters (#395 S5)', () => {
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
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('offers the three filters with their Spanish labels', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filters]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-filter="professional"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-filter="chair"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-filter="branch"]').exists()).toBe(true)

    const labels = wrapper.find('[data-calendar-filters]').text()
    expect(labels).toContain('Profesional')
    expect(labels).toContain('Sillón')
    expect(labels).toContain('Sede')

    // Nothing chosen yet: there is nothing to clear.
    expect(wrapper.find('[data-calendar-clear-filters]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('mounts the filter bar primitive without swallowing the view switcher or the date nav', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const bar = wrapper.get('[data-calendar-filters]')

    expect(bar.attributes('role')).toBe('search')
    expect(bar.find('[data-calendar-filter="professional"]').exists()).toBe(true)
    expect(bar.find('[data-calendar-filter="chair"]').exists()).toBe(true)
    expect(bar.find('[data-calendar-filter="branch"]').exists()).toBe(true)

    // Only the filters travel into the bar: the view switcher and the date nav
    // stay in the header they belong to.
    expect(bar.find('[data-calendar-view-switcher]').exists()).toBe(false)
    expect(bar.find('[data-calendar-nav="prev"]').exists()).toBe(false)
    expect(bar.find('[data-calendar-nav="today"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-view-switcher]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-nav="next"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('offers «Limpiar filtros» inside the bar and clears the read in one go', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await chooseFilter(wrapper, 'professional', 'Dr. Vega')

    const clear = wrapper.get('[data-calendar-filters] [data-calendar-clear-filters]')

    expect(clear.text()).toBe('Limpiar filtros')

    await clear.trigger('click')
    await flushPromises()

    const cleared = lastAppointmentRead()
    expect(cleared.params).not.toHaveProperty('user_id')
    expect(wrapper.find('[data-calendar-filters] [data-calendar-clear-filters]').exists()).toBe(
      false
    )

    wrapper.unmount()
  })

  it('re-reads the range with the chosen professional, without navigating', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const before = lastAppointmentRead()
    const routeBefore = router.currentRoute.value.fullPath
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-appointment="2"]').exists()).toBe(true)

    await chooseFilter(wrapper, 'professional', 'Dra. Ruiz')

    const after = lastAppointmentRead()
    expect(after.params.user_id).toBe(2)
    // Same period, same surface: the filter narrows the read, it does not move
    // the agenda.
    expect(after.params.start_date).toBe(before.params.start_date)
    expect(after.params.end_date).toBe(before.params.end_date)
    expect(router.currentRoute.value.fullPath).toBe(routeBefore)

    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-appointment="2"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('shows the filter empty state — not the agenda one — and clears it', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const initial = lastAppointmentRead()

    await chooseFilter(wrapper, 'professional', 'Dr. Vega')

    const filtered = lastAppointmentRead()
    expect(filtered.params.user_id).toBe(9)
    expect(filtered.params.start_date).toBe(initial.params.start_date)

    const empty = wrapper.find('[data-calendar-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.attributes('data-calendar-empty-scope')).toBe('filtered')
    expect(empty.text()).toContain('Sin citas para estos filtros')
    expect(empty.text()).toContain('Dr. Vega')
    expect(empty.text()).not.toContain('Sin citas esta semana')

    await wrapper.find('[data-calendar-empty-clear-filters]').trigger('click')
    await flushPromises()

    const cleared = lastAppointmentRead()
    expect(cleared.params).not.toHaveProperty('user_id')
    expect(cleared.params.start_date).toBe(initial.params.start_date)
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-appointment="2"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('applies the chair and the branch to the same range read', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await chooseFilter(wrapper, 'chair', 'Sillón 2')

    expect(lastAppointmentRead().params.dental_chair_id).toBe(5)
    expect(wrapper.find('[data-calendar-appointment="2"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(false)

    await chooseFilter(wrapper, 'branch', 'Sede Surco')

    const read = lastAppointmentRead()
    expect(read.params.branch_id).toBe(2)
    expect(read.params.dental_chair_id).toBe(5)

    wrapper.unmount()
  })

  it('keeps the filter applied while the period moves', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await chooseFilter(wrapper, 'professional', 'Dra. Ruiz')
    const filtered = lastAppointmentRead()

    await wrapper.find('[data-calendar-nav="next"]').trigger('click')
    await flushPromises()

    const moved = lastAppointmentRead()
    expect(moved.params.user_id).toBe(2)
    expect(moved.params.start_date).not.toBe(filtered.params.start_date)

    wrapper.unmount()
  })

  it('hides only the filter whose population cannot be read', async () => {
    apiState.failChairs = true

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filter="professional"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-filter="chair"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-filter="branch"]').exists()).toBe(true)

    // The failed lookup never becomes the agenda's load error and never breaks
    // the range read (#396 lesson).
    expect(wrapper.find('[data-calendar-error]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('does not offer the branch filter when the session only sees one branch', async () => {
    apiState.branches = [{ id: 1, name: 'Sede Lima' }]

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filter="branch"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-filter="professional"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('does not read populations the role cannot see and offers no filter then', async () => {
    authState.permissions = ['manage.appointment']

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-calendar-filters]').exists()).toBe(false)
    expect(apiState.reads.some(read => read.url === '/api/users/active')).toBe(false)
    expect(
      apiState.reads.some(read => read.url === '/api/dental-chairs/active?status=active')
    ).toBe(false)
    expect(apiState.reads.some(read => read.url === '/api/branches/active')).toBe(false)
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
