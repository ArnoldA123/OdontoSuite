// Plan #13 (issue #370, U7) — layer 5: the contract of the clinical endpoints.
//
// `{endpoint, method, role, expected, observed}` for every endpoint the U7
// manifest declares (29 routes across the three modules), plus the two reads the
// surfaces borrow (patient, specialty catalogue, dental pieces, procedure
// catalogue) and the clinical routes that must not exist.
//
// The expectation is derived from the permission the route declares, read from
// the catalogue the API publishes at login (`user.permissions`, built by
// `App\Authorization\Permissions`): a role that holds it reaches the door and
// the row expects the door's own answer (200 for a read, 422 for a validation
// refusal, 404 for a missing target, 405 for a route that does not exist); a
// role that does not hold it expects 403 before validation.
//
// The probes never consume the session's fixtures: writes send an incomplete
// body on purpose, deletes name an id that does not exist, and the two `void`
// rows use the cited analysis (which the product refuses) plus a missing id.

import { test, expect } from 'playwright/test'

import { loginAs } from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  AI_ROUTE,
  RECORDS_ROUTE,
  SPECIALTIES_ROUTE,
  audit,
  call,
  callUpload,
  stamp
} from './helpers.js'

const ROLES = Object.freeze(['administrador', 'odontologo', 'asistente', 'tecnico_dental'])
const ROLES_WITH_IMPLANTOLOGO = Object.freeze([...ROLES, 'implantologo'])

const MISSING_ID = 999999999

