import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Issue #164 — PageHeader pages share the app-shell heading contract: the
// topbar owns the document's single <h1>, so the page header contributes a
// section heading (h2) and no second banner landmark inside <main>.
//
// Issue #243 — detail pages name the entity they show in their own <h1>, so
// the topbar drops its heading on those routes: the document keeps exactly one
// <h1> and it is the visible one. Issue #405 added the patient detail surface
// to that rule: the patient name is the page heading.
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
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
    privateChannel: () => {
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
import EnvironmentsPage from '../../../resources/js/modules/environments/EnvironmentsPage.vue'
import AppointmentTypeDetailPage from '../../../resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue'
import ProcedureCatalogDetailPage from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue'
import ProfessionalDetailPage from '../../../resources/js/modules/professionals/ProfessionalDetailPage.vue'
import PatientDetailPage from '../../../resources/js/modules/patients/PatientDetailPage.vue'

const APPOINTMENT_TYPE = {
  id: 7,
  name: 'Limpieza dental',
  description: 'Profilaxis dental completa',
  default_duration_minutes: 45,
  price: 150,
  color: '#4F46E5',
  requires_confirmation: true,
  requires_materials: false,
  is_consultation_mode: true,
  is_active: true
}

const PROCEDURE = {
  id: 7,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  description: 'Tratamiento de conducto',
  specialty_id: 2,
  specialty_name: 'Endodoncia',
  default_cost: 150,
  default_duration_minutes: 45,
  is_active: true
}

const PROFESSIONAL = {
  id: 7,
  name: 'Ana Torres',
  username: 'atorres',
  email: 'ana@test.com',
  phone: '999999999',
  role: 'odontologo',
  is_active: true
}

const PATIENT = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  birth_date: '1990-04-12',
  is_active: true
}

const detailRoutes = [
  {
    pattern: '/appointment-types/:id',
    path: '/appointment-types/7',
    component: AppointmentTypeDetailPage,
    heading: APPOINTMENT_TYPE.name
  },
  {
    pattern: '/procedure-catalog/:id',
    path: '/procedure-catalog/7',
    component: ProcedureCatalogDetailPage,
    heading: PROCEDURE.name
  },
  {
    pattern: '/professionals/:id',
    path: '/professionals/7',
    component: ProfessionalDetailPage,
    heading: PROFESSIONAL.name
  },
  {
    pattern: '/patients/:id',
    path: '/patients/7',
    component: PatientDetailPage,
    heading: `${PATIENT.first_name} ${PATIENT.last_name}`
  }
]

// Module pages mount their own AppLayout (app.js renders the page, not the
// shell), so mounting the page renders the real document: topbar plus content.
const mountPageAt = async (path, component, pattern = path) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: pattern, component }]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(component, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const mountPage = () => mountPageAt('/environments', EnvironmentsPage)

const topbar = wrapper => wrapper.find('[data-app-chrome="topbar"]')

beforeEach(() => {
  getMock.mockReset()
  getMock.mockImplementation(async url => {
    if (url === '/api/appointment-types/7') return { data: APPOINTMENT_TYPE }
    if (url === '/api/procedure-catalog/7') return { data: PROCEDURE }
    if (url === '/api/users/7') return { data: PROFESSIONAL }
    if (url === '/api/patients/7') return { data: PATIENT }
    if (String(url).includes('/versions')) return { data: [] }

    return { data: [] }
  })
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador' }))
})

describe('PageHeader heading hierarchy (issue #164)', () => {
  it('keeps the topbar h1 as the only document heading and renders the page title as h2', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-app-chrome="topbar"] h1')).toHaveLength(1)
    expect(wrapper.findAll('h1')).toHaveLength(1)

    const pageTitle = wrapper.find('.page-header .page-title')
    expect(pageTitle.exists(), 'PageHeader must keep its .page-title element').toBe(true)
    expect(pageTitle.element.tagName).toBe('H2')
    expect(pageTitle.text()).toBe('Ambientes')

    wrapper.unmount()
  })

  it('declares no banner landmark from the page header', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('.page-header').attributes('role')).toBeUndefined()
    expect(wrapper.findAll('[role="banner"]')).toHaveLength(0)

    wrapper.unmount()
  })
})

describe('detail route heading hierarchy (issue #243)', () => {
  it.each(detailRoutes)(
    'renders exactly one h1 on $path',
    async ({ pattern, path, component, heading }) => {
      const wrapper = await mountPageAt(path, component, pattern)

      expect(topbar(wrapper).attributes('data-topbar-title-owner')).toBe('page')
      expect(
        topbar(wrapper).find('h1').exists(),
        'the topbar must not paint a second heading on a detail route'
      ).toBe(false)

      const headings = wrapper.findAll('h1')
      expect(headings).toHaveLength(1)
      expect(headings[0].text()).toBe(heading)
      expect(headings[0].classes()).not.toContain('sr-only')

      wrapper.unmount()
    }
  )

  it('keeps the single topbar h1 on a shell-owned route', async () => {
    const wrapper = await mountPage()

    expect(topbar(wrapper).attributes('data-topbar-title-owner')).toBe('page')
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(topbar(wrapper).find('h1').classes()).toContain('sr-only')

    wrapper.unmount()
  })
})
