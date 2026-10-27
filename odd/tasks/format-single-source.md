# Task: un solo módulo de formato (moneda, fecha y hora) — issue #451

Status: IN PROGRESS. Rama `fix/451-format-single-source` desde `89d1e00`.
Issue: #451 `[Auditoría/UI] Dos módulos de formato: S/ NaN, una fecha que retrocede
un día y dos relojes` (`crit:alta`, `area:frontend`).

## Specs

- **S1** Un solo módulo compartido (`resources/js/composables/useFormatters.js`)
  publica moneda, número, fecha, fecha larga, fecha con hora y hora. Una sola
  locale (`es-PE`) y un solo reloj (24 h, `hourCycle: 'h23'`). Texto del issue:
  «Un solo módulo compartido (propuesta: `composables/useFormatters.js`) con
  moneda, fecha, fecha corta, fecha con hora y hora; una sola locale (`es-PE`) y
  un solo reloj (24 h) o el declarado como canon.»
- **S2** Un importe no numérico nunca produce `S/ NaN`:

  ```console
  1234.5  | utils: S/ 1,234.50 | composable: S/ 1,234.50
  "abc"   | utils: S/ NaN      | composable: S/ 0.00      <-- DIFF
  ```

  Contrato: `formatCurrency(759)` → `S/ 759.00`;
  `formatCurrency('123.45')` → `S/ 123.45`; `formatCurrency('abc')`,
  `formatCurrency(null)`, `formatCurrency(undefined)` → `S/ 0.00`; nunca lanza y
  nunca emite `NaN`.
- **S3** Una fecha sin hora no retrocede un día fuera de UTC:

  ```console
  formatDateOnly('2026-10-27')  -> 27/10/2026   (construye componentes locales)
  formatDateShort('2026-10-27') -> 26/10/2026   (new Date('YYYY-MM-DD') = UTC; TZ America/Lima)
  ```

  Contrato: `formatDate('2026-10-27')` → `27/10/2026` bajo `TZ=America/Lima`;
  una fecha `YYYY-MM-DD` se lee por componentes locales y una cadena con
  instante (`...Z` u offset) se convierte a la hora local de la sesión.
- **S4** Un solo reloj: `formatTime` y `formatDateTime` usan 24 h.

  ```console
  es-PE por defecto -> 03:07 p. m.   vs   formatCompactTime -> 15:07
  ```

  Contrato: `formatTime(2026-10-27T20:07:00Z)` bajo `TZ=America/Lima` → `15:07`;
  `formatDateTime` → `27/10/2026, 15:07`.
- **S5** `resources/js/utils/formatters.js` deja de existir y sus importadores
  consumen el módulo único. Texto del issue: «Retirar de `utils/formatters.js` el
  `formatCurrency` duplicado y las variantes que nadie usa (`formatDateShort`), o
  borrar el módulo si el composable cubre sus importadores.»
- **S6** Los 20 `formatDate` locales y el `formatCurrency` de
  `PatientStatementPanel` consumen el helper único. Texto: «Migrar los 20
  `formatDate` locales y el `formatCurrency` de `PatientStatementPanel` al helper
  único.»
- **S7** El guard escanea `resources/js` completo y falla con archivo y línea
  ante un `Intl.NumberFormat` de moneda o un formateo de fecha/hora por locale
  (`toLocaleDateString`, `toLocaleTimeString`, `toLocaleString` con opciones de
  fecha, `Intl.DateTimeFormat`) fuera del módulo único. Texto: «Ampliar el guard
  para que escanee `resources/js` completo y falle con archivo y línea ante un
  `Intl.NumberFormat` de moneda o un `toLocale*` de fecha en un módulo.»
  Excepción declarada y ya fijada por el guard vigente: `CurrencyInput.vue`
  mantiene un `Intl.NumberFormat('es-PE', {minimumFractionDigits})` sin
  `currency` para el campo de importe.
- **S8** Criterio de aceptación del issue:

  ```console
  $ pnpm exec php vendor/bin/phpunit --filter FormatPENLabelTest   # verde con el barrido ampliado
  $ grep -rn "Intl.NumberFormat('es-PE', { *style: *'currency'" resources/js   # 1 resultado
  $ node tests/e2e/audit/u11/report.js --out=.atl/qa-evidence/audit/ui/U11
  eje format: 0 DIV (o sólo ACE declaradas)
  ```

  El primer comando se ejecuta como
  `vendor/bin/phpunit --filter FormatPENLabelTest` (el prefijo `pnpm exec php` de
  la cita no resuelve un binario PHP en este repositorio).
- **S9** No cambia el contrato de la API ni la estructura de las pantallas. Solo
  cambia el texto formateado (locale y reloj canónicos).
- **S10** Verificación proporcional: guard PHP verde con el barrido ampliado,
  unitarios JS del módulo, `pnpm test:js`, `pnpm lint:check`, `pnpm format:check`,
  `vendor/bin/pint --test`, `node scripts/audit/doc-drift.mjs`, y la corrida U11
  en capa estática; la matriz de navegador se corre cuando la app y la base de
  auditoría estén disponibles, y todo DIV residual se declara como ACE con su
  motivo o se corrige dentro de este alcance.

## Contract

`formatCurrency(amount)` → `S/ 1,234.50` (alias `formatPENLabel`).
`formatUsd(amount, options?)` → `USD 0.0042` (locale `es-PE`, moneda USD).
`formatNumber(value, options?)` → `1,234.5` (locale `es-PE`).
`formatDate(value, options?)` → `27/10/2026` por omisión.
`formatDateTime(value, options?)` → `27/10/2026, 15:07` por omisión.
`formatTime(value)` → `15:07`.
`formatCompactTime`, `formatCompactTimeRange` se conservan.
Todas las funciones aceptan `null`/`undefined`/`''`/valor inválido y devuelven
`''` (moneda y número devuelven su cero canónico), nunca lanzan y nunca `NaN`.

## Tasks

| ID  | Spec    | Ruta   | Acción                                                                 | Commit |
| --- | ------- | ------ | ---------------------------------------------------------------------- | ------ |
| T1  | S1–S4   | inline | Módulo canónico + unitarios JS (RED→GREEN)                              | `5c29d1f` |
| T2  | S7      | inline | Guard `FormatPENLabelTest` con barrido de `resources/js` completo       | `a9fa1cc` |
| T3  | S5      | inline | Borrar `utils/formatters.js` y migrar sus importadores                  | `4ea0e0e` |
| T4  | S6      | inline | Migrar caja, servicios, composables y primitivas de UI                  | `b88747b`, `0e53389` |
| T5  | S6      | inline | Migrar dashboard, My day, agenda y detalle de citas                     | `ed2d293` |
| T6  | S6      | inline | Migrar pacientes e historias clínicas                                   | `4ea0e0e` |
| T7  | S6      | inline | Migrar BI, catálogo, continuidad, laboratorio y sedes                   | `1032cb0` |
| T8  | S8, S10 | inline | Verificación completa, docs (ux-guidelines, AGENTS §12) y auditoría U11 | pendiente |

## Log

- **L1** (pedido original, verbatim): «haz la issue #451 en un worktree nuevo»
- **L2** (decisión de alcance): el módulo único es
  `resources/js/composables/useFormatters.js`; `utils/formatters.js` se borra
  entero (sus otros exports no tienen importadores). Locale única `es-PE`, reloj
  24 h, y las funciones reciben solo opciones de forma (campos de
  `Intl.DateTimeFormat`), nunca locale ni huso horario.
- **L3** (hallazgo del instrumento U11): `shapeOf` en
  `tests/e2e/audit/u11/report.js` colapsa las corridas de dígitos pero conserva
  el separador de miles, así que `S/ 480.00` y `S/ 1,200.00` —ambos del helper
  canónico— se leen como dos formas. La divergencia de moneda medida en
  `/procedure-catalog`, `/reception-procedures`, `/cash-register` y
  `/business-intelligence` es de magnitud, no de formato; se decide al cerrar si
  se declara ACE o se corrige el instrumento.
- **L4** (alcance real de migración): además de los 20 `formatDate` locales hay
  `toLocaleString` de fecha en caja, agenda, catálogo y continuidad, y formateo
  de moneda propio en `NotificationService`, `useTransactions`, `ReceiptPreview`,
  `TreatmentPlanSelector`, `ProcedureSelector` y `useAuditLogs`. Todo entra en
  S6 porque el guard de S7 escanea el árbol completo.
- **L5** (dependencias del worktree): `vendor` se comparte por symlink con el
  checkout principal; `node_modules` se instala con
  `pnpm install --frozen-lockfile --prefer-offline` para que `pnpm exec` no
  aborte por el symlink.
- **L6** (guard RED, medido con
  `vendor/bin/phpunit --filter FormatPENLabelTest` sobre `89d1e00`): 5
  declaraciones de moneda fuera del canon (`ReceiptPreview.vue`,
  `useTransactions.js`, `AiAnalysisModal.vue` con USD, `NotificationService.js`,
  `utils/formatters.js`), 58 sitios de formateo por locale (fechas, horas y
  números) y 4 prefijos literales `S/` delante de un helper o de `toFixed(2)`.
  El barrido incluye `Intl.NumberFormat` y `toLocaleString` completos, no sólo
  los de moneda y fecha, porque tras la migración no queda ningún uso legítimo
  fuera del canon y así el guard coincide con el eje `format` de U11
  (`intl`/`locale`). Excepciones declaradas: el propio módulo canónico y
  `CurrencyInput.vue` (display numérico del campo, ya fijado por
  `test_currency_input_formatter_unchanged`).
- **L7** (reglas de mapeo para la migración): un `toLocaleDateString` de día,
  mes y año pasa a `formatDate(value)`; mes largo a
  `formatDate(value, { day: 'numeric', month: 'long', year: 'numeric' })`;
  mes corto a `formatDate(value, { day: 'numeric', month: 'short', year: 'numeric' })`;
  fecha con hora a `formatDateTime(value, options)` conservando sus campos de
  fecha; hora a `formatTime(value)`; conteos a `formatNumber(value, options)`.
  Los textos de respaldo (`'—'`, `'Sin fecha'`, `'N/A'`) se conservan en el
  call site. Las tablas de meses escritas a mano
  (`DashboardPage`, `labCaseMeta`) desaparecen. Los tests que fijaban formas
  `es-ES` (`calendarDateMath`, `labCaseMeta`, `continuityMeta`) se actualizan a
  la forma `es-PE`, que agrega punto a la abreviatura del mes (`mar.`).
- **L8** (hallazgo de la verificación, corregido): un campo de tipo fecha
  serializado como medianoche UTC (`promised_at:
  '2026-10-24T00:00:00.000000Z'`) retrocedía un día al convertirse como instante.
  Un campo fecha es un día calendario, así que el canon exporta `dateOnly(value)`
  (recorta `YYYY-MM-DD`) y `labCaseMeta`/`continuityMeta` lo pasan a `formatDate`.
  Los instantes (`delivered_at`, `created_at`) siguen usando `formatDateTime`.
  Pin: `tests/js/unit/formatters-single-source.test.js` y el smoke del tab de
  laboratorio del paciente.
- **L9** (hallazgo de la verificación, corregido): el comentario `#451` en
  `calendarDateMath.js` disparó el guard de colores crudos de
  `CitasCalendarMonthViewTest` (`#451` son tres dígitos hexadecimales). Los
  comentarios de `resources/js` nombran «issue 451» sin `#`; los documentos
  markdown conservan `#451`.
