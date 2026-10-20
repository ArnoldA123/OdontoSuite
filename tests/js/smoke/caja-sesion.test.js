import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Caja > sesión contract (T12). G12/F6/D10 + the CloseCashModal arqueo defects:
//  - both modals speak the v-model contract (`modelValue` / `update:modelValue`)
//    instead of the dead `show`/`close` pair;
//  - the open modal posts branch_id + opening_amount and turns a failed branch
//    load into a retryable alert, distinct from the empty catalog;
//  - the counted total is the single authoritative closing_amount and the
//    per-method breakdown is editable without overwriting it;
//  - the visible difference compares the counted total against expected_amount,
//    the same ledger position the badge uses;
//  - the closure report goes through useApi().download, not fetch + localStorage.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiDownload: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    download: harness.apiDownload,
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
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import OpenCashModal from '../../../resources/js/modules/cash-register/components/OpenCashModal.vue'
import CloseCashModal from '../../../resources/js/modules/cash-register/components/CloseCashModal.vue'
import openSource from '../../../resources/js/modules/cash-register/components/OpenCashModal.vue?raw'
import closeSource from '../../../resources/js/modules/cash-register/components/CloseCashModal.vue?raw'

const BRANCHES = [
  { id: 1, name: 'Sede Central', code: 'SUC-01', city: 'Lima' },
  { id: 2, name: 'Sede Norte', code: 'SUC-02', city: 'Trujillo' }
]

const SESSION = { id: 5, opening_amount: 100, opened_at: '2026-10-20T12:00:00.000000Z' }

