import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// La difusión de la agenda mueve UNA cita, no el rango visible.
//
// Cada `.appointment.created`/`.updated` releía `/api/appointments` con
// `per_page: 1000` y encendía el esqueleto de la página: el profesional veía
// una recarga de varios segundos por cada cita de la sede y el costo crecía con
// la agenda. El evento ya trae el `id`, así que la página lee esa cita con
// `GET /api/appointments/{id}` y hace upsert/remove sobre el arreglo leído.
//
// These cases pin the cost and the ownership rule, not the rendering: a large
// agenda (100 rows) plus one broadcast must produce exactly one point read.
const api = vi.hoisted(() => ({
  range: { value: [] },
  calls: [],
  // Fila que devuelve la lectura puntual, por id.
  points: {},
  // 403/404 por id: la cita dejó de ser visible para la sesión.
  pointStatus: {},
  // Cuando está armada, la lectura puntual de ese id queda pendiente hasta
  // `release()`. Permite medir la página mientras la lectura está en vuelo.
  deferPoint: null,
  pointReleases: []
}))

const toastMock = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn()
}))

const echoListeners = vi.hoisted(() => ({ handlers: {} }))

const authState = vi.hoisted(() => ({ role: null, id: null }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: (url, options = {}) => {
      const target = String(url)
      api.calls.push({ url: target, params: options?.params || null })

      // Lectura del rango visible: la que la difusión ya no debe disparar.
      if (target === '/api/appointments') {
        return Promise.resolve({ data: JSON.parse(JSON.stringify(api.range.value)) })
      }

      const pointRead = target.match(/^\/api\/appointments\/([^/?]+)$/)
      if (pointRead) {
        const id = pointRead[1]
        const response = () => {
          const status = api.pointStatus[id]
          if (status) {
            return Promise.reject({ status, response: { data: { message: 'No disponible' } } })
          }
          const row = api.points[id]
          if (!row) {
            return Promise.reject({
              status: 404,
              response: { data: { message: 'Cita no encontrada' } }
            })
          }
          return Promise.resolve({ data: JSON.parse(JSON.stringify(row)) })
        }

        if (api.deferPoint && String(api.deferPoint) === id) {
          return new Promise(resolve => {
            api.pointReleases.push(() => resolve(response()))
          })
        }
        return response()
      }

      // Catálogos de los filtros y del tablero: vacíos, la agenda no depende
      // de ellos.
      return Promise.resolve({ data: [] })
    },
    post: vi.fn().mockResolvedValue({ data: {} }),
    put: vi.fn().mockResolvedValue({ data: {} }),
    patch: vi.fn().mockResolvedValue({ data: {} }),
    del: vi.fn().mockResolvedValue({ data: {} }),
    setToken: () => {},
    normalizeError: error => error?.response?.data?.message || 'Error desconocido'
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: vi.fn() },
    channel: () => ({}),
    privateChannel: () => ({
      listen(name, handler) {
        const key = String(name).replace(/^\./, '')
        echoListeners.handlers[key] = [...(echoListeners.handlers[key] || []), handler]
        return this
      }
    }),
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useToast', async importOriginal => {
  const actual = await importOriginal()
  return { ...actual, useToast: () => toastMock }
})

