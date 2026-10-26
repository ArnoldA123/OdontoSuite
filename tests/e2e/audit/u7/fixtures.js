// Plan #13 (issue #370, U7) — the clinical session's own rows.
//
// Everything the surfaces need is created through the product's own doors
// (`POST /api/patients`, `/api/medical-records`, its evolutions, addenda and
// attachments, `/api/specialty-records` and both AI creation doors), and every
// row is marked `AUDIT-U7` on the patient it hangs from.
//
// The one exception is the state the SPA cannot write: without
// `OPENAI_API_KEY` every analysis ends in `failed` and the citation door only
// accepts a `completed` analysis that was reviewed. This module promotes the
// session's own analyses with the guarded `php artisan tinker` bridge
// (`fixtures-guarded.js`) and declares the flips as fixture state, never as
// observed product behaviour.
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.
//
// Playwright re-evaluates the modules per test, so the fixtures travel through
// `globalThis` (fast path) and mirror to `fixtures-state.json` (worker-restart
// path), the same registry U3–U6 use. Each run builds a fresh set: the AI rows
// are consumed by the void step and the empty patient is consumed by the
// creation step, so reusing yesterday's rows would measure a different state.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { test } from 'playwright/test'

import { loginAs } from '../harness.js'

import { php, promoteScript } from './fixtures-guarded.js'

import {
  U7_EVIDENCE_DIR,
  audit,
  call,
  callUpload,
  localDay,
  pngBuffer,
  stamp,
  uploadPayload
} from './helpers.js'

export const MARKER = 'AUDIT-U7'

const RUN_TOKEN = String(Date.now()).slice(-6)

export const FIXTURE_ROLES = Object.freeze([
  'administrador',
  'odontologo',
  'implantologo',
  'asistente',
  'tecnico_dental'
])

const number = value => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const analysisSnapshot = payload =>
  payload
    ? {
        id: payload.id ?? null,
        patientId: payload.patient_id ?? null,
        attachmentId: payload.clinical_attachment_id ?? null,
        status: payload.status ?? null,
        reviewed: payload.reviewed ?? null,
        reviewDecision: payload.review_decision ?? null,
        confidence: number(payload.confidence_score),
        tokens: payload.tokens_used ?? null,
        estimatedCost: payload.estimated_cost ?? null,
        voided: payload.voided ?? null,
        requestedBy: payload.requested_by?.name ?? null
      }
    : null

const recordSnapshot = payload =>
  payload
    ? {
        id: payload.id ?? null,
        number: payload.record_number ?? null,
        patientId: payload.patient_id ?? null,
        firstVisit: payload.first_visit_date ?? null,
        active: payload.is_active ?? null
      }
    : null

/** The five specialty records, each created by a role the product authorizes. */
const SPECIALTY_FIXTURES = Object.freeze([
  {
    key: 'implantologia',
    role: 'implantologo',
    label: 'Implantología',
    payload: ({ patientId, pieceId, day }) => ({
      specialty: 'implantologia',
      patient_id: patientId,
      dental_piece_id: pieceId,
      implant_brand: `${MARKER} Straumann`,
      implant_model: `${MARKER} BLX`,
      implant_diameter: '4.1',
      implant_length: '10',
      batch_number: `LOTE-${RUN_TOKEN}`,
      placement_date: day,
      status: 'placed',
      surgical_notes: `${MARKER} implante colocado sin incidencias`
    })
  },
  {
    key: 'ortodoncia',
    role: 'odontologo',
    label: 'Ortodoncia',
    payload: ({ patientId, day }) => ({
      specialty: 'ortodoncia',
      patient_id: patientId,
      treatment_type: `${MARKER} ortodoncia fija`,
      treatment_start_date: day,
      treatment_phase: 'initial',
      treatment_objectives: `${MARKER} alinear arcada superior`
    })
  },
  {
    key: 'endodoncia',
    role: 'odontologo',
    label: 'Endodoncia',
    payload: ({ patientId, pieceId, day }) => ({
      specialty: 'endodoncia',
      patient_id: patientId,
      dental_piece_id: pieceId,
      tooth_number: '11',
      canal_count: 2,
      treatment_status: 'in_progress',
      pulp_diagnosis: `${MARKER} pulpitis irreversible`,
      treatment_completion_date: day
    })
  },
  {
    key: 'rehabilitacion',
    role: 'odontologo',
    label: 'Rehabilitación',
    payload: ({ patientId, pieceId }) => ({
      specialty: 'rehabilitacion',
      patient_id: patientId,
      dental_piece_id: pieceId,
      prosthesis_type: 'crown',
      material_type: `${MARKER} zirconio`,
      status: 'impression',
      laboratory_name: `${MARKER} laboratorio`
    })
  },
  {
    key: 'cirugia_oral',
    role: 'odontologo',
    label: 'Cirugía oral',
    payload: ({ patientId, day }) => ({
      specialty: 'cirugia_oral',
      patient_id: patientId,
      procedure_type: `${MARKER} extracción`,
      surgery_site: `${MARKER} cuadrante superior derecho`,
      surgery_date: day,
      status: 'scheduled'
    })
  }
])

