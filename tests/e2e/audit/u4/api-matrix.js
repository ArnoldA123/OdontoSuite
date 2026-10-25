// Plan #13 (issue #367, U4) — layer 5: the session's endpoints by role.
//
// The expected status is derived from `App\Authorization\Permissions` itself:
// the private GRANTS matrix is dumped with PHP reflection and every role is
// probed with a real token. The export door carries a second gate —
// `PatientPolicy::export()` — and that policy is dumped the same way, because
// finanzas holds the inert `export.patient` grant the policy refuses; deriving
// the expectation from GRANTS alone would report a documented design as a defect.
//
// Reads use real fixture ids; writes use an invalid body so an authorized role
// observes 422 and an unauthorized one 403. The only row that is really written
// is the DELETE target: one throwaway patient per run, retired here.

import { execFileSync } from 'node:child_process'

import { test, expect } from 'playwright/test'

import { BASE_URL, loginAs, writeEvidence } from '../harness.js'

import {
  MARKER,
  U4_EVIDENCE_DIR,
  ensureFixtures,
  fixtureSummary,
  patientBySlot
} from './fixtures.js'

import { call, localDay } from './helpers.js'

const ROUTE = '/patients'
const ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

const PERMISSIONS = Object.freeze({
  viewPatient: 'view.patient',
  createPatient: 'create.patient',
  updatePatient: 'update.patient',
  deletePatient: 'delete.patient',
  exportPatient: 'export.patient',
  viewPatientStatement: 'view.patient_statement',
  managePatientCharge: 'manage.patient_charge',
  viewAuditLog: 'view.audit_log',
  viewLabCase: 'view.lab_case',
  manageMedicalRecord: 'manage.medical_record',
  viewQuotation: 'view.quotation',
  manageTreatmentPlan: 'manage.treatment_plan',
  manageSpecialtyRecord: 'manage.specialty_record',
  viewBranch: 'view.branch',
  viewDentalPiece: 'view.dental_piece',
  viewUser: 'view.user'
})

const audit = (filePath, payload) => writeEvidence(filePath, payload, { dir: U4_EVIDENCE_DIR })

const phpJson = script =>
  JSON.parse(
    execFileSync('php', ['-r', script], {
      cwd: process.cwd(),
      encoding: 'utf8',
      timeout: 20000
    })
  )

const dumpGrants = () =>
  phpJson(
    [
      "require 'vendor/autoload.php';",
      '$reflection = new ReflectionClass(App\\Authorization\\Permissions::class);',
      "echo json_encode($reflection->getReflectionConstant('GRANTS')->getValue(), JSON_UNESCAPED_SLASHES);"
    ].join(' ')
  )

// `PatientPolicy::export()` reads only the role, so an empty model is enough.
const dumpExportPolicy = () =>
  phpJson(
    [
      "require 'vendor/autoload.php';",
      '$policy = new App\\Policies\\PatientPolicy();',
      '$user = new App\\Models\\User();',
      '$patient = new App\\Models\\Patient();',
      `$roles = ${JSON.stringify(ROLES)};`,
      '$out = [];',
      'foreach ($roles as $role) { $user->role = $role; $out[$role] = $policy->export($user, $patient); }',
      'echo json_encode($out);'
    ].join(' ')
  )

const revision = () => {
  try {
    return execFileSync('git', ['rev-parse', 'HEAD'], {
      cwd: process.cwd(),
      encoding: 'utf8'
    }).trim()
  } catch {
    return null
  }
}

const endpoint = (key, method, path, permission, options = {}) => ({
  key,
  method,
  path,
  permission,
  mode: options.mode ?? 'read',
  body: options.body,
  authorizedStatus: options.authorizedStatus ?? 200,
  target: options.target ?? 'fixture',
  policy: options.policy ?? null
})

