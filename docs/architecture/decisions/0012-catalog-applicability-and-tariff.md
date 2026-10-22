# ADR-0012 — El catálogo declara sobre qué se hace y a qué precio, con vigencia

- **Status:** Accepted
- **Decided:** 2026-10-22 (issue #293, specs S1-S7 de
  `odd/tasks/catalogo-aplicabilidad-tarifario-293.md`)
- **Body recorded:** 2026-10-22 (misma entrega)
- **Supersedes:** the branch-only tariff of ADR-0010 (#290). **Superseded by:**
  nothing.

## Context

El catálogo de procedimientos era un nomenclador a medias. Tenía código, nombre,
especialidad y un `default_cost` global, y una fila de tarifa por sede que
introdujo #290. Faltaban las tres cosas que hacen que un nomenclador sirva para
cobrar:

- **Aplicabilidad.** Nada decía si un procedimiento se hace sobre una pieza, una
  superficie, un cuadrante, una arcada o sobre el paciente entero. El plan y el
  presupuesto no podían exigir el sitio, así que un presupuesto podía describir
  una obturación sin decir qué diente.
- **Tarifa por convenio.** El precio se acordaba por sede; un financiador
  (convenio, aseguradora, SIS) negocia su propia tarifa, y no había dónde
  escribirla.
- **Vigencia del precio de lista.** La ventana de vigencia existía sólo en la
  fila de sede; el precio de lista no tenía fecha.
- **Mapeo externo.** Un procedimiento no guardaba su código en el catálogo
  público (RM 550-2023/MINSA) ni en el tarifario del SIS, así que no había tabla
  de equivalencias.

La pregunta de fondo era dónde vive el precio que un presupuesto muestra: ¿se
copia del plan, se recalcula al emitir, o lo declara el cliente?

## Decision

**Aplicabilidad declarada (`procedure_catalog.site_type`).** Cinco valores:
`none` (sitio independiente), `tooth`, `surface`, `quadrant`, `arch`. `none` es
el valor por omisión, de modo que el catálogo existente conserva su contrato.
Una línea que nombra un procedimiento con sitio debe llevar ese sitio: la pieza,
la superficie (que ya nombra su pieza), el cuadrante o la arcada. La regla vive
en una sola clase (`ProcedureSiteRule`) y la aplican los dos escritores de
líneas —el plan y el presupuesto—, porque el presupuesto hereda lo que el plan
declaró; el traspaso plan → presupuesto se niega a emitir una línea anterior a
la regla que no nombre su sitio.

**Una sola tabla de tarifario (`procedure_prices`).** `procedure_branch_prices`
se generaliza: cada fila tiene `branch_id` y `payer_id` **opcionales**, de modo
que describe una tarifa con la sede, con el convenio, con ambos o con ninguno
(lista del grupo, también con ventana). `payers` es el catálogo de convenios. La
resolución elige, a la fecha de referencia, la fila vigente de mayor
especificidad: convenio+sede → convenio → sede → `default_cost`. El resultado
nombra su origen y la fila aplicada. Las filas existentes se copiaron
preservando `id`, así que ningún presupuesto escrito antes cambia de
significado.

**El precio se congela al emitir.** El presupuesto resuelve el precio el día de
su emisión (`quotation_date`), no el día en que se escribió el plan: la emisión
es el acto comercial. La línea guarda el precio y la evidencia del tarifario
(`price_source`, `price_row_id`, `price_valid_from`, `price_valid_to`) y el
presupuesto guarda su convenio (`payer_id`). Después nadie recalcula: cambiar el
tarifario no mueve un presupuesto emitido ni una atención cerrada, porque el
cierre copia el costo de la línea ejecutada.

**Mapeo externo (`procedure_external_codes`).** Cada procedimiento guarda sus
códigos por sistema (`minsa_rm550_2023`, `sis`, o el slug de otro financiador),
con el código único por procedimiento y sistema. El listado busca por
`external_system` + `external_code`.

## Alternatives considered

**Sitio como texto libre en la línea.** Descartado: un código de cuadrante sin
vocabulario cerrado no se puede validar ni comparar, y el odontograma ya nombra
la pieza por FK.

**Dos tablas de tarifa (sede y convenio).** Descartado: obliga al resolvedor a
leer dos fuentes y a decidir una precedencia sin un lugar único donde verla. Una
tabla con dimensiones opcionales expresa la misma jerarquía y permite la fila de
lista con vigencia.

**Recalcular el precio en cada lectura del presupuesto.** Descartado: es
exactamente lo que la issue prohíbe. El presupuesto es un documento firmado, no
una vista del tarifario.

**Confiar en el `subtotal` que envía el cliente.** Descartado: el documento
tiene que cuadrar con sus líneas. Cuando llegan líneas, el bruto del presupuesto
es su suma; el cliente no puede declarar un total que el documento no describe.

**Renombrar la tabla de tarifa con `->change()`.** Descartado por la política de
migraciones aditivas (ADR-0009): se creó la tabla nueva, se copió con el
constructor de consultas y se retiró la vieja, con un `down()` que la
reconstruye y devuelve las filas de sede.

## Consequences

- El plan y el presupuesto rechazan con 422 una línea que no nombre el sitio de
  su procedimiento; el mensaje nombra el procedimiento y el sitio que falta.
- `tooth_surfaces` deja de ser una tabla vacía: `ToothSurfaceSeeder` da cinco
  superficies canónicas (V/L/M/D/O) a cada pieza activa, y
  `GET /api/dental-pieces` las publica. Sin eso, un procedimiento de superficie
  no tenía forma de cumplirse.
- Una fila de tarifa de convenio se administra con `manage.payer_price` y
  alcance de grupo (`branch.global`): el precio de un convenio no es una
  decisión de sede. La puerta de sede sólo lee y escribe filas sin convenio.
- `generateQuotation` ya no copia `treatment_plans.total_cost`: el bruto del
  presupuesto es la suma de sus líneas congeladas. Un plan cuyo `total_cost` se
  editó a mano no impone ese número al presupuesto.
- Los convenios y su editor de tarifario, y el mapeo externo masivo, quedan sin
  pantalla propia: la superficie entregada cubre catálogo, plan y presupuesto.
  Se declara como follow-up en la órbita de #304.

## References

- Issue #293 y `odd/tasks/catalogo-aplicabilidad-tarifario-293.md`.
- ADR-0009 (política de migraciones aditivas), ADR-0010 (la sede como dimensión
  de autorización), ADR-0011 (el IGV se descompone del precio).
- RM 550-2023/MINSA (catálogo de procedimientos del sector salud) y tarifario
  del SIS, como referentes del mapeo externo.