// Distinct confidences give every promoted analysis a visible fingerprint in
// the table (the row has no id hook of its own).
const PROMOTION = Object.freeze({
  cited: { confidence: 88, tokens: 1240, cost: 0.0124 },
  voidable: { confidence: 66, tokens: 980, cost: 0.0098 },
  reviewable: { confidence: 81, tokens: 1100, cost: 0.011 }
})

const createPatient = async (request, admin, suffix) => {
  const token = `${RUN_TOKEN}${suffix}`
  const response = await call(request, admin, 'POST', '/api/patients', {
    first_name: MARKER,
    last_name: `paciente ${token}`,
    document_number: `AU7${token}`.slice(0, 20),
    email: `audit-u7-${token}@audit.local`,
    notes: `${MARKER} patient ${token}`,
    birth_date: '1985-04-12',
    gender: 'female'
  })

  return {
    id: response.body?.data?.id ?? null,
    name: `${MARKER} paciente ${token}`,
    document: `AU7${token}`.slice(0, 20),
    status: response.status,
    message: response.message,
    errors: response.errors,
    source: 'POST /api/patients (the run owns the row)'
  }
}

const createAttachment = async (
  request,
  admin,
  patientId,
  { name, rgb, category, description }
) => {
  const response = await callUpload(request, admin, '/api/medical-records/attachments', {
    fields: { patient_id: patientId, category, description },
    file: uploadPayload(name, pngBuffer(48, 48, rgb))
  })

  return {
    id: response.body?.data?.id ?? null,
    name,
    category,
    status: response.status,
    message: response.message,
    errors: response.errors
  }
}

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day: localDay(),
    ok: false,
    reason: 'not built',
    database: null,
    sessions: {},
    permissions: {},
    patients: {},
    piece: null,
    specialtyCatalog: [],
    record: null,
    evolutions: {},
    attachments: {},
    addendum: null,
    specialtyRecords: {},
    analyses: {},
    statusWrites: [],
    declarations: {},
    reach: {},
    createdByWalk: [],
    residues: [],
    teardown: [],
    leftovers: []
  }

  for (const role of FIXTURE_ROLES) {
    const session = await loginAs(request, role)
    built.sessions[role] = {
      id: session.user.id,
      name: session.user.name,
      username: session.user.username,
      role: session.user.role,
      branchId: session.user.branch_id ?? null
    }
    built.permissions[role] = [...(session.user.permissions ?? [])]
  }

  const admin = await loginAs(request, 'administrador')
  const odontologo = await loginAs(request, 'odontologo')

  // Two patients: one carries the clinical set, the other stays empty so the
  // empty tab states of #370 are measured on a patient of this run.
  built.patients.clinical = await createPatient(request, admin, 'A')
  if (!built.patients.clinical.id) {
    built.reason = `the clinical patient was refused: HTTP ${built.patients.clinical.status} ${built.patients.clinical.message ?? ''}`
    return built
  }

  built.patients.empty = await createPatient(request, admin, 'B')
  if (!built.patients.empty.id) {
    built.reason = `the empty patient was refused: HTTP ${built.patients.empty.status} ${built.patients.empty.message ?? ''}`
    return built
  }

  const pieces = await call(request, admin, 'GET', '/api/dental-pieces')
  const piece = Array.isArray(pieces.body?.data) ? pieces.body.data[0] : null

  if (!piece) {
    built.reason = `no dental piece available: HTTP ${pieces.status}`
    return built
  }

  built.piece = { id: piece.id, fdi: piece.fdi_number, name: piece.name }

  const catalog = await call(request, admin, 'GET', '/api/specialties/active')
  built.specialtyCatalog = (catalog.body?.data ?? []).map(entry => ({
    id: entry.id,
    code: entry.code,
    name: entry.name
  }))

  const patientId = built.patients.clinical.id
  const { day } = built

  // --- the medical record, its evolution, attachments and addendum ----------
  const record = await call(request, admin, 'POST', '/api/medical-records', {
    patient_id: patientId,
    first_visit_date: day,
    chief_complaint: `${MARKER} dolor en el primer molar inferior derecho`,
    medical_history: `${MARKER} sin antecedentes sistémicos relevantes`,
    dental_history: `${MARKER} exodoncia previa del 46`,
    allergies: `${MARKER} penicilina`,
    clinical_examination: `${MARKER} caries oclusal en 46, encía sin signos inflamatorios`,
    diagnosis: `${MARKER} caries dentinaria en 46`,
    treatment_plan: `${MARKER} restauración con resina compuesta en dos sesiones`,
    notes: `${MARKER} historia creada por la sesión de auditoría`,
    vital_signs: {
      blood_pressure: '118/76',
      heart_rate: 71,
      temperature: 36.6,
      respiratory_rate: 18
    }
  })

  built.record = {
    ...recordSnapshot(record.body?.data),
    status: record.status,
    message: record.message,
    errors: record.errors
  }

  if (!built.record.id) {
    built.reason = `the medical record was refused: HTTP ${record.status} ${record.message ?? ''}`
    return built
  }

  const evolutionBody = {
    evolution_date: day,
    specialty: built.specialtyCatalog.find(entry => entry.code === 'general')?.code ?? null,
    subjective: `${MARKER} refiere dolor al masticar desde hace tres días`,
    objective: `${MARKER} caries oclusal profunda, sin movilidad`,
    assessment: `${MARKER} pulpitis reversible en 46`,
    plan: `${MARKER} restauración en la próxima sesión`,
    procedures_performed: `${MARKER} apertura y limpieza de la lesión`,
    materials_used: `${MARKER} resina compuesta A2`,
    prescriptions: `${MARKER} ibuprofeno 400 mg cada 8 horas`,
    recommendations: `${MARKER} evitar alimentos fríos`,
    next_appointment_notes: `${MARKER} control en siete días`,
    requires_follow_up: true,
    follow_up_date: null,
    vital_signs: {
      blood_pressure: '120/80',
      heart_rate: 72,
      temperature: 36.5,
      respiratory_rate: 18
    },
    clinical_measurements: {
      pocket_depth_mm: 3.5,
      gingival_recession_mm: 1.2,
      plaque_index_percent: 35,
      tooth_mobility: 1,
      bleeding_on_probing: true
    }
  }

  const evolution = await call(
    request,
    admin,
    'POST',
    `/api/medical-records/${built.record.id}/evolutions`,
    {
      ...evolutionBody,
      follow_up_date: null
    }
  )

  built.evolutions.superadmin = {
    id: evolution.body?.data?.id ?? null,
    status: evolution.status,
    message: evolution.message,
    errors: evolution.errors
  }

  if (!built.evolutions.superadmin.id) {
    built.reason = `the evolution was refused: HTTP ${evolution.status} ${evolution.message ?? ''}`
    return built
  }

  // The odontologist documents one act of her own: it is the clinical
  // relationship the AI policy requires before she may request an analysis.
  const ownEvolution = await call(
    request,
    odontologo,
    'POST',
    `/api/medical-records/${built.record.id}/evolutions`,
    {
      evolution_date: day,
      subjective: `${MARKER} segunda valoración, sin dolor espontáneo`,
      objective: `${MARKER} restauración provisional en buen estado`,
      assessment: `${MARKER} evolución favorable`,
      plan: `${MARKER} alta del episodio agudo`
    }
  )

  built.evolutions.odontologo = {
    id: ownEvolution.body?.data?.id ?? null,
    status: ownEvolution.status,
    message: ownEvolution.message,
    errors: ownEvolution.errors
  }

  built.attachments.radiografia = await createAttachment(request, admin, patientId, {
    name: `audit-u7-radiografia-${RUN_TOKEN}.png`,
    rgb: [180, 190, 200],
    category: 'radiografia',
    description: `${MARKER} radiografía periapical del 46`
  })

  built.attachments.foto = await createAttachment(request, admin, patientId, {
    name: `audit-u7-foto-${RUN_TOKEN}.png`,
    rgb: [210, 160, 150],
    category: 'foto_clinica',
    description: `${MARKER} foto clínica intraoral`
  })

  if (!built.attachments.radiografia.id || !built.attachments.foto.id) {
    built.reason =
      'an attachment was refused: ' +
      `radiografia HTTP ${built.attachments.radiografia.status}, ` +
      `foto HTTP ${built.attachments.foto.status}`
    return built
  }

  const addendum = await call(
    request,
    admin,
    'POST',
    `/api/medical-records/${built.record.id}/addenda`,
    {
      reason: `${MARKER} el diagnóstico inicial omitía la pieza 47`,
      content: `${MARKER} se agrega que el 47 presenta una lesión incipiente en la cara distal.`
    }
  )

  built.addendum = {
    id: addendum.body?.data?.id ?? null,
    status: addendum.status,
    message: addendum.message,
    errors: addendum.errors
  }

  // --- AI analyses: created by the product, finished by the guarded bridge --
  const analysisSeeds = [
    {
      key: 'cited',
      requester: 'administrador',
      rgb: [150, 160, 170],
      label: 'cited'
    },
    { key: 'voidable', requester: 'odontologo', rgb: [160, 170, 180], label: 'voidable' },
    { key: 'reviewable', requester: 'odontologo', rgb: [170, 180, 190], label: 'reviewable' }
  ]

  for (const seed of analysisSeeds) {
    const session = await loginAs(request, seed.requester)
    const attachment = await createAttachment(request, admin, patientId, {
      name: `audit-u7-ai-${seed.key}-${RUN_TOKEN}.png`,
      rgb: seed.rgb,
      category: 'radiografia',
      description: `${MARKER} imagen para el análisis ${seed.key}`
    })

    const analysis = attachment.id
      ? await call(request, session, 'POST', `/api/ai-analysis/analyze/${attachment.id}`, {
          notice_acknowledged: true
        })
      : { status: null, ok: false, message: 'no attachment row', body: null }

    built.analyses[seed.key] = {
      attachmentId: attachment.id,
      attachmentStatus: attachment.status,
      id: analysis.body?.data?.id ?? null,
      createStatus: analysis.status,
      createMessage: analysis.message,
      requester: seed.requester,
      snapshot: analysisSnapshot(analysis.body?.data)
    }
  }

  const missingAnalysis = Object.entries(built.analyses).find(([, entry]) => !entry.id)
  if (missingAnalysis) {
    built.reason = `the analysis "${missingAnalysis[0]}" was refused: HTTP ${missingAnalysis[1].createStatus} ${missingAnalysis[1].createMessage ?? ''}`
    return built
  }

  const promotions = Object.entries(built.analyses).map(([key, entry]) => ({
    id: entry.id,
    findings: [
      {
        diagnosis: `${MARKER} lesión compatible con caries oclusal (${key})`,
        location: 'pieza 46',
        confidence: PROMOTION[key].confidence,
        description: `${MARKER} hallazgo de la sesión de auditoría para el análisis ${key}`,
        severity: 'leve'
      }
    ],
    recommendations: [
      `${MARKER} confirmar el hallazgo con examen clínico directo`,
      `${MARKER} repetir la imagen en el control`
    ],
    confidence_score: PROMOTION[key].confidence,
    raw_response: JSON.stringify({ audit: MARKER, key }),
    tokens_used: PROMOTION[key].tokens,
    estimated_cost: PROMOTION[key].cost,
    model_used: 'gpt-4o'
  }))

  try {
    const applied = php(promoteScript(MARKER, promotions))
    built.database = applied.database
    built.statusWrites = applied.applied.map(row => ({
      ...row,
      source: 'guarded tinker (no OPENAI_API_KEY: every analysis ends failed)',
      declaration: 'fixture state, not observed product behaviour'
    }))
  } catch (thrown) {
    built.reason = `the promotion was refused: ${String(thrown.message).slice(0, 400)}`
    return built
  }

  // The citation door accepts only a reviewed analysis: the fixture reviews the
  // one it will cite through the product door itself.
  const citedReview = await call(
    request,
    admin,
    'POST',
    `/api/ai-analysis/${built.analyses.cited.id}/review`,
    {
      decision: 'accepted',
      notes: `${MARKER} revisión del análisis que se citará en la evolución`
    }
  )

  built.analyses.cited.review = {
    status: citedReview.status,
    reviewed: citedReview.body?.data?.reviewed ?? null,
    decision: citedReview.body?.data?.review_decision ?? null,
    message: citedReview.message
  }

  if (citedReview.status !== 200) {
    built.reason = `the citation fixture could not be reviewed: HTTP ${citedReview.status} ${citedReview.message ?? ''}`
    return built
  }

  const citation = await call(
    request,
    admin,
    'POST',
    `/api/medical-records/${built.record.id}/evolutions`,
    {
      evolution_date: day,
      assessment: `${MARKER} el apoyo de IA coincide con el hallazgo clínico`,
      plan: `${MARKER} se cita el análisis como apoyo a la decisión`,
      cited_analysis_ids: [built.analyses.cited.id]
    }
  )

  built.evolutions.citation = {
    id: citation.body?.data?.id ?? null,
    status: citation.status,
    message: citation.message,
    errors: citation.errors,
    cited: citation.body?.data?.cited_analyses?.map(entry => entry.id) ?? null
  }

  if (!built.evolutions.citation.id) {
    built.reason = `the citing evolution was refused: HTTP ${citation.status} ${citation.message ?? ''}`
    return built
  }

  // --- the five specialty records -------------------------------------------
  for (const entry of SPECIALTY_FIXTURES) {
    const session = await loginAs(request, entry.role)
    const created = await call(
      request,
      session,
      'POST',
      '/api/specialty-records',
      entry.payload({ patientId, pieceId: built.piece.id, day })
    )

    built.specialtyRecords[entry.key] = {
      id: created.body?.data?.id ?? null,
      specialty: entry.key,
      label: entry.label,
      role: entry.role,
      status: created.status,
      message: created.message,
      errors: created.errors
    }
  }

  const refusedSpecialty = Object.entries(built.specialtyRecords).find(([, entry]) => !entry.id)
  if (refusedSpecialty) {
    built.reason = `the specialty record "${refusedSpecialty[0]}" was refused: HTTP ${refusedSpecialty[1].status} ${refusedSpecialty[1].message ?? ''}`
    return built
  }

  // --- reach: what each read door answers for this run's rows ---------------
  const recordsList = await call(
    request,
    admin,
    'GET',
    `/api/medical-records?patient_id=${patientId}&status=all&per_page=50`
  )
  const allSpecialties = await call(
    request,
    admin,
    'GET',
    `/api/specialty-records/patient/${patientId}/all`
  )
  const aiIndex = await call(request, admin, 'GET', `/api/ai-analysis?patient_id=${patientId}`)
  const aiIndexOdontologo = await call(
    request,
    odontologo,
    'GET',
    `/api/ai-analysis?patient_id=${patientId}`
  )
  const statsAdmin = await call(
    request,
    admin,
    'GET',
    `/api/medical-records/patient/${patientId}/stats`
  )

  built.reach = {
    records: {
      status: recordsList.status,
      rows: Array.isArray(recordsList.body?.data) ? recordsList.body.data.length : null,
      found: (recordsList.body?.data ?? []).some(row => row.id === built.record.id)
    },
    specialties: {
      status: allSpecialties.status,
      groups: Object.fromEntries(
        Object.entries(allSpecialties.body?.data ?? {}).map(([key, rows]) => [
          key,
          Array.isArray(rows) ? rows.length : null
        ])
      )
    },
    ai: {
      status: aiIndex.status,
      rows: Array.isArray(aiIndex.body?.data) ? aiIndex.body.data.length : null,
      ids: (aiIndex.body?.data ?? []).map(row => row.id),
      odontologoRows: Array.isArray(aiIndexOdontologo.body?.data)
        ? aiIndexOdontologo.body.data.length
        : null,
      odontologoIds: (aiIndexOdontologo.body?.data ?? []).map(row => row.id)
    },
    stats: {
      status: statsAdmin.status,
      totalRecords: statsAdmin.body?.data?.total_records ?? null,
      totalEvolutions: statsAdmin.body?.data?.total_evolutions ?? null,
      totalAttachments: statsAdmin.body?.data?.total_attachments ?? null,
      attachmentCategories: statsAdmin.body?.data?.attachment_categories ?? null
    }
  }

  if (!built.reach.records.found) {
    built.reason = 'the created record did not appear in GET /api/medical-records'
    return built
  }

  built.declarations = {
    patients:
      'created through POST /api/patients so the run owns them; DELETE /api/patients refuses a patient with a clinical record or attachments',
    record:
      'a clinical history is immutable (#292): no edit and no delete door exists, so the record, its evolutions and its addenda stay as residue',
    analyses:
      'created through POST /api/ai-analysis/analyze; without OPENAI_API_KEY every row lands in `failed`, so status/completion columns are placed with the guarded tinker and declared as fixture state',
    citation:
      'created through POST /api/medical-records/{id}/evolutions with cited_analysis_ids after the product reviewed the analysis',
    specialtyRecords:
      'created through POST /api/specialty-records by the role each specialty authorizes; the walk deletes only what it creates itself'
  }

  built.ok = true
  built.reason = null

  return built
}

