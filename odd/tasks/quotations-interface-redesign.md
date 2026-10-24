# Quotations Interface Redesign

Rama: `feat/quotations-interface-redesign` (desde `main` @ b3c168a)
Worktree: `.worktrees/quotations-ui` · Creado: 2026-10-20 · Orquestador: el Gentleman

## Specs

- **S1.** Continúa el rediseño de interfaces iniciado en el dashboard: la página
  `/quotations` pasa al mismo lenguaje visual (hero band, hairlines, elevación,
  KPI strip, primitivas de `components/ui/`, heroicons), como profesionales,
  ambientes, tipos de cita, métodos de pago y BI.
- **S2.** El trabajo se aísla en un worktree y en la rama
  `feat/quotations-interface-redesign` partiendo de `main` @ b3c168a.
- **S3.** Convenciones de `AGENTS.md` §7: "UI: consumir SIEMPRE de
  `components/ui/`. NO botones/modales ad-hoc."; "NO comentarios pedagógicos";
  pnpm; código e identificadores en inglés, copy de UI en español; commits
  semánticos en inglés sin emojis.
- **S4.** La interfaz sólo muestra lo que el contrato del backend sostiene: sin
  campos muertos, sin acciones que respondan 422, sin ceros inventados (lección
  `dashboard-visual-coherence` / `payment-methods-interface-redesign`).

## Auditoría base (2026-10-20, evidencia path:line)

### Frontend

- **F1** `QuotationsPage.vue:5-18` usa `PageHeader`, que duplica el título del
  topbar; el lenguaje aprobado es la hero band (`PaymentMethodsPage.vue:2-22`,
  `AppointmentTypesPage.vue:2-39`).
- **F2** `QuotationsPage.vue:71-87` lista tarjetas a mano; `components/ui/DataTable.vue`
  es la primitiva de los módulos rediseñados.
- **F3** `QuotationsPage.vue:25,168` envía `patient_name`; `QuotationController.php:31-34`
  acepta sólo `patient_id,status,created_by,date_from,date_to,expired,branch_id`
  → el filtro de paciente es un no-op.
- **F4** `QuotationsPage.vue:216-224` traga los errores (`error` nunca se
  renderiza); carga con spinner (`:63`) en vez de skeleton; estado vacío ad-hoc
  (`:67-79`).
- **F5** La lista es paginada (`QuotationService.php:319`, `paginate(15)`): los
  contadores calculados sobre la página mentirían; el KPI strip necesita un
  agregado del servidor.
- **F6** `QuotationCard.vue:186-188` emite el presupuesto completo y
  `QuotationsPage.vue:96-101,111` lo pasa como `id` a `approveQuotation` /
  `rejectQuotation` / `downloadPDF` → `POST /api/quotations/[object Object]/…`.
- **F7** `QuotationDetail.vue:167-175` llama `can('quotations.update')` como
  función, pero `usePermissions.js:9` exporta `can` como objeto → TypeError al
  renderizar; además los botones de detalle llaman las acciones sin id
  (`:120-137`) y los `emits` declarados (`:160`) no son los que escucha la
  página (`QuotationsPage.vue:111-113`).
- **F8** `QuotationDetail.vue:2-15` no es un diálogo: se renderiza inline bajo la
  lista, sin overlay ni focus trap.
- **F9** Campos muertos: `quotation.title` (`QuotationDetail.vue:55`),
  `quotation.description` (`:59`), `item.description` (`:74`),
  `item.category` (`:75`); `QuotationItem` expone `item_name`,
  `item_description`, `specialty` (`QuotationItem.php:11-22`).
- **F10** `QuotationModal.vue:343` guarda `items` del API (`item_name` /
  `item_description`) en el formulario y luego ejecuta `item.description.trim()`
  → TypeError al editar y guardar.
- **F11** `QuotationModal.vue:2` overlay a mano; inputs nativos (`:40-160`);
  envía `tax_amount` y `total_amount`, que `store`/`update` nunca aceptan
  (`QuotationController.php:78-96,126-146`); en edición envía `patient_id` y
  `treatment_plan_id`, ausentes de las reglas de update → descartados.
- **F12** `QuotationApprovalModal.vue:112-115` envía `comments`, `approved_at`,
  `approved_by`, `conditions`; el backend sólo valida `notes` y
  `patient_signature` (`QuotationController.php:204-207`) → nada se guarda;
  `approved_by = 'Usuario Actual'` (`:137`) inventa una identidad.
