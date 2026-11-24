import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// «Unificar Pendientes por paciente» — the desk publishes ONE row per patient.
// The four sources (overdue controls, continuity requests, quotations and
// treatment plans) merge by patient, and every pending keeps its own action.
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

// S9 — the write actions are gated on `manage.continuity`. A mutable handle
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

// ------------------------------------------------ fixtures

// The queue publishes the patient both as `patient_id` and as the embedded
// patient; the desk groups by the first and falls back to the second.
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

// Luis holds two sources (a request and a plan), Ana holds a control and a
// quotation: both must collapse into a single row.
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
  patientId,
  patientName,
  status = 'sent',
  total = 149.9,
  createdAt = localIso(2026, 9, 3)
) => ({
  id,
  patient_id: patientId,
  patient_name: patientName,
  total_amount: total,
  status,
  created_at: createdAt
})

const planItem = (
  id,
  patientId,
  patientName,
  status = 'proposed',
  cost = 249.5,
  createdAt = localIso(2026, 9, 2)
) => ({
  id,
  patient_id: patientId,
  patient_name: patientName,
  title: 'Plan de tratamiento',
  final_cost: cost,
  status,
  created_at: createdAt
})

// Ana (patient 131) owns both the overdue control and a quotation; Luis
// (patient 241) owns the request and a plan; Marta (261) only a quotation.
const mergedPending = () => ({
  quotations: {
    count: 2,
    items: [
      quotationItem(11, 131, 'Ana Torres', 'sent', 149.9, localIso(2026, 9, 3)),
      quotationItem(12, 261, 'Marta Díaz', 'viewed', 89, localIso(2026, 9, 1))
    ]
  },
  treatment_plans: {
    count: 1,
    items: [planItem(21, 241, 'Luis Rojas', 'proposed', 249.5, localIso(2026, 9, 2))]
  }
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

const deskRows = wrapper => wrapper.findAll('[data-continuity-table="pending"] tbody tr')

const desk = wrapper => wrapper.find('[data-continuity-section="pending"]')

const rowFor = (wrapper, patientName) => {
  const row = deskRows(wrapper).find(
    candidate => candidate.find('[data-continuity-patient]').text() === patientName
  )
  expect(row, `row for ${patientName} must exist`).toBeTruthy()
  return row
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

    const hero = wrapper.find('[data-page-header]').text()
    expect(hero).toContain('Pendientes')
    expect(hero).toContain('Recalls vencidos')
    expect(hero).toContain('solicitudes de continuidad')
    expect(hero).toContain('esperando')

    expect(wrapper.find('[data-kpi-card="recalls"] [data-kpi-value]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-card="requests"] [data-kpi-value]').text()).toBe('1')
    // Three patients: Ana, María and Luis. «Total en cola» counted sources.
    expect(wrapper.find('[data-kpi-card="patients"] [data-kpi-value]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-card="patients"]').text()).toContain('Pacientes con pendientes')
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

  it('paints one row per patient, never one row per source', async () => {
    mockApi(fullPayload(), 0.6667, mergedPending())

    const wrapper = await mountPage()

    const rows = deskRows(wrapper)
    // Ana (control + quotation), María (control), Luis (request + plan) and
    // Marta (quotation) are four patients, not six sources.
    expect(rows).toHaveLength(4)

    const ana = rowFor(wrapper, 'Ana Torres')
    expect(ana.findAll('[data-continuity-label]')).toHaveLength(2)
    expect(ana.find('[data-continuity-label="recall"]').text()).toBe('Control')
    expect(ana.find('[data-continuity-label="quotation"]').text()).toBe('Presupuesto')
    // No action is lost when a patient holds several pendings.
    expect(ana.text()).toContain('Agendar control')
    expect(ana.text()).toContain('Ver presupuesto')

    const luis = rowFor(wrapper, 'Luis Rojas')
    expect(luis.find('[data-continuity-label="request"]').text()).toBe('Solicitud')
    expect(luis.find('[data-continuity-label="plan"]').text()).toBe('Plan')
    expect(luis.text()).toContain('Agendar')
    expect(luis.text()).toContain('Descartar')
    expect(luis.text()).toContain('Ver plan')

    // The patient name is painted once per patient, not once per pending.
    expect(wrapper.findAll('[data-continuity-patient]')).toHaveLength(4)
    expect(
      wrapper.findAll('[data-continuity-patient]').filter(node => node.text() === 'Ana Torres')
    ).toHaveLength(1)

    wrapper.unmount()
  })

  it('names the detail of the most urgent pending with its date, reason and amount', async () => {
    mockApi(fullPayload(), 0.6667, mergedPending())

    const wrapper = await mountPage()

    const ana = rowFor(wrapper, 'Ana Torres')
    // The overdue control wins the detail over the quotation.
    expect(ana.text()).toContain('Control semestral')
    expect(ana.text()).toContain('01 oct. 2026')
    expect(ana.text()).toContain('6 meses')
    expect(ana.text()).toContain('+1 más')

    const luis = rowFor(wrapper, 'Luis Rojas')
    // A request beats the plan: its preferred window is the clinical date.
    expect(luis.text()).toContain('Próxima sesión')
    expect(luis.text()).toContain('02 nov. 2026, 09:00 – 09:30')
    expect(luis.text()).toContain('+1 más')

    const marta = rowFor(wrapper, 'Marta Díaz')
    expect(marta.text()).toContain('Presupuesto')
    expect(marta.text()).toContain('01 oct. 2026')
    expect(marta.text()).toContain('Visto')
    expect(normalize(marta.text())).toContain('S/ 89.00')

    wrapper.unmount()
  })

  it('groups by the embedded patient and by the name when the id is absent', async () => {
    const legacyRecall = recall(31, ['Ana', 'Torres'], 'Control semestral', '2026-10-01', 6)
    delete legacyRecall.patient_id
    legacyRecall.patient.id = 131

    const firstCollected = quotationItem(11, undefined, 'María Quispe')
    delete firstCollected.patient_id
    const secondCollected = quotationItem(12, undefined, 'María Quispe', 'viewed')
    delete secondCollected.patient_id

    mockApi(queuePayload([legacyRecall], []), 0.6667, {
      quotations: { count: 2, items: [firstCollected, secondCollected] }
    })

    const wrapper = await mountPage()

    expect(deskRows(wrapper)).toHaveLength(2)

    const ana = rowFor(wrapper, 'Ana Torres')
    expect(ana.find('[data-continuity-label="recall"]').exists()).toBe(true)
    expect(ana.text()).toContain('Agendar control')

    // Two quotations of the same patient (no id, same name) are one row with
    // both actions, not one row each.
    const maria = rowFor(wrapper, 'María Quispe')
    expect(maria.findAll('[data-continuity-pending-view="quotations"]')).toHaveLength(2)

    wrapper.unmount()
  })

  it('renders a single empty state when every source comes back empty', async () => {
    mockApi(emptyPayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-table="pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-section="pending"]').text()).toContain(
      'Sin pendientes por paciente'
    )

    wrapper.unmount()
  })

  it('reports a failed load and refetches the queue from the retry button', async () => {
    // Module state is a singleton: start from a known empty queue so the retry
    // panel is the only desk surface left after the failure.
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
    expect(wrapper.find('[data-continuity-table="pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)

    const before = queueCalls()
    mockApi(fullPayload())
    await wrapper.find('[data-continuity-retry]').trigger('click')
    await flushPromises()

    expect(queueCalls()).toBe(before + 1)
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="pending"]').exists()).toBe(true)

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

  it('schedules an overdue control through its own door', async () => {
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
    expect(bodyField('[data-schedule-patient]').textContent).toContain('Ana Torres')
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

  it('maps a slot conflict onto the chair field and keeps the row in the desk', async () => {
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
    expect(rowFor(wrapper, 'Luis Rojas').text()).toContain('Solicitud')
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

// The commercial answers share the row with the patient's control or request.
// Their payload key is the visibility contract: an absent subset means the role
// cannot read that module, so no row is invented for it.
describe('continuity pending answers', () => {
  it('opens each answer in its own module list', async () => {
    mockApi(fullPayload(), 0.6667, mergedPending())

    const wrapper = await mountPage()

    await wrapper.find('[data-continuity-pending-view="quotations"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.$router.currentRoute.value.path).toBe('/quotations')

    await wrapper.vm.$router.push('/continuity')
    await flushPromises()

    await wrapper.find('[data-continuity-pending-view="treatment-plans"]').trigger('click')
    await flushPromises()
    expect(wrapper.vm.$router.currentRoute.value.path).toBe('/treatment-plans')

    expect(queueCalls()).toBe(1)
    expect(pendingCalls()).toBe(1)

    wrapper.unmount()
  })

  it('renders only the readable subset and invents no zero for the absent one', async () => {
    mockApi(fullPayload(), 0.6667, {
      treatment_plans: { count: 1, items: [planItem(21, 241, 'Luis Rojas')] }
    })

    const wrapper = await mountPage()

    expect(desk(wrapper).exists()).toBe(true)
    expect(wrapper.findAll('[data-continuity-label="plan"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-continuity-label="quotation"]')).toHaveLength(0)
    expect(wrapper.find('[data-continuity-pending-view="quotations"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-pending-view="treatment-plans"]').exists()).toBe(true)

    // No half is announced as empty: the missing key is invisible.
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the rows mounted when the answers fail and retries only that resource', async () => {
    let pendingAttempt = 0
    harness.get.mockImplementation(async url => {
      if (url === '/api/patient-recalls/metrics') return metricsPayload(0.6667)
      if (url === '/api/dashboard/pending') {
        pendingAttempt += 1
        if (pendingAttempt === 1) {
          throw { response: { data: { message: 'No se pudo conectar con el servidor.' } } }
        }
        return { data: { quotations: { count: 1, items: [quotationItem(11, 131, 'Ana Torres')] } } }
      }
      return fullPayload()
    })

    const wrapper = await mountPage()

    const error = wrapper.find('[data-continuity-pending-error]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar las respuestas pendientes')
    expect(error.text()).toContain('No se pudo conectar con el servidor.')

    // The queue answered 200: its rows stay on screen and its own error is
    // absent, so a commercial failure never blanks the desk.
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(deskRows(wrapper)).toHaveLength(3)
    expect(queueCalls()).toBe(1)

    await wrapper.find('[data-continuity-pending-retry]').trigger('click')
    await flushPromises()

    expect(pendingCalls()).toBe(2)
    expect(queueCalls()).toBe(1)
    expect(wrapper.find('[data-continuity-pending-error]').exists()).toBe(false)
    // Ana keeps her row and gains the quotation action on it.
    expect(rowFor(wrapper, 'Ana Torres').text()).toContain('Ver presupuesto')

    wrapper.unmount()
  })

  it('keeps the queue retry scoped when the two loads fail together', async () => {
    harness.get.mockRejectedValue({ response: { data: { message: 'Sin conexión.' } } })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-error]').exists()).toBe(true)

    const before = pendingCalls()
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 131, 'Ana Torres')] }
    })
    await wrapper.find('[data-continuity-retry]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(deskRows(wrapper)).toHaveLength(3)
    // The queue retry never re-issues the pending resource.
    expect(pendingCalls()).toBe(before)

    wrapper.unmount()
  })
})

// S15 — finance reads quotations but holds no `view.continuity`. The same desk
// must serve it without a queue (and without a 403), showing only the
// commercial rows; the reverse (queue, no answers) is the continuity desks'
// case already covered above.
describe('continuity desk for finance', () => {
  beforeEach(() => {
    harness.canViewContinuity = false
  })

  it('does not call the queue and renders no queue KPI or error', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 131, 'Ana Torres Quispe')] }
    })

    const wrapper = await mountPage()

    const requested = harness.get.mock.calls.map(([url]) => url)
    expect(requested).not.toContain('/api/continuity/queue')
    expect(requested).not.toContain('/api/patient-recalls/metrics')
    expect(requested).toContain('/api/dashboard/pending')

    expect(wrapper.find('[data-continuity-kpis]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-loading]').exists()).toBe(false)

    // No invented zero: the page body never advertises the queue it cannot read.
    const body = wrapper.find('[data-continuity]')
    expect(body.text()).not.toContain('Recalls vencidos')
    expect(body.text()).not.toContain('Sin pendientes por paciente')
    expect(body.text()).toContain('Presupuestos y planes de tratamiento esperando respuesta.')

    // The commercial rows are the whole desk for this session.
    expect(desk(wrapper).exists()).toBe(true)
    expect(deskRows(wrapper)).toHaveLength(1)
    expect(rowFor(wrapper, 'Ana Torres Quispe').text()).toContain('Ver presupuesto')
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('refreshes only the commercial answers from the hero control', async () => {
    mockApi(fullPayload(), 0.6667, {
      quotations: { count: 1, items: [quotationItem(11, 131, 'Ana Torres Quispe')] }
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

    expect(desk(wrapper).exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="pending"]').exists()).toBe(false)
    // The hero still names the desk: the empty body is a permission boundary,
    // not an error.
    expect(wrapper.find('[data-page-header-title]').text()).toBe('Pendientes')
    expect(wrapper.find('[data-continuity-pending-error]').exists()).toBe(false)

    wrapper.unmount()
  })
})
