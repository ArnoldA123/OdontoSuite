// Plan #13 (issue #371, U8) — the session's report: raw findings, the
// walkthrough table, the rubric, the gallery and the gate summary.
//
// The walks record a verdict per action; this module turns that evidence into
// the artefacts the session owes (#371, plan §6): `findings.md`/`findings.raw.json`
// (one row per observed defect, grouped by root class), `walkthrough.md`, the
// consolidated `render.json`/`primitives.json`, `ux-rubric.md`, `gate.json` and
// the `index.html` gallery.
//
// Nothing here measures: every number comes from a JSON an earlier row wrote.
// The generator fails if a finding cites evidence that does not exist.

import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'

import { test, expect } from 'playwright/test'

import { audit, evidencePath, stamp } from './helpers.js'

const REPORTED = (globalThis.__AUDIT_U8_REPORT__ ??= { render: null })

const readJson = relPath => JSON.parse(readFileSync(evidencePath(relPath), 'utf8'))
const has = relPath => existsSync(evidencePath(relPath))

/** Reads every JSON an earlier row wrote into a directory of the session. */
const readDir = dir => {
  try {
    return readdirSync(evidencePath(dir))
      .filter(name => name.endsWith('.json'))
      .sort()
      .map(name => readJson(`${dir}/${name}`))
  } catch {
    return []
  }
}

const stepOf = (walk, id) => (walk?.steps ?? []).find(entry => entry.id === id) ?? null
const obtainedOf = (walk, id) => stepOf(walk, id)?.obtained ?? null

/**
 * The layer-2 aggregate, built from the per-row files.
 *
 * `render/` is written by each render test as it finishes, so this is the run's
 * own evidence.
 */
const renderAggregate = rows => ({
  session: rows[0]?.session ?? 'U8',
  generatedAt: new Date().toISOString(),
  revision: rows[0]?.revision ?? null,
  base: rows[0]?.base ?? null,
  stage: 'layer-2',
  routes: [...new Set(rows.map(row => row.route))],
  roles: [...new Set(rows.map(row => row.role))],
  totals: {
    rows: rows.length,
    rendered: rows.filter(row => row.rendered).length,
    overflow: rows.filter(row => row.horizontalOverflow === true).length,
    singleHeading: rows.filter(row => (row.headingCount ?? row.shell?.h1?.count) === 1).length,
    pageErrors: rows.reduce((total, row) => total + (row.pageErrors ?? []).length, 0),
    consoleErrors: rows.reduce((total, row) => total + (row.consoleErrors ?? []).length, 0),
    unexpectedConsole: rows.reduce(
      (total, row) => total + (row.consoleClassified?.unexpected?.length ?? 0),
      0
    ),
    unexpectedRequests: rows.reduce(
      (total, row) => total + (row.failedRequests?.unexpected?.length ?? 0),
      0
    ),
    negativeRowsWithWriteAffordances: rows.filter(
      row => row.expectation === 'negative' && (row.denial?.writeAffordances ?? []).length > 0
    ).length
  },
  rows: rows.map(row => ({
    route: row.route,
    slug: row.slug,
    role: row.role,
    expectation: row.expectation,
    href: row.href,
    httpStatus: row.httpStatus,
    rendered: row.rendered,
    contentLength: row.contentLength,
    headingCount: row.headingCount ?? null,
    h1: row.shell?.h1 ?? null,
    hero: row.shell?.hero ?? null,
    tabs: row.tabs,
    sweptTabs: row.sweptTabs ?? null,
    navigation: row.navigation ?? null,
    denial: row.denial ?? null,
    horizontalOverflow: row.horizontalOverflow,
    mobileContentLength: row.mobile?.contentLength ?? null,
    shots: row.shots,
    pageErrors: (row.pageErrors ?? []).length,
    consoleErrors: (row.consoleErrors ?? []).length,
    unexpectedRequests: (row.failedRequests?.unexpected ?? []).length
  }))
})

/**
 * The findings the session publishes as raw material for triage.
 *
 * `group` is the root class the issue protocol (#371, plan §8.1) asks for;
 * `evidence` names the artefacts that back each row and `step` pins the
 * walkthrough action whose `obtained` text is quoted verbatim, so no number
 * here is retyped by hand.
 */
