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
import { SPECIALTY_OPTIONS } from '../../../resources/js/modules/professionals/professionals.constants'
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

const openSelect = async select => {
  await select.find('button').trigger('click')
  await flushPromises()
  return select.findAll('[role="listbox"] button').map(button => button.text().trim())
}

const findSelectByPlaceholder = (wrapper, placeholder) =>
  wrapper
    .findAll('.select-container')
    .find(select => select.find('button').text().includes(placeholder))

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

  it('renders the SPECIALTY_OPTIONS labels in the filter and modal and submits the matching value', async () => {
    installTeam([
      makeUser(1, { name: 'Ana Torres', specialty: 'oral_surgery' }),
      makeUser(2, { name: 'Beto Salas', specialty: 'endodontics' })
    ])
    const wrapper = await mountPage({ stubs: { teleport: true } })
    const expectedLabels = SPECIALTY_OPTIONS.map(option => option.label)

    const filter = wrapper.find('[data-professionals-specialty-filter]')
    expect(await openSelect(filter)).toEqual(expectedLabels)

    const filterOption = filter
      .findAll('[role="listbox"] button')
      .find(button => button.text().includes('Cirugía Oral'))
    await filterOption.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-team-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.text()).not.toContain('Beto Salas')

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    const modalSpecialtySelect = findSelectByPlaceholder(wrapper, 'Seleccionar especialidad')
    expect(await openSelect(modalSpecialtySelect)).toEqual(expectedLabels)

    const modalOption = modalSpecialtySelect
      .findAll('[role="listbox"] button')
      .find(button => button.text().includes('Endodoncia'))
    await modalOption.trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-name] input').setValue('Nuevo Pro')
    await wrapper.find('[data-professionals-form-email] input').setValue('nuevo@test.com')
    await findButtonByText(wrapper, 'Crear').trigger('click')
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock.mock.calls[0][1].specialty).toBe('endodontics')

    wrapper.unmount()
  })

  it('labels every specialty code stored in the database and offers them in the filter', async () => {
    installTeam([
      makeUser(1, { name: 'Ana Torres', specialty: 'implantology' }),
      makeUser(2, { name: 'Beto Salas', specialty: 'dental_technician' }),
      makeUser(3, { name: 'Carla Díaz', specialty: 'general' })
    ])
    const wrapper = await mountPage()

    const rows = wrapper.findAll('[data-team-row]')
    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('Implantología')
    expect(rows[1].text()).toContain('Técnico Dental')
    expect(wrapper.text()).not.toContain('implantology')
    expect(wrapper.text()).not.toContain('dental_technician')

    const filter = wrapper.find('[data-professionals-specialty-filter]')
    const labels = await openSelect(filter)
    expect(labels).toContain('Implantología')
    expect(labels).toContain('Técnico Dental')

    const technicalOption = filter
      .findAll('[role="listbox"] button')
      .find(button => button.text().trim() === 'Técnico Dental')
    expect(technicalOption).toBeTruthy()
    if (!technicalOption) return

    await technicalOption.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-team-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Beto Salas')

    wrapper.unmount()
  })

  it('submits the edited values including the selected role to PUT /api/users/{id}', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage({ stubs: { teleport: true } })

    const row = wrapper.findAll('[data-team-row]')[0]
    await row
      .findAll('button')
      .find(button => button.text().includes('Editar'))
      .trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-professionals-edit-name] input').element.value).toBe('Ana Torres')

    await wrapper.find('[data-professionals-edit-name] input').setValue('Ana Torres Editada')
    await chooseSelectOption(wrapper.find('[data-professionals-role-edit]'), 'Asistente')

    await findButtonByText(wrapper, 'Actualizar').trigger('click')
    await flushPromises()

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(putMock.mock.calls[0][0]).toBe('/api/users/1')
    expect(putMock.mock.calls[0][1].name).toBe('Ana Torres Editada')
    expect(putMock.mock.calls[0][1].role).toBe('asistente')

    wrapper.unmount()
  })

  it('routes to the professional detail page from the row action', async () => {
    installTeam(TEAM)
    const pushSpy = vi.spyOn(router, 'push')
    const wrapper = await mountPage()

    const row = wrapper.findAll('[data-team-row]')[1]
    await row
      .findAll('button')
      .find(button => button.text().includes('Ver Detalle'))
      .trigger('click')
    await flushPromises()

    expect(pushSpy).toHaveBeenCalledTimes(1)
    expect(pushSpy).toHaveBeenCalledWith('/professionals/2')

    pushSpy.mockRestore()
    wrapper.unmount()
  })

  it('shows the clear-filters affordance only while filters are active', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()
    const search = wrapper.find('[data-professionals-search] input')

    expect(wrapper.findAll('[data-team-clear-filters]')).toHaveLength(0)

    await search.setValue('ana')
    expect(wrapper.findAll('[data-team-clear-filters]')).toHaveLength(1)

    await wrapper.find('[data-team-clear-filters]').trigger('click')
    await flushPromises()

    expect(search.element.value).toBe('')
    expect(wrapper.findAll('[data-team-clear-filters]')).toHaveLength(0)
    expect(wrapper.findAll('[data-team-row]')).toHaveLength(3)

    wrapper.unmount()
  })

  it('submits licence, branch and status from the create modal', async () => {
    installTeam(TEAM, [{ id: 7, name: 'Sede Miraflores' }])
    const wrapper = await mountPage({ stubs: { teleport: true } })

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-name] input').setValue('Nuevo Pro')
    await wrapper.find('[data-professionals-form-email] input').setValue('nuevo@test.com')
    await wrapper.find('[data-professionals-form-license] input').setValue('COP-2026')
    await chooseSelectOption(wrapper.find('[data-professionals-form-branch]'), 'Sede Miraflores')
    await chooseSelectOption(wrapper.find('[data-professionals-form-status]'), 'Inactivo')

    await findButtonByText(wrapper, 'Crear').trigger('click')
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    const payload = postMock.mock.calls[0][1]
    expect(payload.professional_license).toBe('COP-2026')
    expect(payload.branch_id).toBe(7)
    expect(payload.is_active).toBe(false)

    wrapper.unmount()
  })

  it('prefills licence, branch and status when editing and submits them to PUT', async () => {
    installTeam(
      [
        makeUser(1, {
          name: 'Ana Torres',
          professional_license: 'CMP-999',
          branch_id: 7,
          is_active: false
        })
      ],
      [{ id: 7, name: 'Sede Miraflores' }]
    )
    const wrapper = await mountPage({ stubs: { teleport: true } })

    const row = wrapper.findAll('[data-team-row]')[0]
    await row
      .findAll('button')
      .find(button => button.text().includes('Editar'))
      .trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-professionals-edit-license] input').element.value).toBe('CMP-999')
    expect(wrapper.find('[data-professionals-edit-branch]').text()).toContain('Sede Miraflores')
    expect(wrapper.find('[data-professionals-edit-status]').text()).toContain('Inactivo')

    await wrapper.find('[data-professionals-edit-license] input').setValue('CMP-1000')
    await findButtonByText(wrapper, 'Actualizar').trigger('click')
    await flushPromises()

    expect(putMock).toHaveBeenCalledTimes(1)
    const payload = putMock.mock.calls[0][1]
    expect(payload.professional_license).toBe('CMP-1000')
    expect(payload.branch_id).toBe(7)
    expect(payload.is_active).toBe(false)

    wrapper.unmount()
  })

  it('maps a 422 field error to the form field instead of a generic toast', async () => {
    postMock.mockRejectedValueOnce({
      response: {
        data: {
          message: 'Error de validación',
          errors: { email: ['El email ya está registrado.'] }
        }
      },
      status: 422
    })
    const wrapper = await mountPage({ stubs: { teleport: true } })

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-name] input').setValue('Nuevo Pro')
    await wrapper.find('[data-professionals-form-email] input').setValue('repetido@test.com')
    await findButtonByText(wrapper, 'Crear').trigger('click')
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('El email ya está registrado.')

    wrapper.unmount()
  })

  it('submits the create form when Enter is pressed in a field', async () => {
    const wrapper = await mountPage({ stubs: { teleport: true } })

    await findButtonByText(wrapper, 'Nuevo Profesional').trigger('click')
    await flushPromises()

    await wrapper.find('[data-professionals-form-name] input').setValue('Nuevo Pro')
    await wrapper.find('[data-professionals-form-email] input').setValue('nuevo@test.com')
    await wrapper.find('[data-professionals-form-name] input').trigger('keydown.enter')
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })
})
