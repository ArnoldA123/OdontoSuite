import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { loginMock } = vi.hoisted(() => ({ loginMock: vi.fn() }))

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: loginMock })
}))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: [] }),
    post: async () => ({ data: {} }),
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
import Page from '@/modules/auth/LoginPage.vue'
import pageSource from '@/modules/auth/LoginPage.vue?raw'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
}

function mountPage() {
  return mount(Page, { global: { plugins: [makeRouter(), uiComponents] } })
}

async function fillAndSubmit(wrapper) {
  await wrapper.find('#login-username').setValue('admin@test.com')
  await wrapper.find('#login-password').setValue('wrong-password')
  await wrapper.find('form.login-form').trigger('submit')
}

function retryStage(wrapper) {
  return wrapper.findAll('.login-submit-stage').find(s => s.text() === 'Reintentar') ?? null
}

async function waitForBoth(wrapper, timeoutMs = 3000) {
  const started = Date.now()
  for (;;) {
    await flushPromises()
    const errorVisible = wrapper.find('.auth-error').exists()
    const stage = retryStage(wrapper)
    if (errorVisible && stage && stage.isVisible()) return true
    if (Date.now() - started > timeoutMs) return false
    await new Promise(resolve => setTimeout(resolve, 50))
    await flushPromises()
  }
}

afterEach(() => {
  loginMock.mockReset()
  localStorage.clear()
})

describe('login card centering', () => {
  it('centers the card at desktop widths', () => {
    expect(pageSource).toMatch(/\.login-stage\s*\{[^}]*justify-center/)
    expect(pageSource).not.toMatch(/justify-content:\s*flex-start/)
    expect(pageSource).not.toMatch(/padding-left:\s*clamp\(56px,\s*14vw,\s*220px\)/)
  })

  it('keeps the stage padding symmetric at every breakpoint', () => {
    expect(pageSource).toMatch(/\.login-stage\s*\{[^}]*padding:\s*clamp\(16px,\s*4vw,\s*56px\)/)
  })
})

describe('login password toggle', () => {
  it('is keyboard-focusable and reports its pressed state', async () => {
    const wrapper = mountPage()
    try {
      await flushPromises()
      const toggle = wrapper.find('.password-toggle')
      expect(toggle.exists()).toBe(true)
      expect(toggle.attributes('tabindex')).toBeUndefined()
      expect(toggle.attributes('aria-pressed')).toBe('false')
      expect(toggle.attributes('aria-label')).toBe('Mostrar contraseña')

      await toggle.trigger('click')
      expect(wrapper.find('#login-password').attributes('type')).toBe('text')
      expect(toggle.attributes('aria-pressed')).toBe('true')
      expect(toggle.attributes('aria-label')).toBe('Ocultar contraseña')
    } finally {
      wrapper.unmount()
    }
  })
})

describe('login error feedback', () => {
  it('keeps the error message visible while the retry affordance is shown', async () => {
    loginMock.mockRejectedValueOnce(new Error('Credenciales incorrectas'))
    const wrapper = mountPage()
    try {
      await flushPromises()
      await fillAndSubmit(wrapper)

      expect(await waitForBoth(wrapper)).toBe(true)
      expect(wrapper.find('.auth-error').text()).toContain('Credenciales incorrectas')
    } finally {
      wrapper.unmount()
    }
  })

  it('still shows the error after the button returns to idle', async () => {
    loginMock.mockRejectedValueOnce(new Error('Credenciales incorrectas'))
    const wrapper = mountPage()
    try {
      await flushPromises()
      await fillAndSubmit(wrapper)

      expect(await waitForBoth(wrapper)).toBe(true)
      await new Promise(resolve => setTimeout(resolve, 600))
      await flushPromises()
      expect(wrapper.find('.auth-error').exists()).toBe(true)
    } finally {
      wrapper.unmount()
    }
  })
})

describe('login mini-summary role', () => {
  it('renders the Spanish label for a domain role on success', async () => {
    loginMock.mockResolvedValueOnce({ user: { name: 'Ana Pérez', role: 'odontologo' } })
    const wrapper = mountPage()
    try {
      await flushPromises()
      await fillAndSubmit(wrapper)
      await new Promise(resolve => setTimeout(resolve, 700))
      await flushPromises()

      const role = wrapper.find('.login-mini-role')
      expect(role.exists()).toBe(true)
      expect(role.text()).toBe('Odontólogo')
    } finally {
      wrapper.unmount()
    }
  })
})
