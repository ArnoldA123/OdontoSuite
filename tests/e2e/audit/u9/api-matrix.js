// Plan #13 (issue #372, U9) — layer 5: the role × endpoint contract.
//
// `{endpoint, method, role, expected, observed, ok, note}` for the 24 endpoints
// the layer-1 manifest declares — plus the write doors each surface owns and the
// doors that must not exist (the specialty catalogue has no DELETE: 405).
//
// The expected status is derived from the permission the route declares, read
// from `App\Authorization\Permissions` through a read-only `php -r` call, never
// invented here: a role that holds the permission expects the door's own answer
// (200 for a read, 200 for its own write, 422 for a validation refusal, 404 for
// a missing target); a role that does not hold it expects 403 before validation.
// The login payload (`user.permissions`) is cross-checked against that source.
//
// The probes never consume a row a render or a walkthrough reads: reads use the
// session fixtures, destructive writes use the matrix rows the fixtures created
// for exactly this layer, and the two decision doors consume the matrix requests.

import { execFileSync } from 'node:child_process'

import { test, expect } from 'playwright/test'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import { ROLES, api, audit, readEvidence, roleSessions, stamp } from './helpers.js'

const MISSING_ID = 999999999

const PHP_GRANTS = [
  'require "vendor/autoload.php";',
  '$out = [];',
  'foreach (App\\Authorization\\Permissions::all() as $permission) {',
  '    $out[$permission] = App\\Authorization\\Permissions::rolesFor($permission);',
  '}',
  'echo json_encode(["roles" => App\\Authorization\\Permissions::ROLES, "grants" => $out]);'
].join('\n')

/** Reads the role matrix from the one source of truth; null when PHP is absent. */
const readGrants = () => {
  try {
    const stdout = execFileSync('php', ['-r', PHP_GRANTS], {
      cwd: process.cwd(),
      encoding: 'utf8'
    })
    const parsed = JSON.parse(stdout)

    return {
      roles: parsed.roles ?? ROLES,
      grants: parsed.grants ?? {},
      source: 'App\\Authorization\\Permissions (GRANTS via forRole/rolesFor)'
    }
  } catch {
    return null
  }
}

