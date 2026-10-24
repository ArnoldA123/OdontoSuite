# Registros de especialidad: fecha de cirugía y procedimiento del catálogo — issues #246, #247

Rama `feat/specialty-records-data`, worktree `~/proyectos/odontosuite-specialty-records-data`.
Superficie: `specialty-records` (5 tablas, 5 modelos, store/update, listado y UI del módulo).

## Specs

S1 — #246, persistencia: «`POST /api/specialty-records` y `PUT /api/specialty-records/{id}`
persisten `surgery_date`.»

S2 — #246, orden: «El listado de cirugía oral ordena por esa fecha, no por `created_at`.»

S3 — #246, interfaz: «El formulario y el detalle muestran la fecha de cirugía; un test Feature
fija el contrato.»

S4 — #246, validación: «Agregar `surgery_date` a `oral_surgery_records` (migración aditiva),
validarla en store/update (`date|before_or_equal:today`)».

S5 — #246, histórico (decisión aprobada): «Si se quiere histórico, backfill desde
`appointments.scheduled_at` cuando exista `appointment_id`, y desde `created_at` en el resto.»

S6 — #247, persistencia: «`POST` con `procedure_id` guarda el vínculo y `GET` lo devuelve.»
Modelo de datos aprobado: «Columna procedure_id en las 5 tablas» — FK nullable a
`procedure_catalog`, `nullOnDelete`, índice por tabla, mismo patrón que `appointments` y
`quotations`.

S7 — #247, validación: «La validación `exists:procedure_catalog,id` sigue vigente.»

S8 — #247, interfaz: «La interfaz permite elegir y ver el procedimiento del registro.»

S9 — #247, contrato: «Un test Feature fija el contrato de persistencia; sin cambios silenciosos
en los payloads existentes.»

S10 — #246 nota relacionada (decisión aprobada): `rehabilitation_records` gana `try_in_date`
(nullable, `date`), coherente con el estado `try_in`; visible en formulario y detalle.

## Tasks

| ID  | Spec            | Ruta                                                                  | Commit     |
| --- | --------------- | --------------------------------------------------------------------- | ---------- |
| T1  | S6, S7, S9      | #247 `procedure_id` en las 5 tablas: migración, `$fillable`, relación  | `0718ee5`  |
| T2  | S1, S2, S4, S5  | #246 `surgery_date` + backfill, regla store/update, orden del listado  | `b6a8a36`  |
| T3  | S10             | `try_in_date` en rehabilitación: migración, regla, modelo              | `1bc2105`  |
| T4  | S3, S8          | Interfaz: meta, selector de procedimiento, fechas visibles, tests JS   | `78c94ee`  |
| T5  | S1-S10          | Docs (`AGENTS.md` §4/§12) y guardas del repositorio                    | `2762024`  |
| T6  | S6              | Hallazgo de verificación: `show()` precarga `procedure`                 | `6922ddd`  |
| T7  | S9              | Hallazgo: 500 de ortodoncia en `show()` + clave `dental_piece`          | `0c450b0`, `cb8faa9` |

## Slices de entrega

- **Slice 1 — vínculo con el catálogo** (`0718ee5`): T1, 329 líneas propias.
- **Slice 2 — fechas clínicas de cirugía** (`b6a8a36`): T2, 301 líneas propias.
- **Slice 3 — fecha de prueba de rehabilitación** (`1bc2105`): T3, 162 líneas propias.
- **Slice 4 — interfaz y docs**: T4 `78c94ee` + T5. La rama completa supera las ~400 líneas
  de un PR único, así que la entrega se propone como cadena de dos PRs: backend
  (T1-T3) e interfaz + docs (T4-T5), o como PR único sólo si se acepta el
  presupuesto de revisión ampliado. Push, PR y merge quedan a decisión del
  usuario.

## Verificación

- Tests Feature con `phpunit.mysql.xml` contra la base dedicada del worktree:
  `DB_DATABASE=odontosuite_specialty_records_test DB_SOCKET= php artisan test --configuration=phpunit.mysql.xml`.
  `DB_SOCKET` vacío fuerza el MySQL 8.0 de Docker en `127.0.0.1:3307` en lugar del MariaDB
  local por socket.
- Guardas: `pnpm test:js`, `pnpm lint:check`, `pnpm format:check`, `vendor/bin/pint --test`,
  `node scripts/audit/doc-drift.mjs`.

## Log

L1 — «quiero hacer issues #246, #247 desarrolles, en un worktree nuevo»

L2 — Decisiones aprobadas: (a) #247 con columna `procedure_id` nullable en las cinco tablas,
no tabla puente; (b) `try_in_date` de rehabilitación entra en este alcance; (c) backfill de
`surgery_date` desde `appointments.scheduled_at` y, en su defecto, `created_at`.

L3 — Evidencia: `password` no aplica. Backend: 25 tests / 99 aserciones verdes con
`phpunit.mysql.xml` sobre `odontosuite_specialty_records_test` (T1 5/29, T2+T3+sus contratos
vecinos 25/99). Interfaz: `vitest` 29 tests verdes (22 unitarios + 7 smoke), ESLint y Prettier
sin hallazgos. Guardas completas: suite backend 1971 tests / 9809 aserciones verdes;
`vitest` completo 874 tests / 105 archivos verdes; `eslint .` y `prettier --check .` sin
hallazgos; `pint --test` 639 archivos verde; `doc-drift` 12/12 con 117 migraciones.

T5 entregado en `2762024` (AGENTS.md §4/§12). El documento de feature vive en `odd/`, que el
`.gitignore` excluye: queda como artefacto local del worktree, no viaja en el commit.

L4 — Hallazgo de entorno: con `vendor` como symlink al worktree principal, Laravel infiere el
base path desde Composer y ejecuta el `app/` y las migraciones del repo principal; en este
worktree se usó `vendor` real. `DatabaseMigrations` está roto en el repo por un `down()` ajeno
(`transactions_cash_register_session_id_status_index`), así que el backfill se verifica por la
costura pública `backfill()` dentro de `RefreshDatabase`.

L5 — Verificación independiente (`gentle-ai-verify`): S1-S10 se sostienen, con evidencia
`file:line` y 30 tests / 128 aserciones re-ejecutados. Hallazgos: (a) `GET
/specialty-records/{id}` no precargaba `procedure`; cerrado en `d98cc72` con test RED→GREEN y
`SpecialtyRecordControllerShowQueriesTest` en verde; (b) `show()` sobre un registro de
ortodoncia responde 500 porque `OrthodonticsRecord` no define `dentalPiece` — preexistente,
fuera de alcance, queda como follow-up propuesto.

L6 — Riesgos residuales para la revisión: el backfill de `surgery_date` recorre filas
existentes (ejecutar fuera de ventana de tráfico en tablas grandes); el selector de
procedimiento no se probó contra la API viva; los smoke del modal se escribieron después de
la implementación.

L7 — Entrega: rebase sobre `origin/main` (33 commits nuevos, conflicto resuelto sólo en
`AGENTS.md` §12 conservando las entradas de otros agentes), re-verificación completa post-rebase
(1988 tests backend / 871 JS, pint, eslint, prettier, doc-drift 14/14) y PR #271.