const definitionsFor = fixtures => {
  const patientId = fixtures.patients.clinical.id
  const emptyPatientId = fixtures.patients.empty.id
  const recordId = fixtures.record.id
  const evolutionId = fixtures.evolutions.superadmin.id
  const attachmentId = fixtures.attachments.radiografia.id
  const citationAttachmentId = fixtures.analyses.cited.attachmentId
  const citedId = fixtures.analyses.cited.id
  const reviewedId = fixtures.analyses.reviewable.id
  const specialtyRecordId = fixtures.specialtyRecords.ortodoncia.id
  const catalogId = fixtures.procedureCatalogId ?? null

  return [
    // --- /medical-records: the manifest's eleven endpoints ---------------
    {
      endpoint: '/api/medical-records',
      method: 'GET',
      path: `/api/medical-records?patient_id=${patientId}&status=all`,
      permission: 'manage.medical_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/medical-records/{id}',
      method: 'GET',
      path: `/api/medical-records/${recordId}`,
      permission: 'manage.medical_record',
      expect: { grant: [200], deny: [404] },
      kind: 'read'
    },
    {
      endpoint: '/api/medical-records',
      method: 'POST',
      path: '/api/medical-records',
      body: {},
      permission: 'manage.medical_record',
      expect: { grant: [422] },
      kind: 'validation'
    },
    {
      endpoint: '/api/medical-records/{id}/evolutions',
      method: 'GET',
      path: `/api/medical-records/${recordId}/evolutions`,
      permission: 'manage.evolution',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/medical-records/{id}/evolutions',
      method: 'POST',
      path: `/api/medical-records/${recordId}/evolutions`,
      // Every SOAP field is optional, so an empty body is a valid evolution
      // (walk-records measures exactly that). The refusal is probed with a
      // future date, which the door rejects without writing anything.
      body: { evolution_date: '2999-01-01', subjective: 'AUDIT-U7 fecha futura' },
      permission: 'manage.evolution',
      expect: { grant: [422] },
      kind: 'validation'
    },
    {
      endpoint: '/api/medical-records/{id}/addenda',
      method: 'POST',
      path: `/api/medical-records/${recordId}/addenda`,
      body: {},
      permission: 'manage.medical_record',
      expect: { grant: [422] },
      kind: 'validation'
    },
    {
      endpoint: '/api/medical-records/evolutions/{id}/addenda',
      method: 'POST',
      path: `/api/medical-records/evolutions/${evolutionId}/addenda`,
      body: {},
      permission: 'manage.evolution',
      expect: { grant: [422] },
      kind: 'validation'
    },
    {
      endpoint: '/api/medical-records/attachments',
      method: 'POST',
      path: '/api/medical-records/attachments',
      body: {},
      permission: 'manage.attachment',
      expect: { grant: [422] },
      kind: 'validation'
    },
    {
      endpoint: '/api/medical-records/attachments/{id}',
      method: 'DELETE',
      path: `/api/medical-records/attachments/${MISSING_ID}`,
      permission: 'manage.attachment',
      expect: { grant: [404], deny: [404] },
      kind: 'missing-target'
    },
    {
      endpoint: '/api/medical-records/patient/{id}/attachments',
      method: 'GET',
      path: `/api/medical-records/patient/${patientId}/attachments`,
      permission: 'manage.medical_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/medical-records/patient/{id}/stats',
      method: 'GET',
      path: `/api/medical-records/patient/${patientId}/stats`,
      permission: 'view.medical_record_stats',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/patients/{id}',
      method: 'GET',
      path: `/api/patients/${emptyPatientId}`,
      permission: 'view.patient',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/specialties/active',
      method: 'GET',
      path: '/api/specialties/active',
      permission: 'view.specialty',
      expect: { grant: [200] },
      kind: 'read'
    },

    // --- /specialty-records: the manifest's eight endpoints ---------------
    {
      endpoint: '/api/specialty-records',
      method: 'GET',
      path: `/api/specialty-records?specialty=ortodoncia&patient_id=${patientId}`,
      permission: 'manage.specialty_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/specialty-records/{id}',
      method: 'GET',
      path: `/api/specialty-records/${specialtyRecordId}`,
      permission: 'manage.specialty_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/specialty-records',
      method: 'POST',
      path: '/api/specialty-records',
      body: { specialty: 'ortodoncia', patient_id: patientId },
      permission: 'manage.specialty_record',
      expect: { grant: [422], deny: [403] },
      kind: 'validation',
      note: 'the body names a specialty the caller may write; the refusal is the missing treatment_type'
    },
    {
      endpoint: '/api/specialty-records/{id}',
      method: 'PUT',
      path: `/api/specialty-records/${specialtyRecordId}`,
      body: { specialty: 'ortodoncia', treatment_type: 'AUDIT-U7 contrato' },
      permission: 'manage.specialty_record',
      expect: { grant: [200, 422], deny: [403] },
      kind: 'write',
      note: 'updates the session\u2019s own orthodontics fixture'
    },
    {
      endpoint: '/api/specialty-records/{id}',
      method: 'DELETE',
      path: `/api/specialty-records/${MISSING_ID}`,
      body: { specialty: 'ortodoncia' },
      permission: 'manage.specialty_record',
      expect: { grant: [404], deny: [404] },
      kind: 'missing-target',
      note: '#428: el controlador desenlaza ModelNotFoundException antes del catch genérico, así que un id inexistente responde el mismo 404 que show()'
    },
    {
      endpoint: '/api/specialty-records/{id}',
      method: 'PUT',
      path: `/api/specialty-records/${MISSING_ID}`,
      body: { specialty: 'ortodoncia', treatment_type: 'AUDIT-U7 destino inexistente' },
      permission: 'manage.specialty_record',
      expect: { grant: [404], deny: [404] },
      kind: 'missing-target',
      note: '#428: misma rama en la puerta de edición; el intento no deja error de servidor en el log'
    },
    {
      endpoint: '/api/specialty-records/patient/{id}/all',
      method: 'GET',
      path: `/api/specialty-records/patient/${patientId}/all`,
      permission: 'manage.specialty_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/specialty-records/patient/{id}/{specialty}',
      method: 'GET',
      path: `/api/specialty-records/patient/${patientId}/ortodoncia`,
      permission: 'manage.specialty_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/specialty-records/patient/{id}/{specialty}/stats',
      method: 'GET',
      path: `/api/specialty-records/patient/${patientId}/ortodoncia/stats`,
      permission: 'manage.specialty_record',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/dental-pieces',
      method: 'GET',
      path: '/api/dental-pieces',
      permission: 'view.dental_piece',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog',
      method: 'GET',
      path: '/api/procedure-catalog?per_page=1',
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/{id}',
      method: 'GET',
      path: catalogId ? `/api/procedure-catalog/${catalogId}` : '/api/procedure-catalog/1',
      permission: 'view.procedure_catalog',
      expect: { grant: [200, 404] },
      kind: 'read'
    },

    // --- /ai-analysis: the manifest's ten endpoints -----------------------
    {
      endpoint: '/api/ai-analysis',
      method: 'GET',
      path: '/api/ai-analysis?per_page=5',
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/stats',
      method: 'GET',
      path: '/api/ai-analysis/stats',
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/pending',
      method: 'GET',
      path: '/api/ai-analysis/pending',
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/{id}',
      method: 'GET',
      path: `/api/ai-analysis/${citedId}`,
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/attachment/{id}',
      method: 'GET',
      path: `/api/ai-analysis/attachment/${citationAttachmentId}`,
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/patient/{id}',
      method: 'GET',
      path: `/api/ai-analysis/patient/${patientId}`,
      permission: 'view.ai_analysis',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/ai-analysis/analyze/{attachmentId}',
      method: 'POST',
      path: `/api/ai-analysis/analyze/${attachmentId}`,
      body: {},
      permission: 'create.ai_analysis',
      expect: { grant: [422] },
      kind: 'validation',
      note: 'no `notice_acknowledged`: the declaration is the gate #296 added'
    },
    {
      endpoint: '/api/ai-analysis/upload-and-analyze',
      method: 'POST_UPLOAD',
      path: '/api/ai-analysis/upload-and-analyze',
      multipart: {
        fields: { patient_id: patientId, category: 'radiografia' },
        fileField: 'image',
        file: { name: 'audit-u7-matrix.png', mimeType: 'image/png', bytes: 64 }
      },
      permission: 'create.ai_analysis',
      expect: { grant: [422] },
      kind: 'validation',
      note: 'an image without the notice declaration: 422 proves the authorization and the shape'
    },
    {
      endpoint: '/api/ai-analysis/{id}/review',
      method: 'POST',
      path: `/api/ai-analysis/${reviewedId}/review`,
      body: { decision: 'accepted', notes: 'AUDIT-U7 contrato de la puerta de revisión' },
      permission: 'review.ai_analysis',
      expect: { grant: [200, 422] },
      kind: 'write',
      note: 'the walkthrough may have reviewed this row already; 422 still proves the guard passed'
    },
    {
      endpoint: '/api/ai-analysis/{id}/void',
      method: 'POST',
      path: `/api/ai-analysis/${citedId}/void`,
      body: { reason: 'AUDIT-U7 intento de anular un análisis citado' },
      permission: 'void.ai_analysis',
      expect: { grant: [422] },
      kind: 'refusal',
      note: 'cited by an evolution: the service refuses with the reason the interface never showed'
    },
    {
      endpoint: '/api/ai-analysis/{id}/void',
      method: 'POST',
      path: `/api/ai-analysis/${MISSING_ID}/void`,
      body: { reason: 'AUDIT-U7 destino inexistente' },
      permission: 'void.ai_analysis',
      expect: { grant: [404] },
      kind: 'missing-target'
    },

    // --- the clinical routes that must not exist --------------------------
    {
      endpoint: '/api/medical-records/{id}',
      method: 'PUT',
      path: `/api/medical-records/${recordId}`,
      body: { chief_complaint: 'AUDIT-U7 intento de edición' },
      expect: { all: [405] },
      kind: 'absent-route',
      note: 'a history is immutable (#292): the route is not published'
    },
    {
      endpoint: '/api/medical-records/{id}',
      method: 'PATCH',
      path: `/api/medical-records/${recordId}`,
      body: { chief_complaint: 'AUDIT-U7 intento de edición' },
      expect: { all: [405] },
      kind: 'absent-route'
    },
    {
      endpoint: '/api/medical-records/{id}',
      method: 'DELETE',
      path: `/api/medical-records/${recordId}`,
      expect: { all: [405] },
      kind: 'absent-route'
    }
  ]
}