// El guard de eco vive en el composable real; aquí sólo importa que la agenda
// marque lo que escribe, con la misma semántica de un uso.
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({}),
  markLocalAppointmentEcho: () => {},
  consumeLocalAppointmentEcho: () => true
}))

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { ref, computed } = await import('vue')
  const { default: rolePermissions } = await import('../support/role-permissions.json')

  const role = ref('administrador')
  const id = ref(1)
  authState.role = role
  authState.id = id

  const user = computed(() => ({
    id: id.value,
    name: 'Sesión',
    role: role.value,
    branch_id: null,
    permissions: rolePermissions[role.value] ?? []
  }))

  return {
    useAuth: () => ({
      user,
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      isLoading: computed(() => false),
      login: async () => {},
      logout: async () => {},
      authLogout: async () => {},
      getCurrentUser: async () => {},
      hasRole: value => role.value === value,
      hasAnyRole: values => values.includes(role.value)
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// Una agenda grande y realista: 100 citas repartidas por la semana visible
// (2026-05-11 … 2026-05-17), 30 minutos cada una, sin solapes.
const AGENDA_SIZE = 100

const pad = value => String(value).padStart(2, '0')

const localIso = (day, minutesFromMidnight) =>
  `2026-05-${pad(day)}T${pad(Math.floor(minutesFromMidnight / 60))}:${pad(
    minutesFromMidnight % 60
  )}:00`

const appointmentRow = ({ id, day = 13, minutes = 9 * 60, userId = 1, status = 'scheduled' }) => {
  const start = localIso(day, minutes)
  return {
    id,
    scheduled_at: start,
    ends_at: localIso(day, minutes + 30),
    duration_minutes: 30,
    status,
    user_id: userId,
    patient: { id: 7, first_name: 'Ana', last_name: 'Torres' },
    user: { id: userId, name: 'Dra. Ruiz' },
    dental_chair: { id: 4, name: 'Sillón 4' },
    appointment_type: { id: 4, name: 'Ortodoncia' }
  }
}

const bigAgenda = count =>
  Array.from({ length: count }, (_, index) =>
    appointmentRow({
      id: 1000 + index,
      day: 11 + (index % 7),
      minutes: 7 * 60 + Math.floor(index / 7) * 30
    })
  )

const rangeReads = () => api.calls.filter(call => call.url === '/api/appointments').length
const pointReads = () =>
  api.calls.filter(call => /^\/api\/appointments\/\d+$/.test(call.url)).length

const emit = async (eventName, payload) => {
  const handlers = echoListeners.handlers[eventName] || []
  expect(handlers.length, `la agenda debe escuchar ${eventName}`).toBeGreaterThan(0)
  for (const handler of handlers) {
    await handler(payload)
  }
  await flushPromises()
}

const createdEvent = (id, userId) => ({
  appointment: {
    id,
    user_id: userId,
    status: 'scheduled',
    scheduled_at: '2026-05-13T09:00:00',
    updated_at: '2026-05-13T08:00:00'
  }
})

const updatedEvent = (id, userId, status = 'confirmed') => ({
  appointment: {
    id,
    user_id: userId,
    status,
    scheduled_at: '2026-05-13T09:00:00',
    updated_at: '2026-05-13T09:05:00'
  }
})

const mountPage = async (role = 'administrador', id = 1) => {
  authState.role.value = role
  authState.id.value = id

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  // Semana visible con una agenda grande; la vista se declara en Semana para
  // que el rango leído sea uno solo y las 100 filas se pinten.
  window.localStorage.setItem('calendar-view', 'week')
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 4, 13, 10, 0, 0))

  api.range.value = bigAgenda(AGENDA_SIZE)
  api.calls.length = 0
  api.points = {}
  api.pointStatus = {}
  api.deferPoint = null
  api.pointReleases = []
  echoListeners.handlers = {}

  toastMock.success.mockReset()
  toastMock.error.mockReset()
  toastMock.info.mockReset()
  toastMock.warning.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
  window.localStorage.removeItem('calendar-view')
})