- **F13** `QuotationCard.vue` y `QuotationDetail.vue` duplican el mapa de
  estados y las reglas de rol, y `QuotationCard.vue:150-184` no coincide con el
  middleware real (`routes/api.php:249`): niega editar a `implantologo` y
  aprobar a clínicos, ambos permitidos por la API.
- **F14** `QuotationStatusBadge.vue:19-33` mapea 4 estados y el JSON de la DB es
  `draft|sent|viewed|approved|rejected|expired`
  (`database/migrations/2025_10_24_202256_create_quotations_table.php:28`):
  `viewed` y `expired` se muestran crudos.
- **F15** Los 5 archivos del módulo tienen `<style scoped>` con `border-theme`,
  `bg-danger-badge` y `btn btn-*`; el guard `ModuleAppShellTestCase` sólo cubre
  `QuotationsPage.vue` (`CajaPagesAppShellTest.php:30`).
- **F16** `useQuotations.js:205-219` pide `{responseType:'blob'}` a
  `useApi.get`, que ignora las opciones (`useApi.js:81-98`) → el PDF nunca
  descarga.

### Backend

- **B1** No existe búsqueda por texto: `index` no acepta `q`
  (`QuotationController.php:31-34`).
- **B2** No existe agregado por estado: el KPI strip necesita
  `GET /api/quotations/summary`.
- **B3** `QuotationService::approveQuotation` (`:176-209`) crea
  `QuotationApproval` con `approved_by`, `approval_notes`, `patient_signature`:
  columnas inexistentes (`create_quotation_approvals_table.php:18-24`) y sin
  `patient_id`, que es NOT NULL → el insert falla y la aprobación responde 500.
- **B4** `quotations.treatment_plan_id` es NOT NULL
  (`create_quotations_table.php:16`) y ninguna migración posterior lo relaja,
  pero `StoreQuotationRequest` lo declara opcional y la UI crea presupuestos sin
  plan → 500 en el CTA principal.
- **B5** `approve`/`reject` no tienen guarda de transición: se puede aprobar un
  `draft` o re-aprobar un `approved` (`QuotationService.php:180-190,218-223`).
- **B6** Los 3 eventos emiten el modelo completo por `Channel('quotations')`
  público (`QuotationCreated.php:26-31`). Es el mismo hallazgo cross-cutting ya
  registrado para `dashboard-updates`; **fuera de alcance** (se reporta).

### Consumidores (no se tocan)

- `BillingService.php:141-205` (genera `sent` desde una cita) →
  `ReadyToBillPage.vue`; `ConsultationService.php:119-122`;
  `DashboardController.php:309-390` pendientes (`sent`+`viewed`);
  `TreatmentPlanDetail.vue:154-168` y `PatientDetailPage.vue:295-371` consumen
  `QuotationStatusBadge` y `GET /api/quotations?patient_id=`.

## Decisiones de diseño