const buildEndpoints = targets => [
  endpoint('patients.index', 'GET', () => '/api/patients?per_page=5', PERMISSIONS.viewPatient),
  endpoint(
    'patients.show',
    'GET',
    () => `/api/patients/${targets.patientId}`,
    PERMISSIONS.viewPatient
  ),
  endpoint(
    'patients.export',
    'GET',
    () => `/api/patients/${targets.patientId}/export?format=pdf`,
    PERMISSIONS.exportPatient,
    { policy: 'PatientPolicy::export', target: 'P1 exported as PDF' }
  ),
  endpoint(
    'patients.statement',
    'GET',
    () => `/api/patients/${targets.patientId}/statement`,
    PERMISSIONS.viewPatientStatement
  ),
  endpoint('patients.store', 'POST', () => '/api/patients', PERMISSIONS.createPatient, {
    mode: 'write',
    body: {},
    authorizedStatus: 422,
    target: 'invalid body'
  }),
  endpoint(
    'patients.update',
    'PUT',
    () => `/api/patients/${targets.patientId}`,
    PERMISSIONS.updatePatient,
    { mode: 'write', body: {}, authorizedStatus: 422, target: 'invalid body' }
  ),
  endpoint(
    'patients.destroy',
    'DELETE',
    role => `/api/patients/${targets.deleteIds[role] ?? targets.deleteIds.administrador}`,
    PERMISSIONS.deletePatient,
    { mode: 'write', authorizedStatus: 200, target: 'own throwaway row' }
  ),
  endpoint(
    'patientCharges.store',
    'POST',
    () => '/api/patient-charges',
    PERMISSIONS.managePatientCharge,
    { mode: 'write', body: {}, authorizedStatus: 422, target: 'invalid body' }
  ),
  endpoint(
    'auditLogs.patient',
    'GET',
    () => `/api/audit-logs/patient/${targets.patientId}`,
    PERMISSIONS.viewAuditLog
  ),
  endpoint(
    'labCases.index',
    'GET',
    () => `/api/lab-cases?patient_id=${targets.patientId}`,
    PERMISSIONS.viewLabCase
  ),
  endpoint(
    'medicalRecords.index',
    'GET',
    () => `/api/medical-records?patient_id=${targets.patientId}`,
    PERMISSIONS.manageMedicalRecord
  ),
  endpoint(
    'quotations.index',
    'GET',
    () => `/api/quotations?patient_id=${targets.patientId}`,
    PERMISSIONS.viewQuotation
  ),
  endpoint(
    'treatmentPlans.index',
    'GET',
    () => `/api/treatment-plans?patient_id=${targets.patientId}`,
    PERMISSIONS.manageTreatmentPlan
  ),
  endpoint(
    'specialtyRecords.all',
    'GET',
    () => `/api/specialty-records/patient/${targets.patientId}/all`,
    PERMISSIONS.manageSpecialtyRecord
  ),
  endpoint('branches.active', 'GET', () => '/api/branches/active', PERMISSIONS.viewBranch),
  endpoint('dentalPieces.index', 'GET', () => '/api/dental-pieces', PERMISSIONS.viewDentalPiece),
  endpoint('users.active', 'GET', () => '/api/users/active', PERMISSIONS.viewUser)
]

const createDeleteTarget = async (request, admin, fixtures) => {
  const response = await call(request, admin, 'POST', '/api/patients', {
    first_name: MARKER,
    last_name: `${MARKER} Matrix ${fixtures.token}`,
    document_number: `U4-${fixtures.token}-MX`,
    birth_date: '1991-02-03'
  })

  return {
    id: response.body?.data?.id ?? null,
    status: response.status,
    message: response.message,
    acceptedAt: localDay()
  }
}