const expectedFor = (definition, role, holdsPermission) => {
  if (definition.expect.all) return { statuses: [...definition.expect.all], why: 'route absent' }

  if (!holdsPermission) {
    return {
      statuses: [...new Set([403, ...(definition.expect.deny ?? [])])],
      why: `403: the role does not hold ${definition.permission}`
    }
  }

  return {
    statuses: [...definition.expect.grant],
    why: `granted: the door answers ${definition.expect.grant.join('/')}`
  }
}

const probe = async (request, session, definition) => {
  if (definition.method === 'POST_UPLOAD') {
    return callUpload(request, session, definition.path, {
      fields: definition.multipart.fields,
      fileField: definition.multipart.fileField,
      file: {
        name: definition.multipart.file.name,
        mimeType: definition.multipart.file.mimeType,
        buffer: Buffer.alloc(definition.multipart.file.bytes, 7)
      }
    })
  }

  return call(request, session, definition.method, definition.path, definition.body)
}

test('@U7 · capa 5 — contrato por rol de los 29 endpoints clínicos', async ({ request }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const sessions = new Map()

  for (const role of ROLES_WITH_IMPLANTOLOGO) {
    sessions.set(role, await loginAs(request, role))
  }

  // The catalogue id is read from the product so the detail row does not guess.
  const admin = sessions.get('administrador')
  const catalog = await call(request, admin, 'GET', '/api/procedure-catalog?per_page=1')
  const catalogId = Array.isArray(catalog.body?.data) ? (catalog.body.data[0]?.id ?? null) : null
  fixtures.procedureCatalogId = catalogId

  const definitions = definitionsFor(fixtures)
  const rows = []

  for (const definition of definitions) {
    for (const role of ROLES) {
      const session = sessions.get(role)
      const holdsPermission = definition.expect.all
        ? null
        : (session.user.permissions ?? []).includes(definition.permission)
      const expectation = expectedFor(definition, role, holdsPermission === true)
      const response = await probe(request, session, definition)
      const observed = response.status

      const deviation = definition.deviation ?? null
      const ok = expectation.statuses.includes(observed)
      const deviated =
        !ok && deviation && deviation.status === observed && holdsPermission !== false

      rows.push({
        endpoint: definition.endpoint,
        method: definition.method === 'POST_UPLOAD' ? 'POST' : definition.method,
        role,
        expected: expectation.statuses,
        observed,
        ok: ok || deviated,
        deviation: deviated
          ? { declaredExpected: expectation.statuses, observed, ...deviation }
          : null,
        expectedWhy: expectation.why,
        permission: definition.permission ?? null,
        roleHasPermission: holdsPermission,
        kind: definition.kind,
        message: response.message,
        errors: response.errors ? Object.keys(response.errors) : null,
        path: definition.path,
        note: definition.note ?? null
      })
    }
  }

  // The implantology write row: the only specialty whose authorized role set
  // differs from the other four (`StoreSpecialtyRecordRequest::authorize`).
  for (const role of ROLES_WITH_IMPLANTOLOGO) {
    const session = sessions.get(role)
    const allowed = ['administrador', 'implantologo'].includes(role)
    const response = await call(request, session, 'POST', '/api/specialty-records', {
      specialty: 'implantologia',
      patient_id: fixtures.patients.clinical.id
    })

    rows.push({
      endpoint: '/api/specialty-records (implantologia)',
      method: 'POST',
      role,
      expected: allowed ? [422] : [403],
      observed: response.status,
      ok: (allowed ? [422] : [403]).includes(response.status),
      expectedWhy: allowed
        ? 'granted: the specialty matrix authorizes this role, the body is incomplete'
        : '403: StoreSpecialtyRecordRequest::authorize refuses this role for implantologia',
      permission: 'manage.specialty_record',
      roleHasPermission: (session.user.permissions ?? []).includes('manage.specialty_record'),
      kind: 'specialty-matrix',
      message: response.message,
      errors: response.errors ? Object.keys(response.errors) : null,
      path: '/api/specialty-records',
      note: 'role × specialty matrix measured at the door'
    })
  }

  const byModule = {
    [RECORDS_ROUTE]: rows.filter(row =>
      /medical-records|patients|specialties|dental-pieces/.test(row.path)
    ),
    [SPECIALTIES_ROUTE]: rows.filter(row =>
      /specialty-records|dental-pieces|procedure-catalog/.test(row.path)
    ),
    [AI_ROUTE]: rows.filter(row => /ai-analysis/.test(row.path))
  }

  const summary = {
    rows: rows.length,
    endpoints: [...new Set(rows.map(row => `${row.method} ${row.endpoint}`))].length,
    ok: rows.filter(row => row.ok).length,
    deviations: rows.filter(row => row.deviation),
    mismatches: rows.filter(row => !row.ok),
    byRole: Object.fromEntries(
      ROLES_WITH_IMPLANTOLOGO.map(role => [
        role,
        {
          rows: rows.filter(row => row.role === role).length,
          ok: rows.filter(row => row.role === role && row.ok).length,
          denials: rows.filter(row => row.role === role && row.observed === 403).length
        }
      ])
    ),
    coverage: Object.fromEntries(
      Object.entries(byModule).map(([route, moduleRows]) => [route, moduleRows.length])
    )
  }

  audit('api-matrix.json', {
    ...stamp(),
    stage: 'layer-5',
    expectedSource:
      'user.permissions from POST /api/auth/login, built by App\\Authorization\\Permissions',
    matrix: ['administrador', 'odontologo', 'implantologo', 'asistente', 'tecnico_dental'],
    summary,
    rows,
    fixtures: fixtureSummary(fixtures)
  })

  expect(summary.endpoints, 'endpoints in the matrix').toBeGreaterThanOrEqual(29)
  expect(summary.mismatches, 'rows whose observed status left its expectation').toEqual([])
})
