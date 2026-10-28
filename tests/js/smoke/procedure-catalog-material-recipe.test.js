import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn()
}))

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

import uiComponents from '../../../resources/js/plugins/ui-components'
import ProcedureCatalogFormModal from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogFormModal.vue'

const INVENTORY_PRODUCTS = {
  data: [
    {
      id: 3,
      name: 'Resina A2',
      code: 'INS-003',
      unit: 'jeringa',
      stock_quantity: 12,
      is_active: true
    },
    {
      id: 4,
      name: 'Adhesivo universal',
      code: 'INS-004',
      unit: 'frasco',
      stock_quantity: 8,
      is_active: true
    }
  ],
  meta: { total: 2, per_page: 100, current_page: 1, last_page: 1 }
}

let wrapper = null

const mountForm = async (procedure = null) => {
  wrapper = mount(ProcedureCatalogFormModal, {
    props: { procedure, specialties: [] },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const recipeProcedure = () => ({
  id: 7,
  code: 'OBT-COMP',
  name: 'Obturación composite',
  site_type: 'tooth',
  default_cost: 180,
  default_duration_minutes: 45,
  is_active: true,
  external_codes: [],
  material_recipe: [
    {
      product_id: 3,
      product_name: 'Resina A2',
      unit: 'jeringa',
      cost_price: 20,
      stock_quantity: 12,
      quantity: 0.5
    },
    {
      product_id: 9,
      product_name: 'Grabador ácido',
      unit: 'jeringa',
      cost_price: 15,
      stock_quantity: 2,
      quantity: 2
    }
  ]
})

const rows = () => [...document.querySelectorAll('[data-material-recipe-row]')]

const quantityInput = index =>
  document.querySelector(`[data-material-recipe-quantity="${index}"] input`)

const setQuantity = async (index, value) => {
  const input = quantityInput(index)
  expect(input, `the quantity input of line ${index} must render`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const chooseProduct = async (index, labelFragment) => {
  const scope = document.querySelector(`[data-material-recipe-product="${index}"]`)
  expect(scope, `the product select of line ${index} must render`).toBeTruthy()

  const trigger = scope.querySelector('button[aria-haspopup="true"]')
  expect(trigger, 'the product select must render a trigger').toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...scope.querySelectorAll('[role="listbox"] button')].find(button =>
    button.textContent.includes(labelFragment)
  )
  expect(option, `option containing "${labelFragment}" must exist`).toBeTruthy()
  option.click()
  await flushPromises()
}

const submit = async () => {
  document.querySelector('[data-form-submit]').click()
  await flushPromises()
  await flushPromises()
}

describe('procedure catalog material recipe', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    harness.apiPost.mockReset()
    harness.apiPost.mockResolvedValue({ data: { id: 55 } })
    harness.apiPut.mockReset()
    harness.apiPut.mockResolvedValue({ data: { id: 7 } })
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/inventory/products') return INVENTORY_PRODUCTS
      return { data: null }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('renders the recipe lines of the edited procedure with their product and quantity', async () => {
    await mountForm(recipeProcedure())

    expect(document.querySelector('[data-form-group="material-recipe"]')).toBeTruthy()

    const lines = rows()
    expect(lines).toHaveLength(2)
    expect(lines[0].textContent).toContain('Resina A2')
    expect(lines[0].textContent).toContain('jeringa')
    expect(quantityInput(0).value).toBe('0.5')

    // A line whose product is not on the inventory page still shows its own
    // product, seeded from the detail payload.
    expect(lines[1].textContent).toContain('Grabador ácido')
    expect(quantityInput(1).value).toBe('2')

    expect(document.querySelector('[data-material-recipe-empty]')).toBeNull()
    expect(harness.apiGet).toHaveBeenCalledWith('/api/inventory/products', {
      params: { per_page: 100, is_active: 1 }
    })
  })

  it('sends the recipe of the edited procedure when it is saved', async () => {
    await mountForm(recipeProcedure())

    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)

    const [url, payload] = harness.apiPut.mock.calls[0]
    expect(url).toBe('/api/procedure-catalog/7')
    expect(payload.material_recipe).toEqual([
      { product_id: 3, quantity: 0.5 },
      { product_id: 9, quantity: 2 }
    ])
    expect(payload.site_type).toBe('tooth')
  })

  it('appends an empty line and saves the product and quantity chosen for it', async () => {
    await mountForm(recipeProcedure())

    document.querySelector('[data-material-recipe-add]').click()
    await flushPromises()
    expect(rows()).toHaveLength(3)

    await submit()
    expect(harness.apiPut).not.toHaveBeenCalled()
    expect(
      document
        .querySelector('[data-material-recipe-product="2"]')
        .querySelector('button[aria-invalid="true"]')
    ).toBeTruthy()

    await chooseProduct(2, 'Adhesivo universal')
    await setQuantity(2, '3')
    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)
    expect(harness.apiPut.mock.calls[0][1].material_recipe).toEqual([
      { product_id: 3, quantity: 0.5 },
      { product_id: 9, quantity: 2 },
      { product_id: 4, quantity: 3 }
    ])
  })

  it('blocks the save when a quantity is not greater than zero', async () => {
    await mountForm(recipeProcedure())

    await setQuantity(0, '0')
    await submit()

    expect(harness.apiPut).not.toHaveBeenCalled()

    const scope = document.querySelector('[data-material-recipe-quantity="0"]')
    expect(scope.querySelector('input').getAttribute('aria-invalid')).toBe('true')
    expect(scope.textContent).toContain('La cantidad debe ser mayor a cero.')
  })

  it('sends an empty recipe when every line is removed', async () => {
    await mountForm(recipeProcedure())
    expect(rows()).toHaveLength(2)

    document.querySelector('[data-material-recipe-remove="0"]').click()
    await flushPromises()
    document.querySelector('[data-material-recipe-remove="0"]').click()
    await flushPromises()

    expect(rows()).toHaveLength(0)
    expect(document.querySelector('[data-material-recipe-empty]').textContent).toContain(
      'Este procedimiento no declara receta de insumos.'
    )

    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)
    expect(harness.apiPut.mock.calls[0][1].material_recipe).toEqual([])
  })

  it('keeps the seeded lines and the save working when the inventory page fails', async () => {
    harness.apiGet.mockRejectedValue(new Error('network down'))

    await mountForm(recipeProcedure())

    expect(document.querySelector('[data-material-recipe-load-error]')).toBeTruthy()
    expect(rows()).toHaveLength(2)
    expect(rows()[1].textContent).toContain('Grabador ácido')

    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)
    expect(harness.apiPut.mock.calls[0][1].material_recipe).toEqual([
      { product_id: 3, quantity: 0.5 },
      { product_id: 9, quantity: 2 }
    ])
  })

  it('paints the server recipe errors on their own line', async () => {
    harness.apiPut.mockRejectedValue({
      response: {
        data: {
          message: 'Los datos enviados no son válidos.',
          errors: {
            'material_recipe.0.product_id': ['El insumo seleccionado no existe.'],
            'material_recipe.0.quantity': ['La cantidad estimada debe ser mayor a cero.']
          }
        }
      }
    })

    await mountForm(recipeProcedure())
    await submit()

    expect(
      document
        .querySelector('[data-material-recipe-product="0"]')
        .querySelector('button[aria-invalid="true"]')
    ).toBeTruthy()
    expect(document.querySelector('[data-material-recipe-quantity="0"]').textContent).toContain(
      'La cantidad estimada debe ser mayor a cero.'
    )
    expect(document.querySelector('[data-material-recipe-product="0"]').textContent).toContain(
      'El insumo seleccionado no existe.'
    )
  })

  it('shows the array-level recipe rejection inside the section', async () => {
    harness.apiPut.mockRejectedValue({
      response: {
        data: {
          errors: {
            material_recipe: ['Un procedimiento no puede declarar mas de 50 insumos.']
          }
        }
      }
    })

    await mountForm(recipeProcedure())
    await submit()

    expect(document.querySelector('[data-material-recipe-errors]').textContent).toContain(
      'Un procedimiento no puede declarar mas de 50 insumos.'
    )
  })
})