| #   | Decisión                                                                                                                                                      | Motivo                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| D1  | Hero band `data-quotations-hero` (eyebrow "Operación", título "Presupuestos", caption, Volver + Nuevo presupuesto); sin `PageHeader`                            | Último lenguaje aprobado; mata el título duplicado (F1)         |
| D2  | KPI strip de 4 tarjetas desde `GET /api/quotations/summary`: Total, Pendientes (`sent`+`viewed`), Aprobados, Monto aprobado; "N/D" al cargar o fallar         | F5 / B2: el dato es del servidor, nunca de la página paginada    |
| D3  | Lista sobre `UiDataTable`: Nº + Emitido, Paciente, Válido hasta (+ vencimiento), Total, Estado, Acciones                                                       | Convención de primitivas (F2)                                    |
| D4  | Filtros: `q` server-side con debounce (número, paciente, documento) + `UiSelect` de estado (6 estados) + rango de fechas + Limpiar                             | F3 / F14 / B1                                                    |
| D5  | Detalle en `UiModal` (`size="xl"`) con paciente, ítems reales, totales, condiciones y notas; acciones en el footer                                             | F8, F9                                                          |
| D6  | `quotationMeta.js` como única fuente de verdad: estados, vencimiento, moneda, ítems y matriz de permisos alineada al middleware                                | F13, F14; una sola fuente para página, tarjeta, detalle y modales |
| D7  | Matriz de permisos: ver = 5 roles de lectura; crear/editar/aprobar/rechazar = `administrador,finanzas,odontologo,implantologo`; eliminar = sólo `draft`; PDF = lectura | Contrato real (`routes/api.php:243-254`, `QuotationController.php:166-192`) |
| D8  | Aprobar abre modal con nota opcional (`notes`) y resumen; rechazar exige `reason` (máx. 500) en `UiTextarea`; ambos usan `useConfirm` sólo para eliminar     | F12 / F6: el contrato exige `reason` y acepta `notes`            |
| D9  | Crear/editar en `UiModal` con secciones y primitivas (`PatientSelector`, `TreatmentPlanSelector`, `UiInput`, `UiTextarea`, `RichTextEditor`); en edición paciente y plan quedan fijos con hint | F10/F11; el backend no permite cambiarlos en `update`           |
| D10 | El formulario calcula y muestra totales; el servidor sigue siendo la autoridad (`calculateAmounts`) y no se envían `tax_amount`/`total_amount`                 | F11: contrato real                                              |
| D11 | Backend acotado: `q` en `index`, `summary`, `treatment_plan_id` nullable, `approve` escribiendo columnas reales                                               | B1-B4: sin cambiar contratos de otros consumidores               |
| D12 | Estados: skeleton, error + retry (`role="alert"`), vacío por datos y por filtros; sin spinner suelto                                                          | F4 / anti-patrones                                              |
| D13 | `useQuotations.downloadPDF` usa `fetch` con bearer (patrón de `CashReports.vue:397-430`)                                                                       | F16: `useApi.get` no soporta blob                               |
| D14 | Se conserva `quotationsChannel = channel('quotations')` byte-for-byte                                                                                          | PAGOS-RT-001 y privacidad del canal = follow-up                 |
| D15 | `tests/e2e/smoke.spec.js` cambia el locator a la hero band                                                                                                    | F1: `.page-title` deja de existir en `/quotations`              |

## Contratos de componentes (para delegación)

| Componente | Props | Emits |
| --- | --- | --- |
| `QuotationDetail.vue` | `quotation: Object` (payload de `show`), `open: Boolean` | `close`, `edit`, `approve`, `reject`, `download` (todos con la quotation) |
| `QuotationModal.vue` | `quotation: Object|null`, `isEdit: Boolean`, `open: Boolean` | `close`, `saved(quotation)` |
| `QuotationApprovalModal.vue` | `quotation: Object`, `open: Boolean` | `close`, `approved(quotation)` |
| `QuotationRejectModal.vue` (nuevo) | `quotation: Object`, `open: Boolean` | `close`, `rejected(quotation)` |

Reglas transversales: `<UiModal>` como contenedor (nunca overlay propio);
`UiButton`, `UiStatusBadge`, `UiTextarea`, `UiInput`, `UiEmptyState`; cero
`<style scoped>`; cero `border-theme`/`btn btn-*`/`focus:ring-primary-500`;
copy en español; identificadores en inglés; `data-*` estables para los guards.

## Alcance / No-alcance

**Alcance**: `resources/js/modules/quotations/**`,
`resources/js/composables/useQuotations.js` (descarga PDF y estado de error),
`QuotationController` + `QuotationService` + `QuotationApproval` (fix B3),
migración `treatment_plan_id` nullable (B4), una ruta `GET quotations/summary` (B2),
`tests/js/**`, `tests/Unit/DesignSystem/**`, `tests/Feature/Api/**`,
`tests/e2e/smoke.spec.js` (locator) y `AGENTS.md` §4/§12.

**No-alcance**: privacidad de los canales de broadcast (B6), guardas de
transición de estado (B5), integración del tab de presupuestos en pacientes,
ruta `/quotations/{id}` de `PatientDetailPage.vue:987`, plantilla PDF, y
cualquier cambio en citas/caja/planes.

## Tasks

