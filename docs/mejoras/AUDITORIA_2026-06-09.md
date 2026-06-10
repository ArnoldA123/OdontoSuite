# Análisis de inconsistencias y mejoras — OdontoSuite V2

> **Fecha**: 2026-06-09
> **Alcance**: análisis transversal de 44 modelos, 75 migraciones, 32 controladores, 17 servicios, 34 eventos, 28 composables y 103 componentes Vue.
> **Stack**: Laravel 12 + Vue 3 + Sanctum + Reverb + Tailwind + FullCalendar
> **Referencia**: `AGENTS.md` §11 (bugs conocidos ya documentados)

---

## Resumen ejecutivo

| Severidad | Cantidad | Descripción |
|---|---|---|
| 🔴 Crítico | 4 | Rompen features o exponen datos entre sedes |
| 🟠 Importante | 8 | Inconsistencias de convención, dead code, DX rota |
| 🟡 Mejora | 6 | Pulido, robustez, cobertura de tests |

**Los 4 críticos requieren ~1 hora de fix combinado y resuelven los bugs más visibles del sistema.**

---

## 🔴 CRÍTICO — Rompe features o corrompe datos

### C-1. QuotationService genera presupuestos en S/ 0.00

**Archivo**: `app/Services/QuotationService.php` (L57-58)
**Causa**: `generateQuotation()` lee `$item->unit_price` y `$item->description` de `TreatmentPlanItem`, pero ese modelo tiene `unit_cost` y `procedure_description`. Los campos leídos no existen → Eloquent devuelve `null`.
**Impacto**: Cada presupuesto generado desde un plan de tratamiento crea items con `unit_price=NULL`, `description=NULL`, `total_price=0`. Los pacientes ven presupuestos en S/ 0.00.
**Verificación**: `BillingService` (L192-193) ya hace el mapeo correctamente (`unit_cost → unit_price`). Copiar el patrón.

**Fix**:
```php
// app/Services/QuotationService.php — dentro del foreach($plan->items)
QuotationItem::create([
    'quotation_id' => $quotation->id,
    'item_name' => $item->procedure_name,        // ANTES: $item->description (NULL)
    'item_description' => $item->procedure_description,
    'quantity' => $item->quantity,
    'unit_price' => $item->unit_cost,             // ANTES: $item->unit_price (NULL)
    'total_price' => $item->quantity * $item->unit_cost,
]);
```

---

### C-2. 8 rutas API apuntan a métodos inexistentes → 500

**Archivo**: `routes/api.php`
**Causa**: Refactor de nombres de métodos en controllers sin actualizar las rutas correspondientes.
**Impacto**: Cualquier request HTTP a estas 8 rutas retorna `500 Internal Server Error`.

| Ruta | Método en ruta | Método real | Controller |
|---|---|---|---|
| `POST /api/auth/register` | `register()` | **No existe** | AuthController |
| `POST /api/reminders/{id}/send` | `send()` | **No existe** | ReminderController |
| `POST /api/cash-register-sessions/open` | `openSession()` | `open()` | CashRegisterController |
| `POST /api/cash-register-sessions/close` | `closeSession()` | `close()` | CashRegisterController |
| `GET /api/cash-register-sessions/active` | `getActiveSession()` | `current()` | CashRegisterController |
| `GET /api/cash-reports/daily` | `dailyReport()` | `daily()` | CashReportController |
| `GET /api/cash-reports/period` | `periodReport()` | `period()` | CashReportController |
| `POST /api/pending-payments/{id}/pay` | `pay()` | **No existe** | PendingPaymentsController |

**Fix**:
```php
// routes/api.php — corregir referencias
Route::post('cash-register-sessions/open', [CashRegisterController::class, 'open']);
Route::post('cash-register-sessions/close', [CashRegisterController::class, 'close']);
Route::get('cash-register-sessions/active', [CashRegisterController::class, 'current']);
Route::get('cash-reports/daily', [CashReportController::class, 'daily']);
Route::get('cash-reports/period', [CashReportController::class, 'period']);
// register y pay: implementar el método o eliminar la ruta
```

---

### C-3. Patient y Appointment — campos multi-sede ausentes de $fillable

**Archivo**: `app/Models/Patient.php`, `app/Models/Appointment.php`
**Causa**: La migración `2025_10_24_202936_add_multi_sede_fields_to_existing_tables.php` agregó columnas que nunca se añadieron a `$fillable`.
**Impacto**: `Patient::create([...])` con `branch_id`, `dni`, `blood_type`, `insurance_provider`, `insurance_number` descarta silenciosamente esos valores. Lo mismo para `Appointment` con 11 campos nuevos.

**Patient — campos faltantes en $fillable**:
- `branch_id`, `dni`, `blood_type`, `insurance_provider`, `insurance_number`

