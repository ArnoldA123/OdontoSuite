# UI Design-System Debt — Issues #218 #219 #221 #205

**Branch**: `fix/ui-design-system-debt` (worktree `~/proyectos/odontosuite-ui-debt`, base `main` @ 3cc3bd9)
**Estado**: en curso
**Inicio**: 2026-10-20

Cuatro defectos del frontend que comparten la misma superficie: el design system
y el módulo de análisis de IA. Ninguno cambia rutas, modelos, migraciones ni
primitivas UI nuevas.

## Specs

**S1 — #218: `AiAnalysisButton` fuera del CSS scoped y de los alias legacy.**
Criterio de aceptación verbatim del issue:

- `AiAnalysisButton.vue` sin `<style>` y sin alias legacy.
- El botón conserva el gate por permiso y por categoría (`radiografia` +
  `file_type === 'image'`), cubierto por
  `tests/js/smoke/ai-analysis-button.test.js`.
- `ModuleAppShellTestCase` incluye el archivo y sigue verde.

Estados exigidos por la propuesta: sin análisis, analizando, pendiente,
completado, fallido y revisado, con las mismas etiquetas que ya usa el módulo.

**S2 — #219: un solo aviso por contexto en el módulo de análisis de IA.**
Criterio de aceptación verbatim del issue:

- Un fallo de carga muestra solo el bloque de error con reintento.
- Un fallo de subida muestra solo el mensaje en línea del Sheet.
- `pnpm test:js` verde.

Regla: el composable no anuncia por toast un error que una superficie del
consumidor ya pinta. Las lecturas devuelven el error; `uploadAndAnalyze` es
silencioso porque `AiAnalysisSheet` es dueño del mensaje; solo las acciones sin
superficie de error propia (`analyzeImage`, `reviewAnalysis`, `deleteAnalysis`)
conservan el toast.

**S3 — #221: utilidades `text-label`, `border-hairline`, `bg-canvas`.**
Criterio de aceptación verbatim del issue:

1. Registrar las utilidades faltantes en el plugin (o renombrar los usos a su
   forma real) con un repaso del delta visual en los módulos que las usan.
2. Un test estático que falle si una clase usada en los módulos rediseñados no
   existe en el config/CSS generado.

Corrección de evidencia: `rounded-ios` sí se genera (`theme.extend.borderRadius`
sale de `tokens.radius`, que es plano). El guard estático descubre además siete
utilidades token inerte preexistentes, que se registran en el mismo lote.

**S4 — #205: `UiEmptyState.icon` acepta componentes funcionales.**
Criterio de aceptación verbatim del issue:

- Ampliar el tipo a `[String, Object, Function]` (los tres son válidos para
  `<component :is>`), o convertir los iconos a objeto con `markRaw(...)` en los
  llamadores.
- Agregar un smoke que monte `UiEmptyState` con un icono de heroicons y falle
  si aparece el warning.

## Tasks

| ID  | Issue | Tarea                                                              | Specs | Ruta       | Commit |
| --- | ----- | ------------------------------------------------------------------ | ----- | ---------- | ------ |
| T1  | #221  | Registrar utilidades token y guard estático                        | S3    | frontend   | 6d2a073 |
| T2  | #205  | Ampliar `UiEmptyState.icon` y smoke de warning                     | S4    | frontend   | e7d2011 |
| T3  | #218  | Reescribir `AiAnalysisButton` y extender el guard de app-shell     | S1    | frontend   | bd0fe7e |
| T4  | #219  | Disciplina de toasts del composable y cobertura de aviso único     | S2    | frontend   | 56e71b2 |
| T5  | —     | Checks finales: vitest, PHPUnit de design system, lint, format     | —     | verificación | —    |

## Log

**L1 — pedido del usuario (verbatim).**

> quiero hacer issues #218 #219 #221 #205 desarrolles, en un worktree nuevo

**L2 — evidencia recogida antes de escribir.**

- `AiAnalysisButton.vue` ya no se monta en ninguna superficie: el rediseño de
  historias clínicas (`a9f63aa`) retiró el import de `AttachmentGallery.vue`.
  El componente queda como deuda aislada; se refactoriza igual porque el issue
  fija su contrato y su smoke sigue vigente.
- El plugin de Tailwind no declara `.text-label`, `.border-hairline`,
  `.bg-canvas`. `rounded-ios` sí existe en el build (`theme.extend.borderRadius`
  consume `tokens.radius`, plano).
- Un barrido estático sobre `resources/js/{modules,components}/**/*.vue`
  encuentra siete utilidades token adicionales inertes: `bg-systemBackground`,
  `text-systemBackground`, `border-separator`, `divide-hairline`, `ring-accent`,
  `outline-accent`, `text-accent-hover`. `Card.vue` y `AppLayout.vue` dependen
  de las dos primeras para el contrato canvas/surface ya documentado en sus
  comentarios.
- `text-ink-700` en `modules/auth/ForgotPasswordModal.vue` referencia un ramp
  retirado (`ink` no existe en `tokens.js`); no es una utilidad token viva y
  queda como follow-up fuera de alcance.

