# Plan del Flujo Post-Registro de Cita — OdontoSuite

> **Propósito**: cerrar el ciclo de una cita, desde que el paciente llega hasta que la cita queda lista para cobrar.
> **Estado**: Sprint 1 ✅ completado y mergeado a `main` (commit `833ff91`).
> **Próximo**: Sprint 2.

---

## 0. Resumen ejecutivo

OdontoSuite ya tenía las **piezas sueltas** del modelo clínico (`ClinicalEvolution`, `TreatmentPlan`, `OdontogramRecord`, `ProcedureMaterial`, `SpecialtyRecord`), pero ninguna las conectaba. El resultado: un clínico atendía una cita, escribía en el sistema lo que quería, y el siguiente en la cadena (cobro, plan a largo plazo, auditoría) tenía que adivinar qué había pasado.

Este plan arma la **capa de orquestación**: define 3 modos de atención, una transacción única que persiste todo (evolución + odontograma + materiales + plan), y deja la cita con `final_amount` calculado y lista para que el módulo de caja la cobre.

---

## 1. Contexto del problema (lo que existía antes)

### Lo que había
| Pieza | Estado | Conectada con cita? |
|---|---|---|
| `Appointment` con status `in_consultation` / `completed` | ✅ existía | sí (vía `AppointmentController@updateStatus`) |
| `MedicalRecord` (1 por paciente) + `ClinicalEvolution` (muchas, con `appointment_id` opcional) | ✅ existía | **parcial** — el FK estaba, pero nadie lo usaba |
| `TreatmentPlan` + `TreatmentPlanItem` (con `procedure_name`, `dental_piece_id`, `phase_number`, `status`) | ✅ existía | **NO** — el plan no sabía de qué cita salía |
| `ProcedureMaterial` (insumos consumidos por cita) | ✅ existía | **NO** — tabla sin endpoints, nadie la llenaba |
| `Odontogram` + `OdontogramRecord` (con `appointment_id`) | ✅ existía | **NO** |
| `Quotation` (hijo de `TreatmentPlan`) | ✅ existía | depende del plan, no de la cita |
| `Transaction` (con `appointment_id` + `treatment_plan_id` + `status`) | ✅ existía | listo para ser el ancla de cobro |

### Diagnóstico
El modelo de datos estaba **bien diseñado** para lo que queríamos (cita → procedimiento → pago). Lo que faltaba era la **capa de orquestación**: el momento en que una cita pasa a `in_consultation` debería disparar un "modo consulta" donde el clínico registre la evolución, marque piezas, consuma materiales y, si corresponde, cree/avance el plan. Todo eso era manual, separado y fácil de olvidar.

---

## 2. Sprint 1 — Orquestación mínima ✅ COMPLETADO

**Commit**: `833ff91` (merge de `wt/e1b06534`).

### 2.1 Decisiones de diseño cerradas

#### 2.1.1 Tres modos de cita (mutuamente excluyentes)
| Modo | Cuándo | Crea plan | Items | Materiales |
|---|---|---|---|---|
| `consultation` | Evaluación, diagnóstico, control sin ejecución | Opcional (puede crear `proposed`) | — | Opcionales (warning si los hay) |
| `execution` | Limpieza, exodoncia, endodoncia, restauración | Sí, **1 item completed** | 1 | Obligatorios si `requires_materials` |
| `plan_session` | Sesión de un plan existente (ortodoncia mes 3, implante fase 2) | Ya existe, se **avanza** | N (los que ejecute hoy) | Obligatorios si los items lo requieren |

El modo lo **declara el clínico al inicio** del expediente, no al final. Es la primera pregunta del wizard.

#### 2.1.2 Una sola ClinicalEvolution por cita
El modelo permite N evoluciones por cita (polimórfico), pero el 90% de los casos es 1 cita = 1 consulta = 1 evolución. El flujo de Sprint 1 enforce 1 obligatoria al cerrar (validado en el servicio). Si después se necesitan varias (ej. control post-operatorio), se puede relajar la regla.

