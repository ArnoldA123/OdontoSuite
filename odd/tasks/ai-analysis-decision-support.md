# Apoyo a la decisión con IA: revisión obligatoria, anulación con traza y costo

Issue #296 (épica #294). Depende de F2 (#282, `clinical_encounters`) y F3 (#292,
historia inmutable y auditoría de lectura), ambas en `main` (`cdc5243`). Rama
`feat/ai-analysis-decision-support`, worktree
`~/proyectos/odontosuite-ia`.

## Specs

S1. «Un análisis no revisado no puede citarse en la evolución ni en el plan.»
(Criterio de aceptación de la issue.)

S2. «`tecnico_dental` recibe 403 en el módulo.» (Criterio de aceptación.)

S3. «Borrar un análisis no destruye la traza de revisión.» (Criterio de
aceptación.)

S4. «Cuelga de la atención y del adjunto; aparece en el registro solo si fue
revisado.» «Nunca bloqueante: opcional en el flujo, jamás un paso obligatorio del
cierre.»

S5. «Anulación con traza en lugar de borrado; el escritorio de laboratorio no
accede.»

S6. «Aviso al paciente de que es apoyo asistido por IA.» «Ante el paciente se
declara que es asistivo.» «el análisis asistido por IA sobre radiografías o
fotografías es **apoyo a la decisión**, no diagnóstico: requiere revisión y
decisión de un profesional, queda registrado quién lo revisó y con qué
resultado, no sustituye el registro clínico y no se factura como acto propio.»

S7. «Costo no contabilizado: `tokens_used` y `model_used` existen, pero no hay
costo del servicio por paciente ni control de uso.»

Fuera de alcance, declarado por la issue: replantear el flujo de revisión (es el
correcto), facturar el análisis como acto propio y cualquier bloqueo del cierre
clínico. La issue es `area:backend`; la interfaz se ajusta solo donde el
contrato anterior cambia (borrado, aviso y anulación).

## Tasks

- T1 — Esquema y modelos: `ai_image_analyses` gana `clinical_encounter_id`,
  `voided_at`/`voided_by`/`void_reason`, `notice_acknowledged_at`/
  `notice_acknowledged_by` y `estimated_cost`; nueva tabla
  `ai_analysis_citations` (polimórfica: evolución o plan) con modelo
  `AiAnalysisCitation`. Specs: S1, S4, S5, S6, S7. Commit: `e435893`.
- T2 — Anulación: `DELETE /api/ai-analysis/{id}` se retira y nace
  `POST /api/ai-analysis/{id}/void` con motivo obligatorio que conserva la
  revisión; las lecturas excluyen lo anulado y `index?voided=1` lo hace
  consultable. Specs: S3, S5. Commit: `ae3966f`.
- T3 — Permisos: `tecnico_dental` sale de las cuatro capacidades de IA, la
  política lee el catálogo, la navegación y `CREDENTIALS.md` se alinean.
  Specs: S2. Commit: `985813f`.- T4 — Acto, aviso y costo: el análisis hereda la atención del adjunto o la
  recibe explícita, el alta exige la declaración de aviso, el costo se calcula
  al completar, el tope diario se aplica y la atención publica solo lo revisado.
  Specs: S4, S6, S7. Commit: `ca8768d`.
- T5 — Cita obligatoriamente revisada: `cited_analysis_ids` en la evolución y en
  el plan, validado contra revisión, paciente y anulación, con la cita legible en
  los dos payloads. Specs: S1. Commit: `3670ec4`.
- T6 — Documentación y guardas: AGENTS.md §4/§6/§12, `doc-drift`, matriz de rutas
  y suites completas. Specs: S1-S7. Commit: (U6, documentación).

## Contrato decidido

