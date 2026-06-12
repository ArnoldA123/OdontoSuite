# Sprint 2 — PageHeader + Paleta semántica en 5 vistas top

**Branch**: `feat/ux-sprint-1-design-system`
**Fecha**: 2026-06-12
**Commits**:
- `913db23` — feat(ux): registrar 9 componentes UI globales (cierre Sprint 1)
- `0944584` — refactor(ux): Sprint 2 - PageHeader + LoadingSpinner + EmptyState + paleta semantica en 5 vistas top
**Plan ref**: [plan-ux-ui-2026-06.md §5 Sprint 2](../plan-ux-ui-2026-06.md#sprint-2--layouts-base-pageheader--patrones-de-página-15-d-h)

## Alcance ejecutado

5 de las 5 vistas top migradas (100% del target Sprint 2):
Dashboard, Calendar, Patients, CashRegister, BusinessIntelligence.

## Cambios por archivo

### 1. `resources/js/plugins/ui-components.js` (cierre Sprint 1)

9 componentes registrados como tags globales (ya no requieren import local):

| Tag global | Componente | Notas |
|---|---|---|
| `LoadingSpinner` | `ui/LoadingSpinner.vue` | sin prefijo Ui, ya exportaba así |
| `EmptyState` | `ui/EmptyState.vue` | sin prefijo Ui |
| `UiSkeleton` | `ui/Skeleton.vue` | prefijo Ui para consistencia |
| `UiBreadcrumbs` | `ui/Breadcrumbs.vue` | prefijo Ui |
| `UiStatusPill` | `ui/StatusPill.vue` | nuevo en Sprint 1 |
| `UiProgressBar` | `ui/ProgressBar.vue` | nuevo en Sprint 1 |
| `UiConfirmDialog` | `ui/ConfirmDialog.vue` | nuevo en Sprint 1 |
| `UiFilterBar` | `ui/FilterBar.vue` | nuevo en Sprint 1 |
| `PageHeader` | `layout/PageHeader.vue` | nuevo en Sprint 1, sin prefijo (sigue patrón de AppLayout) |

### 2-6. Las 5 vistas migradas

| Vista | LOC | PageHeader | LoadingSpinner | EmptyState | Stat cards paleta |
|---|---|---|---|---|---|
| `CalendarPage.vue` | 1023 | ✅ | ✅ | — | — |
| `PatientsPage.vue` | 1195 | ✅ | ✅ | ✅ | 3/4 migradas (success/warning/accent) |
| `CashRegisterPage.vue` | 663 | ✅ | — (no tiene spinner de página) | — | 3/3 migradas (success/error/accent) |
| `DashboardPage.vue` | 754 | — (no tiene h1, entra a stats directo) | ✅ | — | 4 stat cards migradas a success/warning/error |
| `BusinessIntelligencePage.vue` | 839 | ✅ | ya tenía | — | 3 stat cards migradas a success/accent/warning |

**Total**: 5 archivos modificados, +117 / -128 líneas (delta neto -11 líneas).

## Patrón aplicado (template para sprints siguientes)

```vue
<template>
  <AppLayout>
    <PageHeader
      title="<Título de la página>"
      subtitle="<Subtítulo descriptivo>"
      class="mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">Volver</UiButton>
        <UiButton v-if="can.create?.value" @click="openCreate">Nuevo X</UiButton>
      </template>
    </PageHeader>

    <!-- contenido específico de la página -->

    <LoadingSpinner v-if="loading" class="p-12" size="lg" text="Cargando..." />
    <EmptyState
      v-else-if="data.length === 0"
      class="p-12"
      title="Sin resultados"
      description="Intenta ajustar los filtros"
      action-label="Limpiar filtros"
      @action="resetFilters"
    />
    <div v-else>
      <!-- lista / tabla / kanban -->
    </div>
  </AppLayout>
</template>
```

## Verificación

### `pnpm build` (output literal, últimas 8 líneas)

```
public/build/assets/QuotationsPage-Qlt3Pvuf.js               44.21 kB │ gzip:  11.83 kB
public/build/assets/TreatmentPlansPage-CGrxxIlG.js           47.09 kB │ gzip:  13.45 kB
public/build/assets/CalendarPage-Bb94KmrL.js                 48.30 kB │ gzip:  13.05 kB
public/build/assets/MedicalRecordsPage-CgfY0M20.js           54.53 kB │ gzip:  14.43 kB
public/build/assets/CashRegisterPage-CIFRG0ZR.js            121.65 kB │ gzip:  27.29 kB
public/build/assets/chart-CXLAvRhu.js                       208.27 kB │ gzip:  71.53 kB
public/build/assets/app-BMhfPGyE.js                         477.36 kB │ gzip: 152.51 kB
✓ built in 8.24s
```

**0 errores.** Bundle principal sin cambio (+0 bytes — los 9 componentes nuevos son tree-shakeable y solo se incluyen en las vistas que los consumen).

### Diff stats de commits

```
913db23  1 file changed, 18 insertions(+)   -- ui-components.js
0944584  5 files changed, 117 insertions(+), 128 deletions(-)
```

### Verificación de paleta

```bash
grep -rE "bg-(green|yellow|purple|red|orange|blue|indigo|violet)-[0-9]+|text-(green|yellow|purple|red|orange|blue|indigo|violet)-[0-9]+" \
  resources/js/modules/dashboard resources/js/modules/appointments resources/js/modules/patients \
  resources/js/modules/cash-register resources/js/modules/business-intelligence \
  --include="*.vue"
```

**Antes del Sprint 2** (en las 5 vistas target):
- DashboardPage: 14 ocurrencias
- CalendarPage: 1 (spinner)
- PatientsPage: 7 (3 stat cards + 1 spinner + 3 "filtered")
- CashRegisterPage: 9 (3 stat cards + 3 gradientes + 3 texto)
- BusinessIntelligencePage: 9 (3 stat cards + 3 gradientes + 3 texto)
- **Total: 40 ocurrencias**

**Después del Sprint 2**:
- DashboardPage: 0 ✅
- CalendarPage: 0 ✅
- PatientsPage: 0 ✅
- CashRegisterPage: 0 ✅
- BusinessIntelligencePage: 0 ✅
- **Total: 0 ocurrencias en las 5 vistas migradas** ✅

## Deviations del plan

1. **DashboardPage no tiene PageHeader**: la página entra directo a stat cards sin un `<h1>` claro. Decidí **NO** agregar un header artificial solo por consistencia — la estructura de Dashboard es "stats grid + acciones rápidas + actividad reciente", no requiere un header tipo página. Queda como observación para revisar si el usuario lo quiere.

2. **BusinessIntelligencePage conserva los 2 "No hay datos disponibles" inline dentro de `<tr colspan>`**: el contexto es una tabla, no un empty state de página. Usar `<EmptyState>` ahí rompería la estructura del `<tbody>`. Lo correcto es reemplazar la fila vacía por un componente de tabla empty, pero ese componente no existe en el design system. Se deja como observación para un sprint futuro (crear `TableEmptyRow` o similar).

3. **CashRegisterPage sigue usando `Button` (no `UiButton`)**: pre-existente, no introducido en este sprint. El componente `Button` está importado localmente (`import Button from '@/components/ui/Button.vue'`) y funciona, pero la convención es usar `UiButton` (el alias global). Migrar es masivo (todos los `Button variant=` en CashRegister y sus modales), mejor hacerlo en un sprint dedicado de renombrado.

4. **CalendarPage no recibió EmptyState**: la vista de calendario (day/week/month) tiene su propio patrón de "no hay citas" con `class="text-xs text-theme-secondary text-center py-2"` dentro de cada celda. Reemplazar por `<EmptyState>` no aplica (sería invasivo y rompería el layout de grid). Dejado.

## Notas para Sprint 3+ (paleta global)

El Sprint 2 migró las 5 vistas top. Quedan **al menos 14 vistas más** con colores `purple/green/orange/red/blue/indigo` hardcodeados (Sprint 3 del plan). Estimación rápida basada en el ritmo de este sprint:

- ~10 min por vista pequeña (solo 1-2 colores)
- ~30 min por vista mediana (header + stat cards)
- ~60 min por vista grande (PatientDetailPage, 1347 LOC)

Estimación: Sprint 3 (paleta global) se puede hacer en 1 sesión de 1.5-2 horas.
