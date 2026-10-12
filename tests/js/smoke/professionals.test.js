import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3/T4 (D2/D3/D5/D12): the page loads ONE collection for the whole clinical
// team and derives KPIs, rendered rows, search and specialty filter from it.
// useApi and useConfirm are the only mocked boundaries. Branch names come from
// the /api/branches/active dictionary (id -> name); it is a lookup, never a
// second team source.
const { getMock, postMock, putMock, removeMock, confirmMock, makeUser, installTeam } = vi.hoisted(
  () => {
    const makeUser = (id, overrides = {}) => ({
      id,
      name: `Profesional ${id}`,
      username: `prof${id}`,
      email: `prof${id}@test.com`,
      phone: '999999999',
      specialty: 'general',
      role: 'odontologo',
      professional_license: '',
      branch_id: null,
      is_active: true,
      ...overrides
    })

    const state = { users: [], branches: [] }

    const getMock = vi.fn(async url => {
      if (typeof url === 'string' && url.startsWith('/api/users?role=')) {
        return { data: state.users }
      }
      if (typeof url === 'string' && url.startsWith('/api/branches')) {
        return { data: state.branches }
      }
      return { data: [] }
    })

    const installTeam = (users, branches = []) => {
      state.users = users
      state.branches = branches
    }

    return {
      getMock,
      postMock: vi.fn(async () => ({ data: {} })),
      putMock: vi.fn(async () => ({ data: {} })),
      removeMock: vi.fn(async () => ({ data: {} })),
      confirmMock: vi.fn(async () => true),
      makeUser,
      installTeam
    }
  }
)

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: putMock,
    patch: async () => ({ data: {} }),
    delete: removeMock,
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useConfirm', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useConfirm: () => ({
      confirm: confirmMock,
      isOpen: actual.confirmIsOpen,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})
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
import Page from '../../../resources/js/modules/professionals/ProfessionalsPage.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async (options = {}) => {
  const wrapper = mount(Page, {
    global: { plugins: [router, uiComponents], stubs: options.stubs }
  })
  await flushPromises()
  return wrapper
}

const findButtonByText = (wrapper, text) =>
  wrapper.findAll('button').find(button => button.text().trim() === text)

const chooseSelectOption = async (select, label) => {
  await select.find('button').trigger('click')
  const option = select.findAll('button').find(button => button.text().includes(label))
  await option.trigger('click')
  await flushPromises()
}

// 2 active / 1 inactive; specialties: orthodontics x2, endodontics x1.
const TEAM = [
  makeUser(1, {
    name: 'Ana Torres',
    username: 'atorres',
    email: 'ana@test.com',
    specialty: 'orthodontics',
    is_active: true
  }),
  makeUser(2, {
    name: 'Beto Salas',
    username: 'bsalas',
    email: 'beto@test.com',
    specialty: 'orthodontics',
    is_active: true
  }),
  makeUser(3, {
    name: 'Carla Díaz',
    username: 'cdiaz',
    email: 'carla@test.com',
    specialty: 'endodontics',
    is_active: false
  })
]

