# Dialog contract — #379 / #385 / #423 / #429

Rama: `fix/dialog-contract-a11y-stacking` (desde `main` @ 89d1e00)
Worktree: `.worktrees/dialog-contract`
Creado: 2026-10-27 · Orquestador: el Gentleman

## Specs

- **S1.** "haz las issues #379 #385 #423 #429 en un worktree nuevo"
- **S2.** #379 — "El sheet de navegación móvil anuncia «Navegación» como nombre accesible y el foco no sale del diálogo al tabular."; "El sheet de `/ai-analysis` (mismo componente) también queda en verde, cerrando el hallazgo F2 de #363."; "Una prueba falla si un `UiSheet` abierto no atrapa el foco o se queda sin nombre accesible."; alcance: "Igualar `Sheet.vue` a `Modal.vue` en nombre accesible y activación de la trampa de foco (incluido el caso de apertura por `v-model`)."
- **S3.** #385 — "La fila `focus-trapped` del contrato queda `ok: true` y un test de la primitiva fija que el foco inicial y el ciclo de Tab quedan dentro del sheet."; "`Sheet.vue` y su cobertura: activar la trampa cuando la ref ya está montada (watch/`nextTick`). No se toca el contenido del sheet ni la pila compartida de modales."; criterio de aceptación literal: `AUDIT_SCOPE=route:/ai-analysis pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep "dialog contract /ai-analysis"`.
- **S4.** #423 — "La confirmación se pinta sobre el detalle, su botón recibe el clic y el flujo termina como promete: análisis no citado → `POST …/void` `200` y la fila sale de la lista; análisis citado → `422` con «No se puede anular un análisis citado en una evolución o en un plan.» (el contrato ya lo responde; hoy la pantalla no lo alcanza)."; "La corrección debe resolver el caso general —una confirmación pedida desde dentro de un modal abierto—, no sólo el botón de IA. No se toca el contrato de anulación."
- **S5.** #429 — "El diálogo se anuncia con el nombre que ya muestra («Análisis de IA» + paciente): `aria-labelledby` apuntando al encabezado real o `labelledby` pasado por el consumidor."; "`AiAnalysisModal.vue`: nombrar el diálogo con su encabezado. No se toca la primitiva ni el sheet."
- **S6.** Convenciones heredadas de `AGENTS.md` §7: código, tests, comentarios y docs de repositorio en inglés; copy de UI en español; `pnpm` siempre; commits semánticos sin emojis; "UI: consumir SIEMPRE de `components/ui/`."; sin comentarios pedagógicos.

## Tasks

| ID  | Unidad                                | Specs    | Ruta   | Commit |
| --- | ------------------------------------- | -------- | ------ | ------ |
| T1  | #423 pila de overlays con z-index     | S1, S4   | inline |        |
| T2  | #379/#385 nombre y foco del sheet     | S1-S3    | inline |        |
| T3  | #429 nombre del detalle de IA         | S1, S5   | inline |        |
| T4  | Verificación (suite, lint, e2e)       | S2-S4    | inline |        |
| T5  | Docs y cierre                         | S6       | inline |        |

## Log

- **L1.** "haz las issues #379 #385 #423 #429 en un worktree nuevo"
- **L2.** Lectura de las cuatro issues: comparten la misma raíz de primitivas de diálogo. #385 es el mismo defecto de #379 medido sobre `/ai-analysis`; #423 y #429 son del consumidor `AiAnalysisModal` y del apilado de overlays.
