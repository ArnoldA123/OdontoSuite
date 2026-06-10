
# Análisis de inconsistencias y mejoras — OdontoSuite V2

> **Alcance**: Análisis transversal backend (75 migrations, 44 modelos, 32 controladores, 17 servicios, 34 eventos) + frontend (15 módulos, 28 composables, 30 UI primitives) + routes/api.php (376 líneas).  
> **Doc de referencia**: `AGENTS.md` (cross-referenciado — no se duplican bugs ya documentados).

---

## 🔴 CRÍTICO — Rompe features o corrompe datos

### C-1. Ocho (8) rutas apuntan a métodos que NO existen → 500 en runtime

**Archivo**: `routes/api.php` (L53-L374)  
**Causa**: Las rutas referencian métodos con nombres que no coinciden con los métodos reales de los controladores. Son rutas activas — Laravel las compila sin error pero fallan con 500 al ser invocadas.  
**Impacto**: Features rotas en producción si el frontend las llama. Afecta auth (register), recordatorios (send), caja (openSession/closeSession/getActiveSession), reportes de caja (dailyReport/periodReport), y pagos pendientes (pay).

| Ruta | Método en route | Método real en controller |
|---|---|---|
| `POST /register` | `AuthController::register()` | **NO EXISTE** |
| `POST reminders/{id}/send` | `ReminderController::send()` | **NO EXISTE** (service tiene `sendReminder`) |
| `POST cash-register-sessions/{id}/open` | `CashRegisterController::openSession()` | `open()` |
| `POST cash-register-sessions/{id}/close` | `CashRegisterController::closeSession()` | `close()` |
| `GET cash-register-sessions/active` | `CashRegisterController::getActiveSession()` | `current()` |
| `GET cash-reports/daily` | `CashReportController::dailyReport()` | `daily()` |
| `GET cash-reports/period` | `CashReportController::periodReport()` | `period()` |
| `POST pending-payments/{id}/pay` | `PendingPaymentsController::pay()` | **NO EXISTE** |

**Fix**:
```php
// Opción A: Renombrar métodos en los controladores para que coincidan con las rutas
// CashRegisterController: openSession() → open(), closeSession() → close(), getActiveSession() → current()
// CashReportController: dailyReport() → daily(), periodReport() → period()
// Agregar método send() en ReminderController que delegue a ReminderService::sendReminder()
// Agregar método pay() en PendingPaymentsController
// Eliminar ruta POST /register o implementar AuthController::register()

// Opción B (más rápida): Corregir las rutas para que apunten a los métodos existentes
Route::post('cash-register-sessions/{id}/open', [CashRegisterController::class, 'open']);
Route::post('cash-register-sessions/{id}/close', [CashRegisterController::class, 'close']);
Route::get('cash-register-sessions/active', [CashRegisterController::class, 'current']);
Route::get('cash-reports/daily', [CashReportController::class, 'daily']);
Route::get('cash-reports/period', [CashReportController::class, 'period']);
```

---

### C-2. Split-brain de `useAuth`: 6 componentes importan de `useApi.js` y 6 de `useAuth.js`

**Archivo**: `resources/js/composables/useApi.js` (L? — export function useAuth) + `resources/js/composables/useAuth.js` (canónico)  
**Causa**: `useApi.js` define su propio `useAuth()` con estado independiente. 6 componentes importan de `useApi.js` y otros 6 de `useAuth.js` canónico. Cada path de import crea una instancia separada de los `ref()` a nivel de módulo → el estado (token, user, isAuthenticated) diverge entre ambos grupos.  
**Impacto**: La mitad de la app puede ver al usuario como no autenticado mientras la otra mitad lo ve como autenticado. Comportamiento impredecible en `AppLayout.vue` (importa de `useApi.js`), `LoginPage.vue` (importa de `useApi.js`), `MedicalRecordsPage.vue` (importa de `useAuth.js`), etc.

**Sitios de import**:
- **Desde `useApi.js`**: `MobileNavigation.vue`, `AppLayout.vue`, `usePermissions.js`, `LoginPage.vue`, `OpenCashModal.vue` (+1 más)
- **Desde `useAuth.js`**: `MedicalRecordsPage.vue`, `QuotationsPage.vue`, `QuotationCard.vue`, `SpecialtyRecordsPage.vue`, `TreatmentPlansPage.vue` (+1 más)

**Fix**:
```javascript
// 1. En useApi.js: ELIMINAR export function useAuth() { ... }
// 2. En todos los componentes que importan de useApi.js, cambiar a:
import { useAuth } from '@/composables/useAuth'
// 3. Mantener useAuth.js como única fuente canónica
```

