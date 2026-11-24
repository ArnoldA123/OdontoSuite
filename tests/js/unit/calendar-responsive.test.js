import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// T6 — el calendario deja de ser «una columna estrecha que no se rompe» y pasa
// a ser una superficie que reparte el ancho de verdad.
//
// jsdom no calcula layout, así que —igual que `calendar-frame.test.js`— esta
// prueba fija el contrato declarado con números: el mínimo de columna, la suma
// que decide si hay scroll, el radio de la tarjeta, el punto en el que el panel
// de pendientes baja debajo del tablero y el alto táctil de los controles. La
// medición en navegador de los cinco anchos vive en el informe de entrega.
const apiState = vi.hoisted(() => ({
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Paredes' }
  ],
  chairs: Array.from({ length: 8 }, (_, index) => ({
    id: 4 + index,
    name: `Ambiente ${index + 1}`,
    code: `AMB-00${index + 1}`
  })),
  branches: [
    { id: 1, name: 'Sede San Isidro' },
    { id: 2, name: 'Sede Surco' },
    { id: 3, name: 'Sede Miraflores' }
  ],
  appointments: [
    {
      id: 1,
      scheduled_at: '2026-05-13T09:00:00',
      ends_at: '2026-05-13T10:00:00',
      duration_minutes: 60,
      status: 'arrived',
      checked_in_at: '2026-05-13T08:56:00',
      user_id: 2,
      patient: { id: 7, first_name: 'Ana', last_name: 'Torres' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
      appointment_type: { id: 3, name: 'Control' }
    },
    {
      id: 2,
      scheduled_at: '2026-05-13T11:00:00',
      ends_at: '2026-05-13T11:30:00',
      duration_minutes: 30,
      status: 'scheduled',
      user_id: 3,
      patient: { id: 8, first_name: 'Luis', last_name: 'Ramírez' },
      user: { id: 3, name: 'Dr. Paredes' },
      dental_chair: { id: 5, name: 'Ambiente 2', code: 'AMB-002' },
      appointment_type: { id: 3, name: 'Control' }
    }
  ]
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
          'create.appointment',
          'manage.appointment',
          'complete.appointment',
          'check_in.appointment',
          'start.consultation',
          'announce.patient',
          'view.user',
          'view.dental_chair',
          'view.branch',
          'view.cash_register'
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
  BOARD_COLUMN_MIN_PX,
  BOARD_GUTTER_PX
} from '../../../resources/js/modules/appointments/calendarBoardMath.js'
import {
  CALENDAR_VIEW_STORAGE_KEY,
  DEFAULT_CALENDAR_VIEW,
  initialCalendarView
} from '../../../resources/js/modules/appointments/calendarDateMath.js'

const PAGE_SOURCE = readFileSync(
  resolve(process.cwd(), 'resources/js/modules/appointments/CalendarPage.vue'),
  'utf8'
)

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const radiusOf = element => element.style.borderRadius || element.style.borderRadius === ''

beforeEach(() => {
  window.localStorage.clear()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 4, 13, 10, 12, 0))
})

afterEach(() => {
  window.localStorage.clear()
  vi.useRealTimers()
})

