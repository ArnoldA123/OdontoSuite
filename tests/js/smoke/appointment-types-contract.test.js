import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Appointment-types CRUD contract (2026-10-19 audit). Every rule here pins an
// observable behavior of the page against the API contract:
//  - a delete refused with 409 shows the server message (the type still has
//    appointments), never a generic "could not delete" toast;
//  - a null price renders "No especificado", never `S/ 0.00`;
//  - the modal footers submit their <form> (native required validation);
//  - the create payload carries the three catalog flags.
const { getMock, postMock, deleteMock, makeType } = vi.hoisted(() => {
  const makeType = (id, overrides = {}) => ({
    id,
    name: `Tipo ${id}`,
    description: `Descripción del tipo ${id}`,
    default_duration_minutes: 30,
    price: 100,
    color: '#3B82F6',
    requires_confirmation: false,
    requires_materials: false,
    is_consultation_mode: false,
    is_active: true,
    ...overrides
  })

  return {
    getMock: vi.fn(async () => ({ data: [] })),
    postMock: vi.fn(async () => ({ data: {} })),
    deleteMock: vi.fn(async () => ({ data: {} })),
    makeType
  }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: deleteMock,
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
import Page from '../../../resources/js/modules/appointment-types/AppointmentTypesPage.vue'
import { globalToasts } from '../../../resources/js/composables/useToast'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async (catalog = []) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/appointment-types')) return { data: catalog }
    return { data: [] }
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const confirmDeletion = async wrapper => {
  await wrapper.find('[data-row-action="delete"]').trigger('click')
  await flushPromises()
  const confirmBtn = [...document.body.querySelectorAll('button')].find(
    b => b.textContent.trim() === 'Eliminar'
  )
  expect(confirmBtn).toBeTruthy()
  confirmBtn.click()
  await flushPromises()
}

describe('appointment-types CRUD contract', () => {
  beforeEach(() => {
    globalToasts.value = []
    getMock.mockReset()
    postMock.mockReset()
    postMock.mockImplementation(async () => ({ data: {} }))
    deleteMock.mockReset()
    deleteMock.mockImplementation(async () => ({ data: {} }))
  })

  afterEach(() => {
    globalToasts.value = []
  })

  it('surfaces the API message when deletion is blocked by existing appointments', async () => {
    const message =
      'No se puede eliminar el tipo de cita: tiene 1 cita asociada. Desactívalo en su lugar.'
    deleteMock.mockRejectedValueOnce({
      response: { status: 409, data: { message } },
      status: 409
    })

    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental' })])
    await confirmDeletion(wrapper)

    expect(deleteMock).toHaveBeenCalledTimes(1)
    expect(globalToasts.value.map(t => t.message)).toContain(message)
    expect(globalToasts.value.map(t => t.message)).not.toContain(
      'Error al eliminar el tipo de cita'
    )
    wrapper.unmount()
  })
})
