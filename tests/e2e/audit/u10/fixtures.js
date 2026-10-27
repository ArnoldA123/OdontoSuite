// Plan #13 (issue #373, U10) — the money, continuity and laboratory rows the
// three desks of the session measure.
//
// Everything is created through the product's own doors and named with the
// `AUDIT-U10-` prefix. Three groups:
//
//   money      one appointment plus two electronic payments (card and transfer)
//              and one partial refund, so the revenue report has money to show.
//              The 372 seeded transactions are legacy `income`/`expense` rows,
//              which `MoneyVocabulary` excludes from every money total: without
//              this group revenue is empty for every period, which is exactly
//              what the surface would measure as "Sin datos en el período".
//   continuity one appointment checked in, started and closed with the NTS-139
//              payload, so the queue publishes one pending request and one
//              overdue recall — the two populations of the desk.
//   lab        four work orders: on time, overdue, assigned to the other
//              technician and one walked to `placed`, so the detail can show the
//              closed machine.
//
// A payment and its refund always need an open drawer (`MoneyVocabulary` moves
// cash for both, and `StoreTransactionRequest`/`TransactionService` both enforce
// it), even when the method is a card: the group opens a cash-register session of
// its own and closes it at teardown. It never touches the session another user
// left open.
//
// A refused door returns `{ ok: false, reason }` instead of throwing: a skipped
// measurement with its reason is evidence, an exception is not. Residue policy:
// lab cases, transactions, the patient charge and the closed encounter have no
// delete door (or must not be deleted), so they stay and are declared; the
// teardown dismisses the request, cancels the recalls, deletes the two
// appointments when the API allows it and closes the drawer it opened.

import { existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { expect, test } from 'playwright/test'

import { PASSWORD } from '../harness.js'

import { EVIDENCE, ROLES, api, audit, hasEvidence, login, readEvidence, stamp } from './helpers.js'

export const MARKER = 'AUDIT-U10'

// Every row the session creates carries this prefix so a residue is searchable.
const NAME_PREFIX = 'AUDIT-U10-'

const RUN_TOKEN = String(Date.now()).slice(-6)

const DIAGNOSIS_REASON = `${MARKER} control ${RUN_TOKEN}`

const pad2 = value => String(value).padStart(2, '0')

const localDay = (date = new Date()) =>
  `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`

const localDateTime = date =>
  `${localDay(date)} ${pad2(date.getHours())}:${pad2(date.getMinutes())}:00`

const atTime = (date, hour, minute = 0) =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate(), hour, minute, 0, 0)

const addDays = (date, days) => {
  const next = new Date(date.getTime())
  next.setDate(next.getDate() + days)
  return next
}

// The agenda rejects a slot less than a minute ahead, so the fallbacks start an
// hour and a half out and land on a half-hour boundary; a late run rolls to the
// next morning instead of inventing a 22:30 appointment.

/** `api()` returns `{ status, body }`; success is the 2xx range. */
const isSuccess = response =>
  response.status !== null && response.status >= 200 && response.status < 300

/** Rows of a payload whose list may be `data` or `data.data`. */
const rowsOf = body => {
  const payload = body?.data

  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data

  return []
}

const sessionSummary = session => ({
  id: session.user.id,
  name: session.user.name,
  username: session.user.username,
  role: session.user.role,
  branchId: session.user.branch_id ?? null
})

const attempt = (label, response) => ({
  label,
  status: response.status,
  message: response.body?.message ?? null,
  errors: response.body?.errors ? Object.keys(response.body.errors) : null
})