const definitionsFor = fixtures => [
  // --- accounting lock ------------------------------------------------------
  {
    endpoint: '/api/accounting-locks',
    method: 'GET',
    path: () => '/api/accounting-locks',
    permission: 'manage.accounting_lock',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/accounting-locks/{module}',
    method: 'PUT',
    path: () => '/api/accounting-locks/quotations',
    body: () => ({ locked_through: fixtures.day }),
    permission: 'manage.accounting_lock',
    grant: [200],
    kind: 'write-gate',
    note: 'fija el bloqueo con la fecha efectiva del día'
  },
  {
    endpoint: '/api/accounting-locks/{module}',
    method: 'DELETE',
    path: () => '/api/accounting-locks/quotations',
    permission: 'manage.accounting_lock',
    grant: [200],
    kind: 'write-gate',
    note: 'retira el bloqueo que la fila anterior fijó'
  },

  // --- branch transfers -----------------------------------------------------
  {
    endpoint: '/api/branch-transfers',
    method: 'GET',
    path: () => '/api/branch-transfers?per_page=100',
    permission: 'request.branch_transfer',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/branch-transfers/destinations',
    method: 'GET',
    path: () => '/api/branch-transfers/destinations',
    permission: 'request.branch_transfer',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/branch-transfers',
    method: 'POST',
    path: () => '/api/branch-transfers',
    body: () => ({}),
    permission: 'request.branch_transfer',
    grant: [422],
    kind: 'write-gate',
    note: 'cuerpo vacío: la puerta de solicitud responde 422 a quien la tiene'
  },
  {
    endpoint: '/api/branch-transfers/{id}/cancel',
    method: 'POST',
    path: () => `/api/branch-transfers/${fixtures.transfers.cancel.id}/cancel`,
    permission: 'request.branch_transfer',
    grant: [403],
    kind: 'ownership-gate',
    note: '403 para todos: solo el solicitante cancela su solicitud'
  },
  {
    endpoint: '/api/branch-transfers/{id}/approve',
    method: 'POST',
    path: () => `/api/branch-transfers/${fixtures.transfers.matrixApprove.id}/approve`,
    body: () => ({ decision_notes: 'AUDIT-U9 aprobación de la matriz' }),
    permission: 'decide.branch_transfer',
    grant: [200],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/branch-transfers/{id}/reject',
    method: 'POST',
    path: () => `/api/branch-transfers/${fixtures.transfers.matrixReject.id}/reject`,
    body: () => ({ decision_notes: 'AUDIT-U9 rechazo de la matriz' }),
    permission: 'decide.branch_transfer',
    grant: [200],
    kind: 'write-gate'
  },

  // --- branches -------------------------------------------------------------
  {
    endpoint: '/api/branches',
    method: 'GET',
    path: () => '/api/branches',
    permission: 'manage.branch',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/branches/active',
    method: 'GET',
    path: () => '/api/branches/active',
    permission: 'view.branch',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/branches/{id}',
    method: 'GET',
    path: () => `/api/branches/${fixtures.branches.target.id}`,
    permission: 'manage.branch',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/branches',
    method: 'POST',
    path: () => '/api/branches',
    body: () => ({}),
    permission: 'manage.branch',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/branches/{id}',
    method: 'PUT',
    path: () => `/api/branches/${fixtures.matrix.branch.id}`,
    body: () => ({
      name: `${fixtures.marker} ${fixtures.token} sede de la matriz (PUT)`,
      city: 'Lima'
    }),
    permission: 'manage.branch',
    grant: [200],
    kind: 'write-gate',
    note: 'la superficie desactiva; la edición existe en la API'
  },
  {
    endpoint: '/api/branches/{id}',
    method: 'DELETE',
    path: () => `/api/branches/${fixtures.matrix.branch.id}`,
    permission: 'manage.branch',
    grant: [200],
    kind: 'write-gate',
    note: 'la API permite borrar una sede sin referencias; la superficie no lo ofrece'
  },

  // --- dental chairs --------------------------------------------------------
  {
    endpoint: '/api/dental-chairs',
    method: 'GET',
    path: () => '/api/dental-chairs?archived=include',
    permission: 'manage.dental_chair',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/dental-chairs/search',
    method: 'GET',
    path: () => '/api/dental-chairs/search?q=AUDIT-U9',
    permission: 'manage.dental_chair',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/dental-chairs/{id}',
    method: 'GET',
    path: () => `/api/dental-chairs/${fixtures.chairs.detail.id}`,
    permission: 'manage.dental_chair',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/dental-chairs',
    method: 'POST',
    path: () => '/api/dental-chairs',
    body: () => ({}),
    permission: 'manage.dental_chair',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/dental-chairs/{id}',
    method: 'PUT',
    path: () => `/api/dental-chairs/${fixtures.matrix.chairs.matrixPut.id}`,
    body: () => ({ is_active: false }),
    permission: 'manage.dental_chair',
    grant: [200],
    kind: 'write-gate',
    note: 'archivar es un PUT is_active=false'
  },
  {
    endpoint: '/api/dental-chairs/{id}',
    method: 'DELETE',
    path: () => `/api/dental-chairs/${fixtures.matrix.chairs.matrixDelete.id}`,
    permission: 'manage.dental_chair',
    grant: [200],
    kind: 'write-gate'
  },

  // --- appointment types ----------------------------------------------------
  {
    endpoint: '/api/appointment-types',
    method: 'GET',
    path: () => '/api/appointment-types',
    permission: 'manage.appointment_type',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/appointment-types/{id}',
    method: 'GET',
    path: () => `/api/appointment-types/${fixtures.appointmentTypes.detail.id}`,
    permission: 'manage.appointment_type',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/appointment-types',
    method: 'POST',
    path: () => '/api/appointment-types',
    body: () => ({}),
    permission: 'manage.appointment_type',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/appointment-types/{id}',
    method: 'PUT',
    path: () => `/api/appointment-types/${fixtures.matrix.appointmentTypes.matrixPut.id}`,
    body: () => ({ duration_minutes: 33 }),
    permission: 'manage.appointment_type',
    grant: [200],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/appointment-types/{id}',
    method: 'DELETE',
    path: () => `/api/appointment-types/${fixtures.matrix.appointmentTypes.matrixDelete.id}`,
    permission: 'manage.appointment_type',
    grant: [200],
    kind: 'write-gate'
  },

  // --- specialties ----------------------------------------------------------
  {
    endpoint: '/api/specialties',
    method: 'GET',
    path: () => '/api/specialties',
    permission: 'view.specialty',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/specialties/{id}',
    method: 'GET',
    path: () => `/api/specialties/${fixtures.specialties.fixture.id}`,
    permission: 'view.specialty',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/specialties',
    method: 'POST',
    path: () => '/api/specialties',
    body: () => ({}),
    permission: 'manage.specialty',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/specialties/{id}',
    method: 'PUT',
    path: () => `/api/specialties/${fixtures.specialties.fixture.id}`,
    body: () => ({ code: `U9-CHANGED-${fixtures.token}` }),
    permission: 'manage.specialty',
    grant: [200],
    kind: 'write-gate',
    note: 'el código es inmutable: PUT lo ignora y responde 200'
  },
  {
    endpoint: '/api/specialties/{id}',
    method: 'DELETE',
    path: () => `/api/specialties/${fixtures.specialties.fixture.id}`,
    permission: null,
    grant: [405],
    kind: 'absent-door',
    note: 'la ruta no existe: 405 para todos los roles'
  },

  // --- payment methods ------------------------------------------------------
  {
    endpoint: '/api/payment-methods',
    method: 'GET',
    path: () => '/api/payment-methods',
    permission: 'manage.payment_method',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/payment-methods/{id}',
    method: 'GET',
    path: () => `/api/payment-methods/${fixtures.paymentMethods.fixture.id}`,
    permission: 'manage.payment_method',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/payment-methods',
    method: 'POST',
    path: () => '/api/payment-methods',
    body: () => ({}),
    permission: 'manage.payment_method',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/payment-methods/{id}',
    method: 'PUT',
    path: () => `/api/payment-methods/${fixtures.matrix.paymentMethods.matrixPut.id}`,
    body: () => ({ name: `${fixtures.marker} ${fixtures.token} método matrixPut (PUT)` }),
    permission: 'manage.payment_method',
    grant: [200],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/payment-methods/{id}',
    method: 'DELETE',
    path: () => `/api/payment-methods/${fixtures.matrix.paymentMethods.matrixDelete.id}`,
    permission: 'manage.payment_method',
    grant: [200],
    kind: 'write-gate',
    note: 'un método propio sin transacciones se elimina'
  },

  // --- users ----------------------------------------------------------------
  {
    endpoint: '/api/users',
    method: 'GET',
    path: () => '/api/users',
    permission: 'manage.user',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/users/{id}',
    method: 'GET',
    path: () => `/api/users/${fixtures.professionals.plain.id}`,
    permission: 'manage.user',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/users',
    method: 'POST',
    path: () => '/api/users',
    body: () => ({}),
    permission: 'manage.user',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/users/{id}',
    method: 'PUT',
    path: () => `/api/users/${fixtures.matrix.user.id}`,
    body: () => ({ name: `${fixtures.marker} ${fixtures.token} cuenta de la matriz (PUT)` }),
    permission: 'manage.user',
    grant: [200],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/users/{id}',
    method: 'DELETE',
    path: () => `/api/users/${fixtures.matrix.user.id}`,
    permission: 'manage.user',
    grant: [200],
    kind: 'write-gate',
    note: 'la cuenta no tiene historia clínica ni de cobro'
  },

  // --- audit history (detail surfaces) -------------------------------------
  {
    endpoint: '/api/audit-logs',
    method: 'GET',
    path: () => '/api/audit-logs',
    permission: 'view.audit_log',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/audit-logs/{id}',
    method: 'GET',
    path: () => `/api/audit-logs/${MISSING_ID}`,
    permission: 'view.audit_log',
    grant: [404],
    kind: 'missing-target'
  },
  {
    endpoint: '/api/audit-logs/patient/{id}',
    method: 'GET',
    path: () => `/api/audit-logs/patient/${MISSING_ID}`,
    permission: 'view.audit_log',
    grant: [404],
    kind: 'missing-target'
  },
  {
    endpoint: '/api/audit-logs/user/{id}',
    method: 'GET',
    path: () => `/api/audit-logs/user/${fixtures.professionals.plain.id}`,
    permission: 'view.audit_log',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/audit-logs/dental-chair/{id}',
    method: 'GET',
    path: () => `/api/audit-logs/dental-chair/${fixtures.chairs.detail.id}`,
    permission: 'view.audit_log',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/audit-logs/appointment-type/{id}',
    method: 'GET',
    path: () => `/api/audit-logs/appointment-type/${fixtures.appointmentTypes.detail.id}`,
    permission: 'view.audit_log',
    grant: [200],
    kind: 'read'
  }
]

