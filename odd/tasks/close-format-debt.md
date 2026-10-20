# Task: cerrar la deuda de formato y volver bloqueantes los gates de Pint y Prettier

Status: DONE. Rama `chore/close-format-debt`.

## Contexto

AGENTS.md §10 declara: «Avisos explícitos (no-bloqueantes con motivo anotado en
el workflow): Pint y Prettier, con deuda de formato pendiente (#15)». El issue
#15 está cerrado (PR #80, merge b6f6789): allí se eliminaron los `|| echo` que
enmascaraban las fallas. Lo que quedó abierto es su follow-up explícito, sin
issue propio:

- `vendor/bin/pint --test` → 369 archivos PHP con violaciones (medido 2026-10-20).
- `pnpm format:check` → 577 archivos sin formatear; 482 son artefactos
  vendorizados o históricos (`.agents/` 195, `openspec/` 259, `odd/` 28) y 18
  son planes cerrados en `docs/mejoras/`.

## Specs

- **S1** Los dos steps del job `quality` en `.github/workflows/ci.yml` son
  bloqueantes: `Lint backend (Laravel Pint dry-run)` y
  `Format check (Prettier)` fallan cuando el árbol no está formateado
  (`continue-on-error` eliminado, sin `|| echo`).
- **S2** El árbol que se formatea es el código propio del repositorio:
  `app/`, `database/`, `routes/`, `config/`, `bootstrap/`, `tests/`,
  `resources/js/`, `resources/css/`, `tests/js/`, `scripts/` y la documentación
  viva (`AGENTS.md`, `README.md`, `INSTALACION.md`, `CREDENTIALS.md`,
  `docs/architecture/`, `docs/database/`, `docs/ux-guidelines.md`).
- **S3** Quedan excluidos de Prettier, con el motivo escrito en
  `.prettierignore`: `.agents/` (skills instaladas, se re-desformatean al
  reinstalar), `openspec/` (cambios archivados, registro histórico), `odd/`
  (notas de tarea), `docs/mejoras/` (planes cerrados), `pnpm-lock.yaml`
  (lockfile gestionado por pnpm), `composer.json` (Composer lo reescribe en
  cada `require`) y `resources/css/tokens.generated.css` (generado por
  `scripts/build-tokens-css.mjs`).
- **S4** `vendor/bin/pint --test` y `pnpm format:check` terminan con exit 0
  sobre el árbol completo del repositorio.
- **S5** Ninguna verificación existente se rompe: suite MySQL completa verde,
  `pnpm lint:check` sin errores, `pnpm test:js` verde, `pnpm build` verde y
  `node scripts/audit/doc-drift.mjs` con sus 12 checks en verde.
- **S6** `AGENTS.md` §10 deja de citar la deuda pendiente (#15) y documenta los
  gates bloqueantes; §12 registra el cambio con la fecha y las mediciones.
  El issue #15 no se reabre: la deuda de formato es su follow-up.

## Tasks

| ID  | Spec   | Ruta   | Acción                                                          | Commit    |
| --- | ------ | ------ | --------------------------------------------------------------- | --------- |
| T1  | S3     | inline | `.prettierignore` con las exclusiones y su motivo               | `f94dc8e` |
| T2  | S2, S4 | inline | `vendor/bin/pint` sobre el backend completo                     | `be7f5cc`, `a91ff97` |
| T3  | S2, S4 | inline | `pnpm exec prettier --write .` sobre el árbol incluido          | `d5d65a8`, `b878b5a`, `d36661c` |
| T4  | S1     | inline | Quitar `continue-on-error` de Pint y Prettier en CI             | `40b5bce` |
| T5  | S6     | inline | Sincronizar AGENTS.md §10/§12                                   | —         |
| T6  | S5     | inline | Verificación completa (Pint, Prettier, ESLint, Vitest, MySQL, build, doc-drift) | sin código |

Los commits de formato mecánico exceden el slice de ~400 líneas por
construcción: no hay comportamiento que separar. Los cortes se eligieron por
superficie revisable (aplicación / tests+DB / frontend+config / docs) para que
cada uno se pueda revisar por muestreo sin leer el diff entero.

## Evidencia

- Pint: 369 archivos con violaciones → `pint --test` 540 archivos PASS.
- Prettier: 577 archivos sin formatear → 92 en scope, `format:check` exit 0.
- Gates en ambas direcciones: `pint --test` exit 1 con un archivo de prueba
  mal formateado y 0 al retirarlo; `prettier --check` exit 1 con un archivo
  de prueba fuera de estilo y 0 al retirarlo.
- ESLint: 0 errores (44 warnings de `vue/attributes-order`, preexistentes).
- Vitest: 66 archivos, 540 tests, todos verdes.
- `doc-drift.mjs`: 12/12 checks en verde. `AgentsDocsSyncTest`: 5/5.
- Suite MySQL completa: 1418 tests, 6187 assertions, todos verdes
  (`DB_SOCKET="" php artisan test --configuration=phpunit.mysql.xml`; la base
  local escucha en 127.0.0.1:3307 y el `DB_SOCKET` de XAMPP desviaba la
  conexión). El total coincide con la corrida previa a los cambios, así que
  ningún test dejó de ejecutarse.
- `pnpm build`: exit 0.
- Guards ajustados por formato, no debilitados: `CredentialsDocumentationTest`
  (padding de tablas markdown), `PatientsListAppShellTest` y
  `patients-list-redesign.test.js` (reflow del atributo `style` inline).

## Log

- **L1** (request original, verbatim): «quieoro ver un tema y soluciuonarlo eso
  de ""Pint y Prettier, con deuda de formato pendiente (#15)"" busca a que hace
  referncia de este proyecto y dar solucion»
- **L2** (aclaración del usuario): «un tema creo que se levanto una issue 154
  creo revisa haber». Verificado: #154 es `fix(recordatorios): agendar
  confirmaciones al crear o reprogramar una cita`, sin relación con formato. El
  único issue del tema es #15, cerrado con PR #80.
- **L3** (decisión de alcance, elegida por el usuario entre tres opciones):
  formatear solo código propio y documentación viva; excluir en
  `.prettierignore` `.agents/`, `openspec/`, `odd/`, `docs/mejoras/` y
  `pnpm-lock.yaml` con el motivo escrito. Ambos gates pasan a bloqueantes.
- **L4** (medición previa, 2026-10-20, base `main` = 33a6a06): Pint 369 archivos
  con violaciones sobre 539 PHP rastreados; Prettier 577 archivos sin formatear,
  110 descontando `.agents/`, `openspec/` y `odd/`.
- **L5** (estado del working tree): tres archivos con cambios sin commitear
  (`ProcedureStatsPage.vue`, `procedure-stats-charts.test.js`,
  `procedure-stats.test.js`). Pertenecen a la rama
  `fix/procedure-stats-charts-narrow` (commit 3c91734): esta rama se rebasó
  sobre `main` para no arrastrarlos, y no se tocaron.
- **L6** (hallazgo durante T3): `CREDENTIALS.md` es markdown verificado por un
  guard. El padding que Prettier agrega al alinear tablas rompió 3 aserciones
  (`| Funcionalidad |` y dos regex de fila). Se corrigieron los patrones para
  tolerar espacios: la regla es la estructura de la tabla, no el espaciado.
- **L7** (hallazgo durante T3): Prettier parte el atributo `style` inline de
  `PatientsPage.vue` en varias líneas, lo que rompía un test PHP (greppea el
  fuente) y un smoke de Vitest (lee el atributo del DOM). Ambos comparan ahora
  la declaración con el whitespace normalizado.
- **L8** (hallazgo durante T3): `tokens.generated.css` lo regenera
  `scripts/build-tokens-css.mjs` y `GeneratedTokensCssTest` compara su
  contenido contra `tokens.js`. Formatearlo rompía esa relación; quedó excluido
  de Prettier y restaurado con `pnpm tokens:build`.
- **L9** (hallazgo durante T3): `composer.json` lo reescribe Composer con
  `JSON_PRETTY_PRINT` en cada `require`; Prettier no puede mantenerlo estable.
  Quedó excluido con ese motivo.
- **L10** (verificación final): ver §Evidencia. Sin regresiones observadas en
  la suite MySQL ni en los guards estructurales.
- **L11** (review nativo): `assess` devolvió `reviewDue: true` (native risk
  high, 457 paths). `inspect` preparó un target base-diff contra `main`
  (`33a6a06`, committed-only) y `start` fue rechazado antes de crear autoridad
  con `lens_context_budget_exceeded`: el candidato completo excede el budget de
  contexto de las lentes. `mutation_outcome: none`, así que no hay linaje que
  abandonar ni autoridad que quemar. El provider pide dividir en candidatos
  más pequeños; los 10 commits ya están cortados por superficie para eso.
  La entrega sigue la política ordinaria del repositorio; el review nativo no
  es un gate de delivery.
