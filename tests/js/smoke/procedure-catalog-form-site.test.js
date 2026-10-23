import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn()
}))

let detailProcedure = null

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    put: harness.apiPut,
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
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
import ProcedureCatalogFormModal from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogFormModal.vue'
import ProcedureCatalogDetailPage from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue'

const setValue = async (input, value) => {
  expect(input, 'the field must render an input').toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const chooseOption = async (scope, label) => {
  expect(scope, 'the select scope must be rendered').toBeTruthy()

  const trigger = scope.querySelector('button[aria-haspopup="true"]')
  expect(trigger, 'the select must render a trigger').toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...scope.querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist`).toBeTruthy()
  option.click()
  await flushPromises()
}

const submit = async () => {
  document.querySelector('[data-form-submit]').click()
  await flushPromises()
  await flushPromises()
}

const mountForm = async (procedure = null) => {
  const wrapper = mount(ProcedureCatalogFormModal, {
    props: { procedure, specialties: [] },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const fillIdentity = async () => {
  const inputs = [...document.querySelectorAll('[data-form-group="identity"] input')]
  await setValue(inputs[0], 'OBT-COMP')
  await setValue(inputs[1], 'Obturación composite')
}

let wrapper = null

describe('procedure catalog applicability and equivalences', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    harness.apiPost.mockReset()
    harness.apiPost.mockResolvedValue({ data: { id: 55 } })
    harness.apiPut.mockReset()
    harness.apiPut.mockResolvedValue({ data: { id: 7 } })
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async () => ({ data: detailProcedure }))
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('sends site_type and the equivalences when a procedure is created', async () => {
    wrapper = await mountForm()

    await fillIdentity()
    await chooseOption(document.querySelector('[data-field="site_type"]'), 'Pieza dental')

    document.querySelector('[data-external-code-add]').click()
    await flushPromises()

    let rows = [...document.querySelectorAll('[data-external-code-row]')]
    expect(rows).toHaveLength(1)
    await setValue(rows[0].querySelectorAll('input')[0], '997301')
    await setValue(rows[0].querySelectorAll('input')[1], 'Obturación con resina')

    document.querySelector('[data-external-code-add]').click()
    await flushPromises()

    rows = [...document.querySelectorAll('[data-external-code-row]')]
    expect(rows).toHaveLength(2)
    await chooseOption(rows[1], 'SIS')
    await setValue(rows[1].querySelectorAll('input')[0], 'SIS-001')

    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)

    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/procedure-catalog')
    expect(payload.site_type).toBe('tooth')
    expect(payload.external_codes).toEqual([
      { system: 'minsa_rm550_2023', code: '997301', description: 'Obturación con resina' },
      { system: 'sis', code: 'SIS-001', description: null }
    ])
  })

  it('prefills the applicability and the equivalences of the edited procedure', async () => {
    wrapper = await mountForm({
      id: 7,
      code: 'END-001',
      name: 'Endodoncia unirradicular',
      site_type: 'surface',
      default_cost: 150,
      default_duration_minutes: 45,
      is_active: true,
      external_codes: [{ system: 'sis', code: 'SIS-77', description: 'Tarifario SIS' }]
    })

    const siteField = document.querySelector('[data-field="site_type"]')
    expect(siteField.textContent).toContain('Superficie dental')

    const rows = [...document.querySelectorAll('[data-external-code-row]')]
    expect(rows).toHaveLength(1)
    expect(rows[0].textContent).toContain('SIS')
    expect(rows[0].querySelectorAll('input')[0].value).toBe('SIS-77')
    expect(rows[0].querySelectorAll('input')[1].value).toBe('Tarifario SIS')

    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)

    const [url, payload] = harness.apiPut.mock.calls[0]
    expect(url).toBe('/api/procedure-catalog/7')
    expect(payload.site_type).toBe('surface')
    expect(payload.external_codes).toEqual([
      { system: 'sis', code: 'SIS-77', description: 'Tarifario SIS' }
    ])
  })
})

describe('procedure catalog detail applicability and equivalences', () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/procedure-catalog/:id', component: { template: '<div />' } }]
  })

  const mountDetail = async () => {
    await router.push('/procedure-catalog/7')
    await router.isReady()
    const page = mount(ProcedureCatalogDetailPage, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    await new Promise(resolve => setTimeout(resolve, 0))
    await flushPromises()
    return page
  }

  beforeEach(() => {
    document.body.innerHTML = ''
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async () => ({ data: detailProcedure }))
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    detailProcedure = null
    document.body.innerHTML = ''
  })

  it('renders the applicability and the public nomenclator codes', async () => {
    detailProcedure = {
      id: 7,
      code: 'OBT-COMP',
      name: 'Obturación composite',
      site_type: 'tooth',
      default_cost: 180,
      default_duration_minutes: 45,
      is_active: true,
      external_codes: [
        { system: 'minsa_rm550_2023', code: '997301', description: 'Obturación con resina' }
      ]
    }

    wrapper = await mountDetail()

    expect(wrapper.find('[data-detail-field="site_type"]').text()).toBe('Pieza dental')

    const rows = wrapper.findAll('[data-external-code-row]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('MINSA RM 550-2023')
    expect(rows[0].text()).toContain('997301')
    expect(rows[0].text()).toContain('Obturación con resina')
    expect(wrapper.find('[data-external-codes-empty]').exists()).toBe(false)
  })

  it('explains when the procedure declares no equivalences', async () => {
    detailProcedure = {
      id: 7,
      code: 'LIMP',
      name: 'Limpieza dental',
      site_type: 'none',
      default_cost: 80,
      default_duration_minutes: 30,
      is_active: true,
      external_codes: []
    }

    wrapper = await mountDetail()

    expect(wrapper.find('[data-detail-field="site_type"]').text()).toBe('Sitio independiente')
    expect(wrapper.find('[data-external-code-row]').exists()).toBe(false)
    expect(wrapper.find('[data-external-codes-empty]').exists()).toBe(true)
  })
})
