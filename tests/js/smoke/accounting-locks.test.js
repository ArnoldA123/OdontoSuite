import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #304 S1/S5/S6/S9 — the accounting freeze surface: the four modules with their
// effective date and origin, fijar/retirar through the API with the scope the
// administrator picked, the reload-after-write without a page refresh, and the
// permission gate that removes the actions. Transports and authorization are
// mocked: the page only consumes their contracts.
const { getMock, putMock, deleteMock, confirmMock, permits } = vi.hoisted(() => ({
  getMock: vi.fn(),
  putMock: vi.fn(),
  deleteMock: vi.fn(),
  confirmMock: vi.fn(),
  permits: { manageAccountingLock: { value: true } }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
    put: putMock,
    patch: vi.fn(),
    delete: deleteMock,
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
vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')

  return {
    useConfirm: () => ({ confirm: confirmMock }),
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default')
  }
})
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({ can: permits })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import { useToast } from '../../../resources/js/composables/useToast'
import Page from '../../../resources/js/modules/accounting-locks/AccountingLocksPage.vue'
import Select from '../../../resources/js/components/ui/Select.vue'

const GROUP_ADMIN = { id: 1, name: 'Ana Admin', role: 'administrador', branch_id: null }
const BRANCH_ADMIN = { id: 2, name: 'Beto Sede', role: 'administrador', branch_id: 3 }

const BRANCHES = [
  { id: 1, code: 'SC-LIM-01', name: 'Sede Central Lima', is_active: true },
  { id: 3, code: 'SC-AQP-01', name: 'Sucursal Arequipa', is_active: true }
]

const LOCKS = () => [
  {
    module: 'cash_transactions',
    locked_through: '2026-09-30',
    source: 'branch',
    branch_id: 1,
    set_by: 1,
    set_at: '2026-10-01T10:15:00-05:00'
  },
  {
    module: 'cash_movements',
    locked_through: '2026-08-31',
    source: 'global',
    branch_id: null,
    set_by: 4,
    set_at: '2026-09-01T09:00:00-05:00'
  },
  {
    module: 'quotations',
    locked_through: null,
    source: 'none',
    branch_id: null,
    set_by: null,
    set_at: null
  },
  {
    module: 'treatment_plans',
    locked_through: null,
    source: 'none',
    branch_id: null,
    set_by: null,
    set_at: null
  }
]

let locksPayload = LOCKS()

const signInAs = user => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify(user))
}

const baseGet = async url => {
  if (String(url).startsWith('/api/branches/active')) {
    return { data: BRANCHES }
  }
  if (String(url).startsWith('/api/accounting-locks')) {
    return { data: locksPayload }
  }
  return { data: [] }
}

const makeRouter = () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/settings/accounting-locks', component: { component: { template: '<div />' } } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  return router
}

const mountPage = async (user = GROUP_ADMIN) => {
  signInAs(user)

  const router = makeRouter()
  await router.push('/settings/accounting-locks')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()

  return wrapper
}

const rows = wrapper => wrapper.findAll('[data-locks-table] tbody tr')

const scopeSelect = wrapper =>
  wrapper
    .findAllComponents(Select)
    .find(component => component.attributes('data-scope-select') !== undefined)

const chooseScope = async (wrapper, label) => {
  const select = scopeSelect(wrapper)
  expect(select, 'the scope selector must exist for group scope').toBeTruthy()

  await select.find('button[aria-haspopup="true"]').trigger('click')
  await flushPromises()

  const option = select.findAll('button').find(button => button.text().trim() === label)
  expect(option, `scope option "${label}" must exist`).toBeTruthy()

  await option.trigger('click')
  await flushPromises()
  await flushPromises()
}

const locksCalls = () =>
  getMock.mock.calls.filter(([url]) => String(url).startsWith('/api/accounting-locks'))

const fillLockDate = async date => {
  const input = document.querySelector('[data-lock-date] input')
  expect(input, 'the lock modal must expose a date input').toBeTruthy()

  input.value = date
  input.dispatchEvent(new Event('input'))
  await flushPromises()
}

const clickInBody = async selector => {
  const element = document.querySelector(selector)
  expect(element, `${selector} must be rendered`).toBeTruthy()

  element.click()
  await flushPromises()
  await flushPromises()
}

