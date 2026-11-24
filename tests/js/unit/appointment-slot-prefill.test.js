import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #531 — el tablero entrega el hueco con su columna: el alta adopta ese
// ambiente (o ese profesional) una sola vez, después de leer su catálogo, y lo
// ignora en silencio cuando el formulario no lo publica. La elección posterior
// del usuario nunca se revierte.
const apiState = vi.hoisted(() => ({
  reads: [],
  users: [
    { id: 2, name: 'Dra. Ruiz' },
    { id: 3, name: 'Dr. Paredes' }
  ],
  chairs: [
    { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
    { id: 5, name: 'Ambiente 2', code: 'AMB-002' },
    { id: 6, name: 'Ambiente 3', code: 'AMB-003' }
  ]
}))

const clone = value => JSON.parse(JSON.stringify(value))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async (url, config = {}) => {
      const path = String(url)
      apiState.reads.push({ url: path, params: config?.params || null })
      if (path === '/api/users/active') return { data: clone(apiState.users) }
      if (path.startsWith('/api/dental-chairs/active')) return { data: clone(apiState.chairs) }
      if (path === '/api/appointment-types/active') {
        return { data: [{ id: 3, name: 'Control', default_duration_minutes: 30 }] }
      }
      if (path === '/api/appointments') return { data: [], meta: {} }
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

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({ id: 1, name: 'Test User', role: 'administrador', branch_id: 1 })),
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      hasRole: () => true,
      hasAnyRole: () => true
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'

const mounted = []

const mountModal = async props => {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const wrapper = mount(NewAppointmentModal, {
    attachTo: host,
    props: { modelValue: true, ...props },
    // Los buscadores de procedimiento viven dentro de «Más detalles» y no son
    // objeto de esta prueba: su propio contrato ya está cubierto.
    global: {
      plugins: [uiComponents],
      stubs: { ProcedureQuickPicker: true, ProcedureCatalogPicker: true }
    }
  })
  mounted.push({ wrapper, host })
  await flushPromises()
  await flushPromises()
  return wrapper
}

const chairRows = () => [
  ...document.querySelectorAll('[data-appointment-chair-rows] [data-appointment-chair]')
]

const pressedChairs = () =>
  chairRows()
    .filter(row => row.getAttribute('aria-pressed') === 'true')
    .map(row => row.getAttribute('data-appointment-chair'))

const pressedProfessionals = () =>
  [
    ...document.querySelectorAll(
      '[data-appointment-professional-rows] [data-appointment-professional]'
    )
  ]
    .filter(row => row.getAttribute('aria-pressed') === 'true')
    .map(row => row.getAttribute('data-appointment-professional'))

describe('precarga del recurso del hueco (#531)', () => {
  beforeEach(() => {
    apiState.reads = []
  })

  afterEach(() => {
    while (mounted.length) {
      const { wrapper, host } = mounted.pop()
      try {
        wrapper.unmount()
      } finally {
        host.remove()
      }
    }
  })

  it('abre con el ambiente de la columna del hueco ya elegido', async () => {
    await mountModal({ initialDate: '2026-05-13T07:00', initialChairId: 6 })

    expect(chairRows()).toHaveLength(3)
    expect(pressedChairs()).toEqual(['6'])
    expect(pressedProfessionals()).toEqual([])
  })

  it('abre con el profesional de la columna cuando el tablero agrupa por profesional', async () => {
    await mountModal({ initialDate: '2026-05-13T07:00', initialProfessionalId: 3 })

    expect(pressedProfessionals()).toEqual(['3'])
    expect(pressedChairs()).toEqual([])
  })

  it('no propone nada cuando el alta no viene de un hueco', async () => {
    await mountModal({ initialDate: '2026-05-13T09:00' })

    expect(pressedChairs()).toEqual([])
    expect(pressedProfessionals()).toEqual([])
  })

  it('ignora en silencio un ambiente que el formulario no ofrece', async () => {
    await mountModal({ initialDate: '2026-05-13T07:00', initialChairId: 999 })

    // El catálogo cargó (los tres ambientes están) y ninguno quedó elegido: el
    // id ausente se descarta sin aviso ni error de campo.
    expect(chairRows()).toHaveLength(3)
    expect(pressedChairs()).toEqual([])
    expect(document.querySelector('[role="alert"]')).toBeNull()
  })

  it('no revierte la elección posterior del usuario', async () => {
    await mountModal({ initialDate: '2026-05-13T07:00', initialChairId: 6 })

    const other = chairRows().find(row => row.getAttribute('data-appointment-chair') === '4')
    other.click()
    await flushPromises()

    expect(pressedChairs()).toEqual(['4'])

    // Un cambio de fecha relee la agenda del día: la elección sigue en pie.
    const dateInput = document.querySelector('input[type="date"]')
    dateInput.value = '2026-05-14'
    dateInput.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    expect(pressedChairs()).toEqual(['4'])
  })
})
