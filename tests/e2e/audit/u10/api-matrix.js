// Plan #13 (issue #373, U10) — layer 5: the role × endpoint contract.
//
// `{endpoint, method, role, expected, observed, ok, note}` for the endpoints the
// three surfaces of the session consume: the seven reports and their export, the
// continuity queue and its two recall reads, the laboratory list and its detail,
// the eleven write doors, and the three lookups the pages fill their filters
// with.
//
// The expected status is derived from the permission the route declares, read
// from `App\Authorization\Permissions` through a read-only `php -r` call, never
// invented here: a role that holds the permission expects the door's own answer
// (200 for a read or an export, 422 for the empty-body validation refusal, 200
// for its own write, 404 for a missing target); a role that does not hold it
// expects 403 before validation. The one exception is a door whose `{id}` does
// not resolve: route-model binding runs before the permission middleware, so a
// denied role is answered 404 as well — those definitions declare both values and
// the row records which one was observed.
//
// The probes never consume a row a render or a walkthrough reads: the reads use
// the session fixtures, the cancel probe consumes the two pending recalls the
// fixtures created for exactly this layer, and every other write carries an empty
// body, which the door refuses with 422 before anything is written.

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
      source: 'App\\Authorization\\Permissions (GRANTS via rolesFor)'
    }
  } catch {
    return null
  }
}

const period = fixtures => `start_date=${fixtures.day}&end_date=${fixtures.day}`