const buildFixtures = async () => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day: localDay(),
    ok: false,
    reason: 'not built',
    sessions: {},
    permissions: {},
    actors: { admin: null, professional: null, technicians: [] },
    lookups: {
      branch: null,
      chair: null,
      appointmentType: null,
      patients: { money: null, continuity: null },
      paymentMethods: { card: null, transfer: null },
      dentalPiece: null,
      diagnosis: null
    },
    money: {
      session: { id: null, owned: false, status: null, message: null },
      appointment: null,
      slotAttempts: [],
      payments: [],
      refund: null,
      revenueBefore: null,
      revenueAfter: null
    },
    continuity: {
      appointment: null,
      slotAttempts: [],
      checkIn: null,
      startAttention: null,
      closure: { status: null, message: null, errors: null, encounterId: null },
      request: null,
      recall: null,
      queue: null,
      metrics: null
    },
    lab: { cases: {}, transitions: [], index: null },
    conflict: null,
    matrix: { recalls: {} },
    baseline: {},
    reach: {},
    declarations: {},
    teardown: [],
    leftovers: [],
    residues: []
  }

  // --- sessions and the permission payload of every role -------------------
  const sessions = await roleSessionsForEvidence()

  for (const [role, session] of Object.entries(sessions)) {
    built.sessions[role] = sessionSummary(session)
    built.permissions[role] = [...(session.user.permissions ?? [])]
  }

  const admin = sessions.administrador
  const professional = sessions.odontologo

  // --- the seeded actors the fixtures need ---------------------------------
  const usersList = await api(admin, 'GET', '/api/users?per_page=100')
  const users = rowsOf(usersList.body)
  const byUsername = username => users.find(row => row.username === username) ?? null
  const professionalRow = byUsername('ever_odontologo')
  const technicianRows = users.filter(row => row.role === 'tecnico_dental')

  built.actors.admin = {
    id: admin.user.id,
    username: admin.user.username,
    role: admin.user.role
  }
  built.actors.professional = professionalRow
    ? { id: professionalRow.id, username: professionalRow.username, role: professionalRow.role }
    : {
        id: professional.user.id,
        username: professional.user.username,
        role: professional.user.role
      }
  built.actors.technicians = technicianRows.map(row => ({
    id: row.id,
    username: row.username,
    role: row.role
  }))
  built.actors.usersStatus = usersList.status

  if (!professionalRow) {
    built.reason = `the professional ever_odontologo was not found: HTTP ${usersList.status}`
    return built
  }

  if (technicianRows.length === 0) {
    built.reason = 'no dental technician is seeded; the laboratory cases would have no assignee'
    return built
  }

  const primaryTechnician =
    technicianRows.find(row => row.username === 'tecnico_test') ?? technicianRows[0]
  const secondaryTechnician =
    technicianRows.find(row => row.id !== primaryTechnician.id) ?? technicianRows[0]

  built.actors.technicians = [
    {
      id: primaryTechnician.id,
      username: primaryTechnician.username,
      role: primaryTechnician.role
    },
    {
      id: secondaryTechnician.id,
      username: secondaryTechnician.username,
      role: secondaryTechnician.role
    }
  ]

  // --- lookups the three pages read ---------------------------------------
  const branches = await api(admin, 'GET', '/api/branches/active')
  const chairs = await api(admin, 'GET', '/api/dental-chairs/active')
  const types = await api(admin, 'GET', '/api/appointment-types?is_active=1')
  const patients = await api(admin, 'GET', '/api/patients?per_page=5')
  const methods = await api(admin, 'GET', '/api/payment-methods')
  const pieces = await api(admin, 'GET', '/api/dental-pieces?per_page=5')
  const diagnoses = await api(admin, 'GET', '/api/diagnoses?per_page=5')

  const branchRows = rowsOf(branches.body)
  const chairRows = rowsOf(chairs.body)
  const typeRows = rowsOf(types.body)
  const patientRows = rowsOf(patients.body)
  const methodRows = rowsOf(methods.body)
  const pieceRows = rowsOf(pieces.body)
  const diagnosisRows = rowsOf(diagnoses.body)

  built.lookups.branch = branchRows[0] ?? null
  built.lookups.chair = chairRows.find(row => row.id === 1) ?? chairRows[0] ?? null
  built.lookups.appointmentType = typeRows.find(row => row.id === 1) ?? typeRows[0] ?? null
  built.lookups.patients.money = patientRows[0] ?? null
  built.lookups.patients.continuity = patientRows[1] ?? patientRows[0] ?? null
  built.lookups.paymentMethods.card = methodRows.find(row => row.type === 'card') ?? null
  built.lookups.paymentMethods.transfer = methodRows.find(row => row.type === 'transfer') ?? null
  built.lookups.dentalPiece = pieceRows[0] ?? null
  built.lookups.diagnosis = diagnosisRows[0] ?? null
  built.lookups.statuses = {
    branches: branches.status,
    chairs: chairs.status,
    appointmentTypes: types.status,
    patients: patients.status,
    paymentMethods: methods.status,
    dentalPieces: pieces.status,
    diagnoses: diagnoses.status
  }

  const missingLookup = Object.entries({
    branch: built.lookups.branch,
    chair: built.lookups.chair,
    appointmentType: built.lookups.appointmentType,
    patientMoney: built.lookups.patients.money,
    patientContinuity: built.lookups.patients.continuity,
    card: built.lookups.paymentMethods.card,
    transfer: built.lookups.paymentMethods.transfer,
    dentalPiece: built.lookups.dentalPiece,
    diagnosis: built.lookups.diagnosis
  }).find(([, row]) => !row)

  if (missingLookup) {
    built.reason = `the lookup "${missingLookup[0]}" is not available (HTTP ${JSON.stringify(built.lookups.statuses)})`
    return built
  }

  // --- the baseline the run compares its own writes against ---------------
  const queueBefore = await api(admin, 'GET', '/api/continuity/queue')
  const recallsBefore = await api(admin, 'GET', '/api/patient-recalls/metrics')
  const labBefore = await api(admin, 'GET', '/api/lab-cases')
  const revenueBefore = await api(
    admin,
    'GET',
    `/api/reports/revenue?start_date=${built.day}&end_date=${built.day}`
  )

  built.baseline = {
    queueCounts: queueBefore.body?.data?.counts ?? null,
    recallMetrics: recallsBefore.body?.data ?? null,
    labTotal: labBefore.body?.meta?.total ?? null,
    revenue: revenueBefore.body?.meta?.summary ?? null,
    statuses: {
      queue: queueBefore.status,
      recalls: recallsBefore.status,
      lab: labBefore.status,
      revenue: revenueBefore.status
    }
  }

  built.money.revenueBefore = built.baseline.revenue

  // --- money: the drawer the electronic payments still demand -------------
  const openSession = await api(admin, 'POST', '/api/cash-register/open', {
    branch_id: built.lookups.branch.id,
    opening_amount: 100,
    opening_notes: `${NAME_PREFIX}${RUN_TOKEN} apertura de la sesión de auditoría`
  })

  if (openSession.status === 201) {
    built.money.session = {
      id: openSession.body?.data?.id ?? null,
      owned: true,
      status: openSession.status,
      openingAmount: 100,
      message: openSession.body?.meta?.message ?? null
    }
  } else {
    // The door refuses a second session for the same user and branch; a previous
    // run may have left one open. Reuse it and never close a drawer this session
    // did not open.
    const mine = await api(
      admin,
      'GET',
      `/api/cash-register-sessions?user_id=${admin.user.id}&status=open&per_page=100`
    )
    const existing = rowsOf(mine.body)[0] ?? null

    built.money.session = {
      id: existing?.id ?? null,
      owned: false,
      status: openSession.status,
      message: openSession.body?.message ?? null,
      errors: openSession.body?.errors ?? null,
      reused: Boolean(existing),
      lookupStatus: mine.status,
      lookupCount: rowsOf(mine.body).length
    }
  }

  // --- money: the appointment the payments are attributed to --------------
  const now = new Date()
  const tokenMinute = 1 + (Number(String(built.token).slice(-4)) % 58)
  const moneyCandidates = []
  const addCandidate = (chair, at) => {
    const key = `${chair.id}-${localDateTime(at)}`
    if (moneyCandidates.some(candidate => candidate.key === key)) return
    moneyCandidates.push({ key, chair, at })
  }

  // `unique_user_time_slot` makes a slot unique per professional and window, not
  // per chair, so a slot an earlier run burned stays burned. The run token moves
  // the minute of every candidate and the days move with it, which keeps a
  // repeated run on the same database asking for slots nobody used while the
  // agenda door still decides the outcome.
  for (const offset of [1, 2, 3, 4, 5, 6]) {
    for (const hour of [10, 11, 12, 13, 14, 15, 16, 17, 18]) {
      addCandidate(built.lookups.chair, atTime(addDays(now, offset), hour, tokenMinute))
    }
  }

  for (const candidate of moneyCandidates) {
    const response = await api(admin, 'POST', '/api/appointments', {
      patient_id: built.lookups.patients.money.id,
      user_id: professionalRow.id,
      dental_chair_id: candidate.chair.id,
      appointment_type_id: built.lookups.appointmentType.id,
      scheduled_at: localDateTime(candidate.at),
      duration_minutes: 30,
      notes: `${NAME_PREFIX}${RUN_TOKEN} cita de ingresos de la sesión`
    })

    built.money.slotAttempts.push({
      ...attempt(localDateTime(candidate.at), response),
      slot: localDateTime(candidate.at),
      chairId: candidate.chair.id
    })

    if (response.status === 201) {
      built.money.appointment = {
        id: response.body?.data?.id ?? null,
        scheduledAt: localDateTime(candidate.at),
        patientId: built.lookups.patients.money.id,
        userId: professionalRow.id,
        chairId: candidate.chair.id,
        typeId: built.lookups.appointmentType.id,
        status: response.status
      }
      break
    }
  }

  if (!built.money.appointment?.id) {
    built.reason = `the money appointment was refused: ${JSON.stringify(built.money.slotAttempts)}`
    return built
  }

  // --- money: two electronic payments and one partial refund --------------
  const paymentSeeds = [
    { method: built.lookups.paymentMethods.card, amount: 120, reference: `U10-${RUN_TOKEN}-C` },
    { method: built.lookups.paymentMethods.transfer, amount: 80, reference: `U10-${RUN_TOKEN}-T` }
  ]

  for (const seed of paymentSeeds) {
    const response = await api(admin, 'POST', '/api/transactions', {
      patient_id: built.lookups.patients.money.id,
      appointment_id: built.money.appointment.id,
      payment_method_id: seed.method.id,
      type: 'payment',
      amount: seed.amount,
      description: `${NAME_PREFIX}${RUN_TOKEN} cobro ${seed.method.name}`,
      reference_number: seed.reference
    })

    built.money.payments.push({
      id: response.body?.data?.id ?? null,
      methodId: seed.method.id,
      methodName: seed.method.name,
      methodType: seed.method.type,
      amount: seed.amount,
      reference: seed.reference,
      status: response.status,
      message: response.body?.message ?? null,
      errors: response.body?.errors ?? null
    })
  }

  const firstPayment = built.money.payments.find(payment => payment.id) ?? null
  const refundMethod = built.lookups.paymentMethods.card

  if (firstPayment) {
    const response = await api(admin, 'POST', '/api/transactions', {
      patient_id: built.lookups.patients.money.id,
      appointment_id: built.money.appointment.id,
      payment_method_id: refundMethod.id,
      type: 'refund',
      amount: 30,
      description: `${NAME_PREFIX}${RUN_TOKEN} devolución parcial`,
      reference_number: `U10-${RUN_TOKEN}-R`,
      parent_transaction_id: firstPayment.id,
      authorized_by: admin.user.id,
      authorization_reason: `${NAME_PREFIX}${RUN_TOKEN} devolución parcial autorizada por administración`
    })

    built.money.refund = {
      id: response.body?.data?.id ?? null,
      parentTransactionId: firstPayment.id,
      amount: 30,
      authorizedBy: admin.user.id,
      status: response.status,
      message: response.body?.message ?? null,
      errors: response.body?.errors ?? null
    }
  } else {
    built.money.refund = {
      id: null,
      status: null,
      message: 'no payment was created, so no refund could be measured'
    }
  }

  const revenueAfter = await api(
    admin,
    'GET',
    `/api/reports/revenue?start_date=${built.day}&end_date=${built.day}`
  )

  built.money.revenueAfter = {
    status: revenueAfter.status,
    summary: revenueAfter.body?.meta?.summary ?? null,
    types: [...new Set((revenueAfter.body?.data ?? []).map(row => row.type))],
    rows: (revenueAfter.body?.data ?? []).length
  }

  // --- continuity: an appointment the professional opens and closes -------
  const continuityCandidates = []
  const addContinuityCandidate = (chair, at) => {
    const key = `${chair.id}-${localDateTime(at)}`
    if (continuityCandidates.some(candidate => candidate.key === key)) return
    continuityCandidates.push({ key, chair, at })
  }

  // Same rule as the money slot: the window is unique per professional, so the
  // token minute and a window further out keep runs from burning each other.
  for (let offset = 20; offset <= 29; offset += 1) {
    for (const hour of [8, 9, 10, 11, 12, 14, 15, 16, 17]) {
      addContinuityCandidate(built.lookups.chair, atTime(addDays(now, offset), hour, tokenMinute))
    }
  }

  let continuityCandidate = null

  for (const candidate of continuityCandidates.slice(0, 24)) {
    const response = await api(admin, 'POST', '/api/appointments', {
      patient_id: built.lookups.patients.continuity.id,
      user_id: professionalRow.id,
      dental_chair_id: candidate.chair.id,
      appointment_type_id: built.lookups.appointmentType.id,
      scheduled_at: localDateTime(candidate.at),
      duration_minutes: 30,
      notes: `${NAME_PREFIX}${RUN_TOKEN} cita de continuidad de la sesión`
    })

    built.continuity.slotAttempts.push({
      ...attempt(localDateTime(candidate.at), response),
      slot: localDateTime(candidate.at),
      chairId: candidate.chair.id
    })

    if (response.status === 201) {
      continuityCandidate = candidate
      built.continuity.appointment = {
        id: response.body?.data?.id ?? null,
        scheduledAt: localDateTime(candidate.at),
        patientId: built.lookups.patients.continuity.id,
        userId: professionalRow.id,
        chairId: candidate.chair.id,
        typeId: built.lookups.appointmentType.id,
        status: response.status
      }
      break
    }
  }

  if (!built.continuity.appointment?.id) {
    built.reason = `the continuity appointment was refused: ${JSON.stringify(built.continuity.slotAttempts)}`
    return built
  }

  const appointmentId = built.continuity.appointment.id

  const checkIn = await api(admin, 'POST', `/api/appointments/${appointmentId}/check-in`)
  built.continuity.checkIn = {
    status: checkIn.status,
    appointmentStatus: checkIn.body?.data?.status ?? null,
    message: checkIn.body?.meta?.message ?? null
  }

  const startAttention = await api(
    professional,
    'POST',
    `/api/appointments/${appointmentId}/start-attention`
  )
  built.continuity.startAttention = {
    status: startAttention.status,
    appointmentStatus: startAttention.body?.data?.status ?? null,
    message: startAttention.body?.meta?.message ?? null
  }

  // The next session the professional leaves at the close, and the recall. Both
  // are what the continuity desk publishes; the recall is due today so it enters
  // the overdue queue the moment it exists.
  const nextAt = addDays(continuityCandidate.at, 14)

  const closure = await api(professional, 'POST', `/api/appointments/${appointmentId}/complete`, {
    mode: 'consultation',
    skip_materials: true,
    reason_for_consultation: `${MARKER} control de continuidad de la sesión`,
    diagnoses: [{ diagnosis_catalog_id: built.lookups.diagnosis.id, type: 'definitive' }],
    signature: { password: PASSWORD },
    evolution: {
      subjective: `${MARKER} paciente sin sintomatología`,
      objective: `${MARKER} tejidos sanos, sin caries activa`,
      assessment: `${MARKER} control periodontal estable`,
      plan: `${MARKER} control en seis meses`
    },
    next_appointment: {
      scheduled_at: localDateTime(nextAt),
      duration_minutes: 30,
      notes: `${NAME_PREFIX}${RUN_TOKEN} próxima sesión de la sesión de auditoría`
    },
    recall: {
      interval_months: 6,
      reason: DIAGNOSIS_REASON,
      due_date: built.day
    }
  })

  built.continuity.closure = {
    status: closure.status,
    message: closure.body?.message ?? null,
    errors: closure.body?.errors ? Object.keys(closure.body.errors) : null,
    error: closure.body?.error ?? null,
    encounterId: closure.body?.meta?.encounter_id ?? null,
    finalAmount: closure.body?.meta?.final_amount ?? null,
    appointmentStatus: closure.body?.data?.status ?? null
  }

  if (closure.status !== 200) {
    built.reason = `the consultation close was refused: HTTP ${closure.status} ${JSON.stringify(
      closure.body?.errors ?? closure.body?.error ?? closure.body?.message ?? null
    )}`
    return built
  }

  // --- continuity: the two queue populations the desk reads --------------
  const queue = await api(admin, 'GET', '/api/continuity/queue')
  const queueRows = queue.body?.data?.requests ?? []
  const ourRequest =
    queueRows.find(row => String(row.notes ?? '').includes(`${NAME_PREFIX}${RUN_TOKEN}`)) ??
    queueRows.find(row => row.clinical_encounter_id === built.continuity.closure.encounterId) ??
    null

  const recallList = await api(
    admin,
    'GET',
    `/api/patient-recalls?patient_id=${built.lookups.patients.continuity.id}&per_page=100`
  )
  const ourRecall =
    rowsOf(recallList.body).find(recall => recall.reason === DIAGNOSIS_REASON) ?? null

  built.continuity.request = ourRequest
    ? {
        id: ourRequest.id,
        status: ourRequest.status,
        kind: ourRequest.kind ?? null,
        preferredFrom: ourRequest.preferred_from ?? null,
        durationMinutes: ourRequest.duration_minutes ?? null,
        patientId: ourRequest.patient_id ?? null,
        encounterId: ourRequest.clinical_encounter_id ?? null,
        notes: ourRequest.notes ?? null
      }
    : null

  built.continuity.recall = ourRecall
    ? {
        id: ourRecall.id,
        status: ourRecall.status,
        dueDate: ourRecall.due_date ?? null,
        intervalMonths: ourRecall.interval_months ?? null,
        patientId: ourRecall.patient_id ?? null,
        reason: ourRecall.reason ?? null
      }
    : null

  built.continuity.queue = {
    status: queue.status,
    counts: queue.body?.data?.counts ?? null,
    requestIds: queueRows.map(row => row.id),
    recallIds: (queue.body?.data?.recalls ?? []).map(row => row.id)
  }

  const metrics = await api(admin, 'GET', '/api/patient-recalls/metrics')
  built.continuity.metrics = { status: metrics.status, data: metrics.body?.data ?? null }

  // --- laboratory: four work orders, two of them advanced ------------------
  const labSeeds = [
    {
      key: 'A',
      work: `${NAME_PREFIX}${RUN_TOKEN} corona de zirconio`,
      material: 'Zirconio',
      sentOffset: -3,
      promisedOffset: 10,
      technicianId: primaryTechnician.id,
      phases: []
    },
    {
      key: 'B',
      work: `${NAME_PREFIX}${RUN_TOKEN} puente metal-cerámica vencido`,
      material: 'Metal-cerámica',
      sentOffset: -20,
      promisedOffset: -10,
      technicianId: primaryTechnician.id,
      phases: ['in_laboratory']
    },
    {
      key: 'C',
      work: `${NAME_PREFIX}${RUN_TOKEN} prótesis parcial acrílica`,
      material: 'Acrílico',
      sentOffset: -5,
      promisedOffset: 15,
      technicianId: secondaryTechnician.id,
      phases: []
    },
    {
      key: 'D',
      work: `${NAME_PREFIX}${RUN_TOKEN} incrustación de disilicato colocada`,
      material: 'Disilicato de litio',
      sentOffset: -12,
      promisedOffset: 3,
      technicianId: primaryTechnician.id,
      phases: ['in_laboratory', 'try_in', 'delivered', 'placed']
    }
  ]

  for (const seed of labSeeds) {
    const response = await api(professional, 'POST', '/api/lab-cases', {
      patient_id: built.lookups.patients.continuity.id,
      dental_piece_id: built.lookups.dentalPiece.id,
      technician_id: seed.technicianId,
      work: seed.work,
      material: seed.material,
      sent_at: localDay(addDays(now, seed.sentOffset)),
      promised_at: localDay(addDays(now, seed.promisedOffset)),
      notes: `${NAME_PREFIX}${RUN_TOKEN} caso de laboratorio de la sesión de auditoría`
    })

    const entry = {
      id: response.body?.data?.id ?? null,
      work: seed.work,
      material: seed.material,
      technicianId: seed.technicianId,
      sentAt: localDay(addDays(now, seed.sentOffset)),
      promisedAt: localDay(addDays(now, seed.promisedOffset)),
      technician:
        seed.technicianId === primaryTechnician.id
          ? primaryTechnician.username
          : secondaryTechnician.username,
      status: response.status,
      phase: response.body?.data?.status ?? null,
      message: response.body?.message ?? null,
      errors: response.body?.errors ?? null,
      phases: []
    }

    if (entry.id) {
      for (const phase of seed.phases) {
        const transition = await api(professional, 'POST', `/api/lab-cases/${entry.id}/phase`, {
          phase
        })

        entry.phases.push({
          phase,
          status: transition.status,
          current: transition.body?.data?.status ?? null,
          message: transition.body?.meta?.message ?? transition.body?.message ?? null,
          errors: transition.body?.errors ?? null
        })

        if (transition.status === 200) entry.phase = transition.body?.data?.status ?? phase

        built.lab.transitions.push({ key: seed.key, phase, status: transition.status })
      }
    }

    built.lab.cases[seed.key] = entry
  }

  const labIndex = await api(admin, 'GET', '/api/lab-cases?per_page=100')
  built.lab.index = {
    status: labIndex.status,
    total: labIndex.body?.meta?.total ?? null,
    ids: rowsOf(labIndex.body).map(row => row.id)
  }

  // --- the two matrix recalls the cancel probe consumes -------------------
  for (const [key, role] of [
    ['administrador', 'administrador'],
    ['recepcionista', 'recepcionista']
  ]) {
    const response = await api(admin, 'POST', '/api/patient-recalls', {
      patient_id: built.lookups.patients.money.id,
      interval_months: 3,
      reason: `${NAME_PREFIX}${RUN_TOKEN} recall de la matriz (${role})`,
      due_date: localDay(addDays(now, 30))
    })

    built.matrix.recalls[key] = {
      id: response.body?.data?.id ?? null,
      status: response.status,
      phase: response.body?.data?.status ?? null,
      message: response.body?.message ?? null
    }
  }

  // --- the agenda conflict the walks need --------------------------------
  const upcoming = await api(
    admin,
    'GET',
    `/api/appointments?per_page=100&start_date=${built.day}&end_date=${localDay(addDays(now, 7))}`
  )
  const horizon = new Date(now.getTime() + 5 * 60000)
  const futureAppointment =
    rowsOf(upcoming.body)
      .filter(row => ['scheduled', 'confirmed'].includes(row.status))
      .map(row => ({
        row,
        at: new Date(row.scheduled_at),
        ours: row.id === built.money.appointment.id
      }))
      .filter(entry => !Number.isNaN(entry.at.getTime()) && entry.at > horizon)
      // A seeded row first: the fixture's own slot disappears at teardown.
      .sort((left, right) => Number(left.ours) - Number(right.ours) || left.at - right.at)[0] ??
    null

  built.conflict = futureAppointment
    ? {
        id: futureAppointment.row.id,
        userId: futureAppointment.row.user_id,
        chairId: futureAppointment.row.dental_chair_id,
        scheduledAt: futureAppointment.row.scheduled_at,
        status: futureAppointment.row.status,
        ours: futureAppointment.ours,
        source: 'GET /api/appointments (a row that already occupies the slot)'
      }
    : null

  if (!built.conflict) {
    const moneyAt = new Date(built.money.appointment.scheduledAt.replace(' ', 'T'))

    built.conflict =
      moneyAt > horizon
        ? {
            id: built.money.appointment.id,
            userId: built.money.appointment.userId,
            chairId: built.money.appointment.chairId,
            scheduledAt: built.money.appointment.scheduledAt,
            status: 'scheduled',
            source: 'the session own money appointment'
          }
        : null
  }

  // --- what each read door answers for this run's rows -------------------
  built.reach = {
    labCases: {
      status: built.lab.index.status,
      total: built.lab.index.total,
      hasA: built.lab.index.ids.includes(built.lab.cases.A?.id),
      hasB: built.lab.index.ids.includes(built.lab.cases.B?.id),
      hasC: built.lab.index.ids.includes(built.lab.cases.C?.id),
      hasD: built.lab.index.ids.includes(built.lab.cases.D?.id)
    },
    continuity: {
      requestId: built.continuity.request?.id ?? null,
      requestPending: built.continuity.request?.status === 'pending',
      recallId: built.continuity.recall?.id ?? null,
      recallPending: built.continuity.recall?.status === 'pending',
      queueCounts: built.continuity.queue.counts,
      metrics: built.continuity.metrics.data
    },
    money: {
      paymentsCreated: built.money.payments.filter(payment => payment.id).length,
      refundCreated: Boolean(built.money.refund?.id),
      drawerId: built.money.session.id,
      drawerOwned: built.money.session.owned,
      revenueNet: built.money.revenueAfter.summary?.total_revenue ?? null,
      revenueRefunds: built.money.revenueAfter.summary?.refunds ?? null,
      revenueTypes: built.money.revenueAfter.types
    }
  }

  const failedPayment = built.money.payments.find(payment => !payment.id)

  if (failedPayment) {
    built.reason = `the payment with ${failedPayment.methodName} was refused: HTTP ${failedPayment.status} ${JSON.stringify(
      failedPayment.errors ?? failedPayment.message
    )}`
    return built
  }

  if (!built.money.refund?.id) {
    built.reason = `the refund was refused: HTTP ${built.money.refund?.status} ${JSON.stringify(
      built.money.refund?.errors ?? built.money.refund?.message
    )}`
    return built
  }

  if (!built.continuity.request?.id) {
    built.reason = 'the closure produced no pending continuity request'
    return built
  }

  const refusedCase = Object.entries(built.lab.cases).find(([, entry]) => !entry.id)

  if (refusedCase) {
    built.reason = `the laboratory case "${refusedCase[0]}" was refused: HTTP ${refusedCase[1].status} ${JSON.stringify(
      refusedCase[1].errors ?? refusedCase[1].message
    )}`
    return built
  }

  built.ok = true
  built.reason = null

  built.declarations = {
    money:
      'two payments (card, transfer) and one partial refund through POST /api/transactions; no delete door keeps the ledger immutable, so the three rows stay as declared residue',
    drawer:
      'opened with POST /api/cash-register/open because a payment and its refund demand an open drawer even with an electronic method; teardown closes it when this session opened it',
    continuity:
      'one appointment checked in, started and closed through the three custody doors; teardown dismisses the request and cancels the recall (neither has a delete door)',
    labCases:
      'four cases through POST /api/lab-cases and the phase door; the API exposes no DELETE, so they stay as declared residue',
    matrixRecalls:
      'two pending recalls through POST /api/patient-recalls, one per granted role of the cancel probe',
    noBridge:
      'no tinker and no direct database access: every row was written and is retired through the API'
  }

  return built
}

