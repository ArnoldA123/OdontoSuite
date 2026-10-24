# F3 — Historia clínica inmutable: addenda, sin borrado y auditoría de lectura

Issue #292 (épica #294). Fase F3; depende de F1 (#281) y F2 (#282), ambas en
`main` (`e59f248`). Rama `feat/f3-historia-clinica-addenda`, worktree
`~/proyectos/odontosuite-f3`.

## Specs

S1. «Ninguna ruta permite eliminar una evolución o una historia.»

S2. «Corregir crea addendum con autor, fecha y motivo; el original queda
intacto.»

S3. «Toda lectura de historia queda registrada.»

S4. «Un registro clínico no debe poder eliminarse.» (Brecha de la issue:
`ClinicalEvolution` y `MedicalRecord` usan SoftDeletes.)

S5. «Se registra quién la leyó y quién la modificó.»

Fuera de alcance, declarado en la issue: retención y archivo por antigüedad
(migración de expedientes), diagnóstico y plan por atención, y firma digital
(F6). La issue es `area:backend`; la interfaz se ajusta solo donde el contrato
anterior rompía (editar/eliminar).

## Tasks

- T1 — Esquema y modelo: tabla `clinical_addenda`, modelo `ClinicalAddendum`
  inmutable, relaciones en `MedicalRecord` y `ClinicalEvolution`. Specs: S4.
  Commit: `bdae73d`.
- T2 — Borrado fuera del contrato: se retiran `DELETE /medical-records/{id}` y
  `DELETE /medical-records/evolutions/{evolution}`, sus métodos, el servicio
  `deleteEvolution()` y el evento `ClinicalEvolutionDeleted`; los dos modelos
  pierden SoftDeletes y rechazan `deleting`. Specs: S1, S4. Commit: `a27df0c`.
- T3 — Addendum como única corrección: `POST
  /api/medical-records/{id}/addenda` y `POST
  /api/medical-records/evolutions/{evolution}/addenda`; se retiran `PUT` y
  `PATCH` de la historia y de la evolución; autor, fecha y motivo obligatorios;
  evento `ClinicalAddendumCreated`; auditoría de autoría. Specs: S2, S5.
- T4 — Auditoría de lectura en las cinco superficies que exponen contenido
  clínico (`index`, `show`, `evolutions`, `attachments`, `stats`). Specs: S3,
  S5. T3+T4: commit `dd3d4a4`.
- T5 — Interfaz: "Agregar addendum" reemplaza editar/eliminar en timeline,
  tarjetas, detalle y página; los addenda se leen junto al original. Specs: S1,
  S2. Commit: `ac3a986`.
- T6 — Documentación y guardas: AGENTS.md §4/§6/§12, `doc-drift`, matriz de
  rutas y suite completa. Specs: S1-S5. Commit: `c3a026f`.

## Contrato decidido

**Addendum.** Tabla `clinical_addenda` con `medical_record_id` (obligatorio),
`clinical_evolution_id` (nullable: un addendum puede corregir la historia o una
evolución concreta), `user_id` (autor), `reason` (motivo, obligatorio, ≤255) y
`content` (texto de la corrección, obligatorio, ≤5000). Sin `updated_at`: un
addendum no se edita. El modelo rechaza `updating` y `deleting`.

**Corrección.** El original nunca se toca: `PUT`/`PATCH` de historia y de
evolución desaparecen y la única escritura posterior al alta es un addendum.
El autor sale de la sesión, la fecha de `created_at` y el motivo del cuerpo. Un
addendum se escribe en transacción con su entrada de auditoría.

**Borrado.** Los dos modelos clínicos pierden `SoftDeletes` y declaran una
guarda `deleting` que lanza `ClinicalRecordImmutableException`; las columnas
`deleted_at` quedan en la base (no se destruye esquema), así que una fila
marcada como borrada por la regla anterior vuelve a ser parte de la historia.
La desactivación (`is_active = false`) también sale de la API: era el `destroy`
de la historia.