- **L10** (cortes de entrega): el primer commit
  (`5c29d1f`, 795 líneas) es una unidad de contrato: el módulo canónico y su
  propia suite no se pueden separar sin dejar el contrato sin oráculo. Los
  commits siguientes bajan de ese tamaño y cada uno queda verde por sí solo:
  servicios y UI (moneda), caja y análisis IA (moneda + sus pruebas), retiro de
  `utils/formatters.js` con pacientes e historias, dashboard/agenda/planes,
  BI/catálogo/continuidad/laboratorio/sedes, y al final el guard con su
  documentación. Ningún corte borra el módulo legacy antes de migrar a todos sus
  importadores.

## Evidencia

- Guard: `vendor/bin/phpunit --filter FormatPENLabelTest` → `OK (27 tests, 57
  assertions)` con el barrido de `resources/js` completo.
- Unidad canónica: `pnpm exec vitest run
  tests/js/unit/formatters-single-source.test.js` → 25 casos (currency sin
  `NaN`, USD, número, fecha sin desfase bajo `TZ=America/Lima`, reloj 24 h,
  `dateOnly`, robustez).
- Vitest completo: **1250 pruebas en 142 archivos**, verde.
- Suite MySQL completa (`vendor/bin/phpunit -c phpunit.mysql.xml` sobre la base
  aislada del worktree): **OK (2783 tests, 16970 assertions)**.
