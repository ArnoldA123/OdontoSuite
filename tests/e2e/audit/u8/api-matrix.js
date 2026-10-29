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

import { WIDE_CATALOG_CODE, audit, call, stamp } from './helpers.js'

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

    // --- the code-length contract of the create door (#434) ----------------
    {
      endpoint: '/api/procedure-catalog (código de 21 caracteres)',
      method: 'POST',
      path: '/api/procedure-catalog',
      body: {
        // 21 characters: `procedure_catalog.code` is varchar(20) and both write
        // doors declare max:20, so the request is refused with 422, not 500.
        code: `AUD-U8-${String(Date.now()).slice(-6)}-MATRIZ1`,
        name: 'AUDIT-U8 código de 21 caracteres',
        default_cost: 10
      },
      permission: 'manage.procedure_catalog',
      expect: { grant: [422], deny: [403] },
      kind: 'write-gate',
      note: 'un código de 21 caracteres no cabe en la columna: la petición lo rechaza con code.max (#434)'
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
  const unfiltered = await call(request, admin, 'GET', '/api/procedure-catalog?per_page=1')
  const ignoredSearch = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog?search=${WIDE_CATALOG_CODE}&per_page=100`
  )
  const workingSearch = await call(
    request,
    admin,
    'GET',
    `/api/procedure-catalog?q=${WIDE_CATALOG_CODE}&per_page=100`
  )
  const inactiveFilter = await call(
    request,
    admin,
    'GET',
    '/api/procedure-catalog?is_active=false&per_page=100'
  )

  const catalogTotal = unfiltered.body?.meta?.total ?? null
  const searchTotal = ignoredSearch.body?.meta?.total ?? null
  const qTotal = workingSearch.body?.meta?.total ?? null
  const inactiveRows = Array.isArray(inactiveFilter.body?.data) ? inactiveFilter.body.data : []
  const inactiveTotal = inactiveFilter.body?.meta?.total ?? null
  const activeInPage = inactiveRows.filter(row => row.is_active !== false).length

  const contentFacts = [
    {
      endpoint: '/api/procedure-catalog?search=…',
      method: 'GET',
      role: 'administrador',
      expected:
        '200 con la página completa: el índice lee `q` (#416/#432) y `search` no es un parámetro suyo',
      observed: `200 con ${searchTotal ?? '—'} fila(s) para search, ${qTotal ?? '—'} para q y ${catalogTotal ?? '—'} sin filtro`,
      // The two halves are measured, not assumed: `search` leaves the population
      // untouched and `q` narrows it.
      ok:
        catalogTotal !== null &&
        searchTotal === catalogTotal &&
        qTotal !== null &&
        qTotal < catalogTotal,
      detail: { catalogTotal, searchTotal, qTotal }
    },
    {
      endpoint: '/api/procedure-catalog?is_active=false',
      method: 'GET',
      role: 'administrador',
      expected: '200 y sólo filas inactivas',
      observed: `200 con ${inactiveTotal ?? '—'} fila(s) y ${activeInPage} activa(s) en la página`,
      // #433 normalizes the string with `filter_var`; an empty population is a
      // measured answer, so `ok` reads the returned rows, not their count.
      ok: inactiveFilter.status === 200 && activeInPage === 0,
      detail: {
        total: inactiveTotal,
        returned: inactiveRows.length,
        activeInPage
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
  expect(
    summary.deviations,
    'a code of 21 characters answers 422 (#434), `q` filters and `search` does not, and `is_active=false` returns only inactive rows: no deviation left to declare'
  ).toEqual([])
})