beforeEach(() => {
  document.body.innerHTML = ''
  locksPayload = LOCKS()
  permits.manageAccountingLock.value = true
  getMock.mockReset()
  getMock.mockImplementation(baseGet)
  putMock.mockReset()
  putMock.mockResolvedValue({ data: {} })
  deleteMock.mockReset()
  deleteMock.mockResolvedValue({ data: { removed: true }, meta: { message: 'Bloqueo retirado' } })
  confirmMock.mockReset()
  confirmMock.mockResolvedValue(true)
  useToast().clearAll()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('accounting locks surface (#304 S1)', () => {
  it('renders the hero band and the four modules with effective date and origin', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-locks-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Bloqueo Contable')
    expect(hero.find('[data-hero-eyebrow]').text().length).toBeGreaterThan(0)
    expect(hero.find('[data-hero-caption]').text().length).toBeGreaterThan(0)

    const tableRows = rows(wrapper)
    expect(tableRows).toHaveLength(4)

    expect(tableRows[0].find('[data-row-module]').text()).toBe('Transacciones de caja')
    expect(tableRows[0].find('[data-row-locked-through]').text()).toBe('30 set 2026')
    expect(tableRows[0].find('[data-row-source]').text()).toBe('Sede')
    expect(tableRows[0].find('[data-row-set-by]').text()).toBe('Usuario #1')
    expect(tableRows[0].find('[data-row-set-at]').text()).toBe('01 oct 2026, 10:15')

    expect(tableRows[1].find('[data-row-module]').text()).toBe('Movimientos de caja')
    expect(tableRows[1].find('[data-row-locked-through]').text()).toBe('31 ago 2026')
    expect(tableRows[1].find('[data-row-source]').text()).toBe('Global')

    expect(tableRows[2].find('[data-row-module]').text()).toBe('Presupuestos')
    expect(tableRows[2].find('[data-row-locked-through]').text()).toBe('Sin bloqueo')
    expect(tableRows[2].find('[data-row-source]').text()).toBe('Sin bloqueo')
    expect(tableRows[2].find('[data-row-set-by]').text()).toBe('—')
    expect(tableRows[2].find('[data-row-set-at]').text()).toBe('—')

    expect(tableRows[3].find('[data-row-module]').text()).toBe('Planes de tratamiento')

    expect(wrapper.find('[data-kpi-value="modules"]').text()).toBe('4')
    expect(wrapper.find('[data-kpi-value="locked"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="open"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('reads the global row for gerencia and the chosen sede on demand', async () => {
    const wrapper = await mountPage()

    expect(locksCalls()[0][0]).toBe('/api/accounting-locks')

    await chooseScope(wrapper, 'Sede Central Lima')

    expect(locksCalls().at(-1)[0]).toBe('/api/accounting-locks?branch_id=1')
    expect(rows(wrapper)).toHaveLength(4)

    await chooseScope(wrapper, 'Toda la organización')

    expect(locksCalls().at(-1)[0]).toBe('/api/accounting-locks')

    wrapper.unmount()
  })

  it('keeps the newest scope when an older read resolves later', async () => {
    const stalePayload = LOCKS().map(row =>
      row.module === 'cash_transactions'
        ? { ...row, locked_through: '2026-01-01', source: 'global', branch_id: null }
        : row
    )

    let resolveStale
    let lockReads = 0

    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/branches/active')) return { data: BRANCHES }

      lockReads += 1
      if (lockReads === 1) {
        return new Promise(resolve => {
          resolveStale = () => resolve({ data: stalePayload })
        })
      }

      return { data: LOCKS() }
    })

    const wrapper = await mountPage()
    await chooseScope(wrapper, 'Sede Central Lima')

    expect(rows(wrapper)[0].find('[data-row-locked-through]').text()).toBe('30 set 2026')

    resolveStale()
    await flushPromises()
    await flushPromises()

    expect(rows(wrapper)[0].find('[data-row-locked-through]').text()).toBe('30 set 2026')
    expect(rows(wrapper)[0].find('[data-row-source]').text()).toBe('Sede')

    wrapper.unmount()
  })

  it('drops the scope selector for a sede administrator and lets the API resolve their sede', async () => {
    const wrapper = await mountPage(BRANCH_ADMIN)

    expect(scopeSelect(wrapper)).toBeFalsy()
    expect(wrapper.find('[data-scope-fixed]').exists()).toBe(true)
    expect(wrapper.find('[data-scope-name]').text()).toBe('Sucursal Arequipa')
    expect(locksCalls()[0][0]).toBe('/api/accounting-locks')

    wrapper.unmount()
  })

  it('puts the lock with locked_through and branch_id, then reloads the table', async () => {
    putMock.mockImplementation(async () => {
      locksPayload = LOCKS().map(row =>
        row.module === 'quotations'
          ? { ...row, locked_through: '2026-10-15', source: 'branch', branch_id: 1, set_by: 1 }
          : row
      )
      return { data: { module: 'quotations', locked_through: '2026-10-15' } }
    })

    const wrapper = await mountPage()
    await chooseScope(wrapper, 'Sede Central Lima')

    await wrapper.findAll('[data-row-action="lock"]')[2].trigger('click')
    await flushPromises()

    expect(document.querySelector('[data-lock-modal]')).toBeTruthy()
    await fillLockDate('2026-10-15')
    await clickInBody('[data-lock-submit]')

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(putMock.mock.calls[0][0]).toBe('/api/accounting-locks/quotations')
    expect(putMock.mock.calls[0][1]).toEqual({
      locked_through: '2026-10-15',
      branch_id: 1
    })

    expect(locksCalls().at(-1)[0]).toBe('/api/accounting-locks?branch_id=1')
    expect(rows(wrapper)[2].find('[data-row-locked-through]').text()).toBe('15 oct 2026')
    expect(rows(wrapper)[2].find('[data-row-source]').text()).toBe('Sede')
    expect(document.querySelector('[data-lock-modal]')).toBeFalsy()
    expect(useToast().toasts.value.at(-1)?.message).toContain('15 oct 2026')

    wrapper.unmount()
  })

  it('puts the organization-wide lock without branch_id', async () => {
    const wrapper = await mountPage()

    await wrapper.findAll('[data-row-action="lock"]')[3].trigger('click')
    await flushPromises()
    await fillLockDate('2026-10-31')
    await clickInBody('[data-lock-submit]')

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(putMock.mock.calls[0][0]).toBe('/api/accounting-locks/treatment_plans')
    expect(putMock.mock.calls[0][1]).toEqual({ locked_through: '2026-10-31' })

    wrapper.unmount()
  })

  it('refuses to submit an empty date before reaching the API', async () => {
    const wrapper = await mountPage()

    await wrapper.findAll('[data-row-action="lock"]')[2].trigger('click')
    await flushPromises()
    await clickInBody('[data-lock-submit]')

    expect(putMock).not.toHaveBeenCalled()
    expect(document.querySelector('[data-lock-date]').textContent).toContain(
      'Indica la fecha de cierre contable.'
    )

    wrapper.unmount()
  })

  it('removes the lock through DELETE for the sede in scope and reloads', async () => {
    deleteMock.mockImplementation(async () => {
      locksPayload = LOCKS().map(row =>
        row.module === 'cash_transactions'
          ? { ...row, locked_through: null, source: 'none', branch_id: null, set_by: null }
          : row
      )
      return { data: { removed: true }, meta: { message: 'Bloqueo contable eliminado' } }
    })

    const wrapper = await mountPage()
    await chooseScope(wrapper, 'Sede Central Lima')

    await wrapper.findAll('[data-row-action="clear"]')[0].trigger('click')
    await flushPromises()

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(deleteMock).toHaveBeenCalledTimes(1)
    expect(deleteMock.mock.calls[0][0]).toBe('/api/accounting-locks/cash_transactions?branch_id=1')

    expect(rows(wrapper)[0].find('[data-row-locked-through]').text()).toBe('Sin bloqueo')
    expect(rows(wrapper)[0].find('[data-row-source]').text()).toBe('Sin bloqueo')
    expect(useToast().toasts.value.at(-1)?.message).toBe('Bloqueo contable eliminado')

    wrapper.unmount()
  })

  it('never removes a lock the administrator declined to confirm', async () => {
    confirmMock.mockResolvedValue(false)

    const wrapper = await mountPage()

    await wrapper.findAll('[data-row-action="clear"]')[0].trigger('click')
    await flushPromises()

    expect(deleteMock).not.toHaveBeenCalled()
    expect(rows(wrapper)[0].find('[data-row-source]').text()).toBe('Sede')

    wrapper.unmount()
  })

  it('hides fijar and retirar without manage.accounting_lock', async () => {
    permits.manageAccountingLock.value = false

    const wrapper = await mountPage()

    expect(rows(wrapper)).toHaveLength(4)
    expect(wrapper.find('[data-row-action="lock"]').exists()).toBe(false)
    expect(wrapper.find('[data-row-action="clear"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('offers the sidebar entry only to a session that holds manage.accounting_lock', async () => {
    const allowed = await mountPage()
    expect(allowed.find('a[href="/settings/accounting-locks"]').exists()).toBe(true)
    allowed.unmount()

    permits.manageAccountingLock.value = false

    const denied = await mountPage()
    expect(denied.find('a[href="/settings/accounting-locks"]').exists()).toBe(false)
    denied.unmount()
  })

  it('renders the retryable error state and recovers on retry', async () => {
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/accounting-locks')) {
        throw { status: 500, response: { data: { message: 'boom' } } }
      }
      return { data: BRANCHES }
    })

    const wrapper = await mountPage()

    const alert = wrapper.find('[data-locks-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(wrapper.find('[data-locks-table]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="modules"]').text()).toBe('N/D')

    getMock.mockImplementation(baseGet)
    await wrapper.find('[data-locks-retry]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-locks-error]').exists()).toBe(false)
    expect(rows(wrapper)).toHaveLength(4)

    wrapper.unmount()
  })

  it('renders the shared empty state when the service returns no modules', async () => {
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/accounting-locks')) return { data: [] }
      return { data: BRANCHES }
    })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-locks-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-locks-empty]').text()).toContain('No hay módulos para mostrar')
    expect(wrapper.find('[data-locks-table]').exists()).toBe(false)

    wrapper.unmount()
  })
})