const loginCredential = async (username, password = PASSWORD) => {
  const { apiClient } = await import('./helpers.js')
  const context = await apiClient()
  const response = await context.post('/api/auth/login', { data: { username, password } })

  if (!response.ok()) {
    const body = await response.text().catch(() => '')
    throw new Error(`login failed for ${username}: HTTP ${response.status()} ${body}`.trim())
  }

  const body = await response.json()
  const token = body?.data?.token
  const user = body?.data?.user

  if (!token || !user) {
    throw new Error(`login for ${username} returned no token/user: ${JSON.stringify(body)}`)
  }

  return { role: user.role, token, user, username }
}

const roleSessionsForEvidence = async () => {
  const sessions = {}

  for (const role of ROLES) sessions[role] = await login(role)

  return sessions
}

const fixtureSummaryOf = fixtures => ({
  marker: fixtures.marker ?? MARKER,
  token: fixtures.token ?? RUN_TOKEN,
  day: fixtures.day ?? localDay(),
  ok: fixtures.ok === true,
  reason: fixtures.reason ?? null,
  sessions: fixtures.sessions ?? {},
  permissions: fixtures.permissions ?? {},
  actors: fixtures.actors ?? {},
  lookups: fixtures.lookups ?? {},
  money: fixtures.money ?? {},
  continuity: fixtures.continuity ?? {},
  lab: fixtures.lab ?? {},
  conflict: fixtures.conflict ?? null,
  matrix: fixtures.matrix ?? {},
  baseline: fixtures.baseline ?? {},
  reach: fixtures.reach ?? {},
  declarations: fixtures.declarations ?? {},
  teardown: fixtures.teardown ?? [],
  leftovers: fixtures.leftovers ?? [],
  residues: fixtures.residues ?? []
})

