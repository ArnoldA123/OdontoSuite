import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T8 — capacidad: cuarenta citas en un día y solapes a la misma hora.
//
// La base sembrada no tiene solapes, así que esta prueba los fabrica: una
// jornada con cuarenta citas y un grupo de cuatro simultáneas en el mismo
// ambiente. Fija lo que el usuario preguntó: qué hace el mes con el «+N», cómo
// se reparten las simultáneas en Semana y Día, cuándo aparece el indicador y
// que nada de eso desborde el documento.
const DAY_DATE = '2026-05-13'

const buildBusyDay = () => {
  const list = []
  let id = 1
  // Cuatro simultáneas cada media hora de 08:00 a 14:00: 12 grupos × 4 = 48,
  // recortado a 40. El primer grupo (08:00) es el que más se mide.
  for (let slot = 0; slot < 12 && list.length < 40; slot += 1) {
    const hour = 8 + Math.floor(slot / 2)
    const minute = slot % 2 === 0 ? '00' : '30'
    for (let lane = 0; lane < 4 && list.length < 40; lane += 1) {
      list.push({
        id: id++,
        scheduled_at: `${DAY_DATE}T${String(hour).padStart(2, '0')}:${minute}:00`,
        ends_at: `${DAY_DATE}T${String(hour).padStart(2, '0')}:${minute === '00' ? '30' : '59'}:00`,
        duration_minutes: 30,
        status: 'confirmed',
        user_id: 2,
        patient: { id: 100 + lane, first_name: `Paciente`, last_name: `${lane + 1}` },
        user: { id: 2, name: 'Dra. Ruiz' },
        // Todas en el mismo ambiente: es el caso duro del tablero.
        dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
        appointment_type: { id: 3, name: 'Control' }
      })
    }
  }
  return list
}

const apiState = vi.hoisted(() => ({
  users: [{ id: 2, name: 'Dra. Ruiz' }],
  chairs: [{ id: 4, name: 'Ambiente 1', code: 'AMB-001' }],
  branches: [{ id: 1, name: 'Sede San Isidro' }],
  appointments: []
}))

