import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { postMock, loginMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  loginMock: vi.fn()
}))

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: loginMock })
}))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: [] }),
    post: postMock,
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('@/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('@/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '@/plugins/ui-components'
import LoginPage from '@/modules/auth/LoginPage.vue'
import ResetPasswordModal from '@/modules/auth/ResetPasswordModal.vue'

function makeRouter(initialPath = '/login') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: { template: '<div />' } },
      { path: '/reset-password', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  router.push(initialPath)
  return router
}

async function mountPage(initialPath = '/login') {
  const router = makeRouter(initialPath)
  await router.isReady()
  const wrapper = mount(LoginPage, {
    global: {
      plugins: [router, uiComponents],
      stubs: { teleport: true }
    }
  })
  await flushPromises()
  return { wrapper, router }
}

function mountResetModal(props = {}) {
  return mount(ResetPasswordModal, {
    props: { modelValue: true, ...props },
    global: { stubs: { teleport: true } }
  })
}

async function fillAndSubmitResetForm(wrapper) {
  const [password, confirmation] = wrapper.findAll('input[type="password"]')
  await password.setValue('nueva-clave-123')
  await confirmation.setValue('nueva-clave-123')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

afterEach(() => {
  postMock.mockReset()
  loginMock.mockReset()
  localStorage.clear()
})

describe('forgot password handoff', () => {
  it('opens the reset modal prefilled with the submitted email', async () => {
    postMock.mockResolvedValueOnce({ data: { message: 'Correo enviado' } })
    const { wrapper } = await mountPage()

    await wrapper.find('.forgot-password-link').trigger('click')
    await flushPromises()
    await wrapper.find('input[type="email"]').setValue('ana@test.com')
    await wrapper.find('form:not(.login-form)').trigger('submit')
    await flushPromises()

    const resetLink = wrapper.find('.reset-link')
    expect(resetLink.exists()).toBe(true)
    await resetLink.trigger('click')
    await flushPromises()

    const reset = wrapper.findComponent(ResetPasswordModal)
    expect(reset.props('modelValue')).toBe(true)
    expect(reset.props('email')).toBe('ana@test.com')
    expect(wrapper.text()).toContain('Ingresa tu nueva contraseña')

    wrapper.unmount()
  })
})

describe('reset password payload', () => {
  it('sends the typed recovery code as token', async () => {
    postMock.mockResolvedValueOnce({ data: { message: 'ok' } })
    const wrapper = mountResetModal({ email: 'ana@test.com' })
    await flushPromises()

    await wrapper.find('#reset-password-token').setValue('654321')
    await fillAndSubmitResetForm(wrapper)

    expect(postMock).toHaveBeenCalledWith('/api/auth/reset-password', {
      email: 'ana@test.com',
      password: 'nueva-clave-123',
      password_confirmation: 'nueva-clave-123',
      token: '654321'
    })

    wrapper.unmount()
  })

  it('prefers the token prop over a typed recovery code', async () => {
    postMock.mockResolvedValueOnce({ data: { message: 'ok' } })
    const wrapper = mountResetModal({ email: 'ana@test.com', token: 'desde-el-enlace' })
    await flushPromises()

    await wrapper.find('#reset-password-token').setValue('654321')
    await fillAndSubmitResetForm(wrapper)

    expect(postMock).toHaveBeenCalledWith(
      '/api/auth/reset-password',
      expect.objectContaining({ token: 'desde-el-enlace' })
    )

    wrapper.unmount()
  })

  it('omits the token when neither the prop nor the code field provides one', async () => {
    postMock.mockResolvedValueOnce({ data: { message: 'ok' } })
    const wrapper = mountResetModal({ email: 'ana@test.com' })
    await flushPromises()

    await fillAndSubmitResetForm(wrapper)

    expect(postMock).toHaveBeenCalledWith('/api/auth/reset-password', {
      email: 'ana@test.com',
      password: 'nueva-clave-123',
      password_confirmation: 'nueva-clave-123'
    })

    wrapper.unmount()
  })
})

describe('reset password deep link', () => {
  it('opens the reset modal prefilled from the query params', async () => {
    const { wrapper } = await mountPage('/reset-password?token=desde-el-enlace&email=ana@test.com')
    const reset = wrapper.findComponent(ResetPasswordModal)

    expect(reset.props('modelValue')).toBe(true)
    expect(reset.props('email')).toBe('ana@test.com')
    expect(reset.props('token')).toBe('desde-el-enlace')
    expect(wrapper.find('input[type="email"]').element.value).toBe('ana@test.com')
    expect(wrapper.text()).toContain('Ingresa tu nueva contraseña')

    wrapper.unmount()
  })

  it('strips the query once the modal closes so a remount cannot reopen it', async () => {
    const { wrapper, router } = await mountPage(
      '/reset-password?token=desde-el-enlace&email=ana@test.com'
    )
    const reset = wrapper.findComponent(ResetPasswordModal)
    const cancel = wrapper.findAll('button').find(button => button.text() === 'Cancelar')

    expect(cancel).toBeTruthy()
    await cancel.trigger('click')
    await flushPromises()

    expect(reset.props('modelValue')).toBe(false)
    expect(router.currentRoute.value.fullPath).toBe('/reset-password')

    const remounted = mount(LoginPage, {
      global: {
        plugins: [router, uiComponents],
        stubs: { teleport: true }
      }
    })
    await flushPromises()
    expect(remounted.findComponent(ResetPasswordModal).props('modelValue')).toBe(false)

    remounted.unmount()
    wrapper.unmount()
  })
})
