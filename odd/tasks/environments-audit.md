# Environments Audit — verificación funcional de Ambientes (front + back)

Rama: `fix/environments-audit` · Worktree: `.worktrees/environments-audit` (desde `main` @ 862f416)
Creado: 2026-10-19 · Orquestador: el Gentleman

## Specs

- **S1** — Todo el trabajo vive en el worktree `.worktrees/environments-audit` (rama `fix/environments-audit`). `main` y el worktree `patients-audit` no se modifican.
- **S2** — Eliminar un ambiente nunca borra citas ni bloqueos en cascada. Si tiene citas (incluidas soft-deleted) o bloqueos, `DELETE /api/dental-chairs/{id}` responde 409 con `No se puede eliminar el ambiente porque tiene citas o bloqueos asociados.` y no modifica datos. Sin relaciones, elimina y registra auditoría.
- **S3** — El código `AMB-###` es único y secuencial aunque se hayan eliminado ambientes: con AMB-001..AMB-003 existentes, tras eliminar AMB-002 el siguiente creado es AMB-004 (nunca reutiliza un código vivo ni falla con 500 por duplicado).
- **S4** — IDs inexistentes: `PUT` y `DELETE` responden 404 con `Ambiente no encontrado`; `GET` responde 404 solo para inexistente y 500 genérico (`Error al obtener ambiente`) sin filtrar mensajes internos de SQL.
- **S5** — Visibilidad coherente: `GET /api/dental-chairs` y `GET /api/dental-chairs/search` aplican la misma regla (`is_active = true`) y ordenan por nombre; `GET /api/dental-chairs/active` conserva el contrato `is_active = true` y acepta `?status=` validado; el alta de citas (`NewAppointmentModal`) solicita `?status=active` para no ofrecer ambientes en `maintenance` o `inactive`.
- **S6** — Lista frontend (`/environments`): la búsqueda espera 300 ms desde la última tecla y descarta respuestas obsoletas; el filtro de estado es consistente con o sin búsqueda activa; 403 muestra `No tienes permisos para ver los ambientes.`; 500 y fallo de red muestran mensajes distintos entre sí (un 404 no es un resultado esperable de una colección). Se conservan los `data-*` y el flujo create/edit/delete.
- **S7** — Detalle frontend (`/environments/:id`): interpreta el payload real de auditoría (arrays vacíos, no `null`). Creado muestra `Ambiente creado en el sistema.`, eliminado `Ambiente eliminado del sistema.`, actualizado muestra el diff; `action` nulo no rompe. Error 404 muestra `El ambiente no existe.` y 403 `No tienes permisos para ver este ambiente.`
- **S9** — El archivado (`is_active=false`) es una capacidad gestionable: `GET /api/dental-chairs` y `/search` aceptan `archived=exclude|include|only` (default `exclude`, validado); la lista ofrece el filtro `Sin archivados / Incluir archivados / Solo archivados`, marca las filas archivadas con el badge `Archivado`, permite `Archivar` (con confirmación) y `Reactivar` (sin confirmación), y el borrado sigue reservado a ambientes sin historial. `AMB-008` del seeder queda accesible al activar `Solo archivados`.
- **S8** — Regresión: tests focalizados MySQL (`DentalChair*`, `AuditLog*`), tests JS del módulo, ESLint y build en verde; los contratos de citas, auditoría y BI no cambian.

## Tasks

- T1 · S2 · inline · commit 1
- T2 · S3 · inline · commit 1
- T3 · S4 · inline · commit 2
- T4 · S5 · inline · commit 2
- T5 · S6 · inline · commit 3
- T6 · S7 · inline · commit 3
- T7 · S8 · inline · commit 3

## Log

