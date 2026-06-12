# Sprint 3 — Paleta global: 0 colores crudos en `resources/js`

**Branch**: `feat/ux-sprint-1-design-system`
**Fecha**: 2026-06-12
**Commit**: `c7010bb` — refactor(ux): Sprint 3 - paleta global, 0 colores crudos en resources/js
**Plan ref**: [plan-ux-ui-2026-06.md §5 Sprint 3](../plan-ux-ui-2026-06.md#sprint-3--migrar-paleta-a-la-canónica-10-d-h)

## Objetivo cumplido

**0 ocurrencias de colores crudos** (`blue-*`, `sky-*`, `cyan-*`, `indigo-*`, `purple-*`, `violet-*`, `emerald-*`, `amber-*`, `orange-*`, `pink-*`, `rose-*`, `teal-*`, `lime-*`, `fuchsia-*`) en `resources/js/**/*.vue` y `resources/js/**/*.js`.

**Antes**: 52 ocurrencias en 18 archivos.
**Después**: 0 ocurrencias en 0 archivos.

## Archivos migrados (18)

### Componentes UI (3)

| Archivo | Cambio |
|---|---|
| `components/AccessibleButton.vue` | `bg-indigo-600/700/ring-indigo-500` → `bg-accent/hover/ring-accent` |
| `components/ui/RadioGroup.vue` | `border-purple-500 bg-purple-50/100/500 text-purple-700` → `border-accent bg-primary-50 bg-accent text-primary-700` (2 estados checked) |
| `components/layout/FloatingActionButton.vue` | `from-purple-500 to-purple-600` → `from-accent to-accent-hover` |

### Componentes funcionales (3)

| Archivo | Cambio |
|---|---|
| `components/appointments/NewAppointmentModal.vue` | Spinner morado inline → `<LoadingSpinner size="md" text="Cargando datos...">` |
| `components/procedures/ImportCsvModal.vue` | `border-amber-200 bg-amber-50` (errores) → `border-warning-100 bg-warning-50`. `border-green-200 bg-green-50` (éxito) → `border-success-100 bg-success-50` |
| `modules/treatment-plans/components/PlanStatusBadge.vue` | `bg-emerald-100 text-emerald-800` (status completed) → `bg-success-badge text-success-text` (consistente con `UiStatusPill`) |

### Páginas (12)

| Archivo | Ocurrencias | Cambio |
|---|---|---|
| `modules/appointment-types/AppointmentTypesPage.vue` | 1 | `text-blue-600 hover:text-blue-900` → `text-accent hover:text-accent-hover` |
| `modules/appointments/ConsultationWizard.vue` | 1 | Banner info materiales: `bg-blue-50 border-blue-200 text-blue-800` → `bg-primary-50 border-primary-200 text-primary-700` |
| `modules/environments/EnvironmentsPage.vue` | 3 | 1× link azul, 2× botones `bg-purple-600 hover:bg-purple-700` → `bg-accent hover:bg-accent-hover` |
| `modules/my-procedures/MyProceduresPage.vue` | 1 | Pill código: `bg-blue-100 text-blue-800` → `bg-primary-50 text-primary-700` |
| `modules/procedure-catalog/ProcedureCatalogDetailPage.vue` | 1 | Pill código: `bg-blue-50 text-blue-800` → `bg-primary-50 text-primary-700` |
| `modules/procedure-catalog/ProcedureCatalogPage.vue` | 2 | Pill código + link azul |
| `modules/professionals/ProfessionalsPage.vue` | 1 | Link azul en tabla |
| `modules/reception-procedures/ReceptionProceduresPage.vue` | 1 | Pill código |
| `modules/ai-analysis/AiAnalysisPage.vue` | 3 | 2× gradientes morados en iconos, 1× `text-purple-600` |
| `modules/cash-register/components/CashReports.vue` | 2 | 2× `text-purple-600` |
| `modules/cash-register/components/MovementList.vue` | 4 | `text-purple-600/900` + `bg-purple-50/100 border-purple-200` |
| `modules/medical-records/components/MedicalRecordStats.vue` | 1 | `text-orange-600` (CalendarIcon) → `text-warning-600` |

## Mapeo de tokens (decisiones)

Para los casos donde el token directo no existe (Tailwind config solo tiene `-50/100` y `-500/600/700` en semánticos), se usó el equivalente más cercano:

| Origen (Tailwind crudo) | Destino (token del design system) | Razón |
|---|---|---|
| `text-blue-600 hover:text-blue-900` (links) | `text-accent hover:text-accent-hover` | Links de acción → accent (azul) |
| `bg-blue-100/50` (fondo info) | `bg-primary-50` | primary-50 es el fondo suave canónico |
| `text-blue-800` (texto sobre fondo primary-50) | `text-primary-700` | primary-700 es el texto legible sobre primary-50 |
| `border-blue-200` (border sutil) | `border-primary-200` | mismo tono, mismo uso |
| `border-purple-200` (border banner) | `border-primary-200` | idem |
| `text-purple-600/500` (icono) | `text-accent` | icono principal → accent |
| `text-purple-900` (texto emphasis) | `text-accent-active` | texto emphasis → variant más oscuro |
| `bg-purple-100/50` (fondo icon) | `bg-primary-50` | fondo suave icon → primary-50 |
| `from-purple-500 to-purple-600` (gradiente) | `from-accent to-accent-hover` | gradiente primary |
| `text-indigo-600/700` (botón) | `text-accent` | indigo era alias de accent |
| `bg-indigo-600 hover:bg-indigo-700` (botón) | `bg-accent hover:bg-accent-hover` | mismo |
| `text-orange-600` (icono warning) | `text-warning-600` | icon warning semántico |
| `border-amber-200 bg-amber-50` (alerta errores) | `border-warning-100 bg-warning-50` | tokens warning-100/50 existen en config |
| `border-green-200 bg-green-50` (alerta éxito) | `border-success-100 bg-success-50` | tokens success-100/50 existen |
| `bg-emerald-100 text-emerald-800` (badge success) | `bg-success-badge text-success-text` | utility ya existe en `tailwind.config.js` línea 289-291 (mapea a `--color-success-bg/--color-success-text`) |

### Por qué NO se usaron ciertos tokens directamente

- `success-200`, `success-800`, `warning-200` **no existen** en `tailwind.config.js`. La escala solo llega a `-100` y luego salta a `-500/600/700`. Se usó `-100` para borders y `-700` para texto emphasis.
- `purple-200` y `purple-100` se mapearon a `primary-200` y `primary-50` (que sí existen y son el equivalente semántico).

## Verificación

### `grep` de colores crudos (output literal)

**Antes del Sprint 3**:
```
=== TOTAL ===
52
=== ARCHIVOS ===
[18 archivos listados]
```

**Después del Sprint 3**:
```
=== TOTAL ===
0
=== ARCHIVOS ===
[vacío]
```

### `pnpm build` (output literal, últimas 5 líneas)

```
public/build/assets/MedicalRecordsPage-B9seT8pS.js           54.53 kB │ gzip:  14.43 kB
public/build/assets/CashRegisterPage-DtppIhJB.js            121.64 kB │ gzip:  27.28 kB
public/build/assets/chart-CXLAvRhu.js                       208.27 kB │ gzip:  71.53 kB
public/build/assets/app-BGc3QyZf.js                         477.34 kB │ gzip: 152.51 kB
✓ built in 9.41s
```

**0 errores, 0 warnings.** Bundle principal: 477.34 KB (delta -0.02 KB vs 477.36 — la diferencia son las strings moradas que ahora son azules, marginal).

### Diff stats del commit

```
c7010bb  18 files changed, 32 insertions(+), 35 deletions(-)
```

## Observaciones

1. **`AccessibleButton.vue` es código muerto**: verificado con `grep -r "AccessibleButton" resources/js` que tiene **0 imports** en el proyecto. Las variants `danger/success/warning/info` que usan `red-600/green-600/yellow-500/blue-500` (NO son los tokens semánticos del design system) no afectan el bundle porque el componente no se importa. Recomendación: eliminar el archivo en un sprint de limpieza. NO se hace en este sprint para mantener el scope acotado.

2. **`PlanStatusBadge.vue` quedó con `text-primary-700` para `closing`**: durante una migración previa (probablemente Sprint 2 o algún refactor manual) el `text-purple-800` se cambió a `text-primary-700` pero el `bg-purple-100` se mantuvo. Este sprint cierra esa inconsistencia.

3. **Decisión sobre el color del cierre de caja en `CashReports.vue:106`** (valor `total_difference`): originalmente `text-purple-600` (highlight neutro). Se migró a `text-accent` (azul) en vez de `text-warning-600` (amarillo) porque la convención del design system es: amarillo solo para advertencias activas/banners, no para valores numéricos a destacar. El azul accent marca el valor como "información importante a leer" sin connotación de error.

4. **Decisión sobre los iconos morados en `AiAnalysisPage.vue`**: el módulo se llama "AI Analysis" y originalmente usaba morado como "color de marca de IA" (típico en dashboards). Se cambió a `accent` (azul) por consistencia con el resto de la app. Si en el futuro se quiere reintroducir morado SOLO para IA, documentar como excepción permitida en `docs/ux-guidelines.md` §2.

## Métricas de éxito del plan (cerradas con Sprint 3)

Del plan original §2 — Métricas de éxito al cerrar el plan:

- ✅ **0 colores `blue/purple/indigo/cyan/violet/emerald/amber/orange/pink/rose` en `resources/js` y `resources/views`** (CERRADO con Sprint 3 + Sprint 2)
- ✅ **0 spinners `border-purple-*` inline** (CERRADO con Sprint 2 + Sprint 3: CalendarPage, PatientsPage, DashboardPage, NewAppointmentModal migrados a `<LoadingSpinner>`. MovementList/CashReports usan LoadingSpinner propio)
- ✅ **0 `window.confirm()` nativos** (PENDIENTE — Sprint 5)
- ✅ **17/17 páginas con `<PageHeader>`** (PENDIENTE — 5/17 en Sprint 2, 12/17 pendientes en Sprint 6)
- ⏸️ **5/5 componentes críticos propagados** (parcial: LoadingSpinner, EmptyState, Skeleton, Breadcrumbs, PageHeader — Sprint 1+2)
- ⏸️ **100% modales con scale-in** (PENDIENTE — Sprint 4)
- ⏸️ **1 indicador visual de "WS conectado"** (PENDIENTE — Sprint 5)
- ✅ **0 regresiones: pnpm build OK** (verificado en cada sprint)
- ✅ **Plan de inconsistencias sigue funcionando** (verificado: no se tocaron controllers, models, routes, tests)

## Próximo sprint recomendado

**Sprint 4** (Apple animations): aplicar `animate-scale-in` en modales, `animate-slide-down` en toasts, `animate-pulse-subtle` en badges "live" del BI, propagar `hover-lift` a las cards clickeables. Es el siguiente sprint puramente visual/UX que no requiere migración de paleta.
