# Presupuestos con IGV explícito e inmutables + métodos de pago con tipo y referencia

Rama `feat/billing-integrity-289-291`, worktree `~/proyectos/odontosuite-289-291`.
PR https://github.com/ArnoldA123/OdontoSuite/pull/306 (base `main`, commit base `c89ce4b`).
Familias de endpoints: `quotations` (#289) y `payment-methods` / `transactions` / `cash-reports` (#291).

## Specs

S1 — #289, política de IGV: «**IGV**: existe `tax_percentage` pero sin política. Definir si los
precios del catálogo son con o sin IGV y dónde se aplica; hoy otorga 0 por defecto.» Criterio de
aceptación: «Está definido y probado el tratamiento del IGV.»
Decisión: el precio del catálogo y del plan es el **precio de venta** que paga el paciente
(IGV incluido). El IGV **se descompone** del importe, nunca se suma encima: `total_amount` es la
suma de los ítems menos el descuento; `subtotal` es la base imponible y `tax_amount` el IGV
contenido en ese total. La tasa de un presupuesto nuevo que no la declara viene de
`config('billing.tax.default_percentage')` (18.00); una tasa declarada en 0 deja el presupuesto
sin IGV y con `subtotal == total_amount`. Fórmulas, con `bruto` = suma de ítems y `d` descuento:
`neto = round(bruto - d, 2)`, `base = round(neto / (1 + t/100), 2)`, `igv = round(neto - base, 2)`.

S2 — #289, inmutabilidad: «**Inmutabilidad**: verificar que el presupuesto aceptado conserve sus
precios aunque el catálogo o el plan cambien.» Criterio de aceptación: «Un presupuesto aceptado
no cambia de importe si el catálogo cambia después.»
Regla: `PUT`/`PATCH /api/quotations/{id}` responde 422 con
`Solo se puede modificar un presupuesto en borrador, enviado o visto. Estado actual: {status}.`
para `approved`, `rejected` y `expired`, sin escribir nada; `draft`, `sent` y `viewed` siguen
editables. Un cambio posterior en `procedure_catalog` o en el plan no altera importes ni
`unit_price` de un presupuesto ya emitido.

S3 — #291, tipo de método: «No hay `tipo` (efectivo, tarjeta, transferencia, billetera): todos
son iguales y la conciliación no distingue comportamiento.»
Regla: `payment_methods.type` es una familia de comportamiento con cuatro valores —
`cash`, `card`, `transfer`, `wallet`— validada en el CRUD de administración y expuesta por
`GET /api/payment-methods` y `GET /api/payment-methods/active`. Los métodos del sistema quedan:
`cash`→`cash`, `transfer`→`transfer`, `credit_card`/`debit_card`→`card`, `yape`→`wallet`.

S4 — #291, referencia obligatoria: «No se exige número de operación por método.» Criterio de
aceptación: «Un método que exige referencia no registra el cobro sin ella.»
Regla: `payment_methods.requires_reference` (verdadero en `card`, `transfer` y `wallet`).
`POST /api/transactions` y `POST /api/pending-payments/{id}/pay` responden 422 con
`El método de pago {nombre} exige un número de referencia.` cuando el cuerpo no trae
`reference_number` o trae solo espacios, y no se escribe transacción, movimiento ni saldo.

S5 — #291, conciliación del día: criterio de aceptación «La conciliación del día separa efectivo
de medios electrónicos.»
Regla: `GET /api/cash-reports/daily` añade `reconciliation.cash` y `reconciliation.electronic`
(`count` y `total`, cada uno la suma de sus métodos) y cada fila de
`summary_by_payment_method` declara su `type`; el bloque `payment_methods_analysis` del reporte
de período declara también su `type`. La población y las claves existentes no cambian.

Fuera de alcance (no son criterios de aceptación): emisión de boleta/factura y enlace SUNAT
(#289, brecha 2); pago mixto o split y el gobierno de `requires_authorization`/`allows_change`
(#291, brechas 2, 4 y 5). Quedan como seguimiento, sin tarea en este documento.

## Tasks

- T1 · S1 · inline · `12da55e` — Política de IGV: `config/billing.php`, `App\Support\QuotationTax`,
  `QuotationService`, `BillingService`, ADR-0011.
- T2 · S2 · inline · `2f16b1f` — Presupuesto aprobado, rechazado o vencido deja de ser editable;
  guard de estado en `QuotationService::updateQuotation`.
- T3 · S3 · inline · `ff9330c` — `payment_methods.type` con migración, modelo, seeder, CRUD y
  selector de familia en la UI admin.
- T4 · S4 · inline · `ff9330c` — `requires_reference` y rechazo del cobro sin referencia en las
  dos puertas de caja.
- T5 · S5 · inline · `68add8f` — Conciliación por familia en `CashReportService`.
- T6 · S1 · inline · `8fce63b` — Superficie del presupuesto: el preview del modal descompone el
  IGV como el servidor y la forma deja de declarar una tasa 0 por defecto.
- T7 · S1 · inline · `3f4219f` — Defectos D1 y D2 de la verificación: el PATCH parcial ya no
  descuenta dos veces y el PDF y el panel de detalle parten del precio de lista.
- T8 · S4 · inline · `b0fd15e` — Superficie de caja: el método elegido marca la referencia como
  obligatoria y el error `reference_number` cae en el campo que el usuario escribe.

## Log

L1 (2026-10-22, solicitud original, verbatim): «haz la issue 289 y 291, en worktree nuevo»

L2 (2026-10-22, decisión de entrega): el primer commit superaba las ~400 líneas; se separó en
`12da55e` (IGV, el slice de riesgo alto para la revisión nativa) y `2f16b1f` (inmutabilidad).
Límite de slice anotado también para `ff9330c` (603 líneas, 353 de ellas tests): la migración, el
modelo, el guard y la UI del tipo de método son una sola unidad; separarlas dejaría el esquema en
un commit y su regla en otro.

L3 (2026-10-22, seeds): RED observado antes de cada implementación en S1, S2 y S5; en S3/S4 el
código se escribió antes del test, así que el RED se comprobó ejecutando los dos archivos nuevos
contra `c89ce4b` en el worktree principal (16 de 16 en rojo, 16 de 16 en verde aquí).

L4 (2026-10-22, hallazgo durante la verificación): el modal de presupuestos sumaba el IGV encima
del neto y enviaba `tax_percentage: 0` por defecto, así que el preview contradecía al servidor y
la puerta de la UI nacía exenta. Se alineó la superficie (`8fce63b`) con dos casos de smoke que
en `c89ce4b` fallan.

L5 (2026-10-22, verificación independiente): PASS en S1-S5 y dos defectos reales.
D1 — `calculateAmounts` reconstruía el bruto desde `total_amount` (ya neto del descuento) y volvía
a restar el descuento guardado: un PATCH parcial sobre un presupuesto de 200 con descuento 20
dejaba 160 en vez de 180. Corregido con `bruto = total + descuento` y dos casos nuevos en
`QuotationTaxPolicyTest` (T7). D2 — el PDF y el panel de detalle imprimían
`subtotal − descuento + IGV`, pero `subtotal` es la base imponible desde la política: en un
presupuesto con descuento las filas no llegaban al total. Ambos documentos parten ahora del precio
de lista (`total + descuento`), que vale para las filas nuevas y para las históricas sin tasa
declarada; cubierto por `QuotationDocumentBreakdownTest` y `quotation-detail-breakdown.test.js`
(T7). Observación del verificador sin caso vivo: `generateQuotation` ya no honra
`treatment_plans.discount_amount` —ninguna vía escribe ese campo—; queda como nota.
