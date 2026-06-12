# Sprint 4 — Apple animations: pulse, hover-lift, NotificationToast slide

**Branch**: `feat/ux-sprint-4-animations`
**Fecha**: 2026-06-12
**Commit**: `4a2ac51` — feat(ux): Sprint 4 - Apple animations: pulse-subtle, hover-lift, NotificationToast slide
**Plan ref**: [plan-ux-ui-2026-06.md §5 Sprint 4](../plan-ux-ui-2026-06.md#sprint-4--apple-animations-aplicar-scaleslideripple-10-d-h)

## Objetivo cumplido

3 de los 4 items del Sprint 4 (UiModal y Toast ya tenian animaciones, verificado en código).

## Cambios por componente

### 1. `NotificationToast.vue` — TransitionGroup con slide horizontal

**Antes**: notificaciones aparecian y desaparecian sin animación (v-if directo).

**Después**: `<TransitionGroup>` con:
- Entrada: `opacity-0 translate-x-full` → `opacity-100 translate-x-0` (300ms ease-out)
- Salida: `opacity-100 translate-x-0` → `opacity-0 translate-x-full` (200ms ease-in, posición absolute para evitar layout shift)

Inspirado en las notificaciones nativas de macOS/iOS (entran desde la derecha).

### 2. Badge "En vivo" en `BusinessIntelligencePage.vue` y `CalendarPage.vue`

**Patrón**:
```vue
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-success-badge text-success-text text-xs font-medium"
      aria-label="Actualizaciones en tiempo real activas"
      title="Actualizaciones en tiempo real activas">
  <span class="w-2 h-2 rounded-full bg-success-500 animate-pulse-subtle" aria-hidden="true" />
  En vivo
</span>
```

- Dot verde de 2x2 con `animate-pulse-subtle` (2s loop, opacity 1→0.8)
- Wrapper pill: `bg-success-badge` (verde claro) + `text-success-text` (verde oscuro)
- `aria-label` + `title` para accesibilidad
- Posición: `#actions` slot del `PageHeader`

### 3. `hover-lift` propagado a 9 cards clickeables (de 3 a 12 archivos)

| Archivo | Migración |
|---|---|
| `AiAnalysisCard.vue` | `hover:shadow-md` → `hover-lift` |
| `CalendarPage.vue` | `hover:shadow-md` → `hover-lift` |
| `AttachmentGallery.vue` | `hover:shadow-md` → `hover-lift` |
| `MedicalRecordCard.vue` | `hover:shadow-md` → `hover-lift` |
| `QuotationCard.vue` | `hover:shadow-md` → `hover-lift` |
| `ReceptionProceduresPage.vue` | `hover:shadow-md` → `hover-lift` |
| `SpecialtyRecordCard.vue` | `hover:shadow-md` → `hover-lift` |
| `KanbanColumn.vue` | `hover:shadow-soft` → `hover-lift` |
| `TreatmentPlanCard.vue` | `hover:shadow-md` → `hover-lift` |

**Diferencia visual**:
- Antes: `hover:shadow-md` solo cambia la sombra (efecto de "flotar" sin moverse)
- Ahora: `hover-lift` = `translateY(-1px)` + `shadow-medium` (efecto Apple: la card se levanta 1px y aparece la sombra). Movimiento sutil pero perceptible.

### 4. Fix crítico: `hover-lift` registrado en `tailwind.config.js`

**Problema encontrado durante el build**:
- `hover-lift` estaba definido en `resources/css/utilities.css` PERO fuera de `@layer utilities`
- Tailwind no lo encuentra cuando se usa en `@apply hover-lift` dentro de `<style scoped>` de Vue
- Build fallaba con: `The 'hover-lift' class does not exist. If 'hover-lift' is a custom class, make sure it is defined within a '@layer' directive.`

**Fix aplicado**:
- Movido al plugin de `addUtilities` en `tailwind.config.js` (mismo patrón que `.bg-accent`, `.text-theme-primary`, etc.)
- Definición duplicada en `utilities.css` removida (single source of truth: ahora solo en `tailwind.config.js`)
- Build OK después del fix

**Por qué este fix importa**:
- Sin él, los 9 archivos migrados no compilan
- Cualquier futuro `@apply hover-lift` en otros componentes hubiera fallado
- Es un fix de infraestructura que blinda el uso futuro del utility

## Verificación

### `pnpm build` (output literal, últimas 5 líneas)

```
public/build/assets/CalendarPage-Dvy9ao-f.js                 48.66 kB │ gzip:  13.16 kB
public/build/assets/MedicalRecordsPage-DXGTSsqJ.js           54.53 kB │ gzip:  14.43 kB
public/build/assets/CashRegisterPage-BAteYsVq.js            121.64 kB │ gzip:  27.28 kB
public/build/assets/chart-CXLAvRhu.js                       208.27 kB │ gzip:  71.53 kB
public/build/assets/app-Dd2tNBMs.js                         477.72 kB │ gzip: 152.58 kB
✓ built in 9.65s
```

**0 errores.** Bundle principal: 477.72 KB (+0.40 KB vs 477.34 del Sprint 3 — esperado, son +0.4 KB de CSS para las nuevas animaciones).

### Diff stats

```
4a2ac51  12 files changed, 64 insertions(+), 20 deletions(-)
```

## Items del Sprint 4 que ya estaban hechos (no requirieron cambio)

Verificado durante la ejecución:

1. **`UiModal.vue` scale + fade**: ya estaba implementado correctamente (líneas 27-34)
   - `enter-from-class="opacity-0 scale-95 translate-y-4"`
   - `enter-to-class="opacity-100 scale-100 translate-y-0"`
   - Duración 300ms ease-out (entrada), 200ms ease-in (salida)
   - **Sin cambios necesarios**

2. **`Toast.vue` slide + scale**: ya estaba implementado correctamente (líneas 3-12)
   - `enter-from-class="opacity-0 translate-y-2 scale-95"`
   - `enter-to-class="opacity-100 translate-y-0 scale-100"`
   - Duración 300ms ease-out (entrada), 200ms ease-in (salida)
   - **Sin cambios necesarios**

## Notas para Sprint 5

El badge "En vivo" agregado en este sprint es **puramente decorativo** — se muestra siempre independientemente del estado real de WebSocket. El indicador real de conexión (verde/gris/glowing) es parte del Sprint 5 (M-UX-2).

Cuando Sprint 5 implemente el indicador real, este badge se puede gatear así:

```vue
<span v-if="wsConnected" class="...">
  <span class="animate-pulse-subtle ..." />
  En vivo
</span>
<span v-else class="...">
  <span class="bg-theme-secondary ..." />
  Sin conexión
</span>
```

## Métricas del plan (avance)

- ✅ **0 colores crudos** (cerrado Sprint 3)
- ✅ **0 spinners morados inline** (cerrado Sprint 2+3)
- ✅ **100% modales con scale-in** (verificado, ya estaba)
- ⏸️ **100% toasts con slide-down** (parcial: Toast sí, NotificationToast ahora sí con TransitionGroup agregado en este sprint)
- ⏸️ **1 indicador visual de "WS conectado"** (badge "En vivo" decorativo agregado, gating real en Sprint 5)
- ⏸️ **0 `window.confirm()` nativos** (PENDIENTE — Sprint 5)
- ⏸️ **17/17 páginas con `<PageHeader>`** (5/17 en Sprint 2, 12/17 pendientes Sprint 6)