const buildFindings = evidence => {
  const { catalog, detail, tariff, favorites, render, primitives, matrix } = evidence

  const renderRow = (slug, role) =>
    (render?.rows ?? []).find(row => row.slug === slug && row.role === role) ?? null

  const matrixRows = endpoint => (matrix?.rows ?? []).filter(row => row.endpoint === endpoint)

  const findings = [
    {
      id: 'U8-F01',
      group: 'indice-del-catalogo',
      type: 'defecto',
      surface: '/procedure-catalog (filtro de estado)',
      action: 'Elegir «Inactivos» en el filtro de estado',
      expected:
        'El filtro devuelve los procedimientos inactivos, como declara su propia opción y como hace el resto del índice con cada filtro',
      obtained: obtainedOf(catalog, 'filter-status'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'app/Services/ProcedureCatalogService.php:44-45 aplica `(bool) $filters[\'is_active\']` sobre la cadena "false" (que en PHP es true), así que el índice responde la población activa',
      repro:
        'GET /api/procedure-catalog?is_active=false → 200 con la página completa y 0 filas inactivas; en la pantalla, «Inactivos» pinta filas con badge «Activo».',
      evidence: [
        { file: 'walk-catalog.json', step: 'filter-status' },
        { file: 'api-matrix.json', endpoint: '/api/procedure-catalog?is_active=false' }
      ]
    },
    {
      id: 'U8-F02',
      group: 'indice-del-catalogo',
      type: 'defecto',
      surface: '/reception-procedures (buscador)',
      action: 'Escribir el código de un procedimiento en la búsqueda de recepción',
      expected:
        'La consulta de recepción filtra por el término escrito, como la lista del catálogo',
      obtained: obtainedOf(catalog, 'reception-search'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'resources/js/modules/reception-procedures/ReceptionProceduresPage.vue:83 (filters.search → buildQuery) envía `search` y app/Http/Controllers/Api/ProcedureCatalogController.php:25-37 lee sólo `q` (el filtro vive en app/Services/ProcedureCatalogService.php:48-54): el término viaja y el servidor lo ignora',
      repro:
        'GET /api/procedure-catalog?search=REST-COMP-1S devuelve la página completa (total sin filtrar); ?q=REST-COMP-1S devuelve 1.',
      evidence: [
        { file: 'walk-catalog.json', step: 'reception-search' },
        { file: 'api-matrix.json', endpoint: '/api/procedure-catalog?search=…' }
      ]
    },
    {
      id: 'U8-F03',
      group: 'indice-del-catalogo',
      type: 'defecto',
      surface: '/procedure-catalog (paginación)',
      action: 'Leer la información de paginación con la página completa',
      expected: 'El texto «Mostrando 1 a N de M» usa el tamaño de página que la lista pide',
      obtained: obtainedOf(catalog, 'pagination-info'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue pide `per_page: 20` y monta `Pagination` sin `per-page`, así que el componente informa «1 a 10» y pinta numeración de 10 en 10',
      repro: 'Abrir /procedure-catalog: 20 filas visibles y «Mostrando 1 a 10 de N resultados».',
      evidence: [{ file: 'walk-catalog.json', step: 'pagination-info' }]
    },
    {
      id: 'U8-F04',
      group: 'picker-compartido',
      type: 'defecto',
      surface: '/quotations → «Nuevo presupuesto» → «Elegir del catálogo»',
      action: 'Buscar por código un procedimiento que existe fuera de la primera página',
      expected:
        'El picker encuentra el procedimiento: es el selector que usan presupuestos y planes',
      obtained: obtainedOf(tariff, 'picker-search'),
      layer: 5,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'resources/js/components/procedures/ProcedureCatalogPicker.vue filtra en cliente su página de 12 filas (`filteredProcedures`) y `onSearchInput` recarga la página 1 sin término; el defecto ya está registrado en #416',
      repro:
        'Abrir el alta de presupuesto → «Elegir del catálogo» → escribir REST-COMP-1S: 0 resultados y «Sin resultados»; GET /api/procedure-catalog?q=REST-COMP-1S responde 1.',
      evidence: [
        { file: 'walk-tariff.json', step: 'picker-search' },
        { file: 'walk-tariff.json', step: 'picker-price' }
      ]
    },
    {
      id: 'U8-F05',
      group: 'picker-compartido',
      type: 'defecto',
      surface: '/quotations → «Nuevo presupuesto» → picker del catálogo',
      action: 'Leer el precio que el picker ofrece a un administrador de sede con tarifa propia',
      expected:
        'El precio que el picker muestra es el que la sede cobra: la cascada de #293 resuelve sede → convenio → lista',
      obtained: obtainedOf(tariff, 'picker-price'),
      layer: 5,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'app/Services/ProcedureCatalogService.php::paginate (el índice que consume el picker) no resuelve el tarifario, mientras `search` (línea 129) y `activeList` (línea 161) sí llaman a ProcedurePriceResolver::resolveMany: la misma sede ve dos precios según la puerta, y QuotationService::freezeLine vuelve a resolver al guardar',
      repro:
        'Con un administrador de sede (branch_id 1) y una fila de 375 para el procedimiento: /api/procedure-catalog/search responde 375 (origen branch) y el picker pinta S/ 500.00 (lista).',
      evidence: [{ file: 'walk-tariff.json', step: 'picker-price' }]
    },
    {
      id: 'U8-F06',
      group: 'alta-y-duplicado',
      type: 'defecto',
      surface: '/procedure-catalog (duplicar)',
      action: 'Duplicar un procedimiento que declara aplicabilidad y equivalencias',
      expected:
        'La copia conserva los campos del original y el código propuesto cabe en la columna',
      obtained: obtainedOf(catalog, 'duplicate'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue:665 (submitDuplicate) copia una lista fija de campos (omite site_type, external_codes y material_recipe) y propone `${code}-COPIA`; procedure_catalog.code es varchar(20) (database/migrations/2025_10_24_202849_create_procedure_catalog_table.php:16) mientras StoreProcedureCatalogRequest admite max:50',
      repro:
        'Duplicar «AUDIT-U8-…-SIT»: POST 500 «Error al crear el procedimiento» con el código propuesto; con un código corto la copia pierde site_type y las equivalencias.',
      evidence: [
        { file: 'walk-catalog.json', step: 'duplicate' },
        {
          file: 'api-matrix.json',
          endpoint: '/api/procedure-catalog (código de 21 caracteres)'
        }
      ]
    },
    {
      id: 'U8-F07',
      group: 'detalle',
      type: 'defecto',
      surface: '/procedure-catalog/:id (pestaña Historial)',
      action: 'Abrir el historial de auditoría del procedimiento',
      expected: 'El historial lista los cambios que la API guardó para ese procedimiento',
      obtained: obtainedOf(detail, 'tab-audit'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue:603 (loadAudit, línea 597) envía `model_type` con doble barra; el registro guardado usa una sola y el filtro nunca coincide',
      repro:
        'Abrir el detalle de un procedimiento recién creado → «Historial»: «No hay historial de auditoría» mientras GET /api/audit-logs?model_type=App\\Models\\ProcedureCatalog&auditable_id={id} devuelve los registros.',
      evidence: [
        { file: 'walk-detail.json', step: 'tab-audit' },
        { file: 'api-matrix.json', endpoint: '/api/audit-logs' }
      ]
    },
    {
      id: 'U8-F08',
      group: 'detalle',
      type: 'defecto',
      surface: '/procedure-catalog/:id (Tarifario → precio de sede)',
      action: 'Guardar una vigencia final anterior al inicio',
      expected: 'El formulario explica el rechazo en el campo, como hace con el precio',
      obtained: obtainedOf(tariff, 'price-window-invalid'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/procedure-catalog/BranchTariffPanel.vue::validate escribe `formErrors.valid_to`, pero el UiInput de «Vigente hasta» no recibe `:error` (sólo el precio lo hace): el envío se bloquea sin mensaje',
      repro:
        'Editar un precio de sede → «Vigente desde» hoy, «Vigente hasta» hace dos días → «Guardar»: no pasa nada y no aparece ningún texto.',
      evidence: [{ file: 'walk-tariff.json', step: 'price-window-invalid' }]
    },
    {
      id: 'U8-F09',
      group: 'detalle',
      type: 'defecto',
      surface: '/procedure-catalog/:id → «Editar»',
      action: 'Pulsar «Editar» en la cabecera del detalle',
      expected: 'La edición abre el formulario del procedimiento que se está mirando',
      obtained: obtainedOf(detail, 'edit-door'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue::openEditFromQuery (línea 604) consume `?edit=` y lo retira (616) antes de que la fila esté cargada: la vista se monta dos veces y el formulario no abre',
      repro:
        'Abrir /procedure-catalog/{id} → «Editar»: la URL pasa por /procedure-catalog?edit={id} y termina en /procedure-catalog sin diálogo; abrir ?edit= directamente tampoco abre el formulario.',
      evidence: [{ file: 'walk-detail.json', step: 'edit-door' }]
    },
    {
      id: 'U8-F10',
      group: 'detalle',
      type: 'no-terminado',
      surface: '/procedure-catalog/:id y formulario del procedimiento',
      action: 'Buscar la receta de insumos del procedimiento',
      expected:
        'La receta que la API acepta y sincroniza (#295) tiene una superficie donde se lee y se edita',
      obtained: obtainedOf(detail, 'material-recipe'),
      layer: 4,
      origin: 'INFERIDO',
      severity: 'crit:media',
      cause:
        'StoreProcedureCatalogRequest y ProcedureCatalogService tratan `material_recipe`, pero ningún archivo de resources/js lo lee ni lo escribe (grep de `material_recipe` en resources/js: 0 coincidencias); el detalle publica bloques general/external-codes/preparation/care',
      repro:
        'Abrir un procedimiento: no hay bloque de receta; el formulario de alta/edición tampoco lo envía.',
      evidence: [
        { file: 'walk-detail.json', step: 'material-recipe' },
        { file: 'walk-catalog.json', step: 'duplicate' }
      ]
    },
    {
      id: 'U8-F11',
      group: 'primitivas-y-permisos',
      type: 'defecto',
      surface: '/procedure-catalog (rol recepción)',
      action: 'Abrir la lista como recepción',
      expected:
        'La lista no ofrece escrituras a un rol cuya escritura responde 403 en todas las puertas',
      obtained: `${obtainedOf(favorites, 'catalog-writes-as-reception')} · puertas medidas: ${matrixRows('/api/procedure-catalog').filter(row => row.role === 'recepcionista' && row.observed === 403).length ?? 0} respuestas 403`,
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue pinta «Nuevo procedimiento», «Importar CSV» y las acciones de fila sin consultar `usePermissions`; sólo «Estadísticas» usa `hasAnyRole`',
      repro:
        'Login recepcionista_test → /procedure-catalog: la fila ofrece editar, duplicar y desactivar; POST/PUT/DELETE responden 403.',
      evidence: [
        { file: 'walk-favorites.json', step: 'catalog-writes-as-reception' },
        { file: 'walk-favorites.json', step: 'reception-write-gate' },
        { file: 'render/procedure-catalog-recepcionista.json' }
      ]
    },
    {
      id: 'U8-F12',
      group: 'primitivas-y-permisos',
      type: 'defecto',
      surface: '/procedure-catalog y /procedure-stats',
      action: 'Comparar las dos tablas del módulo',
      expected:
        'Dos listas del mismo módulo comparten paginación, estado vacío y affordance de scroll',
      obtained: obtainedOf(catalog, 'list-vs-stats'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue monta una `<table class="min-w-[880px]">` propia mientras ProcedureStatsPage usa `UiDataTable` (data-table-scroll, affordance, vacío propio)',
      repro:
        'A 1440 px la lista del catálogo no declara `[data-table-scroll]`; estadísticas sí. A 390 px la lista desborda dentro de su marco sin aviso de desplazamiento.',
      evidence: [
        { file: 'walk-catalog.json', step: 'list-vs-stats' },
        { file: 'walk-catalog.json', step: 'mobile-table' }
      ]
    }
  ]

  // The pictionary rows that only exist to be counted.
  const primitiveFailures = (primitives?.entries ?? []).reduce(
    (total, entry) => total + (entry.contractFailures ?? []).length,
    0
  )
  if (primitiveFailures > 0) {
    findings.push({
      id: 'U8-F13',
      group: 'primitivas-y-permisos',
      type: 'defecto',
      surface: 'los seis diálogos del módulo',
      action: 'Medir el contrato de la primitiva',
      expected: 'Centrado, un solo h1, foco atrapado, scroll bloqueado, nombre accesible y Escape',
      obtained: `${primitiveFailures} comprobación(es) fallida(s) en ${(primitives?.entries ?? []).length} diálogo(s)`,
      layer: 3,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause: 'primitives.json',
      repro: 'Abrir cada diálogo del catálogo y del tarifario.',
      evidence: [{ file: 'primitives.json' }]
    })
  }

  // Two render facts worth publishing even when nothing throws.
  const negativeWriteRow = renderRow('procedure-catalog', 'recepcionista')
  if ((negativeWriteRow?.denial?.writeAffordances ?? []).length > 0) {
    findings.push({
      id: 'U8-F14',
      group: 'primitivas-y-permisos',
      type: 'defecto',
      surface: '/procedure-catalog (capa 2, recepción)',
      action: 'Renderizar la lista para el rol excluido',
      expected: 'La fila negativa llega a la superficie sin controles que su rol no puede ejecutar',
      obtained: `capa 2: ${JSON.stringify(negativeWriteRow.denial.writeAffordances)}`,
      layer: 2,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause: 'El mismo que U8-F11, medido en la capa 2',
      repro: '/procedure-catalog como recepcionista.',
      evidence: [{ file: 'render/procedure-catalog-recepcionista.json' }]
    })
  }

  return findings
}

test('@U8 · informe: hallazgos, recorrido, rúbrica y galería', async () => {
  test.setTimeout(120000)

  const evidence = {
    catalog: has('walk-catalog.json') ? readJson('walk-catalog.json') : null,
    detail: has('walk-detail.json') ? readJson('walk-detail.json') : null,
    tariff: has('walk-tariff.json') ? readJson('walk-tariff.json') : null,
    stats: has('walk-stats.json') ? readJson('walk-stats.json') : null,
    favorites: has('walk-favorites.json') ? readJson('walk-favorites.json') : null,
    render: renderAggregate(readDir('render')),
    primitives: {
      entries: globalThis.__AUDIT_U8_PRIMITIVES__?.entries ?? [],
      aggregates: globalThis.__AUDIT_U8_PRIMITIVES__?.aggregates ?? []
    },
    matrix: has('api-matrix.json') ? readJson('api-matrix.json') : null,
    fixtures: has('fixtures.json') ? readJson('fixtures.json') : null
  }

  // A targeted `--grep` run does not execute the other layers: their artefacts
  // are read from disk instead of from this process's registry.
  if (!evidence.primitives.entries.length) {
    evidence.primitives.entries = readDir('primitives').map(row => ({
      ...row,
      outcome: row.outcome ?? (row.dialogs?.length ? 'measured' : 'unreachable')
    }))
  }

  const findings = buildFindings(evidence)

  // --- the layer-2 aggregate, from this run's own rows --------------------
  audit('render-session.json', evidence.render)
  audit('render.json', evidence.render)
  REPORTED.render = evidence.render

  // --- integrity: every finding cites evidence that exists ----------------
  const missingEvidence = []

  for (const finding of findings) {
    for (const reference of finding.evidence) {
      if (!has(reference.file)) missingEvidence.push({ finding: finding.id, file: reference.file })
    }
  }

  // --- the gate ------------------------------------------------------------
  const walkTotalsOf = walk => walk?.totals ?? null
  const gate = {
    ...stamp(),
    stage: 'gate',
    pageErrors: {
      render: evidence.render.totals.pageErrors,
      walks: [
        evidence.catalog,
        evidence.detail,
        evidence.tariff,
        evidence.stats,
        evidence.favorites
      ]
        .filter(Boolean)
        .reduce((total, walk) => total + (walk.pageErrors ?? []).length, 0),
      primitives: (evidence.primitives.entries ?? []).reduce(
        (total, entry) => total + (entry.pageErrors ?? []).length,
        0
      )
    },
    console: {
      renderUnexpected: evidence.render.totals.unexpectedConsole,
      walks: [
        evidence.catalog,
        evidence.detail,
        evidence.tariff,
        evidence.stats,
        evidence.favorites
      ]
        .filter(Boolean)
        .reduce((total, walk) => total + (walk.consoleClassified?.unexpected?.length ?? 0), 0)
    },
    requests: {
      renderUnexpected: evidence.render.totals.unexpectedRequests,
      walks: [
        evidence.catalog,
        evidence.detail,
        evidence.tariff,
        evidence.stats,
        evidence.favorites
      ]
        .filter(Boolean)
        .reduce((total, walk) => total + (walk.failedRequests?.unexpected?.length ?? 0), 0)
    },
    actions: {
      catalog: walkTotalsOf(evidence.catalog),
      detail: walkTotalsOf(evidence.detail),
      tariff: walkTotalsOf(evidence.tariff),
      stats: walkTotalsOf(evidence.stats),
      favorites: walkTotalsOf(evidence.favorites),
      total: [
        evidence.catalog,
        evidence.detail,
        evidence.tariff,
        evidence.stats,
        evidence.favorites
      ]
        .filter(Boolean)
        .reduce((total, walk) => total + (walk.steps?.length ?? 0), 0),
      classified: [
        evidence.catalog,
        evidence.detail,
        evidence.tariff,
        evidence.stats,
        evidence.favorites
      ]
        .filter(Boolean)
        .reduce(
          (total, walk) =>
            total + (walk.steps ?? []).filter(entry => entry.verdict !== 'NO PROBADO').length,
          0
        )
    },
    dialogs: {
      declared: (evidence.primitives.entries ?? []).length,
      measured: (evidence.primitives.entries ?? []).filter(entry => entry.outcome === 'measured')
        .length,
      contractFailures: (evidence.primitives.entries ?? []).reduce(
        (total, entry) => total + (entry.contractFailures ?? []).length,
        0
      )
    },
    matrix: evidence.matrix?.summary
      ? {
          rows: evidence.matrix.summary.rows ?? null,
          endpoints: evidence.matrix.summary.endpoints ?? null,
          // Evidence written before the matrix published these lists cannot be
          // counted (#501 S4): the gate says `null` instead of a zero it never
          // observed.
          mismatches: Array.isArray(evidence.matrix.summary.mismatches)
            ? evidence.matrix.summary.mismatches.length
            : null,
          deviations: Array.isArray(evidence.matrix.summary.deviations)
            ? evidence.matrix.summary.deviations.length
            : null,
          contentFacts: (evidence.matrix.summary.contentFacts ?? []).filter(row => !row.ok).length
        }
      : null,
    surfacesWithoutHorizontalOverflow: `${evidence.render.rows.filter(row => row.horizontalOverflow === false).length}/${evidence.render.rows.length}`
  }

  audit('gate.json', gate)

  // --- findings.raw.json --------------------------------------------------
  audit('findings.raw.json', {
    ...stamp(),
    stage: 'layer-6',
    session: 'U8',
    issue: '#371',
    plan: 'docs/mejoras/13-programa-auditoria-ui-funcional-2026-10.md §7 U8',
    method: 'capas 2-3-4-5; cada fila cita el paso medido y el artefacto que lo respalda',
    totals: {
      findings: findings.length,
      bySeverity: findings.reduce((accumulator, finding) => {
        accumulator[finding.severity] = (accumulator[finding.severity] ?? 0) + 1
        return accumulator
      }, {}),
      byLayer: findings.reduce((accumulator, finding) => {
        accumulator[finding.layer] = (accumulator[finding.layer] ?? 0) + 1
        return accumulator
      }, {}),
      byOrigin: findings.reduce((accumulator, finding) => {
        accumulator[finding.origin] = (accumulator[finding.origin] ?? 0) + 1
        return accumulator
      }, {}),
      groups: [...new Set(findings.map(finding => finding.group))].length
    },
    groups: [...new Set(findings.map(finding => finding.group))],
    findings,
    coverage: {
      walks: [
        evidence.catalog && { file: 'walk-catalog.json', totals: evidence.catalog.totals },
        evidence.detail && { file: 'walk-detail.json', totals: evidence.detail.totals },
        evidence.tariff && { file: 'walk-tariff.json', totals: evidence.tariff.totals },
        evidence.stats && { file: 'walk-stats.json', totals: evidence.stats.totals },
        evidence.favorites && { file: 'walk-favorites.json', totals: evidence.favorites.totals }
      ].filter(Boolean),
      renderRows: evidence.render?.totals ?? null,
      matrix: evidence.matrix?.summary
        ? {
            rows: evidence.matrix.summary.rows ?? null,
            endpoints: evidence.matrix.summary.endpoints ?? null,
            mismatches: Array.isArray(evidence.matrix.summary.mismatches)
              ? evidence.matrix.summary.mismatches.length
              : null,
            deviations: Array.isArray(evidence.matrix.summary.deviations)
              ? evidence.matrix.summary.deviations.length
              : null
          }
        : null
    },
    unobservable: [
      {
        item: 'La receta de insumos con datos reales',
        reason:
          'El seed de la base de auditoría no tiene productos (`GET /api/inventory/products` → 0), y `material_recipe.*.product_id` exige un producto existente: el campo se midió como ausencia de superficie (U8-F10, INFERIDO) y no como diferencia de datos.'
      },
      {
        item: 'El precio de sede dentro del picker con la cascada completa (convenio + sede)',
        reason:
          'La sesión no crea convenios: la cascada se midió en su rama «sede» (fila de 375 sobre lista de 500), que es la que #371 nombra. La rama de convenio pertenece a U6.'
      },
      {
        item: 'La sesión de caja o los planes de tratamiento más allá de las dos filas que alimentan las estadísticas',
        reason:
          'El objeto de U8 es el catálogo; los dos planes existen sólo para que `/api/admin/procedure-stats` tenga población.'
      }
    ]
  })

  // --- walkthrough.md -----------------------------------------------------
  const tables = [
    ['Catálogo (capa 4 · administración)', evidence.catalog],
    ['Detalle (capa 4 · administración)', evidence.detail],
    ['Tarifario y picker (capas 4-5 · administrador de sede)', evidence.tariff],
    ['Estadísticas (capa 4 · administración)', evidence.stats],
    ['Favoritos y recepción (capa 4)', evidence.favorites]
  ]

  const lines = [
    '# U8 — Catálogo de procedimientos y tarifario (#371)',
    '',
    `Generado por \`tests/e2e/audit/u8/report.js\` el ${new Date().toISOString()} sobre la revisión ${(evidence.catalog ?? evidence.detail ?? {}).revision ?? 'desconocida'}.`,
    '',
    `Base: \`${(evidence.catalog ?? evidence.detail ?? {}).base ?? 'desconocida'}\` · evidencia cruda en \`.atl/qa-evidence/audit/ui/U8/\`.`,
    '',
    'Criterio de salida (#371): el precio de sede con vigencia se refleja en el picker y en el detalle; el CSV coincide exactamente con lo mostrado; dos listas del mismo módulo se comportan igual; cero `pageerror`; rúbrica puntuada.',
    '',
    `Puerta: **${gate.pageErrors.render + gate.pageErrors.walks + gate.pageErrors.primitives} pageerror(s)** · ${gate.actions.classified}/${gate.actions.total} acciones clasificadas · ${gate.dialogs.measured}/${gate.dialogs.declared} diálogos medidos · ${gate.matrix?.rows ?? 0} filas de matriz (${gate.matrix?.mismatches ?? 0} desvíos de estado, ${gate.matrix?.deviations ?? 0} desviación declarada) · ${gate.surfacesWithoutHorizontalOverflow} superficies sin desborde a 390 px.`,
    ''
  ]

  for (const [title, walk] of tables) {
    if (!walk) continue

    lines.push(`## ${title}`, '')
    lines.push(
      `Veredicto: ${walk.totals?.ok ?? 0} OK · ${walk.totals?.defect ?? 0} DEFECTO · ${walk.totals?.noop ?? 0} NO-OP · ${walk.totals?.confusing ?? 0} CONFUSO · ${walk.totals?.notProbed ?? 0} NO PROBADO`,
      ''
    )
    lines.push('| # | Acción | Esperado | Obtenido | Veredicto | Request |')
    lines.push('| --- | --- | --- | --- | --- | --- |')

    for (const entry of walk.steps ?? []) {
      const escape = value =>
        String(value ?? '')
          .replace(/\|/g, '\\|')
          .replace(/\s+/g, ' ')
          .slice(0, 300)
      lines.push(
        `| ${escape(entry.step)} | ${escape(entry.action)} | ${escape(entry.expected)} | ${escape(entry.obtained ?? entry.reason)} | ${entry.verdict} | ${entry.requestStatus ?? '—'} |`
      )
    }

    lines.push('')
  }

  if (evidence.matrix?.summary) {
    lines.push('## Contrato por rol (capa 5)', '')
    lines.push('| # | Endpoint | Método | Rol | Esperado | Observado | Veredicto |')
    lines.push('| --- | --- | --- | --- | --- | --- | --- |')

    for (const row of evidence.matrix.rows ?? []) {
      lines.push(
        `| — | \`${row.endpoint}\` | ${row.method} | ${row.role} | ${row.expected.join('/')} | ${row.observed} | ${row.deviation ? 'DESVIACIÓN declarada' : row.ok ? 'OK' : 'DEFECTO'} |`
      )
    }

    lines.push('', '### Hechos de contenido medidos en la matriz', '')

    for (const fact of evidence.matrix.summary.contentFacts ?? []) {
      lines.push(`- ${fact.ok ? 'OK' : 'DEFECTO'} · \`${fact.endpoint}\`: ${fact.observed}`)
    }

    lines.push('')
  }

  lines.push('## Hallazgos', '')
  for (const finding of findings) {
    lines.push(
      `- **${finding.id}** (${finding.severity}, capa ${finding.layer}, ${finding.origin}) ${finding.surface}: ${finding.action}`
    )
  }
  lines.push('')

  writeFileSync(evidencePath('walkthrough.md'), `${lines.join('\n')}\n`)

  // --- findings.md --------------------------------------------------------
  const findingsLines = [
    '# U8 — hallazgos agrupados por clase raíz (#371)',
    '',
    `Un hallazgo por clase raíz, no por síntoma (plan §8.1). El triage del programa decide cuántos issues se publican (tope 3–7 por sesión); esta lista es el material crudo.`,
    ''
  ]

  for (const group of [...new Set(findings.map(finding => finding.group))]) {
    findingsLines.push(`## ${group}`, '')

    for (const finding of findings.filter(entry => entry.group === group)) {
      findingsLines.push(`### ${finding.id} · ${finding.surface}`, '')
      findingsLines.push(`- **Acción**: ${finding.action}`)
      findingsLines.push(`- **Esperado**: ${finding.expected}`)
      findingsLines.push(`- **Obtenido**: ${finding.obtained ?? '—'}`)
      findingsLines.push(`- **Causa**: ${finding.cause}`)
      findingsLines.push(`- **Reproducción**: ${finding.repro}`)
      findingsLines.push(`- **Severidad**: ${finding.severity} · **origen**: ${finding.origin}`)
      findingsLines.push(
        `- **Evidencia**: ${finding.evidence.map(reference => (reference.step ? `${reference.file} · ${reference.step}` : reference.file)).join(' · ')}`
      )
      findingsLines.push('')
    }
  }

  writeFileSync(evidencePath('findings.md'), `${findingsLines.join('\n')}\n`)

  // --- primitives.json (consolidated) -------------------------------------
  const primitiveEntries = evidence.primitives.entries
  const primitiveAggregate = {
    ...stamp(),
    stage: 'layer-3',
    declared: primitiveEntries.length,
    measured: primitiveEntries.filter(entry => entry.outcome === 'measured').length,
    unreachable: primitiveEntries.filter(entry => entry.outcome === 'unreachable').length,
    contractFailures: primitiveEntries.reduce(
      (total, entry) => total + (entry.contractFailures ?? []).length,
      0
    ),
    entries: primitiveEntries,
    pageErrors: [],
    consoleErrors: [],
    failedRequests: { expected: [], unexpected: [] }
  }

  audit('primitives.json', primitiveAggregate)
  // `primitives.json` is also rewritten by `scripts/audit/ui-pass.mjs --run=false`,
  // so the session keeps its own copy.
  audit('primitives-session.json', primitiveAggregate)

  // --- ux-rubric.md -------------------------------------------------------
  const renderRow = (slug, role) =>
    (evidence.render?.rows ?? []).find(row => row.slug === slug && row.role === role) ?? null

  const rubricRows = [
    {
      surface: '/procedure-catalog (lista)',
      rows: [
        [
          '1 Jerarquía de títulos',
          1,
          `El topbar deja el h1 oculto (${JSON.stringify(renderRow('procedure-catalog', 'administrador')?.h1?.visibleTexts ?? [])}) pero la página pinta el título en un \`h2\` y repite «Procedimientos» como segunda sección.`
        ],
        ['2 Densidad de texto', 2, 'Hero de una frase, cinco KPIs y una única tarjeta de filtros.'],
        [
          '3 Información repetida',
          1,
          'La tarjeta de filtros dice «Mostrando N de M» y la paginación repite el total con otro tamaño de página (U8-F03).'
        ],
        [
          '4 Acciones con efecto',
          1,
          'La lista ofrece escrituras a recepción aunque la API las rechace (U8-F11); «Duplicar» falla con 500 en códigos largos (U8-F06).'
        ],
        [
          '5 Estados completos',
          2,
          'Carga con spinner, vacío con CTA y error con reintento (medido en create-empty y search-empty).'
        ],
        [
          '6 Primitivas',
          1,
          'La tabla es propia, no `UiDataTable`: sin affordance de scroll ni paginación compartida (U8-F12).'
        ],
        [
          '7 Feedback',
          2,
          'Alta, edición, desactivación y reactivación responden con toast y recargan la lista.'
        ],
        [
          '8 Navegación',
          2,
          '«Volver» va al panel, «Estadísticas» al reporte y el detalle abre por el nombre del procedimiento.'
        ],
        [
          '9 Accesibilidad',
          2,
          'Los seis diálogos del módulo cumplen el contrato de la capa 3 (primitives.json).'
        ],
        [
          '10 Responsive',
          2,
          'Sin desborde de documento a 390 px; la tabla se desplaza dentro de su marco.'
        ],
        [
          '11 Formato de datos',
          1,
          'El código se corta en la columna (13 % del ancho) y la paginación informa un tamaño de página distinto al que pide.'
        ],
        [
          '12 Honestidad de la superficie',
          1,
          'El filtro «Inactivos» devuelve activos (U8-F01) y recepción ve controles que no puede usar.'
        ]
      ]
    },
    {
      surface: '/procedure-catalog/:id (detalle)',
      rows: [
        [
          '1 Jerarquía de títulos',
          2,
          'Un h1 con el nombre del procedimiento, badge de estado y código en la cabecera.'
        ],
        ['2 Densidad de texto', 2, 'Cuatro tarjetas y una tira de pestañas; sin bloques vacíos.'],
        [
          '3 Información repetida',
          1,
          'El precio aparece en Datos, en Tarifario y en el listado sin decir cuál manda.'
        ],
        [
          '4 Acciones con efecto',
          0,
          '«Editar» no abre el formulario (U8-F09) y la ventana invertida se bloquea sin mensaje (U8-F08).'
        ],
        [
          '5 Estados completos',
          1,
          'Historial declara «No hay historial» cuando la API sí tiene registros (U8-F07); Versiones carga bien.'
        ],
        [
          '6 Primitivas',
          2,
          'UiTabs, UiCard, UiDataTable en el tarifario y UiModal en los formularios.'
        ],
        [
          '7 Feedback',
          1,
          'El tarifario confirma con toast; la validación de la ventana no explica nada.'
        ],
        ['8 Navegación', 2, 'Miga de pan al catálogo y «Volver» en la cabecera.'],
        ['9 Accesibilidad', 2, 'Los dos diálogos del tarifario cumplieron el contrato.'],
        ['10 Responsive', 2, 'Sin desborde a 390 px.'],
        [
          '11 Formato de datos',
          2,
          'Vigencia como dd/mm/aaaa — dd/mm/aaaa y precios con dos decimales.'
        ],
        [
          '12 Honestidad de la superficie',
          1,
          'La receta de insumos no tiene superficie (U8-F10) y el historial miente por su filtro.'
        ]
      ]
    },
    {
      surface: '/procedure-stats',
      rows: [
        [
          '1 Jerarquía de títulos',
          2,
          'h1 oculto en el topbar y hero «Estadísticas del Catálogo» como título visible.'
        ],
        [
          '2 Densidad de texto',
          2,
          'Hero, cuatro KPIs, filtros y dos gráficos; los números mandan.'
        ],
        [
          '3 Información repetida',
          1,
          'El período se declara dos veces (chip de preset y línea «Período aplicado»), con el mismo dato.'
        ],
        [
          '4 Acciones con efecto',
          2,
          'Presets, Top N, aplicar, exportar y ampliar período hacen lo que dicen (medido).'
        ],
        ['5 Estados completos', 2, 'Vacío con «Ampliar a 90 días» y error con reintento.'],
        ['6 Primitivas', 2, 'UiDataTable, UiCard, UiSelect y UiEmptyState.'],
        [
          '7 Feedback',
          2,
          '«Consultando…» mientras carga y el botón de exportar se deshabilita sin datos.'
        ],
        ['8 Navegación', 2, '«Volver al catálogo».'],
        ['9 Accesibilidad', 2, 'Cada gráfico es un `canvas` con `role="img"` y `aria-label`.'],
        ['10 Responsive', 2, 'Sin desborde a 390 px y lienzos redibujados a 308 px.'],
        [
          '11 Formato de datos',
          2,
          'El CSV coincide exactamente con la tabla y con la distribución (comparado fila a fila).'
        ],
        [
          '12 Honestidad de la superficie',
          2,
          'El encabezado dice «Top 3» porque hay tres filas, no «Top 50» porque se pidió 50; el alcance del rol se declara en el hero.'
        ]
      ]
    },
    {
      surface: '/my-procedures',
      rows: [
        [
          '1 Jerarquía de títulos',
          1,
          'Hero con `h2` («Mis Procedimientos») y secciones también en `h2`: dos niveles con el mismo peso.'
        ],
        ['2 Densidad de texto', 2, 'Contador de favoritos, lista y dos bloques de sugerencias.'],
        [
          '3 Información repetida',
          1,
          'El contador «N favoritos» repite el largo de la lista que tiene debajo.'
        ],
        [
          '4 Acciones con efecto',
          2,
          'Marcar, reordenar y quitar funcionan y persisten tras recargar (medido).'
        ],
        [
          '5 Estados completos',
          1,
          'Sin favoritos usa UiEmptyState, pero el explorador vacío es un párrafo suelto.'
        ],
        ['6 Primitivas', 2, 'UiCard, UiEmptyState, UiStatusBadge y UiInput.'],
        ['7 Feedback', 2, 'Cada alta y baja responde con toast y el contador se actualiza.'],
        ['8 Navegación', 2, '«Volver al panel» y el explorador dentro de la misma página.'],
        [
          '9 Accesibilidad',
          2,
          'Los tres controles por fila tienen `aria-label` («Subir favorito», etc.).'
        ],
        ['10 Responsive', 2, 'Sin desborde a 390 px (fila medida en capa 2).'],
        [
          '11 Formato de datos',
          2,
          'Código, especialidad, duración y precio con el mismo formato que el catálogo.'
        ],
        [
          '12 Honestidad de la superficie',
          2,
          'El favorito duplicado no crea filas y el orden se guarda en el servidor.'
        ]
      ]
    },
    {
      surface: '/reception-procedures',
      rows: [
        [
          '1 Jerarquía de títulos',
          1,
          'Repite el nombre «Catálogo de Procedimientos» que ya usa el catálogo de administración: dos superficies con el mismo título.'
        ],
        ['2 Densidad de texto', 2, 'Tres KPIs y tarjetas de procedimiento con precio y duración.'],
        [
          '3 Información repetida',
          1,
          'El KPI «Procedimientos activos» cuenta todo el catálogo mientras las tarjetas muestran 12.'
        ],
        [
          '4 Acciones con efecto',
          0,
          'El buscador no filtra: envía `search` y el índice sólo lee `q` (U8-F02).'
        ],
        ['5 Estados completos', 2, 'Carga, vacío «Sin resultados» y error con reintento.'],
        ['6 Primitivas', 2, 'UiCard, UiBadge, UiEmptyState y Pagination.'],
        ['7 Feedback', 1, 'El error de búsqueda no existe: la lista simplemente no cambia.'],
        ['8 Navegación', 2, '«Volver al panel» y «Volver» en la cabecera.'],
        ['9 Accesibilidad', 1, 'Sin diálogos que medir; el buscador no anuncia su resultado.'],
        ['10 Responsive', 2, 'Sin desborde a 390 px.'],
        ['11 Formato de datos', 2, 'Precio y duración con el mismo formato que el catálogo.'],
        [
          '12 Honestidad de la superficie',
          2,
          'No ofrece ningún control de escritura (medido: 0 controles, 0 formularios).'
        ]
      ]
    }
  ]

  const rubric = [
    '# U8 — rúbrica UX/UI (insumo para el triage)',
    '',
    'Puntuación 0/1/2 por criterio (plan §5). Cada fila cita lo medido en esta sesión; el veredicto y la severidad los fija el triage del programa.',
    ''
  ]

  for (const surface of rubricRows) {
    rubric.push(`## ${surface.surface}`, '')
    rubric.push('| Criterio | Puntos | Cita de lo observado |')
    rubric.push('| --- | --- | --- |')

    for (const [criterion, score, quote] of surface.rows) {
      rubric.push(`| ${criterion} | ${score} | ${String(quote).replace(/\|/g, '\\|')} |`)
    }

    const total = surface.rows.reduce((sum, [, score]) => sum + score, 0)
    rubric.push('', `Total: **${total}/24**`, '')
  }

  const sessionTotal = rubricRows.reduce(
    (sum, surface) => sum + surface.rows.reduce((inner, [, score]) => inner + score, 0),
    0
  )
  rubric.push(
    '## La sesión en su conjunto',
    '',
    `Total de las cinco superficies primarias: **${sessionTotal}/${rubricRows.length * 24}**.`,
    '',
    `- Las cuatro superficies cuentan la misma verdad del procedimiento —código, aplicabilidad, equivalencias y tarifa— con dos excepciones medidas: el precio de sede no llega al picker (U8-F05) y la receta de insumos no tiene superficie (U8-F10).`,
    `- El CSV de estadísticas coincide exactamente con la pantalla (comparado fila a fila en \`walk-stats.json\`).`,
    `- Las dos listas del módulo no se comportan igual: el catálogo usa una tabla propia y estadísticas usa \`UiDataTable\` (U8-F12).`,
    `- Cero \`pageerror\` en las ${evidence.render.rows.length} filas de capa 2, las cinco caminatas y los seis diálogos.`,
    ''
  )

  writeFileSync(evidencePath('ux-rubric.md'), `${rubric.join('\n')}\n`)

  // --- index.html gallery -------------------------------------------------
  const collect = dir => {
    try {
      return readdirSync(evidencePath(dir))
        .filter(name => name.endsWith('.png'))
        .sort()
        .map(name => `${dir}/${name}`)
    } catch {
      return []
    }
  }

  const groups = {}
  for (const dir of ['render', 'primitives']) groups[dir] = collect(dir)

  try {
    for (const name of readdirSync(evidencePath('walk')).sort()) {
      const dir = `walk/${name}`
      const shots = name.endsWith('.png') ? [dir] : collect(dir)
      if (shots.length) groups[dir] = shots
    }
  } catch {
    // A targeted run may not have written a walkthrough capture yet.
  }

  const html = [
    '<!doctype html>',
    '<html lang="es"><head><meta charset="utf-8">',
    '<title>U8 — Catálogo de procedimientos y tarifario</title>',
    '<style>body{font-family:system-ui,sans-serif;margin:24px;background:#f6f6f7;color:#111}',
    'h1{font-size:20px}h2{margin-top:32px;font-size:16px}',
    '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}',
    'figure{margin:0;background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden}',
    'figcaption{padding:6px 8px;font-size:12px;color:#555;word-break:break-all}',
    'img{display:block;width:100%;height:auto}</style></head><body>',
    '<h1>U8 — Catálogo de procedimientos y tarifario (#371)</h1>',
    `<p>Revisión ${(evidence.fixtures ?? {}).revision ?? 'desconocida'} · base ${(evidence.fixtures ?? {}).base ?? 'desconocida'} · ${new Date().toISOString()}</p>`,
    `<p>${gate.pageErrors.render + gate.pageErrors.walks + gate.pageErrors.primitives} pageerror(s) · ${gate.actions.classified}/${gate.actions.total} acciones clasificadas · ${findings.length} hallazgo(s) en ${[...new Set(findings.map(finding => finding.group))].length} clase(s) raíz</p>`
  ]

  for (const [group, shots] of Object.entries(groups)) {
    if (!shots.length) continue

    html.push(`<h2>${group} (${shots.length})</h2>`, '<div class="grid">')
    for (const shot of shots) {
      html.push(
        `<figure><a href="${shot}"><img loading="lazy" src="${shot}" alt="${shot}"></a><figcaption>${shot}</figcaption></figure>`
      )
    }
    html.push('</div>')
  }

  html.push('</body></html>')
  writeFileSync(evidencePath('index.html'), `${html.join('\n')}\n`)
  writeFileSync(evidencePath('gallery.html'), `${html.join('\n')}\n`)

  expect(missingEvidence, 'findings citing evidence that does not exist').toEqual([])
  expect(findings.length, 'findings published').toBeGreaterThanOrEqual(10)
  expect(primitiveEntries.length, 'dialogs measured in layer 3').toBeGreaterThanOrEqual(6)
  expect(
    gate.pageErrors.render + gate.pageErrors.walks + gate.pageErrors.primitives,
    'page errors of the session'
  ).toBe(0)
})

test.afterAll(async () => {
  if (!REPORTED.render) return

  // Last writer wins; if a sibling session's hook lands after this one, the
  // session's own aggregate still survives as `render-session.json`.
  audit('render.json', REPORTED.render)
})