---

### C-3. Campos multi-sede en `patients` no están en `$fillable` → mass assignment los descarta silenciosamente

**Archivo**: `app/Models/Patient.php` (L? — $fillable) + `database/migrations/2025_10_24_202936_add_multi_sede_fields_to_existing_tables.php`  
**Causa**: La migración multi-sede agrega `branch_id`, `dni`, `blood_type`, `insurance_provider`, `insurance_number` a la tabla `patients`, pero `Patient::$fillable` solo tiene 14 campos y **ninguno de estos 5**.  
**Impacto**: `Patient::create([...])` o `$patient->update([...])` descartan estos campos sin error. Los pacientes se crean sin DNI, tipo de sangre, sede ni seguro. Datos clínicos y administrativos se pierden silenciosamente.

**Fix**:
```php
// En app/Models/Patient.php
protected $fillable = [
    // ... campos existentes
    'branch_id',
    'dni',
    'blood_type',
    'insurance_provider',
    'insurance_number',
];
```

---

### C-4. Seis (6) controladores clave sin filtro `branch_id` → fuga de datos entre sedes

**Archivo**: `app/Http/Controllers/Api/{Patient,Appointment,MedicalRecord,Quotation,Transaction,TreatmentPlan}Controller.php`  
**Causa**: La migración multi-sede agregó `branch_id` a las tablas, pero ninguno de los 6 controladores principales filtra por `branch_id`. Cualquier usuario autenticado ve TODOS los registros de TODAS las sedes.  
**Impacto**: Un recepcionista de la Sede A ve pacientes, citas, HC, presupuestos y transacciones de la Sede B. Ruptura total del modelo multi-tenant.

**Fix**:
```php
// En cada controller, en los métodos index:
$query->when(
    $request->user()->branch_id,
    fn($q, $branchId) => $q->where('branch_id', $branchId)
);
```

---

### C-5. Tres rutas duplicadas → la segunda definición pisa a la primera silenciosamente

**Archivo**: `routes/api.php`  
**Causa**: `POST /login` (L53 y L60), `POST /register` (L55 y L62), `POST /logout` (L71 y L179) están definidas 2 veces cada una. Laravel usa la última definición.  
**Impacto**: La primera definición (L53-55) está fuera de cualquier grupo de middleware con rate-limit. Si esa es la que se pisa, el rate-limit configurado podría no aplicarse. En el caso de `/register`, la ruta existe pero el método no → siempre 500.

**Fix**: Eliminar las definiciones duplicadas. Conservar solo una por ruta, dentro del grupo correcto.
```php
// Eliminar L53-55 (sin middleware de rate-limit) y L179
// Conservar L60-71 (con throttle.login:3,1 y prefix auth — si aplica)
```

---

### C-6. Middleware alias drift: `RoleMiddleware.php` existe pero `bootstrap/app.php` aliasa `CheckRole`

**Archivo**: `bootstrap/app.php` + `app/Http/Middleware/RoleMiddleware.php` + `app/Http/Middleware/CheckRole.php`  
**Causa**: `bootstrap/app.php` aliasa `'role' => CheckRole::class`, pero también existe `RoleMiddleware.php`. El `AGENTS.md` dice que se usa `RoleMiddleware`. Si algún código o ruta hace referencia a `RoleMiddleware` directamente (sin el alias), no se aplica el middleware correcto.  
**Impacto**: Confusión entre 2 middlewares de rol. Si `CheckRole` y `RoleMiddleware` tienen comportamientos distintos (ej: response shape diferente para 403), las rutas que usan el alias vs las que usan la clase directa tendrían comportamientos inconsistentes.

**Fix**:
```php
// Opción A: Consolidar en uno solo. Eliminar RoleMiddleware.php y
// renombrar CheckRole → RoleMiddleware, actualizando bootstrap/app.php.
// Opción B: Verificar que ambos tengan exactamente el mismo comportamiento
// de respuesta 403 y documentar cuál es el canónico.
```

---

## 🟠 IMPORTANTE — Inconsistencias que rompen convenciones o DX

### I-1. Catorce (14) modelos clínicos/financieros sin SoftDeletes

**Archivo**: `app/Models/` — Patient, Appointment, MedicalRecord, ClinicalEvolution, ClinicalAttachment, Quotation, Transaction, PaymentPlan, Installment, TreatmentPlan, Odontogram, CashRegisterSession, CashMovement, Interconsultation  
**Causa**: Ninguno usa `SoftDeletes`. Un `DELETE` accidental elimina permanentemente datos clínicos, financieros o legales.  
**Impacto**: Sin trazabilidad de borrados. En clínica real, perder una historia clínica o una transacción es grave.