// opening_amount and expected_amount deliberately differ: a difference computed
// against the opening would read 200, against the ledger position it reads 50.
const SUMMARY = {
  opening_amount: 100,
  total_income: 320,
  total_expenses: 70,
  expected_amount: 250,
  transactions_count: 7,
  movements_count: 3
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const normalize = value => (value || '').replace(/\u00a0/g, ' ')

const setInput = async (selector, value) => {
  const input = document.body.querySelector(selector)
  expect(input, `${selector} must render an input`).toBeTruthy()
  input.value = String(value)
  input.dispatchEvent(new Event('input'))
  await flushPromises()
}

const chooseOption = async (root, optionText) => {
  expect(root, `select for "${optionText}" must render`).toBeTruthy()
  root.querySelector('button').click()
  await flushPromises()
  const option = [...root.querySelectorAll('button')].find(candidate =>
    candidate.textContent.trim().startsWith(optionText)
  )
  expect(option, `option "${optionText}" must be rendered`).toBeTruthy()
  option.click()
  await flushPromises()
}

const mockBranchCatalog = () => {
  harness.apiGet.mockImplementation(async url => {
    if (String(url) === '/api/branches/active') return { data: BRANCHES }
    return { data: {} }
  })
}

const mountOpenModal = async (initial = true) => {
  mockBranchCatalog()
  const wrapper = mount(OpenCashModal, {
    props: { modelValue: initial },
    attachTo: document.body,
    global: { plugins: [router, uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const mountCloseModal = async () => {
  const wrapper = mount(CloseCashModal, {
    props: { modelValue: true, session: SESSION, summary: SUMMARY },
    attachTo: document.body,
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const originalCreateObjectURL = URL.createObjectURL
const originalRevokeObjectURL = URL.revokeObjectURL

let wrapper = null
let clicks = []
let createObjectURL
let revokeObjectURL
let clickSpy

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiPost.mockResolvedValue({ data: { id: SESSION.id } })
  harness.apiDownload.mockReset()
  harness.apiDownload.mockResolvedValue(new Blob(['closure']))
  mockBranchCatalog()

  clicks = []
  createObjectURL = vi.fn(() => 'blob:http://localhost:3000/closure-report')
  revokeObjectURL = vi.fn()
  URL.createObjectURL = createObjectURL
  URL.revokeObjectURL = revokeObjectURL
  clickSpy = vi
    .spyOn(HTMLAnchorElement.prototype, 'click')
    .mockImplementation(function handleClick() {
      clicks.push({ href: this.href, download: this.download })
    })
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
  clickSpy.mockRestore()
  if (originalCreateObjectURL === undefined) {
    delete URL.createObjectURL
  } else {
    URL.createObjectURL = originalCreateObjectURL
  }
  if (originalRevokeObjectURL === undefined) {
    delete URL.revokeObjectURL
  } else {
    URL.revokeObjectURL = originalRevokeObjectURL
  }
})

describe('Caja > open cash modal', () => {
  it('opens from modelValue and closes through update:modelValue', async () => {
    wrapper = await mountOpenModal(false)

    expect(document.body.querySelector('[data-open-cash-form]')).toBeNull()

    await wrapper.setProps({ modelValue: true })
    await flushPromises()
    expect(document.body.querySelector('[data-open-cash-form]')).toBeTruthy()

    document.body.querySelector('[data-modal-cancel]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    expect(openSource).not.toContain('<select')
    expect(closeSource).not.toContain('<select')
  })

  it('posts branch_id and opening_amount to the open endpoint', async () => {
    wrapper = await mountOpenModal()

    await chooseOption(
      document.body.querySelector('[data-modal-field="branch_id"]'),
      'Sede Central'
    )
    await setInput('[data-modal-amount] input', '150')

    document.body.querySelector('[data-modal-submit]').click()
    await flushPromises()

    expect(harness.apiPost).toHaveBeenCalledWith('/api/cash-register/open', {
      branch_id: 1,
      opening_amount: 150,
      opening_notes: null
    })
    expect(wrapper.emitted('success')).toHaveLength(1)
  })

  it('turns a failed branch load into a retryable alert, not an empty catalog', async () => {
    harness.apiGet.mockReset()
    harness.apiGet.mockRejectedValueOnce({
      response: { data: { message: 'No se pudieron cargar las sucursales' } }
    })

    wrapper = await mountOpenModal()

    const alert = document.body.querySelector('[data-branch-error]')
    expect(alert).toBeTruthy()
    expect(alert.getAttribute('role')).toBe('alert')
    expect(normalize(alert.textContent)).toContain('No se pudieron cargar las sucursales')
    expect(document.body.textContent).not.toContain('No hay sucursales registradas')

    harness.apiGet.mockImplementation(async url => {
      if (String(url) === '/api/branches/active') return { data: BRANCHES }
      return { data: {} }
    })

    const retry = document.body.querySelector('[data-branch-retry]')
    expect(retry).toBeTruthy()
    expect(normalize(retry.textContent)).toContain('Reintentar')
    retry.click()
    await flushPromises()

    expect(document.body.querySelector('[data-branch-error]')).toBeNull()
    expect(document.body.querySelector('[data-modal-field="branch_id"]')).toBeTruthy()
  })

  it('renders errors.general and the branch field error on a rejected open', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: { branch_id: ['La sucursal seleccionada no existe.'] }
        }
      }
    })

    wrapper = await mountOpenModal()
    await chooseOption(
      document.body.querySelector('[data-modal-field="branch_id"]'),
      'Sede Central'
    )
    await setInput('[data-modal-amount] input', '150')

    document.body.querySelector('[data-modal-submit]').click()
    await flushPromises()

    const general = document.body.querySelector('[data-open-cash-error]')
    expect(general).toBeTruthy()
    expect(general.getAttribute('role')).toBe('alert')
    expect(normalize(general.textContent)).toContain('Datos inválidos')

    const fieldError = document.body.querySelector('[data-field-error="branch_id"]')
    expect(fieldError).toBeTruthy()
    expect(normalize(fieldError.textContent)).toContain('La sucursal seleccionada no existe.')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('caps the opening notes at the 500 characters the API accepts', async () => {
    wrapper = await mountOpenModal()

    const notes = document.body.querySelector('#open-cash-notes')
    notes.value = 'a'.repeat(520)
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    expect(normalize(document.body.textContent)).toContain('500/500 caracteres')
  })
})

describe('Caja > close cash modal', () => {
  it('opens from modelValue and closes through update:modelValue', async () => {
    wrapper = await mountCloseModal()

    expect(document.body.querySelector('[data-close-cash-form]')).toBeTruthy()

    document.body.querySelector('[data-modal-cancel]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('compares the counted total against expected_amount, not against the opening', async () => {
    wrapper = await mountCloseModal()

    await setInput('[data-close-counted] input', '300')

    const difference = normalize(document.body.querySelector('[data-close-difference]').textContent)
    expect(difference).toContain('50.00')
    expect(difference).toContain('Sobrante')
    expect(difference).not.toContain('200.00')
  })

  it('keeps one authoritative counted total and submits the breakdown as arqueo', async () => {
    wrapper = await mountCloseModal()

    await setInput('[data-close-counted] input', '300')
    await setInput('[data-arqueo="efectivo"] input', '200')
    await setInput('[data-arqueo="tarjeta_debito"] input', '80')

    expect(normalize(document.body.querySelector('[data-arqueo-total]').textContent)).toContain(
      '280.00'
    )
    expect(normalize(document.body.querySelector('[data-close-difference]').textContent)).toContain(
      '50.00'
    )
    expect(normalize(document.body.querySelector('[data-arqueo-gap]').textContent)).toContain(
      '20.00'
    )

    document.body.querySelector('[data-modal-submit]').click()
    await flushPromises()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/cash-register/close')
    expect(payload).toEqual({
      session_id: 5,
      closing_amount: 300,
      closing_notes: '',
      arqueo: {
        efectivo: 200,
        tarjeta_debito: 80,
        tarjeta_credito: 0,
        transferencia: 0,
        otros: 0
      }
    })
    expect(wrapper.emitted('success')).toHaveLength(1)
  })

  it('downloads the closure report through useApi().download', async () => {
    wrapper = await mountCloseModal()

    await setInput('[data-close-counted] input', '300')
    document.body.querySelector('[data-modal-submit]').click()
    await flushPromises()

    expect(harness.apiDownload).toHaveBeenCalledTimes(1)
    expect(harness.apiDownload).toHaveBeenCalledWith('/api/cash-register/sessions/5/closure-report')
    expect(clicks).toEqual([
      { href: 'blob:http://localhost:3000/closure-report', download: 'cierre-caja-5.pdf' }
    ])

    expect(closeSource).not.toContain('fetch(')
    expect(closeSource).not.toContain('localStorage')
    expect(closeSource).toContain('download(')
    expect(closeSource).toContain('closure-report')
  })
})
