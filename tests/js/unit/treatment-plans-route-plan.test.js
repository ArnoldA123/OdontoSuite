import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// The specialty record links to its plan as
// `/treatment-plans?patient_id=…&plan_id=…`. The index ignored the query, so the
// link landed on the whole desk and the plan the clinician asked for stayed
// closed. These cases pin the two halves of the contract: the deep link opens
// the plan (and scopes the list to its patient), and a dead link never breaks
// the index behind it.
const requests = []

const SUMMARY = {
  total: 1,
  by_status: { draft: 0, proposed: 1, approved: 0, in_progress: 0, completed: 0, cancelled: 0 },
  active: 1,
  overdue: 0,
  pending_approval: 1,
  pending_cost: 1500
}

const PLAN = {
  id: 3,
  patient_id: 7,
  plan_number: 'PT-2026-0003',
  title: 'Rehabilitación oral',
  status: 'proposed',
  final_cost: 1500,
  estimated_duration_weeks: 6,
  created_at: '2026-10-20T14:30:00Z',
  patient: { id: 7, first_name: 'Lucía', last_name: 'Ramírez' },
  items: [],
  quotations: []
}

const apiState = vi.hoisted(() => ({ detail: null, detailReject: null }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      requests.push(url)

      if (url.includes('/api/treatment-plans/summary')) return { data: SUMMARY }
      if (/\/api\/treatment-plans\/\d+$/.test(url)) {
        if (apiState.detailReject) throw apiState.detailReject

        return { data: apiState.detail }
      }
      if (url.includes('/api/specialty-records/')) return { data: {} }

      return {
        data: [{ ...PLAN }],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 }
      }
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({ listen: () => ({ listen: () => ({ listen: () => ({}) }) }) }),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
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
      confirm: async () => true,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})

import rolePermissions from '../support/role-permissions.json'
import { globalToasts } from '../../../resources/js/composables/useToast'
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/treatment-plans/TreatmentPlansPage.vue'

const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const mountAt = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(Page, {
    global: { plugins: [router, uiComponents] },
    attachTo: document.body
  })

  await flushPromises()

  return { wrapper, router }
}

const listRequests = () => requests.filter(url => url.includes('/api/treatment-plans?'))

describe('treatment plans · deep link of a specialty record', () => {
  beforeEach(() => {
    requests.length = 0
    apiState.detail = { ...PLAN }
    apiState.detailReject = null
    globalToasts.value = []
    seedSession('administrador')
  })

  afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
  })

  it('opens the plan the link names and scopes the index to its patient', async () => {
    const { wrapper } = await mountAt('/treatment-plans?patient_id=7&plan_id=3')

    expect(listRequests().at(-1)).toContain('patient_id=7')
    expect(requests).toContain('/api/treatment-plans/3')

    const detail = document.querySelector('[data-plan-detail]')

    expect(detail).not.toBeNull()
    expect(detail.textContent).toContain('Rehabilitación oral')
    expect(detail.textContent).toContain('PT-2026-0003')

    wrapper.unmount()
  })

  it('keeps the index usable when the linked plan cannot be read', async () => {
    apiState.detailReject = {
      response: { status: 404, data: { message: 'Plan de tratamiento no encontrado' } }
    }

    const { wrapper } = await mountAt('/treatment-plans?patient_id=7&plan_id=99')

    // No detail, no page-level error block: the list behind the shortcut is the
    // one the user can still work with.
    expect(document.querySelector('[data-plan-detail]')).toBeNull()
    expect(wrapper.find('[data-state="error"]').exists()).toBe(false)
    expect(listRequests()).toHaveLength(1)
    expect(wrapper.text()).toContain('Rehabilitación oral')

    expect(globalToasts.value).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'error', message: 'Plan de tratamiento no encontrado' })
      ])
    )

    wrapper.unmount()
  })

  it('ignores the query when the id is not a positive integer', async () => {
    const { wrapper } = await mountAt('/treatment-plans?plan_id=abc&patient_id=xyz')

    expect(listRequests().at(-1)).not.toContain('patient_id')
    expect(requests.some(url => /\/api\/treatment-plans\/\d+$/.test(url))).toBe(false)
    expect(document.querySelector('[data-plan-detail]')).toBeNull()

    wrapper.unmount()
  })

  it('opens the plan when the query changes without remounting the page', async () => {
    const { wrapper, router } = await mountAt('/treatment-plans')

    expect(document.querySelector('[data-plan-detail]')).toBeNull()

    await router.push('/treatment-plans?patient_id=7&plan_id=3')
    await flushPromises()

    expect(document.querySelector('[data-plan-detail]')).not.toBeNull()
    expect(listRequests().at(-1)).toContain('patient_id=7')

    wrapper.unmount()
  })
})