**Lectura auditada.** `audit_logs` con acción `medical_record_viewed`,
`auditable` = la historia y `metadata.surface`:
`index | record | evolutions | attachments | stats`. El índice registra una
entrada por historia devuelta en la página; las superficies por paciente
registran una por historia activa. La escritura de auditoría no se silencia: si
falla, la lectura falla.

**Autoría.** El addendum guarda su propia entrada `clinical_addendum_created`
con `auditable` = la historia, para que "quién la modificó" se lea en el mismo
registro que "quién la leyó". Altas y evoluciones conservan su autor en
`created_by`.

### Contrato implementado (consecuencias y desvíos)

- `GET /medical-records/{id}/evolutions` responde **404** para una historia
  inexistente (antes: 200 con lista vacía): la historia es la entidad auditada
  y una lectura sin sujeto no se registra. Ningún consumidor dependía del 200
  vacío (suite completa en verde).
- La evolución responde **404** a `PUT`/`DELETE`: al retirar su último verbo la
  URI deja de existir, así que no hay 405 que devolver. La guarda primaria es la
  tabla de rutas (`test_no_route_deletes_a_history_or_an_evolution` y la
  aserción de que no queda `PUT/PATCH` bajo `medical-records`); el test de
  método sobre la historia sí observa 405 porque conserva `GET`.
- No hay permisos nuevos: la corrección de la historia viaja con
  `manage.medical_record` y la de una evolución con `manage.evolution`.
  `CREDENTIALS.md` no cambia.
- Sanitización del addendum: `strip_tags` + `Str::limit` (motivo 255, contenido
  5000), el mismo patrón que `StoreEvolutionRequest`.
- Auditoría de lectura: acción `medical_record_viewed`, `auditable` = la
  historia y `metadata.surface`. El índice escribe una entrada por historia
  devuelta en la página; `show` y `evolutions` una por la historia abierta;
  `stats` y `attachments` una por historia activa del paciente. La escritura no
  se silencia: si falla, la lectura falla.
- Auditoría de autoría: `clinical_addendum_created` con `auditable` = la
  historia y `new_values.reason`, dentro de la misma transacción que el
  addendum.
- Las filas que todavía llevan `deleted_at` de la regla anterior vuelven a
  contar en la historia (consecuencia deliberada de S4). La columna no se
  elimina: no se destruye esquema.
- `usePermissions` pierde `deleteMedicalRecord`, `editEvolution` y
  `deleteEvolution`. La corrección se gatea con `editMedicalRecord` (historia) y
  `addEvolution` (evolución), que ya existían.
- Eventos: se retiran `MedicalRecordUpdated`, `ClinicalEvolutionUpdated` y
  `ClinicalEvolutionDeleted` (sin publicador tras el cambio) y nace
  `ClinicalAddendumCreated` en el canal privado `medical-records`.

### Frontera de slices

`bdae73d` (T1, 339 líneas) es un cambio de esquema con su modelo: partirlo
dejaría una tabla sin dueño. `a27df0c` (T2, 542) retira el borrado de ruta,
modelo, servicio, evento e interfaz en una sola unidad verificable.
`dd3d4a4` (T3+T4, 1191, de las cuales ~700 son pruebas nuevas) reescribe el
contrato de escritura y añade la auditoría de lectura sobre el mismo
controlador; viaja junto porque ambas tocan las mismas cinco superficies.
`ac3a986` (T5, 1154) migra la interfaz. `c3a026f` (T6) sincroniza la
documentación. La entrega es **un PR con cinco commits** (convención F1/F2) y la
revisión se hace por unidad, no por el acumulado; la descripción del PR publica
este mapa.

### Verificación final

- Suite PHP completa en MySQL: OK (2177 tests, 12680 assertions, tras el fix del envelope).
- Vitest completo: 907 tests en 109 archivos.
- `vite build`, `eslint --quiet`, `prettier --check .`, `pint --test` (695
  archivos), `doc-drift` (14/14) y `sdd:check-events` (35 eventos, 0
  huérfanos): verdes.

## Log

L1. Usuario: "haz la issue 292, en worktree nuevo".

