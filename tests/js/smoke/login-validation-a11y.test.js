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

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
}

function mountPage() {
  return mount(Page, { global: { plugins: [makeRouter(), uiComponents] } })
}

async function submitWith(wrapper, username, password) {
  await wrapper.find('#login-username').setValue(username)
  await wrapper.find('#login-password').setValue(password)
  await wrapper.find('form.login-form').trigger('submit')
  await flushPromises()
}

async function blurField(wrapper, selector, value) {
  const input = wrapper.find(selector)
  await input.setValue(value)
  await input.trigger('blur')
  await flushPromises()
  return input
}

afterEach(() => {
  loginMock.mockReset()
  localStorage.clear()
})

describe('login minimum-length validation', () => {
  it('rejects a username shorter than 3 characters with a Spanish message', async () => {
    const wrapper = mountPage()
    try {
      await submitWith(wrapper, 'ab', 'password123')

      expect(wrapper.find('#login-username-error').text()).toBe('Mínimo 3 caracteres')
      expect(wrapper.find('#login-password-error').exists()).toBe(false)
      expect(loginMock).not.toHaveBeenCalled()
    } finally {
      wrapper.unmount()
    }
  })

  it('rejects a password shorter than 8 characters with a Spanish message', async () => {
    const wrapper = mountPage()
    try {
      await submitWith(wrapper, 'ever', 'corta')

      expect(wrapper.find('#login-password-error').text()).toBe('Mínimo 8 caracteres')
      expect(wrapper.find('#login-username-error').exists()).toBe(false)
      expect(loginMock).not.toHaveBeenCalled()
    } finally {
      wrapper.unmount()
    }
  })

  it('accepts the shortest seeded demo credentials', async () => {
    loginMock.mockResolvedValueOnce({ user: { name: 'Ever Huamán Cruz', role: 'administrador' } })
    const wrapper = mountPage()
    try {
      await submitWith(wrapper, 'ever', 'password123')

      expect(wrapper.find('#login-username-error').exists()).toBe(false)
      expect(wrapper.find('#login-password-error').exists()).toBe(false)

      await new Promise(resolve => setTimeout(resolve, 300))
      await flushPromises()
      expect(loginMock).toHaveBeenCalledTimes(1)
    } finally {
      wrapper.unmount()
    }
  })
})

describe('login valid-state semantics', () => {
  it('points a valid field at a screen-reader success text', async () => {
    const wrapper = mountPage()
    try {
      const username = await blurField(wrapper, '#login-username', 'ever')

      expect(username.attributes('aria-invalid')).toBe('false')
      expect(username.attributes('aria-describedby')).toBe('login-username-success')
      expect(wrapper.find('#login-username-success').text()).toBe('Usuario válido')
      expect(wrapper.find('.field-success-mark').exists()).toBe(true)
    } finally {
      wrapper.unmount()
    }
  })

  it('keeps aria-invalid and the error description for invalid fields', async () => {
    const wrapper = mountPage()
    try {
      const username = await blurField(wrapper, '#login-username', 'ab')

      expect(username.attributes('aria-invalid')).toBe('true')
      expect(username.attributes('aria-describedby')).toBe('login-username-error')
      expect(wrapper.find('#login-username-success').exists()).toBe(false)
    } finally {
      wrapper.unmount()
    }
  })

  it('announces form validity once through a polite live region', async () => {
    const wrapper = mountPage()
    try {
      const live = wrapper.find('p.sr-only[role="status"][aria-live="polite"]')
      expect(live.exists()).toBe(true)
      expect(live.text()).toBe('')

      await blurField(wrapper, '#login-username', 'ever')
      expect(live.text()).toBe('')

      await blurField(wrapper, '#login-password', 'password123')
      expect(live.text()).toBe('Usuario y contraseña válidos')
    } finally {
      wrapper.unmount()
    }
  })

  it('does not rewrite the live region while the form stays valid', async () => {
    const wrapper = mountPage()
    try {
      await blurField(wrapper, '#login-username', 'ever')
      await blurField(wrapper, '#login-password', 'password123')

      const live = wrapper.find('p.sr-only[role="status"][aria-live="polite"]').element
      const mutations = []
      const observer = new MutationObserver(records => mutations.push(...records))
      observer.observe(live, { childList: true, characterData: true, subtree: true })

      await blurField(wrapper, '#login-password', 'password1234')
      await new Promise(resolve => setTimeout(resolve, 300))
      await flushPromises()
      observer.disconnect()

      expect(live.textContent).toBe('Usuario y contraseña válidos')
      expect(mutations).toHaveLength(0)
    } finally {
      wrapper.unmount()
    }
  })
})

describe('login card scroll region', () => {
  it('exposes the scrollable card as a labelled keyboard-reachable region', () => {
    const wrapper = mountPage()
    try {
      const card = wrapper.find('section.login-card')

      expect(card.attributes('role')).toBe('region')
      expect(card.attributes('tabindex')).toBe('0')
      expect(card.attributes('aria-labelledby')).toBe('login-headline')
      expect(wrapper.find('#login-headline').exists()).toBe(true)
    } finally {
      wrapper.unmount()
    }
  })

  it('keeps the terms and contact links inside the scrollable card', () => {
    const wrapper = mountPage()
    try {
      const footer = wrapper.find('section.login-card').find('.login-footer')

      expect(footer.exists()).toBe(true)
      expect(footer.findAll('a').map(a => a.text())).toEqual([
        'Términos y Condiciones',
        'Contacta al administrador'
      ])
    } finally {
      wrapper.unmount()
    }
  })
})
