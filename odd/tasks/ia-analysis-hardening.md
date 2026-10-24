# IA analysis hardening (B1)

Lote B1 del programa de issues abiertos: cierra #214, #215, #216 y #217 sobre el
mismo controlador, servicio, modelo y contrato de payload. Rama
`fix/ia-analysis-hardening`, trabajo en `odontosuite-work`.

## Specs

- **S1** — Alcance (decisión del autor, #214): «admin ve todo; un clínico ve los
  análisis de pacientes con cita propia o evolución propia, y siempre los
  análisis que él solicitó o revisó. `index`, `pending` y `stats` se scopean con
  el mismo criterio».
- **S2** — «Un clínico sin relación con el paciente recibe 403 en `show`,
  `review`, `destroy`, `byPatient` y `byAttachment`.»
- **S3** — «El índice solo devuelve los análisis dentro del alcance del usuario,
  con `meta.total` coherente.»
- **S4** — «Tests de feature cubren los pares permitidos y denegados; la suite
  sigue verde.»
- **S5** — «`GET /api/ai-analysis` y `GET /api/ai-analysis/{id}` no incluyen
  `prompt_sent` ni `raw_response`; el test lo fija.»
- **S6** — «La interfaz de `/ai-analysis` sigue funcionando sin cambios de UI
  (sus 40 tests JS se mantienen verdes).»
- **S7** — «`UserResource` mínimo (`id`, `name`) para las relaciones.»
- **S8** — «Un fallo en las métricas produce 500 y el bloque de error/reintento,
  nunca 0/0/0/0 %.»
- **S9** — «`pending` y `byPatient` aceptan `per_page` con el mismo rango y lo
  reflejan en `meta`.»
- **S10** — «`upload-and-analyze` responde con `meta.message` y el test lo fija.»
- **S11** — «`?date_from=abc` no produce 500»: se ignora el filtro, igual que los
  valores vacíos desde #178, y el test lo fija.
- **S12** — «`AiImageAnalysisFactory` con `clinical_attachment_id`, `patient_id`
  y `requested_by` por factory anidada.»

## Tasks

- T1 — #217 contrato de lista: helper `per_page`, sobre `meta.message`, fechas
  parseables, factory (S9-S12) · commit `aa0e126`
- T2 — #216 métricas honestas: 500 en vez de ceros (S8) · commit
  `15709ac`
- T3 — #215 payload: resource explícito + modelo oculto (S5-S7) · commit
  `174081c` (T3 y T4 comparten el commit de alcance: el resource y la policy se probaron juntos)
- T4 — #214 alcance clínico + policy (S1-S4) · commit
  `174081c`

## Log

- L1 — Pedido del autor: «quiero hacer issues agarra de una en una o de montones
  pequeños que haya relacion como tal y las desarrolles, en un worktree nuevo, y
  me giugas cuales haras para tenerlo en cuenta».
- L2 — Decisión de alcance para #214 (regla B): «Igual que A, pero además ve
  siempre los análisis que él solicitó o revisó, aunque no haya cita ni
  evolución.» + «`stats`/`pending` con el mismo criterio.»

- L3 — Evidencia: suite PHP completa 1722/1722 (8271 aserciones) y `AiAnalysisContractTest` 24/24 sobre MySQL 8.0;
  `pnpm test:js` 91 archivos / 746 tests en verde. El runner de este worktree
  usa `odontosuite_test_ia` porque otras sesiones comparten `odontosuite_test`
  en el mismo contenedor y sus `migrate:fresh` concurrentes producían deadlocks
  de DDL.

- L4 — Verificación independiente (gentle-ai-verify): S1-S12 cubiertos, con dos
  hallazgos. Cerrados en `HEAD` (commit de corrección): `ClinicalAccess` ahora
  excluye citas con soft delete (la asimetría con evoluciones era una omisión) y
  `POST analyze/{attachmentId}` responde 404 `Adjunto no encontrado` en vez de
  500 cuando el adjunto no existe. Se añadieron los tres tests que faltaban
  (cita borrada, analyze 403 y analyze 404); `AiAnalysisContractTest` 27/27.

- L5 — Review nativo (lineage review-332c7ad42f6520dc, lentes risk + reliability,
  tier medium, 13 archivos / 1065 líneas): **approved** y autoridad consumida
  (`gentle-ai.review-acknowledged/v1`). El primer run del lente risk falló por
  transporte del host relay ("Stream ended without finish_reason"); STATUS
  reofertó el slot y el reintento admitió. Hallazgos no bloqueantes:
  R3-001 controller:127-144, R3-002 DateFilter:19-28, R3-003 controller:244-252,
  R3-004 service:458-461 (todos informational, sin transición de corrección).
- L6 — Entrega: rama local `fix/ia-analysis-hardening` con 4 commits; push y PR
  quedan a decisión del autor. Los issues #214-#217 se cierran al integrar.

- L7 — Advisories del review resueltos en `f0be894` (los cuatro puntos
  señalados eran acotados, no abren issue): `stats` pasa por `viewAny`;
  `pending` ignora `requested_by`/`patient_id` vacíos o no numéricos;
  `byAttachment` autoriza al paciente antes de responder "sin análisis" (sin
  oráculo de existencia); `DateFilter` acepta solo formatos explícitos, así que
  "next monday"/"tomorrow" se ignoran en vez de mover la ventana. Tres tests
  nuevos: `pending ignores blank filters`, `by_attachment does not reveal
  analyses out of scope`, `relative date expressions are ignored`.
- L8 — Rebase sobre `origin/main` (37 commits por delante) sin conflictos;
  `AiAnalysisContractTest` 30/30, `pnpm test:js` 96 archivos / 770 tests.
  `assess` del candidato final: tier medium, `reviewDue: false` (under_budget).

- L9 — Integrado: PR #241 con los tres gates de CI en verde en dos runs
  (quality, backend-tests MySQL, frontend-build); merge commit `e4f372b` sobre
  `main` y rama remota eliminada. #214-#217 cerrados por el propio PR. Rama
  local y worktree limpiados.
