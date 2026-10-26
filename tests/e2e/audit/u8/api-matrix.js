// Plan #13 (issue #371, U8) — layer 5: the role × endpoint contract.
//
// `{endpoint, method, role, expected, observed, ok}` for the 22 endpoints the U8
// manifest declares, plus the write doors #371 names as forbidden for the roles
// that must not have them (catalogue POST/PUT/DELETE, favourites
// POST/DELETE/reorder, branch price PUT/DELETE and the CSV importer).
//
// The expectation is derived from the permission the route declares, read from
// the catalogue the API publishes at login (`user.permissions`, built by
// `App\Authorization\Permissions`): a role that holds it expects the door's own
// answer (200 for a read, 201/200 for its own write, 422 for a validation
// refusal, 404 for a missing target); a role that does not hold it expects 403
// before validation.
//
// The probes never consume the session's fixtures: writes send an incomplete
// body on purpose, deletes name an id that does not exist, and the favourite
// write/delete pair uses the dedicated `matrix` procedure so the walkthrough's
// favourites are untouched.

import { test, expect } from 'playwright/test'

import { loginAs } from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import { audit, call, stamp } from './helpers.js'

const ROLES = Object.freeze(['administrador', 'finanzas', 'odontologo', 'recepcionista'])

const MISSING_ID = 999999999

