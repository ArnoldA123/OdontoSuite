# Informe final — programa de auditoría UI/funcional 2026-10

Cierre del plan
[#13](13-programa-auditoria-ui-funcional-2026-10.md) (issue #375, sesión
U12). Trece sesiones midieron el producto sin corregirlo; este informe responde
la única pregunta que las doce anteriores no podían responder solas: **¿está
perfecto o hay que arreglar?** No está perfecto. Las 36 superficies con pantalla
quedaron auditadas, y **68 defectos abiertos** tienen issue con evidencia. Dos
superficies no tienen ningún defecto abierto; 34 sí.

- **Base del cierre**: `da9db08` sobre `main`. Cada sesión midió su propia
  revisión (citada en su nota ODD); este informe no vuelve a medir producto.
- **Evidencia**: `.atl/qa-evidence/audit/ui/U0…U11/`, gitignored por §9 del
  plan. Lo que sobrevive al repositorio es `odd/tasks/audit-ui-u0.md`…
  `audit-ui-u12.md` (versionadas) y los issues publicados.
- **Alcance**: no se corrige producto ni se cierran issues de hallazgos (§8.1
  regla 6). Este informe decide, no arregla.

---

## 1. Qué se midió

| Capa                    | Instrumento                                          | Resultado medido                                                                 |
| ----------------------- | ---------------------------------------------------- | -------------------------------------------------------------------------------- |
| 1 · Manifiesto          | `scripts/audit/ui-manifest.mjs --all`                | **36 superficies**, **509 acciones** (0 sin clasificar), **59 diálogos**, **140 endpoints**, 3 permisos sin resolver (rutas anónimas y catch-all) |
| 2 · Render y salud      | Playwright, `tests/e2e/audit/surface.spec.js`        | **36/36 filas de render**, 0 `pageerror`, 0 scroll horizontal a 390 px, 35/36 con un solo `h1` |
| 3 · Contrato de primitivas | Playwright + matriz de ejes                       | **432 celdas** (36 rutas × 12 ejes), **0 en «NO PROBADO»**                        |
| 4 · Recorrido funcional | Walkthroughs dirigidos por sesión                    | 12 sesiones, cada una con su `walkthrough.md` y sus artefactos por paso           |
| 5 · Contrato API por rol | `api-matrix.json` por sesión                        | **1 049 filas** rol × endpoint en 7 sesiones (U3, U4, U5, U7–U10), **0 desajustes** con la expectativa de `App\Authorization\Permissions` |
| 6 · Rúbrica UX/UI       | `ux-rubric.md` por sesión                            | **29 superficies puntuadas**, 506/696 puntos (72,7 %); **19 criterios en 0**, todos con dueño |

**Dos rutas del router no son superficie**: `/` y `/appointments/new` redirigen
y quedan excluidas por el manifiesto. Las **36** restantes son las que este
informe declara una por una.

Comando reproducible de la línea base (`ui-pass --all`, §11.6 del plan):

```bash
node scripts/audit/ui-manifest.mjs --all --out=.atl/qa-evidence/audit/ui/U0/manifest.json
node scripts/audit/ui-pass.mjs --all --no-run
pnpm exec playwright test tests/e2e/audit/surface.spec.js
```

---

## 2. Tablero final

### 2.1 Veredicto por sesión

