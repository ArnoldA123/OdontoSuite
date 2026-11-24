import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// T4 — la vista Día de /calendar es un tablero por recurso: una columna por
// ambiente (o por profesional con el interruptor «Agrupar por»), una fila por
// hora, huecos agendables con su hora y su ambiente, la línea de «ahora» y un
// panel de pendientes del turno. Esta prueba fija ese contrato sobre la página
// montada: los ganchos `data-calendar-*` son lo que el tablero promete, no sus
// clases.
const apiState = vi.hoisted(() => ({
  reads: [],
  posts: [],
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Paredes' }
  ],
  chairs: [
    { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
    { id: 5, name: 'Ambiente 2', code: 'AMB-002' },
    { id: 6, name: 'Ambiente 3', code: 'AMB-003' }
  ],
  branches: [{ id: 1, name: 'Sede San Isidro' }],
  labCases: [
    {
      id: 91,
      status: 'delivered',
      work: 'Corona de zirconio',
      delivered_at: '2026-05-13T08:00:00',
      dental_piece: { fdi_number: 26 },
      patient: { id: 7, first_name: 'Rosa', last_name: 'Huamán' }
    }
  ],
  appointments: [
    {
      id: 1,
      scheduled_at: '2026-05-13T09:00:00',
      ends_at: '2026-05-13T10:00:00',
      duration_minutes: 60,
      status: 'arrived',
      checked_in_at: '2026-05-13T09:56:00',
      user_id: 2,
      patient: { id: 7, first_name: 'Ana', last_name: 'Torres' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
      appointment_type: { id: 3, name: 'Control' }
    },
    {
      id: 2,
      scheduled_at: '2026-05-13T10:00:00',
      ends_at: '2026-05-13T11:00:00',
      duration_minutes: 60,
      status: 'in_consultation',
      user_id: 3,
      patient: { id: 8, first_name: 'Luis', last_name: 'Ramírez' },
      user: { id: 3, name: 'Dr. Paredes' },
      dental_chair: { id: 5, name: 'Ambiente 2', code: 'AMB-002' },
      procedure: { id: 9, name: 'Endodoncia' },
      appointment_type: { id: 3, name: 'Control' }
    },
    {
      id: 3,
      scheduled_at: '2026-05-13T12:00:00',
      ends_at: '2026-05-13T12:30:00',
      duration_minutes: 30,
      status: 'scheduled',
      user_id: 2,
      patient: { id: 9, first_name: 'Rosa', last_name: 'Huamán' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
      appointment_type: { id: 3, name: 'Control' }
    },
    {
      id: 4,
      scheduled_at: '2026-05-14T09:00:00',
      ends_at: '2026-05-14T09:30:00',
      duration_minutes: 30,
      status: 'scheduled',
      user_id: 2,
      patient: { id: 10, first_name: 'Mateo', last_name: 'Ríos' },
      user: { id: 2, name: 'Dra. Ruiz' },
      dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
      appointment_type: { id: 3, name: 'Control' }
    }
  ]
}))

const clone = value => JSON.parse(JSON.stringify(value))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async (url, config = {}) => {
      apiState.reads.push({ url: String(url), params: config?.params || null })
      const path = String(url)
      if (path === '/api/users/active') return { data: clone(apiState.users) }
      if (path.startsWith('/api/dental-chairs/active')) return { data: clone(apiState.chairs) }
      if (path === '/api/branches/active') return { data: clone(apiState.branches) }
      if (path === '/api/lab-cases') return { data: clone(apiState.labCases), meta: {} }
      if (path === '/api/appointments') return { data: clone(apiState.appointments), meta: {} }
      return { data: [], meta: {} }
    },
    post: async (url, payload) => {
      apiState.posts.push({ url: String(url), payload })
      return { data: {} }
    },
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
        branch_id: 1,
        permissions: [
          'manage.appointment',
          'complete.appointment',
          'check_in.appointment',
          'start.consultation',
          'announce.patient',
          'view.user',
          'view.dental_chair',
          'view.branch',
          'view.lab_case',
          'pay.pending_payment'
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
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mounted = []

const mountPage = async () => {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
  mounted.push({ wrapper, host })
  await flushPromises()
  await wrapper.find('[data-calendar-view="day"]').trigger('click')
  await flushPromises()
  return wrapper
}

const columnNames = wrapper =>
  wrapper.findAll('[data-calendar-board-column]').map(column => column.find('p').text())

const chipIds = wrapper =>
  wrapper
    .findAll('[data-calendar-appointment]')
    .map(chip => chip.attributes('data-calendar-appointment'))

describe('tablero del día (T4)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 10, 12, 0))
    apiState.reads = []
    apiState.posts = []
  })

  afterEach(() => {
    while (mounted.length) {
      const { wrapper, host } = mounted.pop()
      try {
        wrapper.unmount()
      } catch {
        // ya desmontado
      }
      host.remove()
    }
    vi.useRealTimers()
  })

  it('pinta una columna por ambiente del catálogo y sitúa cada cita en la suya', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-calendar-week-strip]').exists()).toBe(true)
    expect(wrapper.findAll('[data-calendar-strip-day]')).toHaveLength(7)
    expect(wrapper.find('[data-calendar-strip-day="2026-05-13"]').attributes('aria-current')).toBe(
      'date'
    )

    // El tablero agrupa por ambiente por omisión.
    expect(wrapper.find('[data-calendar-group-by]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-group-by-option="chair"]').attributes('aria-pressed')).toBe(
      'true'
    )

    // Los nombres son los de la fila del catálogo, nunca un número inventado.
    expect(columnNames(wrapper)).toEqual(['Ambiente 1', 'Ambiente 2', 'Ambiente 3'])

    const ambiente1 = wrapper.find('[data-calendar-board-column-body="chair-4"]')
    expect(
      ambiente1
        .findAll('[data-calendar-appointment]')
        .map(chip => chip.attributes('data-calendar-appointment'))
    ).toEqual(['1', '3'])

    const ambiente2 = wrapper.find('[data-calendar-board-column-body="chair-5"]')
    expect(ambiente2.findAll('[data-calendar-appointment]')).toHaveLength(1)

    // La cita de otro día no viaja al tablero del día visible.
    expect(chipIds(wrapper)).not.toContain('4')

    // La cita en consulta se tiñe con la arista del registro de estados.
    const enConsulta = wrapper.find('[data-calendar-appointment="2"]')
    expect(enConsulta.find('.bg-systemYellow-500').exists()).toBe(true)
    expect(enConsulta.attributes('data-calendar-block-tier')).toBe('standard')
    // T5b: el bloque imprime el TIPO de cita, no el procedimiento.
    expect(enConsulta.text()).toContain('Control')

    wrapper.unmount()
  })

  it('declara el estado de cada columna y la línea de ahora del día visible', async () => {
    const wrapper = await mountPage()

    // T5b: el estado se lee completo, sin el nombre del paciente recortado.
    expect(
      wrapper.find('[data-calendar-board-column="chair-4"] [data-calendar-column-state]').text()
    ).toBe('En espera · 16 min')
    expect(
      wrapper.find('[data-calendar-board-column="chair-5"] [data-calendar-column-state]').text()
    ).toBe('En consulta · 48 min')
    expect(
      wrapper.find('[data-calendar-board-column="chair-6"] [data-calendar-column-state]').text()
    ).toBe('Libre')

    // La cabecera de columna tiene dos líneas de texto (nombre completo arriba;
    // estado y código si cabe, abajo) más la arista de ocupación al pie.
    const header = wrapper.find('[data-calendar-board-column="chair-4"]')
    expect(header.element.children).toHaveLength(3)
    expect(header.element.children[0].textContent.trim()).toBe('Ambiente 1')
    // La cabecera imprime el nombre y el estado; el código vive en su `title`.
    expect(header.element.children[1].textContent).toContain('En espera')
    expect(header.element.children[1].textContent).not.toContain('AMB-001')
    expect(header.element.children[2].getAttribute('role')).toBe('progressbar')
    expect(header.attributes('title')).toContain('AMB-001')
    expect(wrapper.find('[data-calendar-board-column="chair-6"]').text()).not.toContain('AMB-003')
    expect(
      wrapper.find('[data-calendar-board-column="chair-6"]').attributes('data-calendar-column-code')
    ).toBe('AMB-003')

    const nowLine = wrapper.find('[data-calendar-now-line]')
    expect(nowLine.exists()).toBe(true)
    expect(wrapper.find('[data-calendar-now-pill]').text()).toBe('10:12')

    wrapper.unmount()
  })

  it('ofrece huecos agendables con su hora real y su ambiente, y abre el alta precargada', async () => {
    const wrapper = await mountPage()

    // Ambiente 1 tiene dos huecos de 120 min (07:00 y 10:00): son los que se
    // ofrecen. Un hueco mayor se declara, no se convierte en botón.
    const slot = wrapper.find(
      '[data-calendar-board-column-body="chair-4"] [data-calendar-free-slot]'
    )
    expect(slot.exists()).toBe(true)
    expect(slot.attributes('data-calendar-free-slot-start')).toBe('07:00')
    expect(slot.attributes('data-calendar-free-slot-minutes')).toBe('120')
    expect(slot.text()).toContain('Libre · 120 min')
    // Compacto: sin la palabra «Agendar» y sin elipsis; el signo «+» es la
    // affordance y el aria-label conserva la frase completa.
    expect(slot.text()).not.toContain('Agendar')
    expect(slot.text()).toContain('+')
    expect(slot.classes()).not.toContain('truncate')
    expect(slot.get('span').classes()).toContain('whitespace-nowrap')
    expect(slot.attributes('aria-label')).toContain('Ambiente 1')
    expect(slot.attributes('aria-label')).toContain('07:00')

    await slot.trigger('click')
    await flushPromises()

    const modal = wrapper.findComponent(NewAppointmentModal)
    expect(modal.props('modelValue')).toBe(true)
    // El alta existente no acepta precarga de ambiente: la hora y el espacio
    // llegan al formulario, el recurso se elige en su lista de ambientes.
    expect(modal.props('initialDate')).toBe('2026-05-13T07:00')

    wrapper.unmount()
  })

  it('declara los huecos de más de dos horas sin botón ni gancho agendable', async () => {
    const wrapper = await mountPage()

    // Ambiente 3 está libre de 07:00 a 20:00: 780 min no son una cita probable.
    const gap = wrapper.find('[data-calendar-board-column-body="chair-6"] [data-calendar-free-gap]')
    expect(gap.exists()).toBe(true)
    // T6b: el hueco no imprime texto —la columna no declara vacío por su
    // cuenta— pero conserva su medida para el contrato y la auditoría.
    expect(gap.text()).toBe('')
    expect(gap.attributes('data-calendar-free-gap-minutes')).toBe('780')

    const column = wrapper.find('[data-calendar-board-column-body="chair-6"]')
    expect(column.find('[data-calendar-free-slot]').exists()).toBe(false)
    expect(column.find('[data-calendar-free-slot-start]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('agrupa por profesional con el interruptor y filtra el tablero con las pastillas', async () => {
    const wrapper = await mountPage()

    // Pastillas del censo del día: Todos + un profesional por nombre real.
    expect(wrapper.find('[data-calendar-professional-pill="all"]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-professional-pill="2"]').text()).toContain('Dra. Ruiz')
    expect(wrapper.find('[data-calendar-professional-pill="2"]').text()).toContain('2')

    await wrapper.find('[data-calendar-professional-pill="3"]').trigger('click')
    await flushPromises()

    expect(chipIds(wrapper)).toEqual(['2'])
    expect(wrapper.find('[data-calendar-professional-pill="3"]').attributes('aria-pressed')).toBe(
      'true'
    )

    await wrapper.find('[data-calendar-professional-pill="all"]').trigger('click')
    await flushPromises()
    expect(chipIds(wrapper).sort()).toEqual(['1', '2', '3'])

    await wrapper.find('[data-calendar-group-by-option="professional"]').trigger('click')
    await flushPromises()

    expect(
      wrapper.find('[data-calendar-group-by-option="professional"]').attributes('aria-pressed')
    ).toBe('true')
    expect(columnNames(wrapper)).toEqual(['Dra. Ruiz', 'Dr. Paredes'])
    expect(
      wrapper
        .find('[data-calendar-board-column-body="professional-3"]')
        .findAll('[data-calendar-appointment]')
    ).toHaveLength(1)

    wrapper.unmount()
  })

  it('publica los pendientes del turno: en sala, sin confirmar y laboratorio listo', async () => {
    const wrapper = await mountPage()

    const arrived = wrapper.find('[data-calendar-pending-section="arrived"]')
    expect(arrived.exists()).toBe(true)
    expect(arrived.text()).toContain('Ana Torres')
    expect(arrived.text()).toContain('Ambiente 1')
    expect(arrived.text()).toContain('espera 16 min')
    // La acción de la sala usa la puerta existente del anuncio.
    await arrived.find('[data-calendar-announce="1"]').trigger('click')
    await flushPromises()
    expect(apiState.posts.map(post => post.url)).toContain('/api/appointments/1/announce')

    const unconfirmed = wrapper.find('[data-calendar-pending-section="scheduled"]')
    expect(unconfirmed.exists()).toBe(true)
    expect(unconfirmed.text()).toContain('Rosa Huamán')
    expect(unconfirmed.text()).toContain('12:00')

    const lab = wrapper.find('[data-calendar-pending-section="lab"]')
    expect(lab.exists()).toBe(true)
    expect(lab.text()).toContain('Corona de zirconio')
    expect(lab.text()).toContain('26')
    expect(lab.find('[data-calendar-lab-link]').attributes('href')).toBe('/lab-cases')

    // El pie lleva a facturar lo atendido, sin pedir nada más.
    expect(wrapper.find('[data-calendar-ready-to-bill]').attributes('href')).toBe(
      '/cash-register/ready-to-bill'
    )
    expect(apiState.reads.some(read => read.url === '/api/lab-cases')).toBe(true)

    wrapper.unmount()
  })

  it('cambia el día visible desde la franja sin volver a leer el rango', async () => {
    const wrapper = await mountPage()

    const readsBefore = apiState.reads.filter(read => read.url === '/api/appointments').length

    await wrapper.find('[data-calendar-strip-day="2026-05-14"]').trigger('click')
    await nextTick()
    await flushPromises()

    expect(wrapper.find('[data-calendar-strip-day="2026-05-14"]').attributes('aria-current')).toBe(
      'date'
    )
    // El día 14 sólo tiene la cita de Mateo: el tablero la muestra y la agenda
    // no volvió a pedir la semana.
    expect(chipIds(wrapper)).toEqual(['4'])
    expect(apiState.reads.filter(read => read.url === '/api/appointments').length).toBe(readsBefore)

    wrapper.unmount()
  })

  it('acuña la cejilla con la sede de la sesión y mantiene el enlace del pie', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-page-header-kicker]').text()).toBe('Calendario · Sede San Isidro')
    // Un solo `h1` visible en la banda (el del topbar es sr-only) y el contexto
    // del día en la misma banda compacta.
    expect(wrapper.find('[data-calendar-frame]').findAll('h1')).toHaveLength(1)
    expect(wrapper.find('[data-calendar-context]').text()).toContain('3 citas cargadas')

    wrapper.unmount()
  })
})
