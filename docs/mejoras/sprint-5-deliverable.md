# Sprint 5 — WebSocket feedback real + ConfirmDialog global

**Branch**: `feat/ux-sprint-5-feedback`
**Fecha**: 2026-06-12
**Commit**: `a075616`
**Plan ref**: [plan-ux-ui-2026-06.md §5 Sprint 5](../plan-ux-ui-2026-06.md#sprint-5--app-wide-ux-websocket-feedback--confirmdialog-075-d-h)

## Alcance ejecutado

Ambos items del Sprint 5 (M-UX-2 + M-UX-3) completados. **16 sitios con `window.confirm` migrados** (el plan estimaba 6; la realidad eran 16).

## Cambios principales

### M-UX-2: WebSocket feedback real

**`useEcho.js` reescrito** (150 → 175 líneas):
- State reactivo singleton `connectionStatus` (ref a nivel módulo):
  - `'connecting'` | `'connected'` | `'disconnected'` | `'unavailable'`
- `console.warn` en todos los `catch` vacíos (PusherError, TransportError, state_change, unavailable, failed)
- Backoff exponencial: `5s → 10s → 20s → 40s → 60s` (cap), max 10 reintentos
- API exportada: `connectionStatus` (ref) + `reconnectAttempts` (ref)
- Reset a `connecting` al `reconnect()` forzado

**`AppLayout.vue` dot indicador** en el header (junto al bell de notificaciones):
- `bg-success-badge text-success-text` + `animate-pulse-subtle` cuando conectado → "En vivo"
- `bg-warning-badge text-warning-text` cuando disconnected → "Reconectando"
- `bg-danger-badge text-error-700` cuando unavailable → "Sin WS"
- Se oculta durante `connecting` (estado inicial, antes del primer bind)
- `aria-label` con el estado actual para screen readers
- `title` nativo para tooltip

### M-UX-3: ConfirmDialog global (16/16 migrados)

**Nuevo composable `useConfirm.js`** (107 líneas):
- Singleton state compartido (isOpen, title, message, confirmText, cancelText, variant, loading)
- `confirm(options)` retorna `Promise<boolean>`:
  - `title` (string, default "Confirmar")
  - `message` (string, required)
  - `confirmText` (default "Confirmar")
  - `cancelText` (default "Cancelar")
  - `variant` ('default' | 'danger', default 'default')
- Helpers export para montar `<UiConfirmDialog>` globalmente desde AppLayout
- Maneja concurrencia: si llaman `confirm()` dos veces seguidas, resuelve el primero con `false`

**`<UiConfirmDialog>` montado globalmente en `AppLayout.vue`** (después del `<ToastContainer>`):
- `v-model="confirmIsOpen"`
- 6 props conectadas a las refs singleton del composable
- `@confirm="handleGlobalConfirm"` / `@cancel="handleGlobalCancel"` delegados a los handlers del composable

**Patrón de uso resultante** (cualquier vista):

```js
import { useConfirm } from '@/composables/useConfirm'

const { confirm } = useConfirm()

async function deleteItem(item) {
  const ok = await confirm({
    title: 'Eliminar elemento',
    message: '¿Estás seguro?',
    confirmText: 'Eliminar',
    variant: 'danger',
  })
  if (ok) {
    // ejecutar delete
  }
}
```

**16 sitios migrados**:

| Archivo | Línea | Acción | variant |
|---|---|---|---|
| `ai-analysis/AiAnalysisPage.vue` | 498 | `deleteAnalysis` | danger |
| `appointment-types/AppointmentTypesPage.vue` | 565 | `deleteType` | danger |
| `appointments/CalendarPage.vue` | 893 | `deleteAppointment` | danger |
| `cash-register/CashRegisterPage.vue` | 583 | `voidTransaction` | danger |
| `cash-register/components/MovementList.vue` | 394 | `deleteMovement` | danger |
| `cash-register/components/SessionList.vue` | 341 | `reopenSession` | danger |
| `cash-register/components/TransactionList.vue` | 322 | `voidTransaction` | danger |
| `environments/EnvironmentsPage.vue` | 490 | `deleteEnvironment` | danger |
| `medical-records/components/AttachmentGallery.vue` | 217 | `deleteAttachment` | danger |
| `medical-records/components/EvolutionTimeline.vue` | 205 | `confirmDelete` | danger |
| `medical-records/components/MedicalRecordCard.vue` | 128 | `confirmDelete` | danger |
| `patients/PatientsPage.vue` | 989 | `deletePatient` | danger |
| `professionals/ProfessionalsPage.vue` | 553 | `deleteProfessional` | danger |
| `quotations/components/QuotationCard.vue` | 191 | `confirmDelete` | danger |
| `treatment-plans/components/TreatmentPlanCard.vue` | 250 | `confirmDelete` | danger |
| `treatment-plans/components/TreatmentPlanModal.vue` | 468 | `handleClose` (cambios sin guardar) | danger |

## Verificación

### `grep` de `window.confirm` restantes

```
$ grep -rE "(window\.)?confirm[(]" resources/js --include="*.vue"
# Solo matches de `confirm({...})` (el composable), 0 nativos restantes
```

**0 sitios con `window.confirm()` o `confirm()` nativo restantes** (antes: 16).

### `pnpm build`

```
public/build/assets/app-DxuDEWjv.js                         480.45 kB │ gzip: 153.51 kB
✓ built in 9.06s
```

**0 errores.** Bundle: 480.45 KB (+0.86 KB vs 479.55 KB del Sprint 4 — esperado: el modal global se incluye en el bundle principal porque `<UiConfirmDialog>` se importa en AppLayout).

### Diff stats

```
a075616  19 files changed, 356 insertions(+), 48 deletions(-)
# + resources/js/composables/useConfirm.js (nuevo)
```

## Decisiones de diseño

### ¿Por qué singleton state y no v-model por componente?

Consideré 2 alternativas:
- **Opción A**: cada vista tiene su propio `<UiConfirmDialog>` con refs locales → 16 vistas con boilerplate idéntico, más código, más bugs potenciales.
- **Opción B** (elegida): singleton state + un solo modal global en AppLayout → DRY, fácil de mantener, escalable.

Costo de la Opción B: un poco más de complejidad conceptual (el state vive a nivel de módulo). Beneficio: agregar `await confirm()` en cualquier vista es 1 línea.

### ¿Por qué `Promise<boolean>` y no callbacks?

`await confirm()` es mucho más legible que callbacks tipo `.then(ok => ...)`. Además, las funciones que llaman `confirm()` se vuelven naturalmente `async`, lo que ayuda al modelo mental (esperar, no bloquear).

### ¿Por qué `variant: 'danger'` en todos los 16?

Verifiqué cada sitio: 15/16 son deletes/anulaciones (acciones destructivas). El único "no destructivo" es el de `TreatmentPlanModal.handleClose` ("cambios sin guardar") — también es variant `danger` porque perder datos del usuario es percibido como destructivo.

## Métricas del plan (avance)

- ✅ **0 colores crudos** (cerrado Sprint 3)
- ✅ **0 spinners morados inline** (cerrado Sprint 2+3)
- ✅ **100% modales con scale-in** (verificado)
- ✅ **0 `window.confirm()` nativos** (cerrado Sprint 5)
- ✅ **1 indicador visual de "WS conectado"** (cerrado Sprint 5, real con state reactivo)
- ⏸️ **17/17 páginas con `<PageHeader>`** (5/17 — quedan 12 en Sprint 6)
- ⏸️ **Polish final** (Sprint 7)

## Próximo sprint

**Sprint 6** (PageHeader en 12 páginas restantes): AppointmentTypes, Professionals, Environments, ProcedureCatalog, MyProcedures, ReceptionProcedures, Quotations, MedicalRecords, SpecialtyRecords, AiAnalysis, PatientDetailPage, TreatmentPlansPage (refinar).
