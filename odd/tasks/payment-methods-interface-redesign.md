# Payment Methods Interface Redesign — CERRADO ✅

Rama: `feat/payment-methods-interface-redesign` (desde `main` @ 862f416)
Worktree: `.worktrees/payment-methods-ui`
Creado: 2026-10-19 · Orquestador: el Gentleman

## Specs

- **S1.** "continuando con este proyecto conforme dejamos el dashboar quiero hacer ahora la interfaz de metodos de pago" — rediseñar la interfaz admin de métodos de pago (`/settings/payment-methods`) con el mismo lenguaje visual con el que quedó el dashboard: superficie canvas, hairlines, elevación, jerarquía hero + KPIs, heroicons y primitivas de `components/ui/`, como en las interfaces ya rediseñadas (profesionales, ambientes, tipos de cita).
- **S2.** "para hacer ello en un worktree nuevo" — el trabajo se aísla en un worktree y en la rama `feat/payment-methods-interface-redesign` partiendo de `main` @ 862f416.
- **S3.** Convenciones heredadas de `AGENTS.md` §7: "UI: consumir SIEMPRE de `components/ui/`. NO botones/modales ad-hoc."; "Código limpio y profesional. **NO comentarios pedagógicos** salvo pedido explícito."; pnpm siempre; commits semánticos en inglés, sin emojis; código e identificadores en inglés, copy de UI en español.

## Auditoría base (2026-10-19, evidencia path:line)

### Frontend

- **F1** `PaymentMethodsPage.vue:137-259` — tabla HTML a mano; `components/ui/DataTable.vue` existe y es la primitiva de los módulos ya rediseñados.
- **F2** `PaymentMethodsPage.vue:120-128` — estados sólo spinner + toast. Un fallo de API deja la lista vacía en silencio (`usePaymentMethods.js:44-49` vacía `methods`), sin error visible ni retry.
- **F3** `PaymentMethodsPage.vue:92-108` — `<select>` nativo para el filtro de estado; `UiSelect` está disponible.
- **F4** `PaymentMethodsPage.vue:266-322` — tres modales de confirmación duplicados; `useConfirm` es la primitiva del repo.
- **F5** `PaymentMethodsPage.vue:190` — la fila muestra `gateway_type` crudo (`mercadopago`) en vez de una etiqueta.
- **F6** `PaymentMethodsPage.vue:190-200` — "configurado" depende de `m.has_gateway_config`, que `index` nunca devuelve (`PaymentMethodController.php:71-86`): indicador muerto.
- **F7** `PaymentMethodsPage.vue:248` — "Eliminar" depende de `m.transactions_count`, que `index` nunca devuelve: el botón aparece sobre métodos con transacciones y el DELETE responde 409 (`PaymentMethodController.php:150-158`).
- **F8** `PaymentMethodsPage.vue:236-253` y `PaymentMethodFormModal.vue:14, 46, 73, 97, 130, 159` — las acciones Activar/Desactivar se ocultan para `is_system` y el modal de edición deshabilita TODOS los campos del sistema con footer "Método del sistema": callejón sin salida. El backend sí permite editar nombre/descripción/comisión/autorización/estado y alternar `is_active`; sólo prohíbe cambiar `code`/`is_system` y borrar (`PaymentMethodController.php:116-133, 145-157`).
- **F9** `PaymentMethodFormModal.vue:126-183` — pasarela y estado con `<select>` nativo; `allows_change` existe en el form (`:229`) sin control que lo edite.
- **F10** `PaymentMethodFormModal.vue:262-268` — `public_key` sola se descarta en silencio: `gateway_config` sólo se envía si hay `access_token`.
- **F11** `PaymentMethodsPage.vue:13` — `PageHeader` duplica el título del topbar de `AppLayout`; el lenguaje aprobado en tipos de cita es la hero band (`AppointmentTypesPage.vue:7-40`).
- **F12** Sin tests del módulo: `tests/js/smoke/settings.test.js` sólo monta `BranchesPage.vue`; no hay guard de diseño propio.

### Backend

- **B1** `/api/payment-methods/active` mapea a `index` (`routes/api.php:113`) y `index` sólo filtra si llega `is_active` (`PaymentMethodController.php:49-51`): el endpoint "activos" devuelve también inactivos → aparecen en los dropdowns de cobro (`PaymentModal.vue:485`), pese al copy "No aparecerá en los dropdowns de cobro" (`PaymentMethodsPage.vue:286`).
- **B2** `index` no expone `has_gateway_config` ni `transactions_count` (F6/F7).
- **B3** `store` acepta `is_system` del cliente (`PaymentMethodController.php:27, 96`): un admin puede crear un método imborrable; `is_system` es una invariante del servidor.