describe('vista inicial del calendario (T6)', () => {
  it('abre en Día cuando no hay preferencia guardada', async () => {
    const wrapper = await mountPage()

    expect(DEFAULT_CALENDAR_VIEW).toBe('day')
    expect(CALENDAR_VIEW_STORAGE_KEY).toBe('calendar-view')
    expect(wrapper.get('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-calendar-board]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-week-grid]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('respeta la vista guardada y descarta lo que no es una vista', async () => {
    expect(initialCalendarView(null)).toBe('day')
    expect(initialCalendarView('')).toBe('day')
    expect(initialCalendarView('trimestre')).toBe('day')
    expect(initialCalendarView('week')).toBe('week')

    window.localStorage.setItem(CALENDAR_VIEW_STORAGE_KEY, 'month')
    const wrapper = await mountPage()

    expect(wrapper.get('[data-calendar-view="month"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-calendar-month-grid]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('recuerda la vista que se elige a mano', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="week"]').trigger('click')
    await flushPromises()
    expect(window.localStorage.getItem(CALENDAR_VIEW_STORAGE_KEY)).toBe('week')

    // La siguiente entrada a la agenda abre donde el usuario dejó.
    const remounted = await mountPage()
    expect(remounted.get('[data-calendar-view="week"]').attributes('aria-pressed')).toBe('true')

    remounted.unmount()
    wrapper.unmount()
  })
})

describe('el tablero reparte el ancho disponible (T6)', () => {
  it('declara 176 px por columna y sólo desplaza cuando la suma no cabe', async () => {
    const wrapper = await mountPage()

    const scroll = wrapper.get('[data-calendar-board-scroll]')
    // El desborde vive dentro del tablero: es el único contenedor que se mueve.
    expect(scroll.classes()).toContain('overflow-auto')

    expect(BOARD_COLUMN_MIN_PX).toBe(176)
    expect(BOARD_GUTTER_PX).toBe(56)

    const columns = Number(scroll.attributes('data-calendar-board-columns'))
    expect(columns).toBe(8)
    expect(scroll.attributes('data-calendar-board-column-min')).toBe('176')
    expect(scroll.attributes('data-calendar-board-gutter')).toBe('56')
    // 56 + 8 × 176: por debajo de eso hay scroll; por encima, las columnas
    // reparten el espacio con `1fr` y no sobra ni falta nada.
    expect(scroll.attributes('data-calendar-board-min-width')).toBe(
      String(BOARD_GUTTER_PX + 8 * BOARD_COLUMN_MIN_PX)
    )

    const headerRow = wrapper.get('[data-calendar-board-scroll] > div > div:first-child')
    expect(headerRow.element.style.gridTemplateColumns).toContain('minmax(176px, 1fr)')
    expect(headerRow.element.style.minWidth).toBe(`${BOARD_GUTTER_PX + 8 * BOARD_COLUMN_MIN_PX}px`)
    // Ninguna columna declara ancho fijo: el reparto es del contenedor.
    expect(headerRow.element.style.gridTemplateColumns).not.toMatch(/\d+px \d+px/)

    wrapper.unmount()
  })

  it('con pendientes, el panel comparte fila sólo desde 1600', async () => {
    const wrapper = await mountPage()

    const layout = wrapper.get('[data-calendar-pending]').element.parentElement
    expect(layout.getAttribute('data-calendar-board-layout')).toBe('split-from-1600')
    // Una sola columna hasta 1600: el tablero se queda con todo el ancho y el
    // panel aparece debajo. Ni `lg` (1024) ni 1440 ponen el panel a un lado.
    expect(layout.className).not.toContain('lg:grid-cols')
    expect(layout.className).not.toContain('min-[1440px]')
    expect(layout.className).toContain('min-[1600px]:grid-cols-[minmax(0,1fr)_288px]')

    wrapper.unmount()
  })

  // T6 (ronda 2), defecto medido: sin ninguna sección con contenido el `aside`
  // montaba vacío y su columna seguía reservada —288 px de agujero mientras el
  // tablero sacaba scroll horizontal—.
  it('sin pendientes no hay aside ni columna reservada', async () => {
    const original = apiState.appointments
    // `confirmed` no entra en «En sala» (arrived) ni en «Sin confirmar»
    // (scheduled), así que el panel se queda sin secciones y el tablero sigue
    // pintando sus columnas.
    apiState.appointments = [original[0] && { ...original[0], status: 'confirmed' }].filter(Boolean)

    try {
      const wrapper = await mountPage()

      expect(wrapper.find('[data-calendar-pending]').exists()).toBe(false)
      const layout = wrapper.get('[data-calendar-board]').element.parentElement
      expect(layout.getAttribute('data-calendar-board-layout')).toBe('single')
      expect(layout.className).not.toContain('grid-cols')
      // El tablero sigue siendo el único hijo de la rejilla: una sola columna
      // de layout en todas las anchuras, sin el hueco de 288 px.
      expect(layout.children).toHaveLength(1)
      expect(wrapper.find('[data-calendar-board-scroll]').exists()).toBe(true)
      expect(wrapper.findAll('[data-calendar-board-column]').length).toBeGreaterThan(0)

      wrapper.unmount()
    } finally {
      apiState.appointments = original
    }
  })
})

describe('un solo lenguaje de esquinas (T6)', () => {
  it('la tarjeta del tablero recorta rejilla y franja de cabecera a su radio', async () => {
    const wrapper = await mountPage()

    const board = wrapper.get('[data-calendar-board]')
    // `rounded-card` no existe en la escala de radios: la tarjeta se pintaba a
    // 90° y la rejilla remataba en ángulo recto sobre el contenedor redondeado.
    expect(board.classes()).not.toContain('rounded-card')
    expect(radiusOf(board.element)).toBe('var(--radius-card-lg)')
    expect(board.classes()).toContain('overflow-hidden')

    const scroll = board.get('[data-calendar-board-scroll]')
    const inner = scroll.element.firstElementChild
    const headerStrip = inner.children[0]
    const bodyGrid = inner.children[1]
    // Ni la rejilla ni su franja de cabecera declaran radio propio: el corte lo
    // hace la tarjeta, así que las esquinas coinciden con las suyas.
    for (const node of [scroll.element, headerStrip, bodyGrid]) {
      expect(node.style.borderRadius).toBeFalsy()
      expect(node.className).not.toMatch(/\brounded-/)
    }

    wrapper.unmount()
  })

  it('Semana y Mes siguen el mismo criterio y los controles comparten el radio de 10', async () => {
    const wrapper = await mountPage()

    // Semana.
    await wrapper.get('[data-calendar-view="week"]').trigger('click')
    await flushPromises()
    const weekCard = wrapper.get('[data-calendar-week-scroll]').element.parentElement.parentElement
    expect(weekCard.getAttribute('style')).toContain('border-radius: var(--radius-card-lg)')
    expect(weekCard.className).toContain('overflow-hidden')
    const weekScroll = wrapper.get('[data-calendar-week-scroll]')
    expect(weekScroll.element.style.borderRadius).toBeFalsy()
    const weekBlock = wrapper.get('[data-calendar-week-grid] [data-calendar-appointment="1"]')
    expect(weekBlock.classes()).toContain('rounded-ios')

    // Mes.
    await wrapper.get('[data-calendar-view="month"]').trigger('click')
    await flushPromises()
    const monthCard = wrapper.get('[data-calendar-month-grid]')
    expect(monthCard.attributes('style')).toContain('border-radius: var(--radius-card-lg)')
    expect(monthCard.classes()).toContain('overflow-hidden')
    const monthScroll = wrapper.get('[data-calendar-month-scroll]')
    expect(monthScroll.element.style.borderRadius).toBeFalsy()
    const monthChip = wrapper.get('[data-calendar-month-scroll] [data-calendar-appointment="1"]')
    expect(monthChip.classes()).toContain('rounded-ios')
    expect(monthChip.classes()).not.toContain('rounded-sm')

    wrapper.unmount()
  })

  it('bloques, huecos, chips y la píldora de ahora usan el radio de control', async () => {
    const wrapper = await mountPage()

    const blocks = wrapper.findAll('[data-calendar-board] [data-calendar-appointment]')
    expect(blocks.length).toBeGreaterThan(0)
    for (const block of blocks) {
      expect(block.classes()).toContain('rounded-ios')
      expect(block.classes()).not.toContain('rounded-md')
    }

    for (const slot of wrapper.findAll('[data-calendar-free-slot]')) {
      expect(slot.classes()).toContain('rounded-ios')
    }

    const nowPill = wrapper.get('[data-calendar-now-pill]')
    expect(nowPill.classes()).toContain('rounded-ios')

    // Nada de esquinas vivas dentro de una superficie redondeada: en la
    // plantilla no sobrevive la clase que no genera CSS.
    expect(PAGE_SOURCE).not.toContain('rounded-card ')
    expect(PAGE_SOURCE).not.toContain('rounded-card"')

    wrapper.unmount()
  })
})

describe('contrato responsive medido (T6)', () => {
  it('las herramientas pueden ocupar dos filas por debajo de 1024', async () => {
    const wrapper = await mountPage()

    const filterRow = wrapper.get('[data-calendar-filter-row]')
    expect(filterRow.classes()).toEqual(expect.arrayContaining(['flex-wrap']))
    expect(filterRow.classes()).toEqual(expect.arrayContaining(['lg:flex-nowrap']))

    const pills = wrapper.get('[data-calendar-professional-pills]')
    // Las pastillas se quedan con su propia fila y se desplazan solas.
    expect(pills.classes()).toContain('overflow-x-auto')
    expect(pills.classes()).toEqual(expect.arrayContaining(['w-full', 'lg:w-auto']))

    // El título nunca se trunca: envuelve en móvil y no se corta desde `md`.
    const title = wrapper.get('[data-calendar-title]')
    expect(title.classes()).toContain('whitespace-normal')
    expect(title.classes()).toContain('md:whitespace-nowrap')
    expect(title.classes()).not.toContain('truncate')

    wrapper.unmount()
  })

  // T6 (ronda 2), defecto medido a 2559: las celdas se estiraban pero el
  // contenido quedaba pegado a la izquierda, así que la franja se leía como
  // siete textos sueltos separados por huecos. Ahora es una rejilla de siete
  // columnas iguales desde `md` y cada celda reparte su contenido de borde a
  // borde.
  it('la franja de días es una rejilla de siete columnas iguales desde md', async () => {
    const wrapper = await mountPage()

    const strip = wrapper.get('[data-calendar-week-strip]')
    expect(strip.classes()).toContain('grid')
    expect(strip.classes()).toContain('md:grid-cols-7')
    expect(strip.classes()).toContain('md:grid-flow-row')
    // En móvil conserva su scroll propio con celdas de 96 px como mínimo.
    expect(strip.classes()).toEqual(
      expect.arrayContaining(['grid-flow-col', 'auto-cols-[minmax(96px,1fr)]', 'overflow-x-auto'])
    )
    expect(strip.classes()).toContain('md:overflow-visible')
    // Ni `flex` ni `flex-1`: el ancho lo manda la pista de la rejilla.
    expect(strip.classes()).not.toContain('flex')

    const cells = strip.findAll('[data-calendar-strip-day]')
    expect(cells).toHaveLength(7)
    for (const cell of cells) {
      expect(cell.classes()).not.toContain('flex-1')
      // Segunda línea centrada con el número y, a su lado, contador y HOY.
      const lines = cell.element.children
      expect(lines).toHaveLength(2)
      expect(lines[1].className).toContain('justify-center')
      expect(lines[1].className).not.toContain('justify-between')
      if (cell.attributes('data-calendar-strip-count-value') !== '0') {
        const count = cell.get('[data-calendar-strip-count]')
        expect(count.classes()).toContain('text-[10px]')
        expect(lines[1].lastElementChild).toBe(count.element)
      } else {
        expect(cell.find('[data-calendar-strip-count]').exists()).toBe(false)
      }
    }

    wrapper.unmount()
  })

  // T6 (ronda 2), defecto medido: a las 08:24 el globo de «ahora» se montaba
  // sobre el «08:00» del gutter. La etiqueta de la hora que el globo puede
  // tocar se retira (el globo ya dice la hora) y el globo no invade el gutter.
  it('la píldora de ahora no puede tapar la etiqueta de la hora', async () => {
    const wrapper = await mountPage()

    // Reloj fijado a las 10:12: la línea cae a 14,4 px de la hora en punto, así
    // que la caja del globo (20 px de alto) y la de la etiqueta (16) se tocan.
    const pill = wrapper.get('[data-calendar-now-pill]')
    expect(pill.text()).toBe('10:12')
    expect(wrapper.find('[data-calendar-hour-label="10"]').exists()).toBe(false)
    // Las demás horas conservan su etiqueta: sólo se retira la que choca.
    expect(wrapper.get('[data-calendar-hour-label="9"]').text()).toBe('09:00')
    expect(wrapper.get('[data-calendar-hour-label="11"]').text()).toBe('11:00')

    // El globo vive dentro del ancho de la columna de horas: empieza donde
    // termina el gutter (`left: boardGutterPx`), nunca encima de las etiquetas.
    const line = wrapper.get('[data-calendar-now-line]')
    expect(line.element.style.left).toBe(`${BOARD_GUTTER_PX}px`)
    expect(BOARD_GUTTER_PX).toBe(56)

    wrapper.unmount()
  })

  it('los controles táctiles llegan a 44 px y la franja se desplaza sola', async () => {
    const wrapper = await mountPage()

    for (const view of ['day', 'week', 'month']) {
      const button = wrapper.get(`[data-calendar-view="${view}"]`)
      expect(button.classes(), view).toContain('min-h-[44px]')
      expect(button.classes(), view).toContain('lg:min-h-[28px]')
    }

    const strip = wrapper.get('[data-calendar-week-strip]')
    // T6b: la franja mide 56 px, así que el control táctil de cada día pasa de
    // 44 px a 56 en todas las anchuras.
    expect(strip.classes()).toContain('h-14')
    expect(strip.classes()).toContain('overflow-x-auto')

    const announce = wrapper.get('[data-calendar-announce]')
    expect(announce.classes()).toContain('min-h-[44px]')

    wrapper.unmount()
  })

  it('el alto del tablero se mide en dvh y conserva vh como respaldo', async () => {
    const wrapper = await mountPage()

    // Las dos declaraciones viajan en el mismo atributo: el navegador se queda
    // con la que entiende (dvh) y el vh sobrevive donde dvh no existe.
    // T6b: la banda de la página creció (franja de 56 px y 20 px de aire), así
    // que el recorte del tablero baja otros 50 px para que el documento siga
    // por debajo de 1.150 px.
    expect(PAGE_SOURCE).toContain(
      'max-height: calc(100vh - 350px); max-height: calc(100dvh - 350px)'
    )

    const board = wrapper.get('[data-calendar-board-scroll]')
    expect(board.element.style.maxHeight).toBe('calc(100dvh - 350px)')

    await wrapper.get('[data-calendar-view="week"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-calendar-week-scroll]').element.style.maxHeight).toBe(
      'calc(100dvh - 350px)'
    )

    await wrapper.get('[data-calendar-view="month"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-calendar-month-scroll]').element.style.maxHeight).toBe(
      'calc(100dvh - 350px)'
    )

    wrapper.unmount()
  })

  it('la rejilla del mes conserva un ancho legible en su propio scroll', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="month"]').trigger('click')
    await flushPromises()

    const scroll = wrapper.get('[data-calendar-month-scroll]')
    expect(scroll.classes()).toContain('overflow-auto')
    const grid = wrapper.get('[data-calendar-month-scroll] > div:last-child')
    expect(grid.classes()).toContain('min-w-[560px]')

    wrapper.unmount()
  })
})

