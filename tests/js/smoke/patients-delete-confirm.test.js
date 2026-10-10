import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const harness = vi.hoisted(() => ({
  confirm: vi.fn(),
  apiGet: vi.fn(),
  apiDelete: vi.fn()
}))

vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')
  return {
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default'),
    useConfirm: () => ({
      confirm: harness.confirm,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: harness.apiDelete,
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => {
      const channel = { listen: () => channel }
      return channel
    },
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/patients/PatientsPage.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  phone: '999888777',
  email: 'ana@test.com',
  birth_date: '1990-04-12',
  age: 36,
  is_active: true
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const findButton = (wrapper, label) =>
  wrapper.findAll('button').find(button => button.text().trim() === label)

describe('patients delete confirmation', () => {
  let windowConfirm

  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.confirm.mockReset()
    harness.apiGet.mockReset()
    harness.apiDelete.mockReset()
    harness.apiGet.mockResolvedValue({
      data: [patient],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 }
    })
    harness.apiDelete.mockResolvedValue({ data: {} })
    windowConfirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
  })

  afterEach(() => {
    windowConfirm.mockRestore()
    localStorage.clear()
  })

  it('resolves deletion through useConfirm before calling the API', async () => {
    let resolveConfirm
    harness.confirm.mockImplementation(
      () =>
        new Promise(resolve => {
          resolveConfirm = resolve
        })
    )

    const wrapper = await mountPage()
    const deleteButton = findButton(wrapper, 'Eliminar')
    expect(deleteButton).toBeTruthy()

    await deleteButton.trigger('click')
    await flushPromises()

    expect(harness.confirm).toHaveBeenCalledTimes(1)
    expect(harness.confirm).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Eliminar paciente',
        variant: 'danger',
        confirmText: 'Eliminar'
      })
    )
    expect(windowConfirm).not.toHaveBeenCalled()
    expect(harness.apiDelete).not.toHaveBeenCalled()

    resolveConfirm(true)
    await flushPromises()

    expect(harness.apiDelete).toHaveBeenCalledTimes(1)
    expect(harness.apiDelete).toHaveBeenCalledWith('/api/patients/7')
    wrapper.unmount()
  })

  it('does not call the API when the dialog is cancelled', async () => {
    harness.confirm.mockResolvedValue(false)

    const wrapper = await mountPage()
    const deleteButton = findButton(wrapper, 'Eliminar')
    expect(deleteButton).toBeTruthy()

    await deleteButton.trigger('click')
    await flushPromises()

    expect(harness.confirm).toHaveBeenCalledTimes(1)
    expect(windowConfirm).not.toHaveBeenCalled()
    expect(harness.apiDelete).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