## Decisiones de diseño (arquitecto)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Hero band con eyebrow/título/caption + acciones (Volver, Nuevo método), lenguaje de `AppointmentTypesPage` | Último lenguaje aprobado; elimina el header duplicado (PageHeader + topbar) |
| D2 | KPI strip de 4 tarjetas (Total, Activos, Inactivos, Del sistema); "N/D" mientras carga o falla | Jerarquía del dashboard sin inventar ceros |
| D3 | Lista sobre `UiDataTable`: Método (nombre + descripción), Pasarela, Comisión, Tipo, Estado + acciones | Convención `components/ui/`; mata la tabla a mano |
| D4 | Filtros: búsqueda server-side con debounce (`q` ya existe) + `UiSelect` de estado (`is_active`) | Conserva el contrato y reemplaza el `<select>` nativo |
| D5 | Acciones de fila: Editar (todos), Activar/Desactivar (todos, incluidos del sistema), Eliminar sólo si `!is_system && transactions_count === 0` | Alinea la UI con el contrato real del backend |
| D6 | Vocabulario compartido `paymentMethodMeta.js` (etiqueta/variante de pasarela, tipo y estado; reglas canEdit/canToggle/canDelete; formato de comisión) | Una sola fuente de verdad lista + modal; mata duplicación |
| D7 | Una sola primitiva de confirmación: `useConfirm` para activar, desactivar y eliminar | Elimina 3 modales duplicados |
| D8 | Modal de formulario con secciones (Identificación / Cobro / Pasarela / Descripción), `UiSelect`, errores/hints; cuerpo `<div>` | Evita el submit implícito de `UiSelect` dentro de `<form>` (bug preexistente documentado en ambientes) |
| D9 | Métodos del sistema: `code` read-only, `is_system` inmutable, resto editable y desactivable; borrado bloqueado con explicación | El backend ya lo permite; la UI no debe contradecirlo |
| D10 | Credenciales: se conserva `data-redacted="true"` y `type="password"`; actualizar exige `access_token` y se explica en el hint | Nunca se renderiza el blob; no se envían actualizaciones parciales que borren el token |
| D11 | Backend acotado: `active` filtrado, `index` con `has_gateway_config` + `transactions_count`, `store` fuerza `is_system=false` | El front necesita metadata veraz; la invariante del sistema es del servidor |
| D12 | Guards anclados al contrato (data-attrs, bindings, reglas), no a literales de clase | Lección dashboard-visual-coherence / ambientes |

## Alcance / No-alcance

**Alcance**: `resources/js/modules/settings/payment-methods/*` (lista + modal + vocabulario), `app/Http/Controllers/Api/PaymentMethodController.php` (los tres ajustes de D11), tests JS/PHP del módulo.

**No-alcance**: los otros consumidores del endpoint admin (`TransactionModal.vue:428`, `TransactionList.vue:319`, `useCashRegister.js:348`) que fallan con 403 para no-admin se reportan como follow-ups; sin migraciones; sin cambios al modelo; sin tocar `cash-register`.

## Tasks

| # | Task | Spec | Commit |
|---|---|---|---|
| T1 | Backend: `active` real, metadata en `index`, `is_system` server-owned; tests PHP RED→GREEN | S1, D11 | ✅ 936061a |
| T2 | Vocabulario compartido + test Vitest | S1, D6 | ✅ 749f48c |
| T3 | Lista: hero + KPI + filtros + UiDataTable + estados + acciones + confirm | S1, S3, D1-D5, D7 | ✅ 3ce0ec5 |
| T4 | Modal crear/editar: secciones, UiSelect, reglas de sistema, credenciales | S1, S3, D8-D10 | ✅ c133a4b |
| T5 | Guards/tests + verificación completa + pasada visual | S1-S3 | hecho (f092127, 60d9293, fadee7d, 80ce86b + polish) |
| T6 | Cierre: evidencia, memoria, reporte | S1-S3 | ✅ b914f45 + cierre |

## Estrategia de entrega (work units)

