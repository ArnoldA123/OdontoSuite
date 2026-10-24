# Feature: permissions catalog and clinical write gap (#280)

Issue #280 (`crit:alta`, `area:backend`). Branch `feat/permissions-catalog-280`,
worktree `.worktrees/feat-permissions-catalog-280`. First phase (F0) of the
permissions program: one catalog, one middleware, no role lists in routes, and
reception loses clinical write access everywhere. Merged as PR #297
(`db6d10a`) and archived here after the worktree was removed.

## Specs

S1. Catálogo de permisos con nomenclatura `verbo.recurso`.

S2. Middleware de autorización por permiso; las rutas declaran permiso, nunca
listas de roles.

S3. Grupos de usuario (recepcion, clinico, asistente, laboratorio, finanzas,
administrador) como conjuntos de permisos.

S4. Retirar la escritura clínica de recepción en todos los endpoints, incluido
el de cierre.

S5. Recepción recibe 403 al intentar escribir evolución por cualquier endpoint,
cubierto por test de matriz.

S6. Existe una sola fuente de verdad de permisos; `usePermissions` deriva de
ella.

S7. Test de matriz rol x permiso que falla si una ruta nueva declara roles en
vez de permiso.

S8. La fuente única vive en PHP (`App\Authorization\Permissions`): catálogo de
permisos + matriz rol → permisos. El cliente no repite la regla: recibe la
lista efectiva de permisos en `POST /api/auth/login` y `GET /api/auth/me` y
evalúa pertenencia. Los grupos de S3 son los roles existentes del sistema
(`users.role`): `clinico` = odontologo + implantologo + tecnico_dental +
asistente; `laboratorio` = tecnico_dental; no se crea una tabla de grupos.

S9. La matriz de S8 replica la autorización vigente en `routes/api.php` con una
sola diferencia deliberada: `complete.appointment` excluye `recepcionista`.
`PATCH /appointments/{id}/status` sigue disponible para recepción porque no
escribe historia clínica.

S10. Ninguna ruta conserva el middleware `role:`. El guard de S7 lee
`routes/api.php` y falla con archivo y línea cuando reaparece `role:`.

## Tasks

- T1 — `feat/permissions-catalog-280` — S1, S3, S8 — catálogo `Permissions` +
  middleware `CheckPermission` + alias + `permissions` en login/me + tests
  unitarios del catálogo.
- T2 — `feat/permissions-catalog-280` — S2, S4, S5, S7, S9 — migración de
  `routes/api.php` a `permission:` + retiro de `complete.appointment` a
  recepción + tests de matriz rol x permiso y de los endpoints reales.
- T3 — `feat/permissions-catalog-280` — S6 — `usePermissions` derivado de
  `user.permissions` + ocultar el cierre de consulta sin permiso + tests JS.
- T4 — `feat/permissions-catalog-280` — docs: `AGENTS.md` §7, matriz de
  `CREDENTIALS.md`, guard estático de `scripts/audit/probe-rbac.mjs`.
- T5 — verificación: PHPUnit MySQL completo, Vitest, Pint, ESLint, Prettier,
  `doc-drift`, evidencia y PR.

Estado de los slices (cada commit es una unidad revisable; el PR fue uno solo
porque las capas dependen entre sí):

| Slice | Commit | Contenido | Líneas autoradas |
| --- | --- | --- | --- |
| C1 | `2d7610b` `feat(auth): add permission catalog and middleware` | catálogo + middleware + payload de auth + tests unitarios | 660 |
| C2 | `0d4b472` `refactor(routes): declare permissions instead of role lists` | 198 rutas a `permission:` + guard de matriz + grieta cerrada | 764 |
| C3 | `ab14c17` `feat(frontend): derive permissions from the auth payload` | `usePermissions` derivado + UI del cierre + fixture de smoke | 992 |
| C4 | `f2d2b09` `docs(permissions): document the catalog and retire the role probe` | AGENTS §7/§12, CREDENTIALS, probe | ~120 |
| C5 | `b4ca66c` `style(permissions): format the fixture generator and the retired probes` | JSON a 2 espacios y probes formateados (CI Prettier) | 4 |
| C6 | `6718a2a` `style(permissions): separate the probe guard from the header comment` | cosmético del probe | 2 |

## Follow-ups (fuera de #280)

- T6 — Policies: `PatientPolicy`, `CashRegisterPolicy`, `CashMovementPolicy`,
  `AiImageAnalysisPolicy` y `AppointmentPolicy` todavía deciden con
  `in_array($user->role, ...)`. Deben consultar `$user->hasPermission(...)`
  conservando sus reglas contextuales (dueño de sesión, alcance de paciente).
  `AppointmentPolicy::delete` además permite admin+recepción mientras la ruta
  permite los seis roles; hoy la policy no se invoca desde ningún controller.
- T7 — `BroadcastingAuthController` autoriza canales privados con su propia
  matriz de roles; migrarlo a permisos de canal requiere permisos por canal
  (`view.channel.*`) y su propio PR.
- Permisos `FRONTEND_ONLY` (`manage.config`, `manage.cash_register`,
  `apply.transaction_discount`, `apply.large_transaction_discount`) no tienen
  consumidor en componentes hoy: deuda preexistente de la UI.

## Log