#### 2.1.3 El plan se crea/avanza al cierre, no al inicio
El clínico va descubriendo lo que hay que hacer durante la atención. Recién cuando termina y sabe el alcance real, crea (o avanza) el plan.

#### 2.1.4 Guard de cierre: SOAP obligatorio
La cita no pasa a `completed` hasta que exista al menos una evolución con los 4 campos SOAP no vacíos. Si el clínico le da "completar" sin SOAP, le salta `MissingEvolutionException` (HTTP 422).

#### 2.1.5 Materiales: opcionales con UX inteligente
- La pestaña de Materiales siempre aparece.
- Tiene un toggle "No se usaron materiales" que cierra la pestaña.
- `AppointmentType.requires_materials = true` (campo nuevo) → materials_count debe ser > 0 al cerrar (o `skip_materials = true`).
- `AppointmentType.requires_materials = false` → toggle apagado, materials_count = 0.
- Para `plan_session`: si el `TreatmentPlanItem` tiene `materials_required` (array en JSON) → obligatorio para ese item.

#### 2.1.6 Cita ancla de cobro, plan ancla de propuesta
- `Appointment` es el evento (la cita ejecutada hoy).
- `TreatmentPlan` es la hoja de ruta (puede ser 1 item o muchos).
- `Appointment.final_amount` se calcula al cierre: suma de `TreatmentPlanItem.total_cost` de los items `completed` en esta cita, o `AppointmentType.price` si no hubo plan.
- `AppointmentCompleted` event expone `final_amount` y `treatment_plan_id` para que el módulo de caja lo consuma.

#### 2.1.7 `TreatmentPlan.last_activity_at`
Campo nuevo persistido. Se actualiza cada vez que una cita avanza el plan. Permite ordenar planes "más activos" y detectar abandono.

### 2.2 Archivos del Sprint 1

#### 2.2.1 Backend (PHP) — 20 archivos
| Tipo | Ruta |
|---|---|
| Migración | `database/migrations/2026_06_06_230000_add_consultation_orchestration_fields.php` |
| Migración (parche) | `database/migrations/2026_06_07_001200_make_odontogram_records_color_nullable.php` |
| Migración (parche) | `database/migrations/2026_06_07_002000_add_proposed_to_treatment_plan_items_status.php` |
| Servicio | `app/Services/ConsultationService.php` |
| Controlador | `app/Http/Controllers/Api/ConsultationController.php` |
| Recurso | `app/Http/Resources/TreatmentPlanResource.php` |
| Recurso (modificado) | `app/Http/Resources/AppointmentResource.php` |
| Evento | `app/Events/AppointmentCheckedIn.php` |
| Evento | `app/Events/AppointmentCompleted.php` |
| Excepciones (7) | `app/Exceptions/Consultation/ConsultationException.php` (base) + 6 específicas |
| Modelo (modificado) | `app/Models/Appointment.php` |
| Modelo (modificado) | `app/Models/AppointmentType.php` |
| Modelo (modificado) | `app/Models/TreatmentPlan.php` |
| Modelo (modificado) | `app/Models/TreatmentPlanItem.php` |
| Modelo (modificado) | `app/Models/ProcedureMaterial.php` |
| Rutas (modificado) | `routes/api.php` |

#### 2.2.2 Frontend (Vue) — 3 archivos
| Tipo | Ruta |
|---|---|
| Composable | `resources/js/composables/useConsultation.js` |
| Componente | `resources/js/modules/appointments/ConsultationWizard.vue` |
| Página (modificada) | `resources/js/modules/appointments/CalendarPage.vue` |