| Sesión | Superficies                            | Rúbrica      | Hallazgos | Dueños publicados            | Veredicto |
| ------ | -------------------------------------- | ------------ | --------- | ---------------------------- | --------- |
| U0     | arnés + línea base de 36 rutas          | —            | 4         | #378, #385; 1 semilla y 1 instrumento | Cumple: el instrumento corre |
| U1     | `/login`, `/reset-password`, 404, shell | **no en disco** | 7      | #378–#382                    | Rúbrica no archivada (brecha declarada) |
| U2     | `/dashboard`, `/my-day`                | 40/48        | 3         | #387–#389                    | Defectos medidos |
| U3     | `/calendar` + asistente                | 27/48        | 7         | #391–#397                    | Defectos medidos, 2 `crit:alta` |
| U4     | `/patients`, `/patients/:id`           | 31/48        | 9         | #399–#405                    | Defectos medidos, 1 `crit:alta` |
| U5     | caja + listos para facturar            | 33/48        | 8         | #407–#412                    | Defectos medidos, 5 `crit:alta` |
| U6     | presupuestos + planes                  | 33/48        | 11        | #414–#420, #422              | Defectos medidos, 5 `crit:alta` |
| U7     | historia, especialidades, IA           | 51/72        | 18        | #423–#429 (+ #385, #402, #405) | Defectos medidos, 3 `crit:alta` |
| U8     | catálogo (5 superficies)               | 98/120       | 13        | #431–#437 (+ #416, #420)     | Defectos medidos, 5 `crit:alta` |
| U9     | administración (8 superficies)         | 156/192      | 18        | #439–#445 (+ #402)           | Defectos medidos |
| U10    | BI, continuidad, laboratorio           | 37/72        | 10        | #447–#449 (+ #402, #429, #439, #392) | Defectos medidos, 1 `crit:alta` |
| U11    | consistencia 36 rutas × 12 ejes        | matriz       | 62 filas → 12 clases | #451–#456 + 5 dueños preexistentes | Divergencia transversal medida |
| U12    | consolidación                          | —            | —         | 1 publicado; 5 aceptaciones  | Este informe |

### 2.2 Veredicto por ruta

Cada fila declara la ruta, el rol con el que se midió en U11, los dueños que la
tocan y el veredicto. **`SANA`** = ningún defecto abierto la nombra;
**`CON DEFECTOS`** = al menos un issue abierto la nombra, con el número de
dueños.

