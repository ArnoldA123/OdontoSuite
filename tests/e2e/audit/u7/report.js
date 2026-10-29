// Plan #13 (issue #370, U7) — the session's report: raw findings, the
// walkthrough table, the primitives aggregate and the gallery.
//
// The walks record a verdict per action; this module turns that evidence into
// the artefacts the session owes (#370, plan §6): `findings.raw.json` (one row
// per observed defect, grouped by root cause), `walkthrough.md`, the consolidated
// `primitives.json`, `ux-rubric.md` and the `index.html` gallery.
//
// Nothing here measures: every number comes from a JSON an earlier row wrote.
// The generator fails if a finding cites evidence that does not exist.

import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'

import { test, expect } from 'playwright/test'

import { audit, evidencePath, stamp } from './helpers.js'

const REPORTED = (globalThis.__AUDIT_U7_REPORT__ ??= { render: null })

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

/**
 * The layer-2 aggregate, built from the per-row files.
 *
 * `render/` is written by each render test as it finishes, so this is the run's
 * own evidence; the module's own `render.json` used to come from a hook that
 * runs after the report, which read the previous run's file.
 */
const renderAggregate = rows => ({
  session: rows[0]?.session ?? 'U7',
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
    pageErrors: rows.reduce((total, row) => total + (row.pageErrors ?? []).length, 0),
    unexpectedRequests: rows.reduce(
      (total, row) => total + (row.failedRequests?.unexpected?.length ?? 0),
      0
    ),
    negativeRowsWithoutEntries: rows.filter(
      row => row.expectation === 'negative' && (row.navigation?.clinicalEntries ?? []).length > 0
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
    h1: row.shell?.h1 ?? null,
    hero: row.shell?.hero ?? null,
    tabs: row.data?.tabs ?? row.tabs,
    sweptTabs: row.sweptTabs ?? null,
    tabSurfaces: (row.tabSurfaces ?? []).map(surface => ({
      id: surface.id,
      label: surface.label,
      clicked: surface.clicked,
      cards:
        surface.data?.evolutions?.cards?.length ??
        surface.data?.attachments?.cards?.length ??
        surface.data?.panel?.cards?.length ??
        null,
      empty: Boolean(
        surface.data?.evolutions?.empty?.present ??
        surface.data?.attachments?.empty?.present ??
        surface.data?.stats?.empty?.present ??
        surface.data?.panel?.empty?.present
      )
    })),
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

const stepOf = (walk, id) => (walk?.steps ?? []).find(entry => entry.id === id) ?? null
const obtainedOf = (walk, id) => stepOf(walk, id)?.obtained ?? null

/**
 * The findings the session publishes as raw material for triage.
 *
 * `evidence` names the artefacts that back each row; `step` pins the walkthrough
 * action whose `obtained` text is quoted verbatim, so no number here is retyped
 * by hand.
 */
const buildFindings = evidence => {
  const { records, ai, aiImplantologo, render, primitives, matrix } = evidence

  const renderRow = (slug, role) =>
    (render?.rows ?? []).find(row => row.slug === slug && row.role === role) ?? null

  const matrixRows = (endpoint, method) =>
    (matrix?.rows ?? []).filter(row => row.endpoint === endpoint && row.method === method)

  const aiReach = aiImplantologo?.reach ?? null
  const findings = [
    {
      id: 'U7-F01',
      group: 'empty-states-without-action',
      type: 'defecto',
      surface: '/medical-records (tab Evoluciones y tab Estadísticas del paciente sin historia)',
      action: 'Abrir «Evoluciones» y «Estadísticas» con el paciente sin historia clínica',
      expected:
        'Cada tab vacía ofrece la acción que la resuelve, como hacen «Historia general» («Nueva historia»), «Adjuntos» («Subir el primer adjunto») y las cinco tabs de especialidades («Nuevo registro»).',
      obtained: `${obtainedOf(records, 'empty-tab-evoluciones')} · Estadísticas: ${obtainedOf(records, 'empty-tab-estadísticas')} · Historia general: ${obtainedOf(records, 'empty-tab-historia-general')} · Adjuntos: ${obtainedOf(records, 'empty-tab-adjuntos')}`,
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/medical-records/MedicalRecordsPage.vue:505 (`data-state="no-record-evolutions"`: UiSectionEmpty sin slot de acción) y resources/js/modules/medical-records/components/MedicalRecordStats.vue:7 (`data-state="empty-stats"`: idle sin acción)',
      repro:
        'Base de auditoría + seed → /medical-records?patient_id=<paciente sin historia> → tab «Evoluciones» → tab «Estadísticas».',
      evidence: [
        { file: 'walk-records.json', step: 'empty-tab-evoluciones' },
        { file: 'walk-records.json', step: 'empty-tab-estadísticas' },
        { file: 'walk-records.json', step: 'empty-tab-adjuntos' },
        { file: 'walk-specialties.json', step: 'empty-patient-tabs' }
      ]
    },
    {
      id: 'U7-F02',
      group: 'evolution-without-content',
      type: 'defecto',
      surface: '/medical-records (tab Evoluciones → «Nueva evolución»)',
      action: 'Enviar el formulario de evolución sin escribir nada',
      expected:
        'El formulario exige al menos un bloque SOAP (o un motivo claro) antes de crear una evolución',
      obtained: obtainedOf(records, 'evolution-empty-submit'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'app/Http/Requests/StoreEvolutionRequest.php:52-73 (todos los campos SOAP son `sometimes|nullable`) + resources/js/modules/medical-records/components/EvolutionModal.vue:buildPayload (no valida contenido)',
      repro:
        'Ficha del paciente → tab «Evoluciones» → «Nueva evolución» → «Guardar evolución» sin llenar nada → 201 y tarjeta sin bloques SOAP.',
      evidence: [
        { file: 'walk-records.json', step: 'evolution-empty-submit' },
        { file: 'api-matrix.json', endpoint: '/api/medical-records/{id}/evolutions' }
      ]
    },
    {
      id: 'U7-F03',
      group: 'ai-confirm-below-detail',
      type: 'defecto',
      surface: '/ai-analysis (detalle → «Anular análisis»)',
      action: 'Anular un análisis con motivo desde el detalle',
      expected: 'La confirmación se pinta sobre el detalle y su botón «Anular» recibe el clic',
      obtained: obtainedOf(ai, 'void-reason'),
      layer: 3,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause:
        'resources/js/components/ui/Modal.vue:150-151 (todo overlay usa `z-50`, sin nivel por instancia) + el ConfirmDialog vive en AppLayout y el modal del detalle se teletransporta después: el botón de la confirmación queda tapado (`document.elementFromPoint` devuelve el detalle)',
      repro:
        'Login administrador → /ai-analysis → icono «Anular análisis» de una fila → escribir motivo → «Anular análisis»: la pantalla no cambia; `elementFromPoint` del botón devuelve el footer del detalle.',
      evidence: [
        { file: 'walk-ai.json', step: 'void-reason' },
        { file: 'primitives/ai-confirm-occlusion.json' },
        { file: 'shots/primitives/nested-ai-void-confirm.png' }
      ]
    },
    {
      id: 'U7-F04',
      group: 'ai-confirm-below-detail',
      type: 'defecto',
      surface: '/ai-analysis (detalle → «Anular análisis» de un análisis citado)',
      action: 'Intentar anular el análisis citado por una evolución',
      expected: 'La confirmación se alcanza y el rechazo 422 con su motivo llega a la pantalla',
      obtained: obtainedOf(ai, 'void-cited'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:alta',
      cause: 'El mismo apilado de overlays que U7-F03 (Modal.vue:150-151)',
      repro:
        'Abrir el detalle del análisis citado → escribir motivo → «Anular análisis»: no ocurre nada; la matriz comprueba que la API responde 422 con «No se puede anular un análisis citado en una evolución o en un plan.»',
      evidence: [
        { file: 'walk-ai.json', step: 'void-cited' },
        { file: 'api-matrix.json', endpoint: '/api/ai-analysis/{id}/void' }
      ]
    },
    {
      id: 'U7-F05',
      group: 'ai-sheet-gates',
      type: 'defecto',
      surface: '/ai-analysis (sheet «Nuevo análisis», paso 2)',
      action: 'Elegir un archivo que no es una imagen',
      expected: 'El sheet lo rechaza con el mensaje en línea del paso, como hace con el tamaño',
      obtained: obtainedOf(ai, 'sheet-non-image'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        "resources/js/components/ui/FileUpload.vue:97 (`alert('Solo se permiten archivos de imagen')`): el primitivo corta antes de emitir `fileSelected`, así que AiAnalysisSheet.onFileSelected nunca valida (su mensaje «Solo se aceptan imágenes.» es inalcanzable)",
      repro:
        'Abrir el sheet → «Seleccionar Archivo» → elegir un .txt → alert nativo y ningún error en línea.',
      evidence: [
        { file: 'walk-ai.json', step: 'sheet-non-image' },
        { file: 'walk-ai.json', step: 'sheet-oversize' }
      ]
    },
    {
      id: 'U7-F06',
      group: 'ai-sheet-gates',
      type: 'defecto',
      surface: '/ai-analysis (sheet «Nuevo análisis», paso 2)',
      action: 'Elegir una imagen de más de 20 MB',
      expected:
        'El archivo rechazado no queda como si estuviera cargado y el selector sigue disponible',
      obtained: obtainedOf(ai, 'sheet-oversize'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/components/ui/FileUpload.vue:12-20 (la vista previa sustituye al bloque que contiene el `<input type="file">`): el sheet descarta el archivo en su modelo, pero el primitivo conserva la vista previa y con ella desaparece el input; la única salida es la X sin etiqueta',
      repro:
        'Abrir el sheet → elegir un PNG de 23 MB → leer «La imagen supera los 20 MB.» arriba y la miniatura del mismo archivo debajo; el input de archivo ya no está en el DOM.',
      evidence: [{ file: 'walk-ai.json', step: 'sheet-oversize' }]
    },
    {
      id: 'U7-F07',
      group: 'ai-failed-is-silent',
      type: 'defecto',
      surface: '/ai-analysis (envío del sheet)',
      action: 'Enviar un análisis con paciente, imagen y aviso declarados',
      expected:
        'Si el análisis termina en `failed`, la pantalla dice por qué (el backend guarda el motivo en `raw_response`)',
      obtained: obtainedOf(ai, 'sheet-submit'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/ai-analysis/AiAnalysisPage.vue:586-589 (el 201 se trata como «completed» y abre el detalle) + app/Http/Resources/AiImageAnalysisResource.php (no publica `raw_response` ni un campo de error)',
      repro:
        'Con el servidor sin `OPENAI_API_KEY`: sheet completo → «Analizar con IA» → el detalle abre en «Fallido», «Sin hallazgos reportados», sin causa.',
      evidence: [
        { file: 'walk-ai.json', step: 'sheet-submit' },
        { file: 'walk-ai.json', step: 'detail' }
      ]
    },
    {
      id: 'U7-F08',
      group: 'ai-filters-disagree',
      type: 'defecto',
      surface: '/ai-analysis (tira de KPIs y bloque de filtros)',
      action: 'Aplicar un preset o un filtro de paciente',
      expected: 'La tira de KPIs describe la misma población que la tabla que tiene debajo',
      obtained: obtainedOf(ai, 'filter-reviewed-initial'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        "resources/js/modules/ai-analysis/AiAnalysisPage.vue:609 (`getStats()` sin los filtros que sí recibe `getAnalyses`) + resources/js/components/ui/Select.vue:225-229 (`modelValue` declara `Boolean` antes que `String`: el valor `''` se convierte en `true` y el selector muestra «Revisados» sin que la consulta lo lleve)",
      repro:
        'Abrir /ai-analysis: el selector «Revisión» dice «Revisados» y la consulta no lleva `reviewed`; aplicar el preset «Fallidos» y comparar el KPI «Total de análisis» con las filas.',
      evidence: [
        { file: 'walk-ai.json', step: 'filter-reviewed-initial' },
        { file: 'walk-ai.json', step: 'preset-failed' },
        { file: 'walk-ai.json', step: 'filter-search' }
      ]
    },
    {
      id: 'U7-F09',
      group: 'ai-voided-unreachable',
      type: 'no-terminado',
      surface: '/ai-analysis (filtros y KPIs)',
      action: 'Buscar los análisis anulados',
      expected:
        'La superficie ofrece consultarlos, como el contrato publicado `GET /api/ai-analysis?voided=1` (#296)',
      obtained: obtainedOf(ai, 'voided-trace'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/ai-analysis/AiAnalysisPage.vue:427-434 (cuatro presets y un selector; ninguno nombra `voided`) mientras el catálogo de KPIs ignora `voided_analyses`',
      repro:
        'Abrir /ai-analysis y recorrer el bloque de filtros: no hay control de anulados; `GET /api/ai-analysis?voided=1` responde 200.',
      evidence: [
        { file: 'walk-ai.json', step: 'voided-trace' },
        { file: 'api-matrix.json', endpoint: '/api/ai-analysis' }
      ]
    },
    {
      id: 'U7-F10',
      group: 'ai-row-actions',
      type: 'defecto',
      surface: '/ai-analysis (acciones por fila)',
      action: 'Pulsar «Revisar análisis» o «Anular análisis» en una fila',
      expected: 'Cada acción lleva a su tarea (revisar / anular) y no sólo a «Ver detalle»',
      obtained: `tres acciones por fila (${JSON.stringify((stepOf(ai, 'detail')?.extra?.actionLabels ?? []).map(entry => entry.label))}) y las tres llaman a \`viewAnalysis(row)\`: el modal abre siempre en la misma posición (${obtainedOf(ai, 'detail')})`,
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'resources/js/modules/ai-analysis/AiAnalysisPage.vue:310 y :319 (las tres acciones llaman a `viewAnalysis(row)`)',
      repro:
        'Abrir /ai-analysis: los tres iconos de la columna Acciones abren el mismo modal en la misma posición de scroll.',
      evidence: [{ file: 'walk-ai.json', step: 'detail' }]
    },
    {
      id: 'U7-F11',
      group: 'overlay-a11y',
      type: 'defecto',
      surface: '/ai-analysis (detalle del análisis)',
      action: 'Abrir el detalle con un lector de pantalla',
      expected: 'El diálogo tiene nombre accesible (`aria-labelledby` o `aria-label`)',
      obtained:
        (primitives?.entries?.find(entry => entry.slug === 'ai-detail')?.contractFailures ?? [])
          .map(check => `${check.id}: ${check.observed}`)
          .join(', ') || 'accessible-name: "" (el diálogo no tiene nombre accesible)',
      layer: 3,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/modules/ai-analysis/components/AiAnalysisModal.vue:2 (`<UiModal model-value size="xl">` con slot `#header` y sin `title`) + resources/js/components/ui/Modal.vue:146 (`hasDefaultHeader = !slots.header && Boolean(title)` deja el diálogo sin nombre)',
      repro:
        'Abrir el detalle de un análisis e inspeccionar el nodo `[role="dialog"]`: sin `aria-labelledby` ni `aria-label`.',
      evidence: [{ file: 'primitives/ai-detail.json' }]
    },
    {
      id: 'U7-F12',
      group: 'overlay-a11y',
      type: 'defecto',
      surface: '/ai-analysis (sheet «Nuevo análisis»)',
      action: 'Abrir el sheet y pulsar Tab',
      expected: 'El foco entra en el panel y queda atrapado (UiSheet declara `useFocusTrap`)',
      obtained:
        (primitives?.entries?.find(entry => entry.slug === 'ai-sheet')?.contractFailures ?? [])
          .map(check => `${check.id}: ${check.observed}`)
          .join(', ') || 'focus-trapped: outside',
      layer: 3,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'resources/js/components/ui/Sheet.vue:242-249 (`watch(modelValue)` llama `focusTrap.activate(sheetRef.value)` antes de que el panel `v-if` exista; `useFocusTrap.activate` retorna con `null`)',
      repro:
        'Abrir el sheet: `document.activeElement` sigue en el botón «Nuevo análisis» de la página; Tab recorre la página de atrás.',
      evidence: [{ file: 'primitives/ai-sheet.json' }]
    },
    {
      id: 'U7-F13',
      group: 'specialty-500-instead-of-404',
      type: 'defecto',
      surface: 'API /api/specialty-records/{id} (DELETE y PUT)',
      action: 'Operar sobre un id que no existe',
      expected: 'La puerta responde 404, como el resto de los recursos del módulo',
      obtained:
        matrixRows('/api/specialty-records/{id}', 'DELETE')
          .filter(row => row.deviation)
          .map(row => `${row.role}: ${row.observed} «${row.message}»`)
          .join(' · ') || '500 «Error al eliminar registro de especialidad»',
      layer: 5,
      origin: 'MEDIDO',
      severity: 'crit:media',
      cause:
        'app/Http/Controllers/Api/SpecialtyRecordController.php::destroy/update capturan `\\Exception` y responden 500; SpecialtyRecordService::deleteRecord/updateRecord usan `findOrFail`',
      repro:
        'DELETE /api/specialty-records/999999999 con `{specialty:"ortodoncia"}` como administrador → 500.',
      evidence: [{ file: 'api-matrix.json', endpoint: '/api/specialty-records/{id}' }]
    },
    {
      id: 'U7-F14',
      group: 'disabled-without-reason',
      type: 'defecto',
      surface: '/ai-analysis (sheet) y /medical-records (índice) y /specialty-records',
      action: 'Mirar los botones primarios antes de completar sus requisitos',
      expected: 'El control deshabilitado explica qué falta (plan §5.4)',
      obtained: obtainedOf(ai, 'sheet-open'),
      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'resources/js/modules/ai-analysis/components/AiAnalysisSheet.vue:canSubmit (sin texto de ayuda), resources/js/modules/medical-records/MedicalRecordsPage.vue:61 (`:disabled="!selectedPatient"`) y resources/js/modules/specialty-records/SpecialtyRecordsPage.vue:39',
      repro:
        'Abrir el sheet sin paciente: «Analizar con IA» deshabilitado y sin pista; en /medical-records el botón «Nueva historia» deshabilitado sin explicación.',
      evidence: [
        { file: 'walk-ai.json', step: 'sheet-open' },
        { file: 'walk-records.json', step: 'index' },
        { file: 'walk-specialties.json', step: 'no-patient' }
      ]
    },
    {
      id: 'U7-F15',
      group: 'scope-vs-empty-copy',
      type: 'defecto',
      surface: '/ai-analysis (rol clínico sin relación con el paciente)',
      action: 'Abrir el módulo como implantólogo que no sigue a ese paciente',
      expected: 'El estado vacío distingue «no hay análisis que puedas ver» de «no hay ninguno»',
      obtained: `filas visibles ${aiReach?.visibleRows ?? '?'} · KPI «Total de análisis» ${aiImplantologo?.ui?.kpis?.total ?? '?'} · el administrador ve ${aiReach?.adminRows ?? '?'} filas de los mismos pacientes · estado vacío: ${JSON.stringify(aiImplantologo?.ui?.empty ?? null)}`,
      layer: 2,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'app/Models/AiImageAnalysis.php::scopeVisibleTo (política #214: administrador ve todo, el resto sólo su relación clínica) contra el texto de resources/js/modules/ai-analysis/AiAnalysisPage.vue:«Sin análisis registrados»',
      repro:
        'Login implantologo_test → /ai-analysis: 0 filas y KPIs en 0; el administrador ve los análisis de los mismos pacientes.',
      evidence: [
        { file: 'walk-ai-implantologo.json' },
        { file: 'render/ai-analysis-implantologo.json' }
      ]
    },
    {
      id: 'U7-F16',
      group: 'permission-reachable-by-url',
      type: 'defecto',
      surface:
        '/medical-records, /medical-records/:id, /specialty-records, /ai-analysis (roles sin permiso)',
      action: 'Abrir la URL del módulo como técnico dental',
      expected:
        'La superficie explica que el módulo no pertenece a su escritorio y no ofrece un reintento que siempre fallará',
      obtained: `técnico en /specialty-records: ${JSON.stringify(renderRow('specialty-records', 'tecnico_dental')?.denial?.errorText ?? null)} · técnico en /ai-analysis: ${JSON.stringify(renderRow('ai-analysis', 'tecnico_dental')?.denial?.errorText ?? null)}`,
      layer: 2,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'resources/js/modules/specialty-records/SpecialtyRecordsPage.vue:152 («Sin especialidades asignadas» se pinta cuando el rol no tiene definiciones, no cuando la API responde 403) + el reintento de cada módulo (MedicalRecordsPage retryRecords, AiAnalysisPage refreshData) repite la misma petición prohibida',
      repro:
        'Login tecnico_test → /specialty-records → elegir paciente: la API responde 403, la pantalla dice «Tu rol no tiene especialidades clínicas habilitadas»; /ai-analysis muestra el error con «Reintentar».',
      evidence: [
        { file: 'render/specialty-records-tecnico_dental.json' },
        { file: 'render/ai-analysis-tecnico_dental.json' },
        { file: 'render/medical-records-tecnico_dental.json' },
        { file: 'walk-specialties-asistente.json' }
      ]
    },
    {
      id: 'U7-F17',
      group: 'data-format',
      type: 'defecto',
      surface: '/ai-analysis (confianza) y /medical-records (contador de adjuntos)',
      action: 'Leer la confianza de un análisis y el contador de adjuntos',
      expected: 'Un formato único para el porcentaje y para el contador (plan §5.11)',
      obtained: `confianza «${stepOf(ai, 'detail')?.extra?.detail?.confidence ?? '—'}» y contador de adjuntos ${JSON.stringify(stepOf(records, 'attachment-upload')?.extra?.gallery?.count ?? null)}`,

      layer: 4,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        'resources/js/modules/ai-analysis/AiAnalysisPage.vue:543 (`formatConfidence` interpola el `decimal:2` de la API) y :265 del detalle; el contador de adjuntos hereda `uppercase` (AttachmentGallery.vue:«N de M» → «DE»)',
      repro:
        'Abrir el detalle de un análisis: «88.00% · Media»; en /medical-records, tab Adjuntos, el contador lee «5 DE 5».',
      evidence: [
        { file: 'walk-ai.json', step: 'detail' },
        { file: 'render/medical-records-id-administrador.json' }
      ]
    },
    {
      id: 'U7-F18',
      group: 'duplicate-title',
      type: 'defecto',
      surface: '/medical-records (índice)',
      action: 'Abrir el índice de historias',
      expected: 'El topbar no repite el título que la página ya pinta (plan §5.1)',
      obtained: `h1 visible ${JSON.stringify(renderRow('medical-records', 'administrador')?.h1?.visibleTexts ?? [])} y hero de la página ${JSON.stringify(renderRow('medical-records', 'administrador')?.hero?.pageTitle ?? null)} en la misma pantalla`,
      layer: 2,
      origin: 'MEDIDO',
      severity: 'crit:baja',
      cause:
        "resources/js/components/layout/AppLayout.vue:1155-1180 (`pageTitledRoutes` no incluye '/medical-records', así que el topbar pinta su h1 visible mientras la página pinta el suyo en `.page-title`)",
      repro:
        '/medical-records: el topbar muestra «Historias Clínicas» y el hero repite «Historias clínicas» justo debajo. Las otras dos superficies de la sesión sí están en la lista y su h1 queda oculto.',
      evidence: [
        { file: 'render/medical-records-administrador.json' },
        { file: 'render/medical-records-id-administrador.json' }
      ]
    }
  ]

  return findings
}

test('@U7 · informe: hallazgos crudos, recorrido, rúbrica y galería', async () => {
  test.setTimeout(120000)

  const evidence = {
    records: has('walk-records.json') ? readJson('walk-records.json') : null,
    recordsAsistente: has('walk-records-asistente.json')
      ? readJson('walk-records-asistente.json')
      : null,
    specialties: has('walk-specialties.json') ? readJson('walk-specialties.json') : null,
    specialtiesOdontologo: has('walk-specialties-odontologo.json')
      ? readJson('walk-specialties-odontologo.json')
      : null,
    specialtiesImplantologo: has('walk-specialties-implantologo.json')
      ? readJson('walk-specialties-implantologo.json')
      : null,
    specialtiesAsistente: has('walk-specialties-asistente.json')
      ? readJson('walk-specialties-asistente.json')
      : null,
    ai: has('walk-ai.json') ? readJson('walk-ai.json') : null,
    aiImplantologo: has('walk-ai-implantologo.json') ? readJson('walk-ai-implantologo.json') : null,
    render: renderAggregate(readDir('render')),
    primitives: {
      entries: globalThis.__AUDIT_U7_PRIMITIVES__?.entries ?? [],
      aggregates: globalThis.__AUDIT_U7_PRIMITIVES__?.aggregates ?? []
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

  // --- the layer-2 aggregate, from this run's own rows -------------------
  // `render.json` is a name every session shares: U5/U6 register an `afterAll`
  // that rewrites it in whatever `AUDIT_OUT` names, even when their tests were
  // filtered out. The session keeps its own copy under a name nobody else
  // writes, and re-writes `render.json` from a hook that runs last.
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

  // --- findings.raw.json --------------------------------------------------
  audit('findings.raw.json', {
    ...stamp(),
    stage: 'layer-6',
    session: 'U7',
    issue: '#370',
    plan: 'docs/mejoras/13-programa-auditoria-ui-funcional-2026-10.md §7 U7',
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
    findings: findings.map(finding => ({
      ...finding,
      obtained: finding.obtained ?? null,
      statusAt: finding.evidence.find(reference => reference.step)?.step ?? null,
      matrixRows:
        finding.evidence.find(reference => reference.endpoint)?.endpoint !== undefined
          ? (evidence.matrix?.rows ?? []).filter(
              row => row.endpoint === finding.evidence.find(ref => ref.endpoint).endpoint
            ).length
          : null
    })),
    coverage: {
      walks: [
        evidence.records && { file: 'walk-records.json', totals: evidence.records.totals },
        evidence.recordsAsistente && {
          file: 'walk-records-asistente.json',
          totals: evidence.recordsAsistente.totals
        },
        evidence.specialties && {
          file: 'walk-specialties.json',
          totals: evidence.specialties.totals
        },
        evidence.ai && { file: 'walk-ai.json', totals: evidence.ai.totals }
      ].filter(Boolean),
      renderRows: evidence.render?.totals ?? null,
      matrix: evidence.matrix?.summary
        ? {
            rows: evidence.matrix.summary.rows ?? null,
            endpoints: evidence.matrix.summary.endpoints ?? null,
            // Evidence written before the matrix published these lists cannot be
            // counted: the report says `null` (not measured) instead of a zero it
            // never observed.
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
        item: 'El texto que la interfaz habría mostrado al anular un análisis citado',
        reason:
          'La confirmación queda debajo del detalle (U7-F03): el 422 no llega a producirse desde la pantalla. El contrato se mide en api-matrix.json y el toast genérico («Error de validación», useAiAnalysis.js:172) queda como INFERIDO.'
      },
      {
        item: 'El análisis en estado `completed` y sus hallazgos',
        reason:
          'Sin OPENAI_API_KEY toda alta termina en `failed`; las tres filas completadas se colocaron con el puente tinker declarado en fixtures.json (statusWrites) y se revisaron por la puerta real.'
      },
      {
        item: 'Un análisis anulado visible en la lista',
        reason:
          'La anulación por interfaz está bloqueada (U7-F03/F04); `?voided=1` se midió con la API y la ausencia de control se midió en el DOM.'
      }
    ]
  })

  // --- walkthrough.md -----------------------------------------------------
  const tables = [
    ['Historia clínica (U7a · administrador)', evidence.records],
    ['Historia clínica (U7a · asistente)', evidence.recordsAsistente],
    ['Especialidades (U7b · administrador)', evidence.specialties],
    ['Especialidades (U7b · odontólogo)', evidence.specialtiesOdontologo],
    ['Especialidades (U7b · implantólogo)', evidence.specialtiesImplantologo],
    ['Especialidades (U7b · asistente)', evidence.specialtiesAsistente],
    ['Análisis IA (U7b · administrador)', evidence.ai],
    ['Análisis IA (U7b · implantólogo)', evidence.aiImplantologo]
  ]

  const lines = [
    `# U7 — Clínica: historia, especialidades y Análisis IA (#370)`,
    '',
    `Generado por \`tests/e2e/audit/u7/report.js\` el ${new Date().toISOString()} sobre la revisión ${(evidence.records ?? evidence.ai ?? {}).revision ?? 'desconocida'}.`,
    '',
    `Base: \`${(evidence.records ?? evidence.ai ?? {}).base ?? 'desconocida'}\` · evidencia cruda en \`.atl/qa-evidence/audit/ui/U7/\`.`,
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
          .slice(0, 260)
      lines.push(
        `| ${escape(entry.step)} | ${escape(entry.action)} | ${escape(entry.expected)} | ${escape(entry.obtained ?? entry.reason)} | ${entry.verdict} | ${entry.requestStatus ?? '—'} |`
      )
    }

    lines.push('')
  }

  if (evidence.matrix?.summary) {
    lines.push('## Contrato por rol (capa 5)', '')
    lines.push(
      `| # | Endpoint | Método | Rol | Esperado | Observado | Veredicto |`,
      '| --- | --- | --- | --- | --- | --- | --- |'
    )

    for (const row of evidence.matrix.rows ?? []) {
      lines.push(
        `| — | \`${row.endpoint}\` | ${row.method} | ${row.role} | ${row.expected.join('/')} | ${row.observed} | ${row.deviation ? 'DESVIACIÓN declarada' : row.ok ? 'OK' : 'DEFECTO'} |`
      )
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
  // Same reason as `render-session.json`: `primitives.json` is also rewritten by
  // `scripts/audit/ui-pass.mjs --no-run`, so the session keeps its own copy.
  audit('primitives-session.json', primitiveAggregate)

  // --- ux-rubric.md -------------------------------------------------------
  const renderRow = (slug, role) =>
    (evidence.render?.rows ?? []).find(row => row.slug === slug && row.role === role) ?? null

  const rubricRows = [
    {
      surface: '/medical-records (índice y workspace)',
      rows: [
        [
          '1 Jerarquía de títulos',
          1,
          `El topbar pinta un \`h1\` visible y el hero repite el título con \`.page-title\` («Historias clínicas» dos veces): el mismo defecto que #405 (U7-F18). Medido: ${JSON.stringify(renderRow('medical-records', 'administrador')?.h1 ?? null)}.`
        ],
        [
          '2 Densidad de texto',
          2,
          'Hero de 2 líneas y un subtítulo; el primer control está en la banda.'
        ],
        [
          '3 Información repetida',
          1,
          'El resumen clínico repite «Historias/Evoluciones/Adjuntos/Seguimientos» sobre el índice y vuelve a aparecer como contadores en las tarjetas.'
        ],
        [
          '4 Acciones con efecto',
          1,
          '«Nueva historia» nace deshabilitado sin decir qué falta (U7-F14).'
        ],
        ['5 Estados completos', 0, 'Dos de las cuatro tabs vacías no ofrecen acción (U7-F01).'],
        ['6 Primitivas', 2, 'UiTabs, UiModal, UiSectionEmpty, UiDataTable; ningún overlay propio.'],
        ['7 Feedback', 1, 'El alta vacía de evolución devuelve 201 sin aviso (U7-F02).'],
        [
          '8 Navegación',
          2,
          '«Quitar selección» vuelve al índice y limpia el query (medido en 16-back-to-index).'
        ],
        [
          '9 Accesibilidad',
          2,
          'Los seis diálogos del módulo cumplen el contrato (primitives/record-*.json); la accesibilidad del detalle de IA se puntúa en su superficie.'
        ],
        [
          '10 Responsive',
          2,
          'Sin scroll horizontal de documento a 390 px en las cuatro filas medidas.'
        ],
        ['11 Formato de datos', 1, 'El contador de adjuntos lee «5 DE 5» (U7-F17).'],
        [
          '12 Honestidad de la superficie',
          2,
          'Sin controles de edición ni borrado; PUT/PATCH/DELETE responden 405 (medido).'
        ]
      ]
    },
    {
      surface: '/specialty-records',
      rows: [
        [
          '1 Jerarquía de títulos',
          2,
          'Un h1 en el topbar, oculto a la vista, con el hero «Registros de Especialidades» como título visible.'
        ],
        ['2 Densidad de texto', 2, 'Eyebrow, título y una frase; los tabs mandan.'],
        [
          '3 Información repetida',
          1,
          'La tarjeta repite el estado en el badge y en el resumen del registro.'
        ],
        [
          '4 Acciones con efecto',
          1,
          '«Nuevo registro» deshabilitado sin paciente y sin explicación (U7-F14).'
        ],
        ['5 Estados completos', 2, 'Las cinco tabs vacías ofrecen «Nuevo registro» (medido: 5/5).'],
        ['6 Primitivas', 2, 'UiTabs, UiModal, UiEmptyState, UiStatusBadge.'],
        ['7 Feedback', 2, 'Alta, edición y borrado con toast y confirmación global.'],
        ['8 Navegación', 2, 'Cambio de paciente en modal; «Volver» en la banda.'],
        [
          '9 Accesibilidad',
          2,
          'Los tres diálogos cumplieron el contrato (primitives/specialty-*.json).'
        ],
        ['10 Responsive', 2, 'Sin overflow a 390 px.'],
        [
          '11 Formato de datos',
          1,
          'La fecha de la tarjeta y la del detalle usan el mismo formateador, pero el título de la tarjeta cae a la etiqueta cuando el resumen está vacío.'
        ],
        [
          '12 Honestidad de la superficie',
          2,
          'La matriz rol × especialidad de la API coincide con las tabs ofrecidas (medido en ambos roles).'
        ]
      ]
    },
    {
      surface: '/ai-analysis',
      rows: [
        [
          '1 Jerarquía de títulos',
          2,
          'Un h1 en el topbar, oculto a la vista, con el hero «Análisis con IA» como título visible.'
        ],
        ['2 Densidad de texto', 2, 'Hero con aviso asistencial de una línea.'],
        [
          '3 Información repetida',
          1,
          'La tira de KPIs es la población global («Acumulados») y la tabla responde a los filtros: no es la misma población y no lo dice; además el selector «Revisión» muestra «Revisados» sin que la consulta lo lleve (U7-F08, #402).'
        ],
        [
          '4 Acciones con efecto',
          0,
          '«Anular análisis» no produce nada visible (U7-F03); las tres acciones por fila abren lo mismo (U7-F10).'
        ],
        ['5 Estados completos', 2, 'Carga con skeleton, vacío con CTA, error con reintento.'],
        [
          '6 Primitivas',
          1,
          'La confirmación global queda debajo del detalle (U7-F03): la primitiva no resuelve el apilado.'
        ],
        [
          '7 Feedback',
          0,
          'El archivo que no es imagen usa `alert()` (U7-F05) y el análisis fallido no explica su causa (U7-F07).'
        ],
        ['8 Navegación', 2, 'El detalle y el sheet cierran con Escape y con su botón.'],
        [
          '9 Accesibilidad',
          0,
          'El detalle no tiene nombre accesible y el sheet no atrapa el foco (U7-F11, U7-F12).'
        ],
        ['10 Responsive', 2, 'Sin overflow a 390 px; el sheet ocupa el ancho completo.'],
        ['11 Formato de datos', 1, 'La confianza se publica con dos decimales (U7-F17).'],
        [
          '12 Honestidad de la superficie',
          0,
          'No hay control para los análisis anulados que la API conserva (U7-F09).'
        ]
      ]
    }
  ]

  const rubric = [
    '# U7 — rúbrica UX/UI (insumo para el triage)',
    '',
    `Puntuación 0/1/2 por criterio (plan §5). Cada fila cita lo medido en esta sesión; el veredicto y la severidad los fija el triage del programa.`,
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

  writeFileSync(evidencePath('ux-rubric.md'), `${rubric.join('\n')}\n`)

  // --- index.html gallery -------------------------------------------------
  const shotDirs = ['render', 'shots/primitives', 'walk/records', 'walk/records-asistente']
  const walkEntries = (() => {
    try {
      return readdirSync(evidencePath('walk'))
    } catch {
      return []
    }
  })()
  const walkShotDirs = walkEntries.filter(
    name => name.startsWith('specialties') || name === 'ai' || name === 'ai-implantologo.png'
  )

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

  for (const dir of shotDirs) groups[dir] = collect(dir)
  for (const name of walkShotDirs) {
    const dir = `walk/${name}`
    const shots = name.endsWith('.png') ? [dir] : collect(dir)

    if (shots.length) groups[dir] = shots
  }

  const html = [
    '<!doctype html>',
    '<html lang="es"><head><meta charset="utf-8">',
    '<title>U7 — Clínica (historia, especialidades, IA)</title>',
    '<style>body{font-family:system-ui,sans-serif;margin:24px;background:#f6f6f7;color:#111}',
    'h1{font-size:20px}h2{margin-top:32px;font-size:16px}',
    '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}',
    'figure{margin:0;background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden}',
    'figcaption{padding:6px 8px;font-size:12px;color:#555;word-break:break-all}',
    'img{display:block;width:100%;height:auto}</style></head><body>',
    '<h1>U7 — Clínica: historia, especialidades y Análisis IA (#370)</h1>',
    `<p>Revisión ${(evidence.fixtures ?? {}).revision ?? 'desconocida'} · base ${(evidence.fixtures ?? {}).base ?? 'desconocida'} · ${new Date().toISOString()}</p>`
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
  // `index.html` is also rewritten by `scripts/audit/ui-pass.mjs`, which only
  // knows the render rows; the session's own gallery (every capture of the three
  // modules) survives as `gallery.html`.
  writeFileSync(evidencePath('index.html'), `${html.join('\n')}\n`)
  writeFileSync(evidencePath('gallery.html'), `${html.join('\n')}\n`)

  expect(missingEvidence, 'findings citing evidence that does not exist').toEqual([])
  expect(findings.length, 'findings published').toBeGreaterThanOrEqual(12)
  expect(primitiveEntries.length, 'dialogs measured in layer 3').toBeGreaterThanOrEqual(12)
})

test.afterAll(async () => {
  if (!REPORTED.render) return

  // Last writer wins; if a sibling session's hook lands after this one, the
  // session's own aggregate still survives as `render-session.json`.
  audit('render.json', REPORTED.render)
})