const definitionsFor = fixtures => [
  // --- the seven Business Intelligence reads --------------------------------
  {
    endpoint: '/api/reports/dashboard',
    method: 'GET',
    path: () => `/api/reports/dashboard?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/appointments',
    method: 'GET',
    path: () => `/api/reports/appointments?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/patients',
    method: 'GET',
    path: () => `/api/reports/patients?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/professionals',
    method: 'GET',
    path: () => `/api/reports/professionals?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/revenue',
    method: 'GET',
    path: () => `/api/reports/revenue?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/utilization',
    method: 'GET',
    path: () => `/api/reports/utilization?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/reports/consolidated',
    method: 'GET',
    path: () => `/api/reports/consolidated?${period(fixtures)}`,
    permission: 'view.report',
    grant: [200],
    kind: 'read'
  },

  // --- the export door of the same surface ---------------------------------
  {
    endpoint: '/api/reports/{report}/export',
    method: 'GET',
    path: () => `/api/reports/revenue/export?format=csv&${period(fixtures)}`,
    permission: 'export.report',
    grant: [200],
    kind: 'export',
    note: 'la superficie ofrece Excel, CSV y PDF; la matriz mide el CSV'
  },

  // --- the continuity desk reads -------------------------------------------
  {
    endpoint: '/api/continuity/queue',
    method: 'GET',
    path: () => '/api/continuity/queue',
    permission: 'view.continuity',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/patient-recalls/metrics',
    method: 'GET',
    path: () => '/api/patient-recalls/metrics',
    permission: 'view.continuity',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/patient-recalls',
    method: 'GET',
    path: () =>
      `/api/patient-recalls?per_page=100&patient_id=${fixtures.lookups.patients.continuity.id}`,
    permission: 'view.continuity',
    grant: [200],
    kind: 'read'
  },

  // --- the laboratory desk reads -------------------------------------------
  {
    endpoint: '/api/lab-cases',
    method: 'GET',
    path: () => '/api/lab-cases?per_page=100',
    permission: 'view.lab_case',
    grant: [200],
    kind: 'read'
  },
  {
    endpoint: '/api/lab-cases/{id}',
    method: 'GET',
    path: () => `/api/lab-cases/${fixtures.lab.cases.A.id}`,
    permission: 'view.lab_case',
    grant: [200],
    kind: 'read'
  },

  // --- the lookups the pages fill their filters with -----------------------
  {
    endpoint: '/api/users/active',
    method: 'GET',
    path: () => '/api/users/active',
    permission: 'view.user',
    grant: [200],
    kind: 'lookup'
  },
  {
    endpoint: '/api/dental-chairs/active',
    method: 'GET',
    path: () => '/api/dental-chairs/active',
    permission: 'view.dental_chair',
    grant: [200],
    kind: 'lookup'
  },
  {
    endpoint: '/api/branches/active',
    method: 'GET',
    path: () => '/api/branches/active',
    permission: 'view.branch',
    grant: [200],
    kind: 'lookup'
  },

  // --- the continuity writes ----------------------------------------------
  {
    endpoint: '/api/patient-recalls',
    method: 'POST',
    path: () => '/api/patient-recalls',
    body: () => ({}),
    permission: 'manage.continuity',
    grant: [422],
    kind: 'write-gate',
    note: 'cuerpo vacío: la puerta de alta responde 422 a quien la tiene'
  },
  {
    endpoint: '/api/patient-recalls/{id}/schedule',
    method: 'POST',
    path: () => `/api/patient-recalls/${fixtures.continuity.recall.id}/schedule`,
    body: () => ({}),
    permission: 'manage.continuity',
    grant: [422],
    kind: 'write-gate',
    note: 'cuerpo vacío: la validación de la agenda responde 422 y el recall sigue pendiente'
  },
  {
    endpoint: '/api/patient-recalls/{id}/cancel',
    method: 'POST',
    path: (data, context) => {
      const granted = (context.grants['manage.continuity'] ?? []).includes(context.role)
      const target = granted
        ? data.matrix.recalls?.[context.role]?.id
        : (data.matrix.recalls?.recepcionista?.id ?? data.continuity.recall?.id)

      return `/api/patient-recalls/${target ?? MISSING_ID}/cancel`
    },
    permission: 'manage.continuity',
    grant: [200],
    deny: [403, 404],
    order: 'deniedFirst',
    kind: 'write-gate',
    note: 'cada rol con el permiso consume su propio recall de la matriz; los demás se miden sobre una fila todavía pendiente'
  },
  {
    endpoint: '/api/continuity-requests/{id}/schedule',
    method: 'POST',
    path: () => `/api/continuity-requests/${fixtures.continuity.request.id}/schedule`,
    body: () => ({}),
    permission: 'manage.continuity',
    grant: [422],
    kind: 'write-gate',
    note: 'cuerpo vacío: la validación responde 422 y la solicitud sigue pendiente'
  },
  {
    endpoint: '/api/continuity-requests/{id}/dismiss',
    method: 'POST',
    path: () => `/api/continuity-requests/${MISSING_ID}/dismiss`,
    body: () => ({ reason: 'AUDIT-U10 descarte de la matriz' }),
    permission: 'manage.continuity',
    grant: [404],
    deny: [403, 404],
    kind: 'missing-target',
    note: 'id inexistente: el binding resuelve antes del permiso, así que un rol denegado también recibe 404'
  },

  // --- the laboratory writes ----------------------------------------------
  {
    endpoint: '/api/lab-cases',
    method: 'POST',
    path: () => '/api/lab-cases',
    body: () => ({}),
    permission: 'manage.lab_case',
    grant: [422],
    kind: 'write-gate'
  },
  {
    endpoint: '/api/lab-cases/{id}',
    method: 'PUT',
    path: () => `/api/lab-cases/${fixtures.lab.cases.A.id}`,
    body: () => ({}),
    permission: 'manage.lab_case',
    grant: [200],
    kind: 'write-gate',
    note: 'cuerpo vacío: la edición no cambia ningún campo y la orden conserva su fase'
  },
  {
    endpoint: '/api/lab-cases/{id}/phase',
    method: 'POST',
    path: () => `/api/lab-cases/${fixtures.lab.cases.A.id}/phase`,
    body: () => ({}),
    permission: 'manage.lab_case',
    grant: [422],
    kind: 'write-gate',
    note: 'cuerpo vacío: la puerta de fase responde 422 y la orden conserva su fase'
  }
]