- 6 commits en la rama: `936061a` (T1 backend), `749f48c` (T2 vocabulario), `c133a4b` (T4 modal), `3ce0ec5` (T3 lista), `f092127` (guard a11y PAGOS-A11Y-001), `60d9293` (guard de contrato del rediseño).
- Revisión nativa por slice cuando ASSESS marque `reviewDue`; agregado final de la feature una vez al cierre. Agregado actual ≈ 2.5k líneas (dentro del presupuesto de lens); si ASSESS lo excede, la ruta es por slice con `baseRef` = frontera previa.
- Push/PR/merge: decisión del usuario; la rama entrega los work units ya separados.

## Verificación

- **T1** `936061a` — RED 4/6 (los 2 verdes eran el contrato preexistente que se conserva) → GREEN 6/6 con `PaymentMethodAdminContractTest`; regresión focuseada `PaymentMethod|PaymentModal|TransactionVoidAndReceipt|RecepcionistaCajaAccess` 51/51. DB aislada `odontosuite_pm_test` porque `odontosuite_test` está compartida con sesiones concurrentes.
- **T2** `749f48c` — `paymentMethodMeta.js` + `tests/js/unit/paymentMethodMeta.test.js`, RED por módulo ausente → GREEN 9/9.
- **T3** `3ce0ec5` — lista sobre `UiDataTable` (Método/Pasarela/Comisión/Tipo/Estado), hero band, 4 KPIs con N/D, búsqueda debounced + `UiSelect` de estado, estados skeleton/error+retry/empty, acciones Editar (todos), Activar/Desactivar (todos, incluidos del sistema) y Eliminar solo si `canDelete`. Smoke 9/9. `DataTable.vue` recibe `scope="col"` (a11y).
- **T4** `c133a4b` — modal con secciones Identificación/Cobro/Pasarela/Descripción, `UiSelect` para pasarela y estado, `allows_change` editable, métodos del sistema editables (sin footer muerto), credenciales redactadas con regla access-token obligatorio. Smoke 7/7 + PAGOS-RED-001.
- **T5** — guards: `PaymentMethodsRedesignContractTest` 11/11; `CajaPagesAppShellTest` 40/40; `LegacyAliasForbiddenTest` 12/12; PHP MySQL 1288/1289 con el único fallo `DashboardAppShellTest:1845` anclado al `PageHeader` retirado → re-anclado al hero (`fadee7d`, 51/51). Suites finales: PHP Unit 1011/1011; JS completa en curso; lint 0 errores; doc-drift 12/12; build ✓.
- **T5 visual** (2026-10-19, `:8001` con build propio y DB demo): PASS 3/3 viewports (1440×900, 834×1112, 390×844); 5 filas, KPIs 5/5/0/5, sin `gateway_type` crudo, sin Eliminar en filas del sistema, Editar/Desactivar en todas, secciones del modal completas, código read-only y nombre editable en métodos del sistema, confirm por `useConfirm`. Capturas en `/tmp/payment-methods-visual/`.
- **T5 defectos visuales corregidos** tras la medición: (1) **footer del modal inalcanzable** en todos los viewports (panel `max-h-screen` sin flex; body no scrolleable, footer a 879/879/1081px con viewports 900/768/844) → `Modal.vue` pasa a columna flex con body `flex-1 min-h-0 overflow-y-auto` y header/footer `flex-shrink-0`; medición posterior: footer visible en los 3 viewports y body con scroll real. (2) header "Acciones" de `DataTable.vue` heredaba el estilo de celda (bold) → usa `data-table-header-cell`. (3) comisiones 3.50% vs 2% → `formatCommission` recorta ceros (3.5%/2%/0%). (4) prefill de comisión en edición 0.00 → número normalizado. (5) h2 "Todos los métodos" se apilaba mal en móvil → `flex-col sm:flex-row`.

- T1: `php artisan test --configuration=phpunit.mysql.xml --filter=PaymentMethod` (MySQL XAMPP por socket).
- T2-T4: `pnpm test:js` + tests focuseados.
- T5: suites completas JS y PHP (MySQL), `pnpm lint:check`, `pnpm build`, `scripts/audit/doc-drift.mjs`, Playwright a 3 viewports.
- Cierre: ASSESS nativo y plan resultante (RDD global `on`).

## Follow-ups abiertos (no bloquean)