**L3 — decisiones.**

- El guard de app-shell se extiende con un segundo grupo de archivos
  (`embeddedFiles`): las reglas de disciplina de estilo aplican a componentes
  embebidos, pero la regla de superficie canvas sigue siendo de páginas. Un
  botón embebido no pinta el canvas de la página.
- El guard estático de S3 se implementa en JS (vitest) porque necesita importar
  `tokens.js` y ejecutar el plugin de Tailwind para construir la lista de
  utilidades generables.
- Se registra el lote completo de utilidades token inertes (las tres del issue
  más las siete preexistentes) para que el guard quede limpio y la deuda no
  reaparezca por la puerta de atrás.

**L4 — T1 (#221) cerrado.**

- RED: `tests/js/unit/design-token-utilities.test.js` (nuevo) lista 9 utilidades
  token que Tailwind no puede generar: las tres del issue más
  `bg-systemBackground`, `text-systemBackground`, `border-separator`,
  `divide-hairline`, `ring-accent` y `text-accent-hover`.
- GREEN: `tailwind.config.js` declara las 9 contra los CSS vars del generador.
  `outline-accent` no se registra porque el plugin ya emite
  `focus-visible:outline-accent` (la variante que usa `PaginationComponent`);
  el guard compara también la clase completa con variantes.
- Verificación de emisión: `pnpm exec tailwindcss -i resources/css/app.css`
  genera `.text-label`, `.border-hairline`, `.bg-canvas`, `.rounded-ios` y las
  seis restantes. Sin rutas, modelos, migraciones ni primitivas UI nuevas.
- Delta visual intencional: la canvas queda pintada en las páginas rediseñadas,
  los títulos toman `--color-label-label`, los bordes hairline dejan de caer al
  gris por defecto, las cards `glass` vuelven a ser blancas opacas
  (`bg-systemBackground`), los divisores de tablas toman el hairline cálido y
  los focus/ring de acceso rápido toman el accent.

**L5 — T2 (#205) cerrado.**

- RED: `tests/js/smoke/ui-empty-state-icon.test.js` reproduce el warning exacto
  (`Invalid prop: type check failed for prop "icon". Expected String | Object,
  got Function`).
- GREEN: `EmptyState.vue` acepta `[String, Object, Function]`; el smoke sigue
  cubriendo el contrato de icono string.
- Commit `e7d2011`.

**L6 — T4 (#219) cerrado.**

- RED: las lecturas fallidas emitian dos toasts (`getAnalyses` + `getStats`) y
  la subida fallida sumaba el toast genérico al mensaje específico del Sheet
  (el texto aparecía dos veces).
- GREEN: lecturas y `uploadAndAnalyze` ya no tuestan; `analyzeImage`,
  `reviewAnalysis` y `deleteAnalysis` conservan el toast. La regla queda
  documentada en el encabezado del composable. Los smokes limpian el estado
  global de toasts por test y afirman cero errores anunciados.
- Verificación focalizada: 4 archivos, 37 tests verdes; eslint y prettier
  limpios. `pnpm test:js` completo queda para T5.

**L7 — T3 (#218) cerrado.**

- RED: `AiAnalysisAppShellTest` falla antes de la reescritura (style discipline,
  `<style>` presente, alias legacy, primitivas ausentes); el smoke de estados
  nace verde porque fija el comportamiento que debía conservarse.
- GREEN: 11 tests (52 aserciones) del test de módulo, 12 del smoke del botón,
  16 de `MedicalRecordsAppShellTest` y 911 de toda la suite de design system.
  Pint, ESLint y Prettier limpios.
- El botón conserva el gate `can['ai-analysis.analyze']?.value` y el de
  categoría (`radiografia` + `file_type === 'image'`), los emits y las
  etiquetas; la variante accent vive en `UiButton`, no duplicada en el archivo.
- `ModuleAppShellTestCase` gana `embeddedFiles()`: los componentes embebidos
  cumplen la disciplina de estilo (sin `border-theme`, focus tokenizado, sin
  alias de focus legacy, sin `<style scoped>`) pero no la regla canvas, que es
  de superficie de página. El provider devuelve una fila no-op cuando la
  subclase no declara componentes embebidos.

**L8 — frontera de entrega (slice boundary).**

- Cuatro commits de unidad de trabajo, uno por issue, más el commit de docs:
  `6d2a073` (#221), `e7d2011` (#205), `56e71b2` (#219), `bd0fe7e` (#218).
  Total medido por el harness: 995 líneas autoradas, por encima de la guía de
  ~400 por slice.
- Estrategia: la rama no se propone como un PR único. Cada commit es una
  unidad revisable por separado (issue-scoped, con sus tests y docs). Si se
  publica, se parte en PRs encadenados por issue; el reviewer puede revisar
  commit por commit. No se hacen más commits de implementación en esta rama:
  solo el commit de documentación (`AGENTS.md`, `cfe2e6f`) y una corrección
  acotada de test (`0421552`, pines de las declaraciones de utilidades).

**L9 — T5 cerrado: verificación final.**

- `pnpm test:js`: 93 archivos, 760 tests verdes (rerun limpio con log
  completo, 62s, exit 0). Un rerun intermedio bajo carga extrema falló 1 test
  por timeout (la suite tardó 156s, 2.3x); no se reprodujo y el árbol no cambió
  entre ambos.
- `pnpm build`: exit 0; el CSS de producción contiene `text-label`,
  `border-hairline`, `bg-canvas`, `rounded-ios`, `bg-systemBackground`,
  `text-systemBackground`, `border-separator`, `divide-hairline`,
  `hover:text-accent-hover` y `focus:ring-accent` con sus valores de token.
- `php artisan test tests/Unit/DesignSystem`: 911 tests, 5000 aserciones.
- `node scripts/audit/doc-drift.mjs`: 12/12 checks.
- ESLint, Prettier y Pint: limpios en todos los archivos tocados.
- ASSESS (RDD on, rango `origin/main..HEAD`): riesgo medium,
  `reviewDue=false` (`under_budget`), sin verifier independiente; la
  verificación del writer es la que manda. `nativeReviewOutcome=unknown`.
- Sin consultas ni escrituras a la base de datos: no se corrieron migraciones,
  seeders ni la suite PHP que toca MySQL (pedido explícito de cuidado por otros
  agentes usando la BD). Los cambios son frontend y su superficie PHP es la
  suite de design system, ya verde.
- Rama limpia, cinco commits; `AGENTS.md` documentado y comprometido
  (`cfe2e6f`). Push y PR quedan como decisión del usuario.

**L10 — corrección acotada y rerun final.**

- `0421552` — el guard estático ahora también fija **a qué variable** apunta
  cada una de las nueve utilidades (no solo que exista). Test-only.
- Rerun completo: 760/760 tests, 93 archivos, exit 0. El fallo aislado del
  rerun anterior fue un timeout bajo carga, no una regresión: mismo árbol,
  mismo comando, 62s frente a 156s.
- ASSESS re-ejecutado tras `0421552`: riesgo medium, `reviewDue=false`
  (`under_budget`), sin verifier independiente.
- Chequeo de consumidores compartidos del cambio de toasts: `useAiAnalysis`
  solo lo consumen `AiAnalysisPage.vue` y `AiAnalysisButton.vue`;
  `MedicalRecordsPage.vue` usa `useMedicalRecords`, no este composable. No hay
  consumidor no mencionado por el issue.

**L11 — hallazgos de la sesión: arreglados o derivados.**

- Arreglados aquí (acotados, con RED/GREEN cuando aplica):
  - `NewAppointmentModal` pasaba `type="textarea"` a `UiInput`, que lo
    rechazaba y renderizaba un input de una línea. `Input.vue` ahora soporta
    `textarea` + `rows`; el smoke de citas fija el elemento real.
  - `DashboardPage` usaba `Badge variant="secondary"`, inexistente en el
    validador: caía al variant por defecto con warning. Mapeado a `neutral` y
    fijado con `data-variant` en el smoke de KPIs.
  - El fixture de `ai-analysis-detail.test.js` pasaba un objeto paciente en
    vez de un análisis y disparaba el warning de `StatusPill`; corregido y
    pinneado con spy de consola.
  - `ForgotPasswordModal` definía un `.text-ink-700` local sobre el valor de
    `--color-label-secondary-label`; reemplazado por `text-theme-secondary`
    (mismo color) y regla scoped eliminada.
- Derivado a issue: **#239** — decidir si `AiAnalysisButton` se elimina o se
  reconecta a la galería de adjuntos; quedó sin consumidor tras `a9f63aa`.
- Los warnings restantes del suite completo quedan en cero para estos casos;
  los `vue/require-default-prop` de `Input.vue` son preexistentes y no
  bloquean (0 errores).

**L12 — verificación post-merge con `origin/main`.**

- Merge `48d9113`; único conflicto `AGENTS.md` §12, resuelto preservando la
  entrada de esta rama y las tres entradas nuevas de main (caja y presupuestos).
- `doc-drift`: 12/12 con los conteos de main (197 rutas API, 210 totales).
- `php artisan test tests/Unit/DesignSystem`: 911 tests.
- `pnpm test:js`: 96 archivos, 770 tests, exit 0, **cero `[Vue warn]`**.
- `pnpm build`: exit 0; las utilidades token siguen en el CSS de producción.
- Sin cambios de dependencias entre la base y `origin/main` (`composer.json`,
  `composer.lock`, `package.json` y `pnpm-lock.yaml` intactos): los enlaces de
  `vendor`/`node_modules` del worktree siguen siendo válidos.

**L13 — entrega.**

- PR #240: https://github.com/ArnoldA123/OdontoSuite/pull/240
- CI en verde en los dos runs (push y pull_request): Quality gates,
  Backend tests (MySQL) y Frontend build.
- Merge commit en `main`: `4451fc1`.
- Worktree y rama local/remota limpiados después del merge; la ficha queda
  copiada en `odd/tasks/` del repo principal y en Engram.