| # | Task | Spec | Commit |
|---|---|---|---|
| T1 | Backend: `q` + `summary` + `treatment_plan_id` nullable + `approve` real; tests RED→GREEN | S4, D11, B1-B4 | ✅ 88a7f93 |
| T2 | `quotationMeta.js` + tests unitarios | S4, D6, D7 | ✅ 3f5d59a |
| T4 | Detalle en `UiModal` con datos reales | S1, S4, D5, F9 | ✅ 75f5dee |
| T5 | Modal crear/editar sobre primitivas y payload real | S1, S4, D9, D10 | ✅ bdff561 |
| T6 | Aprobar + rechazar (nota / motivo) | S4, D8 | ✅ 16d6ee3 |
| T3 | Página: hero + KPI + filtros + `UiDataTable` + estados + acciones | S1, D1-D4, D12 | ✅ c9a6114 |
| T7 | Guards, docs, verificación completa y pasada visual | S1-S4 | ✅ 0c3693f |

## Verificación

- T1: RED 6/7 (sólo el contrato preexistente quedó verde) → GREEN 7/7 con
  `QuotationIndexSummaryTest` contra la DB aislada `odontosuite_quot_test`;
  regresión focuseada `Quotation|RecepcionistaCajaAccess|DashboardPending|FormRequestFields|PatientsModuleContract` 330/330.
  La consola de MySQL confirmó los dos fallos reales del contrato: `Field
  'treatment_plan_id' doesn't have a default value` y la escritura de
  `quotation_approvals` con `patient_id` nulo.
- T2: `pnpm test:js -- tests/js/unit/quotationMeta.test.js`.
- T3-T6: `pnpm test:js` focuseado + smoke del módulo.
- T7: suites completas JS y PHP (MySQL), `pnpm lint:check`, `pnpm build`,
  `scripts/audit/doc-drift.mjs`, Playwright a 1440×900 / 834×1112 / 390×844.
- Cierre: ASSESS nativo y plan resultante (RDD global `on`).

## Cierre (2026-10-20)

- **Commits**: 6 en `feat/quotations-interface-redesign` (`88a7f93` T1,
  `3f5d59a` T2, `75f5dee` T4 + retiro de `QuotationCard`, `bdff561` T5,
  `16d6ee3` T6, `c9a6114` T3, `0c3693f` T7). 4.474 líneas sobre 20 paths.
- **Verificación final**: PHP MySQL 1488/1488 (tras corregir el guard de
  migraciones: `->change()` condicionado a `DB::getDriverName() === 'mysql'`);
  PHP Unit 1082 tests con 39 errores preexistentes de SQLite (AGENTS.md §6) y 0
  fallos nuevos; JS 559/559 (68 archivos previos + 2 nuevos smoke + unit);
  ESLint 0 errores; Prettier en verde; `pnpm build` ✓;
  `scripts/audit/doc-drift.mjs` 12/12.
- **Pasada visual** (`:8001`, DB aislada sembrada, 1440×900 / 834×1112 /
  390×844): KPI 4/2/1/S/ 1.200,00 desde el agregado; 4 filas con los estados
  Borrador/Enviado/Visto/Aprobado (sin enum crudo); badges de vencimiento sólo
  en pendientes ("Vencido", "Vence en 3 días"); acciones 4 Ver / 1 Editar /
  2 Aprobar / 1 Eliminar, coherentes con el contrato; detalle con 6-7 secciones
  y nombre real del procedimiento; aprobación con nota y sin "Usuario Actual";
  formulario con 4 secciones y resumen; edición bloquea paciente y plan con
  hint; sin overflow horizontal en los 3 viewports. Capturas en
  `/tmp/quotations-visual/`.
- **Defecto visual corregido** durante la pasada: la fila de filtros desbordaba
  la tarjeta y recortaba el campo "Hasta" (y el texto `mm/dd/yyyy` quedaba
  cortado en desktop). Se reemplazó la grilla por una fila flexible
  (búsqueda + estado + par de fechas con ancho propio) y se midió
  `scrollWidth <= clientWidth` en los 3 viewports.
- **Entrega** (2026-10-20): PR #207 mergeado con merge commit `587b540` en
  `main`; 9 commits, CI verde en los dos runs (Quality gates, Backend tests
  MySQL, Frontend build). Rebaseado sobre `5351e44` (merge de caja); el único
  conflicto fue `AGENTS.md` §12 y los conteos se re-midieron a 200 rutas API /
  213 con web.
- **Follow-ups issues**: #200 canales públicos, #201 transiciones y estados sin
  escritor, #202 rechazo sin evento, #203 superficies de presupuesto en el
  detalle de paciente, #204 saneo divergente en `update`, #205
  `UiEmptyState.icon`, #206 falso rojo de `ReminderDispatchTest`.