const clone = value => JSON.parse(JSON.stringify(value))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      const path = String(url)
      if (path === '/api/users/active') return { data: clone(apiState.users) }
      if (path.startsWith('/api/dental-chairs/active')) return { data: clone(apiState.chairs) }
      if (path === '/api/branches/active') return { data: clone(apiState.branches) }
      if (path === '/api/appointments') return { data: clone(apiState.appointments), meta: {} }
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

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Test User',
        role: 'administrador',
        branch_id: null,
        permissions: [
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
import {
  OVERLAP_MAX_LANES,
  layoutOverlapLanes
} from '../../../resources/js/modules/appointments/calendarBoardMath.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

// Rango porcentual que declara la caja de un carril: `calc(25% + 2px)` +
// `calc(25% - 4px)` → [25, 50] sin los 2 px de aire de cada lado.
const laneRange = element => {
  const { style } = element
  const left = Number(/calc\(([\d.]+)%/.exec(style.left)?.[1] ?? NaN)
  const width = Number(/calc\(([\d.]+)%/.exec(style.width)?.[1] ?? NaN)
  return { left, width, right: left + width }
}

const view = async (wrapper, name) => {
  await wrapper.get(`[data-calendar-view="${name}"]`).trigger('click')
  await flushPromises()
}

beforeEach(() => {
  window.localStorage.clear()
  apiState.appointments = buildBusyDay()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 4, 13, 10, 12, 0))
})

afterEach(() => {
  window.localStorage.clear()
  vi.useRealTimers()
})

describe('cuarenta citas en un día (T8)', () => {
  it('el mes muestra tres bloques y el cierre con el total exacto', async () => {
    const wrapper = await mountPage()
    await view(wrapper, 'month')

    const cell = wrapper.get(`[data-calendar-month-day="${DAY_DATE}"]`)
    expect(cell.findAll('[data-calendar-appointment]')).toHaveLength(3)
    const more = cell.get('[data-calendar-more]')
    expect(more.text()).toBe('+37 más')
    expect(more.attributes('data-calendar-more-count')).toBe('37')
    // La celda no deja que nada se salga de su caja.
    expect(cell.classes()).toContain('overflow-hidden')

    // El cierre abre el día correspondiente.
    await more.trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.findAll('[data-calendar-board-column]').length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('el día reparte las simultáneas lado a lado y agrupa las que no caben', async () => {
    const wrapper = await mountPage()

    const column = wrapper.get('[data-calendar-board-column-body="chair-4"]')
    // 08:00 tiene cuatro simultáneas: tres visibles y un «+1».
    expect(OVERLAP_MAX_LANES).toBe(3)

    const lanes = [...column.element.querySelectorAll('[data-calendar-appointment]')]
      .map(node => ({ node, range: laneRange(node) }))
      .filter(entry => entry.range.width > 0)
      .slice(0, 3)
    expect(lanes).toHaveLength(3)
    // Cajas disjuntas: cada carril empieza donde termina el anterior.
    expect(lanes[0].range).toEqual({ left: 0, width: 25, right: 25 })
    expect(lanes[1].range).toEqual({ left: 25, width: 25, right: 50 })
    expect(lanes[2].range).toEqual({ left: 50, width: 25, right: 75 })
    // El carril reservado lo ocupa el indicador «+1».
    const indicators = column.findAll('[data-calendar-overlap-more]')
    expect(indicators).toHaveLength(10) // diez grupos de cuatro simultáneas
    expect(indicators[0].text()).toBe('+1')
    expect(laneRange(indicators[0].element).left).toBe(75)
    // Ninguna simultánea queda apilada: las cuarenta se reparten entre bloques
    // visibles e indicadores, sin duplicar la caja de nadie.
    expect(column.findAll('[data-calendar-appointment]').length).toBe(30)
    expect(column.findAll('[data-calendar-overlap-more]').length).toBe(10)

    wrapper.unmount()
  })

  it('la semana reparte los solapes de la columna del día con el mismo criterio', async () => {
    const wrapper = await mountPage()
    await view(wrapper, 'week')

    const column = wrapper.get(`[data-calendar-week-column="${DAY_DATE}"]`)
    const blocks = column.findAll('[data-calendar-appointment]')
    expect(blocks.length).toBe(30)
    const ranges = blocks.slice(0, 3).map(block => laneRange(block.element))
    expect(ranges).toEqual([
      { left: 0, width: 25, right: 25 },
      { left: 25, width: 25, right: 50 },
      { left: 50, width: 25, right: 75 }
    ])
    const indicators = column.findAll('[data-calendar-overlap-more]')
    expect(indicators).toHaveLength(10)
    expect(indicators[0].text()).toBe('+1')

    wrapper.unmount()
  })

  it('con cuarenta citas el desborde vive dentro de los tableros, no del documento', async () => {
    const wrapper = await mountPage()

    // Las cuarenta citas se leen del día: el tablero las reparte y su scroll
    // propio es el único que se desplaza.
    const board = wrapper.get('[data-calendar-board-scroll]')
    expect(board.classes()).toContain('overflow-auto')
    expect(wrapper.findAll('[data-calendar-board] [data-calendar-appointment]')).toHaveLength(30)
    // Ninguna cita se pinta fuera de un contenedor que se desplaza.
    const outside = wrapper.findAll('[data-calendar-appointment]').filter(block => {
      const inside =
        block.element.closest('[data-calendar-board-scroll]') ||
        block.element.closest('[data-calendar-week-scroll]') ||
        block.element.closest('[data-calendar-month-scroll]')
      return !inside
    })
    expect(outside).toHaveLength(0)

    await view(wrapper, 'week')
    expect(wrapper.get('[data-calendar-week-scroll]').classes()).toContain('overflow-x-auto')
    await view(wrapper, 'month')
    expect(wrapper.get('[data-calendar-month-scroll]').classes()).toContain('overflow-auto')

    wrapper.unmount()
  })

  it('el reparto en carriles no depende de la vista: la aritmética es la misma', () => {
    const { blocks, indicators } = layoutOverlapLanes(apiState.appointments)
    expect(blocks).toHaveLength(40)
    expect(indicators).toHaveLength(10)
    expect(blocks.every(block => block.slots === 4)).toBe(true)
    // Una cita sin vecinas ocupa el ancho completo.
    const single = layoutOverlapLanes([apiState.appointments[0]])
    expect(single.blocks[0]).toMatchObject({ slot: 0, slots: 1, hidden: false })
    expect(single.indicators).toEqual([])
  })
})