const publishFixtures = fixtures =>
  audit('fixtures', { stage: 'layer-0', ...fixtureSummaryOf(fixtures) })

const STATE_FILE = resolve(process.cwd(), EVIDENCE, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U10_FIXTURES__ ??= { promise: null, fixtures: null })

const readState = () => readEvidence('fixtures-state.json')

const writeState = patch => {
  const next = {
    ...stamp(),
    out: EVIDENCE,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }

  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

/**
 * Builds the U10 fixtures once per worker and hands them to every row that needs
 * them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = () => {
  REGISTRY.promise ??= (async () => {
    const fixtures = await buildFixtures().catch(thrown => ({
      marker: MARKER,
      token: RUN_TOKEN,
      day: localDay(),
      ok: false,
      reason: `fixture build failed: ${String(thrown?.message ?? thrown).slice(0, 400)}`,
      sessions: {},
      permissions: {},
      actors: {},
      lookups: {},
      money: {},
      continuity: {},
      lab: {},
      conflict: null,
      matrix: {},
      baseline: {},
      reach: {},
      declarations: {},
      teardown: [],
      leftovers: [],
      residues: []
    }))

    REGISTRY.fixtures = fixtures
    writeState({
      createdAt: new Date().toISOString(),
      retired: false,
      fixtures: fixtureSummaryOf(fixtures)
    })
    publishFixtures(fixtures)

    return fixtures
  })()

  return REGISTRY.promise
}

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => fixtureSummaryOf(fixtures ?? {})

const retire = async fixtures => {
  const admin = await login('administrador')
  const steps = []
  const leftovers = []
  const declared = []

  const record = (type, id, outcome) => {
    if (!id) return
    const entry = { type, id, ...outcome }
    steps.push(entry)
    if (outcome.deleted === true || outcome.alreadyGone === true) return
    if (outcome.declared === true) {
      declared.push(entry)
      return
    }
    leftovers.push(entry)
  }

  // --- the request and the recalls leave the queue through their own doors -
  const request = fixtures.continuity?.request

  if (request?.id) {
    if (request.status === 'pending') {
      const response = await api(admin, 'POST', `/api/continuity-requests/${request.id}/dismiss`, {
        reason: `${NAME_PREFIX}${fixtures.token} descarte de cierre de la sesión`
      })

      // A walk resolves its own request before the teardown: a 422 here means
      // the row is already in a terminal state, not an undeclared leftover.
      record('continuity-request', request.id, {
        status: response.status,
        declared: isSuccess(response) || response.status === 422,
        finalStatus: response.body?.data?.status ?? (response.status === 422 ? 'resolved' : null),
        reason: isSuccess(response)
          ? 'la solicitud se descarta: no hay DELETE y la fila queda como residuo declarado'
          : (response.body?.message ?? null)
      })
    } else {
      record('continuity-request', request.id, {
        status: null,
        declared: true,
        finalStatus: request.status,
        reason: 'otra fase del recorrido ya la resolvió; no hay DELETE'
      })
    }
  }

  const recallIds = [
    ['continuity-recall', fixtures.continuity?.recall],
    ['matrix-recall:administrador', fixtures.matrix?.recalls?.administrador],
    ['matrix-recall:recepcionista', fixtures.matrix?.recalls?.recepcionista]
  ]

  for (const [type, recall] of recallIds) {
    if (!recall?.id) continue

    const response = await api(admin, 'POST', `/api/patient-recalls/${recall.id}/cancel`)
    const alreadyResolved = response.status === 422

    record(type, recall.id, {
      status: response.status,
      declared: isSuccess(response) || alreadyResolved,
      finalStatus: response.body?.data?.status ?? (alreadyResolved ? 'resolved earlier' : null),
      reason: isSuccess(response)
        ? 'el recall se cancela: no hay DELETE, la fila queda como residuo declarado'
        : alreadyResolved
          ? 'otra fase del recorrido ya lo resolvió (422 «solo se cancela un recall pendiente»): no hay DELETE'
          : (response.body?.message ?? null)
    })
  }

  // --- the two appointments, when the API still allows the delete ---------
  const appointments = [
    ['money-appointment', fixtures.money?.appointment],
    ['continuity-appointment', fixtures.continuity?.appointment]
  ]

  for (const [type, appointment] of appointments) {
    if (!appointment?.id) continue

    const response = await api(admin, 'DELETE', `/api/appointments/${appointment.id}`)
    const refused = !isSuccess(response) && response.status !== 404

    record(type, appointment.id, {
      status: response.status,
      deleted: isSuccess(response),
      alreadyGone: response.status === 404,
      declared: refused,
      reason: refused
        ? `la cita no se elimina (HTTP ${response.status}: ${
            response.body?.message ?? response.body?.error ?? 'sin mensaje'
          }); conserva su historia y queda como residuo declarado`
        : null
    })
  }

  // --- the drawer this session opened -------------------------------------
  const drawer = fixtures.money?.session

  if (drawer?.id && drawer.owned) {
    const response = await api(admin, 'POST', '/api/cash-register/close', {
      session_id: drawer.id,
      closing_amount: drawer.openingAmount ?? 100,
      closing_notes: `${NAME_PREFIX}${fixtures.token} cierre de la sesión de auditoría`
    })

    record('cash-register-session', drawer.id, {
      status: response.status,
      deleted: false,
      declared: isSuccess(response) || response.status === 422,
      finalStatus: response.body?.data?.status ?? null,
      difference: response.body?.data?.difference_amount ?? null,
      reason: isSuccess(response)
        ? 'la sesión se cierra (no hay DELETE de sesiones); expected == apertura porque ningún cobro electrónico mueve la gaveta'
        : (response.body?.message ?? null)
    })
  } else if (drawer?.id) {
    record('cash-register-session', drawer.id, {
      status: null,
      deleted: false,
      declared: true,
      reason: 'la sesión pertenece a otra ejecución: la auditoría no cierra una gaveta que no abrió'
    })
  }

  // --- the rows with no delete door at all --------------------------------
  for (const [key, entry] of Object.entries(fixtures.lab?.cases ?? {})) {
    if (!entry?.id) continue

    record(`lab-case:${key}`, entry.id, {
      status: null,
      deleted: false,
      declared: true,
      finalPhase: entry.phase,
      reason:
        'la API no expone DELETE para casos de laboratorio; la orden de trabajo queda como residuo declarado'
    })
  }

  for (const [index, payment] of (fixtures.money?.payments ?? []).entries()) {
    if (!payment?.id) continue

    record(`transaction:payment:${index + 1}`, payment.id, {
      status: null,
      deleted: false,
      declared: true,
      reason: 'el libro del paciente es inmutable: un pago no se borra'
    })
  }

  if (fixtures.money?.refund?.id) {
    record('transaction:refund', fixtures.money.refund.id, {
      status: null,
      deleted: false,
      declared: true,
      reason: 'el libro del paciente es inmutable: una devolución no se borra'
    })
  }

  if (fixtures.continuity?.closure?.encounterId) {
    record('clinical-encounter', fixtures.continuity.closure.encounterId, {
      status: null,
      deleted: false,
      declared: true,
      reason: 'el acto cerrado y firmado no se elimina (historia clínica inmutable)'
    })
  }

  // --- the final state, read back -----------------------------------------
  const finalQueue = await api(admin, 'GET', '/api/continuity/queue')
  const finalLab = await api(admin, 'GET', '/api/lab-cases?per_page=100')
  const finalRecalls = await api(
    admin,
    'GET',
    `/api/patient-recalls?patient_id=${fixtures.lookups?.patients?.continuity?.id}&per_page=100`
  )
  const finalDrawer = fixtures.money?.session?.id
    ? await api(admin, 'GET', `/api/cash-register/sessions/${fixtures.money.session.id}`)
    : null

  const ourRequestIds = new Set([fixtures.continuity?.request?.id].filter(Boolean))
  const finalQueueRows = finalQueue.body?.data?.requests ?? []
  const finalRecallRows = rowsOf(finalRecalls.body)
  const finalLabIds = rowsOf(finalLab.body).map(row => row.id)
  const ourLabIds = Object.values(fixtures.lab?.cases ?? {})
    .map(entry => entry?.id)
    .filter(Boolean)

  fixtures.finalState = {
    requestStatus: fixtures.continuity?.request?.id
      ? (finalQueueRows.find(row => row.id === fixtures.continuity.request.id)?.status ?? 'absent')
      : null,
    requestStillQueued: finalQueueRows.some(row => ourRequestIds.has(row.id)),
    recallStatuses: finalRecallRows.map(row => ({
      id: row.id,
      status: row.status,
      reason: row.reason
    })),
    labRemaining: ourLabIds.filter(id => finalLabIds.includes(id)),
    drawerStatus:
      finalDrawer?.body?.data?.session?.status ?? finalDrawer?.body?.data?.status ?? null,
    statuses: {
      queue: finalQueue.status,
      lab: finalLab.status,
      recalls: finalRecalls.status,
      drawer: finalDrawer?.status ?? null
    }
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  fixtures.residues = declared

  publishFixtures(fixtures)
  writeState({
    retired: true,
    teardown: steps,
    leftovers,
    residues: declared,
    finalState: fixtures.finalState
  })

  return { steps, leftovers, residues: declared, finalState: fixtures.finalState }
}

export const retireFixtures = () => {
  if (!REGISTRY.fixtures) return Promise.resolve(null)
  if (REGISTRY.fixtures.teardownDone) return Promise.resolve(REGISTRY.fixtures.finalState ?? null)
  REGISTRY.fixtures.teardownDone = true

  return retire(REGISTRY.fixtures)
}

test(`@U10 · capa 0 — fixtures de la sesión`, async () => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  expect(fixtures.ok, `fixtures: ${fixtures.reason ?? 'built'}`).toBe(true)
  expect(
    fixtures.money.payments.filter(payment => payment.id),
    'cobros con tarjeta y transferencia'
  ).toHaveLength(2)
  expect(fixtures.money.refund?.status, 'devolución parcial autorizada').toBe(201)
  expect(fixtures.continuity.closure.status, 'cierre NTS 139 de la atención de continuidad').toBe(
    200
  )
  expect(
    fixtures.continuity.request?.status,
    'solicitud de continuidad pendiente tras el cierre'
  ).toBe('pending')
  expect(
    Object.values(fixtures.lab.cases).filter(entry => entry.id),
    'casos de laboratorio creados'
  ).toHaveLength(4)
  expect(fixtures.lab.cases.D?.phase, 'el caso D llega a colocado').toBe('placed')
  expect(hasEvidence('fixtures.json'), 'fixtures.json evidence').toBe(true)
})

test.afterAll(async () => {
  const { fixtures } = REGISTRY

  if (!fixtures || fixtures.teardownDone) return

  fixtures.teardownDone = true
  const result = await retire(fixtures)

  expect(result.leftovers, 'residuos no declarados').toEqual([])
  expect(existsSync(STATE_FILE), 'fixtures-state.json evidence').toBe(true)
})

// Exported for the rows that need a non-role credential.
export { loginCredential }