- **F1**: el topbar de `AppLayout` repite el título del hero (patrón global; también en pacientes y tipos de cita).
- **F2**: `DataTable` en móvil oculta columnas sin affordance de scroll horizontal (primitivo; afecta también a ambientes).
- **F3**: otros consumidores usan el endpoint admin `/api/payment-methods` (`TransactionModal.vue:428`, `TransactionList.vue:319`, `useCashRegister.js:348`) y reciben 403 para no-admin; deberían consumir `/api/payment-methods/active` (fuera de alcance de este módulo).
- **F4**: `getDeleteBlockedReason` está exportado y testeado pero aún no se muestra en la UI (candidato a tooltip en un follow-up).

## Cambio cross-cutting

- `Modal.vue`: columna flex + body scrolleable + header/footer fijos. Afecta a todos los modales; verificado con la suite PHP Unit (1011/1011), la suite JS y la medición visual del footer en 3 viewports.
- `DataTable.vue`: `scope="col"` en sus headers y el header de acciones con el estilo de header (no de celda).

## Cierre (2026-10-19)

- **Commits**: 11 en `feat/payment-methods-interface-redesign` (`936061a`, `749f48c`, `c133a4b`, `3ce0ec5`, `f092127`, `60d9293`, `fadee7d`, `80ce86b`, `108c252`, `2712def`, `b914f45`).
- **Verificación final**: JS 446/446 (55 archivos; `--testTimeout=60000` por la carga concurrente de otras dos sesiones), PHP MySQL 1288/1289 (el único fallo era el guard del `PageHeader` retirado, ya re-anclado → 51/51), PHP Unit 1011/1011, ESLint 0 errores (warnings preexistentes), build ✓, doc-drift 12/12, visual Playwright PASS 3/3 viewports sin defectos de contrato.
- **ASSESS**: nativo `medium`, 15 paths / 2614 líneas, `review_due=false` (`under_budget`). La ruta compacta `assess` devolvió `unassessable` para un rango commiteado; se usó el CLI read-only `gentle-ai review assess --base-ref 862f416… --committed-only`. Con `review_due=false` y sin pedido explícito del usuario **no se inicia revisión nativa**; el plan ASSESS no pide verificador independiente (sin escalación de item de alto riesgo).
- **Riesgo ODD**: `none` — sin migraciones, sin datos reescritos, sin contratos de otros consumidores rotos; los cambios de backend son fixes aditivos/alineados al contrato documentado y el cambio de `Modal.vue` es chrome compartido cubierto por la suite y medido visualmente.
- **Entrega**: push, PR y merge quedan a decisión del usuario.

## Log

- **L1** (2026-10-19 16:06) — "continuando con este proyecto conforme dejamos el dashboar quiero hacer ahora la interfaz de metodos de pago, para hacer ello en un worktree nuevo"
- **L2** (2026-10-19 16:14) — Decisión de implementación: `components/ui/DataTable.vue` recibe `scope="col"` en sus `<th>` (fix de accesibilidad del primitivo) para que la lista pueda usar la primitiva y el guard PAGOS-A11Y-001 siga exigiéndolo; el guard se re-ancla al contrato (tabla explícita o primitiva que lo declara).
- **L3** (2026-10-19 16:15) — Base de datos de test aislada `odontosuite_pm_test` (socket XAMPP********) para no colisionar con los worktrees concurrentes que comparten `odontosuite_test`.
- **L4** (2026-10-19 16:20) — T3 y T4 delegados a dos workers con superficies disjuntas; ambos devuelven commits revisados por el orquestador (`3ce0ec5`, `c133a4b`).
- **L5** (2026-10-19 16:45) — Guard `DashboardAppShellTest:1845` fijaba el literal `title="Métodos de Pago"` del `PageHeader` retirado; se re-ancló a `data-hero-title` (regla: anclar al contrato, no al literal). El guard PAGOS-A11Y-001 se re-ancló a la tabla renderizada (`<th scope="col">` en el primitivo).
- **L6** (2026-10-19 16:50) — JS suite completa bajo carga concurrente falló 11 archivos sin un solo test rojo (timeouts de colección); se re-ejecuta en reposo antes de concluir.
- **L7** (2026-10-19 17:05) — JS 446/446 con `--testTimeout=60000`; PHP Unit 1011/1011; visual PASS 3/3 con footer del modal medido visible. `gentle_review assess` (facade) devolvió `unassessable` por no haber cambios pendientes; el CLI read-only devolvió `medium` / `review_due=false` (`under_budget`) → sin revisión nativa por regla de entrada (solo con `reviewDue` o pedido explícito).