### 2.3 Endpoints nuevos
```
GET    /api/appointments/{id}/consultation-context   →  datos para el wizard
POST   /api/appointments/{id}/check-in              →  status = in_consultation
POST   /api/appointments/{id}/complete              →  cierra la consulta (transacción)
```
Todos bajo `auth:sanctum` + `role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente` (los mismos que ya tenía el grupo de citas).

### 2.4 Eventos WebSocket
| Evento | Canal | Disparado por |
|---|---|---|
| `appointment.checked_in` | `appointments`, `dashboard-updates` | `ConsultationService::checkIn()` |
| `appointment.completed` | `appointments`, `dashboard-updates` | `ConsultationService::complete()` |

Reutilizan el canal que ya consumía `CalendarPage.vue` y `DashboardPage.vue`.

### 2.5 Reglas de validación (en el servicio)
| Regla | Excepción | HTTP |
|---|---|---|
| Cita en `in_consultation` | `AppointmentNotInConsultationException` | 409 |
| Modo válido (`consultation`/`execution`/`plan_session`) | `InvalidConsultationModeException` | 422 |
| 4 campos SOAP no vacíos | `MissingEvolutionException` | 422 |
| `plan_session` con `treatment_plan.id` válido del paciente | `InvalidTreatmentPlanException` | 422 |
| `consultation` no debe traer materiales | `UnexpectedMaterialsException` | 422 |
| `requires_materials = true` con 0 materiales y sin `skip_materials` | `MissingMaterialsException` | 422 |

### 2.6 Bugs encontrados durante testing (arreglados)
1. **`odontogram_records.color` era NOT NULL** → migración para hacerlo nullable.
2. **`TreatmentPlanItem.materials_required` no tenía cast `array`** → agregado al modelo. La columna es TEXT; Eloquent serializa solo.
3. **`TreatmentPlanItem.status` enum no incluía `proposed`** → migración extendiendo el enum.

### 2.7 Verificación funcional (5/5 tests verde)
- ✅ Check-in cambia status a `in_consultation`.
- ✅ `consultation-context` devuelve cita + planes activos + `requires_materials`.
- ✅ `complete` modo `execution` cierra con plan nuevo (1 item completed) y `final_amount` calculado.
- ✅ `complete` modo `consultation` con `as_proposed = true` crea plan en status `proposed`.
- ✅ SOAP vacío → HTTP 422. Cita sin check-in → HTTP 409.

---

## 3. Sprint 2 — Catálogo de procedimientos 📋 PENDIENTE

**Por qué se necesita**: en Sprint 1, los items del plan usan `procedure_name` libre. Eso está bien para arrancar, pero a la larga genera problemas: precios inconsistentes, no hay autocomplete, no se puede saber qué hace un procedimiento, no se puede asociar materiales por defecto.

**Objetivo**: estandarizar los procedimientos en un catálogo, con código, nombre, especialidad, costo y materiales sugeridos.

### 3.1 Modelo de datos

