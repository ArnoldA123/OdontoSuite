# Medical Records Interface Redesign — ENTREGADA (PR #226)

Rama: `feat/medical-records-interface-redesign` · Worktree:
`~/proyectos/odontosuite-medical-records` (desde `main` @ b3c168a)
Creado: 2026-10-20 · Orquestador: el Gentleman · Módulo: Historias clínicas
(`/medical-records`)

## Specs

- **S1** — Rediseñar la interfaz de Historias clínicas con el lenguaje visual
  que dejamos en el dashboard (hero band, anatomía KPI, bloques con jerarquía,
  estados vacíos con motivo, tokens `--color-*`/`--elevation-*`, sin
  `<style scoped>`). Cita del usuario: «conforme dejamos el dashboar quiero
  hacer ahora la interfaz de Historias clinicas».
- **S2** — El trabajo se hace en un worktree nuevo. Cita del usuario: «para
  hacer ello en un worktree nuevo».

## Decisiones (arquitecto)

- **D1 — Reparación de contrato incluida.** El módulo lee y escribe campos que
  no existen en el backend (`record.title`, `record.primary_diagnosis`,
  `evolution.subjective` desde un formulario que envía `observations`,
  `attachment.filename`/`url`, `stats.last_updated`, `can('...')` como función).
  Un rediseño sobre un contrato roto sería una maqueta: la reparación de
  contrato es parte de «la interfaz» y va primero, con tests.
- **D2 — La página deja de ser un callejón sin salida.** Hoy la ruta
  `/medical-records` abre en «Selecciona un paciente» y los deep links desde la
  ficha del paciente (`/medical-records/{id}`, `?patient_id=`) caen en 404 o en
  el estado vacío. Se agrega: landing con índice de historias recientes
  (`GET /api/medical-records` ya pagina y trae `patient`), deep link
  `?patient_id=` y ruta `/medical-records/:id` para abrir una historia concreta.
- **D3 — Una sola fuente de estado.** El workspace del paciente carga sus cuatro
  superficies en paralelo y conserva los datos ya cargados si una falla; cada
  superficie recibe su propio estado `loading`/`error`/`empty` con reintento
  (hoy el `catch` es silencioso y `deleteEvolution`/`stats` no existen).
- **D4 — Contrato real de los formularios.** `MedicalRecordModal` y
  `EvolutionModal` se reconstruyen sobre `StoreMedicalRecordRequest` y
  `StoreEvolutionRequest` (SOAP + procedimientos + materiales + prescripciones +
  recomendaciones + signos vitales + seguimiento). Nada de campos inventados.
- **D5 — Adjuntos sobre campos reales.** `original_name`, `file_path` →
  `/storage/{path}`, `file_type`, `category`
  (`radiografia|foto_clinica|documento|otro`), `file_size_human`; filtro por
  `category` (no por `file_type`, que es un concepto distinto).
- **D6 — Anatomía visual compartida.** Hero band con `DentalMotifLayer`,
  lockup de marca y un único CTA primario; KPI strip de máximo 4 tarjetas con
  número en `text-label` (sin rampas de color, sin gradientes); tabs con el
  primitivo `UiTabs`; `UiCard variant="glass"`, `border-hairline`,
  `--radius-panel`, `--elevation-2`; `useConfirm` + `ConfirmDialog` en lugar de
  `confirm()` sin importar; `useSpring` + `data-reveal` con
  `prefersReducedMotion` para la entrada.
- **D7 — Permisos reales.** `usePermissions().can` es un objeto de computed
  refs, no una función: cada guard usa `can.createMedicalRecord?.value` etc.
  (hoy `can('...')` lanza `TypeError` al renderizar detalle y adjuntos).
- **D8 — Sin nuevos módulos ni migraciones.** Los conteos de `AGENTS.md` §4 no
  cambian por esta feature; solo rutas API nuevas (`PUT`/`DELETE` de
  evoluciones) y actualización del changelog §12.

## Auditoría de partida (2026-10-20)

- `MedicalRecordsPage.vue` (521 líneas, `<style scoped>` de 120): 4 tabs
  ad-hoc, `animate-spin` manual, `getEvolutions(patient_id)` contra un endpoint
  que espera `medical_record_id`, `stats` inexistente, `deleteEvolution`
  inexistente, `getAttachmentsByCategory(patient, 'general')` con una categoría
  que el backend no valida, `catch` silencioso, sin paginación, sin deep links.
- `MedicalRecordCard.vue`: renderiza `record.evolutions?.length` y
  `record.attachments?.length` (el índice no los carga, siempre 0), `confirm()`
  sin importar, estado «Activa/Inactiva» en clases ad-hoc.
- `MedicalRecordDetail.vue`: contrato inventado (`title`, `description`,
  `primary_diagnosis`, `secondary_diagnoses`, `treatment`), `can('...')` como
  función, sin `UiModal` (se renderiza inline en el flujo de la página).
- `MedicalRecordModal.vue`: envía `title`/`description`/`primary_diagnosis`/
  `secondary_diagnoses`/`treatment`, que el backend descarta; omite los 13
  campos clínicos reales.
- `EvolutionTimeline.vue`: layout alternado izquierda/derecha con línea
  absoluta, `confirm()` sin importar; renderiza SOAP que el modal nunca envía.
