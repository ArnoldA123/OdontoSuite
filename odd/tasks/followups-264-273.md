# Feature: follow-ups 264–273 (cinco issues `crit:baja`)

Cinco deudas de auditoría abiertas tras #244 y #249, agrupadas en cuatro PRs.
Ninguna bloquea a otra; todas tocan superficies disjuntas salvo el par de
adjuntos clínicos (#265 + #266), que comparten subsistema y van en un PR.

## Specs

S1. (#266) `MedicalRecord::attachments()` no entrega filas con `is_active =
false` por defecto. `ClinicalEvolution::attachments()` tampoco: es el que hoy
sirve `GET /api/medical-records/{id}/evolutions`, `getEvolution()` y
`updateEvolution()` con rutas `/storage/...` que responden 404.

S2. (#266) Los consumidores que ya filtran a mano (`MedicalRecordService:92`,
`:114`, `MedicalRecordController:146`) conservan exactamente su resultado: el
filtro del modelo no duplica ni cambia sus respuestas.

S3. (#266) Test: un adjunto activo y uno inactivo del mismo paciente; la
relación devuelve sólo el activo, en ambas relaciones.

S4. (#265) `ClinicalAttachmentService` conserva una sola operación con
consumidor real (`store()`, usada por `POST /api/ai-analysis/upload-and-analyze`)
y pierde `update()`, `delete()` y `getAttachmentUrl()`, que no tienen llamadas.
El endpoint de subida y `AiAnalysisContractTest` siguen verdes.

S5. (#265) Guard estático que falle si vuelve a existir una segunda
implementación de borrado de adjunto que toque el disco.

S6. (#267) `package.json` deja de declarar `xlsx`, `html2canvas`, `jspdf` y
`vue-chartjs`; `pnpm build` y `pnpm test:js` en verde después del retiro. Sin
reescribir entradas históricas del changelog de `AGENTS.md`.

S7. (#264) Existen `docs/architecture/decisions/0007-*.md` y `0008-*.md` con
cuerpo real; el índice no enlaza archivos ausentes ni presenta un change
archivado como pendiente; un guard falla si una fila del índice apunta a un
archivo inexistente.

S8. (#273) `scripts/audit/probe-realtime.mjs` prueba canales privados: caso
negativo (sin token no hay entrega en `private-appointments`) y caso positivo
(con `POST /api/broadcasting/auth` el evento llega), con `skipped-*` explícito
cuando falte el stack y sin resultado fabricado.

## Tasks

- T1 — `fix/clinical-attachments-lifecycle` — S1–S5 — #266 + #265
- T2 — `chore/drop-unused-frontend-deps` — S6 — #267
- T3 — `docs/adr-0007-0008` — S7 — #264
- T4 — `chore/probe-private-channels` — S8 — #273
- T5 — verificación de los cuatro PRs y evidencia

## Log

L1 (Arnold, verbatim): «revisa todas las issues y dime cuales conviene
desarrollar en grupo o individuales o si todas un una sola sesion, solo investiga
ello y dime»

L2 (Arnold, verbatim): «si empieza»

L3 — Investigación previa: 5 issues abiertas (273, 267, 266, 265, 264), todas
`crit:baja`. Agrupación decidida: #266+#265 en un PR; #267, #264 y #273 con PR
propio, en ese orden.

L4 — Premisas corregidas por medición. (#265) `store()` sí tiene consumidor:
`AiImageAnalysisController:408` y `AiAnalysisContractTest:375`; sólo `update()`,
`delete()` y `getAttachmentUrl()` están muertos, así que retirar el servicio
entero rompería el endpoint de subida. (#266) `getAttachmentsByCategory()` y
`getRecordStats()` consultan el modelo, no la relación; el defecto real y vivo
está en `ClinicalEvolution::attachments()`. (#264) la ruta `docs/decisions/`
sólo sobrevive en el changelog (`AGENTS.md:473`), §4 y §9 ya están correctas.
(#267) `§2` ya no tiene la fila de Excel; el criterio documental quedó cumplido.

L5 — Decisiones tomadas al autorizar «si empieza»: #265 por retiro mínimo
(conserva `store()`); #266 amplía el alcance a `ClinicalEvolution::attachments()`
además de `MedicalRecord::attachments()`.

L6 — T1 cerrado en `bbde97f` (PR #275): relaciones filtradas en ambos modelos,
retiro de los tres métodos muertos y guard de dueño único. RED observado en 3 de
4 tests antes del fix; 66 tests MySQL del área, 1376 unitarios, Pint en verde.
T2 cerrado en `dd1a9e0` (PR #276): `pnpm remove` retira 29 paquetes, build con
los mismos assets, 882 tests JS, lockfile congelado, doc-drift 14/14. `gentle_review
assess` del candidato commiteado: nativeRisk medium, `reviewDue: false`
(`under_budget`), sin verifier adicional.

L7 — Límite de slice de T3: un solo PR con 414 líneas autoradas (prosa de ADR +
índice + guard). Se entrega junto porque es una sola unidad de comportamiento
documental: el contrato del índice. El hook de slice pide registrar el límite,
no partir el PR; partir 381 líneas de documentación en dos PRs empeoraría la
revisión.

L8 — Incidente de revisión nativa (candidato de T3, target `sha256:4a0d546a…`).
El worker de T4 creó su rama en el mismo worktree y cambió HEAD a
`chore/probe-private-channels` (sobre `42e722c`) mientras corría el START. El
`inspect` previo sí veía los cinco paths (`initial_review_tree f614d1d4…`); el
START, ya con HEAD en la base, midió árbol base y candidato idénticos
(`494f9c5…`), `paths: []`, y respondió `empty_base_diff_bootstrap_required`
(terminal). Árboles y conteos de autoridad quedaron en cero —ni lineage ni
candidato—, así que no hay autoridad quemada ni reparación pendiente y no se
bootstrapea un root vacío: la revisión del candidato de T3 se reintenta cuando el
worktree vuelva a estar en `docs/adr-0007-0008` y el worker haya terminado.
Lección: un writer delegado que crea rama necesita worktree propio; en un
worktree compartido mueve HEAD bajo el padre.

L9 — T4 cerrado en `a6852d7` (PR #278). El worker entregó el probe reescrito y el
orquestador lo reprodujo: `serve` + `reverb:start` + `queue:work`, 3/3 casos
live-verified con `--fail-on-unproven` (exit 0), cita 118 soft-deleted por el
cleanup, sin procesos ni puertos al salir. Corrección de forma de la evidencia:
Reverb 1.x responde `pusher:error` 4009 a nivel de conexión, no
`pusher_internal:subscription_error` en el canal.

L10 — Revisión nativa del candidato de T3 (lineage `review-3ab7d92574155043`):
`approved`, dos lentes (readability, reliability), 414 líneas, autoridad quemada
con `gentle-ai.review-acknowledged/v1`. Seis hallazgos, todos advisory y
informacionales; ninguno abrió corrección. El único con sustancia:
`docs/architecture/decisions/README.md` declara en «Conventions» el estado
`Accepted (deprecation in force)` mientras la fila de 0008 dice
`Accepted (drop pending)` — dos nombres para el mismo estado. No se toca ahora
para que el diff del PR sea exactamente el candidato revisado; queda como nit
para un commit posterior si Arnold lo quiere.

L11 — Cierre: cuatro PRs abiertos (#275, #276, #277, #278), ninguno mergeado. El
merge sigue siendo decisión del usuario. Entorno local: base de test
`odontosuite_main_test` creada para la suite MySQL y citas 112–118 soft-deleted
por el probe.

L12 — Merge completado. PRs #275 (`7bb85d0`), #276 (`aabb230`), #277 (`36be9b1`)
y #278 (`7fe0ee8`) entran a `main`, y las cinco issues (264–267, 273) cerraron con
el merge. CI de `main` sobre `7fe0ee8`: run 39650096893 `completed success` con
los tres jobs en verde (Quality gates, Backend tests MySQL, Frontend build). Las
dos anotaciones del runner (Node 20 deprecado en las actions, migración a Ubuntu
26) son avisos de plataforma, ajenos a estos cambios. Worktree limpio: una sola
rama local (`main`), sin remote-tracking de las ramas mergeadas, `git status`
vacío y cero issues/PRs abiertos. Residuo local declarado: base
`odontosuite_main_test` y las citas soft-deleted del probe. Nit advisory pendiente
de decisión: vocabulario de estado en `docs/architecture/decisions/README.md`.

L13 — Decisión de Arnold (2026-10-21): alinear el estado de ADR-0008 a la
convención (`Accepted (deprecation in force)`) y **conservar** la base
`odontosuite_main_test` (6.8 MB, 66 tablas, sólo esquema). El nit se corrige en
`bbf2a07` (PR #279), después de la revisión aprobada de #277 para que ese
candidato coincida con lo mergeado; el detalle del drop queda enlazado desde la
cabecera a `## Planned drop`. `assess`: risk `passive`, `reviewDue: false`, plan
de readback estructural — sin revisión nativa ni verifier adicional.

L14 — Revisión nativa de `bbf2a07` (PR #279): el primer START fue rechazado en
preflight (`invalid_request`) porque declaré la lente `readability` para un
candidato de riesgo `low`; un segundo intento con el árbol como base falló por
`base-ref-unresolvable` (el provider había ofrecido un tree, no un commit). El
tercero, sin lentes y con `baseRef=7fe0ee8`, creó el lineage
`review-f38dcaf011685d1a` y cerró `approved` sin lentes (2 archivos, 7 líneas,
tier low, `lenses_required: false`); la autoridad se quemó con
`gentle-ai.review-acknowledged/v1` tras `status`. Lección: no elegir lentes cuando
el candidato es `low`, y usar siempre un commit como `baseRef`.

L15 — Cierre final del lote. PR #279 mergeado (`792f9d9`) tras CI verde
(Quality gates 2m39s, Backend tests MySQL 2m31s, Frontend build 22s). `main`
tiene los cinco merges del día, una sola rama local, `git status` vacío y cero
issues/PRs abiertos. Confirmado en `main`: las dos apariciones del estado en
ADR-0008 dicen `Accepted (deprecation in force)` y el guard de índice sigue en
verde. Base `odontosuite_main_test` conservada por decisión de Arnold.
