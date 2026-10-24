# Professionals Functionality Audit

Rama: `fix/professionals-functionality-audit` (desde `main` @ 862f416)
Worktree: `.worktrees/professionals-audit`
Creado: 2026-10-19 · Orquestador: el Gentleman

## Specs

**S1 — Pedido del usuario (verbatim).**
"ahora continuando este proyecto quiero verificar el funcionameinto como tal y los bugs que encuentres y problemas, en cuando a los profesionales com tal todo ese apartado sea front inconsistencias o backend como tal, has un wortre y ahi trabajalo"

**S2 — Decisión de producto: borrado (verbatim de la opción aprobada).**
"Bloquear y pedir desactivar (recomendado)"
"Responde 409 con mensaje claro: tiene N registros asociados, desactívalo en su lugar. Sin migración, reversible, cero pérdida de datos."
Preview aprobado:
```
DELETE /api/users/11
→ 409
{
  "message": "No se puede eliminar a Wilmer Valderrama: tiene 18 citas, 3 historias clínicas y 5 transacciones asociadas. Desactívalo en su lugar."
}

+ se impide eliminarse/desactivarse a sí mismo
+ no toca el esquema
```

**S3 — Decisión de alcance (verbatim de la opción aprobada).**
"Todo, incluido el pulido menor"
Preview aprobado:
```
Recomendado + B9/B10
 · UserResource cableado al controller
 · eliminar rol fantasma 'dentista'
 · badge action variant válida
 · submit con Enter en modales
```

**S4 — Alcance recomendado (verbatim del preview aprobado).**
```
Backend
 · role whitelist = 7 roles reales
 · validar professional_license, branch_id,
   commission_rate, is_active
 · guardas de borrado/auto-borrado
 · orden estable en el index

Frontend
 · especialidades alineadas a la BD
 · matrícula + sucursal + estado en formularios
 · errores 422 por campo
 · auditoría: creación vs cambio
 · estado de error en la ficha

Tests: PHP feature + JS smoke (RED→GREEN)
```

## Hallazgos que esta feature corrige (evidencia medida)

| ID | Hallazgo | Evidencia |
|---|---|---|
| B1 | Crear implantólogo / técnico dental / asistente falla | `POST /api/users` con `role=implantologo` → `422 {"role":["The selected role is invalid."]}` |
| B2 | Editar a esos 3 roles falla siempre | `PUT /api/users/11` (rol sin cambios) → 422 |
| B3 | `destroy` borra historia clínica y financiera en cascada | 26 FKs `ON DELETE CASCADE` desde `users` (appointments, medical_records, clinical_evolutions, odontograms, treatment_plans, quotations, transactions, cash_movements, cash_register_sessions, payment_plans, receipts, interconsultations, …) |
| B4 | `professional_license`, `branch_id`, `commission_rate` no se persisten | `PUT` con los 3 campos → respuesta `null`; `validate()` descarta lo que no está en las reglas |
| B5 | Vocabulario de especialidades desalineado con la BD | BD: `implantology`, `dental_technician`, `assistant`, `finance`; `SPECIALTY_LABELS` no los tiene |
| B6 | Auditoría muestra "De: (vacío) → A: …" al crear | payload real `old_values: []` (array vacío es truthy en JS) |
| B7 | 422 del servidor se muestra como "Error de validación" | `useErrorHandler` prioriza `message` sobre `errors` |
| B8 | Ficha de detalle queda en "Cargando…" si el fetch falla | sin estado de error |
| B9 | `getAuditActionVariant` devuelve `'secondary'` | no es variante válida de `UiBadge` |
| B10 | `active()` publica el rol inexistente `dentista`; `UserResource` sin uso | código |
| B11 | Index sin orden estable | `User::query()->get()` |

## Contexto de la deuda

La feature previa (`odd/tasks/professionals-interface-redesign.md`, commit `9c5b478`)
decidió mostrar matrícula y sucursal (D10) y usar la lista completa de roles
clínicos (D4), pero nunca se validó el alta/edición de esos roles contra el
backend ni se cablearon los campos al formulario. Esta feature cierra esa deuda.

## No-alcance

- Sin migraciones ni cambios de esquema (decisión S2).
- `commission_rate` no se expone en la UI (sigue siendo dato sensible de
  finanzas, decisión previa vigente); sí se valida en la API para que no se
  descarte en silencio.
- No se toca ningún otro módulo.

## Tasks

| # | Task | Specs | Estado | Commit |
|---|---|---|---|---|
| T1 | Backend: whitelist de los 7 roles reales + persistir matrícula, sucursal, comisión y estado | S1, S4 | ✅ hecha | f03829a |
| T2 | Backend: borrado protegido (409 con conteos), guardas de auto-borrado/auto-desactivación, orden estable | S2, S4 | ✅ hecha | f03829a |
| T3 | Frontend: vocabulario de especialidades alineado a la BD (visualización + filtro) | S4 | ✅ hecha | 19392fb |
| T4 | Frontend: matrícula, sucursal y estado en los formularios; errores 422 por campo; Enter en modales | S3, S4 | ✅ hecha | 86dff0c |
| T5 | Frontend: auditoría creación vs cambio, estado de error en la ficha, variante de badge válida | S3, S4 | ✅ hecha | 5fe7f32 |
| T6 | Pulido backend (UserResource, rol fantasma) + cierre y verificación completa | S3 | ✅ hecha | (este cierre) |

