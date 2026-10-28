import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #397 S7: a 390 px la rejilla semanal se comprimía (día de 38 px, chip de
// 29 px) y la cabecera de días quedaba cortada en el borde. La agenda abre en
// la vista que se lee y, cuando el usuario elige «Semana» a mano, la rejilla
// vive en un scroll horizontal propio con un ancho mínimo por día. jsdom no
// calcula layout, así que esta prueba fija el contrato observable —vista
// inicial, contenedor con desborde, piso de ancho y affordance— y simula el
// desborde para probar que el aviso aparece y se retira.
const apiState = vi.hoisted(() => ({
  appointments: [
    {
      id: 1,
      scheduled_at: '2026-05-13T10:00:00',
      ends_at: '2026-05-13T10:30:00',
      duration_minutes: 30,
      status: 'scheduled',
      notes: null,
      patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: null,
      appointment_type: { id: 3, name: 'Limpieza' }
    }
  ]
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url === '/api/appointments') {
        return { data: JSON.parse(JSON.stringify(apiState.appointments)) }
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

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Test User',
        role: 'administrador',
        permissions: [
          'manage.appointment',
          'complete.appointment',
          'check_in.appointment',
          'start.consultation',
          'announce.patient'
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
  MOBILE_VIEW_BREAKPOINT_PX,
  initialViewForWidth
} from '../../../resources/js/modules/appointments/calendarDateMath.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// 390x844 — the mobile viewport where the audit measured the compressed grid.
const MOBILE_WIDTH = 390
const DESKTOP_WIDTH = 1440

const originalInnerWidth = Object.getOwnPropertyDescriptor(window, 'innerWidth')

const setViewportWidth = width => {
  Object.defineProperty(window, 'innerWidth', {
    configurable: true,
    writable: true,
    value: width
  })
}

const restoreViewportWidth = () => {
  if (originalInnerWidth) {
    Object.defineProperty(window, 'innerWidth', originalInnerWidth)
  } else {
    delete window.innerWidth
  }
}

const mountAt = async width => {
  setViewportWidth(width)
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const isPressed = (wrapper, view) =>
  wrapper.find(`[data-calendar-view="${view}"]`).attributes('aria-pressed')

describe('initialViewForWidth (#397 S7)', () => {
  it('abre en día por debajo del punto de quiebre de escritorio', () => {
    expect(MOBILE_VIEW_BREAKPOINT_PX).toBe(768)
    expect(initialViewForWidth(390)).toBe('day')
    expect(initialViewForWidth(767)).toBe('day')
  })

  it('conserva la semana desde el punto de quiebre', () => {
    expect(initialViewForWidth(768)).toBe('week')
    expect(initialViewForWidth(1440)).toBe('week')
  })

  it('cae a semana cuando el ancho no se puede medir', () => {
    for (const width of [undefined, null, 0, -1, NaN, 'sin-ancho']) {
      expect(initialViewForWidth(width), String(width)).toBe('week')
    }
  })
})

describe('agenda móvil (#397 S7)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
  })

  afterEach(() => {
    restoreViewportWidth()
    vi.useRealTimers()
  })

  it('a 390 px abre en «Día» y deja elegir «Semana» a mano', async () => {
    const wrapper = await mountAt(MOBILE_WIDTH)

    expect(isPressed(wrapper, 'day')).toBe('true')
    expect(isPressed(wrapper, 'week')).toBe('false')
    expect(wrapper.find('[data-calendar-week-grid]').exists()).toBe(false)

    await wrapper.find('[data-calendar-view="week"]').trigger('click')
    await flushPromises()

    expect(isPressed(wrapper, 'week')).toBe('true')
    expect(wrapper.find('[data-calendar-week-grid]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('a 1440 px abre en «Semana»', async () => {
    const wrapper = await mountAt(DESKTOP_WIDTH)

    expect(isPressed(wrapper, 'week')).toBe('true')
    expect(wrapper.find('[data-calendar-week-grid]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('la rejilla semanal vive en un scroll horizontal con piso de ancho por día', async () => {
    const wrapper = await mountAt(MOBILE_WIDTH)
    await wrapper.find('[data-calendar-view="week"]').trigger('click')
    await flushPromises()

    // La rejilla —cabecera de días, horas y celdas— queda dentro del contenedor
    // que se desplaza: sólo la semana se mueve, la página no desborda.
    const scroll = wrapper.find('[data-calendar-week-scroll]')
    expect(scroll.exists()).toBe(true)
    expect(scroll.classes()).toContain('overflow-x-auto')

    const grid = wrapper.find('[data-calendar-week-scroll] [data-calendar-week-grid]')
    expect(grid.exists()).toBe(true)
    expect(grid.classes()).toContain('grid')

    const hourWidth = Number(grid.attributes('data-calendar-week-hour-width'))
    const dayMinWidth = Number(grid.attributes('data-calendar-week-day-min-width'))
    expect(hourWidth).toBe(64)
    // Un chip de 29 px reducía hora y paciente a un carácter: el piso apunta a
    // que «10:00 - 10:30» entre completo en la columna.
    expect(dayMinWidth).toBeGreaterThanOrEqual(96)

    // La cabecera de días se mueve con la rejilla: no queda cortada en el borde.
    expect(grid.findAll('[data-calendar-slot-day]')).toHaveLength(7 * 16)

    const { style } = grid.element
    expect(style.minWidth).toBe(`${hourWidth + dayMinWidth * 7}px`)
    expect(style.gridTemplateColumns).toContain(`${hourWidth}px`)
    expect(style.gridTemplateColumns).toContain(`minmax(${dayMinWidth}px, 1fr)`)

    // El texto del chip no es un carácter: hora y paciente ocupan cada uno su
    // línea dentro de la columna, así ninguno come el espacio del otro.
    const chip = wrapper.find('[data-calendar-week-grid] [data-calendar-appointment="1"]')
    expect(chip.exists()).toBe(true)
    expect(chip.find('[data-calendar-chip-time]').text()).toBe('10:00 - 10:30')
    expect(chip.find('[data-calendar-chip-patient]').text()).toBe('Ana Pérez')
    expect(chip.text()).toContain('10:00 - 10:30')
    expect(chip.text()).toContain('Ana Pérez')

    wrapper.unmount()
  })

  it('el affordance del scroll aparece sólo cuando la rejilla desborda y se retira al final', async () => {
    const wrapper = await mountAt(MOBILE_WIDTH)
    await wrapper.find('[data-calendar-view="week"]').trigger('click')
    await flushPromises()

    // Sin desborde medido no hay aviso ni gradientes.
    expect(wrapper.find('[data-calendar-week-scroll-affordance]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-week-scroll-edge]').exists()).toBe(false)

    const viewport = wrapper.find('[data-calendar-week-scroll]').element
    Object.defineProperty(viewport, 'scrollWidth', { configurable: true, value: 848 })
    Object.defineProperty(viewport, 'clientWidth', { configurable: true, value: 342 })
    await wrapper.find('[data-calendar-week-scroll]').trigger('scroll')
    await flushPromises()

    expect(wrapper.find('[data-calendar-week-scroll-affordance]').text()).toContain(
      'Desliza para ver la semana completa'
    )
    expect(wrapper.find('[data-calendar-week-scroll-edge="right"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-week-scroll-edge="left"]').exists()).toBe(false)

    // Al final del recorrido no queda nada a la derecha que anunciar.
    viewport.scrollLeft = 506
    await wrapper.find('[data-calendar-week-scroll]').trigger('scroll')
    await flushPromises()

    expect(wrapper.find('[data-calendar-week-scroll-affordance]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-week-scroll-edge="right"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-week-scroll-edge="left"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
