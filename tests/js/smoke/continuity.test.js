import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// F4 (#310) — the reception desk of continuity renders the two populations the
// queue publishes, the recall metric verbatim, and the complete load states.
// Transports and authorization are mocked: the page only consumes their
// contracts.
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  canViewContinuity: true,
  canManageContinuity: true,
  channelStub: { listen: vi.fn() },
  privateChannel: vi.fn(),
  leave: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: harness.post,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: harness.leave },
    channel: () => harness.channelStub,
    privateChannel: harness.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

vi.mock('../../../resources/js/composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: { id: 4, name: 'Rosa Recepción', role: 'recepcionista' } },
    isAuthenticated: { value: true }
  })
}))

// S9 — the two write actions are gated on `manage.continuity`. A mutable handle
// lets one test mount the same page as a clinician who may only read it.
// S15 — `view.continuity` gates the queue itself: finance reaches this desk for
// its commercial answers without that grant.
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      viewContinuity: { value: harness.canViewContinuity },
      viewQuotation: { value: !harness.canViewContinuity },
      manageContinuity: { value: harness.canManageContinuity }
    }
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/continuity/ContinuityPage.vue'

const recall = (id, patient, reason, dueDate, months = 6) => ({
  id,
  patient_id: id + 100,
  interval_months: months,
  reason,
  due_date: dueDate,
  status: 'pending',
  notes: null,
  patient: { id: id + 100, first_name: patient[0], last_name: patient[1] }
})

const request = (id, extra = {}) => ({
  id,
  patient_id: id + 200,
  kind: 'next_session',
  status: 'pending',
  preferred_from: '2026-11-02T09:00:00',
  preferred_to: '2026-11-02T09:30:00',
  duration_minutes: 30,
  notes: null,
  appointment_type_id: 2,
  treatment_plan_id: null,
  clinical_encounter_id: 8,
  appointment_id: 14,
  patient: { id: id + 200, full_name: 'Luis Rojas' },
  appointment_type: { id: 2, name: 'Control' },
  treatment_plan: null,
  clinical_encounter: { id: 8 },
  ...extra
})

const queuePayload = (recalls, requests) => ({
  data: {
    recalls,
    requests,
    counts: {
      recalls: recalls.length,
      requests: requests.length,
      total: recalls.length + requests.length
    }
  },
  meta: { date: '2026-10-23' }
})

const fullPayload = () =>
  queuePayload(
    [
      recall(31, ['Ana', 'Torres'], 'Control semestral', '2026-10-01', 6),
      recall(32, ['María', 'Quispe'], 'Revisión anual', '2026-09-15', 12)
    ],
    [request(41)]
  )

const emptyPayload = () => queuePayload([], [])

const metricsPayload = rate => ({
  data: {
    total: 10,
    pending: 2,
    scheduled: 3,
    completed: rate === null ? 0 : 5,
    cancelled: 1,
    overdue: 2,
    compliance_rate: rate
  }
})

// Lookups the schedule modal reads on open; the two writes validate the very
// same professional and chair (S10).
const lookups = {
  '/api/users/active': [
    { id: 7, name: 'Dra. Elena Paredes', role: 'odontologo' },
    { id: 9, name: 'Dr. Iván Ríos', role: 'implantologo' }
  ],
  '/api/dental-chairs/active': [
    { id: 1, name: 'Sillón 1', code: 'S1', status: 'active' },
    { id: 2, name: 'Sillón 2', code: 'S2', status: 'active' }
  ],
  '/api/appointment-types/active': [
    { id: 2, name: 'Control', default_duration_minutes: 30 },
    { id: 5, name: 'Revisión', default_duration_minutes: 45 }
  ]
}

