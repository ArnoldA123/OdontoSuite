# 311 — El asistente de cierre no reinicia su payload al reabrirse para otra cita

Issue #311 (`[F6] El asistente de cierre no reinicia su payload al reabrirse
para otra cita`, `area:frontend`, `crit:alta`, `type:defecto`). Clasificada
**small**: el defecto se reproduce y se explica en un lote de lectura, el riesgo
es de estado de UI y el trabajo se reanuda con el pedido original y el diff. Por
eso no hubo worker, verificador independiente ni documento de tareas antes de
implementar; este registro es la evidencia ODD de la unidad ya entregada.

Worktree `.worktrees/311-wizard-reset` (retirado), rama
`fix/311-consultation-wizard-reset`, commit `f115ea4`, PR #324 (merge
`53da232`).

## Specs

S1. Issue #311: «Abrir el asistente para una cita después de cerrar otra
muestra todos los campos vacíos.»

S2. Issue #311: «Un smoke de JS lo prueba.»

S3. Issue #311: «No se rompe el flujo de cita única (documentar, firmar,
cerrar).»

S4. Issue #311, propuesta: «Reiniciar `payload` a su estado inicial en
`openForAppointment()` / `close()` de `useConsultation` (o al cambiar
`appointment.id`), o montar el wizard con `:key="appointment.id"`.»

## Tasks

- T1 — S1, S4 — `createInitialPayload()` y `resetForm()` en
  `ConsultationWizard.vue`; reinicio al cambiar de cita y en cada apertura
  (`watch(isOpen)`), con paso inicial, firma, ítems del plan y búsquedas de
  catálogo en vuelo. Commit `f115ea4`; PR #324.
- T2 — S2 — `tests/js/smoke/consultation-wizard-reset.test.js` (2 casos): cierre
  firmado de A y apertura de B vacía; borrador de A cancelado se descarta. RED
  observado antes del arreglo (`expected 'Me duele la muela desde hace tres días'
  to be ''`). Commit `f115ea4`; PR #324.
- T3 — S3 — `tests/js/smoke/consultation-closure-nts139.test.js`: el caso de
  firma de #286 sigue verde y se adapta a que el acto nuevo abre en el paso 1; el
  envío firmado se comprueba en el mismo archivo. Commit `f115ea4`; PR #324.
- T4 — verificación — Vitest completo 956/956 (116 archivos); guardas
  `CitasWizardAppShellTest` y `CitasNegativeSpaceRulesTest` 38/38; ESLint y
  Prettier en verde; `doc-drift` 14/14; CI de la PR (quality, MySQL, frontend
  build) en `success`; `gentle-ai review assess` `reviewDue: false`
  (`under_budget`).
- T5 — documento — entrada en el changelog de `AGENTS.md` (en `main` tras el
  merge `53da232`).

## Log

L1 — Arnold: «haz la issue 311 en un worktree nuevo».

L2 — Arnold: «si, push pr merge y limpia el worktree y demas cosas que ya no
uses».

L3 — Arnold: «y el odd si lo pasaste al repo principal local?».
