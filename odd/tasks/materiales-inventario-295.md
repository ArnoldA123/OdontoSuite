# Materiales e inventario: consumo desde la atención y stock real (#295)

Feature `materiales-inventario-295`. Rama `feat/inventory-materials-295`, worktree
`~/proyectos/odontosuite-295`. Issue #295 (`crit:alta`, `area:backend`).

## Specs

**S1 — El cierre descuenta stock y deja el movimiento.** Cita literal de la
issue: «Cerrar una atención con materiales descuenta stock y crea el
movimiento, cubierto por test.» El descuento y el movimiento ocurren dentro de
la transacción del cierre; si el cierre falla, nada queda escrito.

**S2 — Sin stock suficiente se bloquea con 422 y no se escribe nada.** La issue
dejaba «Un material sin stock suficiente se bloquea o se registra con alerta
explícita, a definir.» Decisión del humano: bloquear. Mensaje exacto:

```
Stock insuficiente para «Resina A2»: disponible 2, solicitado 5.
```

Ni `ProcedureMaterial`, ni `StockMovement`, ni evolución, ni cambio de
`products.stock_quantity` sobreviven al rechazo.

**S3 — El costo del acto usa el costo congelado al consumir.** Cita literal de
la issue: «El costo del acto suma materiales con el costo congelado al momento
del consumo.» El `unit_cost` de la línea es el que se congela en
`procedure_materials.unit_cost` y en `stock_movements.unit_cost`; un cambio
posterior de `products.cost_price` no los mueve. `encounter.final_amount` sigue
siendo el precio de venta del acto: el costo de materiales es el costo, no el
precio.

**S4 — El consumo se declara desde el catálogo.** Forma mínima de la issue:
«procedimiento → insumos con cantidad estimada, ajustable en la atención». El
procedimiento declara su recetario (`material_recipe`: producto + cantidad) y la
atención lo precarga; el profesional ajusta cantidades y puede agregar insumos
fuera del recetario. Clave nueva `material_recipe`; la clave existente
`materials` no se reutiliza (hoy se valida como lista de strings y el servicio la
descarta: ningún consumidor puede depender de ella).

**S5 — Movimiento de salida por lote.** Forma mínima de la issue: «Al cerrar la
atención, movimiento de salida de stock por lote, con costo congelado.» Una
línea de material = un `StockMovement` con su `batch_number` y `expiry_date`.
Dos líneas del mismo producto (dos lotes) son dos movimientos, pero la guarda de
stock se evalúa sobre la cantidad agregada por producto.

**S6 — Superficie de inventario.** Forma mínima de la issue: «recepción por
compra, ajuste por conteo, alertas de mínimo y vencimiento.» Endpoints, sin UI
(`area:backend`):

| Método | Ruta | Permiso |
| ------ | ---- | ------- |
| `GET` | `/api/inventory/products` | `view.inventory` |
| `GET` | `/api/inventory/products/{product}` | `view.inventory` |
| `GET` | `/api/inventory/movements` | `view.inventory` |
| `GET` | `/api/inventory/alerts` | `view.inventory` |
| `POST` | `/api/inventory/receipts` | `manage.inventory` |
| `POST` | `/api/inventory/adjustments` | `manage.inventory` |

`products.stock_quantity` es la única verdad del stock *actual*;
`stock_movements` es el libro. Recepción = movimiento `in`; ajuste por conteo =
movimiento `adjustment` que fija el stock en lo contado.

**S7 — `requires_materials` se deriva del procedimiento.** Forma mínima de la
issue: «`requires_materials` derivado del procedimiento, no de un flag manual.»
Un acto exige materiales cuando alguno de los procedimientos que documenta
declara recetario (el procedimiento por defecto del tipo de cita y los
`procedure_catalog_id` de los ítems del plan). `appointment_types.requires_materials`
queda como columna legacy: se sigue aceptando en la escritura para no romper el
formulario, pero ya no decide nada y la respuesta publica el valor derivado.

**S8 — Cantidades decimales (12,3).** Decisión del humano: pasar a
`decimal(12,3)` `products.stock_quantity`/`minimum_stock`/`maximum_stock`,
`stock_movements.quantity`/`previous_stock`/`new_stock` y
`procedure_materials.quantity_used`, para que 0.5 jeringa, 12.5 ml y 1.250 kg
sean cantidades reales del libro.