const expectedFor = (definition, role, grants) => {
  if (!definition.permission) {
    return {
      statuses: [...definition.grant],
      why: 'la ruta no declara permiso'
    }
  }

  const allowed = (grants[definition.permission] ?? []).includes(role)

  if (!allowed) {
    return {
      statuses: [...(definition.deny ?? [403])],
      why: `${(definition.deny ?? [403]).join(' o ')}: el rol no tiene ${definition.permission}`
    }
  }

  return {
    statuses: [...definition.grant],
    why: `autorizado por ${definition.permission}: ${definition.grant.join('/')}`
  }
}

const resolve = (value, fixtures, context) =>
  typeof value === 'function' ? value(fixtures, context) : value

const orderedRoles = (definition, roleHasPermission) => {
  if (definition.order !== 'deniedFirst') return ROLES

  const denied = ROLES.filter(role => !roleHasPermission(role))
  const granted = ROLES.filter(role => roleHasPermission(role))

  return [...denied, ...granted]
}

test('@U10 · capa 5 — contrato por rol de los endpoints de negocio, continuidad y laboratorio', async () => {
  test.setTimeout(300000)

  const grants = readGrants()

  if (!grants) test.skip(true, 'the permission catalogue could not be read with php -r')

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const sessions = await roleSessions()
  const definitions = definitionsFor(fixtures)
  const rows = []
  const roleHasPermission = (definition, role) =>
    !definition.permission || (grants.grants[definition.permission] ?? []).includes(role)

  for (const definition of definitions) {
    // A probe that consumes the row it targets runs the denied roles first and
    // the granted ones last: the row still exists when each denial is measured.
    const ordered = orderedRoles(definition, role => roleHasPermission(definition, role))

    for (const role of ordered) {
      const session = sessions[role]
      const expectation = expectedFor(definition, role, grants.grants)
      const context = { role, grants: grants.grants }
      const path = resolve(definition.path, fixtures, context)
      const body = definition.body ? resolve(definition.body, fixtures, context) : undefined
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
  const finance = sessions.finanzas
  const revenue = await api(admin, 'GET', `/api/reports/revenue?${period(fixtures)}`)
  const revenueSummary = revenue.body?.meta?.summary ?? null
  const revenueRows = revenue.body?.data ?? []
  const branchCut = revenueRows.filter(row => row.type === 'Por Sede')
  const drawerBranch = fixtures.lookups.branch?.name ?? null
  const labDetail = await api(admin, 'GET', `/api/lab-cases/${fixtures.lab.cases.D.id}`)
  const labOverdue = await api(admin, 'GET', '/api/lab-cases?per_page=100&overdue=1')
  const queue = await api(admin, 'GET', '/api/continuity/queue')
  const metrics = await api(admin, 'GET', '/api/patient-recalls/metrics')
  const backwards = await api(admin, 'POST', `/api/lab-cases/${fixtures.lab.cases.D.id}/phase`, {
    phase: 'in_preparation'
  })
  const backwardsMessage = backwards.body?.errors?.phase?.[0] ?? backwards.body?.message ?? 'null'
  const missingDenied = await api(finance, 'PUT', `/api/lab-cases/${MISSING_ID}`, {})
  const afterBackwards = await api(admin, 'GET', `/api/lab-cases/${fixtures.lab.cases.D.id}`)

  const metricsPayload = metrics.body?.data ?? {}
  const metricsKeys = [
    'total',
    'pending',
    'scheduled',
    'completed',
    'cancelled',
    'overdue',
    'compliance_rate'
  ]

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
      id: 'revenue-shows-fixture-money',
      expected:
        'el fixture deposita 200.00 (tarjeta + transferencia) y devuelve 30.00: neto 170.00',
      observed: `net=${revenueSummary?.total_revenue ?? 'null'} refunds=${revenueSummary?.refunds ?? 'null'}`,
      ok:
        Number(revenueSummary?.total_revenue ?? 0) >= 170 &&
        Number(revenueSummary?.refunds ?? 0) >= 30
    },
    {
      id: 'revenue-cut-por-sede-names-the-drawer-branch',
      expected: `el corte «Por Sede» nombra la sede del cajón (${drawerBranch})`,
      observed: `rows=${branchCut.length} categories=${JSON.stringify(
        branchCut.map(row => row.category)
      )}`,
      ok: branchCut.some(row => row.category === drawerBranch)
    },
    {
      id: 'lab-placed-case',
      expected: 'el caso D quedó en placed con sus dos sellos',
      observed: `${labDetail.body?.data?.status ?? 'null'} delivered_at=${
        labDetail.body?.data?.delivered_at ?? 'null'
      } placed_at=${labDetail.body?.data?.placed_at ?? 'null'}`,
      ok:
        labDetail.body?.data?.status === 'placed' &&
        Boolean(labDetail.body?.data?.delivered_at) &&
        Boolean(labDetail.body?.data?.placed_at)
    },
    {
      id: 'lab-overdue-filter-finds-the-late-case',
      expected: 'GET /api/lab-cases?overdue=1 lista el caso B y no el caso A',
      observed: `ids=${JSON.stringify((labOverdue.body?.data ?? []).map(row => row.id))}`,
      ok:
        (labOverdue.body?.data ?? []).some(row => row.id === fixtures.lab.cases.B.id) &&
        !(labOverdue.body?.data ?? []).some(row => row.id === fixtures.lab.cases.A.id)
    },
    {
      id: 'lab-phase-machine-refuses-backwards',
      expected: 'una fase anterior responde 422 nombrando la fase actual y la pedida',
      observed: `HTTP ${backwards.status}: ${backwardsMessage}`,
      ok:
        backwards.status === 422 &&
        /in_preparation/.test(backwardsMessage) &&
        /placed/.test(backwardsMessage)
    },
    {
      id: 'lab-phase-machine-keeps-the-row-after-a-refusal',
      expected: 'la negativa no reescribe la fase',
      observed: `status=${afterBackwards.body?.data?.status ?? 'null'}`,
      ok: afterBackwards.body?.data?.status === 'placed'
    },
    {
      id: 'continuity-counts-add-up',
      expected: 'counts.total == recalls.length + requests.length sobre la cola publicada',
      observed: `counts=${JSON.stringify(queue.body?.data?.counts ?? null)}`,
      ok:
        Number(queue.body?.data?.counts?.total ?? -1) ===
        (queue.body?.data?.recalls ?? []).length + (queue.body?.data?.requests ?? []).length
    },
    {
      id: 'recall-metrics-shape',
      expected: `el payload de métricas publica ${metricsKeys.join(', ')}`,
      observed: JSON.stringify(metricsPayload),
      ok: metricsKeys.every(key => Object.prototype.hasOwnProperty.call(metricsPayload, key))
    },
    {
      id: 'denied-role-binding-order',
      expected:
        'un id inexistente responde 404 a un rol denegado (el binding corre antes del permiso)',
      observed: `finanzas PUT /api/lab-cases/${MISSING_ID} → HTTP ${missingDenied.status}`,
      ok: missingDenied.status === 404
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
    ),
    byKind: Object.fromEntries(
      [...new Set(rows.map(row => row.kind))].map(kind => [
        kind,
        {
          rows: rows.filter(row => row.kind === kind).length,
          ok: rows.filter(row => row.kind === kind && row.ok).length
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

  expect(summary.endpoints, 'endpoints in the matrix').toBeGreaterThanOrEqual(23)
  expect(summary.rows, 'matrix rows').toBeGreaterThan(0)
  expect(
    summary.mismatches.map(row => `${row.role} ${row.method} ${row.endpoint} → ${row.observed}`),
    'rows whose observed status left its expectation'
  ).toEqual([])
})

test('@U10 · capa 5 — veredicto de la matriz de roles', async () => {
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
    byKind: evidence.summary?.byKind ?? {},
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