**Vínculo con la atención.** `ai_image_analyses.clinical_encounter_id` (FK
nullable, `nullOnDelete`) se hereda del adjunto al crear el análisis: el adjunto
ya es quien pertenece al acto (`clinical_attachments.clinical_encounter_id`,
#282). No se inventa una segunda fuente. `GET /api/encounters/{id}` publica
`ai_analyses` con **solo** los análisis revisados y no anulados del acto: un
análisis sin revisar no aparece en el registro (S4).

**Anulación.** `DELETE /api/ai-analysis/{id}` deja de existir (responde 405) y
nace `POST /api/ai-analysis/{id}/void` con `reason` obligatorio (≤255). La fila
no se borra: se marcan `voided_at`, `voided_by` y `void_reason`, y las columnas
de revisión (`reviewed`, `reviewed_by`, `reviewed_at`, `review_decision`,
`review_notes`) quedan intactas — esa es la traza que S3 protege. Una segunda
anulación responde 422. Las lecturas de lista (`index`, `pending`, `byPatient`,
`byAttachment`, `stats`) excluyen lo anulado; `index?voided=1` lista solo lo
anulado para que la traza sea consultable. Revisar o volver a revisar un
análisis anulado responde 422.

**Aviso asistivo.** Las dos puertas de alta (`POST
/api/ai-analysis/upload-and-analyze` y `POST /api/ai-analysis/analyze/{id}`)
exigen `notice_acknowledged` aceptado y guardan `notice_acknowledged_at` y
`notice_acknowledged_by`: quién declaró que informó al paciente y cuándo. El
recurso publica `assistive` (siempre `true`), el aviso literal y el autor de la
declaración; el texto del aviso vive en una sola constante del modelo.

**Costo y uso.** `estimated_cost` (decimal 10,4, USD) se calcula al completar
como `tokens_used / 1000 × services.openai.price_per_1k_tokens`. `GET
/api/ai-analysis/stats` suma `estimated_cost`, `total_tokens` y
`voided_analyses`, y `GET /api/ai-analysis/patient/{id}` publica el costo y los
tokens del paciente en su `meta`. Control de uso: `services.openai.
daily_analysis_limit_per_patient` (0 = sin tope, valor por defecto) rechaza con
422 el análisis que exceda el tope diario del paciente; el tope es una decisión
de la clínica y no se inventa un número.

**Cita.** Tabla `ai_analysis_citations` (`citable_type`/`citable_id` para
`ClinicalEvolution` y `TreatmentPlan`, `ai_image_analysis_id`, `cited_by`,
`created_at`, único por par). `cited_analysis_ids` es opcional en el alta de la
evolución y en el alta/edición del plan. Regla única: solo se cita un análisis
`completed`, revisado, no anulado y del mismo paciente; cualquier otro caso
responde 422 con el campo y el motivo. La cita se publica como `cited_analyses`
en la evolución y en el plan.

## Log

L1. Usuario: "haz la issue 296, en worktree nuevo".

L2. Worktree `~/proyectos/odontosuite-ia`, rama
`feat/ai-analysis-decision-support`, base `cdc5243` (merge de #300, F3).

L3 (U1, esquema): RED `--filter AiAnalysisDecisionSupportSchemaTest` → 2
failures + 5 errors (columnas y modelo ausentes); GREEN 7/7 (29 aserciones).
Commit `e435893`, 438 líneas autoriales, por encima del slice de ~400: la
prueba de esquema (156 líneas) viaja con la migración que la hace pasar, porque
una tabla sin su guard no es una unidad verificable.

L4 (U2, anulación): delegado a `gentle-ai-worker` por el backstop de contexto
del padre (152k tokens). RED PHP 50 tests / 9 failures (405, 422 de motivo, 404,
200 con traza, segunda anulación, exclusiones de lectura, revisión de anulado,
403 de ajeno) y RED JS 9 failed / 20 passed; GREEN PHP 48/48 (269 aserciones) y
JS 29/29. Verificado por el padre de forma independiente: 69 tests PHP
(`AiAnalysis*` + los tres guards de autorización) y 29 JS. Commit `ae3966f`,
~705 líneas de diff en 15 archivos. El slice excede ~400 por una razón de
cohesión: retirar la ruta rompe la interfaz, así que endpoint, servicio,
política, recurso, lector y pantalla forman una sola unidad verificable.

L5 (paralelo, U3 y U4): el padre lanza dos escritores en background con
superficies disjuntas (permisos/navegación/matriz contra
controlador/servicio/recurso/pantalla) y bases de test separadas
(`odontosuite_ia_test` y `odontosuite_ia_test3`), porque este repositorio ya
sufrió carreras de suite cuando dos procesos comparten base.


L6 (U3, permisos): delegado en paralelo a `gentle-ai-worker` sobre superficies
disjuntas. RED 4 tests / 3 failures (la lista del catálogo traía
`tecnico_dental`, la lista respondía 200 y la navegación nombraba cuatro roles);
GREEN 4/4 (30 aserciones) y 60/60 en el cruce con `AiAnalysisContractTest`,
`PermissionCatalogTest`, `RoutePermissionMatrixTest` y
`FrontendPermissionContractTest`. El 403 se comprobó con relación clínica real
del técnico (una cita propia), así que no viene de `ClinicalAccess`.
`TREATMENT_ROLES` queda intacto: sigue sosteniendo plan, registros de
especialidad y piezas dentales.

L7 (hallazgo de U3, no defecto): el escritorio de laboratorio no entra al módulo
de IA, pero conserva `manage.medical_record`, `manage.evolution` y
`view.encounter`, así que puede **leer** un análisis revisado allí donde ya lee
el documento que lo cita (evolución, plan y el `ai_analyses` del acto). Ese
alcance lo posee la matriz escritorio × permiso de #283 (F3, abierta), no esta
issue: aquí solo se cierra la puerta de entrada al módulo (S2). Para que la
cita no sea una vía de escritura del laboratorio, `assertCitable()` exige
además `view.ai_analysis` al autor de la cita.

L8 (U4, acto + aviso + costo): delegado en paralelo al segundo escritor. RED PHP
56 tests / 13 failures + 1 error y RED JS 7 failed / 32 passed; GREEN PHP 56/56
(364 aserciones) y JS 39/39. Hallazgo del escritor que el padre corrigió antes de
commitear: la puerta de subida guardaba el adjunto antes de que el servicio
rechazara una atención ajena o el tope diario, dejando un adjunto (y su archivo)
huérfano. Se añadió `assertAnalysisAllowed()` —un solo dueño de la regla de
propiedad, compartido con el resolutor— y dos pruebas que fijan que un 422 no
crea adjunto.

L9 (U5, cita): RED 4 failures sobre el cableado de evolución y plan; GREEN 14/14
(47 aserciones) tras añadir `assertCitable()`, `sync()` y el guard de anulación
en `voidAnalysis()`. `MedicalRecordService` y `TreatmentPlanService` reciben el
servicio de cita por constructor; `MedicalRecord::addEvolution` valida antes de
escribir, así que un rechazo no deja evolución a medias. Regla añadida sobre lo
pedido: citar exige `view.ai_analysis` (cierra la vía de escritura del
laboratorio, L7) y un análisis citado no puede anularse.

L10 (U6, verificación): suite PHP completa en MySQL `OK (2224 tests, 12912
assertions)`; Vitest `109 archivos / 914 tests`; `vite build` OK; `eslint`
0 errores (36 warnings preexistentes); `prettier --check .` limpio;
`pint --test` 704 archivos; `doc-drift` 14/14 (re-medido y corregido: 54 modelos,
22 services, 122 migraciones, 204 rutas API / 217 totales; §11 tenía 53 modelos
y §6 el middleware `role:` de antes de #280); `sdd:check-events` 0 huérfanos
sobre 35 eventos.

### Frontera de slices

`e435893` T1 (438 líneas, esquema + modelos + guard de esquema). `ae3966f` T2
(~705, anulación completa: endpoint, servicio, política, recurso, lector y
pantalla; retirar la ruta rompe la interfaz, así que va en una sola unidad
verificable). `985813f` T3 (permisos, 1 archivo de catálogo + política +
navegación + matriz + guard nuevo). `ca8768d` T4 (acto, aviso, costo y tope, con
el arreglo del adjunto huérfano). `3670ec4` T5 (cita). T6 solo documentación.
La entrega es un PR con cinco commits de trabajo y la revisión se hace por
unidad, no por el acumulado.

L11 (entrega): rama publicada y **PR #301** abierto contra `main`
(https://github.com/ArnoldA123/OdontoSuite/pull/301). CI en verde en las dos
corridas (`39838464569` de push y `39838507911` de pull_request): `Quality gates`,
`Backend tests (MySQL)` y `Frontend build` pasan. Assess nativo (`gentle-ai review
assess --committed-only`) sobre el acumulado y sobre tres unidades: risk medium,
`review due: no (under_budget)` en todos los casos, sin linaje ni consentimiento.
El merge queda en manos del usuario. Verificación independiente delegada a
`gentle-ai-verify`; su resultado se anexa aquí al volver.

### Verificación independiente (gentle-ai-verify)

PASS en S1–S7 con evidencia ejecutada: 10 superficies de IA en la tabla de rutas
real (ninguna con `role:`), 403 del técnico en las diez, catálogo exacto de los
cuatro permisos, `DELETE` respondiendo 405, traza de revisión intacta tras
anular, exclusiones de lectura y `index?voided=1`, herencia y rechazo de la
atención ajena, el acto publicando **solo** lo revisado y vigente, cierre de
consulta sin análisis (non-blocking), ambas puertas rechazando sin aviso y
persistiendo autor y fecha, costo `1234 → 0.0123` exacto, tope diario sin uso
nuevo al repetir adjunto y sin adjunto huérfano, y cero rutas que escriban datos
clínicos desde un análisis anulado o sin revisar. Suites del verificador: PHP
2224/2224, Vitest 914/914, doc-drift 14/14, eventos 0 huérfanos.

Dos hallazgos de gravedad baja, ambos corregidos en `1d1222c` (suite PHP
completa tras el fix: `OK (2226 tests, 12928 assertions)`; Vitest 914/914):

1. `GET /medical-records/{id}/evolutions` serializaba la cita como modelo
   completo (hallazgos, tokens, costo, notas de revisión) mientras el plan y el
   acto publicaban resúmenes. Un lector del registro puede no tener
   `view.ai_analysis` (matriz de #283), así que la cita viaja ahora acotada por
   `scopeCitationSummary()`: id, decisión, fecha de revisión y confianza.
2. La puerta `upload-and-analyze` guardaba el adjunto sin el acto que nombraba:
   el análisis lo heredaba pero el archivo no aparecía en `encounter->attachments`.
   `ClinicalAttachmentService::store()` acepta y persiste `clinical_encounter_id`,
   así que el adjunto y el análisis cuelgan del mismo acto.

### Cierre

Siete commits de trabajo (`e435893`, `ae3966f`, `985813f`, `ca8768d`, `3670ec4`,
`3fb05ba`, `1d1222c`) en **PR #301**, revisión por unidad. CI del PR en verde en
las cuatro corridas (dos antes del fix, `39838464569` y `39838507911`, y dos
después, `39841890059` de push y `39841899547` de pull_request): `Quality gates`,
`Backend tests (MySQL)` y `Frontend build` pasan en todas. El merge es decisión del usuario. La rama y los worktrees se
conservan hasta el merge.