| #  | Ruta                          | Rol medido    | Rúbrica | Dueños abiertos                                        | Veredicto |
| -- | ----------------------------- | ------------- | ------- | ------------------------------------------------------ | --------- |
| 1  | `/login`                      | anónimo       | no arch. | —                                                     | **SANA** |
| 2  | `/reset-password`             | anónimo       | no arch. | #382                                                  | CON DEFECTOS (1) |
| 3  | `/dashboard`                  | recepción     | 21/24   | #387 #388 #415 #451                                    | CON DEFECTOS (4) |
| 4  | `/calendar`                   | recepción     | 13/24   | #391 #392 #394 #395 #396 #397 #455                     | CON DEFECTOS (7) |
| 5  | `/my-day`                     | odontología   | 19/24   | #387 #389 #391 #455                                    | CON DEFECTOS (4) |
| 6  | `/patients`                   | recepción     | 14/24   | #401 #402 #403 #405 #415 #451 #453                     | CON DEFECTOS (7) |
| 7  | `/patients/:id`               | recepción     | 17/24   | #387 #399 #400 #404 #405 #451 #455 #456                | CON DEFECTOS (8) |
| 8  | `/professionals`              | administración | 12/24  | #439 #441 #442 #452 #453 #455 #456                     | CON DEFECTOS (7) |
| 9  | `/professionals/:id`          | administración | módulo 12/24 | #452                                            | CON DEFECTOS (1) |
| 10 | `/environments`               | administración | 22/24  | #415 #439 #440 #455 #456                               | CON DEFECTOS (5) |
| 11 | `/environments/:id`           | administración | módulo 22/24 | #405                                            | CON DEFECTOS (1) |
| 12 | `/appointment-types`          | administración | 15/24  | #415 #439 #440 #453 #455                               | CON DEFECTOS (5) |
| 13 | `/appointment-types/:id`      | administración | módulo 15/24 | #415 #439 #443 #444 #452                        | CON DEFECTOS (5) |
| 14 | `/specialties`                | administración | 22/24  | #439 #456                                              | CON DEFECTOS (2) |
| 15 | `/procedure-catalog`          | administración | 18/24  | #420 #432 #433 #434 #436 #451 #453 #455                | CON DEFECTOS (8) |
| 16 | `/procedure-catalog/:id`      | administración | 18/24  | #434 #435 #437 #452                                    | CON DEFECTOS (4) |
| 17 | `/procedure-stats`            | finanzas      | 23/24   | #451 #456                                              | CON DEFECTOS (2) |
| 18 | `/my-procedures`              | odontología   | 21/24   | #415                                                   | CON DEFECTOS (1) |
| 19 | `/reception-procedures`       | recepción     | 18/24   | #432 #451 #456                                         | CON DEFECTOS (3) |
| 20 | `/business-intelligence`      | finanzas      | 12/24   | #402 #405 #439 #447 #451 #456                          | CON DEFECTOS (6) |
| 21 | `/cash-register`              | finanzas      | 14/24   | #407 #408 #409 #410 #411 #451 #452 #455                | CON DEFECTOS (8) |
| 22 | `/cash-register/ready-to-bill` | finanzas     | 19/24   | #412 #415 #452 #459                                    | CON DEFECTOS (4) |
| 23 | `/treatment-plans`            | odontología   | 16/24   | #415 #417 #418 #419 #420 #455 #456                     | CON DEFECTOS (7) |
| 24 | `/quotations`                 | finanzas      | 17/24   | #414 #415 #416 #420 #422 #455 #456                     | CON DEFECTOS (7) |
| 25 | `/medical-records`            | odontología   | 17/24   | #405 #424 #425 #451 #455                               | CON DEFECTOS (5) |
| 26 | `/medical-records/:id`        | odontología   | incl. 17/24 | #387 #405 #424 #425 #451 #455 #456                 | CON DEFECTOS (7) |
| 27 | `/specialty-records`          | odontología   | 21/24   | #428 #452                                              | CON DEFECTOS (2) |
| 28 | `/ai-analysis`                | odontología   | 13/24   | #385 #402 #423 #426 #427 #429 #454 #456                | CON DEFECTOS (8) |
| 29 | `/continuity`                 | recepción     | 12/24   | #429                                                   | CON DEFECTOS (1) |
| 30 | `/lab-cases`                  | recepción     | 13/24   | #448 #449                                              | CON DEFECTOS (2) |
| 31 | `/settings/branches`          | administración | 21/24  | #402 #456                                              | CON DEFECTOS (2) |
| 32 | `/settings/branches/:id`      | administración | módulo 21/24 | #405                                            | CON DEFECTOS (1) |
| 33 | `/branch-transfers`           | recepción     | 21/24   | #440 #454                                              | CON DEFECTOS (2) |
| 34 | `/settings/payment-methods`   | administración | 20/24  | #439 #445 #456                                         | CON DEFECTOS (3) |
| 35 | `/settings/accounting-locks`  | administración | 23/24  | —                                                      | **SANA** |
| 36 | `/:pathMatch(.*)*` (404)      | recepción     | —       | #381                                                   | CON DEFECTOS (1) |

`módulo N/24` significa que la rúbrica de U9 puntuó el módulo completo (lista y detalle), no la ruta de detalle por separado.

**Recuento**: 2 rutas sanas, 34 con defecto abierto. La ruta 22 suma #459, el
único hallazgo que publica U12 (§7.3).

---

## 3. «Sin defectos» — la lista explícita y su evidencia

El plan declara «sin defectos» un resultado válido y exige evidencia dura
(§8.1 regla 7): render limpio en todos los roles, contrato de primitivas
cumplido, rúbrica con todos los criterios ≥ 1 y walkthrough con todas las
acciones `OK`. Solo dos rutas no tienen ningún defecto abierto, y una de ellas
—`/login`— arrastra la brecha de la rúbrica de U1 (§8.1):

