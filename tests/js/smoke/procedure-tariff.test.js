import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { getMock, putMock, deleteMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  putMock: vi.fn(),
  deleteMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(async () => ({ data: {} })),
    put: putMock,
    patch: vi.fn(async () => ({ data: {} })),
    delete: deleteMock,
    setToken: () => {},
    normalizeError: () => ''
  })
}))

const permissionState = vi.hoisted(() => ({ allowed: true }))

vi.mock('../../../resources/js/composables/usePermissions', async () => {
  const { computed } = await import('vue')
  return {
    usePermissions: () => ({
      can: {
        manageBranchPrice: computed(() => permissionState.allowed)
      }
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import BranchTariffPanel from '../../../resources/js/modules/procedure-catalog/BranchTariffPanel.vue'

const PROCEDURE_ID = 77

const BRANCHES = {
  data: [
    { id: 4, name: 'Sede Centro', is_active: true },
    { id: 8, name: 'Sede Norte', is_active: true }
  ]
}

const PRICES = {
  data: [
    {
      id: 9,
      payer_id: null,
      procedure_id: PROCEDURE_ID,
      price: 85,
      list_price: 100,
      valid_from: '2026-10-01',
      valid_to: '2026-10-31',
      is_active: true
    },
    {
      id: 10,
      payer_id: null,
      procedure_id: PROCEDURE_ID,
      price: 110,
      list_price: 100,
      valid_from: '2026-12-01',
      valid_to: null,
      is_active: true
    },
    {
      id: 11,
      payer_id: null,
      procedure_id: PROCEDURE_ID,
      price: 70,
      list_price: 100,
      valid_from: '2026-08-01',
      valid_to: '2026-09-30',
      is_active: true
    },
    {
      id: 12,
      payer_id: null,
      procedure_id: PROCEDURE_ID,
      price: 95,
      list_price: 100,
      valid_from: null,
      valid_to: null,
      is_active: false
    }
  ]
}

const flat = value => String(value).replace(/\s+/g, ' ')

const mountPanel = async () => {
  const wrapper = mount(BranchTariffPanel, {
    props: { procedureId: PROCEDURE_ID },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  await flushPromises()
  return wrapper
}

const setField = async (name, value) => {
  const input = document.querySelector(`[data-field="${name}"] input`)
  expect(input, `field "${name}" must render an input`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const clickButton = async selector => {
  const button = document.querySelector(selector)
  expect(button, `missing button ${selector}`).toBeTruthy()
  button.click()
  await flushPromises()
}

describe('branch tariff panel (#304 S2)', () => {
  beforeEach(() => {
    permissionState.allowed = true
    document.body.innerHTML = ''
    getMock.mockReset()
    putMock.mockReset()
    deleteMock.mockReset()

    getMock.mockImplementation(url => {
      if (url === '/api/branches/active') return Promise.resolve(BRANCHES)
      if (url === '/api/branches/4/procedure-prices') return Promise.resolve(PRICES)
      return Promise.resolve({ data: [] })
    })
    putMock.mockResolvedValue({ data: [], meta: { message: 'ok' } })
    deleteMock.mockResolvedValue({ data: null })

    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 15, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('loads the procedure tariff for the selected branch and shows every state', async () => {
    const wrapper = await mountPanel()

    expect(wrapper.find('[data-branch-tariff]').exists()).toBe(true)
    expect(wrapper.find('[data-tariff-table]').exists()).toBe(true)
    expect(getMock).toHaveBeenCalledWith('/api/branches/4/procedure-prices', {
      params: { procedure_id: PROCEDURE_ID }
    })

    expect(flat(wrapper.text())).toContain('S/ 85.00')
    expect(flat(wrapper.text())).toContain('S/ 100.00')
    expect(flat(wrapper.text())).toContain('01/10/2026 — 31/10/2026')

    const statuses = wrapper.findAll('[data-tariff-status]').map(node => node.text())
    expect(statuses).toEqual(['Vigente', 'Futuro', 'Vencido', 'Inactivo'])

    wrapper.unmount()
  })

  it('creates a price with the procedure id and the validity window', async () => {
    const wrapper = await mountPanel()

    await clickButton('[data-tariff-add]')
    expect(document.body.textContent).toContain('Nuevo precio de sede')

    await setField('price', '120')
    await setField('valid_from', '2026-11-01')
    await setField('valid_to', '2026-12-31')
    await clickButton('[data-tariff-submit]')

    expect(putMock).toHaveBeenCalledTimes(1)
    const [url, payload] = putMock.mock.calls[0]
    expect(url).toBe('/api/branches/4/procedure-prices')
    expect(payload).toEqual({
      items: [
        {
          procedure_catalog_id: PROCEDURE_ID,
          price: 120,
          valid_from: '2026-11-01',
          valid_to: '2026-12-31',
          is_active: true
        }
      ]
    })

    wrapper.unmount()
  })

  it('reports an inverted validity window in its own field without sending the price', async () => {
    const wrapper = await mountPanel()

    await clickButton('[data-tariff-add]')
    await setField('price', '120')
    await setField('valid_from', '2026-11-01')
    await setField('valid_to', '2026-10-01')
    await clickButton('[data-tariff-submit]')

    expect(putMock).not.toHaveBeenCalled()

    const field = document.querySelector('[data-field="valid_to"]')
    expect(field.textContent).toContain('La vigencia final no puede ser anterior al inicio.')
    const input = field.querySelector('input')
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(input.value).toBe('2026-10-01')

    wrapper.unmount()
  })

  it('retires a price through the shared confirmation dialog', async () => {
    const wrapper = await mountPanel()

    const remove = wrapper.findAll('[data-tariff-remove]')[0]
    await remove.trigger('click')
    await flushPromises()

    const dialog = document.body.querySelector('[role="alertdialog"]')
    expect(dialog).toBeTruthy()

    const confirm = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Retirar'
    )
    expect(confirm).toBeTruthy()
    confirm.click()
    await flushPromises()
    await flushPromises()

    expect(deleteMock).toHaveBeenCalledWith('/api/branches/4/procedure-prices/9')

    wrapper.unmount()
  })

  it('is absent and makes no request without manage.branch_price', async () => {
    permissionState.allowed = false

    const wrapper = await mountPanel()

    expect(wrapper.find('[data-branch-tariff]').exists()).toBe(false)
    expect(getMock).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