describe('aire, texto y ritmo de la superficie (T6b)', () => {
  // «En la superficie» = lo que se lee y lo que se anuncia: el texto renderizado
  // más los `aria-label`/`title`, que no viven en `text()`.
  const countPhrase = (wrapper, phrase = 'Sin citas') => {
    const parts = [wrapper.text()]
    for (const node of wrapper.findAll('[aria-label], [title]')) {
      parts.push(node.attributes('aria-label') || '', node.attributes('title') || '')
    }
    return parts.join(' ').split(phrase).length - 1
  }

  it('la franja no imprime «Sin citas» y sólo cuenta cuando hay citas', async () => {
    const wrapper = await mountPage()

    const strip = wrapper.get('[data-calendar-week-strip]')
    // Fila de 56 px y celdas con 12/8 de padding.
    expect(strip.classes()).toContain('h-14')
    expect(strip.text()).not.toContain('Sin citas')
    expect(strip.text()).not.toContain('cita')

    const cells = strip.findAll('[data-calendar-strip-day]')
    expect(cells).toHaveLength(7)
    for (const cell of cells) {
      expect(cell.text()).not.toContain('Sin citas')
      expect(cell.classes()).toEqual(expect.arrayContaining(['px-3', 'py-2']))
      const lines = cell.element.children
      expect(lines).toHaveLength(2)
      // Segunda línea centrada: nada de repartir a los extremos.
      expect(lines[1].className).not.toContain('justify-between')
      expect(lines[1].className).toContain('justify-center')
      // Número 17/650.
      const number = lines[1].firstElementChild
      expect(number.className).toContain('text-[17px]')
      expect(number.className).toContain('font-[650]')
    }

    // Con citas: píldora con el número. Sin citas: ninguna píldora.
    const today = wrapper.get('[data-calendar-strip-day="2026-05-13"]')
    expect(today.get('[data-calendar-strip-count]').text()).toBe('2')
    expect(today.get('[data-calendar-strip-count]').classes()).toEqual(
      expect.arrayContaining(['rounded-full', 'text-[10px]'])
    )
    expect(
      wrapper.find('[data-calendar-strip-day="2026-05-14"] [data-calendar-strip-count]').exists()
    ).toBe(false)
    // La etiqueta accesible tampoco repite la frase.
    expect(
      wrapper.get('[data-calendar-strip-day="2026-05-14"]').attributes('aria-label')
    ).not.toContain('Sin citas')

    wrapper.unmount()
  })

  it('no repite «Sin citas» ni en un día lleno ni en un día vacío', async () => {
    const original = apiState.appointments

    // Día lleno: dos citas en el día visible, sin una sola frase repetida.
    const full = await mountPage()
    expect(countPhrase(full)).toBe(0)
    // La cabecera de columna libre dice «Libre», no «Sin citas».
    expect(full.get('[data-calendar-board]').text()).toContain('Libre')
    full.unmount()

    // Día vacío dentro de una semana con citas: un solo bloque centrado en el
    // tablero, con su acción, y ni una columna con texto de vacío.
    apiState.appointments = original.map(appointment => ({
      ...appointment,
      scheduled_at: appointment.scheduled_at.replace('2026-05-13', '2026-05-15'),
      ends_at: appointment.ends_at.replace('2026-05-13', '2026-05-15')
    }))
    try {
      const empty = await mountPage()
      expect(countPhrase(empty)).toBe(1)
      const block = empty.get('[data-calendar-day-empty]')
      expect(block.text()).toContain('Sin citas este día')
      expect(block.text()).toContain('Nueva cita')
      // El tablero no pinta sus columnas cuando el día está vacío.
      expect(empty.find('[data-calendar-board-scroll]').exists()).toBe(false)
      expect(empty.findAll('[data-calendar-board-column]')).toHaveLength(0)
      empty.unmount()
    } finally {
      apiState.appointments = original
    }
  })

  it('la cabecera de columna no imprime el código y sí un estado de 11 px', async () => {
    const wrapper = await mountPage()

    const header = wrapper.get('[data-calendar-board-column="chair-4"]')
    const name = header.get('[data-calendar-column-name]')
    expect(name.classes()).toEqual(expect.arrayContaining(['text-[13px]', 'font-[650]']))
    // El código sale de la cabecera visible y vive en el `title`.
    expect(header.text()).not.toContain('AMB-001')
    expect(header.attributes('title')).toContain('AMB-001')
    expect(header.attributes('data-calendar-column-code')).toBe('AMB-001')

    const state = header.get('[data-calendar-column-state]')
    expect(state.element.parentElement.className).toContain('text-[11px]')
    expect(state.text()).toBe('En espera · 1 h 16 min')

    // La ocupación queda como arista de 3 px al pie de la cabecera.
    const bar = header.get('[role="progressbar"]')
    expect(bar.classes()).toContain('h-[3px]')
    expect(bar.classes()).toContain('absolute')
    expect(header.element.children).toHaveLength(3)

    wrapper.unmount()
  })

  it('el ritmo vertical y las herramientas tienen el aire declarado', async () => {
    const wrapper = await mountPage()

    // 6 px entre título y subtítulo.
    const context = wrapper.get('[data-calendar-context]')
    expect(context.classes()).toContain('mt-1.5')
    // 20 px entre la cabecera y las herramientas, y 20 entre herramientas y tablero.
    expect(wrapper.get('[data-calendar-tools]').classes()).toContain('mt-5')
    expect(wrapper.get('[data-calendar-shell="grid"]').classes()).toContain('mt-5')

    // Cabecera del tablero con 12/14.
    const header = wrapper.get('[data-calendar-board-column="chair-4"]')
    expect(header.classes()).toEqual(expect.arrayContaining(['px-3.5', 'py-3']))

    // Pastillas de 32 px con `px-3` y `gap-2`; grupos separados por 12.
    const pills = wrapper.get('[data-calendar-professional-pills]')
    expect(pills.classes()).toContain('gap-2')
    const row = wrapper.get('[data-calendar-filter-row]')
    expect(row.classes()).toContain('gap-3')
    const all = wrapper.get('[data-calendar-professional-pill="all"]')
    expect(all.classes()).toEqual(expect.arrayContaining(['h-8', 'px-3']))
    // La leyenda respira.
    expect(wrapper.get('[data-calendar-legend]').classes()).toContain('gap-4')

    wrapper.unmount()
  })

  it('un hueco largo no imprime texto pero conserva su medida', async () => {
    const wrapper = await mountPage()

    const gap = wrapper.get('[data-calendar-free-gap]')
    expect(gap.text()).toBe('')
    expect(gap.attributes('data-calendar-free-gap-minutes')).toBeTruthy()

    wrapper.unmount()
  })
})