const expectedFor = (definition, role, grants) => {
  if (!definition.permission) {
    return {
      statuses: [...definition.grant],
      why: 'la ruta no declara permiso porque no existe: 405 antes de autorizar'
    }
  }

  const allowed = (grants[definition.permission] ?? []).includes(role)

  if (!allowed) {
    return { statuses: [403], why: `403: el rol no tiene ${definition.permission}` }
  }

  return {
    statuses: [...definition.grant],
    why: `autorizado por ${definition.permission}: ${definition.grant.join('/')}`
  }
}

const resolve = (value, fixtures) => (typeof value === 'function' ? value(fixtures) : value)

test('@U9 · capa 5 — contrato por rol de los endpoints de administración', async () => {
  test.setTimeout(300000)

  const grants = readGrants()

  if (!grants) test.skip(true, 'the permission catalogue could not be read with php -r')

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const sessions = await roleSessions()
  const definitions = definitionsFor(fixtures)
  const rows = []

  for (const definition of definitions) {
    // A destructive probe removes the row it targets, so the denied roles run
    // first and the one role that holds the permission runs last: the row still
    // exists when each denial is measured.
    const ordered = definition.method === 'DELETE' ? [...ROLES].reverse() : ROLES

    for (const role of ordered) {
      const session = sessions[role]
      const expectation = expectedFor(definition, role, grants.grants)
      const path = resolve(definition.path, fixtures)
      const body = definition.body ? resolve(definition.body, fixtures) : undefined
      const response = await api(session, definition.method, path, body)
      const observed = response.status

      rows.push({
        endpoint: definition.endpoint,
        method: definition.method,
        role,
        expected: expectation.statuses,
        observed,
        ok: expectation.statuses.includes(observed),
        expectedWhy: expectation.why,
        permission: definition.permission,
        roleHasPermission: definition.permission
          ? (grants.grants[definition.permission] ?? []).includes(role)
          : false,
        kind: definition.kind,
        message: response.body?.message ?? null,
        errors: response.body?.errors ? Object.keys(response.body.errors) : null,
        path,
        note: definition.note ?? null
      })
    }
  }

  // --- content facts the statuses cannot show -----------------------------
  const admin = sessions.administrador
  const specialty = await api(admin, 'GET', `/api/specialties/${fixtures.specialties.fixture.id}`)
  const methods = await api(admin, 'GET', '/api/payment-methods')
  const methodRows = methods.body?.data ?? []
  const fixtureMethod = methodRows.find(row => row.id === fixtures.paymentMethods.fixture.id)
  const approvals = await api(admin, 'GET', '/api/branch-transfers?per_page=100')
  const approvalRows = approvals.body?.data ?? []
  const approvedRow = approvalRows.find(row => row.id === fixtures.transfers.matrixApprove.id)
  const rejectedRow = approvalRows.find(row => row.id === fixtures.transfers.matrixReject.id)

  const permissionAgreement = Object.fromEntries(
    ROLES.map(role => {
      const fromPayload = new Set(sessions[role].user.permissions ?? [])
      const fromSource = Object.entries(grants.grants)
        .filter(([, roles]) => roles.includes(role))
        .map(([permission]) => permission)

      return [
        role,
        {
          payload: fromPayload.size,
          source: fromSource.length,
          missingInPayload: fromSource.filter(permission => !fromPayload.has(permission)),
          extraInPayload: [...fromPayload].filter(permission => !fromSource.includes(permission))
        }
      ]
    })
  )

  const contentFacts = [
    {
      id: 'specialty-code-immutable',
      expected: 'PUT con otro code responde 200 y el code no cambia',
      observed: `code=${specialty.body?.data?.code ?? 'null'}`,
      ok: specialty.body?.data?.code === fixtures.specialties.fixture.code
    },
    {
      id: 'payment-method-transactions-count',
      expected: 'la lista publica transactions_count del método propio',
      observed: `transactions_count=${fixtureMethod?.transactions_count ?? 'null'} is_system=${fixtureMethod?.is_system ?? 'null'}`,
      ok: fixtureMethod?.transactions_count === 0 && fixtureMethod?.is_system === false
    },
    {
      id: 'system-payment-methods-are-flagged',
      expected: 'los métodos del sistema se publican con is_system=true',
      observed: `${methodRows.filter(row => row.is_system === true).length} método(s) del sistema`,
      ok: methodRows.some(row => row.is_system === true)
    },
    {
      id: 'transfer-approve-consumed',
      expected: 'la solicitud de la matriz queda aprobada',
      observed: approvedRow?.status ?? 'missing',
      ok: approvedRow?.status === 'approved'
    },
    {
      id: 'transfer-reject-consumed',
      expected: 'la solicitud de la matriz queda rechazada',
      observed: rejectedRow?.status ?? 'missing',
      ok: rejectedRow?.status === 'rejected'
    },
    {
      id: 'self-decision-refused',
      expected: 'decidir la propia solicitud responde 403 (medido en las fixtures)',
      observed: `${fixtures.transfers.selfDecision?.approve?.status ?? 'null'}`,
      ok: fixtures.transfers.selfDecision?.approve?.status === 403
    },
    {
      id: 'self-cancel-allowed',
      expected: 'el solicitante cancela su propia solicitud (200)',
      observed: `${fixtures.transfers.selfDecision?.cancel?.status ?? 'null'} · ${fixtures.transfers.selfDecision?.finalStatus ?? 'null'}`,
      ok:
        fixtures.transfers.selfDecision?.cancel?.status === 200 &&
        fixtures.transfers.selfDecision?.finalStatus === 'cancelled'
    },
    {
      id: 'one-open-request-per-user',
      expected: 'una segunda solicitud abierta responde 409',
      observed: `${fixtures.transfers.secondOpen?.status ?? 'null'}`,
      ok: fixtures.transfers.secondOpen?.status === 409
    },
    {
      id: 'login-permissions-agree-with-source',
      expected: 'user.permissions coincide con el catálogo de Permissions.php',
      observed: Object.entries(permissionAgreement)
        .map(
          ([role, entry]) =>
            `${role}:${entry.missingInPayload.length + entry.extraInPayload.length}`
        )
        .join(' '),
      ok: Object.values(permissionAgreement).every(
        entry => entry.missingInPayload.length === 0 && entry.extraInPayload.length === 0
      ),
      detail: permissionAgreement
    }
  ]

  const summary = {
    rows: rows.length,
    endpoints: [...new Set(rows.map(row => `${row.method} ${row.endpoint}`))].length,
    ok: rows.filter(row => row.ok).length,
    mismatches: rows.filter(row => !row.ok),
    contentFacts,
    contentFactsOk: contentFacts.filter(fact => fact.ok).length,
    permissions: {
      source: grants.source,
      roles: grants.roles,
      agreement: permissionAgreement
    },
    byRole: Object.fromEntries(
      ROLES.map(role => [
        role,
        {
          rows: rows.filter(row => row.role === role).length,
          ok: rows.filter(row => row.role === role && row.ok).length,
          denials: rows.filter(row => row.role === role && row.observed === 403).length
        }
      ])
    )
  }

  audit('api-matrix', {
    stage: 'layer-5',
    expectedSource: grants.source,
    matrix: ROLES,
    totals: {
      rows: summary.rows,
      endpoints: summary.endpoints,
      ok: summary.ok,
      mismatches: summary.mismatches.length,
      contentFacts: `${summary.contentFactsOk}/${contentFacts.length}`
    },
    summary,
    rows,
    fixtures: fixtureSummary(fixtures)
  })

  expect(summary.endpoints, 'endpoints in the matrix').toBeGreaterThanOrEqual(24)
  expect(summary.rows, 'matrix rows').toBeGreaterThan(0)
  expect(
    summary.mismatches.map(row => `${row.role} ${row.method} ${row.endpoint} → ${row.observed}`),
    'rows whose observed status left its expectation'
  ).toEqual([])
})

test('@U9 · capa 5 — veredicto de la matriz de roles', async () => {
  const evidence = readEvidence('api-matrix.json')

  if (!evidence) test.skip(true, 'the matrix did not run in this scope')

  const rows = evidence.rows ?? []
  const mismatches = rows.filter(row => !row.ok)
  const summary = {
    ...stamp(),
    stage: 'layer-5-summary',
    rows: rows.length,
    ok: rows.filter(row => row.ok).length,
    mismatches: mismatches.map(row => ({
      role: row.role,
      method: row.method,
      endpoint: row.endpoint,
      expected: row.expected,
      observed: row.observed
    })),
    byRole: evidence.summary?.byRole ?? {},
    contentFacts: (evidence.summary?.contentFacts ?? []).map(fact => ({
      id: fact.id,
      ok: fact.ok,
      observed: fact.observed
    }))
  }

  audit('api-matrix-summary', summary)

  expect(mismatches, 'every matrix row agrees with its expectation').toEqual([])
  expect(summary.rows, 'matrix rows measured').toBeGreaterThan(0)
})