## Log

- L1 — (usuario, verbatim) "ahora continuando este proyecto quiero verificar el funcionameinto como tal y los bugs que encuentres y problemas, en cuando a los profesionales com tal todo ese apartado sea front inconsistencias o backend como tal, has un wortre y ahi trabajalo"
- L2 — Auditoría ejecutada: API en vivo contra la BD de desarrollo (login `elizabet`, `GET /api/users?role=…`, `POST`, `PUT`, `DELETE`, `GET /api/audit-logs/user/{id}`) + inspección de FKs en `information_schema`. Hallazgos B1–B11 registrados arriba con su evidencia.
- L3 — (usuario, decisión) "Bloquear y pedir desactivar (recomendado)" para el borrado; "(recomendado)" se materializa como 409 con conteos y guardas de auto-borrado/auto-desactivación.
- L4 — (usuario, decisión) "Todo, incluido el pulido menor" como alcance de corrección.
- L5 — Nota de entorno: la suite MySQL se ejecuta contra el servicio `mysql:8.0` de `docker-compose.yml` (puerto 3307). Requiere `DB_SOCKET=` vacío en el entorno, porque `.env` define `DB_SOCKET` y Laravel prioriza el socket sobre `DB_PORT`; sin vaciarlo los tests golpean el MariaDB de XAMPP. Comando: `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=odontosuite_test DB_USERNAME=odontosuite DB_PASSWORD=secret DB_SOCKET= php artisan test`.
- L6 — T3 cerrado: `SPECIALTY_LABELS` cubre `implantology`, `dental_technician`, `assistant` y `finance`; `SPECIALTY_OPTIONS` pasa a ser la lista explícita de los 9 códigos clínicos seleccionables y el filtro (unión con los códigos presentes en el equipo) y el modal de edición ofrecen los códigos reales de la BD. RED→GREEN en `tests/js/smoke/professionals-constants.test.js` (2 tests) y `tests/js/smoke/professionals.test.js` (17 tests). Sin commit, sin push: el worktree queda con los cambios sin stagear.
- L7 — Frontera de slice: `f03829a` cierra T1+T2 (backend, ~414 líneas autoradas) como una sola unidad de revisión porque ambas mitades son el mismo contrato de mutación del controller (`storeRules` + `update`/`destroy`). El slice siguiente empieza en T3 (frontend de especialidades) y no comparte archivos con el anterior. Verificación: PHP `UserProfileMutationTest` + `UserDeletionGuardTest` 12 tests / 61 assertions GREEN; vitest 52 archivos / 425 tests GREEN.
- L9 — T4 implementado: matrícula, sucursal y estado en los modales de alta y edición (`professional_license`, `branch_id`, `is_active`), mapeo de errores 422 por campo (`applyServerErrors`) y envío con Enter (`onFormEnter`, ignora el trigger del `UiSelect`). RED: 4 tests nuevos fallando; GREEN 21/21 en `professionals.test.js`; suite JS 52 archivos / 429 tests; ESLint 0.
- L10 — Frontera de slice: `f03829a` backend, `19392fb` T3, `5fe7f32` T5, siguiente slice T4 (frontend de formularios). T4 comparte `ProfessionalsPage.vue` con T3, por eso su commit va después del de T3.
- L8 — T5 implementado y verificado: `getChangesSummary` devuelve `null` cuando `old_values` no tiene claves (una creación deja de renderizarse como diff vacío), la ficha muestra estado de error en vez de "Cargando…" y la variante de badge desconocida pasa a `default`. RED observado por stash de las dos fuentes (2 tests fallando), GREEN 9/9.
- L11 — T6 implementado: `index`/`show`/`store`/`update` serializan con `UserResource` (show conserva `audit_logs` vía `whenLoaded`, payload aditivo) y `active()` deja de ofrecer el rol fantasma `dentista`. RED observado por stash del controller (2 tests fallando: falta `specialties`, aparece el usuario `dentista`); GREEN 9/9 en `UserSerializationTest` + `UserProfileMutationTest`.
- L12 — Cierre verificado: suite backend completa 1286 tests / 5613 assertions GREEN contra `mysql:8.0` (docker, puerto 3307); vitest 52 archivos / 429 tests GREEN; `pnpm build` OK; ESLint 0; `node scripts/audit/doc-drift.mjs` 12/12 OK (sin cambios de rutas, modelos ni migraciones, AGENTS.md no requiere actualización). Fallo intermedio detectado y corregido: `test_index_returns_the_team_in_a_stable_name_order` asumía una BD sin otros odontólogos; ahora verifica posición relativa y estabilidad entre dos peticiones.
- L13 — Verificación en vivo contra la BD de desarrollo (servidor del worktree): `POST /api/users` con `implantologo` → 201 (antes 422); `PUT /api/users/11` (implantólogo) → 200 (antes 422); `PUT` con matrícula/sucursal/comisión/estado → 200 y valores persistidos; `DELETE /api/users/11` → 409 con los conteos (antes borraba en cascada); `DELETE` de la propia cuenta → 422; `DELETE` de una cuenta sin historia → 200. Usuario temporal creado y eliminado; la BD de desarrollo queda sin residuos.
- L14 — Commits del slice final: `f03829a` (T1+T2), `19392fb` (T3), `5fe7f32` (T5), `86dff0c` (T4), `3db6986` (T6). Frontera de slice registrada en L7/L10; sin push ni PR (decisión del usuario).