L2. Worktree: `~/proyectos/odontosuite-f3`, rama
`feat/f3-historia-clinica-addenda`, base `e59f248` (merge de F2).

### Entrega

PR #300 (`feat/f3-historia-clinica-addenda` → `main`) fusionado con merge
commit `cdc5243` el 2026-10-22, seis commits y revisión por unidad. La rama y
los worktrees de trabajo y de revisión quedaron retirados. CI del PR en verde en cada push: `Quality gates`,
`Backend tests (MySQL)` y `Frontend build` pasan también tras el fix
`3322ba7` (runs 39829434174 y 39829441594).

### Verificación independiente (gentle-ai-verify)

PASS en S1–S5 y en los nueve chequeos, con evidencia ejecutada (tabla de rutas
real, filas completas antes/después, cinco superficies de auditoría, 403 de
recepción con 0 filas, cero referencias de la interfaz a los endpoints
retirados, guards verdes). Corrió los 28 tests de los filtros F3 (OK) y Vitest
completo (109 archivos / 907 tests). Hallazgos informativos, ninguno
bloqueante; los cuatro primeros son preexistentes y quedan como seguimientos
sin cambio en esta rama:

1. `MedicalRecord::query()->delete()` o `DB::table(...)->delete()` eluden el
   hook `deleting` de Eloquent. Ningún código de la aplicación lo hace.
2. `medical_records.patient_id` cae en `cascadeOnDelete` a nivel de BD;
   `PatientController::destroy` se niega a borrar un paciente con historias.
3. `app/Support/ClinicalAccess.php` sigue filtrando `whereNull('deleted_at')`
   sobre `clinical_evolutions` (analisis IA, #214): una fila legacy con la marca
   antigua es invisible como relación de acceso, aunque cuenta en la historia.
   No se toca aquí porque ampliar el alcance de acceso no lo pide la issue y no
   tiene prueba propia.
4. Los `catch` de 500 del controlador siguen exponiendo `$e->getMessage()`; es
   el patrón preexistente, también presente en la forma nueva.
5. El documento citaba 906 tests de Vitest; medido: 907 (corregido).

### Revisión nativa

`gentle_review assess` no pudo evaluar el candidato: el binario nativo responde
que no hay cambios pendientes incluso con `baseRef`/`committedOnly` (defecto del
proveedor al proyectar un rango ya comprometido). El plan devuelto no marca
verificador independiente: ningún ítem de alto riesgo del tamaño de tarea se
sostiene (la migración es aditiva, los retiros de ruta son los que pide la
issue, y cada regla tiene prueba).

La review nativa del acumulado se intentó sobre un worktree aislado con el
cambio como pendiente (`~/proyectos/odontosuite-f3-review`) y el
proveedor la rechazó en preflight: `lens_context_budget_exceeded` (el candidato
completo excede el presupuesto de contexto de los lentes, sin crear autoridad).
La continuación indicada es revisar por commits/unidades más pequeñas. Se corre
la unidad de mayor riesgo (`dd3d4a4`, contrato de addenda + auditoría) como
candidato propio en `~/proyectos/odontosuite-f3-review-core`
(lineage `review-340a60cac927bf8d`, tier medium, 4 lentes). El host relay falló
dos veces: el grupo abortó en `review-resilience` («Stream ended without
finish_reason», nada admitido) y el slot `review-risk` fue rechazado en
admisión porque el artefacto traía un campo `notes` que el esquema no admite.
El resultado rechazado quedó preservado en
`.git/gentle-ai/rejected-results/review-340a60cac927bf8d/review-risk-1-d2cae70d1ebf.json`
y contiene un único **WARNING**: los 500 de los addenda devolvían
`$e->getMessage()`. Se corrigió en el commit `fix(historia)` posterior (log +
mensaje genérico, con `ClinicalAddendumErrorEnvelopeTest`). La review nativa
queda **bloqueada por defecto del host relay**, no por el cambio; el lineage no
se aprobó ni se consumió y el worktree de revisión es descartable.