- **Riesgo ODD**: `item 1` (migración que relaja `treatment_plan_id` a nullable,
  aditiva y reversible) por la regla de migraciones; el resto del alcance no
  toca datos, credenciales ni contratos de otros consumidores. Ese item dispara
  la regla de verificación: un `gentle-ai-verify` independiente revisa el
  contrato del backend (migración, aprobación, `q`/`summary`, orden de rutas y
  consumidores).
- **Verificación independiente** (`gentle-ai-verify`, 2026-10-20): PASS en los
  6 ítems del contrato backend — migración aditiva y reversible
  (`2026_10_20_000000_…:20-35`), aprobación sobre columnas reales
  (`QuotationService.php:185-193`), filtros y meta de paginación intactos
  (`:212-249`), `q` correctamente agrupado (`:215-220`), `summary` con los seis
  estados pre-sembrados (`:266-295`), `summary` declarado antes de `{id}` y en el
  grupo de lectura (`routes/api.php:242-247`), y consumidores sin dependencia
  del cambio (`BillingService.php:147-177`, `DashboardController.php:368`,
  `PatientController.php:240,337`). Dos advertencias registradas:
  (a) descartada — el `change()` sí se ejecutó sobre MySQL real en
  `odontosuite_quot_test` (RefreshDatabase + `QuotationIndexSummaryTest` crea un
  presupuesto manual con la columna nullable, 7/7 verde); (b) válida — el
  `down()` restauraría NOT NULL y fallaría si existieran presupuestos manuales
  con `treatment_plan_id` NULL, riesgo sólo de rollback con datos.
- **ASSESS nativo** (2026-10-20): `medium`, 20 paths / 4.474 líneas,
  `review_due=false` (`under_budget`) vía `gentle-ai review assess --base-ref
  b3c168a… --committed-only`. La fachada devolvió `unassessable` porque no hay
  cambios pendientes. Con `review_due=false` y sin pedido explícito del usuario
  no se inicia revisión nativa. Push, PR y merge quedan a decisión del usuario.

## Follow-ups abiertos (no bloquean)

- Los 3 eventos de presupuesto emiten el modelo completo por `Channel('quotations')`
  público (`QuotationCreated.php:26-31`); mismo hallazgo cross-cutting ya
  registrado para `dashboard-updates`.
- `approve`/`reject` no tienen guarda de transición en el servidor: la UI sólo
  los ofrece para `sent`/`viewed`, pero la API acepta cualquier estado.
- `QuotationStatusBadge.vue` (consumido por `TreatmentPlanDetail.vue:154-168`)
  sigue mapeando 4 estados; no se rediseñó para no cambiar otro módulo.
- `PatientDetailPage.vue:987` empuja `/quotations/{id}`, ruta que no existe; el
  enlace debería abrir el detalle desde la lista.
- `UiEmptyState.icon` declara `String|Object` y las páginas le pasan
  componentes funcionales de heroicons: warning de Vue en pruebas (preexistente
  en métodos de pago).

## Log

- **L1** (2026-10-20 11:03) — "continuando con este proyecto conforme dejamos el
  dashboar quiero hacer ahora la interfaz de presupuestos, para hacer ello en un
  worktree nuevo"
- **L2** (2026-10-20 11:20) — El guard `CajaPagesAppShellTest` (PAGOS-MNY-002)
  exige que todo archivo que renderice `formatCurrency` lo importe de
  `useFormatters`: se retiró el formateador de `quotationMeta.js` y los tres
  writers recibieron la corrección.
- **L3** (2026-10-20 11:26) — `UiDataTable` renderiza el slot `actions` también
  en su cabecera cuando `show-header` está activo; la página pasó a
  `:show-header="false"` (patrón de métodos de pago) para no duplicar botones
  con `row` indefinido.
- **L4** (2026-10-20 11:33) — DB aislada `odontosuite_quot_test` (socket XAMPP,
  root/<clave local>) y build propio con `VITE_APP_URL=http://127.0.0.1:8001` para la
  pasada visual; `public/build` es artefacto no versionado.
- **L5** (2026-10-20 11:52) — `QuotationCard.vue` se retiró con la reescritura
  del detalle (quedó sin consumidores); `QuotationStatusBadge.vue` se conserva
  porque `TreatmentPlanDetail.vue` lo importa (fuera de alcance).