// T7 — auditoría visual de la superficie servida: señales de desborde, lectura
// agrupada de la franja, dos líneas en los bloques con alto, objetivos táctiles
// del mes y duraciones legibles.
describe('auditoría visual (T7)', () => {
  const measureOverflow = (element, scrollWidth, clientWidth) => {
    Object.defineProperty(element, 'scrollWidth', { configurable: true, value: scrollWidth })
    Object.defineProperty(element, 'clientWidth', { configurable: true, value: clientWidth })
  }

  it('el bloque de la franja se lee agrupado y HOY va pegado al número', async () => {
    const wrapper = await mountPage()

    const cells = wrapper.findAll('[data-calendar-strip-day]')
    for (const cell of cells) {
      // Caja con margen interno y contenido centrado.
      expect(cell.classes()).toEqual(
        expect.arrayContaining(['px-3', 'py-2', 'items-center', 'justify-center'])
      )
      const lines = cell.element.children
      expect(lines[0].className).toContain('justify-center')
      expect(lines[1].className).toContain('justify-center')
    }

    const today = wrapper.get('[data-calendar-strip-day="2026-05-13"]')
    const line = today.element.children[1]
    const roll = [...line.children]
    const numberIndex = roll.findIndex(node => node.textContent.trim() === '13')
    const todayIndex = roll.findIndex(
      node => node.getAttribute('data-calendar-strip-today') !== null
    )
    // HOY vive en la línea del número y arranca justo después de él: sin
    // `ml-auto` que lo empuje al borde de una celda ancha.
    expect(numberIndex).toBe(0)
    expect(todayIndex).toBe(numberIndex + 1)
    expect(roll[todayIndex].className).not.toContain('ml-auto')
    expect(roll[todayIndex].className).toContain('whitespace-nowrap')

    wrapper.unmount()
  })

  it('la franja, la leyenda y el tablero señalan el desborde y lo retiran al final', async () => {
    // La franja y la leyenda se miden al montar/tamaño; el tablero sólo existe
    // cuando llegan las citas, así que su primera medida tiene que ocurrir con
    // la rejilla ya pintada.
    const wrapper = await mountPage()

    for (const [hook, edgePrefix] of [
      ['[data-calendar-week-strip]', 'data-calendar-strip-scroll-edge'],
      ['[data-calendar-legend]', 'data-calendar-legend-scroll-edge']
    ]) {
      const scroller = wrapper.get(hook)
      // Sin desborde medido no hay degradado.
      expect(wrapper.find(`[${edgePrefix}="right"]`).exists()).toBe(false)
      measureOverflow(scroller.element, 900, 320)
      await scroller.trigger('scroll')
      await flushPromises()
      expect(wrapper.find(`[${edgePrefix}="right"]`).exists(), hook).toBe(true)
      // Al llegar al final el aviso desaparece.
      scroller.element.scrollLeft = 580
      await scroller.trigger('scroll')
      await flushPromises()
      expect(wrapper.find(`[${edgePrefix}="right"]`).exists(), hook).toBe(false)
      expect(wrapper.find(`[${edgePrefix}="left"]`).exists(), hook).toBe(true)
    }

    // Tablero: misma señal, con su degradado y su aviso.
    const board = wrapper.get('[data-calendar-board-scroll]')
    expect(wrapper.find('[data-calendar-board-scroll-edge="right"]').exists()).toBe(false)
    measureOverflow(board.element, 1288, 1036)
    await board.trigger('scroll')
    await flushPromises()
    expect(wrapper.find('[data-calendar-board-scroll-edge="right"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-board-scroll-affordance]').exists()).toBe(true)
    board.element.scrollLeft = 252
    await board.trigger('scroll')
    await flushPromises()
    expect(wrapper.find('[data-calendar-board-scroll-edge="right"]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-board-scroll-affordance]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('el tablero mide su desborde cuando la rejilla aparece, sin esperar a un scroll', async () => {
    // jsdom no calcula layout: se fuerza el ancho del viewport para que la
    // primera medida —la del montaje— vea desborde.
    const proto = window.Element.prototype
    const scrollWidth = Object.getOwnPropertyDescriptor(proto, 'scrollWidth')
    const clientWidth = Object.getOwnPropertyDescriptor(proto, 'clientWidth')
    Object.defineProperty(proto, 'scrollWidth', { configurable: true, get: () => 1288 })
    Object.defineProperty(proto, 'clientWidth', { configurable: true, get: () => 1036 })

    try {
      const wrapper = await mountPage()
      expect(wrapper.find('[data-calendar-board-scroll-affordance]').exists()).toBe(true)
      wrapper.unmount()
    } finally {
      if (scrollWidth) Object.defineProperty(proto, 'scrollWidth', scrollWidth)
      if (clientWidth) Object.defineProperty(proto, 'clientWidth', clientWidth)
    }
  })

  it('la leyenda vive bajo las pastillas y no corta palabras', async () => {
    const wrapper = await mountPage()

    const legend = wrapper.get('[data-calendar-legend]')
    expect(legend.classes()).toContain('justify-start')
    expect(legend.classes()).not.toContain('md:justify-end')
    expect(legend.classes()).toContain('overflow-x-auto')
    for (const entry of legend.findAll('[data-status]')) {
      expect(entry.classes()).toContain('shrink-0')
      expect(entry.classes()).toContain('whitespace-nowrap')
    }

    // El subtítulo envuelve en lugar de recortarse.
    const context = wrapper.get('[data-calendar-context]')
    expect(context.classes()).not.toContain('truncate')
    expect(context.classes()).toContain('whitespace-normal')

    wrapper.unmount()
  })

  it('los bloques de una hora admiten dos líneas de paciente y los cortos no', async () => {
    const wrapper = await mountPage()

    // El fixture trae una cita de 60 min (09:00–10:00).
    const tall = wrapper.get('[data-calendar-board] [data-calendar-appointment="1"]')
    const tallName = tall.get('[data-calendar-block-patient]')
    expect(tallName.classes()).toContain('line-clamp-2')
    expect(tallName.classes()).not.toContain('truncate')

    // La de 30 min del bloque corto conserva una sola línea.
    const shortBlock = wrapper.get('[data-calendar-board] [data-calendar-appointment="2"]')
    const shortName = shortBlock.get('[data-calendar-block-patient]')
    expect(shortName.classes()).toContain('truncate')
    expect(shortName.classes()).not.toContain('line-clamp-2')

    wrapper.unmount()
  })

  it('el mes ofrece objetivos táctiles de 32 px sin cambiar el aspecto', async () => {
    const original = apiState.appointments
    apiState.appointments = [
      ...original,
      ...[13, 14].map(hour => ({
        ...original[0],
        id: 100 + hour,
        scheduled_at: `2026-05-13T${hour}:00:00`,
        ends_at: `2026-05-13T${hour}:30:00`,
        duration_minutes: 30
      }))
    ]

    try {
      const wrapper = await mountPage()
      await wrapper.get('[data-calendar-view="month"]').trigger('click')
      await flushPromises()

      const dayButton = wrapper.get('[data-calendar-day-link]')
      expect(dayButton.classes()).toEqual(expect.arrayContaining(['h-8', 'min-w-8']))
      // El círculo visual sigue midiendo 24 px.
      const visual = dayButton.element.firstElementChild
      expect(visual.className).toContain('h-6')
      expect(visual.className).toContain('rounded-full')

      const chip = wrapper.get('[data-calendar-month-scroll] [data-calendar-appointment]')
      expect(chip.classes()).toContain('min-h-8')
      const more = wrapper.get('[data-calendar-more]')
      expect(more.classes()).toContain('min-h-8')

      wrapper.unmount()
    } finally {
      apiState.appointments = original
    }
  })

  it('el día vacío usa un bloque compacto con su acción', async () => {
    const original = apiState.appointments
    apiState.appointments = original.map(appointment => ({
      ...appointment,
      scheduled_at: appointment.scheduled_at.replace('2026-05-13', '2026-05-15'),
      ends_at: appointment.ends_at.replace('2026-05-13', '2026-05-15')
    }))
    try {
      const wrapper = await mountPage()
      const block = wrapper.get('[data-calendar-day-empty]')
      // T8: el bloque es contenido de la tarjeta del tablero —sin borde, fondo
      // ni radio propios— y conserva icono, título, frase y CTA.
      expect(block.find('[data-empty-motif]').exists()).toBe(false)
      expect(block.classes()).not.toContain('border')
      expect(block.classes()).not.toContain('rounded-ios')
      expect(block.attributes('style')).toBeUndefined()
      expect(block.text()).toContain('Nueva cita')
      expect(block.text()).toContain('Sin citas este día')
      // Un solo marco: la tarjeta que lo aloja es la única con borde y radio.
      const card = block.element.closest('[data-calendar-board]')
      expect(card).not.toBeNull()
      expect(card.className).toContain('border')
      expect(card.getAttribute('style')).toContain('border-radius: var(--radius-card-lg)')
      wrapper.unmount()
    } finally {
      apiState.appointments = original
    }
  })
})