**S9 — Escrituras previas conservan su contrato.** El resto de endpoints que
tocan productos, stock o materiales (búsqueda del picker, cierre de atención con
sus claves actuales, ajuste de precios del catálogo) sigue respondiendo lo mismo
cuando la entrada es la misma, salvo lo que las specs anteriores cambian a
propósito.

## Tasks

| ID  | Tarea | Specs | Commit |
| --- | ----- | ----- | ------ |
| T1  | Esquema y modelos: recetario, cantidades decimales, trazabilidad del movimiento | S4, S5, S8 | 1 |
| T2  | Recetario en el catálogo: validación, persistencia y lectura | S4 | 1 |
| T3  | Consumo en el cierre: descuento, guarda de stock, costo congelado, movimiento por lote | S1, S2, S3, S5 | 2 |
| T4  | Superficie de lectura del inventario: productos, movimientos, alertas | S6 | 3 |
| T5  | Superficie de escritura: recepción por compra y ajuste por conteo | S6 | 4 |
| T6  | `requires_materials` derivado del procedimiento y precarga en el contexto | S7 | 5 |
| T7  | Documentación: AGENTS.md, CREDENTIALS.md, desks.md y fixture de permisos | S6, S7 | 5 |

## Log

**L1 — Pedido original del humano, literal:**
«haz la issue 295, en worktree nuevo»

**L2 — Decisiones resueltas por el humano antes de escribir código:**

1. Stock corto: «Bloquear con 422 y no escribir nada». Preview aceptado:
   `Stock insuficiente para «Resina A2»: disponible 2, solicitado 5.` y «No se
   crea ProcedureMaterial, ni StockMovement, ni evolución: la transacción
   completa revierte.»
2. Alcance: «Todo: consumo, stock y almacén completo» — recetario, movimiento al
   cerrar, `GET /inventory/products`, `GET /inventory/movements`,
   `POST /inventory/receipts`, `POST /inventory/adjustments` y
   `requires_materials` derivado.
3. Cantidades: «Decimales 12,3 en stock y consumo» — seis columnas en tres
   tablas, riesgo alto (item 1) con verificación independiente.

**L3 — Evidencia del estado inicial (medida en `4b88b0e`).**

- `products` (133 migraciones, 58 modelos, 218 rutas API, 38 controllers) sin
  `ProductSeeder`: la tabla llega vacía a la demo.
- `ConsultationService::persistProcedureMaterials()` crea `ProcedureMaterial` y
  nunca toca `products.stock_quantity` ni `StockMovement`.
- `CloseClinicalEncounterRequest` valida `materials.*.product_id` como
  `integer`, sin `exists`; el servicio salta un producto inexistente con un
  warning y el comentario que dice que el controlador ya valida `exists` es
  falso.
- `stock_movements.branch_id` es `NOT NULL`; `clinical_encounters` y
  `products` no tienen sede. `users.branch_id` es nullable (alcance de grupo).
- `ProcedureCatalogService::normalize()` no conoce `materials`: la validación
  `materials.* => string|max:200` se descarta en silencio.
