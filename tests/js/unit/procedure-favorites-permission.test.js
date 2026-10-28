import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #396 S6: favorites is a clinical permission (`manage.procedure_favorite`).
// A desk without it must not call the endpoint at all, and no lookup may
// escape `load()` as an unhandled promise rejection.
const getMock = vi.hoisted(() => vi.fn())
const authState = vi.hoisted(() => ({ permissions: null }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    del: async () => ({}),
    normalizeError: (err, fallback) => err?.response?.data?.message || fallback
  })
}))

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { ref, computed } = await import('vue')
  const permissions = ref([])
  authState.permissions = permissions
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        role: 'administrador',
        permissions: permissions.value
      }))
    })
  }
})

import ProcedureQuickPicker from '../../../resources/js/components/procedures/ProcedureQuickPicker.vue'

const favorite = {
  id: 5,
  code: 'P-FAV',
  name: 'Favorito clínico',
  default_duration_minutes: 30,
  default_cost: 100
}
const available = {
  id: 5,
  code: 'P-FAV',
  name: 'Favorito clínico',
  default_duration_minutes: 30,
  default_cost: 100
}
const other = {
  id: 6,
  code: 'P-OTH',
  name: 'Procedimiento disponible',
  default_duration_minutes: 45,
  default_cost: 120
}

const mountPicker = () => mount(ProcedureQuickPicker, { props: { modelValue: null } })

const collectUnhandled = () => {
  const rejections = []
  const listener = reason => rejections.push(reason)
  process.on('unhandledRejection', listener)
  return { rejections, stop: () => process.off('unhandledRejection', listener) }
}

const settle = async () => {
  await flushPromises()
  await new Promise(resolve => setTimeout(resolve, 0))
}

describe('procedure favorites permission (#396 S6)', () => {
  beforeEach(() => {
    getMock.mockReset()
  })

  it('skips the favorites lookup without the permission and keeps the primary list', async () => {
    authState.permissions.value = []
    getMock.mockImplementation(url => {
      if (url === '/api/procedure-catalog-favorites') {
        return Promise.reject({
          response: { status: 403, data: { message: 'This action is unauthorized.' } }
        })
      }
      return Promise.resolve({ data: [available, other], meta: {} })
    })

    const { rejections, stop } = collectUnhandled()
    let wrapper

    try {
      wrapper = mountPicker()
      await settle()

      const urls = getMock.mock.calls.map(([url]) => url)
      expect(urls).not.toContain('/api/procedure-catalog-favorites')
      expect(urls).toContain('/api/procedure-catalog/for-me')
      expect(wrapper.text()).toContain('Procedimiento disponible')
    } finally {
      wrapper?.unmount()
      stop()
    }

    expect(rejections).toEqual([])
  })

  it('requests favorites when the permission is present', async () => {
    authState.permissions.value = ['manage.procedure_favorite']
    getMock.mockImplementation(url => {
      if (url === '/api/procedure-catalog-favorites') {
        return Promise.resolve({ data: [favorite] })
      }
      return Promise.resolve({ data: [available, other], meta: {} })
    })

    const { rejections, stop } = collectUnhandled()
    let wrapper

    try {
      wrapper = mountPicker()
      await settle()

      const urls = getMock.mock.calls.map(([url]) => url)
      expect(urls).toContain('/api/procedure-catalog-favorites')
      expect(urls).toContain('/api/procedure-catalog/for-me')
      expect(wrapper.text()).toContain('Mis favoritos')
    } finally {
      wrapper?.unmount()
      stop()
    }

    expect(rejections).toEqual([])
  })
})