test('@U4 api-matrix · rol × endpoint', async ({ request }) => {
  test.setTimeout(420000)

  const fixtures = await ensureFixtures(request)
  const admin = await loginAs(request, 'administrador')
  const empty = patientBySlot(fixtures, 'p1')
  const grants = dumpGrants()
  const exportPolicy = dumpExportPolicy()
  const evidence = {
    session: 'U4',
    route: ROUTE,
    generatedAt: new Date().toISOString(),
    revision: revision(),
    base: BASE_URL,
    grantsSource: 'App\\Authorization\\Permissions::GRANTS via ReflectionClassConstant',
    policySource: 'App\\Policies\\PatientPolicy::export via instance call',
    grants,
    exportPolicy,
    roles: [...ROLES],
    rows: [],
    endpoints: [],
    targets: null,
    deleteTarget: null,
    cleanup: [],
    fixtures: fixtureSummary(fixtures),
    pageErrors: [],
    consoleErrors: [],
    failedRequests: { expected: [], unexpected: [] }
  }

  let deleteId = null

  try {
    if (!empty?.id) {
      const reason = 'P1 fixture unavailable; no real id to probe'
      evidence.reason = reason
      evidence.verdict = 'NO PROBADO'
      test.skip(true, reason)
      return
    }

    const created = await createDeleteTarget(request, admin, fixtures)
    deleteId = created.id
    evidence.deleteTarget = created

    const deleteIds = Object.fromEntries(ROLES.map(role => [role, created.id]))
    const targets = { patientId: empty.id, deleteIds }
    evidence.targets = {
      patientId: empty.id,
      deleteIds,
      note: 'reads use the empty fixture patient; writes use an invalid body; DELETE uses one throwaway row'
    }

    const endpoints = buildEndpoints(targets)
    evidence.endpoints = endpoints.map(entry => ({
      key: entry.key,
      method: entry.method,
      permission: entry.permission,
      mode: entry.mode,
      authorizedStatus: entry.authorizedStatus,
      target: entry.target,
      policy: entry.policy
    }))

    // The authorized role deletes last: the unauthorized roles must meet the
    // permission middleware, not a 404 from an already retired row.
    const roleOrder = [...ROLES.filter(role => role !== 'administrador'), 'administrador']

    for (const entry of endpoints) {
      if (!Object.hasOwn(grants, entry.permission)) {
        evidence.rows.push({
          endpoint: entry.key,
          method: entry.method,
          role: null,
          permission: entry.permission,
          expected: 'permission not in GRANTS',
          observed: null,
          ok: false,
          reason: 'the endpoint names a permission the catalog does not grant to anyone'
        })
        continue
      }

      for (const role of roleOrder) {
        const granted = (grants[entry.permission] ?? []).includes(role)
        const policyOk = entry.policy ? exportPolicy?.[role] === true : true
        const allowed = granted && policyOk
        const expected = allowed ? entry.authorizedStatus : 403
        const path = typeof entry.path === 'function' ? entry.path(role) : entry.path
        const skipped = entry.key === 'patients.destroy' && !deleteId

        if (skipped) {
          evidence.rows.push({
            endpoint: entry.key,
            method: entry.method,
            role,
            permission: entry.permission,
            expected,
            observed: null,
            ok: null,
            skipped: true,
            reason: 'no throwaway row available',
            target: null
          })
          continue
        }

        const body = entry.mode === 'write' ? entry.body : undefined
        const response = await call(request, await loginAs(request, role), entry.method, path, body)
        const isDelete = entry.key === 'patients.destroy'

        // The delete row is retired by its own authorized request; a second
        // probe against the same id would observe 404 instead of the gate.
        if (isDelete && response.status === 200) deleteId = null

        evidence.rows.push({
          endpoint: entry.key,
          method: entry.method,
          role,
          permission: entry.permission,
          granted,
          policy: entry.policy ? exportPolicy?.[role] === true : null,
          expected,
          observed: response.status,
          ok: response.status === expected,
          allowed,
          path,
          target: entry.target,
          message: response.message,
          ...(response.status === expected ? {} : { body: response.body })
        })
      }
    }

    const live = evidence.rows.filter(row => row.skipped !== true)
    evidence.summary = {
      rows: evidence.rows.length,
      live: live.length,
      ok: live.filter(row => row.ok === true).length,
      mismatches: live
        .filter(row => row.ok !== true)
        .map(row => ({
          endpoint: row.endpoint,
          role: row.role,
          expected: row.expected,
          observed: row.observed
        })),
      skipped: evidence.rows.filter(row => row.skipped === true).length
    }
    evidence.verdict = evidence.summary.mismatches.length === 0 ? 'OK' : 'DEFECTO'
  } finally {
    if (deleteId) {
      const response = await call(request, admin, 'DELETE', `/api/patients/${deleteId}`)
      evidence.cleanup = [{ id: deleteId, status: response.status, message: response.message }]
    }
    audit('api-matrix.json', evidence)
  }

  expect(evidence.rows.length, 'every endpoint probed for every role').toBe(
    evidence.endpoints.length * ROLES.length
  )
  expect(
    evidence.rows.filter(row => row.permission && !Object.hasOwn(grants, row.permission))
  ).toEqual([])
})