const mockApi = (queueResponse, rate = 0.6667, pending = {}) => {
  harness.get.mockImplementation(async url => {
    if (url === '/api/patient-recalls/metrics') return metricsPayload(rate)
    if (url === '/api/dashboard/pending') return { data: pending }
    if (lookups[url]) return { data: lookups[url] }
    return queueResponse
  })
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/continuity')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const queueCalls = () =>
  harness.get.mock.calls.filter(([url]) => url === '/api/continuity/queue').length

const pendingCalls = () =>
  harness.get.mock.calls.filter(([url]) => url === '/api/dashboard/pending').length

// Intl rendering uses U+00A0 between the currency glyph and the amount.
const normalize = value => value.replace(/\u00a0/g, ' ')

// Local-time ISO strings so the rendered day is stable across test timezones.
const localIso = (year, monthIndex, day) => new Date(year, monthIndex, day, 10, 0).toISOString()

const quotationItem = (
  id,
  patientName,
  status = 'sent',
  total = 149.9,
  createdAt = localIso(2026, 9, 3)
) => ({
  id,
  patient_name: patientName,
  total_amount: total,
  status,
  created_at: createdAt
})

const planItem = (
  id,
  patientName,
  status = 'proposed',
  cost = 249.5,
  createdAt = localIso(2026, 9, 2)
) => ({
  id,
  patient_name: patientName,
  title: 'Plan de tratamiento',
  final_cost: cost,
  status,
  created_at: createdAt
})

// Modals teleport to <body>, so their fields are queried from the document.
const bodyField = selector => document.body.querySelector(selector)

const chooseOption = async (selector, label) => {
  const container = bodyField(selector)
  expect(container, `field "${selector}" must render`).toBeTruthy()

  const trigger = container.querySelector('button[aria-haspopup="true"]')
  expect(trigger, `field "${selector}" must render a select`).toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...container.querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist`).toBeTruthy()
  option.click()
  await flushPromises()
}

const openSchedule = async (wrapper, kind) => {
  await wrapper.find(`[data-continuity-schedule="${kind}"]`).trigger('click')
  await flushPromises()
}

const submitSchedule = async () => {
  const button = bodyField('[data-schedule-submit]')
  expect(button).toBeTruthy()
  button.click()
  await flushPromises()
}

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 4, name: 'Rosa Recepción', role: 'recepcionista' })
  )
  document.body.innerHTML = ''
  harness.canViewContinuity = true
  harness.canManageContinuity = true
  harness.get.mockReset()
  harness.post.mockReset()
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()
  harness.privateChannel.mockReset()
  harness.leave.mockReset()
})

describe('continuity desk', () => {
  it('renders the hero and the four KPIs from the API payload', async () => {
    mockApi(fullPayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-page-header]').exists()).toBe(true)
    expect(wrapper.find('[data-page-header-title]').text()).toBe('Pendientes')

    // S15 - the page names the three populations it resolves.
    const hero = wrapper.find('[data-page-header]').text()
    expect(hero).toContain('Pendientes')
    expect(hero).toContain('Recalls vencidos')
    expect(hero).toContain('solicitudes de continuidad')
    expect(hero).toContain('esperando')

    expect(wrapper.find('[data-kpi-card="recalls"] [data-kpi-value]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-card="requests"] [data-kpi-value]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-card="total"] [data-kpi-value]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-card="compliance"] [data-kpi-value]').text()).toBe('67%')

    wrapper.unmount()
  })

  it('shows the compliance rate the API published, verbatim', async () => {
    mockApi(fullPayload(), 0.5)

    const wrapper = await mountPage()

    const raw = wrapper.find('[data-kpi-card="compliance"] [data-kpi-raw]')
    expect(raw.attributes('data-kpi-raw')).toBe('0.5')
    expect(wrapper.find('[data-kpi-card="compliance"] [data-kpi-value]').text()).toBe('50%')

    wrapper.unmount()
  })

  it('renders both populations with their mapped columns', async () => {
    mockApi(fullPayload())

    const wrapper = await mountPage()

    const recalls = wrapper.find('[data-continuity-section="recalls"]')
    expect(recalls.find('[data-continuity-table="recalls"]').exists()).toBe(true)
    const recallText = recalls.text()
    expect(recallText).toContain('Ana Torres')
    expect(recallText).toContain('Control semestral')
    expect(recallText).toContain('01 oct. 2026')
    expect(recallText).toContain('6 meses')
    expect(recallText).toContain('María Quispe')
    expect(recallText).toContain('12 meses')
    expect(recallText).toContain('Vencidos y sin cita agendada')
    // Unit A2 fills this column; T2 only has to publish it.
    expect(recallText).toContain('Acciones')

    const requests = wrapper.find('[data-continuity-section="requests"]')
    expect(requests.find('[data-continuity-table="requests"]').exists()).toBe(true)
    const requestText = requests.text()
    expect(requestText).toContain('Luis Rojas')
    expect(requestText).toContain('Próxima sesión')
    expect(requestText).toContain('02 nov. 2026, 09:00 – 09:30')
    expect(requestText).toContain('30 min')
    expect(requestText).toContain('Atención')
    expect(requestText).toContain('Conducta pendiente de materializar')
    expect(requestText).toContain('Acciones')

    wrapper.unmount()
  })

  it('renders the two empty states when both arrays come back empty', async () => {
    mockApi(emptyPayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-empty="recalls"]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-empty="requests"]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="requests"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('reports a failed load and refetches the queue from the retry button', async () => {
    // Module state is a singleton: start from a known empty queue so the retry
    // panel is the only section surface left after the failure.
    mockApi(emptyPayload())
    const primer = await mountPage()
    primer.unmount()

    harness.get.mockRejectedValue({
      response: { data: { message: 'No se pudo conectar con el servidor.' } }
    })

    const wrapper = await mountPage()

    const panel = wrapper.find('[data-continuity-error]')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('role')).toBe('alert')
    expect(panel.text()).toContain('No se pudo conectar con el servidor.')
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="recalls"]').exists()).toBe(false)

    const before = queueCalls()
    mockApi(fullPayload())
    await wrapper.find('[data-continuity-retry]').trigger('click')
    await flushPromises()

    expect(queueCalls()).toBe(before + 1)
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('continuity desk actions', () => {
  it('gates both row actions on manage.continuity', async () => {
    mockApi(fullPayload())

    harness.canManageContinuity = false
    const readOnly = await mountPage()

    expect(readOnly.findAll('[data-continuity-schedule]')).toHaveLength(0)
    expect(readOnly.findAll('[data-continuity-dismiss]')).toHaveLength(0)
    readOnly.unmount()

    harness.canManageContinuity = true
    const manager = await mountPage()

    expect(manager.findAll('[data-continuity-schedule="recall"]')).toHaveLength(2)
    expect(manager.findAll('[data-continuity-schedule="request"]')).toHaveLength(1)
    expect(manager.findAll('[data-continuity-dismiss]')).toHaveLength(1)
    manager.unmount()
  })

  it('schedules a continuity request with the preferred date and the chosen slot', async () => {
    mockApi(fullPayload())
    harness.post.mockResolvedValue({ data: { request: request(41), appointment: { id: 90 } } })

    const wrapper = await mountPage()
    const before = queueCalls()

    await openSchedule(wrapper, 'request')

    expect(bodyField('[data-schedule-title]').textContent).toContain('Agendar cita de continuidad')
    expect(bodyField('[data-schedule-patient]').textContent).toContain('Luis Rojas')
    expect(bodyField('[data-sc-scheduled-at] input').value).toBe('2026-11-02T09:00')

    // #346 — both placeholders stay on one line: the professional field uses
    // the short copy and the select primitive never wraps a placeholder.
    const userPlaceholder = bodyField(
      '[data-sc-user] button[aria-haspopup="true"] span.text-theme-secondary'
    )
    expect(userPlaceholder.textContent.trim()).toBe('Seleccionar')
    expect(userPlaceholder.classList.contains('truncate')).toBe(true)

    const chairPlaceholder = bodyField(
      '[data-sc-chair] button[aria-haspopup="true"] span.text-theme-secondary'
    )
    expect(chairPlaceholder.textContent.trim()).toBe('Seleccionar ambiente')
    expect(chairPlaceholder.classList.contains('truncate')).toBe(true)

    await chooseOption('[data-sc-user]', 'Dra. Elena Paredes')
    await chooseOption('[data-sc-chair]', 'Sillón 1')
    await submitSchedule()

    expect(harness.post).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.post.mock.calls[0]
    expect(url).toBe('/api/continuity-requests/41/schedule')
    expect(payload.scheduled_at).toBe('2026-11-02 09:00:00')
    expect(payload.user_id).toBe(7)
    expect(payload.dental_chair_id).toBe(1)
    expect(payload.duration_minutes).toBe(30)
    expect(payload.appointment_type_id).toBe(2)

    expect(queueCalls()).toBe(before + 1)
    wrapper.unmount()
  })

  it('schedules an overdue recall through its own door', async () => {
    mockApi(fullPayload())
    harness.post.mockResolvedValue({
      data: {
        recall: recall(31, ['Ana', 'Torres'], 'Control semestral', '2026-10-01', 6),
        appointment: { id: 91 }
      }
    })

    const wrapper = await mountPage()
    const before = queueCalls()

    await openSchedule(wrapper, 'recall')

    expect(bodyField('[data-schedule-title]').textContent).toContain('Agendar cita de control')
    expect(bodyField('[data-sc-scheduled-at] input').value).toMatch(/^\d{4}-\d{2}-\d{2}T09:00$/)

    await chooseOption('[data-sc-user]', 'Dra. Elena Paredes')
    await chooseOption('[data-sc-chair]', 'Sillón 1')
    await chooseOption('[data-sc-type]', 'Control')
    await submitSchedule()

    const [url, payload] = harness.post.mock.calls[0]
    expect(url).toBe('/api/patient-recalls/31/schedule')
    expect(payload.user_id).toBe(7)
    expect(payload.dental_chair_id).toBe(1)
    expect(payload.appointment_type_id).toBe(2)
    expect(payload.scheduled_at).toMatch(/^\d{4}-\d{2}-\d{2} 09:00:00$/)

    expect(queueCalls()).toBe(before + 1)
    wrapper.unmount()
  })

  it('maps a slot conflict onto the chair field and keeps the row in the queue', async () => {
    mockApi(fullPayload())
    harness.post.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'The given data was invalid.',
          errors: { dental_chair_id: ['El sillón ya está reservado en ese horario.'] }
        }
      }
    })

    const wrapper = await mountPage()
    const before = queueCalls()

    await openSchedule(wrapper, 'request')
    await chooseOption('[data-sc-user]', 'Dra. Elena Paredes')
    await chooseOption('[data-sc-chair]', 'Sillón 1')
    await submitSchedule()

    const fieldError = bodyField('[data-schedule-error="dental_chair_id"]')
    expect(fieldError).toBeTruthy()
    expect(fieldError.textContent).toContain('El sillón ya está reservado en ese horario.')

    // The composable only reloads on success, so the row never left the desk.
    expect(queueCalls()).toBe(before)
    expect(wrapper.find('[data-continuity-table="requests"]').text()).toContain('Luis Rojas')
    expect(bodyField('[data-schedule-modal]')).toBeTruthy()

    wrapper.unmount()
  })

  it('refuses a dismissal without a reason and posts the reason once written', async () => {
    mockApi(fullPayload())
    harness.post.mockResolvedValue({ data: request(41, { status: 'dismissed' }) })

    const wrapper = await mountPage()
    const before = queueCalls()

    await wrapper.find('[data-continuity-dismiss]').trigger('click')
    await flushPromises()

    expect(bodyField('[data-dismiss-submit]').disabled).toBe(true)

    bodyField('[data-dismiss-form]').dispatchEvent(
      new Event('submit', { bubbles: true, cancelable: true })
    )
    await flushPromises()

    expect(harness.post).not.toHaveBeenCalled()
    expect(bodyField('[data-dismiss-error]').textContent).toContain('El motivo es obligatorio.')

    const reason = bodyField('[data-dismiss-reason]')
    reason.value = '  El paciente ya agendó por su cuenta.  '
    reason.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    bodyField('[data-dismiss-submit]').click()
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/continuity-requests/41/dismiss', {
      reason: 'El paciente ya agendó por su cuenta.'
    })
    expect(queueCalls()).toBe(before + 1)
    wrapper.unmount()
  })
})

// S15 - the desk absorbs the commercial pending answers the dashboard used to
// publish. The section reads GET /api/dashboard/pending, whose payload key is
// the visibility contract: an absent subset means the role cannot read that
// module, so its rows never render and no zero is invented for it.
describe('continuity pending answers', () => {
  const pendingSection = wrapper => wrapper.find('[data-continuity-section="pending"]')

  it('renders the merged answers with type badges sorted by registration date', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: {
        count: 2,
        items: [
          quotationItem(11, 'Ana Torres Quispe', 'viewed', 149.9, localIso(2026, 9, 3)),
          quotationItem(12, 'Luis Rojas', 'sent', 89, localIso(2026, 9, 1))
        ]
      },
      treatment_plans: {
        count: 1,
        items: [planItem(21, 'Marta Díaz', 'proposed', 249.5, localIso(2026, 9, 2))]
      }
    })

    const wrapper = await mountPage()

    const section = pendingSection(wrapper)
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Esperando respuesta')
    expect(section.text()).toContain('Presupuestos y planes de tratamiento')

    const rows = section.findAll('[data-continuity-table="pending"] tbody tr')
    expect(rows).toHaveLength(3)

    // Sorted by registration date ascending: 1 oct, 2 oct, 3 oct.
    expect(rows[0].text()).toContain('Luis Rojas')
    expect(rows[0].text()).toContain('01 oct. 2026')
    expect(rows[1].text()).toContain('Marta Díaz')
    expect(rows[1].text()).toContain('02 oct. 2026')
    expect(rows[2].text()).toContain('Ana Torres Quispe')
    expect(rows[2].text()).toContain('03 oct. 2026')

    // Row anatomy: type badge, amount (per-type field), status badge.
    expect(normalize(rows[0].text())).toContain('S/ 89.00')
    expect(rows[0].text()).toContain('Presupuesto')
    expect(rows[0].text()).toContain('Enviado')
    expect(normalize(rows[1].text())).toContain('S/ 249.50')
    expect(rows[1].text()).toContain('Plan de tratamiento')
    expect(rows[1].text()).toContain('Propuesto')
    expect(rows[2].text()).toContain('Visto')

    // One action per row, none at the section level.
    for (const row of rows) {
      expect(row.findAll('button')).toHaveLength(1)
    }
    expect(section.text()).not.toContain('Ver todos')

    // The queue above keeps working: its own endpoint is asked exactly once.
    expect(queueCalls()).toBe(1)
    expect(pendingCalls()).toBe(1)

    wrapper.unmount()
  })

  it('routes each answer to its module list', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] },
      treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
    })

    const wrapper = await mountPage()

    await wrapper.find('[data-continuity-pending-view="quotations"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.$router.currentRoute.value.path).toBe('/quotations')

    await wrapper.vm.$router.push('/continuity')
    await flushPromises()

    await wrapper.find('[data-continuity-pending-view="treatment-plans"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.$router.currentRoute.value.path).toBe('/treatment-plans')

    wrapper.unmount()
  })

  it('renders only the readable subset and invents no zero for the absent one', async () => {
    mockApi(fullPayload(), 0.6667, {
      treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
    })

    const wrapper = await mountPage()

    const section = pendingSection(wrapper)
    expect(section.exists()).toBe(true)
    expect(section.findAll('[data-continuity-pending-type]')).toHaveLength(1)
    expect(section.text()).toContain('Plan de tratamiento')
    expect(section.find('[data-continuity-pending-view="quotations"]').exists()).toBe(false)
    expect(section.find('[data-continuity-pending-view="treatment-plans"]').exists()).toBe(true)
    expect(section.text()).not.toContain('0 respondidas')

    // No half is announced as empty: the missing key is invisible.
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('hides the whole section when the payload carries neither subset', async () => {
    mockApi(fullPayload(), 0.6667, {})

    const wrapper = await mountPage()

    expect(pendingSection(wrapper).exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)
    expect(pendingCalls()).toBe(1)

    wrapper.unmount()
  })

  it('renders the shared empty state when every readable subset has no items', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 0, items: [] },
      treatment_plans: { count: 0, items: [] }
    })

    const wrapper = await mountPage()

    const empty = wrapper.find('[data-continuity-empty="pending"]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Sin respuestas pendientes')
    expect(wrapper.find('[data-continuity-table="pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the queue mounted when the answers fail and retries only that resource', async () => {
    let pendingAttempt = 0
    harness.get.mockImplementation(async url => {
      if (url === '/api/patient-recalls/metrics') return metricsPayload(0.6667)
      if (url === '/api/dashboard/pending') {
        pendingAttempt += 1
        if (pendingAttempt === 1) {
          throw { response: { data: { message: 'No se pudo conectar con el servidor.' } } }
        }
        return {
          data: { quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] } }
        }
      }
      return fullPayload()
    })

    const wrapper = await mountPage()

    const error = wrapper.find('[data-continuity-pending-error]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar las respuestas pendientes')
    expect(error.text()).toContain('No se pudo conectar con el servidor.')

    // The queue above answered 200 and stays mounted: its own error is absent.
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(true)
    expect(queueCalls()).toBe(1)

    await wrapper.find('[data-continuity-pending-retry]').trigger('click')
    await flushPromises()

    expect(pendingCalls()).toBe(2)
    expect(queueCalls()).toBe(1)
    expect(wrapper.find('[data-continuity-pending-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="pending"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('keeps the queue retry scoped when the two loads fail together', async () => {
    harness.get.mockRejectedValue({ response: { data: { message: 'Sin conexión.' } } })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-error]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-pending-error]').exists()).toBe(true)

    const before = pendingCalls()
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] }
    })
    await wrapper.find('[data-continuity-retry]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(true)
    // The queue retry never re-issues the pending resource.
    expect(pendingCalls()).toBe(before)

    wrapper.unmount()
  })
})

// S15 - finance reads quotations but holds no `view.continuity`. The same desk
// must serve it without a queue (and without a 403), showing only the
// commercial answers; the reverse (queue, no answers) is the continuity desks'
// case already covered above.
describe('continuity desk for finance', () => {
  beforeEach(() => {
    harness.canViewContinuity = false
  })

  it('does not call the queue and renders no queue section, KPI or error', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] }
    })

    const wrapper = await mountPage()

    const requested = harness.get.mock.calls.map(([url]) => url)
    expect(requested).not.toContain('/api/continuity/queue')
    expect(requested).not.toContain('/api/patient-recalls/metrics')
    expect(requested).toContain('/api/dashboard/pending')

    expect(wrapper.find('[data-continuity-kpis]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-loading]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-section="recalls"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-section="requests"]').exists()).toBe(false)

    // No invented zero: the page body never advertises the queue it cannot read.
    const body = wrapper.find('[data-continuity]')
    expect(body.text()).not.toContain('Recalls vencidos')
    expect(body.text()).not.toContain('Sin recalls vencidos')
    expect(body.text()).toContain('Presupuestos y planes de tratamiento esperando respuesta.')

    // The commercial answers are the whole desk for this session.
    const section = wrapper.find('[data-continuity-section="pending"]')
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Ana Torres Quispe')
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('refreshes only the commercial answers from the hero control', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] }
    })

    const wrapper = await mountPage()
    const beforePending = pendingCalls()
    const beforeQueue = queueCalls()

    await wrapper.find('[data-continuity-refresh]').trigger('click')
    await flushPromises()

    expect(pendingCalls()).toBe(beforePending + 1)
    expect(queueCalls()).toBe(beforeQueue)

    wrapper.unmount()
  })

  it('hides the desk entirely when the session reads neither quotations nor plans', async () => {
    mockApi(fullPayload(), 0.6667, {})

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-section="pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)
    // The hero still names the desk: the empty body is a permission boundary,
    // not an error.
    expect(wrapper.find('[data-page-header-title]').text()).toBe('Pendientes')
    expect(wrapper.find('[data-continuity-pending-error]').exists()).toBe(false)

    wrapper.unmount()
  })
})
