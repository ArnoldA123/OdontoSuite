# Task: historias clínicas — seguimientos #222 #223 #225 #228 #224

Feature branch: `feat/medical-records-followups` (worktree `~/proyectos/odontosuite-records`).
Issues: #222 (clinical_measurements), #223 (eventos de evolución), #225 (especialidad corregible), #228 (varias historias), #224 (cobertura de flujos en navegador/componente).

## Specs

S1 (#222) Registrar `clinical_measurements` en la superficie de evoluciones.
1. Definir el contrato de `clinical_measurements` (claves y unidades) y validarlo en `StoreEvolutionRequest` (`clinical_measurements.*`).
2. Capturarlo en el modal y mostrarlo en la tarjeta de la evolución.
3. Tests: feature para la validación y contrato estático/JS para el render.

S2 (#223) Emitir evento en tiempo real al editar o eliminar una evolución.
1. Eventos `ClinicalEvolutionUpdated` / `ClinicalEvolutionDeleted` (broadcast como los existentes) y listeners en el cliente dentro de `MedicalRecordsPage.vue`.
2. Solo refrescar cuando el evento pertenece al paciente seleccionado (misma regla que hoy).
3. Test de feature que verifique el dispatch y que el borrado lleve el `medical_record_id` para que el cliente pueda filtrar.

S3 (#225) Permitir corregir la especialidad de una evolución.
1. Definir la fuente de especialidades (tabla `specialties` o el pivote `user_specialties`) y exponerla al formulario.
2. Validar el valor contra esa fuente en `StoreEvolutionRequest` en lugar de aceptar cualquier string.
3. Selector en `EvolutionModal.vue` (crear y editar) y actualización del timeline.
4. Tests de feature para la validación y de contrato para el formulario.

S4 (#228) Elegir qué historia del paciente alimenta las pestañas cuando hay más de una.
1. Selector de historia (o evolución del layout) cuando `records.length > 1`, con la historia activa visible en el encabezado de cada pestaña.
2. Evoluciones y adjuntos siguen a la historia elegida; los adjuntos pueden quedarse a nivel paciente con una nota, pero debe ser explícito.
3. Cargar todas las historias del paciente (o paginar) en lugar del tope silencioso de 50.
4. Test de contrato y de comportamiento para el caso de dos historias.

S5 (#224) Cubrir en navegador las superficies y los modales de historias clínicas.
1. Suite de navegador (Playwright) o de componente con `@vue/test-utils` que ejercite esos flujos con la API mockeada.
2. Al menos: crear historia (422 mapeado por campo), crear evolución con seguimiento, borrar con confirmación, subir adjunto con límite de 10 MB.
3. Integrada en el job `quality` de CI.

## Tasks

- T1 · S1 · backend+frontend · commits `8e31dff`, `a119c32`: contrato + validación + modal + timeline + tests
- T2 · S2 · backend+frontend · commit `f6ea29f`: eventos updated/deleted + dispatch + listeners + tests
- T3 · S3 · backend+frontend · commit `3a64a69`: catálogo de especialidades + validación + selector + timeline + tests
- T4 · S4 · frontend · commit `8986303`: carga paginada de historias + selector activo + alcance de pestañas + tests
- T5 · S5 · commit `527538c`: suite de componente de flujos (delegada a `gentle-ai-worker`, superficie única `tests/js/smoke/medical-records-flows.test.js`)
- T6 · docs · commit `830a3f9`: AGENTS.md §12 + guard doc-drift

## Log

L1 (solicitud del usuario, verbatim): «quiero hacer issues #222 #223 #225 #228 #224 desarrolles, en un worktree nuevo».

L2 (evidencia): issues leídos con `gh issue view`; los cinco abiertos, etiqueta `crit:baja`, ninguno asignado. #222/#223/#228 son `type:no-terminado`, #225 `type:feature`, #224 `type:verificacion-ausente`.

L3 (decisión S1 — contrato de `clinical_measurements`): JSON plano con cinco medidas periodontales de uso general, unidades en el nombre de la clave para que viajen con el dato:
`pocket_depth_mm` (numérico, 0-20 mm), `gingival_recession_mm` (numérico, 0-20 mm),
`plaque_index_percent` (numérico, 0-100), `bleeding_on_probing` (booleano),
`tooth_mobility` (entero 0-3, grados de Miller). Las cinco son opcionales y la
sección se omite si no se registra ninguna. Sin migración: la columna JSON ya existe.

L4 (decisión S2 — payload del borrado): `ClinicalEvolutionDeleted` transmite
`evolution_id`, `medical_record_id` y `patient_id`; el modelo soft-deleted se
serializa antes de despachar, porque después del `delete()` la fila ya no es
legible por la relación. `ClinicalEvolutionUpdated` reutiliza la forma del
evento creado (`evolution` con `medicalRecord.patient` y `createdBy`).

L5 (decisión S3 — fuente de especialidades): catálogo maestro `specialties`
(el endpoint existente `GET /api/specialties/active` alimenta el selector).
La evolución guarda el `code` (compatible con la columna string(50) y con el
`users.specialty` legacy). Validación `Rule::exists('specialties', 'code')` —
cualquier estado, para que un valor histórico desactivado siga siendo válido al
reeditar; el selector sólo ofrece activas. Si la especialidad guardada no está
en el catálogo cargado, el modal no reenvía el campo y preserva el valor actual.
El timeline muestra el `name` del catálogo con fallback al `code`.

L6 (decisión S4 — alcance de pestañas): Evoluciones sigue a la historia activa
(`currentRecord`); Adjuntos y Estadísticas siguen al paciente y lo dicen en una
nota explícita. La carga del workspace pagina el índice del paciente hasta
`meta.last_page` (per_page 50) en vez de truncar en 50.

L7 (decisión S5 — runner): suite de componente con `@vue/test-utils` dentro de
Vitest, no Playwright: el job `quality` ya ejecuta `pnpm test:js`, así que la
integración en CI no requiere pasos nuevos ni descarga de navegadores. Los
flujos se ejercitan con la API mockeada, como pide el issue.

L8 (entorno): la base `odontosuite_test` que fija `phpunit.mysql.xml` está
compartida con otras sesiones activas del repositorio (worktrees
`quotations-contract` y otros procesos `phpunit`), y un `migrate:fresh`
concurrente rompió una migración. La verificación de esta feature usa
`DB_DATABASE=odontosuite_records_test` (base propia) contra el MySQL local por
socket, sin editar `phpunit.mysql.xml`.

L9 (routing T5): la suite de flujos se delega a `gentle-ai-worker` en
background con una sola superficie editable
(`tests/js/smoke/medical-records-flows.test.js`); el parent conserva la
verificación y el commit.

L10 (evidencia de cierre):
- PHP MySQL completo: 1728 tests / 8313 aserciones, exit 0 (`odontosuite_records_test`).
- PHP SQLite: 1279 passed / 449 skipped, exit 0 (skips por diseño, #170).
- JS: 94 archivos / 767 tests, exit 0; el nuevo archivo aporta 7 flujos.
- `pnpm build`: exit 0. `scripts/audit/doc-drift.mjs`: 12/12 checks.
- Formato: Pint, ESLint y Prettier en verde sobre el árbol.

L11 (estrategia de entrega): el trabajo queda en 7 commits sobre
`feat/medical-records-followups`, uno por issue (T1 en dos slices backend /
frontend) más el changelog. No hay PR abierto: push, PR y merge son decisión
del dueño. La revisión debe hacerse commit por commit.

L12 (cierre y entrega):
- Rebase sobre `origin/main` 83a5902 (otros agentes mergearon #230–#241); único
  conflicto en `AGENTS.md` §12, resuelto conservando las entradas de main y la
  de esta feature. Doc-drift verde con los conteos de main (197 rutas API).
- Hallazgo fuera de alcance: `MedicalRecordService::deleteAttachment()` no se
  usa y el endpoint deja el archivo físico huérfano en storage. Issue #244
  creada con evidencia y criterio de aceptación.
- Hallazgo pequeño resuelto aquí: el upload rechazaba la categoría `general`
  que el servicio ya defaultea y el filtro ya acepta. Regla ampliada y dos
  tests en `MedicalRecordAttachmentTest`; commit `b08dfec`.
- Verificación post-rebase: JS 103 archivos / 845 tests; Pint 618 archivos;
  ESLint 0 errores; Prettier OK. MySQL y SQLite completos + build en curso.

L13 (hallazgos adyacentes resueltos antes del PR):
- `QuotationErrorEnvelopeTest` (de main, #238) no declaraba `RefreshDatabase`:
  el runner SQLite local moría con 5 `QueryException` en vez de skipear como
  el resto de tests con BD (#170). Añadido el trait: SQLite 5 skipped, MySQL
  5/5. Commit `8ce0400`.
- Sin otros hallazgos accionables dentro del alcance.

L14 (entrega): rama rebasada sobre `origin/main` 83a5902, pusheada y PR #245
creado (base main). CI en observación; pendiente merge y limpieza del worktree.

L15 (CI del PR #245): la suite MySQL del runner falló en dos tests propios por
el orden canónico de claves que MySQL 8 impone al objeto JSON: `assertSame`
compara orden y MariaDB local conserva el del request. Los valores eran
idénticos. Se normaliza con `ksort` en ambos lados y se mantiene la
comparación estricta de valores y tipos (commit `4236914`). Quality y
frontend-build pasaron en el primer run.

L16 (cierre): PR #245 mergeado en main como `b7b1171` (2026-10-21T00:27:27Z).
#222, #223, #225, #228 y #224 cerrados por el merge. #244 queda abierta para
decidir el archivo huérfano del borrado de adjuntos. Rama remota eliminada,
worktree `~/proyectos/odontosuite-records` limpiado y rama local
borrada.