**Appointment — campos faltantes en $fillable**:
- `branch_id`, `procedure_id`, `total_cost`, `paid_amount`, `balance`, `requires_payment`, `specialty`, `requires_anesthesia`, `treatment_plan_item_id`, `origin_appointment_id`, `last_activity_at`

**Fix**:
```php
// app/Models/Patient.php
protected $fillable = [
    'address', 'allergies', 'birth_date', 'document_number', 'email',
    'emergency_contact_name', 'emergency_contact_phone', 'first_name',
    'gender', 'is_active', 'last_name', 'medical_history', 'notes', 'phone',
    // ↓ AGREGAR
    'branch_id', 'dni', 'blood_type', 'insurance_provider', 'insurance_number',
];

// app/Models/Appointment.php
protected $fillable = [
    'appointment_type_id', 'checked_in_at', 'completed_at', 'consultation_mode',
    'created_by', 'dental_chair_id', 'duration_minutes', 'ends_at', 'final_amount',
    'idempotency_key', 'notes', 'patient_id', 'scheduled_at', 'status',
    'treatment_notes', 'treatment_plan_id', 'updated_by', 'user_id',
    // ↓ AGREGAR
    'branch_id', 'procedure_id', 'total_cost', 'paid_amount', 'balance',
    'requires_payment', 'specialty', 'requires_anesthesia',
    'treatment_plan_item_id', 'origin_appointment_id', 'last_activity_at',
];
```

---

### C-4. Multi-sede: 6 controllers sin filtrar por branch_id

**Archivo**: `PatientController.php`, `AppointmentController.php`, `MedicalRecordController.php`, `TransactionController.php`, `QuotationController.php`, `TreatmentPlanController.php`
**Causa**: La migración multi-sede agregó `branch_id` a las tablas, pero los controllers nunca filtran por él en sus métodos `index`.
**Impacto**: Un odontólogo de la sede A ve y edita pacientes, citas, historias clínicas y transacciones de la sede B. **Violación de aislamiento de datos multi-tenant.**

**Servicios también afectados**: `AppointmentService`, `CalendarService`, `QuotationService`, `TransactionService` — ninguno filtra por `branch_id`.

**Fix** (patrón base para cada controller):
```php
// En el método index() de cada controller
$query = Model::query();

// Agregar filtro multi-sede
$query->when(
    $request->user()->branch_id,
    fn($q, $branchId) => $q->where('branch_id', $branchId)
);

// Controllers que YA lo hacen correctamente (referencia):
// - CashRegisterController ✅
// - DashboardController ✅
```

---

## 🟠 IMPORTANTE — Inconsistencias que rompen convenciones o DX

### I-1. useAuth split-brain: 6 componentes importan de useApi.js, 6 de useAuth.js

**Archivo**: `resources/js/composables/useApi.js` (re-exporta `useAuth`), `resources/js/composables/useAuth.js` (canonical)
**Causa**: `useApi.js` exporta su propia versión de `useAuth` como "conveniencia". Cada import path crea una instancia separada del estado reactivo.
**Impacto**: Estado de autenticación divergente. Mitad de la app puede ver al usuario como logueado, la otra mitad no.

**Desde `useApi.js`** (6 sites):
- `MobileNavigation.vue`, `AppLayout.vue`, `LoginPage.vue`, `usePermissions.js`, `OpenCashModal.vue`, `DashboardPage.vue`

**Desde `useAuth.js`** (6 sites):
- `MedicalRecordsPage.vue`, `QuotationsPage.vue`, `QuotationCard.vue`, `SpecialtyRecordsPage.vue`, `TreatmentPlansPage.vue`, `TreatmentPlanCard.vue`

**Fix**: Eliminar `export function useAuth` de `useApi.js`. Migrar todos los imports a `@/composables/useAuth`.

---

### I-2. 10 FormRequests huérfanos — validación inline en controllers

**Archivo**: `app/Http/Requests/` (10 de 14 archivos nunca se type-hintean)
**Impacto**: Validación duplicada e inconsistente. Los FormRequests centralizan mensajes y autorización, pero los controllers hacen `$request->validate([...])` inline.

**Huérfanos**:
- `StoreAppointmentRequest.php`
- `StoreEvolutionRequest.php`
- `StoreInterconsultationRequest.php`
- `StoreMedicalRecordRequest.php`
- `StoreOdontogramRecordRequest.php`
- `StoreOdontogramRequest.php`
- `StoreQuotationRequest.php`
- `StoreSpecialtyRecordRequest.php`
- `StoreTreatmentPlanRequest.php`
- `UpdateAppointmentRequest.php`

**Fix**: Type-hintear los FormRequests en los controllers:
```php
// ANTES
public function store(Request $request) { ... }

// DESPUÉS
public function store(StoreAppointmentRequest $request) { ... }
```

