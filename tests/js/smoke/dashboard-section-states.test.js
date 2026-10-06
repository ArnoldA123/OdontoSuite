import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5 regression coverage: the dashboard renders ONE shared section-error
// pattern and ONE shared section-empty pattern across the agenda, the
// upcoming-week strip and the pending block; no section shows a count while
// its own error state is visible; the pending plan rows render final_cost
// with the same row anatomy as the quotation rows.
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
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'
import pageSource from '../../../resources/js/modules/dashboard/DashboardPage.vue?raw'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountDashboard = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

const makeAppointment = id => ({
  id,
  scheduled_at: `2026-10-05T${String(8 + id).padStart(2, '0')}:30:00-05:00`,
  status: 'confirmed',
  patient: {
    id: 100 + id,
    full_name: `Paciente ${id}`,
    first_name: 'Paciente',
    last_name: `${id}`
  },
  appointment_type: { id: 3, name: 'Limpieza dental' },
  user: { id: 2, name: 'Dra. Carla Núñez' }
})

const todayCalls = () =>
  getMock.mock.calls.filter(([url]) => url === '/api/dashboard/appointments-today')
const upcomingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/upcoming')
const pendingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/pending')

// Intl rendering uses U+00A0 between the currency glyph and the amount.
const normalize = value => value.replace(/\u00a0/g, ' ')

