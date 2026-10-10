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

  it('never renders the appointment count in the agenda header (D8); the day KPI goes N/D on failure', async () => {
    let todayAttempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
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

    // The count lives only in the KPI, never in the agenda header.
    const agenda = wrapper.find('section[aria-label="Agenda de hoy"]')
    expect(agenda.text()).not.toMatch(/\d+\s+citas?\b/)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('2')

    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(agenda.find('[data-state="error-appointments"]').exists()).toBe(true)
    expect(agenda.text()).not.toMatch(/\d+\s+citas?\b/)
    // Single source: the KPI reports N/D instead of a stale or fake count.
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('N/D')

    wrapper.unmount()
  })

  it('renders the compact shared empty pattern with per-section copy and no redundant CTA (D9)', async () => {
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

    // At most three usages after unification: agenda, upcoming, pending.
    const empties = wrapper.findAll('[data-section-empty]')
    expect(empties).toHaveLength(3)
    for (const empty of empties) {
      // Compact pattern: reduced padding, one title + one sentence.
      expect(empty.classes()).toContain('p-6')
      expect(empty.findAll('p')).toHaveLength(2)
      // WU5b: the login's dental motif, scaled down, decorates empty states.
      const motif = empty.find('[data-empty-motif]')
      expect(motif.exists()).toBe(true)
      expect(motif.attributes('aria-hidden')).toBe('true')
      const marks = motif.findAll('img')
      expect(marks.length).toBeGreaterThanOrEqual(6)
      for (const mark of marks) {
        expect(mark.attributes('src')).toMatch(/^\/images\/login\//)
      }
    }

    const agendaEmpty = wrapper.find('[data-state="empty-appointments"]')
    expect(agendaEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(agendaEmpty.find('svg').exists()).toBe(true)
    expect(agendaEmpty.text()).toContain('Sin citas para hoy')
    expect(agendaEmpty.text()).toContain('Cuando registres citas, aparecerán aquí.')
    expect(agendaEmpty.text()).not.toContain('sección de calendario')
    expect(agendaEmpty.find('button').exists()).toBe(false)

    const upcomingEmpty = wrapper.find('[data-state="empty-upcoming"]')
    expect(upcomingEmpty.element.hasAttribute('data-section-empty')).toBe(true)
    expect(upcomingEmpty.text()).toContain('No hay citas registradas de mañana en adelante.')
    expect(upcomingEmpty.text()).not.toContain('Sin citas programadas para esta semana')

    // Unified pending: ONE empty state for the merged list.
    const pendingEmpties = wrapper.findAll('[data-state="empty-pending"]')
    expect(pendingEmpties).toHaveLength(1)
    expect(pendingEmpties[0].element.hasAttribute('data-section-empty')).toBe(true)
    expect(pendingEmpties[0].text()).toContain('Sin pendientes')

    wrapper.unmount()
  })

  it('renders merged pending rows with one uniform anatomy and a muted null-cost fallback', async () => {
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

    // WU5b: motif is empty-state only; rows render on a clean surface.
    const motifs = wrapper.findAll('[data-empty-motif]')
    const empties = wrapper.findAll('[data-section-empty]')
    expect(empties.length).toBeGreaterThan(0)
    expect(motifs).toHaveLength(empties.length)
    expect(wrapper.find('[data-state="empty-pending"]').exists()).toBe(false)
    for (const motif of motifs) {
      expect(motif.element.closest('[data-section-empty]')).not.toBeNull()
    }
    const rows = wrapper.findAll('[data-pending-row]')
    expect(rows).toHaveLength(3)

    // Sorted by pending date ascending: Pedro (1 oct), Marta (2 oct), Ana (3 oct).
    const nullCostRow = rows[0]
    const planRow = rows[1]
    const quotationRow = rows[2]

    expect(normalize(quotationRow.text())).toContain('S/ 149.90')
    expect(normalize(planRow.text())).toContain('S/ 249.50')
    expect(planRow.text()).toContain('Propuesto')

    // One row template for both types: patient / amount / type badge /
    // status badge / date / action, all rendered through the same
    // UiBadge pill primitive.
    const cells = row =>
      Array.from(row.element.querySelector('.card-content > div').children).map(el =>
        el.tagName.toLowerCase()
      )
    expect(cells(rows[0])).toEqual(cells(rows[1]))
    expect(cells(rows[1])).toEqual(cells(rows[2]))
    expect(planRow.findAll('[data-variant]').length).toBeGreaterThanOrEqual(2)

    expect(nullCostRow.text()).toContain('N/D')
    expect(normalize(nullCostRow.text())).not.toContain('S/')
    expect(nullCostRow.text()).not.toContain('\u2014')

    wrapper.unmount()
  })

  it('keeps hero actions in one top cluster and KPI cards equal-height', async () => {
    getMock.mockImplementation(async () => ({ data: [] }))

    const { wrapper } = await mountDashboard()

    // WU5b: refresh lives in a controlled hero actions cluster (no orphan
    // wrap at 390px) and every KPI card shares the equal-height anatomy.
    const hero = wrapper.find('[data-dashboard-hero]')
    const actions = hero.find('[data-hero-actions]')
    expect(actions.exists()).toBe(true)
    expect(actions.find('[data-refresh-button]').exists()).toBe(true)

    const cards = wrapper.findAll('[data-stat-card]')
    expect(cards).toHaveLength(4)
    for (const card of cards) {
      expect(card.classes()).toContain('h-full')
      expect(card.classes()).toContain('flex-col')
    }

    wrapper.unmount()
  })
})

describe('dashboard section-state extraction (T5 source)', () => {
  it('leaves no duplicated inline error or empty markup in the page', () => {
    expect(pageSource).not.toContain('rounded-ios p-5 bg-systemRed-50')
    expect(pageSource.match(/<DashboardSectionError\b/g) || []).toHaveLength(3)
    // After the pending unification only three usages remain: agenda,
    // upcoming and the single unified pending empty state.
    expect(pageSource.match(/<DashboardSectionEmpty\b/g) || []).toHaveLength(3)
  })
})