**Fix**:
```php
// 1. Agregar columna deleted_at a cada tabla:
Schema::table('patients', fn($t) => $t->softDeletes());
// (repetir para las otras 13 tablas)

// 2. En cada modelo:
use Illuminate\Database\Eloquent\SoftDeletes;
class Patient extends Model { use SoftDeletes; }
```

---

### I-2. Dos (2) eventos huérfanos — definidos pero nunca disparados

**Archivo**: `app/Events/AppointmentCheckedIn.php`, `app/Events/DashboardStatsUpdated.php`  
**Causa**: Los eventos existen como clases pero ningún servicio los dispara (`event(new ...)` o `::dispatch()`).  
**Impacto**: Código muerto. Si se planificó funcionalidad de check-in que notifique o dashboard que se actualice por eventos, nunca se ejecuta.

**Fix**: Disparar `AppointmentCheckedIn` desde `ConsultationService::checkIn()`. Disparar `DashboardStatsUpdated` desde `DashboardService` después de cada actualización de caché. O eliminar los eventos si no se van a usar.

---

### I-3. Diez (10) de 14 FormRequests NUNCA usados por ningún controlador

**Archivo**: `app/Http/Requests/{StoreAppointmentRequest, StoreEvolutionRequest, StoreInterconsultationRequest, StoreMedicalRecordRequest, StoreOdontogramRecordRequest, StoreOdontogramRequest, StoreQuotationRequest, StoreSpecialtyRecordRequest, StoreTreatmentPlanRequest, UpdateAppointmentRequest}.php`  
**Causa**: Los FormRequests existen pero los controladores validan inline con `$request->validate(...)`. Las clases están huérfanas.  
**Impacto**: Código muerto. Validación descentralizada (cada controller repite reglas). Si se cambia una regla, hay que buscar en todos los controllers.

**Fix**:
```php
// Opción A: Usar los FormRequests en los controllers (type-hint en el método)
public function store(StoreAppointmentRequest $request) { ... }

// Opción B: Eliminar los FormRequests no usados
```

---

### I-4. Cuatro (4) servicios con `broadcast()` sin `try/catch` → rollback de transacción si WebSocket cae

**Archivo**: `app/Services/{BillingService, ConsultationService, QuotationService, TreatmentPlanService}.php`  
**Causa**: Los servicios disparan `event(new ...)` o `broadcast(new ...)` sin `try/catch`. Si Reverb está caído, el broadcast lanza excepción → si está dentro de un `DB::transaction`, revierte toda la operación de negocio.  
**Impacto**: Una transacción financiera o una historia clínica se pierde porque la notificación WebSocket falló.

**Fix**:
```php
DB::transaction(function () use ($data) {
    // ... lógica de negocio
    try {
        broadcast(new QuotationApproved($quotation));
    } catch (\Exception $e) {
        Log::warning('Broadcast falló: ' . $e->getMessage());
    }
});
```

---

### I-5. Siete (7) seeders legacy con dominio `@easydent.com` — riesgo de ejecución accidental

**Archivo**: `database/seeders/{AdminUserSeeder, DentistUserSeeder, ReceptionUserSeeder, EssentialDataSeeder, EssentialUsersSeeder, ProfessionalSeeder, TestUserSeeder}.php`  
**Causa**: Seeders antiguos con credenciales `@easydent.com` y roles desfasados (`admin`, `recepcion`). El `AGENTS.md` los marca como "NO usar" pero siguen en el código.  
**Impacto**: Si alguien ejecuta `php artisan db:seed --class=AdminUserSeeder`, se crean usuarios con dominio y roles incorrectos.

**Fix**: Mover a `database/seeders/legacy/` o eliminar. Agregar un comentario `@deprecated` en cada uno.

---

### I-6. `POST /register` definido 2 veces pero método `AuthController::register()` NO existe

**Archivo**: `routes/api.php` (L55, L62) + `app/Http/Controllers/Api/AuthController.php`  
**Causa**: La ruta existe (y está duplicada) pero el controller no tiene método `register`. Autenticación espera que el admin cree usuarios, no self-registration — pero la ruta persiste.  
**Impacto**: 500 si alguien intenta `POST /api/register`. Además, confunde a desarrolladores nuevos.

**Fix**: Si no se necesita self-registration, eliminar ambas definiciones de `POST /register`. Si se necesita, implementar `AuthController::register()`.

---

