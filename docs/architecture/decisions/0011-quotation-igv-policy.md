# ADR-0011 — El IGV de un presupuesto se descompone del precio, no se suma

- **Status:** Accepted
- **Decided:** 2026-10-22 (issue #289, spec S1 de
  `odd/tasks/billing-289-291-igv-metodos-pago.md`)
- **Body recorded:** 2026-10-22 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

`quotations` tenía `tax_percentage`, `tax_amount` y `subtotal` desde la primera
migración, y tres escritores distintos del bloque económico: el servicio del
presupuesto manual (`QuotationService`), el generador del plan
(`generateQuotation`) y el cierre de la atención (`BillingService`). Ninguno
declaraba una política:

- `generateQuotation` copiaba la tasa del payload y por defecto ponía `0`, así
  que el IGV nunca aparecía salvo que un cliente lo pidiera.
- `BillingService` escribía `0` fijo: el presupuesto automático del cierre nacía
  sin IGV por construcción.
- `calculateAmounts` **sumaba** el impuesto encima del importe:
  `total = subtotal − descuento + IGV`. Con tasa 18% un procedimiento de S/ 118
  pasaba a costar S/ 139.24.
- `updateQuotation` recalculaba desde `$data['subtotal'] ?? 0`, de modo que un
  `PATCH` que solo cambiaba la tasa o el descuento ponía la cotización en cero.

La pregunta de fondo era de negocio, no de código: ¿el precio del catálogo es el
precio que paga el paciente, o es un precio neto al que se le agrega IGV?

## Decision

**El precio del catálogo y del plan es el precio de venta: incluye el IGV.**

1. `total_amount` es el importe que paga el paciente —suma de ítems menos
   descuento— y **nunca** se incrementa por declarar una tasa.
2. El IGV **se descompone** de ese total: `base = round(neto / (1 + t/100), 2)`,
   `igv = round(neto − base, 2)`, y `subtotal` guarda la base imponible.
   `subtotal + tax_amount == total_amount` por construcción.
3. El descuento se aplica antes del desglose y también dentro del precio: el
   paciente paga `bruto − descuento`, con su IGV contenido.
4. La tasa de un presupuesto nuevo que no la declara sale de
   `config('billing.tax.default_percentage')` (18.00, variable
   `BILLING_TAX_PERCENTAGE`). Una tasa declarada en `0` deja el presupuesto sin
   IGV y con `subtotal == total_amount`: es el modo exento, y sigue siendo una
   decisión explícita del documento, no un olvido.
5. Una sola implementación (`App\Support\QuotationTax`) alimenta a todos los
   escritores, para que las puertas manual y automática no puedan divergir.

## Alternatives considered

- **Precio neto + IGV sumado (`total = neto × 1.18`).** Rechazada: cambia lo que
  el paciente paga por el mismo procedimiento según el documento que se emita, y
  en una clínica el precio de lista es el precio de venta. Habría obligado a
  re-etiquetar todo el catálogo para no duplicar el importe.
- **Dejar la tasa en `0` y documentar que el IGV no aplica.** Rechazada: deja el
  defecto como accidente del código y no responde qué pasa cuando una sede sí
  declara tasa. El modo exento existe explícitamente (tasa `0` declarada).
- **Guardar la base y dejar `subtotal` como suma bruta de ítems.** Rechazada: el
  documento mostraría `subtotal + IGV > total`, que es exactamente la cuenta que
  un lector usa para verificar el importe.

## Consequences

- Un presupuesto con tasa por defecto muestra base imponible e IGV en la API, el
  PDF y la pantalla de detalle; el total no cambia respecto de la suma de ítems.
- `subtotal` significa **base imponible** en las filas nuevas. Las filas
  históricas conservan sus valores: con `tax_percentage = 0` la lectura sigue
  siendo coherente (`subtotal == total_amount`). No hay backfill.
- Un `PATCH` parcial recalcula desde el bruto y la tasa ya guardados, así que no
  puede poner los importes en cero.
- La política es de la cotización, no del comprobante: el presupuesto sigue sin
  ser documento tributario y no emite boleta ni factura (brecha 2 de #289, fuera
  de este alcance).
- `BILLING_TAX_PERCENTAGE=0` deja toda la instalación en modo exento sin tocar
  código.

## References

- `app/Support/QuotationTax.php`, `config/billing.php`
- `app/Services/QuotationService.php`, `app/Services/BillingService.php`
- `tests/Feature/Api/QuotationTaxPolicyTest.php`
- `resources/views/reports/quotation.blade.php` (desglose en el PDF)
