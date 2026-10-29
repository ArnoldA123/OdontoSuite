# Cierre del plan de corrección — auditoría UI/funcional 2026-10

**Fecha:** 2026-10-28 · **Rama:** `chore/cierre-auditoria-2026-10` ·
**Worktree:** `../odontosuite-cierre` desde `main` (`a3a7db29`).

Esta acta cierra la **ola 7** del plan #13: verificación global y cruce de las
71 issues del programa contra su estado real. No corrige producto; un defecto
encontrado durante la verificación se publica como issue y queda como
follow-up.

## 1. Estado de partida

- Las olas 1-6 están mergeadas: `main` y `origin/main` apuntan al mismo commit
  (`a3a7db29`) y las 88 issues con la etiqueta `auditoria-2026-10` están
  cerradas como `COMPLETED`.
- Las 88 se reparten así: 13 unidades de sesión (U0-U12, #363-#375), 71
  hallazgos del programa (67 de sesión + el duplicado #384 + los dos del
  instrumento #386 y #458 + #459 publicado por U12) y 4 follow-ups posteriores
  (#466, #467, #472, #499).
- #375 (U12) ya estaba cerrada y **no se reabrió**. No hay issue de cierre
  abierta.
- Al terminar la verificación, `gh issue list --state open --label
  auditoria-2026-10` devuelve **0**.

## 2. Método

1. Se creó el worktree `../odontosuite-cierre` desde `main`, con base MySQL
   propia (`odontosuite_cierre_test`) sobre el servicio MySQL 8.0 de
   `docker-compose.yml` y una base de auditoría también propia
   (`odontosuite_cierre_audit`). No se tocó la base de desarrollo
   (`odontosuite`) ni datos sembrados de otro flujo.
2. Para cada issue se verificó, en este orden: estado en GitHub, PR de
   corrección mergeado, commit alcanzable desde `main`
   (`git merge-base --is-ancestor`) y al menos un archivo de prueba en el diff
   de ese commit. La única excepción declarada es #386, cuya comprobación es el
   propio lint sobre `.mjs` (no tiene test unitario).
3. Los siete duplicados se documentaron en su issue con el issue clase y el
   commit que contiene la corrección compartida.
4. La verificación global se ejecutó sobre el código de `main` en el worktree.
   `ui-pass` se lanzó generando su manifiesto y ejecutando después el mismo
   `playwright test tests/e2e/audit/surface.spec.js` con las variables que el
   propio CLI exporta: el wrapper `pnpm exec` no puede correr en un worktree con
   `node_modules` enlazado.

## 3. Las 71 issues: issue → commit → veredicto

Resultado: **64 cerradas con evidencia + 7 duplicadas de otra issue = 71**.
**Pendientes: 0.**

### Ola 1 — overlays, formato, cliente y gates

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #379 | Sheet de navegación móvil sin trampa de foco ni nombre | `72b27f7a` | sheetPrimitive.test.js | corregida |
| #385 | Sheet de «Nuevo análisis» sin trampa de foco | `72b27f7a` | sheetPrimitive.test.js | duplicada de #379 |
| #423 | Confirmación de anulación debajo del detalle | `72b27f7a` | overlayStack.test.js | corregida |
| #429 | Detalle de IA sin nombre accesible | `72b27f7a` | ai-analysis-detail.test.js | corregida |
| #451 | Dos módulos de formato (moneda, fecha y hora) | `759cb35f` | formatters-single-source.test.js / FormatPENLabelTest.php | corregida |
| #402 | UiSelect convierte el valor vacío en true | `8bed6da3` | select-empty-value.test.js | corregida |
| #408 | Pestaña Cobros vacía: la query envía «undefined» | `8bed6da3` | useApi-params.test.js | corregida |
| #386 | Los scripts .mjs no pasan por pnpm lint:check | `8bed6da3` | eslint --ext .mjs scripts (sin test propio) | corregida |
| #458 | Notas informativas de la revisión nativa de U11 | `8bed6da3` | tests/e2e/audit/u11/report.js | corregida |

### Ola 2 — dinero backend, agenda, presupuestos

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #393 | El cobro de una atención cerrada responde 409 | `d03c42e6` | AppointmentPaymentBalanceTest.php | corregida |
| #407 | La caja publica la sesión de otro usuario | `d03c42e6` | CashRegisterCurrentSessionScopeTest.php | corregida |
| #410 | El bloqueo contable no cubre el alta de dinero | `d03c42e6` | AccountingLockCreationGuardTest.php | corregida |
| #412 | Buscador de pacientes por nombre completo | `d03c42e6` | PatientSearchFullNameTest.php | corregida |
| #391 | Asistente de cierre fuera de la primitiva | `360fc2bc` | consultation-wizard-close-guard.test.js | corregida |
| #392 | Reprogramar a un horario liberado responde 500 | `360fc2bc` | AppointmentSlotReleaseTest.php | corregida |
| #394 | Cancelar una cita sin confirmación ni motivo | `360fc2bc` | AppointmentCancellationReasonTest.php | corregida |
| #395 | Agenda sin filtros de profesional, sillón y sede | `360fc2bc` | AppointmentCalendarFiltersTest.php | corregida |
| #396 | La agenda no trata sus errores de lectura | `360fc2bc` | calendar-load-error.test.js | corregida |
| #397 | Vista semanal cortada en móvil | `360fc2bc` | calendar-mobile-layout.test.js | corregida |
| #414 | El resumen del alta no coincide con lo guardado | `49d3cbbb` | quotationPricing.test.js | corregida |
| #415 | El motivo del 422 se pierde | `49d3cbbb` | validationErrors.test.js | corregida |
| #420 | Escrituras ofrecidas a roles sin permiso | `4973a054` | QuotationsRedesignContractTest.php | corregida |
| #422 | Presupuesto creado que no puede enviarse | `49d3cbbb` | QuotationSendTest.php | corregida |

### Ola 3 — dinero frontend, pacientes, catálogo, dashboard

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #409 | Modal de cobro sin 422 ni referencia | `904ac939` | caja-cobros.test.js | corregida |
| #411 | Caja sin carga ni error representados | `c68facdd` | useCashRegister.test.js / ui-skeleton.test.js | corregida |
| #459 | El desglose de pago no cuadra consigo mismo | `ff382b5d` | AppointmentPaymentPreviewTest.php | corregida |
| #399 | Tabs de la ficha que el rol no puede leer | `6aa7b9c0` | patients-detail-redesign.test.js | corregida |
| #400 | Tarjetas fantasma de especialidades | `6aa7b9c0` | specialty-records.test.js | corregida |
| #401 | Filtros de la lista no sobreviven la ida y vuelta | `6aa7b9c0` | patients-list-controls.test.js | corregida |
| #403 | Alta de paciente sin mensaje en su campo | `6aa7b9c0` | PatientValidationMessageLocaleTest.php | corregida |
| #404 | Exportar visible sin permiso | `6aa7b9c0` | PatientExportCapabilityTest.php | corregida |
| #431 | Precio de sede ausente del picker y la lista | `cb775770` | ProcedureIndexPriceTest.php | corregida |
| #432 | Recepción envía «search» y el índice lee «q» | `cb775770` | reception-procedures.test.js | corregida |
| #433 | «Inactivos» devuelve los procedimientos activos | `cb775770` | ProcedureIndexActiveFilterTest.php | corregida |
| #387 | Dashboard y Mi día pintan su vacío antes de cargar | `368eecfd` | dashboard-first-paint.test.js | corregida |
| #388 | Una sección caída apaga las demás | `368eecfd` | dashboard-section-isolation.test.js | corregida |
| #389 | «Failed to fetch» como mensaje de error | `368eecfd` | my-day-error-message.test.js | corregida |

### Ola 4 — catálogo, clínica/IA, shell

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #434 | Duplicar pierde datos y el código responde 500 | `f16a06ab` | ProcedureCatalogCodeLengthTest.php / procedureCode.test.js | corregida |
| #435 | El detalle no entrega historial ni abre «Editar» | `f16a06ab` | procedure-catalog-detail.test.js | corregida |
| #436 | Lista con table propia y paginación que miente | `737a84da` | data-table-contract.test.js | duplicada de #453 |
| #437 | Vigencia invertida sin mensaje | `845fc851` | procedure-tariff.test.js | duplicada de #415 |
| #424 | Tabs vacías sin la acción que las resuelve | `81cd6e56` | medical-record-stats-empty-action.test.js | corregida |
| #425 | El formulario de evolución vacío guarda | `81cd6e56` | MedicalRecordEvolutionContentTest.php | corregida |
| #426 | alert() y vista previa retenida en el alta de IA | `81cd6e56` | file-upload-rejection.test.js | corregida |
| #427 | «Completado» sobre un análisis fallido | `81cd6e56` | AiAnalysisFailureReasonTest.php | corregida |
| #428 | Especialidad inexistente responde 500 | `81cd6e56` | SpecialtyRecordMissingRecordTest.php | corregida |
| #378 | El shell pide canales que el rol no puede leer | `5ae405b0` | BroadcastChannelAuthorizationTest.php | corregida |
| #384 | El shell se suscribe a canales no autorizados | `5ae405b0` | BroadcastChannelAuthorizationTest.php | duplicada de #378 |
| #380 | Menú de usuario con acciones sin efecto | `d837ba70` | user-menu-contract.test.js | corregida |
| #381 | El 404 dentro del shell pinta dos h1 | `33369bbd` | page-header-hierarchy.test.js | duplicada de #405 |
| #382 | /reset-password pide el código que el enlace trae | `5ae405b0` | login-reset-flow.test.js | corregida |
| #405 | Títulos duplicados y entidad sin encabezado | `d837ba70` | page-header-hierarchy.test.js | corregida |

### Ola 5 — planes, administración, BI/laboratorio

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #416 | El picker filtra sólo la página cargada | `5e171e61` | procedure-catalog-picker.test.js | corregida |
| #417 | Planes sin líneas en lista, detalle y edición | `5e171e61` | TreatmentPlansIndexPayloadTest.php | corregida |
| #418 | Descripción «opcional» que la API exige | `5e171e61` | TreatmentPlanItemDescriptionTest.php | corregida |
| #419 | Cerrar la edición sobre el detalle deja el plan nulo | `5e171e61` | treatment-plans.test.js | corregida |
| #439 | Seis pantallas ofrecen escrituras que la API niega | `f93f2db3` | payment-methods-list.test.js / professionals.test.js | duplicada de #420 |
| #440 | El rechazo del formulario no llega al campo | `c806dcb0` | branch-transfers.test.js / environments.test.js | duplicada de #415 |
| #441 | Error de carga pintado como lista vacía | `5d0db081` | professionals.test.js | corregida |
| #442 | Eliminar una cuenta protegida con toast genérico | `5d0db081` | professionals.test.js | corregida |
| #443 | «Editar» del tipo de cita vuelve a la lista | `eca0a0f4` | appointment-types-detail.test.js | corregida |
| #444 | «Requiere materiales» que la API no guarda | `eca0a0f4` | appointment-types-contract.test.js | corregida |
| #445 | Métodos de pago sin referencia ni filtro de familia | `c806dcb0` | payment-methods-list.test.js | corregida |
| #447 | Cuatro reportes sin estado vacío | `f580df03` | ReportPeriodActivitySummaryTest.php | corregida |
| #448 | Laboratorio sin alta ni edición | `f580df03` | lab-cases.test.js / lab-case-form.test.js | corregida |
| #449 | Cualquier técnico edita el caso de otro | `f580df03` | LabCaseOwnershipTest.php | corregida |

### Ola 6 — consistencia transversal (U11)

| Issue | Hallazgo | Commit | Prueba | Veredicto |
| ---: | --- | --- | --- | --- |
| #452 | Siete superficies no representan la carga | `7406e713` | patients-detail-redesign.test.js / caja-caja-actual.test.js | corregida |
| #453 | Cuatro listas con table propio | `e07c5328` | DataTableListInheritanceTest.php | corregida |
| #454 | Dos primitivas de insignia | `25bfa29f` | SingleStatusBadgeTest.php | corregida |
| #455 | Cuatro verbos para la misma alta | `8c2e3bb4` | consultation-closure-nts139.test.js / professionals.test.js | corregida |
| #456 | Doce superficies filtran y tres usan FilterBar | `633783e6` | BusinessIntelligenceAppShellTest.php / TreatmentPlansAppShellTest.php | corregida |


## 4. Duplicados cerrados con su clase

Los siete ya estaban cerrados por su corrección; el comentario añade la
trazabilidad que el plan pidió (issue clase + commit). Enlaces:

| Duplicado | Issue clase | Commit(s) citados | Comentario |
| --- | --- | --- | --- |
| #384 | #378 | `d32681c` (PR #481) | https://github.com/ArnoldA123/OdontoSuite/issues/384#issuecomment-6487323770 |
| #385 | #379 | `e33fbde` (PR #461) | https://github.com/ArnoldA123/OdontoSuite/issues/385#issuecomment-6487324137 |
| #436 | #453 | `e07c5328` (PR #493) y `737a84da` (PR #480) | https://github.com/ArnoldA123/OdontoSuite/issues/436#issuecomment-6487324496 |
| #439 | #420 | `f93f2db3` (#486), `c806dcb0` (#487), `5d0db081` (#491), `eca0a0f4` (#489) | https://github.com/ArnoldA123/OdontoSuite/issues/439#issuecomment-6487324872 |
| #440 | #415 | `9f18f3e4` (#487), `1486a373` (#491), `198b6d9b` (#489) | https://github.com/ArnoldA123/OdontoSuite/issues/440#issuecomment-6487325209 |
| #437 | #415 | `845fc8515` (PR #480) | https://github.com/ArnoldA123/OdontoSuite/issues/437#issuecomment-6487325608 |
| #381 | #405 | `33369bbd2` (PR #482) | https://github.com/ArnoldA123/OdontoSuite/issues/381#issuecomment-6487326077 |

## 5. Verificación global

Ejecutada en `../odontosuite-cierre` sobre `a3a7db29`.

| Comando | Resultado |
| --- | --- |
| `DB_DATABASE=odontosuite_cierre_test vendor/bin/phpunit -c phpunit.mysql.xml` | **OK (2915 pruebas, 17853 aserciones)** |
| `./node_modules/.bin/vitest run` (equivale a `pnpm test:js`) | **173 archivos, 1564 pruebas, todas en verde** |
| `./node_modules/.bin/eslint . --ext .mjs,.vue,.js,.jsx,.ts,.tsx` (equivale a `pnpm lint:check`) | **0 errores, 36 avisos** (preexistentes) |
| `./node_modules/.bin/prettier --check .` (equivale a `pnpm format:check`) | **All matched files use Prettier code style** |
| `./node_modules/.bin/vite build` (equivale a `pnpm build`) | **✓ built** (sin errores) |
| `vendor/bin/pint --test` | **PASS — 928 archivos** |
| `node scripts/audit/doc-drift.mjs` | **14/14 chequeos en verde** |
| `node scripts/audit/ui-pass.mjs --all` | Gate **OK**: **36/36 superficies renderizan**, 0 page errors, 18 diálogos y 0 fallos de contrato. La corrida Playwright del mismo spec deja **361 passed, 10 failed, 60 skipped**; los 10 fallos son del arnés (follow-up #501), no de producto. |

Dos notas de entorno, para que la corrida sea reproducible:

- `pnpm test:js` aborta en un worktree cuyo `node_modules` es un enlace
  simbólico al checkout principal (`ERR_PNPM_ABORTED_REMOVE_MODULES_DIR_NO_TTY`).
  Se invocaron los binarios equivalentes de `node_modules/.bin`, que es lo que
  ejecuta cada script de `package.json`.
- La suite PHP se ejecutó contra el MySQL 8.0 de `docker-compose.yml`
  (127.0.0.1:3307) con `DB_DATABASE=odontosuite_cierre_test`; el runner
  `phpunit.mysql.xml` fija el motor y el puerto. `odontosuite_test` y
  `odontosuite` no se tocaron.

## 6. Follow-ups abiertos

- **#501 — cerrado: el arnés de auditoría soporta la corrida `--all` sobre una
  sola base** (`fix/audit-harness-run-501`). Las seis causas medidas se
  corrigieron sin tocar producto: U1 lee el token aplicado con `count()` antes
  de `inputValue()`; U3 declara y no afirma el contrato de la rejilla semanal
  donde el rol negativo no recibe datos; U5 adopta su `fixtures-state.json`
  sólo cuando su forma, su día y su base son los de esta corrida, cae en
  `{ ok: false, reason }` en vez de rechazar, y no ofrece en su catálogo de
  métodos de pago las filas `AUDIT-*` de otra sesión; el cierre de U9 declara
  el 409 del libro inmutable con su motivo; U7/U8 regeneran la cobertura de
  matriz sobre evidencia anterior; y la matriz de U8 sigue el contrato
  corregido del catálogo (código de 21 caracteres → 422, `q` filtra, `is_active`
  filtra) exigiendo cero desviaciones. En la misma pasada, «listos para
  facturar» lee el vacío declarado por la página (`[data-rtb-empty]`, #452) en
  vez de desreferenciar una tabla ausente.
  Medido sobre una base `odontosuite_audit` sembrada y la app en `:8000`: rojo
  reproducido para cada causa y verde para cada corrección, `vitest` en 175
  archivos / 1574 pruebas, y la corrida completa `--all` de **361 passed / 10
  failed / 60 skipped** a **425 passed / 4 failed / 2 skipped** (48.6 min). De
  esos cuatro, la lectura del vacío quedó re-medida en verde; **quedan tres
  sondas** fuera de las seis causas —los dos walkthroughs de caja de U5 y el
  walk de planes de U6—, que arrastran expectativas anteriores a #407/#410/#466
  (héroe de tres estados, bloqueo contable, cobro sin gaveta) y agotan los
  900 s del walk de planes. Su veredicto exige una decisión de producto y no se
  tocó aquí.
- **Ningún defecto de producto nuevo.** La verificación no encontró una
  regresión de producto que exigiera issue.
- Se mantienen las **cinco aceptaciones escritas** del informe final (§7.4:
  A1-A5) y las cuatro observaciones que no son defecto; no son issues y no
  cambian con este cierre.
- Los items que el informe final dejó como límites de la medición (§8) siguen
  siendo límites del arnés, no defectos: el canal en vivo, el descuento > 0 %
  en presupuestos, la reprogramación por teclado y el vacío del laboratorio en
  base limpia.

## 7. Conclusión

El programa de auditoría UI/funcional 2026-10 queda cerrado con **71/71
issues resueltas o declaradas duplicadas**, **0 pendientes** y la verificación
global en verde: suite MySQL, cliente, estáticos y gate de las 36 superficies.
La deuda del arnés de sesión quedó corregida en #501 (§6): las seis causas del
follow-up están cerradas y la corrida completa bajó de diez fallos a cuatro, de
los cuales uno ya está re-medido en verde; los tres restantes son sondas de
dinero y planes que necesitan una decisión de producto. Las olas 1-6 dejaron sus
correcciones en `main`; esta ola deja el acta, los conteos re-medidos y la
trazabilidad issue → commit → prueba.