## 🟡 MEJORAS — Pulido y robustez

### M-1. 353 llamadas a `console.log/warn/error` en frontend de producción

**Archivo**: 30+ archivos Vue/JS en `resources/js/`  
**Top offenders**: `BusinessIntelligencePage.vue` (55), `useCashRegister.js` (29), `CashRegisterPage.vue` (24), `DashboardPage.vue` (19), `useEcho.js` (18)  
**Fix**: Agregar un wrapper condicional (`if (import.meta.env.DEV)`) o usar un logger centralizado que se desactive en producción.

---

### M-2. Cobertura de tests: 24% (30 tests para 124 métodos de servicio)

**Archivo**: `tests/` (7 archivos PHP, 30 test methods) vs `app/Services/` (17 archivos, 124 métodos públicos)  
**Impacto**: Refactors sin red de seguridad. Los paths de dinero (TransactionService, CashRegisterService) no tienen tests.  
**Fix**: Priorizar tests para `TransactionService`, `CashRegisterService`, `QuotationService` y `BillingService` (los paths financieros).

---

### M-3. Vite resolve alias no incluye `@` → imports mixtos (`@/composables/` vs `../../composables/`)

**Archivo**: `vite.config.js`  
**Causa**: El alias `@` no está configurado en Vite, pero varios componentes lo usan igual. Funciona por ahora (Vite lo resuelve de forma laxa), pero es frágil.  
**Fix**:
```js
// vite.config.js
resolve: {
    alias: {
        '@': '/resources/js',
        'vue': 'vue/dist/vue.esm-bundler.js'
    }
}
```

---

### M-4. Vista blade todavía se llama "EasyDent"

**Archivo**: `resources/views/app.blade.php`  
**Causa**: Documentado en `AGENTS.md` como "refactor pendiente".  
**Fix**: Cambiar `<title>EasyDent</title>` → `<title>OdontoSuite</title>`.

---

### M-5. Validaciones `nullable` sin `sometimes` en FormRequests

**Archivo**: Varios `app/Http/Requests/*.php`  
**Causa**: Campos con `'nullable|integer|exists:...'` requieren que el campo esté presente (aunque sea null). Si el frontend omite el campo, Laravel falla la validación. Agregar `sometimes|` permite omitirlo.  
**Fix**: `'appointment_id' => 'sometimes|nullable|integer|exists:...'`

---

## ✅ Lo que YA está bien (no tocar)

- **Password en `$hidden`**: `User.php` correctamente oculta `password` y `remember_token` — sin riesgo de exposición en JSON.
- **34 eventos definidos, 33 efectivamente disparados** — solo 2 huérfanos. La arquitectura de eventos está madura.
- **Auth completo**: Sanctum bearer tokens + rate-limit + `is_active` check + forgot/reset password — bien implementado.
- **Estructura de servicios**: Lógica de negocio separada de controladores (17 services + 7 de reportes). Buena arquitectura.
- **75 migraciones ordenadas**: Esquema bien versionado. Las migraciones `add_*` están correctamente secuenciadas.
- **14 FormRequests**: Bien tipados con `authorize()` y `rules()`. Solo falta usarlos.

---

## 🗺️ Sprints sugeridos (orden de ataque)

| Sprint | Foco | Ítems | Esfuerzo |
|---|---|---|---|
| **A** (hoy) | Bugs que rompen features | C-1 (8 rutas rotas), C-3 (Patient $fillable), C-5 (rutas duplicadas) | 2-3 horas |
| **B** (mañana) | Split-brain + multi-tenant | C-2 (useAuth), C-4 (branch_id scoping) | 3-4 horas |
| **C** (esta semana) | Middleware + broadcasts | C-6 (middleware drift), I-4 (broadcasts try/catch) | 2 horas |
| **D** (siguiente) | Deuda técnica | I-1 (SoftDeletes), I-2 (eventos huérfanos), I-3 (FormRequests), I-5 (seeders legacy) | 4-5 horas |
| **E** (pulido) | Mejoras | M-1 (console.log), M-2 (tests), M-3 (Vite alias), M-4 (EasyDent naming) | 5-8 horas |

---

## 🎯 Recomendación inmediata

**Arrancar con el Sprint A**: corregir las 8 rutas rotas (C-1) + agregar los 5 campos multi-sede al `$fillable` de Patient (C-3) + eliminar las 3 rutas duplicadas (C-5). Son fixes pequeños (< 20 líneas cada uno) con el mayor impacto: features que hoy devuelven 500 pasan a funcionar, y los datos de pacientes dejan de perderse silenciosamente.
