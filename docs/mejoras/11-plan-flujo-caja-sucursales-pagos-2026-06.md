# Plan #11 — Flujo de caja: sucursales personalizables, métodos de pago y pasarela Mercado Pago (junio 2026)

> **Fecha**: 2026-06-13
> **Origen**: feedback de Arnold al abrir caja y ver el dropdown de sucursales vacío ("debo seleccionar alguna sucursal y no aparece ninguna"). El sistema completo (migrations, modelos, controllers, rutas, modal `OpenCashModal`) ya existe, pero **faltan 3 piezas críticas** que se diagnosticaron al validar el flujo en Chrome:
> 1. Las tablas `branches` y `payment_methods` están vacías porque **no existen seeders** que las pueblen.
> 2. **No hay UI de administración** para que el rol `administrador` agregue/edite/desactive sucursales o métodos de pago desde la app (todo es por BD/seeder).
> 3. **No hay pasarela de pago** real para tarjeta o Mercado Pago. El sistema solo soporta "registrar cobro" con un método de pago manual.
>
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno. Independiente de los planes #1-#10. **NO** asume cambios en módulos clínicos (citas, presupuestos, planes de tratamiento); solo toca el módulo `cash-register` y agrega un nuevo módulo `settings/branches` + `settings/payment-methods` + `payments/gateways`.
> **Convención del proyecto**: español, prefijos semánticos en commits, pnpm siempre, tests donde aporten valor, sin emojis. Este plan NO toca el cálculo de comisiones ni el flujo de cierre de caja (ya cubierto por el plan #2 / `CashRegisterService`).

---

## 1. Contexto y oportunidad

Arnold abrió `/cash-register` y clickeó "Abrir Caja" para validar el flujo. El modal `OpenCashModal` carga `branches` desde `GET /api/branches` (BranchController::index) y los pinta en un `<select>`. Como la tabla `branches` está vacía (no hay `BranchSeeder` en `database/seeders/`), el dropdown solo muestra la opción placeholder "Seleccionar sucursal" — no hay nada para elegir. El mismo problema ocurre con el dropdown de métodos de pago en el flujo de cobro (PaymentModal). El sistema no se rompe: la validación `exists:branches,id` en `OpenCashRegisterRequest` rechaza el POST, pero la UX no le dice al usuario **por qué** el dropdown está vacío.

### 1.1 Tres problemas reportados

| # | Severidad | Síntoma | Causa raíz verificada |
|---|---|---|---|
| B-CASH-1 | 🔴 Funcional | Dropdown "Sucursal" en "Abrir Caja" sale vacío | `BranchSeeder` no existe en `database/seeders/`. Verificado con `ls database/seeders/`: solo hay 10 seeders, ninguno para `branches` ni `payment_methods`. La tabla está vacía tras `migrate:fresh --seed`. |
| B-CASH-2 | 🟠 Funcional | Dropdown "Método de Pago" en "Registrar Cobro" sale vacío | `PaymentMethodSeeder` no existe. Misma causa raíz que B-CASH-1. |
| B-CASH-3 | 🟠 UX/UI | No hay forma de agregar/editar sucursales o métodos de pago desde la app (solo el admin podría hacerlo por BD) | No existen vistas Vue ni composables para CRUD de `branches` o `payment_methods`. El admin depende de `php artisan tinker` o un INSERT manual. |
| B-CASH-4 | 🟠 Feature | No hay pasarela de pago real (Mercado Pago / tarjeta) | El sistema solo soporta cobro manual. `config/services.php` no tiene bloque `mercadopago`. `composer.json` no requiere `mercadopago/dx-php`. |
| B-CASH-5 | 🟡 UX | Modal `OpenCashModal` no da feedback de "no hay sucursales" — solo muestra un `<select>` vacío | `OpenCashModal.vue:179-185`: `loadBranches()` silencia el error (`catch {}` vacío) y deja `branches.value = []` sin UI empty state. |
| B-CASH-6 | 🟡 UX | Dropdown "Sucursal" usa `<select>` HTML nativo — no busca/autocompleta, no muestra el código de la sede ni permite distinguir 5+ sedes a simple vista | Mismo patrón en `OpenCashModal.vue:15-28` y `PaymentModal` (dropdown de métodos de pago). Diseño inconsistente con `Select.vue` UI component que ya existe en el design system. |

### 1.2 Verificación directa (yo mismo, sin BD poblada)

```bash
# Confirmar que no hay seeders de branches ni payment_methods
ls "database/seeders/" | grep -iE "branch|sucursal|payment"
# → (sin matches)

# Confirmar que las tablas existen pero están vacías
php artisan tinker --execute="echo \App\Models\Branch::count().' branches, '.\App\Models\PaymentMethod::count().' payment methods';"
# → "0 branches, 0 payment methods"

# Confirmar que las rutas existen y devuelven []
curl -X GET http://127.0.0.1:8000/api/branches -H "Authorization: Bearer $TOKEN"
# → {"success":true,"data":[]}

curl -X GET http://127.0.0.1:8000/api/payment-methods -H "Authorization: Bearer $TOKEN"
# → {"success":true,"data":[]}
```

### 1.3 Decisiones de diseño (las contesto de antemano, no necesito que las revises)

**Pregunta del usuario: "métodos de pago: ¿personalizable sí o no?"**

**Respuesta: SÍ, personalizable, con un set por defecto no-borrable.** Razón:
- Cada clínica peruana tiene su mix: algunas aceptan Yape/Plin, otras solo efectivo+tarjeta, otras agregan transferencias BCP/Interbank. Hardcodear "Efectivo/Tarjeta/Transferencia" en código es regresivo.
- La columna `code` (unique) en `payment_methods` ya está pensada como slug estable (`cash`, `credit_card`, `mercadopago`, `yape`, etc.) — el código depende de los códigos, no de los IDs. Eso permite referenciar métodos por código en lógica de negocio (ej: "comisión = 0% para `cash`, 3.5% para `credit_card`") sin que un rename del admin rompa nada.
- El flag `is_active` (boolean) ya existe. Bajar la flag es preferible a `DELETE` (que es destructivo y rompe auditoría de transacciones viejas).
- Para los métodos del sistema (`cash`, `transfer`, `credit_card`) pongo `is_system = true` en una nueva columna. Eso permite: (a) que el admin los desactive pero no los borre, (b) que el frontend no muestre el botón "Eliminar" para los del sistema. La transacción vieja que apuntaba a "cash" sigue apuntando a "cash" aunque el admin lo haya renombrado a "Efectivo (S/.)".

**Pregunta del usuario: "¿qué necesito hacer manual para Mercado Pago?"**

**Respuesta: TODO lo que requiere ir a mercadopago.com lo hace el usuario, lo demás lo hace el agente.**

1. **El usuario crea una cuenta Mercado Pago** (5 min) → https://www.mercadopago.com.pe
2. **El usuario crea una aplicación** en el panel de developers (3 min) → "Tus integraciones" → "Crear aplicación" → tipo "Checkout Pro" o "Checkout Bricks" (Bricks es más moderno y granular).
3. **El usuario copia 2 pares de credenciales** del panel:
   - **TEST** (sandbox): `TEST-XXXXXXXX-XXXXXX-XXXXXX-XXXXXXXXXXXX` (Access Token público) + `TEST-XXXXXXXX-XXXXXX-XXXXXX-XXXXXXXXXXXX` (Public Key).
   - **PROD** (productivo, con cuenta validada): `APP_USR-XXXXXXXX` + `APP_USR-XXXXXXXX`.
4. **El usuario configura la URL de webhook** en el panel: "Webhooks > Configurar notificaciones" → URL = `https://tu-dominio.com/api/payments/webhooks/mercadopago` (en dev local: ngrok).
5. **El usuario pega las credenciales en `.env`** (5 min) — el agente le da el template exacto.
6. **El agente instala `composer require mercadopago/dx-php`**, crea el service, el controller, la migración de `payment_gateway_transactions`, los composables Vue, y migra el modal de cobro para que el botón "Cobrar con Mercado Pago" abra el Checkout Bricks embebido.

**No hay que tocar HTML del checkout**. Mercado Pago provee `CardPaymentBrick` (un componente JS) que se monta en un `<div>` y se inicializa con un `Preference` o `Payment` (creado server-side). El usuario nunca abandona el sistema.

**Sobre pasarela de tarjeta directa (sin MP)**: NO recomendada para este sprint. Razones:
- PCI-DSS: integrar tarjeta directa exige certificación o usar un proveedor tokenizador (Stripe, Izipay, Niubiz) que ya hace eso por vos. MP es el atajo.
- La realidad peruana: el 80% de pagos online van por Yape/Plin (QR MP) o tarjeta vía Checkout Pro. "Tarjeta directa" sin intermediario es feature enterprise que no se justifica en capstone.
- Si el usuario quiere tarjeta directa, lo agregamos como Sprint 5 (Izipay o Niubiz) — son 1 sprint extra.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Seeders mínimos + UI empty state en modales | 0.3 d-h | ✅ HECHO 2026-06-13 | 2 seeders + 1 migration + 2 modales |
| 1 | Módulo `settings/branches` (CRUD admin) | 1.5 d-h | ✅ HECHO 2026-06-13 (en verificacion visual) | 8 archivos nuevos (1 composable, 2 pages, 1 modal, 1 router, 1 menu, 1 nav, 1 store), 1 modified (apiResource) |
| 2 | Módulo `settings/payment-methods` (CRUD admin) | 1.5 d-h | ✅ HECHO 2026-06-13 (~1.3 d-h) | 5 archivos nuevos + 5 modificados (migration, model, controller, composable, page, modal, route, sidebar) |
| 3 | Pasarela Mercado Pago (backend + frontend Bricks) | 3 d-h | ⏳ Pendiente | SDK install, 3 migrations, 2 controllers, 1 webhook, 2 composables, 1 modal "Cobrar con MP" |
| 4 | Polish UX: reemplazar `<select>` nativos por `Select.vue`, empty states, validaciones inline, loading skeletons | 0.7 d-h | ⏳ Pendiente | 5 modales migrados, design system consistency |
| **Total** | **5 sprints** | **~7 d-h** | **~2.8 d-h ejecutados** | 4 bugs cerrados, 1 feature de pago real, 2 admin modules |

**Esfuerzo neto estimado: 1 sesión larga o 2 sesiones cortas (3-4 h cada una).**

---

## 3. Hallazgos verificados al 2026-06-13

### 🔴 B-CASH-1 — No hay `BranchSeeder`, tabla `branches` vacía

**Evidencia** (`ls database/seeders/`):
```
AppointmentTypeSeeder.php          ProcedureCatalogSeeder.php
CashRegisterSeeder.php             ReminderSchedulesSeeder.php
CompletedAppointmentsSeeder.php    RoleBasedUsersSeeder.php
DatabaseSeeder.php                 SimpleAppointmentsSeeder.php
PatientSeeder.php                  SpecialtyRecordSeeder.php
                                   SpecialtySeeder.php
```

**Causa raíz**: la migración `2025_10_24_201959_create_branches_table.php` crea la tabla con 13 columnas (`name`, `code`, `address`, `city`, `timezone`, `latitude`, `longitude`, `settings`, `is_active`, etc.), pero ningún seeder la puebla. `DatabaseSeeder.php` (verificado línea 16-41) llama 10 seeders — ninguno toca `branches`.

**Impacto verificado**:
- `OpenCashModal.vue:179-185` llama `GET /api/branches` → recibe `[]` → renderiza `<select>` con solo "Seleccionar sucursal".
- `OpenCashRegisterRequest.php:39-43` valida `branch_id` con `exists:branches,id` → al enviar el form sin elegir nada, devuelve 422 "branch_id is required" (que en la UI sale como "La sucursal es requerida", OK).
- Si el admin intenta abrir caja sin haber cargado sucursales por `tinker`, **no puede**. Esto bloquea el flujo entero de caja.

**Fix mínimo (Sprint 0)**: crear `BranchSeeder` con 3 sedes (Sede Central Lima, Sede Norte, Sede Sur) usando `updateOrCreate` por `code` para idempotencia. Así `migrate:fresh --seed` deja las 3 listas.

**Fix definitivo (Sprint 1)**: UI CRUD admin que reemplace el seeder manual. El seeder sigue siendo útil para demo (deja datos al instalar fresh), pero el CRUD es la vía real.

---

### 🟠 B-CASH-2 — No hay `PaymentMethodSeeder`, tabla `payment_methods` vacía

**Evidencia**: misma verificación que B-CASH-1, pero para `payment_methods`. La migración `2025_10_24_202521_create_payment_methods_table.php` tiene 8 columnas (`name`, `code`, `requires_authorization`, `allows_change`, `commission_percentage`, etc.), modelo `PaymentMethod` con `$fillable` completo, controller completo, ruta `apiResource` en `routes/api.php:354` — pero la tabla está vacía.

**Impacto verificado**:
- `useCashRegister.js:297-310` llama `GET /api/payment-methods` → recibe `[]`.
- `PaymentModal` (donde el usuario registra un cobro) usa este endpoint para llenar el dropdown de método de pago → vacío.
- Si el usuario intenta cobrar, no puede elegir método → falla silenciosa o el form rechaza submit.

**Fix mínimo (Sprint 0)**: `PaymentMethodSeeder` con 5 métodos del sistema (`cash`, `transfer`, `credit_card`, `debit_card`, `yape`) marcados con `is_system = true` (columna nueva a agregar). `updateOrCreate` por `code` para idempotencia.

**Decisión de schema**: agregar columna `is_system` (boolean default false) en migration nueva. Migración aditiva (no toca la original):
```php
// database/migrations/2026_06_13_120000_add_is_system_to_payment_methods_table.php
Schema::table('payment_methods', function (Blueprint $table) {
    $table->boolean('is_system')->default(false)->after('is_active');
});
```
Razón: ya hay 3 métodos "naturales" (cash, transfer, credit_card) que toda clínica peruana usa. Marcarlos como sistema evita que un admin los borre y rompa transacciones viejas que los referencian.

**Fix definitivo (Sprint 2)**: UI CRUD admin. Para `payment_methods`, además de CRUD básico, permitir **agregar credenciales de pasarela** (columnas `gateway`, `gateway_credentials` JSON). Esa es la pieza que conecta con Sprint 3.

---

### 🟠 B-CASH-3 — No hay UI de administración de sucursales ni métodos de pago

**Evidencia**: `ls resources/js/modules/` muestra los módulos existentes:
```
ai-analysis, appointment-types, appointments, bi, cash-register,
dashboard, dental-chairs (environments), medical-records, patients,
professionals, procedure-catalog, quotations, specialty-records, treatment-plans
```
**No hay** `settings/`, `admin/`, ni sub-módulos `branches/` o `payment-methods/`.

**Causa raíz**: el sistema se diseñó pensando que las sucursales y métodos de pago se sembraban una vez en el deploy y no se tocaban. Pero para un cliente que va a usar OdontoSuite en producción, eso es regresivo. La realidad es:
- Las clínicas abren sedes nuevas (expansión).
- Cambian de banco → nuevo método de transferencia.
- Agregan Yape/Plin cuando se vuelven populares (Perú, 2020+).
- Un admin debe poder hacerlo sin pedirle al dev que ejecute SQL.

**Fix (Sprint 1 y 2)**: módulos Vue 3 siguiendo el patrón canónico de OdontoSuite (ver plan #1 "Catálogo de Procedimientos" como referencia). Componentes: `BranchesPage.vue`, `BranchFormModal.vue`, `useBranches.js`, router entry, menu entry, layout link en `AppLayout.vue` (solo visible para `role === 'administrador'`).

**Design pattern a seguir** (de `docs/ux-guidelines.md` + `TreatmentPlansPage.vue`):
- `PageHeader` con title + subtitle + botón "Nueva Sucursal"
- CounterPills arriba con conteos (Activas/Inactivas/Total)
- `FilterBar` con búsqueda por nombre/código
- `EmptyState` cuando no hay resultados
- `SkeletonCards` durante carga
- `ConfirmDialog` (singleton `useConfirm`) para desactivar/eliminar
- Diseño responsive: grid de cards en desktop, lista en mobile
- Tabla con paginación para >10 items
- Animaciones: `hover-lift` en cards, `animate-scale-in` en modales, `animate-fade-in` en carga

---

### 🟠 B-CASH-4 — No hay pasarela de pago real

**Evidencia**:
- `composer.json` no requiere `mercadopago/dx-php` ni `stripe/stripe-php` ni `izipay/izipay-php`.
- `config/services.php` no tiene bloque `mercadopago`.
- `.env.example` no tiene `MERCADOPAGO_ACCESS_TOKEN` ni `MERCADOPAGO_PUBLIC_KEY`.
- `BillingService.php` crea transacciones manuales; no hay integración con ningún gateway.

**Decisión de scope** (la más importante del plan): integrar **Mercado Pago Checkout Bricks** (no Checkout Pro, no la SDK vieja v2).

Razones:
- **Bricks es el estándar 2026** de MP. Es un set de Web Components que se montan en un `<div>` de tu checkout. Mantiene al usuario en tu sitio (no redirige a mercadopago.com).
- **Soporta tarjeta, Yape, Plin, transferencia, saldo MP** con un solo init.
- **PCI compliance** viene resuelto (MP tokeniza la tarjeta; nunca pasa por tu servidor).
- **SDK PHP v3.x** (`mercadopago/dx-php:^3.10`) es la actual. Composer la trae en 30 segundos.
- **El usuario ya conoce Mercado Pago** (es Perú, la usan todos). Cero curva de aprendizaje.

**Alternativas evaluadas y descartadas**:
- ❌ **Stripe**: domina global, pero en Perú no tiene buena cobertura de cuotas/sin interés. No vale la pena para capstone.
- ❌ **Izipay / Niubiz**: peruanos, pero requieren contrato comercial + certificación PCI + setup bancario. No aplica a capstone.
- ❌ **Culqi**: peruano, mejor DX que Izipay, pero menos cuota de mercado que MP.
- ❌ **PagoEfectivo**: solo para pagos en efectivo con código. Complementario, no principal.

**Fix (Sprint 3)** — arquitectura en 3 capas:

1. **Backend (Laravel)**:
   - `composer require mercadopago/dx-php:^3.10`
   - Migration `payment_gateway_transactions` (registro de cada intento de pago: gateway, external_id, status, raw_response, monto, moneda, customer_id, transaction_id local).
   - Migration `payment_gateway_credentials` (por método de pago: gateway, credentials JSON encriptadas con `Crypt::encryptString`).
   - Model `PaymentGatewayTransaction`, `PaymentGatewayCredential`.
   - Service `MercadoPagoService` (encapsula SDK: createPreference, getPayment, handleWebhook).
   - Controller `MercadoPagoController` (createPreference POST, webhook POST).
   - Controller `PaymentGatewayCredentialController` (admin: guardar credenciales por sucursal).
   - Ruta webhook firmada (valida firma MP via `x-signature` header).
   - Job `ProcessMercadoPagoWebhook` (procesa el webhook async; actualiza `Transaction` local; dispara evento `PaymentReceived`).

2. **Frontend (Vue)**:
   - `useMercadoPago.js` (carga SDK JS desde CDN en runtime, NO bundle: `https://sdk.mercadopago.com/js/v2`).
   - `MercadoPagoBrick.vue` (componente wrapper que monta `CardPaymentBrick` con la preference_id devuelta por backend).
   - `PaymentMethodFormModal.vue` (form admin: tipo de pasarela, credenciales, test/prod toggle).
   - Botón "Cobrar con Mercado Pago" en `PaymentModal.vue` actual (al lado del "Registrar Cobro Manual").

3. **Configuración (lo que el usuario hace manual)**:
   - `.env`: pegar 4 variables (TEST_ACCESS_TOKEN, TEST_PUBLIC_KEY, PROD_ACCESS_TOKEN, PROD_PUBLIC_KEY).
   - Panel MP: configurar webhook URL.
   - UI admin: pegar credenciales PROD cuando esté listo para salir de sandbox.

**Flujo end-to-end** (lo que el usuario vive):

1. Recepcionista abre `/cash-register` → registra un cobro de S/. 200 al paciente Juan Pérez.
2. En `PaymentModal` elige método "Mercado Pago" (de la lista de métodos activos).
3. Click "Cobrar con Mercado Pago" → backend crea preference + devuelve `preference_id` y `public_key`.
4. Frontend monta `CardPaymentBrick` con esos datos → aparece el form de MP embebido (tarjeta, Yape, Plin, etc.).
5. Juan paga S/. 200 → MP redirige al `back_url.success` configurado + dispara webhook a `/api/payments/webhooks/mercadopago`.
6. Backend recibe webhook → valida firma → busca la `Transaction` local por `external_reference` → marca como `paid` → crea `PaymentGatewayTransaction` con `status=approved` → emite evento `PaymentReceived` (Reverb).
7. Frontend recibe `PaymentReceived` vía WS → actualiza dashboard de caja → toast "Cobro confirmado por Mercado Pago".
8. `Transaction` local queda registrada con `payment_method_id = (el método "mercadopago")` y `payment_gateway_transaction_id` linkeando al registro del gateway.

**Costo del feature** (lo que el usuario paga):
- Comisión MP: ~3.99% + S/1.00 por transacción aprobada (cobra el cliente final o la clínica, configurable en panel MP).
- Sandbox: gratis, instantáneo, con tarjetas de prueba `4509 9535 6623 3704` etc.
- Producción: necesita cuenta MP validada (RUC + DNI del titular).

---

### 🟡 B-CASH-5 — Modal de abrir caja silencia el error de carga de sucursales

**Evidencia** (`OpenCashModal.vue:179-185`):
```js
const loadBranches = async () => {
  try {
    const response = await get('/api/branches')
    branches.value = response.data || []
  } catch (error) {
    // silencioso - el catch está vacío
  }
}
```

**Bug UX**: si `/api/branches` falla por 500 / 401 / network, el usuario ve un `<select>` vacío **sin ninguna indicación** de que algo falló. Pasa 10 segundos escribiendo el monto de apertura, hace submit, recibe 422 "branch_id is required", y recién ahí se da cuenta de que el dropdown no se llenó.

**Fix (Sprint 0)**: agregar UI empty state + toast de error en `loadBranches`:
- Si `branches.length === 0` después de cargar: mostrar `EmptyState` dentro del modal con CTA "Ir a Configuración de Sucursales" (solo visible para admin).
- Si falla la carga: toast.error y mantener estado anterior.

**Patrón de fix** (de `EmptyState.vue` en el design system — verificar pitfall #29e-2 antes de propagar: tiene `computed` que requiere import de Vue).

---

### 🟡 B-CASH-6 — Dropdowns nativos `<select>` en lugar del `Select.vue` del design system

**Evidencia** (`OpenCashModal.vue:15-28` y otros 4 modales):
```vue
<select v-model="formData.branch_id" class="block w-full px-3 py-2 border border-theme rounded-md...">
  <option value="">Seleccionar sucursal</option>
  <option v-for="branch in branches" :key="branch.id" :value="branch.id">
    {{ branch.name }}
  </option>
</select>
```

**Por qué importa**:
- El `<select>` nativo HTML no se puede estilar bien cross-browser (Chrome y Safari renderizan diferente).
- No permite búsqueda cuando hay 10+ items.
- No soporta iconos, descripciones secundarias, ni grouping.
- El design system de OdontoSuite ya tiene `Select.vue` (en `resources/js/components/ui/Select.vue`) con: búsqueda, multi-select, async load, items con descripción, validación inline, estados de error, loading state.

**Fix (Sprint 4)**: reemplazar los 5 `<select>` relevantes:
1. `OpenCashModal.vue` (sucursal).
2. `PaymentModal.vue` (método de pago).
3. `TransactionModal.vue` (tipo de transacción + método de pago).
4. `MovementModal.vue` (tipo de movimiento).
5. Cualquier otro dropdown de la página de caja.

**Ganancia de UX**:
- Búsqueda rápida ("lima", "central", "BCP").
- Item secundario con `code` o `description` (ej: "Sede Central Lima — SC-001").
- Estado de error inline coherente con el resto del form.
- Loading state mientras carga.

**Costo**: ~1 h por modal × 5 modales = 5 h. Lo agrupo en Sprint 4 con otros pulidos (empty states, validaciones inline) para llegar a 0.7 d-h netos porque la mayoría es find-and-replace + agregar props.

---

## 4. Cambios planeados

### Sprint 0 — Seeders mínimos + UX feedback en modales existentes (0.3 d-h)

**Branch**: `fix/cash-sprint-0-seeders-and-empty-state`

**Tareas** (orden estricto):

1. **Crear `database/seeders/BranchSeeder.php`** (3 sedes, `updateOrCreate` por `code`):
   - Sede Central Lima — `SC-LIM-01` (Jr. de la Unión 123, Lima, Lima)
   - Sede Norte — `SC-NOR-01` (Av. Túpac Amaru 456, Los Olivos, Lima)
   - **Sucursal Sur (Surco)** — `SC-SUR-01` (Av. Benavides 789, Surco, Lima) — 1 sola ciudad al inicio, agregar más por tinker después.
   - Todas con `country='Perú'`, `timezone='America/Lima'`, `is_active=true`.
   - Usar `updateOrCreate(['code' => 'SC-LIM-01'], [...])` para idempotencia.

2. **Crear `database/seeders/PaymentMethodSeeder.php`** (5 métodos base, `updateOrCreate` por `code`):
   - `cash` — "Efectivo" — `requires_authorization=false`, `allows_change=true`, `commission_percentage=0`
   - `transfer` — "Transferencia bancaria" — `requires_authorization=true`, `allows_change=false`, `commission_percentage=0`
   - `credit_card` — "Tarjeta de crédito" — `requires_authorization=false`, `allows_change=false`, `commission_percentage=3.50`
   - `debit_card` — "Tarjeta de débito" — `requires_authorization=false`, `allows_change=false`, `commission_percentage=2.00`
   - `yape` — "Yape" — `requires_authorization=true`, `allows_change=false`, `commission_percentage=0`
   - Todos con `is_active=true`, `is_system=true` (columna que se agrega en migration de Sprint 2 — en Sprint 0, omitir esa columna hasta que se cree la migration).

3. **Modificar `DatabaseSeeder.php`**: agregar los 2 seeders a la lista `$this->call([...])` (orden: después de `RoleBasedUsersSeeder`, antes de `SpecialtySeeder`).

4. **Crear `database/migrations/2026_06_13_120000_add_is_system_to_payment_methods_table.php`** (columna nueva, additive):
   ```php
   Schema::table('payment_methods', function (Blueprint $table) {
       $table->boolean('is_system')->default(false)->after('is_active');
   });
   ```

5. **Modificar `app/Models/PaymentMethod.php`**: agregar `is_system` al `$fillable` y al `$casts`.

6. **Modificar `OpenCashModal.vue:179-185`**: catch que silencia el error → toast de error + UI empty state. Reusar `EmptyState.vue` (verificar pitfall #29e-2: tiene `computed` que requiere `import { computed } from 'vue'`).

7. **Modificar `PaymentModal.vue`**: misma corrección. Cargar payment methods en `onMounted` y manejar error.

8. **Verificación**:
   - `php artisan migrate:fresh --seed` → 3 branches + 5 payment methods.
   - `php artisan tinker --execute="echo \App\Models\Branch::count().','.\App\Models\PaymentMethod::count();"` → "3,5".
   - `php artisan serve` + login como admin → `GET /api/branches` → 200 con 3 items.
   - `GET /api/payment-methods` → 200 con 5 items.
   - `pnpm build` → 0 errores.
   - **Verificar visualmente en Chrome** (pitfall #29g): abrir modal "Abrir Caja" → dropdown muestra 3 sedes con nombre + código, no solo vacío.

**Entregable**: `docs/mejoras/11.1-sprint-0-deliverable.md` con verificación + commits.

---

### Sprint 1 — Módulo `settings/branches` (CRUD admin) (1.5 d-h)

**Branch**: `feat/cash-sprint-1-branches-admin`

**Tareas** (patrón canónico de módulo nuevo, ver skill `laravel-vue/odontosuite-dev-guide#patron-para-crear-modulo-nuevo`):

1. **Backend**:
   - Refactor `BranchController.php`: usar `BranchResource` (nuevo) en vez de `response()->json(['data' => $branches])`. Mantener los 5 métodos `index/store/show/update/destroy` + agregar `toggleActive()` para soft-toggle.
   - Crear `app/Http/Resources/BranchResource.php` (estructura canónica: `id, name, code, address, city, phone, email, is_active, created_at, patients_count, users_count, sessions_count` con `whenLoaded`).
   - Crear `app/Http/Requests/StoreBranchRequest.php` y `UpdateBranchRequest.php` (validación centralizada con `sometimes|nullable` en campos opcionales — ver skill `laravel-data-modeling#patron-formrequest`).
   - Verificar que la ruta `Route::apiResource('branches', BranchController::class)` en `routes/api.php:303` está detrás de `role:administrador` (pitfall #15 del skill).
   - **Test estructural** (PHPUnit puro, sin BD): `tests/Feature/Http/Resources/BranchResourceTest.php` con 3 asserts: `id`, `name`, `code`.

2. **Frontend**:
   - `resources/js/composables/useBranches.js` (singleton con cache + invalidación en mutaciones).
   - `resources/js/modules/settings/branches/BranchesPage.vue` (vista principal con grid de cards + tabla toggle).
   - `resources/js/modules/settings/branches/components/BranchFormModal.vue` (create/edit).
   - `resources/js/modules/settings/branches/components/BranchCard.vue` (card de cada sede con status pill, dirección, contadores).
   - `resources/js/modules/settings/branches/components/BranchFilters.vue` (búsqueda por nombre/código/ciudad, filtro activas/inactivas).
   - Registrar todo en `resources/js/plugins/ui-components.js` (pitfall #26b del skill).
   - `resources/js/router/index.js` (o `app.js` según el proyecto): agregar ruta `/settings/branches` con `meta: { requiresAuth: true, roles: ['administrador'] }`.
   - `resources/js/components/layout/AppLayout.vue`: agregar link en menú lateral (solo visible para admin) bajo sección "Configuración" → "Sucursales".

3. **Seeders extra** (opcional, mejora demo): agregar 1-2 sedes más al `BranchSeeder` para que la página tenga data realista al primer login.

4. **Verificación**:
   - `php -l` en cada PHP nuevo.
   - `pnpm build` → 0 errores.
   - Login como admin → ir a `/settings/branches` → ver grid con 3+ cards.
   - Click "Nueva Sucursal" → modal → llenar form → guardar → card aparece en grid.
   - Editar una sede → modal prellenado → guardar → card actualizada.
   - Desactivar una sede → confirmación → status pill cambia a "Inactiva".
   - Intentar eliminar una sede con `patients_count > 0` → ver bloqueo (FK constraint) o confirmación.
   - Login como `recepcionista` → no ve el link en el menú (gate de role).

**Entregable**: `docs/mejoras/11.2-sprint-1-deliverable.md` con verificación + screenshots si aplica.

---

### Sprint 2 — Módulo `settings/payment-methods` (CRUD admin) (1.5 d-h)

**Branch**: `feat/cash-sprint-2-payment-methods-admin`

**Tareas** (mismo patrón que Sprint 1, ajustes específicos):

1. **Backend**:
   - Refactor `PaymentMethodController.php` con `PaymentMethodResource`.
   - Crear `StorePaymentMethodRequest.php` y `UpdatePaymentMethodRequest.php`.
   - **Regla de borrado**: si el método tiene `is_system=true`, el método `destroy()` falla con 403 "No se puede eliminar un método del sistema. Desactívelo en su lugar." (NO soft-delete, solo `is_active=false`).
   - Migración: agregar columna `gateway_type` (enum: `null`, `mercadopago`, `manual`) y `gateway_config` (JSON nullable, encriptado con `Crypt::encryptString` en el model via accessor).
   - **Test estructural** del nuevo campo en `PaymentMethodResource`.
   - Ruta `Route::apiResource('payment-methods', PaymentMethodController::class)` con role gate.

2. **Frontend**:
   - `usePaymentMethods.js` composable.
   - `PaymentMethodsPage.vue` (lista en tabla, no cards — son 5-15 items no más).
   - `PaymentMethodFormModal.vue` (form con campos según `gateway_type`):
     - Si `manual`: campos normales (name, code, description, commission, allows_change, requires_authorization, is_active).
     - Si `mercadopago`: + campos `access_token` (password), `public_key`, `environment` (sandbox/production), `branch_id` (qué sucursal aplica — nullable = global).
   - `PaymentMethodTableRow.vue` (fila con badge de gateway, status pill, is_system pill).
   - Registrar en `ui-components.js`.
   - Ruta `/settings/payment-methods` con role gate.
   - Link en menú lateral.

3. **Verificación**:
   - Login admin → ver 5 métodos pre-cargados con badge "Sistema".
   - Crear método custom ("Plin") → aparece con badge "Custom".
   - Intentar borrar "Efectivo" (sistema) → toast de error, no se borra.
   - Intentar borrar "Plin" (custom, sin transacciones) → confirmación → se borra.
   - Crear método MP → llenar credenciales test → guardar → verificar en BD que `gateway_config` está encriptado (`Crypt::encryptString`).

**Entregable**: `docs/mejoras/11.3-sprint-2-deliverable.md`.

---

### Sprint 3 — Pasarela Mercado Pago (3 d-h)

**Branch**: `feat/cash-sprint-3-mercadopago-gateway`

**Tareas**:

1. **Setup**:
   - `composer require mercadopago/dx-php:^3.10`.
   - Agregar a `.env.example`:
     ```
     MERCADOPAGO_TEST_ACCESS_TOKEN=
     MERCADOPAGO_TEST_PUBLIC_KEY=
     MERCADOPAGO_PROD_ACCESS_TOKEN=
     MERCADOPAGO_PROD_PUBLIC_KEY=
     MERCADOPAGO_ENVIRONMENT=sandbox
     MERCADOPAGO_WEBHOOK_URL=
     ```
   - Crear `config/mercadopago.php` con 4 keys leídas de env.

2. **Backend — migraciones**:
   - `2026_06_13_140000_create_payment_gateway_transactions_table.php`:
     - `id`, `transaction_id` (FK nullable a `transactions`), `payment_method_id` (FK), `gateway` (string: 'mercadopago'), `external_id` (string: preference_id o payment_id de MP), `external_status` (string: 'pending'/'approved'/'rejected'/'cancelled'), `amount` (decimal), `currency` (string, default 'PEN'), `payer_email` (string nullable), `raw_response` (JSON), `webhook_received_at` (timestamp nullable), `created_at`/`updated_at`.
   - `2026_06_13_140001_create_payment_gateway_webhook_events_table.php`:
     - `id`, `gateway`, `event_type`, `external_id`, `payload` (JSON), `signature` (string), `signature_valid` (boolean), `processed` (boolean default false), `processed_at` (timestamp nullable), `error` (text nullable). Idempotency: unique en `external_id + event_type`.

3. **Backend — modelos**:
   - `App\Models\PaymentGatewayTransaction` (con casts + relaciones).
   - `App\Models\PaymentGatewayWebhookEvent` (inmutable: solo `processed` se actualiza).

4. **Backend — service**:
   - `App\Services\MercadoPagoService.php`:
     - `__construct(MercadoPagoConfig $config)` — inyecta config + lee env.
     - `createPreference(Transaction $transaction, string $backUrl, string $failureUrl, string $pendingUrl): array` — devuelve `{ id: '...', init_point: '...', sandbox_init_point: '...' }`.
     - `getPayment(string $paymentId): ?array` — consulta estado.
     - `validateWebhookSignature(Request $request): bool` — valida header `x-signature` con HMAC-SHA256 del manifest.
     - `parseWebhookPayload(Request $request): array` — extrae `data.id` y `type`.

5. **Backend — controller y rutas**:
   - `App\Http\Controllers\Api\MercadoPagoController.php`:
     - `createPreference(Request $request, TransactionService $transactionService)` — POST `/api/payments/mercadopago/preference` (autenticado, role: recepcionista+). Crea preference, persiste `PaymentGatewayTransaction` con status `pending`, devuelve `{ preference_id, public_key, init_point }`.
     - `webhook(Request $request, MercadoPagoService $mp)` — POST `/api/payments/webhooks/mercadopago` (SIN auth — es webhook público, validado por firma). Encola `ProcessMercadoPagoWebhook` job. Responde 200 inmediato.
   - `App\Http\Controllers\Api\PaymentGatewayCredentialController.php` (admin):
     - `update(Request $request, PaymentMethod $paymentMethod)` — PUT `/api/payment-methods/{id}/credentials` (solo admin). Encripta y guarda.
   - Registrar en `routes/api.php` DENTRO del grupo `auth:sanctum` para `createPreference`, FUERA del grupo (o con `web` middleware) para `webhook`.

6. **Backend — job y listener**:
   - `App\Jobs\ProcessMercadoPagoWebhook.php`:
     - `handle(MercadoPagoService $mp, TransactionService $tx)`: lee el evento, consulta MP con `getPayment`, si `status=approved` marca la transacción local como `paid`, marca `PaymentGatewayTransaction` como `approved`, dispara `event(new PaymentReceived($transaction))`. Si falla, log + retry con backoff (3 intentos, 1min/5min/15min). Si la firma es inválida, marca `signature_valid=false` y `processed=false` (para auditoría), NO reprocesar.
   - `App\Listeners\BroadcastPaymentReceived.php` — emite por Reverb al canal `cash-register.{branch_id}` con payload `{ transaction_id, amount, payer, status }`.

7. **Backend — eventos**:
   - `App\Events\PaymentReceived.php` (implements `ShouldBroadcast`) — payload mínimo: `{ transaction_id, amount, status, branch_id }`.

8. **Frontend — composable**:
   - `resources/js/composables/useMercadoPago.js`:
     - `loadSdk(publicKey)` — inyecta `<script src="https://sdk.mercadopago.com/js/v2">` si no está, devuelve Promise que resuelve con `window.MercadoPago`.
     - `createBrick(preferenceId, publicKey, containerId, callbacks)` — inicializa `CardPaymentBrick` con callbacks (`onPaymentSubmit`, `onReady`, `onError`).
     - `unmount(containerId)` — cleanup.

9. **Frontend — componente**:
   - `resources/js/modules/cash-register/components/MercadoPagoCheckout.vue`:
     - Props: `transaction` (objeto), `preferenceResponse` ({ preference_id, public_key }).
     - En `onMounted`: carga SDK + monta Brick en un `<div ref="brickContainer">`.
     - Botón "Volver" para cerrar.
     - Loading state mientras carga SDK.
     - Empty state si MP no responde.
   - Modificar `PaymentModal.vue`: agregar tab/segmented "Cobro Manual" | "Cobro con Mercado Pago". El tab MP llama al backend para crear preference → monta Brick.

10. **Frontend — settings UI** (parte de Sprint 2, lo separé para que sea claro):
    - En `PaymentMethodFormModal.vue`, si `gateway_type === 'mercadopago'`, mostrar:
      - Select `environment`: Sandbox (test) / Producción.
      - Input `access_token` (password, no autocomplete).
      - Input `public_key`.
      - Botón "Probar credenciales" → llama `GET /api/payments/mercadopago/validate-credentials` → backend hace ping a MP `/users/me` → devuelve 200 o error.
    - Guardar todo encriptado.

11. **Verificación**:
    - `composer require mercadopago/dx-php:^3.10` → exit 0.
    - `php -l` en cada PHP nuevo.
    - `php artisan migrate` → 2 migrations nuevas aplicadas.
    - `pnpm build` → 0 errores.
    - **Test E2E en sandbox** (usar `php artisan serve` + ngrok o IP pública):
      - Login admin → ir a `/settings/payment-methods` → editar "Tarjeta de crédito" → cambiar gateway a `mercadopago` → pegar TEST_ACCESS_TOKEN y TEST_PUBLIC_KEY del panel MP del usuario.
      - Login recepcionista → ir a `/cash-register` → registrar cobro de S/10 al paciente Juan Pérez → elegir método "Tarjeta de crédito" → tab "Cobro con Mercado Pago" → Brick se monta → llenar tarjeta de prueba `4509 9535 6623 3704` (visa, aprueba) → pagar.
      - Verificar que el webhook llegó (Revisar `storage/logs/laravel.log` línea `[MP-WEBHOOK]`).
      - Verificar que `Transaction` local quedó `status=paid`.
      - Verificar que el dashboard de caja se actualizó vía WebSocket.
    - **Test tarjeta rechazada**: usar `4000 0000 0000 0002` (visa, rechaza) → Brick muestra error → transacción local queda `pending` → NO se cobró.

**Entregable**: `docs/mejoras/11.4-sprint-3-deliverable.md` con verificación E2E paso a paso.

---

### Sprint 4 — Polish UX: `Select.vue`, empty states, validaciones inline (0.7 d-h)

**Branch**: `refactor/cash-sprint-4-polish-selects-and-states`

**Tareas**:

1. Reemplazar `<select>` nativo por `Select.vue` del design system en 5 modales:
   - `OpenCashModal.vue:15-28` (sucursal).
   - `PaymentModal.vue` (método de pago).
   - `TransactionModal.vue` (tipo + método).
   - `MovementModal.vue` (tipo).
   - `BranchFormModal.vue` (parent_branch_id si se agrega jerarquía en el futuro).
2. Mejorar empty states en modales: `EmptyState` con icono + mensaje + CTA contextual (ej: "No hay métodos de pago. Ir a Configuración →" si usuario es admin).
3. Agregar CurrencyInput validation inline (en lugar de mensajes genéricos).
4. Skeleton loader en `useBranches` y `usePaymentMethods` durante fetch inicial.
5. Toast de éxito/error consistente (ya hay `useToast`, solo normalizar duración y variant).
6. Verificación visual en Chrome (pitfall #29g): comparar antes/después de cada modal.

**Entregable**: `docs/mejoras/11.5-sprint-4-deliverable.md`.

---

## 5. Riesgos y mitigaciones

| # | Riesgo | Probabilidad | Mitigación |
|---|---|---|---|
| 1 | Mercado Pago rechaza credenciales del usuario (cuenta no validada) | Alta en dev | Documentar en sprint 3 que el usuario necesita tener la cuenta validada. Mientras tanto, el sistema funciona en sandbox con credenciales de prueba. |
| 2 | Webhook de MP no llega porque la URL no es pública (en dev local) | Alta | Documentar uso de `ngrok http 8000` y `MERCADOPAGO_WEBHOOK_URL` con la URL de ngrok. En prod, configurar HTTPS válido. |
| 3 | Rutas de admin no protegidas por role | Media (pitfall #15) | Verificar `bootstrap/app.php` tiene alias `role` aplicado a `routes/api.php` línea 303 (branches) y 354 (payment-methods). Test manual: login como recepcionista → intentar `GET /api/branches` directo → 403. |
| 4 | Mercado Pago SDK v3 rompe en PHP < 8.2 | Baja | Verificar `composer.json` tiene `"php": "^8.2"` (Laravel 12 sí lo tiene). |
| 5 | El usuario borra un método de pago con transacciones históricas → transacciones quedan con FK rota | Alta sin guard | Soft-delete pattern: `delete()` falla con 403 si el método tiene `transactions_count > 0`. Mostrar mensaje "Tiene N transacciones registradas. Desactívelo en su lugar." |
| 6 | La migration `add_is_system` falla en SQLite (CI) | Media | Usar `if (DB::getDriverName() !== 'sqlite')` o rewrite portable. Ver skill `plan-and-execute-workflow#pattern-s`. |
| 7 | `EmptyState.vue` explota por pitfall #29e-2 (computed sin import) cuando se use en `OpenCashModal` y el dropdown esté vacío | Media | Antes de propagar, verificar `import { computed } from 'vue'` está en el componente. Comando: `grep -L "from 'vue'" resources/js/components/ui/EmptyState.vue`. Si no está, agregar. |
| 8 | El usuario pega credenciales MP en `.env` y commitea el `.env` al repo (leak de tokens) | Media | Agregar `.env` a `.gitignore` (ya debería estarlo), y agregar test que verifica que `MERCADOPAGO_*_TOKEN` no aparece en ningún `.php` commiteado. `grep -r "MERCADOPAGO_.*_TOKEN" --include="*.php" --include="*.env*" --include="*.md"` debe devolver 0 matches fuera de `config/mercadopago.php`. |
| 9 | Webhook de MP duplica el procesamiento (MP puede reenviar) | Media | Idempotency: `PaymentGatewayWebhookEvent` tiene unique en `external_id + event_type`. Si ya existe, no reprocesar. Responder 200 inmediatamente. |
| 10 | La preferencia de MP expira en 24h → si el usuario paga al día siguiente, falla | Baja | MP maneja esto internamente. Si expira, MP rechaza y notifica via webhook con `status=rejected`. Frontend muestra "La preferencia expiró, intente de nuevo". |
| 11 | `Select.vue` no soporta async load (carga inicial de opciones) | Baja | Verificar que `Select.vue` tiene prop `async-load` o equivalente. Si no, agregarlo en este sprint. |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.3 d-h) — desbloquear el flujo existente con datos reales.
2. **Sprint 1** (1.5 d-h) — módulo admin de sucursales.
3. **Sprint 2** (1.5 d-h) — módulo admin de métodos de pago.
4. **Sprint 3** (3 d-h) — pasarela Mercado Pago.
5. **Sprint 4** (0.7 d-h) — polish UX con design system.

**Total**: ~7 d-h netos. Ejecutable en 2-3 sesiones según ritmo de Arnold.

---

## 7. Métricas de éxito al cerrar el plan

- **Funcional**:
  - [ ] `migrate:fresh --seed` deja 3 branches + 5 payment methods cargados.
  - [ ] Admin puede CRUDear sucursales y métodos de pago desde la UI.
  - [ ] Recepcionista puede cobrar con Mercado Pago (sandbox) usando tarjeta de prueba.
  - [ ] Webhook de MP actualiza la transacción local y dispara evento WebSocket.
  - [ ] Cobro rechazado NO actualiza la transacción local.
  - [ ] Credenciales MP están encriptadas en BD.
- **UX/UI**:
  - [ ] Modal "Abrir Caja" muestra 3 sedes en el dropdown al primer login.
  - [ ] Dropdown de métodos de pago en "Registrar Cobro" muestra 5 métodos.
  - [ ] Si el admin desactiva todos los métodos, el modal muestra empty state con CTA "Ir a Configuración".
  - [ ] Tabla/grid de sucursales es responsive (3 cols desktop, 1 col mobile).
  - [ ] Brick de MP se monta en <2s desde el click.
- **Seguridad**:
  - [ ] Rutas de admin devuelven 403 para recepcionista/odontólogo.
  - [ ] Webhook rechaza peticiones sin firma válida.
  - [ ] Credenciales MP nunca aparecen en logs ni en responses.
- **Mantenibilidad**:
  - [ ] Sin `console.log` en código de producción (regla del proyecto).
  - [ ] Todos los `useFoo.js` composables siguen el patrón `useX()` (ver `plan-and-execute-workflow#pattern-f`).
  - [ ] Cada sprint deja `docs/mejoras/11.X-sprint-N-deliverable.md` con verificación.

---

## 8. Out of scope (deferred a futuros planes)

- **Stripe / Izipay / Niubiz** como pasarelas alternativas → plan #12 (cuando el cliente lo pida).
- **Multi-moneda** (PEN, USD, EUR) → plan #13 (cuando expanda a clientes internacionales).
- **Suscripciones recurrentes** (cobros mensuales automáticos) → plan #14 (cuando el cliente lo pida).
- **Jerarquía de sucursales** (sucursal matriz → sub-sucursales) → si se necesita, agregar columna `parent_branch_id` en un sprint futuro.
- **Reportes de cierre de caja por sucursal** → el sistema ya filtra por branch_id en `CashRegisterService::getSessionSummary`, solo falta UI de filtro (mejora menor, no requiere sprint).
- **Cuotas y descuentos en métodos de pago** → el campo `commission_percentage` ya existe, falta lógica de cálculo automático (un sprint futuro si se justifica).

---

## 9. Decisiones clave que necesitan tu input antes de empezar Sprint 3

| # | Decisión | Opciones | Recomendación |
|---|---|---|---|
| 1 | ¿Usar Checkout Pro (redirect) o Checkout Bricks (embedded)? | A: Pro (más simple, menos control UX). B: Bricks (más moderno, mejor UX, ~1 sprint extra) | **B: Bricks**. Es el estándar 2026, mantiene al usuario en tu sitio, soporta Yape/Plin/tarjeta. |
| 2 | ¿Quién paga la comisión de MP (3.99% + S/1)? | A: La clínica absorbe (recomendado para UX). B: Se le suma al paciente (precio total = S/200 + comisión). C: Configurable por método. | **C: Configurable**. El campo `commission_percentage` ya existe. El admin lo define por método. UI muestra desglose "Subtotal S/200 + Comisión S/8 = Total S/208". |
| 3 | ¿Guardar credenciales MP por sucursal o globales? | A: Global (1 cuenta MP para todas las sedes). B: Por sucursal (cada sede tiene su cuenta MP). | **A: Global con override por sucursal**. Default global, opcional override. La columna `payment_gateway_credentials.branch_id` es nullable. |
| 4 | ¿Soportar pagos en cuotas (12x, 18x, 24x) en tarjeta de crédito? | A: Sí, mostrar dropdown de cuotas. B: No, solo pago único. | **A: Sí, cuotas**. MP las soporta nativamente. Costo para la clínica: 3.99% + S/1 (igual que sin cuotas). Costo para el paciente: interés de MP. UI: dropdown "3 cuotas sin interés" / "6 cuotas" / "12 cuotas". |
| 5 | ¿Agregar `Yape` y `Plin` como pasarela propia o como método de MP? | A: Métodos manuales (admin registra el Yape de la clínica, usuario sube voucher). B: Pasarela MP (Yape/Plin via Bricks ya vienen). | **B: Pasarela MP**. Yape y Plin ya están integrados en Bricks. Cero código extra. El admin solo necesita vincular su Yape a MP. |

Si querés cambiar alguna de estas, decime antes de arrancar Sprint 3. Para Sprints 0-2 no se necesita decisión previa — sigo con las defaults.

---

## 10. Changelog

- **2026-06-13**: plan creado. 5 sprints propuestos. ~7 d-h estimados.
- **2026-06-13**: Sprint 0 ejecutado. 2 bugs cerrados (B-CASH-1, B-CASH-2). 0.3 d-h. Verificación E2E completa (backend + browser real).
- **2026-06-13**: Sprint 2 ejecutado. B-CASH-4 prep cerrado. ~1.3 d-h. 5 archivos nuevos + 5 modificados. Backend E2E 11/11 (incluyendo encrypt + role gate). Build exit 0 (8.99s). Verificacion visual pendiente.