L1 (Arnold, verbatim): «quiero hacer la issue 280, en un worktree nuevo»

L2 (issue #280, verbatim): «## Contexto
La regla de permisos se escribe en tres fuentes divergentes: `routes/api.php`
(listas de roles por ruta), `resources/js/composables/usePermissions.js`
(segunda copia en el cliente) y las Policies (tercera copia, parcial).

## Problema
`POST /medical-records` está restringido a los roles clínicos, pero
`POST /appointments/{id}/complete` está abierto a seis roles y escribe
`ClinicalEvolution`, plan de tratamiento, materiales y cotización. Recepción
puede documentar clínica por la puerta del cierre.

## Alcance
- Catálogo de permisos con nomenclatura `verbo.recurso`.
- Middleware de autorización por permiso; las rutas declaran permiso, nunca
  listas de roles.
- Grupos de usuario (recepcion, clinico, asistente, laboratorio, finanzas,
  administrador) como conjuntos de permisos.
- Retirar la escritura clínica de recepción en todos los endpoints, incluido el
  de cierre.

## Criterios de aceptación
- Recepción recibe 403 al intentar escribir evolución por cualquier endpoint,
  cubierto por test de matriz.
- Existe una sola fuente de verdad de permisos; `usePermissions` deriva de ella.
- Test de matriz rol x permiso que falla si una ruta nueva declara roles en vez
  de permiso.

## Dependencias
Ninguna. Es la primera fase.

## Riesgo
item 2 (permisos) e item 3 (contrato de API).»

L3 — Exploración medida (worktree `792f9d9`): `routes/api.php` tiene 25 grupos
`middleware('role:...')`; `usePermissions.js` repite la matriz en ~60 claves
`can.*`; `ConsultationService::complete()` persiste `ClinicalEvolution`,
odontograma, plan, materiales, adjuntos y cotización. Único consumidor del
cierre: `useConsultation.js:111`, montado desde `CalendarPage.vue` para los
seis roles del grupo de citas. `PATCH status` acepta `completed` sin evolución
pero no escribe clínica: queda fuera del alcance con nota en S9.

L4 — Decisión D1 (S8): fuente única en PHP y lista efectiva por usuario en el
payload de autenticación. Se descarta un JSON generado en build: duplicaría el
catálogo en dos artefactos y volvería a permitir deriva. Se descarta una tabla
de grupos: los siete roles ya existen y la migración de roles a grupos es un
cambio de producto no pedido.

L5 — Decisión D2 (S6): `usePermissions` conserva sus claves públicas
(`can.createPatient`, `isClinical`, ...) para no tocar los consumidores; cambia
la implementación a pertenencia de permiso. Las claves cuya regla vigente
divergía del backend (`deleteAppointment`) pasan a reflejar el backend real.

L6 — Verificación (2026-10-21, worktree): `vendor/bin/phpunit -c
phpunit.mysql.xml` contra `odontosuite_perm280_test` → `OK (2092 tests, 12040
assertions)`. `vitest run` → `106 files, 886 tests` en verde. `vendor/bin/pint
--test` y `prettier --check` limpios; `eslint` sin hallazgos en los archivos
tocados. `php artisan route:list` conserva las 211 rutas de `main` (198 API) y
`RoutePermissionMatrixTest` confirma que ninguna ruta autenticada queda sin
permiso. CI del PR #297: backend MySQL y frontend build verdes en el primer
run; `quality` falló solo en Prettier por el fixture a 4 espacios y los probes
sin formatear; corregido en C5.

L7 — Verificación independiente (`gentle-ai-verify`, task mviy7c60-1-invw):
PASS en S5, S6 y S7; matriz vieja comparada grupo a grupo, única desviación
`complete.appointment`; sin hallazgos bloqueantes. Dos cosméticos en los probes
corregidos en `6718a2a`.

L8 — CI del PR #297 en verde: Quality gates, Backend tests (MySQL) y Frontend
build pasan; PR `MERGEABLE`. Mergeado el 2026-10-21 como `db6d10a`; issue #280
cerrada. Worktree y ramas eliminados; la base de test `odontosuite_perm280_test`
se eliminó al cerrar la feature.

L9 (Arnold, verbatim): «porque tienes dudas del odd las task de pasarlo al main
no lo hiciste en el merge??, ademas lo otro de la bd de test obvimnet si has
hecho un test y ya no sirve es obvio que lo elimines»

L10 — Corrección: este documento no viajó en el merge. El worktree se eliminó
con `git worktree remove --force` y con él el archivo físico; el mirror en
Engram existía, pero el archivo del checkout principal no. Lo que está hoy en
el main local **no es una copia del original** (que ya no existe): es una
reconstrucción hecha en la sesión desde el contenido redactado y el mirror,
con L9/L10 y la línea del merge añadidos. También es incompleto decir que
`odd/` no se versiona: `origin/main` tiene 32 documentos `odd/tasks/*.md`
versionados; la exclusión de `.git/info/exclude` es una convención de este
clon, no del repositorio. Regla que faltó y que queda escrita: antes de
eliminar un worktree, inventariar y preservar sus artefactos locales no
versionados (documento ODD, `.env`, base de datos de test, ramas) en el
checkout principal o en Engram, sin asumir que la exclusión local equivale a
"no va al repo".