**Nueva tabla** `procedure_catalog`:
```php
Schema::create('procedure_catalog', function (Blueprint $table) {
    $table->id();
    $table->string('code', 32)->unique();          // 'ENDO-001', 'REST-COMP-2S', etc.
    $table->string('name');                         // 'Endodoncia unirradicular'
    $table->text('description')->nullable();
    $table->string('specialty', 64)->nullable();    // 'endodoncia', 'rehabilitacion', etc.
    $table->decimal('default_cost', 12, 2);
    $table->integer('default_duration_minutes')->nullable();
    $table->json('default_materials')->nullable(); // ['lima_K', 'gutta_percha', 'anestesia']
    $table->boolean('requires_anesthesia')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**FK nueva en `treatment_plan_items`**: `procedure_catalog_id` (nullable).

### 3.2 Endpoints nuevos
```
GET    /api/procedure-catalog                       →  lista paginada con filtros
GET    /api/procedure-catalog/active                →  solo activos (para selects)
GET    /api/procedure-catalog/{id}                  →  detalle
POST   /api/procedure-catalog                       →  crear (admin)
PUT    /api/procedure-catalog/{id}                  →  editar (admin)
DELETE /api/procedure-catalog/{id}                  →  soft-delete (admin)
GET    /api/procedure-catalog/search?q=             →  búsqueda rápida (autocomplete)
```

### 3.3 Cambios al frontend

En el `ConsultationWizard.vue`, en el paso "Procedimientos":
- Reemplazar el input `procedure_name` libre por un **selector con autocomplete** que llame a `/api/procedure-catalog/search?q=...`.
- Al seleccionar un procedimiento del catálogo → autocompleta `unit_cost`, `default_duration_minutes`, `materials_required`, `specialty`.
- El clínico puede override el costo (con justificación) si quiere.

### 3.4 Cambios al backend

- `TreatmentPlanItem.procedure_catalog_id` (nullable FK).
- `TreatmentPlanItem.procedure_name` se mantiene como snapshot (texto libre) para que cambios al catálogo no rompan planes viejos.
- `ProcedureCatalogService` (nuevo) — encapsula el autocomplete.

### 3.5 Seeder
Crear `ProcedureCatalogSeeder` con al menos 30-50 procedimientos comunes (limpieza, profilaxis, restauración con resina, endodoncia uni/bi/tri, exodoncia simple/compleja, corona, puente, implante, ortodoncia brackets, blanqueamiento, sellantes, fluorización, etc.).

### 3.6 Criterios de aceptación
- [ ] El clínico puede buscar un procedimiento por texto y seleccionarlo del catálogo.
- [ ] Al seleccionar, los campos del item se autocompletan.
- [ ] Los precios de los items en planes viejos se mantienen aunque el catálogo cambie.
- [ ] Hay un CRUD de catálogo para el admin (futuro sprint de "administración clínica").
- [ ] Hay al menos 30 procedimientos en el seeder demo.

### 3.7 Estimación
- 1 migración + 1 modelo + 1 service + 1 controller + 1 resource + 1 seeder + 1 componente Vue.
- Total: ~7 archivos nuevos, 3 modificados.

---

## 4. Sprint 3 — Quotation + hook de pago 💰 PENDIENTE

**Por qué se necesita**: hoy la cita queda con `final_amount` pero no hay link visible hacia el módulo de caja. Falta:
1. Generar una `Quotation` automáticamente al cierre de la cita (cuando aplica).
2. Exponer un endpoint "listo para cobrar" en la cita.
3. Que el módulo de caja (que ya existe) pueda consumir el `AppointmentCompleted` event.

### 4.1 Flujo objetivo

```
[Consulta cerrada, status=completed, final_amount=X]
   │
   │  Si tiene TreatmentPlan Y los items no se cobraron en otra cita
   │  → auto-crear Quotation (status=sent)
   │  → link a PDF
   │
   │  Evento AppointmentCompleted dispara
   │  → listener en módulo de caja crea TransactionPending con appointment_id
   │
   ▼
[Listado "Citas por cobrar" en módulo de caja]
   - cita #43 - Luis Cano - S/ 350.00
   - cita #25 - María - S/ 50.00 (ya tiene pago parcial)
   - cita #10 - Juan - S/ 1200.00 (plan ortodoncia, primer pago)
   │
   ▼
[Cajero selecciona, aplica método de pago, registra Transaction]
   → Appointment.has_payment = true
   → evento PaymentRegistered (ya existe)