const definitionsFor = fixtures => {
  const { surface } = fixtures.procedures
  const matrixProcedure = fixtures.procedures.matrix
  const branchId = fixtures.branch.id
  const auditUser = fixtures.sessions.administrador.id

  return [
    // --- the catalogue list and its doors ---------------------------------
    {
      endpoint: '/api/procedure-catalog',
      method: 'GET',
      path: '/api/procedure-catalog?per_page=1',
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/summary',
      method: 'GET',
      path: '/api/procedure-catalog/summary',
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/{id}',
      method: 'GET',
      path: `/api/procedure-catalog/${surface.id}`,
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/{id}/versions',
      method: 'GET',
      path: `/api/procedure-catalog/${surface.id}/versions`,
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/search',
      method: 'GET',
      path: `/api/procedure-catalog/search?q=${encodeURIComponent(surface.code)}&limit=20`,
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/for-me',
      method: 'GET',
      path: '/api/procedure-catalog/for-me?per_page=5',
      permission: 'view.procedure_catalog',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog',
      method: 'POST',
      path: '/api/procedure-catalog',
      body: {},
      permission: 'manage.procedure_catalog',
      expect: { grant: [422], deny: [403] },
      kind: 'write-gate',
      note: 'cuerpo vacío: la puerta de escritura del catálogo'
    },
    {
      endpoint: '/api/procedure-catalog/{id}',
      method: 'PUT',
      path: `/api/procedure-catalog/${surface.id}`,
      body: { default_cost: -1 },
      permission: 'manage.procedure_catalog',
      expect: { grant: [422], deny: [403] },
      kind: 'write-gate',
      note: 'un importe negativo: el rol autorizado no escribe, el resto recibe 403'
    },
    {
      endpoint: '/api/procedure-catalog/{id}',
      method: 'DELETE',
      path: `/api/procedure-catalog/${MISSING_ID}`,
      permission: 'manage.procedure_catalog',
      expect: { grant: [404], deny: [403] },
      kind: 'write-gate',
      note: 'id inexistente: el rol autorizado llega a la puerta, el resto no'
    },
    {
      endpoint: '/api/admin/procedure-catalog/import',
      method: 'POST',
      path: '/api/admin/procedure-catalog/import',
      body: {},
      permission: 'import.procedure_catalog',
      expect: { grant: [422], deny: [403] },
      kind: 'write-gate',
      note: 'sin archivo: la importación es de finanzas y administración'
    },
    {
      endpoint: '/api/specialties',
      method: 'GET',
      path: '/api/specialties',
      permission: 'view.specialty',
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

    // --- the detail: audit history, branches and the branch tariff ---------
    {
      endpoint: '/api/audit-logs',
      method: 'GET',
      path: `/api/audit-logs?model_type=${encodeURIComponent('App\\Models\\ProcedureCatalog')}&auditable_id=${surface.id}`,
      permission: 'view.audit_log',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/audit-logs/{id}',
      method: 'GET',
      path: `/api/audit-logs/${MISSING_ID}`,
      permission: 'view.audit_log',
      expect: { grant: [404], deny: [403] },
      kind: 'missing-target'
    },
    {
      endpoint: '/api/audit-logs/patient/{id}',
      method: 'GET',
      path: `/api/audit-logs/patient/${fixtures.patient.id}`,
      permission: 'view.audit_log',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/audit-logs/user/{id}',
      method: 'GET',
      path: `/api/audit-logs/user/${auditUser}`,
      permission: 'view.audit_log',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/audit-logs/appointment-type/{id}',
      method: 'GET',
      path: '/api/audit-logs/appointment-type/1',
      permission: 'view.audit_log',
      expect: { grant: [200], deny: [403] },
      kind: 'read'
    },
    {
      endpoint: '/api/audit-logs/dental-chair/{id}',
      method: 'GET',
      path: '/api/audit-logs/dental-chair/1',
      permission: 'view.audit_log',
      expect: { grant: [200], deny: [403] },
      kind: 'read'
    },
    {
      endpoint: '/api/branches/active',
      method: 'GET',
      path: '/api/branches/active',
      permission: 'view.branch',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/branches/{id}/procedure-prices',
      method: 'GET',
      path: `/api/branches/${branchId}/procedure-prices?procedure_id=${surface.id}`,
      permission: 'manage.branch_price',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/branches/{id}/procedure-prices',
      method: 'PUT',
      path: `/api/branches/${branchId}/procedure-prices`,
      body: { items: [] },
      permission: 'manage.branch_price',
      expect: { grant: [422], deny: [403] },
      kind: 'write-gate',
      note: 'sin items: la puerta del tarifario de sede'
    },
    {
      endpoint: '/api/branches/{id}/procedure-prices/{priceId}',
      method: 'DELETE',
      path: `/api/branches/${branchId}/procedure-prices/${MISSING_ID}`,
      permission: 'manage.branch_price',
      expect: { grant: [404], deny: [403] },
      kind: 'write-gate'
    },

    // --- the statistics ----------------------------------------------------
    {
      endpoint: '/api/admin/procedure-stats',
      method: 'GET',
      path: '/api/admin/procedure-stats?limit=5',
      permission: 'view.procedure_stats',
      expect: { grant: [200] },
      kind: 'read'
    },

    // --- the favourites ----------------------------------------------------
    {
      endpoint: '/api/procedure-catalog-favorites',
      method: 'GET',
      path: '/api/procedure-catalog-favorites',
      permission: 'manage.procedure_favorite',
      expect: { grant: [200] },
      kind: 'read'
    },
    {
      endpoint: '/api/procedure-catalog/{id}/favorite',
      method: 'POST',
      path: `/api/procedure-catalog/${matrixProcedure.id}/favorite`,
      permission: 'manage.procedure_favorite',
      expect: { grant: [201], deny: [403] },
      kind: 'write-gate',
      note: 'la fila `matrix` del fixture se marca y se desmarca sólo en esta matriz'
    },
    {
      endpoint: '/api/procedure-catalog/{id}/favorite',
      method: 'DELETE',
      path: `/api/procedure-catalog/${matrixProcedure.id}/favorite`,
      permission: 'manage.procedure_favorite',
      expect: { grant: [200], deny: [403] },
      kind: 'write-gate'
    },
    {
      endpoint: '/api/procedure-catalog-favorites/reorder',
      method: 'PUT',
      path: '/api/procedure-catalog-favorites/reorder',
      body: { ids: [matrixProcedure.id] },
      permission: 'manage.procedure_favorite',
      expect: { grant: [200], deny: [403] },
      kind: 'write-gate'
    },

    // --- declared deviations, measured -------------------------------------
    {
      endpoint: '/api/procedure-catalog (código de 21 caracteres)',
      method: 'POST',
      path: '/api/procedure-catalog',
      body: {
        // 23 caracteres: la columna admite 20 y la petición admite 50.
        code: `AUD-U8-${String(Date.now()).slice(-6)}-MATRIZ-X`,
        name: 'AUDIT-U8 código que la columna no admite',
        default_cost: 10
      },
      permission: 'manage.procedure_catalog',
      expect: { grant: [201, 422], deny: [403] },
      kind: 'write-gate',
      deviation: {
        status: 500,
        cause:
          'procedure_catalog.code es varchar(20) (database/migrations/2025_10_24_202849_create_procedure_catalog_table.php:16) mientras StoreProcedureCatalogRequest declara max:50',
        note: 'un código de 21 caracteres responde 500 «Error al crear el procedimiento» en vez de 422'
      }
    }
  ]
}

const expectedFor = (definition, holdsPermission) => {
  if (!holdsPermission) {
    return {
      statuses: [...new Set([403, ...(definition.expect.deny ?? [])])],
      why: `403: el rol no tiene ${definition.permission}`
    }
  }

  return {
    statuses: [...definition.expect.grant],
    why: `autorizado: la puerta responde ${definition.expect.grant.join('/')}`
  }
}

test('@U8 · capa 5 — contrato por rol de los endpoints del catálogo', async ({ request }) => {
  test.setTimeout(240000)

  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const sessions = new Map()

  for (const role of ROLES) sessions.set(role, await loginAs(request, role))

  const definitions = definitionsFor(fixtures)
  const rows = []

  for (const definition of definitions) {
    for (const role of ROLES) {
      const session = sessions.get(role)
      const holdsPermission = (session.user.permissions ?? []).includes(definition.permission)
      const expectation = expectedFor(definition, holdsPermission)
      const response = await call(
        request,
        session,
        definition.method,
        definition.path,
        definition.body
      )
      const observed = response.status
      const ok = expectation.statuses.includes(observed)
      const deviation = definition.deviation ?? null
      const deviated = !ok && deviation && deviation.status === observed && holdsPermission

      rows.push({
        endpoint: definition.endpoint,
        method: definition.method,
        role,
        expected: expectation.statuses,
        observed,
        ok: ok || deviated,
        deviation: deviated
          ? { declaredExpected: expectation.statuses, observed, ...deviation }
          : null,
        expectedWhy: expectation.why,
        permission: definition.permission,
        roleHasPermission: holdsPermission,
        kind: definition.kind,
        message: response.message,
        errors: response.errors ? Object.keys(response.errors) : null,
        path: definition.path,
        note: definition.note ?? null
      })
    }
  }

  // --- two content facts the matrix records on top of the statuses ---------
  const admin = sessions.get('administrador')
  const ignoredSearch = await call(
    request,
    admin,
    'GET',
    '/api/procedure-catalog?search=REST-COMP-1S&per_page=100'
  )
  const workingSearch = await call(
    request,
    admin,
    'GET',
    '/api/procedure-catalog?q=REST-COMP-1S&per_page=100'
  )
  const inactiveFilter = await call(
    request,
    admin,
    'GET',
    '/api/procedure-catalog?is_active=false&per_page=100'
  )
  const inactiveRows = (inactiveFilter.body?.data ?? []).filter(
    row => row.is_active === false
  ).length

  const contentFacts = [
    {
      endpoint: '/api/procedure-catalog?search=…',
      method: 'GET',
      role: 'administrador',
      expected: '200 y la página filtrada',
      observed: `200 con ${ignoredSearch.body?.meta?.total ?? '—'} filas (el índice lee q, no search)`,
      ok: (ignoredSearch.body?.meta?.total ?? 0) === (workingSearch.body?.meta?.total ?? -1),
      detail: {
        searchTotal: ignoredSearch.body?.meta?.total ?? null,
        qTotal: workingSearch.body?.meta?.total ?? null
      }
    },
    {
      endpoint: '/api/procedure-catalog?is_active=false',
      method: 'GET',
      role: 'administrador',
      expected: '200 y sólo filas inactivas',
      observed: `200 con ${inactiveFilter.body?.meta?.total ?? '—'} filas y ${inactiveRows} inactiva(s)`,
      ok: inactiveRows > 0,
      detail: {
        total: inactiveFilter.body?.meta?.total ?? null,
        inactiveRows,
        cause:
          'ProcedureCatalogService::paginate aplica (bool) al valor: la cadena "false" es true en PHP'
      }
    }
  ]

  const summary = {
    rows: rows.length,
    endpoints: [...new Set(rows.map(row => `${row.method} ${row.endpoint}`))].length,
    ok: rows.filter(row => row.ok).length + contentFacts.filter(row => row.ok).length,
    deviations: rows.filter(row => row.deviation),
    mismatches: rows.filter(row => !row.ok),
    contentFacts,
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

  audit('api-matrix.json', {
    ...stamp(),
    stage: 'layer-5',
    expectedSource:
      'user.permissions from POST /api/auth/login, built by App\\Authorization\\Permissions',
    matrix: ROLES,
    summary,
    rows,
    fixtures: fixtureSummary(fixtures)
  })

  expect(summary.endpoints, 'endpoints in the matrix').toBeGreaterThanOrEqual(22)
  expect(summary.mismatches, 'rows whose observed status left its expectation').toEqual([])
  expect(summary.deviations.length, 'declared deviations measured').toBeGreaterThanOrEqual(1)
})