- Gates: `vendor/bin/pint --test` 891 archivos PASS; `pnpm exec eslint .` 0
  errores (36 avisos preexistentes); `pnpm exec prettier --check .` limpio;
  `pnpm build` verde; `node scripts/audit/doc-drift.mjs` 14/14.
- Aceptación del issue: el grep literal
  `grep -rn "Intl.NumberFormat('es-PE', { *style: *'currency'" resources/js`
  devuelve 1 resultado, y el guard
  `only_one_file_declares_the_pen_currency_formatter` fija que la declaración
  viva solo en el módulo canónico (tolera el salto de línea).
- U11 capa 1 (`static.js`): 36 superficies con `intl=0`, `locale=0` y
  `legacyModule=0`; 28 superficies consumen el helper de moneda.
- U11 capa 2 (`report.js`, app servida en `127.0.0.1:8011` sobre una base
  sembrada propia): eje `format` **OK (13 OK, 0 DIV, 22 N/A, 1 NO PROBADO)**.
  Antes de la corrección: 10 DIV en ese eje. La fila NO PROBADO es
  `/medical-records/:id`: la base sembrada de esta corrida no tenía una historia
  clínica de muestra. El gate de la sesión queda en FAIL por esa fila sin
  evidencia y por cuatro 403 clínicos de recepción en la ficha del paciente
  (frontera de escritorio de #313/#314), no por formato. La forma de moneda se
  mide ahora por formato y no por magnitud: `S/ 480.00` y `S/ 1,200.00` son la
  misma forma (`currencyShapeOf` en `tests/e2e/audit/u11/report.js`).