describe('dashboard unified section states (T5)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
  })

  it('renders one shared error component per section with its own hooks and scoped retry', async () => {
    let upcomingAttempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') throw { status: 500 }
      if (url === '/api/dashboard/upcoming') {
        upcomingAttempt += 1
        if (upcomingAttempt === 1) throw { status: 500 }
        return { data: [] }
      }
      if (url === '/api/dashboard/pending') throw { status: 500 }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const sharedErrors = wrapper.findAll('[data-section-error]')
    expect(sharedErrors).toHaveLength(3)

    const agendaError = wrapper.find('[data-state="error-appointments"]')
    expect(agendaError.element.hasAttribute('data-section-error')).toBe(true)
    expect(agendaError.attributes('role')).toBe('alert')
    expect(agendaError.text()).toContain('No pudimos cargar la agenda de hoy')
    expect(agendaError.text()).toContain('Reintentar')

    const upcomingError = wrapper.find('[data-state="error-upcoming"]')
    expect(upcomingError.element.hasAttribute('data-section-error')).toBe(true)
    expect(upcomingError.attributes('role')).toBe('alert')
    expect(upcomingError.text()).toContain('No pudimos cargar las próximas citas')

    const pendingError = wrapper.find('[data-state="error-pending"]')
    expect(pendingError.element.hasAttribute('data-section-error')).toBe(true)
    expect(pendingError.attributes('role')).toBe('alert')
    expect(pendingError.text()).toContain('No pudimos cargar los pendientes')

    // The retry affordance stays scoped: only the upcoming resource reloads.
    await wrapper.find('[data-retry-upcoming]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(upcomingCalls()).toHaveLength(2)
    expect(todayCalls()).toHaveLength(1)
    expect(pendingCalls()).toHaveLength(1)
    expect(wrapper.find('[data-state="error-upcoming"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="error-appointments"]').exists()).toBe(true)
    expect(wrapper.find('[data-state="error-pending"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides the agenda count while its own error state is visible', async () => {
    let todayAttempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 2 } }
      if (url === '/api/dashboard/appointments-today') {
        todayAttempt += 1
        if (todayAttempt === 1) {
          return { data: [makeAppointment(1), makeAppointment(2)] }
        }
        throw { status: 500 }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Agenda de hoy"]').text()).toContain('2 citas')

    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()
    await flushPromises()

    const agenda = wrapper.find('section[aria-label="Agenda de hoy"]')
    expect(agenda.find('[data-state="error-appointments"]').exists()).toBe(true)
    expect(agenda.text()).not.toMatch(/\d+\s+citas?\b/)

    wrapper.unmount()
  })

  it('renders the shared empty component with per-section copy and one padding token', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') {
        return {
          data: {
            quotations: { count: 0, items: [] },
            treatment_plans: { count: 0, items: [] }
          }
        }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const empties = wrapper.findAll('[data-section-empty]')
    expect(empties).toHaveLength(4)
    for (const empty of empties) {
      expect(empty.classes()).toContain('p-10')
    }

    const agendaEmpty = wrapper.find('[data-state="empty-appointments"]')
    expect(agendaEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(agendaEmpty.find('svg').exists()).toBe(true)
    expect(agendaEmpty.text()).toContain('Sin citas para hoy')

    const cta = agendaEmpty.find('[data-cta="empty-create-appointment"]')
    expect(cta.exists()).toBe(true)
    expect(cta.text()).toContain('Crear nueva cita')
    await cta.trigger('click')
    await flushPromises()
    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(true)

    const upcomingEmpty = wrapper.find('[data-state="empty-upcoming"]')
    expect(upcomingEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(upcomingEmpty.text()).toContain('Sin citas programadas para esta semana')

    const quotationEmpty = wrapper.find(
      '[data-pending-group="quotations"] [data-state="empty-pending"]'
    )
    expect(quotationEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(quotationEmpty.text()).toContain('Sin presupuestos pendientes')

    const planEmpty = wrapper.find(
      '[data-pending-group="treatment-plans"] [data-state="empty-pending"]'
    )
    expect(planEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(planEmpty.text()).toContain('Sin planes por aceptar')

    wrapper.unmount()
  })

  it('renders plan final_cost with the quotation row anatomy and a muted null fallback', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') {
        return {
          data: {
            quotations: {
              count: 1,
              items: [
                {
                  id: 11,
                  patient_name: 'Ana Torres Quispe',
                  total_amount: 149.9,
                  status: 'sent',
                  created_at: '2026-10-03T10:00:00.000Z'
                }
              ]
            },
            treatment_plans: {
              count: 2,
              items: [
                {
                  id: 21,
                  patient_name: 'Marta Díaz',
                  title: 'Plan de tratamiento',
                  final_cost: 249.5,
                  status: 'proposed',
                  created_at: '2026-10-02T10:00:00.000Z'
                },
                {
                  id: 22,
                  patient_name: 'Pedro Salas',
                  title: 'Plan de tratamiento',
                  final_cost: null,
                  status: 'proposed',
                  created_at: '2026-10-01T10:00:00.000Z'
                }
              ]
            }
          }
        }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const quotationRow = wrapper.find('[data-pending-row="quotations"]')
    const planRow = wrapper.find('[data-pending-row="treatment-plans"]')

    expect(normalize(quotationRow.text())).toContain('S/ 149.90')
    expect(normalize(planRow.text())).toContain('S/ 249.50')
    expect(planRow.text()).toContain('Propuesto')

    // Same cell anatomy as the quotation rows: patient / amount / status /
    // date, all rendered through the same UiBadge pill primitive.
    const cells = row =>
      Array.from(row.element.querySelector('.card-content > div').children).map(el =>
        el.tagName.toLowerCase()
      )
    expect(cells(planRow)).toEqual(cells(quotationRow))
    expect(planRow.find('[data-variant]').exists()).toBe(true)

    const nullCostRow = wrapper.findAll('[data-pending-row="treatment-plans"]')[1]
    expect(nullCostRow.text()).toContain('N/D')
    expect(normalize(nullCostRow.text())).not.toContain('S/')
    expect(nullCostRow.text()).not.toContain('\u2014')

    wrapper.unmount()
  })
})

describe('dashboard section-state extraction (T5 source)', () => {
  it('leaves no duplicated inline error or empty markup in the page', () => {
    expect(pageSource).not.toContain('rounded-ios p-5 bg-systemRed-50')
    expect(pageSource.match(/<DashboardSectionError\b/g) || []).toHaveLength(3)
    expect((pageSource.match(/<DashboardSectionEmpty\b/g) || []).length).toBeGreaterThanOrEqual(4)
  })
})