describe('la difusión refresca una cita, no el rango visible', () => {
  it('lee una sola cita al crearse y la inserta sin encender el esqueleto', async () => {
    const wrapper = await mountPage('odontologo', 1)
    expect(wrapper.vm.appointments).toHaveLength(AGENDA_SIZE)

    const rangeBefore = rangeReads()
    api.points[5001] = appointmentRow({ id: 5001, day: 13, minutes: 7 * 60 + 15, userId: 1 })
    // La lectura puntual queda en vuelo: mientras espera, la página no puede
    // estar en carga ni haber pedido el rango.
    api.deferPoint = '5001'

    const pending = emit('appointment.created', createdEvent(5001, 1))
    await flushPromises()

    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.find('[data-calendar-loading]').exists()).toBe(false)
    expect(rangeReads()).toBe(rangeBefore)

    api.deferPoint = null
    api.pointReleases.forEach(release => release())
    api.pointReleases = []
    await pending

    expect(pointReads()).toBe(1)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.vm.appointments).toHaveLength(AGENDA_SIZE + 1)

    // Upsert: reemplaza por id y ordena por `scheduled_at` (la nueva cae en su
    // lugar cronológico, no al final del arreglo).
    const schedule = wrapper.vm.appointments.map(row => new Date(row.scheduled_at).getTime())
    expect([...schedule].sort((left, right) => left - right)).toEqual(schedule)

    const inserted = wrapper.vm.appointments.find(row => row.id === 5001)
    expect(inserted.scheduled_at).toBe('2026-05-13T07:15:00')
    const earlier = wrapper.vm.appointments.filter(
      row => new Date(row.scheduled_at).getTime() < new Date(inserted.scheduled_at).getTime()
    )
    expect(wrapper.vm.appointments.indexOf(inserted)).toBe(earlier.length)

    expect(wrapper.find('[data-calendar-appointment="5001"]').exists()).toBe(true)
    // La difusión sólo sincroniza la fila: el aviso del hecho es del shell
    // (NotificationService). La agenda no apila un segundo toast.
    expect(toastMock.success).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('no lee nada de una cita ajena en un escritorio clínico', async () => {
    const wrapper = await mountPage('odontologo', 1)
    const rangeBefore = rangeReads()

    // La cita es de otro profesional: no entra a esta agenda, así que no hay
    // lectura que hacer (ni del rango ni puntual).
    api.points[5002] = appointmentRow({ id: 5002, userId: 9 })
    await emit('appointment.created', createdEvent(5002, 9))

    expect(pointReads()).toBe(0)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.appointments).toHaveLength(AGENDA_SIZE)
    expect(toastMock.success).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('quita la fila reasignada a otro profesional sin leerla', async () => {
    const wrapper = await mountPage('odontologo', 1)
    expect(wrapper.vm.appointments.some(row => row.id === 1000)).toBe(true)

    const rangeBefore = rangeReads()
    await emit('appointment.updated', updatedEvent(1000, 9, 'in_consultation'))

    expect(pointReads()).toBe(0)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.appointments.some(row => row.id === 1000)).toBe(false)
    expect(wrapper.find('[data-calendar-appointment="1000"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('quita la fila que se movió fuera del rango visible', async () => {
    const wrapper = await mountPage('administrador', 1)
    expect(wrapper.vm.appointments.some(row => row.id === 1001)).toBe(true)

    // La cita existe y se lee, pero su nueva fecha cae fuera del período que la
    // agenda está mirando.
    api.points[1001] = appointmentRow({ id: 1001, day: 13, minutes: 11 * 60 })
    api.points[1001].scheduled_at = '2026-06-20T11:00:00'
    api.points[1001].ends_at = '2026-06-20T11:30:00'

    const rangeBefore = rangeReads()
    await emit('appointment.updated', updatedEvent(1001, 1, 'confirmed'))

    expect(pointReads()).toBe(1)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.appointments.some(row => row.id === 1001)).toBe(false)
    wrapper.unmount()
  })

  it('quita la fila eliminada sin releer la agenda', async () => {
    const wrapper = await mountPage('administrador', 1)
    expect(wrapper.vm.appointments.some(row => row.id === 1002)).toBe(true)

    const rangeBefore = rangeReads()
    await emit('appointment.deleted', { appointment_id: 1002, user_id: 1 })

    expect(pointReads()).toBe(0)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.appointments.some(row => row.id === 1002)).toBe(false)
    wrapper.unmount()
  })

  it('trata el 403/404 de la lectura puntual como una fila que sale', async () => {
    const wrapper = await mountPage('administrador', 1)

    api.pointStatus[1003] = 404
    const rangeBefore = rangeReads()
    await emit('appointment.updated', updatedEvent(1003, 1))

    expect(pointReads()).toBe(1)
    expect(rangeReads()).toBe(rangeBefore)
    expect(wrapper.vm.appointments.some(row => row.id === 1003)).toBe(false)
    // Nunca es el error de la página: la agenda conserva su superficie.
    expect(wrapper.vm.loadError).toBeNull()
    expect(wrapper.find('[data-calendar-error]').exists()).toBe(false)
    expect(toastMock.error).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('refresca el detalle abierto con el registro leído', async () => {
    const wrapper = await mountPage('odontologo', 1)
    const rangeBefore = rangeReads()

    await wrapper.find('[data-calendar-appointment="1004"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.selectedAppointment?.id).toBe(1004)

    api.points[1004] = {
      ...appointmentRow({ id: 1004, day: 13, minutes: 9 * 60 + 30, userId: 1, status: 'arrived' }),
      announced_at: '2026-05-13T09:40:00'
    }
    await emit('appointment.checked_in', updatedEvent(1004, 1, 'arrived'))

    expect(wrapper.vm.selectedAppointment.status).toBe('arrived')
    expect(wrapper.vm.selectedAppointment.announced_at).toBe('2026-05-13T09:40:00')
    expect(rangeReads()).toBe(rangeBefore)
    wrapper.unmount()
  })

  it('cierra el detalle abierto cuando la cita se elimina', async () => {
    const wrapper = await mountPage('administrador', 1)

    await wrapper.find('[data-calendar-appointment="1005"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.selectedAppointment?.id).toBe(1005)

    await emit('appointment.deleted', { appointment_id: 1005, user_id: 1 })

    expect(wrapper.vm.selectedAppointment).toBeNull()
    wrapper.unmount()
  })
})