| Ruta                         | Evidencia medida                                                                                                                                                    |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/login`                     | U11: 8 ejes `OK`, 1 `ACE` (no carga datos), 3 `N/A`, 0 hallazgos; U1: login válido 200, inválido 422 sin filtración, throttle `422,422,422,429`, 0 `pageerror`; render a 390 sin overflow. **Rúbrica no archivada** |
| `/settings/accounting-locks` | **Cumple la definición completa**: U9 23/24 con los 12 criterios ≥ 1, sin hallazgo; U11 10 ejes `OK`, 0 `DIV`, 0 hallazgo; render por rol sin `pageerror` y sin overflow a 390 px |

Cuatro superficies quedan en el borde: `/professionals/:id`,
`/environments/:id`, `/procedure-stats` y `/my-procedures` no tienen defecto
**propio** de su sesión, pero sí una clase transversal de U11 (estados, filtros,
formato o feedback). No se cuentan como sanas: el usuario las ve con el defecto.

La rúbrica no se redondea: **19 celdas en 0** y **26 superficies por debajo de
24/24**. Las que están más cerca del canon son `/procedure-stats` (23/24),
`/settings/accounting-locks` (23/24), `/specialties` (22/24),
`/environments` (22/24) y `/branch-transfers` (21/24).

---

## 4. Criterios de la rúbrica en 0 y su dueño

Cada criterio en 0 exige issue (§11.3). Esta es la correspondencia completa:

| Sesión | Superficie       | Criterio en 0                  | Dueño |
| ------ | ---------------- | ------------------------------ | ----- |
| U3     | `/calendar`      | 5 · Estados completos          | #396 |
| U3     | `/calendar`      | 10 · Responsive                | #397 |
| U3     | `/calendar`      | 12 · Honestidad                | #396 + aceptación U12 (§7.4: badge «En vivo») |
| U3     | asistente        | 6 · Primitivas                 | #391 |
| U3     | asistente        | 9 · Accesibilidad              | #391 |
| U5     | `/cash-register` | 4 · Acciones con efecto        | #407 #408 #409 |
| U5     | `/cash-register` | 5 · Estados completos          | #411 |
| U5     | `/cash-register` | 12 · Honestidad                | #407 #408 #410 |
| U6     | `/quotations`    | 4 · Acciones con efecto        | #422 #420 |
| U6     | `/quotations`    | 12 · Honestidad                | #414 |
| U6     | `/treatment-plans` | 4 · Acciones con efecto      | #417 #420 |
| U6     | `/treatment-plans` | 12 · Honestidad            | #417 #418 |
| U9     | `/professionals` | 5 · Estados completos          | #441 |
| U9     | `/professionals` | 12 · Honestidad                | #442 |
| U9     | `/appointment-types` | 12 · Honestidad            | #444 |
| U9     | `/settings/branches` | 11 · Formato de datos      | #402 |
| U10    | BI (BI-sesión)   | 5 · Estados completos          | #447 |
| U10    | BI (BI-sesión)   | 12 · Honestidad                | #447 |
| U10    | laboratorio      | 4 · Acciones con efecto        | #448 |

Un solo criterio en 0 no tiene issue propio: la honestidad del badge «En vivo»
de `/calendar`, que U3 dejó en su backlog por bajo impacto y U12 acepta por
escrito (§7.4). Los demás están publicados y con evidencia.

---

## 5. Deduplicación: una clase raíz, un issue

El cruce de los doce informes produjo **170 filas medidas** (108 de U0–U10 y 62
de U11 ruta × eje). La tabla siguiente agrupa cada fila por clase raíz y cita el
issue que la posee. El tope de 3–7
issues por sesión se respetó en U1–U11; el tope **global** de 25 §7 U12 no se
cumplió: las sesiones publicaron 70 hallazgos antes de llegar aquí. U12 no
reabre esa decisión; la registra y publica solo lo que no tenía dueño.

| Clase raíz                                                   | Dueño | Sesiones que la midieron |
| ------------------------------------------------------------ | ----- | ------------------------ |
| Canales privados que el rol no puede autorizar                | #378  | U0, U1                   |
| El `Sheet` no atrapa el foco (dos superficies)                | #379, #385 | U0, U1, U7          |
| Menú de usuario sin acción                                    | #380  | U1                       |
| Doble `h1` en el 404                                          | #381  | U1, U11                  |
| `/reset-password` sin token y campo redundante                | #382  | U1                       |
| Vacío falso antes de cargar                                   | #387  | U2, U4                   |
| Una sección caída apaga las demás                             | #388  | U2                       |
| Mensaje de error crudo del navegador                          | #389  | U2                       |
| Asistente de cierre fuera de la primitiva                     | #391  | U2, U3, U11              |
| Reprogramar sobre un horario liberado responde 500            | #392  | U3, U10                  |
| El cobro de una atención cerrada responde 409                 | #393  | U3                       |
| Cancelar sin confirmación ni motivo                           | #394  | U3                       |
| Agenda sin filtros                                            | #395  | U3                       |
| La agenda no trata sus errores                                | #396  | U3                       |
| Vista semanal cortada en móvil                                | #397  | U3                       |
| Tabs que el rol no puede leer                                 | #399  | U4, U11                  |
| Tarjetas fantasma de especialidades                           | #400  | U4                       |
| Filtros que no sobreviven y limpiar sin nombre                | #401  | U4                       |
| `UiSelect` convierte `''` en `true`                           | #402  | U4, U7, U9, U10, U11     |
| Alta de paciente sin mensaje en el campo                      | #403  | U4                       |
| Exportar visible sin permiso                                  | #404  | U4                       |
| Títulos duplicados y entidad sin encabezado                   | #405  | U4, U7, U11              |
| La caja publica la sesión de otro                             | #407  | U5                       |
| Cobros pendientes vacío por `undefined`                       | #408  | U5                       |
| El modal de cobro no comunica el 422 ni la referencia         | #409  | U5                       |
| El bloqueo contable no cubre el alta de dinero                | #410  | U5                       |
| Caja sin carga ni error representados                         | #411  | U5                       |
| Buscador de pacientes por nombre completo                     | #412  | U5                       |
| El resumen del presupuesto no coincide con lo guardado        | #414  | U6                       |
| El motivo del 422 se pierde                                   | #415  | U6, U7, U9, U11          |
| El picker filtra sólo la página cargada                       | #416  | U6, U8                   |
| Planes sin líneas en lista, detalle y edición                 | #417  | U6                       |
| Descripción «opcional» que la API exige                       | #418  | U6                       |
| Cerrar el editar sobre el detalle deja `plan = null`          | #419  | U6                       |
| Escrituras ofrecidas a roles sin permiso                      | #420  | U6, U8, U9               |
| Presupuesto creado que no puede enviarse                      | #422  | U6                       |
| Confirmación de anulación debajo del detalle                  | #423  | U7                       |
| Tabs vacías sin acción                                        | #424  | U7                       |
| Evolución vacía que se guarda                                 | #425  | U7                       |
| `alert()` y vista previa retenida en el alta de IA            | #426  | U7                       |
| «Completado» sobre un análisis fallido                        | #427  | U7                       |
| Especialidad inexistente responde 500                         | #428  | U7                       |
| Detalle sin nombre accesible (IA, continuidad)                | #429  | U7, U10                  |
| Precio de sede ausente del picker y la lista                  | #431  | U8                       |
| Recepción envía `search` y el índice lee `q`                  | #432  | U8                       |
| «Inactivos» devuelve activos                                  | #433  | U8                       |
| Duplicar pierde datos y el código propuesto responde 500      | #434  | U8                       |
| Historial y «Editar» del detalle                              | #435  | U8                       |
| Lista con `<table>` propia y paginación que miente            | #436  | U8, U11                  |
| Vigencia invertida sin mensaje                                | #437  | U8                       |
| Seis pantallas ofrecen escrituras que la API niega            | #439  | U9, U10                  |
| Rechazo del formulario fuera de su campo                      | #440  | U9                       |
| Error de carga pintado como lista vacía                       | #441  | U9                       |
| Eliminar una cuenta protegida con toast genérico              | #442  | U9                       |
| «Editar» del tipo de cita vuelve a la lista                   | #443  | U9                       |
| «Requiere materiales» que la API no guarda                    | #444  | U9                       |
| Métodos de pago sin referencia ni filtro de familia           | #445  | U9                       |
| Reportes sin estado vacío                                     | #447  | U10                      |
| Laboratorio sin alta ni edición                               | #448  | U10                      |
| Cualquier técnico edita el caso de otro                       | #449  | U10                      |
| Dos módulos de formato (moneda y fecha)                       | #451  | U11                      |
| Siete superficies sin representar la carga                    | #452  | U11                      |
| Cuatro listas con `<table>` propio                            | #453  | U11                      |
| Dos primitivas de insignia                                    | #454  | U11                      |
| Cuatro verbos para la misma alta                              | #455  | U11                      |
| Doce superficies filtran y tres usan `FilterBar`              | #456  | U11                      |
| El desglose de pago no cuadra consigo mismo                   | #459  | U12 (§7.3)               |

Dos issues son del instrumento, no del producto, y se conservan por trazabilidad:
#386 (los scripts `.mjs` no pasan por `pnpm lint:check`) y #458 (notas de la
revisión nativa de U11).

### 5.1 Duplicados y solapamientos declarados

La verificación independiente de U12 cruzó las tablas contra los issues y
encontró cinco relaciones que las sesiones no habían declarado. Se documentan
aquí; **ninguna se cierra**, porque §8.1.6 prohíbe a la sesión cerrar lo que mide.

| Relación | Qué comparten | Decisión |
| -------- | ------------- | -------- |
| #378 ↔ #384 | El shell se suscribe a canales que el rol no puede autorizar; #378 trae la matriz de 7 roles × 9 canales, #384 el recorte de U0 (171 solicitudes en allowlist) | **#378 es la clase**; #384 queda como evidencia de la primera medición y se sugiere cerrarlo cuando #378 esté resuelto |
| #420 ↔ #439 | Escrituras ofrecidas a un rol que la API responde 403 sin consultar `usePermissions` | **#420 es la clase** (U6/U8); #439 enumera seis pantallas de administración y será el detalle de la misma corrección |
| #436 ↔ #453 | Listas con `<table>` propio frente a `UiDataTable` | **#453 es la clase** (cuatro listas); #436 conserva un defecto distinto que #453 no cubre —la paginación informa 10 con 20 filas— y se corrige con ella |
| #415 ↔ #440 ↔ #437 | El rechazo de la API no llega a su campo: el motivo se pierde en el `message` genérico | **Familia con #415 como clase**; #440 aporta tres superficies de administración y #437 la vigencia del tarifario. Se corrigen con el mismo helper de error |
| #381 ↔ #405 | El shell y la página compiten por el `h1` (`pageTitledRoutes`) | **#405 es el mecanismo más amplio**; #381 queda como el caso del 404 (dos `h1`) |

Estas relaciones no cambian el veredicto por ruta —los cinco pares ya estaban
contados en sus rutas—, pero sí el orden de corrección: arreglar la clase
(#378, #420, #453, #415, #405) cierra el par menor de una vez.

---

## 6. Backlog del programa (§8.4): decisión por ítem

El §8.4 del plan dejó nueve ítems sin issue. Esta es la decisión de cada uno,
con su motivo:

| Ítem del backlog                                        | Sesión | Decisión |
| ------------------------------------------------------- | ------ | -------- |
| Rótulo del select de estado de sucursales (F07/F08)     | U9     | **Duplica #402**: la causa es `Select.vue` con valor `''`; la fila queda como evidencia del issue |
| Detalle con 403 sin `h1` (rol excluido)                 | U9     | **Aceptado** (§7.4): estado alcanzable sólo por URL, sin control que lo abra; `crit:baja` |
| Sobre del 403 sin el motivo de dominio                  | U9     | **Duplica #415**: el motivo viaja fuera de `message`, la misma clase que el 422 perdido |
| «En vivo» con las suscripciones caídas                  | U3     | **Aceptado** (§7.4): `crit:baja`, medido con la sonda que aborta las suscripciones a propósito |
| Vacío del laboratorio no observable                     | U10    | **Aceptado**: con residuo de corridas previas no se alcanza; en base limpia se mide con 4 filas. No hay defecto, hay límite del fixture |
| Densidad y repetición sin medición dedicada (U10)       | U10    | **Aceptada como límite**: la rúbrica puntúa `1` (parcial), no `0`; se declara en §8 |
| Descuento > 0 % no caminado por la interfaz (U6)        | U6     | **Aceptado como límite**: los fixtures cubren 0 %; el cálculo de IGV ya tiene su issue (#414) |
| Reprogramación por teclado no medida (U3)               | U3     | **Aceptada como límite**: la agenda es puntero-dependiente; se suma al alcance de #394/#397 |
| F1 de U0 «no publicado (decisión del dueño)»            | U0     | **Publicado en U1 como #378**; el duplicado #384 se deja abierto |

**El backlog queda vacío de ítems sin decisión.**

---

## 7. Publicación y aceptaciones

### 7.1 Recuento

| Concepto                                                                | Cantidad |
| ----------------------------------------------------------------------- | -------: |
| Issues de unidad de sesión (`type:eje`, #363–#375)                       | 13       |
| Hallazgos publicados por las sesiones                                    | 67       |
| Duplicado declarado (#384)                                               | 1        |
| Instrumento (#386, #458)                                                 | 2        |
| **Publicado por U12**                                                    | **1**    |
| **Aceptaciones escritas**                                               | **5**    |
| Issues abiertos con `auditoria-2026-10` al cierre                        | 72       |
| Unidades de sesión cerradas                                              | 12       |

La etiqueta `auditoria-2026-10` está aplicada a las 13 unidades (12 cerradas y
#375 abierta) y a los 67 hallazgos de sesión, #384, #386, #458 y #459. No hay
issue del programa sin etiqueta.

### 7.2 Reparto por criticidad (abiertos)

| Criticidad | Issues |
| ---------- | -----: |
| `crit:alta`  | 18   |
| `crit:media` | 46   |
| `crit:baja`  | 7    |

Los 18 `crit:alta` son los que justifican que el veredicto no sea «perfecto»:
dinero que se registra bajo bloqueo contable (#410), cobros que no se pueden
cobrar (#393, #408), gaveta de otro usuario publicada como propia (#407),
citas que responden 500 y deudas que no cuadran (#392, #414), planes que no se
pueden crear (#417, #418), fichas que ofrecen lo que la API niega (#399) y el
presupuesto que no puede enviarse (#422).

### 7.3 Publicado por U12

**#459** `[Auditoría/Dinero] El desglose de una cita sin ítems dice Subtotal
S/ 0,00 y Total a cobrar S/ 90,00` (`crit:baja`, `area:frontend`,
`type:defecto`, `auditoria-2026-10`). Medido en U5 (`ready-to-bill.json`): el
desglose publica `subtotal: 0` (suma de ítems del plan, y la cita es de monto
fijo) junto a `final_amount: 90`. La aritmética que el cajero lee no cuadra.

### 7.4 Aceptaciones escritas

| # | Hallazgo | Motivo |
| - | -------- | ------ |
| A1 | `dialog`: `/ai-analysis` da de alta en `UiSheet` anclado (U7, U11) | Flujo largo con pasos y vista previa; el `Sheet` es el idioma declarado para ese caso y el canon lo reserva. Su accesibilidad se sigue en #385 |
| A2 | `states`: `/login`, `/reset-password` y el 404 no representan carga | No cargan datos: no hay espera que representar. Registradas en `U11/acceptances.json` |
| A3 | `/calendar`: el badge «En vivo» no refleja el estado de la conexión (U3) | `crit:baja`; la sonda que lo mide aborta las suscripciones a propósito. #378 corrige la causa de producción (canales que el rol no puede autorizar) |
| A4 | `/appointment-types/:id`: el bloque de error de un 403 no tiene `h1` (U9) | `crit:baja`; único estado sin encabezado en 24 filas de render, alcanzable sólo por URL y sin control que lo abra. El eje de títulos tiene dueño (#381) |
| A5 | Filas de IA: «Revisar» y «Anular» abren el detalle; los KPIs son globales mientras la tabla filtra (U7) | El detalle aloja las tres acciones y el KPI global declara su población; `crit:baja`, sin pérdida de función |

Además, cuatro observaciones de sesión se cierran sin issue porque **no son
defectos**: el `h1` oculto de caja (coherente con el eje de títulos de U11), el
botón de depósito habilitado por encima del efectivo (el error es local y bloquea
el envío), el panel de error que imprime el mensaje de la API (es el motivo de
dominio, en español) y la cotización `sent` que no se borra (dueño: #422).

---

## 8. Límites de la medición

Se declaran para que una sesión futura no los lea como «sin defectos»:

1. **La rúbrica de U1 no está en disco.** La carpeta `U1/` de evidencia no
   sobrevive y su nota no incluye la tabla de 12 criterios; U11 sí mide sus 12
   ejes. `/login`, `/reset-password`, el 404 y el shell quedan sin puntuación de
   rúbrica recuperable.
2. **Criterios `NO MEDIDO`** en U10 (densidad y repetición de continuidad,
   navegación y primitivas de las tres superficies) y en U9 (algunas
   justificaciones parciales): puntúan `1`, no `0`.
3. **Caminos no recorridos**: descuento > 0 % en presupuestos, reprogramación por
   teclado, vacío del laboratorio en base limpia y el canal en vivo (fuera de
   alcance por el plan #12 A8; Reverb corre con `BROADCAST_CONNECTION=null`).
4. **Datos de la base de auditoría**: los 105 pacientes del seed no tienen sede,
   así que el filtro de sede de `/patients` se midió con 0 filas —población, no
   defecto— y el laboratorio arrastra residuo de corridas previas.
5. **La evidencia no se versiona** (`.atl/` gitignored): lo reproducible es el
   comando y el issue; las capturas viven sólo en la máquina de la sesión.
6. **Una fila es `INFERIDO` y viaja dentro de un issue medido**: U8 F10 (la
   receta de insumos no tiene superficie en `resources/js`) se verificó por
   lectura de código, no por recorrido, y se publicó dentro de #434 junto a
   filas `MEDIDO` de la misma clase. El issue lo declara como «no observable»;
   el lector no debe contarlo como medición de navegador.

---

## 9. Qué se decidió no hacer

- **No se corrige ningún defecto.** El programa mide y publica; cada issue trae
  su alcance y sus criterios de aceptación (§9 del plan).
- **No se cierran issues de hallazgos**, ni siquiera el duplicado #384: la regla
  §8.1.6 lo prohíbe a la sesión que los mide y U12 no es una sesión de
  corrección.
- **No se puntúa de nuevo la rúbrica** ni se re-mide producto: se consolidan los
  artefactos de U0–U11.
- **No se publica el backlog de bajo impacto**: se acepta con motivo escrito
  (§7.4), que es la otra salida que el plan permite.
- **No se amplía el alcance** a los ejes del plan #12 (A1–A11) ni a la entrega
  de exports, retención de datos o i18n.

---

## 10. Conclusión

El programa entrega lo que prometió: **36 superficies auditadas**, **12 ejes
transversales con veredicto**, **una rúbrica de 29 superficies** y **68 defectos
con evidencia**, agrupados por clase raíz y sin duplicados sin declarar. La
respuesta a la pregunta del plan es **«hay que arreglar»**: 18 defectos de
criticidad alta tocan dinero, agenda, planes y permisos, y las 29 superficies
puntuadas están por debajo del canon (la mejor, 23/24). Dos superficies —`/login` y
`/settings/accounting-locks`— no tienen ningún defecto abierto y su evidencia
está arriba.

El siguiente paso no es otra auditoría: es tomar los issues `crit:alta` por
orden de impacto (#410, #407, #408, #393, #392, #414, #417, #418, #422) y
corregirlos con el mismo protocolo de evidencia con el que se midieron.

## 11. Changelog

- **2026-10-27** — Creación. Cierre del plan #13 (issue #375). Cruce de los doce
  informes, dedupe contra 71 issues abiertos, una publicación (#459), cinco
  aceptaciones escritas, veredicto de las 36 rutas y cierre del backlog §8.4.
  Base `da9db08`. Sin cambios de producto.
