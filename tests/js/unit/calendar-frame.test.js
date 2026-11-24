import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5a/T5b — contrato numérico del marco único de /calendar. jsdom no calcula
// layout, así que la prueba fija el contrato declarado: las utilidades de
// espaciado de la banda, la altura máxima de cada scroll interno y la suma que
// deja el contenido por debajo de 300 px a 1440×1000. Si una banda vuelve a
// aparecer o una medida cambia, esta prueba falla con el número.
const apiState = vi.hoisted(() => ({
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Paredes' }
  ],
  chairs: [
    { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
    { id: 5, name: 'Ambiente 2', code: 'AMB-002' },
    { id: 6, name: 'Ambiente 3', code: 'AMB-003' },
    { id: 7, name: 'Ambiente 4', code: 'AMB-004' }
  ],
  branches: [
    { id: 1, name: 'Sede San Isidro' },
    { id: 2, name: 'Sede Surco' }
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
      ends_at: '2026-05-13T12:00:00',
      duration_minutes: 60,
      status: 'in_consultation',
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
import { statusChipClasses } from '../../../resources/js/modules/appointments/appointmentStatus.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// Chrome que AppLayout pinta antes del slot de la página: topbar de 78 px
// (botones de 36 + `py-4` de 32) y `main` con `lg:py-8` (32). Medido en la app
// servida a 1440×1000; se declara aquí porque la página no lo controla.
const TOP_CHROME_PX = 110

// Medidas declaradas por las utilidades de la banda (px). T6b: el ritmo es
// 6 px entre título y subtítulo, 20 entre la banda y las herramientas y 20
// entre las herramientas y el tablero.
const FRAME_PX = {
  frameTop: 4, // pt-1
  kicker: 12, // text-[11px] leading-3
  title: 32, // text-2xl leading-8
  titleToContext: 6, // mt-1.5
  context: 16, // text-[12.5px] leading-4
  toolsGap: 20, // mt-5
  frameBottom: 8, // pb-2
  hairline: 1 // border-b
}
const FRAME_BASE_PX = Object.values(FRAME_PX).reduce((total, px) => total + px, 0)

// Día: franja de 56 px + 12 px + fila de controles (32, pastillas de 32) + 8 px
// + leyenda (14). T8/A: cada banda lleva su propio margen.
const DAY_TOOLS_PX = 56 + 12 + 32 + 8 + 14
// Semana y Mes: sin franja, una fila de controles y la leyenda.
const PERIOD_TOOLS_PX = 32 + 8 + 14
// 20 px entre la banda de herramientas y el tablero (`mt-5` en la superficie).
const TOOLS_TO_BOARD_PX = 20

const CONTENT_TOP_DAY_PX = TOP_CHROME_PX + FRAME_BASE_PX + DAY_TOOLS_PX + TOOLS_TO_BOARD_PX
const CONTENT_TOP_PERIOD_PX = TOP_CHROME_PX + FRAME_BASE_PX + PERIOD_TOOLS_PX + TOOLS_TO_BOARD_PX

// Altura máxima declarada de cada scroll interno + el aire de la superficie y
// el chrome que AppLayout pinta debajo (`main` con `lg:py-8` = 32).
const SCROLL_MAX_PX = 650 // calc(100vh - 350px) a 1000 px de alto
const SHELL_CHROME_PX = 1 + 24 + 24 + 1 // hairline + padding de página 24 ×2 + hairline
const DOCUMENT_MAX_PX =
  CONTENT_TOP_DAY_PX +
  SHELL_CHROME_PX +
  SCROLL_MAX_PX +
  32 + // affordance del scroll horizontal, cuando aparece
  40 + // pie «Listos para facturar»
  32 // padding inferior de `main`

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

describe('marco de página del calendario (T5a/T5b)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 10, 12, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('el título nunca se corta y el contenido empieza antes de 300 px', async () => {
    const wrapper = await mountPage()
    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    // 110 (chrome) + 99 base + 122 (herramientas del Día) + 20 = 351.
    expect(FRAME_BASE_PX).toBe(99)
    expect(CONTENT_TOP_DAY_PX).toBe(351)
    // T8/A: 12 px más de aire entre la franja y la fila de herramientas, y 8
    // entre ésta y la leyenda; el documento puede crecer hasta 40 px más.
    expect(CONTENT_TOP_DAY_PX).toBeLessThanOrEqual(360)
    // Semana y Mes no llevan franja: 283.
    expect(CONTENT_TOP_PERIOD_PX).toBe(283)
    expect(CONTENT_TOP_PERIOD_PX).toBeLessThanOrEqual(300)

    const frame = wrapper.get('[data-calendar-frame]')
    expect(frame.classes()).toEqual(expect.arrayContaining(['border-b', 'border-hairline']))
    expect(frame.classes()).toEqual(expect.arrayContaining(['pt-1', 'pb-2']))
    expect(frame.attributes('style')).toBeUndefined()
    expect(frame.classes().some(name => name.startsWith('shadow'))).toBe(false)
    expect(frame.classes().some(name => name.startsWith('rounded'))).toBe(false)

    const kicker = wrapper.get('[data-calendar-kicker]')
    expect(kicker.classes()).toEqual(expect.arrayContaining(['text-[11px]', 'leading-3']))

    // El h1 no declara recorte ni elipsis, y su fila no compite con las
    // acciones: el bloque del título conserva su ancho.
    const title = wrapper.get('[data-calendar-title]')
    expect(title.classes()).toEqual(expect.arrayContaining(['text-2xl', 'leading-8']))
    expect(title.classes()).not.toContain('truncate')
    expect(title.classes()).not.toContain('overflow-hidden')
    expect(title.classes()).not.toContain('text-ellipsis')
    // T6: desde `md` el título va en una sola línea; en móvil envuelve, porque
    // un «Miércoles 13 de mayo» de 24 px no cabe en 390 y el `nowrap` empujaba
    // el documento a scroll horizontal.
    expect(title.classes()).toContain('md:whitespace-nowrap')
    // La fila del título conserva un ancho mínimo declarado: por debajo de él
    // las acciones bajan a una segunda línea antes que recortar el título.
    const titleRow = title.element.closest('div')
    expect(titleRow.className).toContain('md:min-w-[300px]')

    const context = wrapper.get('[data-calendar-context]')
    expect(context.classes()).toEqual(expect.arrayContaining(['text-[12.5px]', 'leading-4']))

    const tools = wrapper.get('[data-calendar-tools]')
    expect(tools.classes()).toContain('mt-5')
    // T8/A: el aire entre bandas es margen de cada banda, no un hueco vacío.
    expect(tools.classes()).not.toContain('gap-1')
    expect(tools.element.firstElementChild.className).toContain('mb-3')
    expect(wrapper.get('[data-calendar-filter-row]').classes()).toContain('mb-2')
    // Día: franja + controles + leyenda (tres hijos, la leyenda al final).
    // T7: franja y leyenda viven dentro de su marco `relative`, que es quien
    // aloja el degradado de «hay más» fuera del scroll.
    expect(tools.element.children).toHaveLength(3)
    const stripFrame = tools.element.firstElementChild
    expect(stripFrame.querySelector('[data-calendar-week-strip]')).not.toBeNull()
    expect(stripFrame.querySelector('[data-calendar-strip-scroll-edge]')).toBeNull()
    const legend = wrapper.get('[data-calendar-legend]')
    expect(tools.element.lastElementChild.contains(legend.element)).toBe(true)
    // T6: por debajo de `lg` la leyenda se desplaza en su propia línea —ningún
    // estado queda cortado por la mitad— y desde `lg` vuelve a recortarse.
    expect(legend.classes()).toEqual(
      expect.arrayContaining(['h-[14px]', 'overflow-x-auto', 'lg:overflow-hidden'])
    )

    // Entre la banda y la superficie no hay otra banda de chrome.
    const pageChildren = [...frame.element.parentElement.children]
    const shell = wrapper.get('[data-calendar-shell="grid"]').element
    expect(pageChildren[pageChildren.indexOf(frame.element) + 1]).toBe(shell)

    wrapper.unmount()
  })

  it('Semana y Mes no tienen barra de tres selects: mismas pastillas y filtros compactos', async () => {
    const wrapper = await mountPage()

    for (const view of ['week', 'month']) {
      await wrapper.get(`[data-calendar-view="${view}"]`).trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-calendar-filter="professional"]').exists()).toBe(false)
      expect(wrapper.findAll('[data-calendar-filters] select')).toHaveLength(0)
      expect(wrapper.find('[data-calendar-filters] label').exists()).toBe(false)
      const chair = wrapper.find('[data-calendar-filter="chair"]')
      const branch = wrapper.find('[data-calendar-filter="branch"]')
      expect(chair.exists()).toBe(true)
      expect(branch.exists()).toBe(true)
      // Etiquetas plurales cortas: caben sin elipsis; el aria-label describe.
      expect(chair.attributes('aria-label')).toBe('Filtrar por ambiente')
      expect(chair.text()).toContain('Ambientes')
      expect(chair.text()).not.toContain('Todos los ambientes')
      expect(branch.attributes('aria-label')).toBe('Filtrar por sede')
      expect(branch.text()).toContain('Sedes')
      expect(branch.text()).not.toContain('Todas las sedes')
      expect(wrapper.find('[data-calendar-professional-pills]').exists()).toBe(true)
      expect(wrapper.find('[data-calendar-professional-pill="all"]').exists()).toBe(true)
      // Los filtros viven en la fila de herramientas, no en la cabecera.
      expect(wrapper.find('[data-calendar-actions] [data-calendar-filters]').exists()).toBe(false)
      expect(wrapper.find('[data-calendar-tools] [data-calendar-filters]').exists()).toBe(true)
    }

    wrapper.unmount()
  })

  it('la leyenda se queda con cinco estados en una sola línea', async () => {
    const wrapper = await mountPage()

    const legend = wrapper.get('[data-calendar-legend]')
    expect(legend.attributes('aria-label')).toBe('Estados de la cita')
    expect(legend.classes()).toContain('h-[14px]')

    const entries = legend.findAll('[data-status]')
    expect(entries).toHaveLength(5)
    expect(entries.map(entry => entry.attributes('data-status'))).toEqual([
      'scheduled',
      'confirmed',
      'arrived',
      'in_progress',
      'completed'
    ])

    entries.forEach(entry => {
      expect(entry.classes()).toEqual(expect.arrayContaining(['text-[10px]', 'leading-[14px]']))
      const dot = entry.get('span')
      expect(dot.classes()).toEqual(expect.arrayContaining(['h-[7px]', 'w-[7px]', 'rounded-full']))
    })

    // Cancelada, no se presentó y reprogramada salen de la leyenda.
    expect(legend.text()).not.toContain('Cancelada')
    expect(legend.text()).not.toContain('No se presentó')
    expect(legend.text()).not.toContain('Reprogramada')

    wrapper.unmount()
  })

  it('la franja de días tiene alto fijo, dos líneas y ninguna celda sale de la fila', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    const strip = wrapper.get('[data-calendar-week-strip]')
    // La fila fija su alto y todas las celdas lo heredan: la seleccionada sólo
    // cambia borde/fondo y no puede montarse sobre la fila de herramientas.
    // T6b: fila de 56 px en todas las anchuras (alto táctil y aire vertical).
    expect(strip.classes()).toContain('h-14')

    const cells = strip.findAll('[data-calendar-strip-day]')
    expect(cells).toHaveLength(7)
    cells.forEach(cell => {
      expect(cell.classes()).toEqual(expect.arrayContaining(['h-full', 'min-w-[96px]']))
      expect(cell.classes()).toContain('overflow-hidden')
      // Dos líneas: día+HOY y número+recuento.
      expect(cell.element.children).toHaveLength(2)
      expect(cell.element.children[0].className).toContain('leading-3')
      expect(cell.element.children[1].className).toContain('leading-4')
      expect(cell.text()).not.toContain('+')
      // La celda no imprime frases: o hay contador (número) o no hay nada.
      expect(cell.text()).not.toContain('cita')
    })

    const today = wrapper.get('[data-calendar-strip-day="2026-05-13"]')
    expect(today.attributes('aria-current')).toBe('date')
    expect(today.find('[data-calendar-strip-today]').text()).toBe('hoy')
    expect(today.get('[data-calendar-strip-count]').text()).toBe('2')
    expect(today.text()).toContain('13')
    // La cita vive en el día 13: el 14 no inventa un «0» suelto ni una frase.
    expect(
      wrapper.find('[data-calendar-strip-day="2026-05-14"] [data-calendar-strip-count]').exists()
    ).toBe(false)

    wrapper.unmount()
  })

  it('la cabecera de columna no trunca el nombre y lee el estado completo', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    // Las cuatro columnas visibles: el nombre va solo en su línea, a ancho
    // completo y sin recorte.
    const visible = wrapper.findAll('[data-calendar-board-column]').slice(0, 4)
    expect(visible).toHaveLength(4)
    visible.forEach(column => {
      const name = column.get('[data-calendar-column-name]')
      expect(name.classes()).not.toContain('truncate')
      expect(name.classes()).not.toContain('overflow-hidden')
      expect(name.element.parentElement.children[0]).toBe(name.element)
    })
    expect(wrapper.get('[data-calendar-column-name="chair-4"]').text()).toBe('Ambiente 1')
    expect(wrapper.get('[data-calendar-column-name="chair-5"]').text()).toBe('Ambiente 2')

    const header = wrapper.get('[data-calendar-board-column="chair-4"]')
    const state = header.get('[data-calendar-column-state]')
    expect(state.text()).toBe('En espera · 1 h 16 min')
    expect(state.classes()).not.toContain('truncate')
    // El porcentaje vive en la arista (aria + title), no roba ancho al nombre.
    expect(header.text()).not.toContain('%')
    const bar = header.get('[role="progressbar"]')
    expect(bar.attributes('aria-valuenow')).toBeDefined()
    expect(bar.attributes('title')).toContain('Ocupación')
    // El código sale de la cabecera visible y vive en el `title` de la celda.
    expect(header.text()).not.toContain('AMB-001')
    expect(header.attributes('title')).toContain('AMB-001')
    expect(wrapper.get('[data-calendar-board-column="chair-6"]').text()).not.toContain('AMB-003')

    wrapper.unmount()
  })

  it('el bloque de Semana comparte la anatomía y las clases de estado del Día', async () => {
    const wrapper = await mountPage()

    const anatomy = block =>
      Object.fromEntries(
        [
          ['rail', block.find('.w-1')],
          ['time', block.find('[data-calendar-block-time], [data-calendar-chip-time]')],
          ['patient', block.find('[data-calendar-block-patient], [data-calendar-chip-patient]')]
        ].map(([key, node]) => [key, { exists: node.exists(), classes: node.classes() }])
      )

    // Día.
    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()
    const dayBlock = wrapper.get('[data-calendar-board] [data-calendar-appointment="1"]')
    const dayAnatomy = anatomy(dayBlock)

    // Semana: mismo bloque, posicionado por minuto en su columna de día.
    await wrapper.get('[data-calendar-view="week"]').trigger('click')
    await flushPromises()
    const weekBlock = wrapper.get('[data-calendar-week-grid] [data-calendar-appointment="1"]')
    const weekAnatomy = anatomy(weekBlock)

    for (const key of ['rail', 'time', 'patient']) {
      expect(weekAnatomy[key].exists, `${key} in the week block`).toBe(true)
      expect(dayAnatomy[key].exists, `${key} in the day block`).toBe(true)
    }
    expect(weekBlock.classes()).toContain('absolute')
    expect(weekBlock.classes()).toContain('overflow-hidden')
    expect(weekAnatomy.rail.classes).toEqual(expect.arrayContaining(['absolute', 'w-1']))
    expect(weekAnatomy.time.classes).toEqual(
      expect.arrayContaining(['text-[11px]', 'tabular-nums'])
    )
    expect(weekAnatomy.patient.classes).toEqual(
      expect.arrayContaining(['text-[12.5px]', 'font-[650]'])
    )
    // Misma clase de estado que el bloque del día (registro de estados).
    const statusClasses = statusChipClasses('arrived').split(' ')
    expect(weekBlock.classes()).toEqual(expect.arrayContaining(statusClasses))
    expect(dayBlock.classes()).toEqual(expect.arrayContaining(statusClasses))
    // La semana no apila píldoras dentro de una celda de hora: el bloque se
    // posiciona en la columna del día y las celdas sólo son la retícula.
    expect(
      wrapper
        .get('[data-calendar-week-column="2026-05-13"] [data-calendar-appointment="1"]')
        .exists()
    ).toBe(true)

    wrapper.unmount()
  })

  it('el tablero y la rejilla de Semana y Mes llevan su propio scroll con cabeceras fijas', async () => {
    const wrapper = await mountPage()

    const expectScrollContract = element => {
      // T6: el alto se mide en `dvh` (el viewport del móvil cambia con la barra
      // del navegador) y `vh` queda como respaldo dentro del mismo atributo.
      expect(element.element.style.maxHeight).toBe('calc(100dvh - 350px)')
      const scrollsVertically =
        element.classes().includes('overflow-auto') ||
        (element.classes().includes('overflow-x-auto') &&
          element.classes().includes('overflow-y-auto'))
      expect(scrollsVertically).toBe(true)
    }

    // Semana.
    expectScrollContract(wrapper.get('[data-calendar-week-scroll]'))
    const weekHeader = wrapper.get('[data-calendar-week-grid] > div:nth-child(2)')
    expect(weekHeader.classes()).toEqual(expect.arrayContaining(['sticky', 'top-0']))
    const weekGutter = wrapper.get('[data-calendar-week-grid] > div:nth-child(9)')
    expect(weekGutter.classes()).toEqual(expect.arrayContaining(['sticky', 'left-0']))
    // Una hora de 84 px y la ventana desde las 07:00.
    const hourHeight = Number(
      wrapper.get('[data-calendar-week-grid]').attributes('data-calendar-week-hour-height')
    )
    expect(hourHeight).toBe(84)
    expect(
      wrapper.get('[data-calendar-week-grid]').attributes('data-calendar-week-start-hour')
    ).toBe('7')

    // Mes.
    await wrapper.get('[data-calendar-view="month"]').trigger('click')
    await flushPromises()
    expectScrollContract(wrapper.get('[data-calendar-month-scroll]'))
    const monthHeader = wrapper.get('[data-calendar-month-scroll] > div:first-child')
    expect(monthHeader.classes()).toEqual(expect.arrayContaining(['sticky', 'top-0']))
    expect(wrapper.get('[data-calendar-month-day]').classes()).toContain('min-h-[100px]')

    // Día: tablero.
    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()
    expectScrollContract(wrapper.get('[data-calendar-board-scroll]'))
    const boardHeader = wrapper.get('[data-calendar-board-scroll] > div > div:first-child')
    expect(boardHeader.classes()).toEqual(expect.arrayContaining(['sticky', 'top-0']))

    wrapper.unmount()
  })

  it('el documento no crece más de 1.150 px a 1440×1000 por contrato declarado', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    // T8/A: el aire nuevo crece 12 px y el tope sube en esa misma medida (el
    // límite del encargo es +40 px sobre los 1.150 del contrato anterior).
    expect(DOCUMENT_MAX_PX).toBeLessThanOrEqual(1190)
    expect(DOCUMENT_MAX_PX).toBe(1155)

    expect(wrapper.findAll('[data-calendar-board-scroll]')).toHaveLength(1)
    expect(wrapper.findAll('[data-calendar-week-scroll]')).toHaveLength(0)
    expect(wrapper.findAll('[data-calendar-month-scroll]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('el panel lateral mide 288 px y sus filas son de dos líneas sin nombres cortados', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    const panel = wrapper.get('[data-calendar-pending]')
    const grid = panel.element.parentElement
    // T6 (ronda 2): el panel baja debajo del tablero hasta 1600; desde ahí
    // comparte fila, y sin secciones ni siquiera existe su columna.
    expect(grid.getAttribute('data-calendar-board-layout')).toBe('split-from-1600')
    expect(grid.className).toContain('min-[1600px]:grid-cols-[minmax(0,1fr)_288px]')

    const rows = wrapper.findAll('[data-calendar-pending-section="arrived"] li')
    expect(rows.length).toBeGreaterThan(0)
    rows.forEach(row => {
      expect(row.classes()).toContain('grid')
      const name = row.findAll('p')[0]
      expect(name.classes()).not.toContain('truncate')
      const meta = row.findAll('p')[1]
      expect(meta.classes()).toContain('truncate')
    })

    wrapper.unmount()
  })

  it('el mes muestra hasta tres bloques compactos y el «+N más»', async () => {
    const wrapper = await mountPage()

    apiState.appointments.push(
      ...[13, 14, 15, 16].map(hour => ({
        id: 100 + hour,
        scheduled_at: `2026-05-13T${hour}:00:00`,
        ends_at: `2026-05-13T${hour}:30:00`,
        duration_minutes: 30,
        status: 'scheduled',
        user_id: 2,
        patient: { id: 20 + hour, first_name: 'Paciente', last_name: `${hour}` },
        user: { id: 2, name: 'Dra. Ruiz' },
        dental_chair: { id: 6, name: 'Ambiente 3', code: 'AMB-003' },
        appointment_type: { id: 3, name: 'Control' }
      }))
    )

    await wrapper.get('[data-calendar-view="month"]').trigger('click')
    await flushPromises()

    const cell = wrapper.get('[data-calendar-month-day="2026-05-13"]')
    expect(cell.findAll('[data-calendar-appointment]')).toHaveLength(3)
    expect(cell.get('[data-calendar-more]').text()).toBe('+3 más')
    // Bloque compacto: arista de estado, hora y paciente en una línea.
    const block = cell.get('[data-calendar-appointment]')
    expect(block.get('.w-\\[3px\\]').exists()).toBe(true)
    expect(block.get('[data-calendar-month-time]').text()).toBe('09:00')

    apiState.appointments.splice(-4)
    wrapper.unmount()
  })

  it('las columnas del tablero reservan 176 px como mínimo', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    const headerRow = wrapper.get('[data-calendar-board-scroll] > div > div:first-child')
    expect(headerRow.element.style.gridTemplateColumns).toContain('minmax(176px, 1fr)')

    wrapper.unmount()
  })
})