describe('professionals smoke', () => {
  beforeEach(() => {
    getMock.mockClear()
    postMock.mockClear()
    putMock.mockClear()
    removeMock.mockClear()
    confirmMock.mockClear()
    confirmMock.mockResolvedValue(true)
    installTeam([])
  })

  it('mounts ProfessionalsPage.vue and renders content', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Profesionales')
    wrapper.unmount()
  })

  it('derives the four team KPIs from the single loaded collection', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()

    const strip = wrapper.find('[data-team-kpis]')
    expect(strip.findAll('[data-stat-card]')).toHaveLength(4)

    expect(strip.find('[data-stat="team-total"]').text()).toBe('3')
    expect(strip.find('[data-stat="team-active"]').text()).toBe('2')
    expect(strip.find('[data-stat="team-inactive"]').text()).toBe('1')
    expect(strip.find('[data-stat="team-specialties"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('filters rendered rows client-side from the search input', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()
    const search = wrapper.find('[data-professionals-search] input')

    expect(wrapper.findAll('[data-team-row]')).toHaveLength(3)

    await search.setValue('ana')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Ana Torres')

    await search.setValue('cdiaz')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Carla Díaz')

    await search.setValue('sin coincidencias')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('filters rendered rows by specialty', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()

    await chooseSelectOption(wrapper.find('[data-professionals-specialty-filter]'), 'Ortodoncia')

    expect(wrapper.findAll('[data-team-row]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.text()).toContain('Beto Salas')
    expect(wrapper.text()).not.toContain('Carla')

    wrapper.unmount()
  })

  it('shows license, branch and role cells with the N/D and Sin sucursal fallbacks', async () => {
    const team = [
      makeUser(1, {
        name: 'Ana Torres',
        role: 'tecnico_dental',
        professional_license: 'CMP-12345',
        branch_id: 7
      }),
      makeUser(2, {
        name: 'Beto Salas',
        professional_license: '',
        branch_id: 99
      }),
      makeUser(3, {
        name: 'Carla Díaz',
        professional_license: null,
        branch_id: null
      })
    ]
    installTeam(team, [{ id: 7, name: 'Sede Miraflores' }])
    const wrapper = await mountPage()

    const rows = wrapper.findAll('[data-team-row]')
    expect(rows).toHaveLength(3)

    expect(rows[0].text()).toContain('CMP-12345')
    expect(rows[0].text()).toContain('Sede Miraflores')
    expect(rows[0].text()).toContain('Técnico Dental')

    expect(rows[1].text()).toContain('N/D')

    expect(rows[2].text()).toContain('N/D')
    expect(rows[2].text()).toContain('Sin sucursal')

    wrapper.unmount()
  })

  it('counts results in the block header, honoring active filters', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()
    const results = wrapper.find('[data-team-results]')

    expect(results.text()).toBe('3 profesionales')

    await wrapper.find('[data-professionals-search] input').setValue('ana')
    expect(results.text()).toBe('1 de 3 profesionales')

    wrapper.unmount()
  })

  it('submits the selected role in the create payload', async () => {
    const wrapper = await mountPage({ stubs: { teleport: true } })

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-name] input').setValue('Nuevo Pro')
    await wrapper.find('[data-professionals-form-email] input').setValue('nuevo@test.com')
    await chooseSelectOption(wrapper.find('[data-professionals-role]'), 'Técnico Dental')

    await findButtonByText(wrapper, 'Crear').trigger('click')
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock.mock.calls[0][0]).toBe('/api/users')
    expect(postMock.mock.calls[0][1].role).toBe('tecnico_dental')
    expect(postMock.mock.calls[0][1].name).toBe('Nuevo Pro')

    wrapper.unmount()
  })

  it('does not call the API on invalid submit and shows inline validation', async () => {
    const wrapper = await mountPage({ stubs: { teleport: true } })

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-email] input').setValue('nuevo@test.com')

    await findButtonByText(wrapper, 'Crear').trigger('click')
    await flushPromises()

    expect(postMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('El nombre es obligatorio.')

    wrapper.unmount()
  })

  it('deletes only after the confirm resolves true', async () => {
    installTeam(TEAM)
    confirmMock.mockResolvedValueOnce(true)
    const wrapper = await mountPage()

    const row = wrapper.findAll('[data-team-row]')[0]
    await row
      .findAll('button')
      .find(button => button.text().includes('Eliminar'))
      .trigger('click')
    await flushPromises()

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(removeMock).toHaveBeenCalledWith('/api/users/1')

    wrapper.unmount()
  })

  it('never calls the delete API when the confirm is cancelled', async () => {
    installTeam(TEAM)
    confirmMock.mockResolvedValueOnce(false)
    const wrapper = await mountPage()

    const row = wrapper.findAll('[data-team-row]')[0]
    await row
      .findAll('button')
      .find(button => button.text().includes('Eliminar'))
      .trigger('click')
    await flushPromises()

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(removeMock).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('shows the empty-team state when the collection is empty', async () => {
    installTeam([])
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('Sin profesionales registrados')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('clears filters from the no-results empty state and brings rows back', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()
    const search = wrapper.find('[data-professionals-search] input')

    await chooseSelectOption(wrapper.find('[data-professionals-specialty-filter]'), 'Ortodoncia')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(2)

    await search.setValue('sin coincidencias')
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(0)
    expect(wrapper.text()).toContain('Sin resultados')

    await wrapper.findAll('[data-team-clear-filters]')[0].trigger('click')
    await flushPromises()

    expect(search.element.value).toBe('')
    expect(wrapper.find('[data-professionals-specialty-filter]').text()).toContain(
      'Todas las especialidades'
    )
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(3)

    wrapper.unmount()
  })
})