---

### I-3. Rutas duplicadas: POST /login, /logout, /register definidas 2 veces

**Archivo**: `routes/api.php`
**Impacto**: Laravel usa la última definición silenciosamente. La versión fuera del grupo de middleware puede eludir throttle/login.

**Fix**: Eliminar las definiciones duplicadas fuera del grupo de middleware. Mantener solo las del grupo `Route::prefix('auth')`.

---

### I-4. RoleMiddleware existe pero está huérfano

**Archivo**: `app/Http/Middleware/RoleMiddleware.php` (906 chars, nunca referenciado)
**Causa**: `bootstrap/app.php` usa `CheckRole` como alias `role`. `RoleMiddleware` quedó del refactor.
**Impacto**: Confusión para desarrolladores que buscan el middleware de roles.

**Fix**: Eliminar `RoleMiddleware.php` o documentar que `CheckRole` es el activo.

---

### I-5. RequireActiveCashSession middleware nunca se usa en rutas

**Archivo**: `app/Http/Middleware/RequireActiveCashSession.php`
**Impacto**: No hay protección de ruta para operaciones de caja que requieran sesión activa. Cualquier usuario autenticado podría intentar crear transacciones sin caja abierta.

**Fix**: Registrar alias en `bootstrap/app.php` y aplicarlo a rutas de transacciones/movimientos de caja.

---

### I-6. 9 composables nunca importados — dead code

**Archivo**: `resources/js/composables/`
**Impacto**: Bundle size innecisario. Confusión para desarrolladores que creen que están disponibles.

| Composable | Exports muertos |
|---|---|
| `useAccessibility.js` | `useAccessibility` |
| `useApiWithLoading.js` | `useApiWithLoading` |
| `useExport.js` | `useExport` |
| `useInterconsultations.js` | `useInterconsultations` |
| `useLoading.js` | `useLoading` |
| `useOptionsTransform.js` | 8 funciones (`transformToOptions`, `transformProfessionals`, etc.) |
| `usePagination.js` | `usePagination` |
| `useValidation.js` | `useValidation` |
| `useZIndex.js` | `useZIndex` |

**Fix**: Auditar cada uno. Eliminar los truly dead, o wirearlos en los componentes que los necesitan.

---

### I-7. 110+ campos nullable sin sometimes en FormRequests

**Archivo**: `app/Http/Requests/` (13 de 14 archivos afectados)
**Impacto**: `nullable` permite `null` explícito pero NO permite omitir el campo. Si el frontend no envía el campo, falla validación con 422.

**Archivos más afectados**:
- `StoreSpecialtyRecordRequest.php` — 45+ campos
- `StoreEvolutionRequest.php` — 16 campos
- `StoreMedicalRecordRequest.php` — 14 campos
- `StoreQuotationRequest.php` — 10 campos

**Fix**: Prepend `sometimes|` antes de `nullable`:
```php
// ANTES
'notes' => 'nullable|string|max:1000',

// DESPUÉS
'notes' => 'sometimes|nullable|string|max:1000',
```

---

### I-8. 352 llamadas console.log/warn/error en código de producción

**Archivo**: `resources/js/` (distribuidos en múltiples componentes)
**Impacto**: Consola ensuciada, posibles leaks de datos sensibles en producción.

**Ejemplos de alto riesgo**:
- `PatientSelector.vue:L187`: `console.log('Pacientes cargados:', response.data)` — expone datos de pacientes
- `NewAppointmentModal.vue:L426-448`: múltiples `console.log` de eventos WebSocket

**Fix**: Eliminar todos los `console.log` de producción. Mantener solo `console.error` en catch blocks con `import.meta.env.DEV` check.

---

## 🟡 MEJORAS — Pulido y robustez

### M-1. 13 modelos clínicos/financieros sin SoftDeletes

**Modelos afectados**: `Patient`, `MedicalRecord`, `ClinicalEvolution`, `ClinicalAttachment`, `Quotation`, `Transaction`, `PaymentMethod`, `TreatmentPlan`, `Odontogram`, `Appointment`, `Interconsultation`, `CashRegisterSession`, `CashMovement`

**Impacto**: `DELETE` borra permanentemente datos clínicos y financieros. Sin auditoría de eliminación. Potencial violación de regulaciones de retención de historias clínicas.

**Prioridad**: Empezar con `Patient`, `Transaction`, `Appointment`, `MedicalRecord`.

**Fix**:
```php
// 1. Migración
Schema::table('patients', fn($t) => $t->softDeletes());

// 2. Modelo
use Illuminate\Database\Eloquent\SoftDeletes;
class Patient extends Model { use SoftDeletes; }
```

---

### M-2. 4 servicios disparan eventos sin try/catch dentro de transacciones DB

