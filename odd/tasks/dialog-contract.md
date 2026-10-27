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

| ID  | Unidad                                | Specs    | Ruta   | Commit    |
| --- | ------------------------------------- | -------- | ------ | --------- |
| T1  | #423 pila de overlays con z-index     | S1, S4   | inline | `a11bc2c` |
| T2  | #379/#385 nombre y foco del sheet     | S1-S3    | inline | `e33fbde` |
| T3  | #429 nombre del detalle de IA         | S1, S5   | inline | `2f06d95` |
| T4  | Verificación (suite, lint, e2e)       | S2-S4    | inline | —         |
| T5  | Docs y cierre                         | S6       | inline | —         |

Presupuesto de revisión: 548 líneas autoradas en cuatro cortes (`a11bc2c` 323,
`e33fbde` +138, `2f06d95` +28, docs +59). Estrategia de entrega: un solo pull
request con los cuatro commits, uno por unidad de comportamiento.

## Log

- **L1.** "haz las issues #379 #385 #423 #429 en un worktree nuevo"
- **L2.** Lectura de las cuatro issues: comparten la misma raíz de primitivas de diálogo. #385 es el mismo defecto de #379 medido sobre `/ai-analysis`; #423 y #429 son del consumidor `AiAnalysisModal` y del apilado de overlays.
- **L3.** #423 — pila de overlays (`a11bc2c`): `useOverlayStack.js` es la única pila ordenada; `Modal.vue` y `Sheet.vue` pintan el fondo en `50 + profundidad` y sólo la superficie superior responde a Escape y al clic del fondo, con el bloqueo de scroll contado por superficie abierta. `overlayStack.test.js` (5) cubre el caso que fallaba: la confirmación global de `AppLayout`, montada antes que el modal que la pide, recibe el clic y se pinta encima.
- **L4.** #379/#385 — sheet (`e33fbde`): `labelledby` como prop, `aria-label` desde `title` cuando el consumidor reemplaza el `#header` («Navegación»), `aria-labelledby` sin apuntar a un id inexistente y trampa de foco armada con `nextTick` en la apertura por `v-model`. `sheetPrimitive.test.js` (5). El criterio e2e de #385 no se ejecutó: el worktree no tiene `.env` y no se leen ni copian credenciales del repositorio; queda declarado, no en verde.
- **L5.** #429 y verificación — `AiAnalysisModal.vue` (`2f06d95`) nombra el diálogo con su encabezado visible («Análisis de IA» + paciente) y un caso nuevo en `ai-analysis-detail.test.js` (16 en el archivo) fija el nombre accesible. `pnpm test:js` → 143 archivos / 1236 pruebas en verde; `vendor/bin/phpunit tests/Unit/DesignSystem/PrimitivePressTest.php` → 12 pruebas / 144 aserciones en verde. T4 y T5 quedan sin commit propio: la verificación vive en la entrega y este cierre documental lo confirma la revisión del orquestador. Entrega: un solo pull request con `a11bc2c`, `e33fbde` y `2f06d95`.
- **L6.** Cortes rehechos: el primer intento dejó el cambio de `onBeforeMount` en `Modal.vue` dentro del commit de #429; los tres commits se reconstruyeron desde `main` y los hashes de la tabla son los definitivos. `doc-drift` midió 45 composables y §4 se sincronizó: el guard cierra 14/14. Cierre documental en el mismo commit de §12.