- `EvolutionModal.vue`: envía `evolution_type`, `professional_name`,
  `consultation_reason`, `observations`, `treatment_performed`,
  `prescribed_medications`, `next_appointment` (ninguno validado) y
  `professional_name = 'Usuario Actual'` hardcodeado.
- `AttachmentGallery.vue`: `can('...')` como función, `attachment.filename` y
  `attachment.url` inexistentes (`TypeError` al montar), filtro por `file_type`
  contra un `<select>` de categorías.
- `UploadAttachmentModal.vue`: llama `addAttachment` (no exportado; el
  composable exporta `uploadAttachment`) y envía `file_date`/`notes` no
  validados.
- `MedicalRecordStats.vue`: 4 KPI y 3 secciones que esperan
  `last_updated`, `days_since_creation`, `evolutions_by_month`,
  `attachments_by_type`, `recent_activity`; el backend devuelve
  `total_evolutions`, `total_attachments`, `last_evolution`,
  `specialties_involved`, `attachment_categories`. Deriva 100%.
- Backend: `index` no filtra `is_active`, no busca, fija `paginate(10)`; no hay
  endpoint para editar/eliminar evoluciones (`MedicalRecordService::
  updateEvolution` existe sin ruta y no hay `deleteEvolution`); `getStats`
  agrega solo la primera historia activa; `getAttachmentsByCategory` exige una
  categoría exacta.
- Tests existentes: `tests/js/smoke/medical-records.test.js` (solo monta),
  `tests/Feature/Api/MedicalRecordAttachmentTest.php`. No hay
  `MedicalRecords*AppShellTest` ni contrato de rediseño.

## Tasks

| # | Task | S# | Ruta | Commit |
| --- | --- | --- | --- | --- |
| T1 | Backend: índice con `is_active`/`search`/`per_page`/conteos, `PUT`/`DELETE` de evoluciones, stats agregadas por paciente, adjuntos por categoría opcional + tests de feature | S1 | inline | `cfd3c1e` |
| T2 | Capa de datos frontend: `useMedicalRecords` reparado (`stats`, `deleteEvolution`, `updateEvolution`, `meta`, evoluciones por historia), errores por superficie | S1 | inline | `3339f65` |
| T3 | Shell de página: hero band, contexto del paciente, KPI strip 4, `UiTabs`, landing con historias recientes, deep links y ruta `/medical-records/:id` | S1 | worker | `c1b73f8` |
| T4 | Superficie «Historia general»: row, detalle en `UiModal`, modal crear/editar sobre el contrato real | S1 | worker | `f9d217e` |
| T5 | Superficie «Evoluciones»: timeline y modal SOAP sobre el contrato real | S1 | worker | `c606133` |
| T6 | Superficie «Adjuntos»: galería y modal de subida sobre campos reales | S1 | worker | `a9f63aa` |
| T7 | Superficie «Estadísticas»: detalle real (última evolución, especialidades, distribución) | S1 | worker | `c237798` + `e1dfe80` |
| T8 | Verificación: suites PHP/JS, lint/format, pasada visual en navegador, guard de docs y merge con `main` | S1-S2 | inline | `8d3f45a`..`fa7e845` |

### Estrategia de entrega y fronteras de slice

Entrega por commits de work unit sobre la rama de la feature (sin push ni PR
hasta que el usuario lo decida). Fronteras de slice registradas:

- `cfd3c1e` (T1): 647 líneas, casi todas tests de contrato; es la reparación
  coherente de una sola capa (endpoints + modelo) y no se puede partir sin
  dejar un commit intermedio que no compila.
- `3339f65` (T2): 190 líneas, capa de datos + sus 12 tests.
- `c1b73f8` (T3): 1307 líneas; es la reescritura de una sola página y la
  siguiente unidad natural (T4-T7) son las superficies que ya consume.
- T4-T7 se ejecutan en paralelo con superficies de edición disjuntas (un
  writer por superficie, sin commits) y se commitean por separado al verificar.

## Log

- **L1** (2026-10-20, verbatim): «continuando con este proyecto conforme dejamos
  el dashboar quiero hacer ahora la interfaz de Historias clinicas, para hacer
  ello en un worktree nuevo»

### Cierre (2026-10-20)

- Merge de `origin/main` (PRs #174, #178, #207 y otros) resuelto: solo
  conflicto en `AGENTS.md` §12; conteos remedidos a 202 rutas API / 215 con web.
- Suites post-merge: PHP 1707 tests / 8175 assertions (0 fallos; el runner
  devuelve 1 por warnings de doc-comment, reproducible en `main`); JS 746 tests
  en 91 archivos; lint, format, Pint, doc-drift y build en verde.
- Pasada visual: landing, workspace, evoluciones, adjuntos, estadísticas y
  modal de alta. Dos defectos corregidos: selector de pacientes tapando la
  lista (`868b873`) y «1 evoluciones».
- Follow-ups levantados: #221 utilities inertes, #222 `clinical_measurements`,
  #223 sin evento en tiempo real, #224 cobertura de navegador, #225 especialidad
  no corregible. #220 cerrado: el `can(...)` de `AiAnalysisButton` ya lo arregló
  el rediseño de Análisis IA.