**Servicios afectados**:
- `BillingService.php` — dispara `QuotationCreated`
- `ConsultationService.php` — dispara `AppointmentCompleted`
- `QuotationService.php` — dispara `QuotationCreated`, `QuotationUpdated`, `QuotationApproved`
- `TreatmentPlanService.php` — dispara `TreatmentPlanCreated`, `TreatmentPlanUpdated`

**Impacto**: Si Reverb/WebSocket está caído, `event()` lanza excepción y **revierte la transacción DB** — datos de negocio perdidos por una notificación fallida.

**Servicios que YA lo hacen bien** (referencia): `CashRegisterService`, `ReminderService`, `TransactionService`, `WaitingListService`

**Fix**:
```php
DB::transaction(function () {
    // ... lógica de negocio
    
    try {
        event(new QuotationCreated($quotation));
    } catch (\Exception $e) {
        Log::warning('No se pudo emitir evento QuotationCreated', [
            'quotation_id' => $quotation->id,
            'error' => $e->getMessage(),
        ]);
    }
});
```

---

### M-3. Ratio tests/servicios: 6/17 = 0.35 — cobertura insuficiente

**Archivo**: `tests/` (6 archivos de test vs 17 servicios, 44 modelos)
**Impacto**: Los paths de dinero (caja, transacciones, presupuestos) no tienen tests.

**Recomendación**: Priorizar tests para:
1. `CashRegisterService` (apertura/cierre/arqueos)
2. `TransactionService` (pagos, descuentos)
3. `QuotationService` (generación — especialmente después del fix C-1)
4. `AppointmentService` (creación con validación de conflictos)

---

### M-4. Imports mixtos: 119 @/ alias vs 146 relativos ../

**Impacto**: Inconsistencia que dificulta refactorizaciones. Algunos componentes usan `@/composables/useAuth`, otros `../../composables/useAuth`.

**Fix**: Estandarizar a `@/` alias (ya configurado en `vite.config.js`). ESLint rule:
```js
// .eslintrc.cjs
rules: {
  'no-restricted-imports': ['error', {
    patterns: [{ group: ['../*', './*'], message: 'Usa @/ alias en lugar de imports relativos.' }]
  }]
}
```

---

### M-5. 2 eventos definidos pero nunca disparados

**Eventos**: `AppointmentCheckedIn`, `DashboardStatsUpdated`
**Archivo**: `app/Events/`
**Impacto**: Dead code.

**Fix**: Disparar `AppointmentCheckedIn` desde `ConsultationController::checkIn()` o eliminar el evento.

---

### M-6. Vista blade sigue llamándose "EasyDent"

**Archivo**: `resources/views/app.blade.php`
**Impacto**: Mismatch de branding con el nombre "OdontoSuite".

**Fix**: Cambiar el título y meta tags de "EasyDent" a "OdontoSuite".

---

## ✅ Lo que YA está bien (no tocar)

- **Auth**: Login/logout/me/refresh/forgot/reset completo con Sanctum + rate limit + `is_active` check
- **Password hidden**: `User` tiene `password` en `$fillable` Y en `$hidden` — no expone hashes
- **Multi-rol**: `CheckRole` middleware correctamente aliasado y usado en 22 apiResources
- **Exception handling**: `bootstrap/app.php` maneja 422/404/401/403 con JSON consistente
- **BillingService**: Mapea correctamente `unit_cost → unit_price` y `total_cost → total_price`
- **AuditLog**: Modelo actualizado post-`renameColumn` con morphs correctos
- **Encoding**: Sin artefactos en archivos PHP (UTF-8 correcto)
- **Eventos**: 32 de 34 eventos sí se dispatchan correctamente
- **CashRegisterService y TransactionService**: Filtran por `branch_id` y tienen try/catch en eventos

---

## 🗺️ Sprints sugeridos (orden de ataque por impacto visible)

| Sprint | Foco | Ítems | Tiempo est. |
|---|---|---|---|
| **0 — Hoy** | Bugs que rompen features visibles | C-1, C-2 | 30 min |
| **A — 1 día** | Seguridad de datos multi-sede | C-3, C-4, I-3 | 2-3 h |
| **B — 1-2 días** | Consistencia frontend | I-1, I-6, I-8, M-4 | 3-4 h |
| **C — 1 semana** | Robustez backend | M-1, M-2, I-2, I-7 | 1 día |
| **D — Capstone** | Calidad y polish | M-3, I-4, I-5, M-5, M-6 | 2-3 días |

---

## 🎯 Recomendación inmediata

**Arreglar C-1 y C-2 primero** — son 2 fixes de ~10 líneas cada uno que corrigen:
1. Presupuestos mostrando S/ 0.00 (C-1)
2. 8 rutas retornando 500 (C-2)

Son los de mayor impacto visible con el menor esfuerzo.