- **L1** — `ahora continuando este proyecto quiero verificar el funcionameinto como tal y los bugs que encuentres y problemas, en cuanto a los ambientes com tal todo ese apartado sea front inconsistencias o backend como tal, has un wortre y ahi trabajalo`
- **L2** — Evidencia de auditoría (2026-10-19):
  - `appointments_dental_chair_id_foreign` es `ON DELETE CASCADE`; `appointment_blocks_dental_chair_id_foreign` también. `DentalChairController@destroy` hace hard delete: borra en cascada citas (108 en dev) y bloqueos, saltándose SoftDeletes.
  - `store()` genera `code` con `DentalChair::count() + 1`: colisión garantizada tras cualquier baja (código único en BD → 500).
  - `update()`/`destroy()` con id inexistente: `findOrFail` cae en `catch (\Exception)` → 500 con mensaje SQL filtrado.
  - `index()` filtra `is_active=true` sin orden; `search()` no filtra `is_active` ni ordena → la misma fila archivada aparece buscando pero no listando.
  - `active()` filtra solo `is_active=true`: ambientes en `maintenance`/`inactive` son agendables desde `NewAppointmentModal.vue:321`.
  - Payload real de auditoría: `old_values`/`new_values` son `[]` (no `null`); el detalle solo contempla `null`, así que las ramas "Ambiente creado/eliminado" son inalcanzables.
  - Lista: sin debounce ni guarda de respuestas obsoletas; errores 403/404/500 se muestran como "Revisa tu conexión".
- **L3** — Línea base en el worktree (2026-10-19): 21 tests JS del módulo en verde; 662 tests `tests/Unit/DesignSystem` en verde; `DentalChairStatusFilterTest` corre contra MySQL local por socket.
- **L4** — Incidente de entorno: otras sesiones concurrentes (`patients-audit`, `procedure-catalog-ui-redesign`) comparten `odontosuite_test`; sus `migrate:fresh` borraban tablas a mitad de la cadena. Este worktree usa `odontosuite_env_audit_test` (override `DB_DATABASE`) y `./vendor/bin/phpunit -c phpunit.mysql.xml`. También se reemplazó el symlink `vendor` por una copia con autoload propio (el symlink resolvía `Tests\` y `App\` a `main`).
- **L5** — Rebanadas reales: commit 1 = backend T1-T4 + migración restrict + tests (24 verdes con `DentalChairLifecycleTest`, `DentalChairStatusFilterTest`, `AuditLogFiltersTest`); commit 2 = T5-T6 frontend + tests; commit 3 = T6 detalle; commit 4 = contrato `/active` + consumidor de agenda; commit 5 = archivado gestionable (S9).
- **L6** — T5/T6 implementados y verificados de forma independiente (el subagente cerró con estado anómalo): 41 tests JS verdes, ESLint 0 errores (1 warning Prettier preexistente en `EnvironmentsPage.vue:10`). Extra sobre el contrato: el flujo de borrado ahora muestra el mensaje 409 del servidor (antes el `customMessage` lo ocultaba) y el filtro de estado cancela un debounce pendiente. El 404 de lista se excluyó de S6 por no ser un resultado esperable de la colección.
- **L7** — Verificación final: MySQL aislado 35/35 (lifecycle + status filter + audit filters); Vitest 41/41 del módulo; `vite build` en verde; API real (login admin, dev DB) confirma índice ordenado sin archivados, search sin archivados, `/active?status=active` operativo, PUT/DELETE inexistente = 404. `tests/Unit` completo: 1000 tests / 39 errores preexistentes idénticos en `main` (SQLite #79). Riesgo de contrato compartido corregido: `/active` no cambia de semántica para BI; el filtro operativo se pide explícitamente desde la agenda.
- **L8** — Decisión de ingeniería (solicitada por el usuario): no se maquilla el seeder; `is_active` pasa a ser un estado administrable de primera clase. Se añade `archived=exclude|include|only` al backend, filtro tri-estado + badge + archivar/reactivar en la lista, y el borrado queda como última opción. Alternativa descartada: sembrar `AMB-008` visible, porque dejaba el archivado sin UI y volvía a crear registros inalcanzables en el futuro.
- **L9** — CI post-merge (run 39337344427) falló solo en backend: 11 fallos de `DentalChairLifecycleTest` porque una clase previa (`MigrateFreshPortabilityTest`) deja la BD sembrada en el mismo proceso y mis fixtures usaban `AMB-001..004` + igualdad exacta de listados. Corrección: códigos únicos fuera del namespace numérico para los casos de borrado, códigos numéricos adaptativos (`max+1`) para generación, y aserciones membership-based filtradas a los fixtures (mismo patrón que `DentalChairStatusFilterTest`). Verificación: suite MySQL completa local en un proceso con BD sembrada.