```

### 4.2 Modelo de datos
**Cero cambios nuevos** — el modelo ya tiene:
- `quotations.treatment_plan_id` (FK)
- `quotations.status` (enum: `draft`, `sent`, `viewed`, `approved`, `rejected`, `expired`)
- `quotation_items.treatment_plan_item_id` (FK)
- `transactions.appointment_id` (FK nullable)
- `transactions.treatment_plan_id` (FK nullable)
- `transactions.status` (enum: `pending`, `completed`, `failed`, `cancelled`)

Solo hay que:
- Agregar `quotations.appointment_id` (nullable, FK) — para cotizaciones que nacen directo de una cita sin plan a largo plazo.
- Agregar `transactions.quotation_id` (nullable, FK) — para trazabilidad.

### 4.3 Endpoints nuevos / modificados

Nuevos:
```
GET    /api/appointments/ready-to-bill               →  lista de citas completed sin pago completo
GET    /api/appointments/{id}/payment-preview       →  desglose: items del plan, monto, pagos parciales, saldo
POST   /api/appointments/{id}/generate-quotation    →  genera la quotation desde la cita cerrada
```

Listeners nuevos:
- `OnAppointmentCompleted → CreateTransactionIfApplicable` (en módulo de caja)

### 4.4 Cambios al `ConsultationService`

En el método `complete()`, al final:
```php
if ($treatmentPlan && $this->shouldGenerateQuotation($treatmentPlan, $payload)) {
    $quotation = $this->createQuotation($appointment, $treatmentPlan);
    event(new QuotationCreated($quotation));
}
```

`shouldGenerateQuotation()` devuelve `true` cuando:
- El plan tiene al menos 1 item `completed` o `proposed`.
- El clínico pasó `payload['generate_quotation'] = true` (default true para `execution` y `plan_session`, false para `consultation` puro).

### 4.5 Cambios al frontend

- En `CalendarPage.vue`, después de cerrar una cita → mostrar un toast con el link "Ver cotización" si se generó.
- En el módulo de caja, agregar vista "Citas por cobrar" que use `GET /api/appointments/ready-to-bill`.

### 4.6 Criterios de aceptación
- [ ] Al cerrar una cita modo `execution`, se genera una `Quotation` automáticamente.
- [ ] Al cerrar una cita modo `consultation` con plan propuesto, NO se genera cotización (es propuesta, no compromiso).
- [ ] El cajero ve las citas completadas y puede aplicar pagos.
- [ ] Pagos parciales se reflejan en `Appointment.has_payment` y en el saldo del plan.

### 4.7 Estimación
- 1 migración (2 columnas nuevas) + 1 listener + 1 método en `ConsultationService` + 1 endpoint nuevo + 1 vista Vue.
- Total: ~5 archivos nuevos, 4 modificados.

---

## 5. Sprint 4 (opcional) — Refinamientos 🔧 PENDIENTE

Cosas que probablemente vas a querer después de usar el sistema en producción:

### 5.1 Stock automático
- Cuando se registra un `ProcedureMaterial`, descontar del `Product.stock` actual.
- Si `quantity_used > stock`, warning (no bloquear) con flag `stock_exceeded = true`.
- Listado de "productos con stock bajo" en el dashboard.

### 5.2 Auditoría detallada de la consulta
Hoy hay `AuditLog::log()` por evento individual (crear plan, crear evolución). Para el Sprint 1 sería útil un `AuditLog::log('consultation_completed', ...)` único al cerrar la cita, con metadata de las acciones (items afectados, materiales consumidos, plan creado).

### 5.3 Dashboard "Mi plan" del paciente
Una vista de solo lectura donde el paciente (o el recepcionista) ve:
- Plan actual
- Barra de progreso
- Última evolución
- Próxima cita
- Saldo

### 5.4 Odontograma en vivo
Hoy el odontograma se llena con un dropdown de condición. Mejor: SVG interactivo donde el clínico hace click en una pieza y se abre un panel de "lo que pasó con esta pieza en esta cita". Esto es un trabajo de UX pesado (Sprint dedicado).

### 5.5 Test coverage
- `tests/Feature/ConsultationServiceTest.php` con casos por modo + edge cases.
- `tests/Feature/ConsultationControllerTest.php` con auth + validación.

### 5.6 Catálogo de diagnósticos
Hoy `MedicalRecord.diagnosis` es texto libre. Catálogo de diagnósticos CIE-10 (migración + autocomplete en la evolución).

---

## 6. Convenciones del proyecto (recordatorio rápido)

- **Backend**: Laravel 11 + Sanctum, eventos auto-descubiertos, services en `app/Services/`, Resources en `app/Http/Resources/`, Excepciones tipadas con `error_code` + `httpStatus` en `app/Exceptions/`.
- **Frontend**: Vue 3 + Composition API, **sin Pinia** (estado via composables singleton), UI primitives en `resources/js/components/ui/`, módulos en `resources/js/modules/<dominio>/`.
- **Auth state**: `useAuth()` lee de `localStorage` (`auth_token` + `user`).
- **API client**: SIEMPRE `useApi().get/post/put/del`. No axios directo.
- **Roles backend** (string en `users.role`): `administrador`, `recepcionista`, `odontologo`, `implantologo`, `tecnico_dental`, `asistente`, `finanzas`.
- **Package manager**: `pnpm` siempre. No `npm install`.
- **Git**: worktrees para features grandes, merge con `--no-ff`, mensajes cortos y descriptivos sin emojis.
- **Codificación**: UTF-8 en todos los archivos. Cuidado con PowerShell corrompiendo acentos (usar `-Encoding UTF8` o Read/Write/Edit tools).

---

## 7. Comandos útiles

```powershell
# Backend
cd "E:\UNIVERSIDAD PRIVADA DEL NORTE\UPN 10 CICLO\Capstone\Proyecto\OdontoSuiteV2\OdontoSuite"
php artisan migrate                    # aplica migraciones pendientes
php artisan migrate:rollback --step=1  # rollback
php artisan route:list | Select-String "consultation"
php artisan tinker --execute="..."