const fixtureSummaryOf = fixtures => ({
  marker: fixtures.marker,
  token: fixtures.token,
  day: fixtures.day,
  ok: fixtures.ok === true,
  reason: fixtures.reason ?? null,
  database: fixtures.database,
  sessions: fixtures.sessions ?? {},
  permissions: fixtures.permissions ?? {},
  patients: fixtures.patients ?? {},
  piece: fixtures.piece,
  specialtyCatalog: fixtures.specialtyCatalog ?? [],
  record: fixtures.record,
  evolutions: fixtures.evolutions ?? {},
  attachments: fixtures.attachments ?? {},
  addendum: fixtures.addendum,
  specialtyRecords: fixtures.specialtyRecords ?? {},
  analyses: Object.fromEntries(
    Object.entries(fixtures.analyses ?? {}).map(([key, entry]) => [
      key,
      {
        id: entry.id,
        attachmentId: entry.attachmentId,
        createStatus: entry.createStatus,
        requester: entry.requester,
        snapshot: entry.snapshot,
        review: entry.review ?? null
      }
    ])
  ),
  statusWrites: fixtures.statusWrites ?? [],
  declarations: fixtures.declarations ?? {},
  reach: fixtures.reach ?? {},
  createdByWalk: fixtures.createdByWalk ?? [],
  residues: fixtures.residues ?? [],
  teardown: fixtures.teardown ?? [],
  leftovers: fixtures.leftovers ?? []
})