- `AppointmentType` no tiene relación con `default_procedure_catalog_id`
  (la columna existe desde #293).

**L4 — Decisiones de diseño tomadas al implementar (no preguntadas):**

- `stock_movements.branch_id` pasa a nullable: un usuario de alcance de grupo no
  tiene sede y el movimiento debe poder registrarse (ADR-0010: sin sede = grupo).
- `stock_movements` gana `clinical_encounter_id` nullable para trazar el consumo
  al acto sin depender de `reference` (string de 50).
- El ajuste por conteo con delta 0 responde 422: no hay nada que registrar.
- El ajuste no pasa por `accounting_locks`: el libro de stock no es dinero
  liquidado y el bloqueo contable no lo cubre hoy.
- El lote es trazabilidad, no saldo por lote: `products.stock_quantity` sigue
  siendo el saldo y FIFO por lote queda fuera de esta issue.
- `requires_materials` se acepta en la escritura del tipo de cita y se ignora
  para la regla; la respuesta publica el valor derivado.

**L5 — Cortes de commit (work units).** Commit 1 `ade19f6` = T1+T2 juntos, 739
líneas: el esquema sin la API del recetario no es revisable solo (migración →
modelo → endpoint es una cadena de dependencia). Queda registrado por encima de
la guía de ~400 líneas; los cortes siguientes se mantienen dentro de la guía:
T3 consumo (commit 2), T4 lectura (3), T5 escritura (4), T6+T7 cierre (5).

**L6 — Corte del commit 2.** `524eee2` = T3, ~680 líneas de diff (servicio,
3 excepciones, delegación en el cierre, request, 7 tests nuevos y 2 fixtures
ajustados). El fixture de `EncounterReadSurfaceTest` entra aquí: su `Product`
nace con stock 0 y el cierre consume 2 unidades, así que S2 lo convierte en 422
y el ajuste es consecuencia de la regla, no alcance nuevo. Queda pendiente el
corte de T4+T5, que comparten el guard `RoutePermissionMatrixTest`:
`manage.inventory` no puede commitearse sin sus dos puertas POST.

**L7 — Cortes del commit 3 y 4.** `987ac21` = T4+T5 juntos (~1.500 líneas):
el guard `RoutePermissionMatrixTest` exige ruta para todo permiso del catálogo,
así que `manage.inventory` no puede entrar sin sus dos puertas POST, y las
lecturas comparten controlador con las escrituras. `7163cab` = T6 (~580
líneas): la derivación de `requires_materials`, la precarga de
`consultation-context` y dos arreglos de alcanzabilidad que el writer no podía
tocar —la regla `treatment_plan.items.*.procedure_catalog_id` en el request y la
persistencia de ese vínculo en los ítems que crea el cierre—. Ambos cortes por
encima de la guía de ~400 líneas y registrados como tales.

**L8 — Cierre de documentación y formato.** `a15702a` = T7 (AGENTS.md §4/§6/§12
medido; doc-drift en 14/14 y Prettier limpio). `4f180fb` = orden de imports que
Pint exigía en los dos tests del primer corte. Con eso: Pint 778 archivos en
verde, ESLint 0 errores (36 warnings preexistentes de `vue/one-component-per-file`),
Prettier limpio, suite JS 946 tests en verde.

**L9 — Evidencia de la migración decimal (item 1).** Base descartable
`odontosuite_295_rollback_test`: `migrate` completo deja las 7 columnas en
`decimal(12,3)` con su nulabilidad y defaults (`stock_quantity` y `minimum_stock`
`NO`/`0.000`, `maximum_stock` `YES`/`NULL`, las cinco restantes `NO` sin
default). Ejecutado `down()` de la migración: las columnas vuelven a `int(11)`.
Insertadas filas con el esquema entero (`stock_quantity=12`, `minimum_stock=2`,
`quantity_used=7`) y ejecutado `up()`: los valores quedan `12.000`, `2.000` y
`7.000`, sin pérdida. La dirección inversa trunca fracciones (documentado en el
`down()`): es la precisión que tenía el esquema anterior.

**L10 — Suite completa.** `OK (2478 tests, 14766 assertions)` en
`odontosuite_295_test` después del guard de driver de la migración (`e75c1a1`).
La primera pasada destapó `SddCheckMigrationsTest`: `->change()` compila a
`MODIFY COLUMN` y exige guard de driver.

**L11 — Verificación independiente.** Veredicto 7/7 `cumple`, ningún defecto
causado por el candidato. Revisó la migración con ida y vuelta de datos, la
aritmética del libro leyendo el servicio, el «no escribe nada» (la guarda corre
antes de cualquier `create`), el costo congelado, la derivación de S7 (ninguna
superficie decide por la columna legacy), los contratos (cero `role:`, recepción
fuera de las seis puertas, stock negativo inalcanzable) y doc-drift. Señaló tres
huecos de cobertura, cerrados en `ae8519c`: cadena por lote con costos distintos,
insuficiente en segunda posición sin escrituras de la primera línea, y
`unit_cost` negativo rechazado por la puerta. Observaciones preexistentes que no
son de esta rama: `stock_movements.created_by` NOT NULL con `$userId` nullable
en llamadas internas (toda puerta HTTP autentica) y la precisión de dos
decimales de `unit_cost`/`total_cost`, que es la del esquema original.

**L12 — Cierre.** Suite PHP completa sobre el HEAD final (`ae8519c`):
`OK (2481 tests, 14791 assertions)`, sin fallos ni saltados. doc-drift 14/14,
Pint 778 archivos, Prettier limpio, ESLint 0 errores (36 warnings preexistentes),
suite JS 946 tests. Ocho commits en `feat/inventory-materials-295`; sin push y sin
PR, que es decisión del humano. Riesgos declarados: item 1 en `ade19f6` y
`e75c1a1` (cambio de tipo de columna sobre datos guardados) e item 3 en
`7163cab` (el significado de `requires_materials` para los consumidores del tipo
de cita y del payload de la cita).