# Frontend
cd "..."
pnpm dev
pnpm build
pnpm lint:check

# Servidor dev
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start

# Si autoload no reconoce clases nuevas
composer dump-autoload -o

# Ver logs de errores
Get-Content storage/logs/laravel.log -Tail 30
```

---

## 8. Decisiones pendientes (reabrir si quieres)

1. **¿Las evoluciones se siguen acumulando 1 por cita, o permitimos varias?** Hoy el flujo force 1. Si después quieres "evoluciones de control" sin cerrar la cita, hay que relajar la validación.
2. **¿El odontograma debe tener UI de SVG interactivo o basta con el formulario actual?** El SVG es bonito pero es mucho trabajo de UX.
3. **¿Los clínicos pueden editar planes creados por otro clínico?** Hoy no hay policy. Si quieres, se puede agregar `TreatmentPlanPolicy`.
4. **¿La receta se imprime al cierre de la cita?** Hay `prescriptions` en la evolución, pero no hay endpoint de impresión todavía.

---

## 9. Archivos críticos (los que NO debes borrar sin querer)

- `app/Services/ConsultationService.php` — el corazón del Sprint 1. Si lo borras, el cierre de citas se rompe.
- `database/migrations/2026_06_06_230000_add_consultation_orchestration_fields.php` — sin esto, las 4 columnas nuevas no existen y el modelo no se hidrata.
- `database/migrations/2026_06_07_001200_make_odontogram_records_color_nullable.php` — parche que arregló el bug de `color` NOT NULL.
- `database/migrations/2026_06_07_002000_add_proposed_to_treatment_plan_items_status.php` — parche que extendió el enum `status`.
- `resources/js/modules/appointments/ConsultationWizard.vue` — el "expediente de cita" entero.
- `resources/js/composables/useConsultation.js` — el singleton que maneja el estado del wizard.

---

## 10. Resumen de una línea

> Sprint 1 ✅ hecho: cita → expediente (3 modos) → plan/items/materiales/evolución en una transacción. Sprint 2 catálogo de procedimientos. Sprint 3 cotización + pago. Todo documentado para que retomes en frío.