const publishFixtures = fixtures =>
  audit('fixtures.json', { ...stamp(), stage: 'layer-0', ...fixtureSummaryOf(fixtures) })

const STATE_FILE = resolve(process.cwd(), U7_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U7_FIXTURES__ ??= {
  promise: null,
  fixtures: null,
  state: null
})

const readState = () => {
  if (REGISTRY.state) return REGISTRY.state

  try {
    if (!existsSync(STATE_FILE)) return null
    REGISTRY.state = JSON.parse(readFileSync(STATE_FILE, 'utf8'))
    return REGISTRY.state
  } catch {
    return null
  }
}

const writeState = patch => {
  const next = {
    ...stamp(),
    session: 'U7',
    out: U7_EVIDENCE_DIR,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }

  REGISTRY.state = next
  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

/**
 * Builds the U7 fixtures once per worker and hands them to every row that needs
 * them. A build failure is a reason in the evidence, never an exception.
 */
export const ensureFixtures = request => {
  if (!REGISTRY.promise) {
    REGISTRY.promise = (async () => {
      const fixtures = await buildFixtures(request).catch(thrown => ({
        marker: MARKER,
        token: RUN_TOKEN,
        day: localDay(),
        ok: false,
        reason: `fixture build failed: ${String(thrown?.message ?? thrown).slice(0, 400)}`,
        database: null,
        sessions: {},
        permissions: {},
        patients: {},
        specialtyCatalog: [],
        analyses: {},
        specialtyRecords: {},
        statusWrites: [],
        createdByWalk: [],
        residues: [],
        teardown: [],
        leftovers: []
      }))

      REGISTRY.fixtures = fixtures
      writeState({
        createdAt: new Date().toISOString(),
        day: fixtures.day,
        retired: false,
        fixtures
      })
      publishFixtures(fixtures)
      return fixtures
    })()
  }

  return REGISTRY.promise
}

export const recordFixture = fixtures => fixtures?.record ?? null

export const analysisByKey = (fixtures, key) => fixtures?.analyses?.[key] ?? null

export const specialtyFixture = (fixtures, key) => fixtures?.specialtyRecords?.[key] ?? null

/** Declares a row this session created that the API does not let it retire. */
export const registerResidue = residue => {
  if (!REGISTRY.fixtures) return null

  const entry = { declaredAt: new Date().toISOString(), ...residue }
  REGISTRY.fixtures.residues = [...(REGISTRY.fixtures.residues ?? []), entry]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return entry
}

/** A row a walkthrough created through the UI; teardown tries to delete it. */
export const registerCreated = (type, entry) => {
  if (!REGISTRY.fixtures) return null

  const row = { type, declaredAt: new Date().toISOString(), ...entry }
  REGISTRY.fixtures.createdByWalk = [...(REGISTRY.fixtures.createdByWalk ?? []), row]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return row
}

const retire = async (request, fixtures) => {
  const admin = await loginAs(request, 'administrador')
  const steps = []
  const leftovers = []

  const record = (type, id, outcome, reason) => {
    steps.push({ type, id, ...outcome })

    if (outcome.deleted !== true) {
      leftovers.push({
        type,
        id,
        reason: reason ?? outcome.message ?? 'the API refused the delete'
      })
    }
  }

  if (fixtures.database !== 'odontosuite_audit') {
    steps.push({ action: 'refused', reason: 'fixtures did not confirm the audit database' })
    fixtures.teardown = steps
    fixtures.leftovers = leftovers
    publishFixtures(fixtures)
    writeState({ retired: true, teardown: steps, leftovers })
    return steps
  }

  // Specialty records are the only clinical rows with a delete door.
  const specialtyTargets = [
    ...Object.values(fixtures.specialtyRecords ?? {}).map(entry => ({
      specialty: entry.specialty,
      id: entry.id,
      origin: 'fixture'
    })),
    ...(fixtures.createdByWalk ?? [])
      .filter(entry => entry.type === 'specialty-record' && entry.deleted !== true)
      .map(entry => ({ specialty: entry.specialty, id: entry.id, origin: 'walkthrough' }))
  ]

  for (const target of specialtyTargets) {
    if (!target.id) continue

    const response = await call(request, admin, 'DELETE', `/api/specialty-records/${target.id}`, {
      specialty: target.specialty
    })
    record(
      `specialty-record:${target.origin}`,
      target.id,
      { specialty: target.specialty, status: response.status, deleted: response.ok === true },
      'the API refused the delete (the record may already be gone)'
    )
  }

  if (fixtures.patients?.clinical?.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/patients/${fixtures.patients.clinical.id}`
    )
    record(
      'patient',
      fixtures.patients.clinical.id,
      { status: response.status, deleted: response.ok === true, message: response.message },
      'the patient carries a clinical history and attachments: the delete door refuses it (422)'
    )
  }

  if (fixtures.patients?.empty?.id) {
    const response = await call(
      request,
      admin,
      'DELETE',
      `/api/patients/${fixtures.patients.empty.id}`
    )
    record(
      'patient',
      fixtures.patients.empty.id,
      { status: response.status, deleted: response.ok === true, message: response.message },
      'the empty patient was kept: the walkthrough creates its first history through the UI'
    )
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps, leftovers })
  return steps
}

export const retireFixtures = request => retire(request, REGISTRY.fixtures)

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => fixtureSummaryOf(fixtures ?? {})
