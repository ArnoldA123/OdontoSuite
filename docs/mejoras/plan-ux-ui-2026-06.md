# Plan UX/UI + Realtime + Router — OdontoSuite V2 (alcance total)

> **Fecha**: 2026-06-11 (revisión 2 — alcance expandido)
> **Origen**: feedback directo de Arnold + auditoría de cobertura del design system.
> **Estado**: pendiente de ejecución.
> **Referencia visual**: `TreatmentPlansPage.vue` y sus sub-componentes (la zona mejor lograda del proyecto, base del look & feel).
> **Dependencias**: ninguno (los hallazgos son independientes del `plan-inconsistencias-2026-06-actualizado.md` ya mergeado).

---

## 1. Contexto y oportunidad

OdontoSuite V2 ya tiene **un design system Apple/iCloud completo y bien armado** que solo está siendo usado por una fracción del proyecto. Esta es la base que existe y hay que propagar:

### 1.1 Lo que YA existe (assets reutilizables)

| Capa | Archivo | Qué tiene | Reutilizable |
|---|---|---|---|
| **Tokens CSS** | `resources/css/themes.css` | 30+ CSS vars: `--color-accent` (#0066CC), `--color-text-primary`, `--color-surface-elevated`, `--shadow-sm/md/lg/xl/glass`, `--transition-fast/normal/slow`, `--glass-bg/border/backdrop` | ✅ Listo para usar |
| **Animaciones** | `resources/css/animations.css` | `fadeIn`, `slideInRight/Left/Up/Down`, `scaleIn`, `scaleOut`, `bounceIn`, `bounceOut`, `pulse` | ✅ Listo para usar |
| **Utilities** | `resources/css/utilities.css` | `.z-dropdown/modal/...`, `.glass`, `.hover-lift`, `.focus-ring` | ✅ Listo para usar |
| **App-level** | `resources/css/app.css` | `.glass-card`, `.apple-shadow`, `.apple-shadow-hover`, `.smooth-transition`, `.grid-responsive`, `.btn-touch` | ✅ Listo para usar |
| **Tailwind tokens** | `tailwind.config.js` | `primary` (50-900), `success/warning/error/info`, `neutral`, `fontSize` (xs-4xl), `borderRadius`, `animation`, `backdropBlur`, `boxShadow` | ✅ Listo para usar |
| **Componentes UI** | `resources/js/components/ui/` | 30 componentes: Button (7 variants + ripple), Card (5 variants), Modal (scale+fade), Input (floating label), Select, Badge, Sheet, Toast, Tabs, Skeleton, LoadingSpinner (3 anillos), etc. | ✅ Listo para usar |

### 1.2 Lo que NO existe (gaps a cerrar)

| Gap | Impacto |
|---|---|
| **Guía de uso del design system** (cuándo usar qué utility, qué variant) | Cada dev re-decide, el resultado es inconsistente |
| **Patrones de page-level** (header, filtros, vista lista/kanban, modales) | Cada `Page.vue` reinventa el layout |
| **Componentes faltantes** | No hay `PageHeader`, `EmptyState`, `ConfirmDialog`, `StatusPill`, `ProgressBar` reutilizables. Cada vista los hace ad-hoc. |
| **Componentes UI sin propagar** | `LoadingSpinner`, `EmptyState`, `Pagination`, `Skeleton`, `Breadcrumbs` existen pero casi no se usan |

### 1.3 Problemas reportados por el usuario

1. **Cita nueva no aparece en el calendario sin F5** (CalendarPage + NewAppointmentModal).
2. **Botón "atrás" del browser queda pegado** (`<router-view>` sin `:key`).
3. **Paleta de colores fragmentada** — 19 archivos con `blue/purple/indigo/cyan/violet` Tailwind crudos rompiendo la paleta `primary`/`accent`/`info`.
4. **Animaciones aplicadas de forma desigual** — 10 keyframes definidos, la mitad sin usar, donde se necesitan no se aplican.
5. **Estilo visual inconsistente entre módulos** — solo TreatmentPlans se ve "Apple". El resto se ve "Bootstrap con Tailwind".

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Quick wins UX (router-key + modal-cable) | 0.5 d-h | ✅ HECHO (commit `7626181`) | 2 bugs críticos |
| 1 | Design system: guía + componentes faltantes | 1.5 d-h | ✅ HECHO (commits `2a246a3` + `7e3bdd3` + `8490240` + `913db23`) | 4 componentes nuevos + README + 9 registrados como globales |
| 2 | Layouts base: PageHeader + patrones de página | 1.5 d-h | ✅ HECHO (commit `0944584` + `fa61ccb`) | 5 páginas top migradas (Dashboard, Calendar, Patients, CashRegister, BusinessIntelligence) |
| 3 | Migrar paleta a la canónica | 1.0 d-h | ✅ HECHO (commit `c7010bb` + `fe77929`) | 18 archivos, 52→0 colores crudos |
| 4 | Apple animations: aplicar scale/slide/ripple | 1.0 d-h | ✅ HECHO (commit `4a2ac51` + `957ad0d`) | hover-lift propagado a 12 archivos, NotificationToast slide, badge "En vivo" en Calendar+BI |
| 5 | App-wide UX: WebSocket feedback + ConfirmDialog | 0.75 d-h | ✅ HECHO (commit `a075616`) | useEcho state reactivo, dot WS en header, useConfirm composable, 16 confirm() nativos migrados |
| 6 | Migrar módulos restantes a PageHeader pattern | 3.0 d-h | ✅ HECHO (commit `a219a1b`) | 11 vistas migradas (15/17 total) |
| 7 | Polish final: micro-interacciones, empty states, a11y | 1.5 d-h | ⏳ Pendiente | cross-cutting |
| **Total** | **8 sprints** | **~10.75 d-h** | **9.25 d-h hechos** | (7 de 8 sprints completados) |

Sprints 0-6 ya mergeados a `main`; Sprint 7 es el último.

---

## 3. Hallazgos verificados al 2026-06-11

### 🔴 CRÍTICOS (UX rota, Sprint 0)

#### C-UX-1 — Cita creada desde `NewAppointmentModal` no refresca `CalendarPage`

**Evidencia** (`resources/js/modules/appointments/CalendarPage.vue:437-440`):
```vue
<NewAppointmentModal v-model="showNewAppointmentModal" />
<!-- sin @created, @updated, @saved. handleAppointmentSaved existe pero no está cableado -->
```

El modal **sí emite** los eventos (`NewAppointmentModal.vue:119-122`) pero el padre no los escucha. F5 funciona porque al recargar corre `onMounted → loadAppointments()`.

**Fix**: 2 líneas (agregar `@created @updated @saved` + verificar que `handleAppointmentSaved` esté en el `return` del `setup()`).

#### C-UX-2 — Botón "atrás" del browser pega la UI

**Evidencia** (`resources/js/app.js:172`):
```js
const App = { template: '<router-view />' };
```

Sin `key`, Vue Router **reusa la misma instancia** del componente al ir/volver. `onMounted` no se vuelve a llamar, listeners WebSocket quedan colgados.

**Fix**: `template: '<router-view :key="$route.fullPath" />'`. Una línea.

---

### 🟠 IMPORTANTES (consistencia, Sprints 1-4)

#### I-UX-1 — El design system no está documentado

No existe `docs/ux-guidelines.md` ni `resources/js/design-system/README.md` con reglas de uso. Consecuencia: cada dev decide, el resultado es inconsistente.

**Fix** (Sprint 1): crear `docs/ux-guidelines.md` con:
- Paleta semántica: cuándo usar `accent` vs `primary` vs semánticos (`success/warning/error/info`).
- Componentes UI disponibles y cuándo usar cada uno.
- Patrones de layout (page-header, filtros, vista lista, vista kanban).
- Tabla de animaciones (qué keyframe usar en qué situación).
- Reglas de espaciado (grid 8px), tipografía (sizes del `tailwind.config.js`), sombras (`shadow-soft/medium/large/elevated/glass`).
- Accesibilidad (focus ring, ARIA, prefers-reduced-motion).

#### I-UX-2 — 19 archivos .vue con colores Tailwind crudos rompiendo la paleta

**Evidencia** (grep en `resources/js/**/*.vue`):

```
purple-600: 15 ocurrencias   (CashRegisterPage, CashReports, MovementList, AiAnalysisPage, BI)
purple-100: 4
purple-50:  2
purple-500: 8
indigo-500: 2               (AccessibleButton, BI spinner)
indigo-600: 1
blue-500:   3
blue-50:    2
blue-100:   3
blue-600:   4
blue-800:   5
```

**Top offenders**:
1. `MovementList.vue` — 5 ocurrencias
2. `EnvironmentsPage.vue` — 3
3. `CashReports.vue` — 3
4. `BusinessIntelligencePage.vue` — 3
5. `ProcedureCatalogPage.vue` — 2
6. `AiAnalysisPage.vue` — 2
7. `RadioGroup.vue` — 2 (componente UI reusable — el más peligroso)
8. `CalendarPage.vue` — 1
9. `NewAppointmentModal.vue` — 1
10. `PatientsPage.vue` — 1
11. `app.blade.php` — 3 (`bg-blue-500` en loading skeleton)

**Fix** (Sprint 3): sed + verificación visual. ≤5 ocurrencias justificadas permitidas (con comentario inline).

#### I-UX-3 — 4 componentes UI críticos infrautilizados

| Componente | Existe en | Se usa en | % adopción |
|---|---|---|---|
| `LoadingSpinner` (3 anillos concéntricos, paleta correcta) | `ui/LoadingSpinner.vue` | 0 archivos | **0%** |
| `EmptyState` (icono + título + acción) | `ui/EmptyState.vue` | 1-2 archivos | **~10%** |
| `Pagination` (numerada) | `ui/Pagination.vue` | 2 archivos | **~10%** |
| `Skeleton` (loading placeholder) | `ui/Skeleton.vue` | 0 archivos | **0%** |
| `Breadcrumbs` | `ui/Breadcrumbs.vue` | 0 archivos | **0%** |

En su lugar, cada vista implementa su propio skeleton/spinner inline con `border-purple-200 border-t-purple-600` o `bg-gray-200 animate-pulse`.

**Fix** (Sprint 1 + 2): propagar uso en sprints de migración.

#### I-UX-4 — Componentes UI faltantes

Estos componentes se reinventan ad-hoc en cada página:

| Componente faltante | Por qué falta | Reusado en (mínimo) |
|---|---|---|
| `PageHeader` (título + subtítulo + acciones + breadcrumbs) | 17 páginas reinventan | 17 páginas |
| `StatusPill` (badge con color semántico) | 8 páginas reinventan | 8 páginas |
| `ProgressBar` (con threshold de color) | 3 páginas reinventan | 3 páginas (TreatmentPlans, Quotations, TreatmentPlanDetail) |
| `ConfirmDialog` (modal de confirmación) | 6 páginas usan `window.confirm` nativo | 6 páginas |
| `EmptyState` ya existe, falta **propagarlo** | — | (ver I-UX-3) |
| `FilterBar` (filtros siempre visibles tipo iCloud) | 5 páginas reinventan | 5 páginas (TreatmentPlans, Patients, ProcedureCatalog, MyProcedures, ReceptionProcedures) |
| `ViewToggle` (botón segmentado lista/kanban) | 1 página reinventa | 1 página (TreatmentPlans) — **promover a componente reusable** |
| `QuickFilters` (pills de 1-click) | 1 página reinventa | 1 página — **promover a reusable** |

**Fix** (Sprint 1): crear 5 componentes nuevos (`PageHeader`, `StatusPill`, `ProgressBar`, `ConfirmDialog`, `FilterBar`).

#### I-UX-5 — Patrón de página inconsistente

Solo `TreatmentPlansPage` tiene el layout "Apple completo":
- Header con `PageHeader` (título + subtítulo + acciones)
- Counter pills con números (Todos/Activos/Vencidos)
- Filtros siempre visibles
- Quick filters (1 click)
- View toggle (lista/kanban)
- Empty state bien diseñado
- Skeleton cards durante carga
- Drag & drop en kanban

**Las otras 16 páginas** reinventan este patrón con calidad variable.

**Fix** (Sprint 2 + 6): migrar las 17 páginas al patrón `PageHeader` + `FilterBar` + skeleton + empty state.

---

### 🟡 MEJORAS (Sprints 4-7)

#### M-UX-1 — Animaciones dispersas, sin guía de uso

10 keyframes definidos en `tailwind.config.js` + 8 más en `animations.css`:
- `animate-fade-in` — usado en 6+ headers de página
- `animate-spin` — usado en 8+ spinners (todos con colores incorrectos)
- `animate-slide-*`, `animate-scale-*` — usados en **0 lugares**
- `animate-bounce-subtle`, `animate-pulse-subtle` — usados en **0 lugares**

**Fix** (Sprint 4): documentar guía de animaciones + aplicar scale-in a `UiModal`, pulse-subtle a badges "live" de BI, slide-down a toasts, bounce-subtle a CTAs de cita próxima.

#### M-UX-2 — `useEcho.js` traga todos los errores de WebSocket

**Evidencia** (`resources/js/composables/useEcho.js:41-82`): los `if` tienen cuerpo vacío, los `catch` están vacíos. Cuando Reverb está caído (escenario común en dev), el usuario no recibe feedback.

**Fix** (Sprint 5): `console.warn` + `toast.warning` tras N intentos. Indicador visual "WS conectado/desconectado" en header del AppLayout.

#### M-UX-3 — `window.confirm()` nativo en 6 lugares

`TreatmentPlanCard.vue:250` y al menos 5 más usan `window.confirm()` nativo del browser, rompiendo el look Apple. **Fix** (Sprint 5): crear `ConfirmDialog.vue` + migrar los 6 sitios.

#### M-UX-4 — `design-system/tokens.js` huérfano

Verificado: **0 imports** en `resources/js/`. Duplica valores de `tailwind.config.js`. **Fix** (Sprint 1): eliminar o consolidar.

#### M-UX-5 — `app.blade.php` con `bg-blue-500` en loading skeleton

Líneas 9, 13, 22, 25 con azul Tailwind crudo. Se ve antes de que Vue se monte. **Fix** (Sprint 3): alinear con `var(--color-accent)`.

#### M-UX-6 — Sin atajos de teclado cross-app

Solo `TreatmentPlansPage` tiene atajos (`N` para nuevo, `Esc` para cerrar). Es un diferenciador Apple que se debería propagar.

**Fix** (Sprint 7): composable `useGlobalShortcuts` con bindings: `N` = nuevo (en módulo actual), `/` = focus search, `Esc` = cerrar modal, `?` = ayuda. **Out of scope** del plan base, propuesto como follow-up.

---

## 4. Decisiones de diseño (transversales a todos los sprints)

### 4.1 Paleta canónica (migrar todo a esto)

| Caso | Utility CSS | Hex |
|---|---|---|
| **Acción primaria** | `bg-accent` | `#0066CC` |
| **Acción hover** | `bg-accent-hover` | `#0052a3` |
| **Acción active/pressed** | `bg-accent-active` | `#003d7a` |
| **Acento fondo suave** | `bg-primary-50` / `bg-accent-light` | `#e6f0ff` |
| **Texto en acento** | `text-primary-700` | `#003d7a` |
| **Éxito** | `bg-success-500` / `bg-success-badge` | `#10b981` / `#d1fae5` |
| **Advertencia** | `bg-warning-500` / `bg-warning-badge` | `#f59e0b` / `#fef3c7` |
| **Error / peligro** | `bg-error-500` / `bg-danger-badge` | `#ef4444` / `#fee2e2` |
| **Info** | `bg-info-500` | `#0066CC` |
| **Texto principal** | `text-theme-primary` | `#1D1D1F` |
| **Texto secundario** | `text-theme-secondary` | `#86868B` |
| **Borde** | `border-theme` | `#d2d2d7` |
| **Surface (cards)** | `bg-theme-surface-elevated` | `#FFFFFF` |
| **Background** | `bg-theme-background` | `#FFFFFF` |
| **Fondo secundario** | `bg-theme-background-secondary` | `#F5F5F7` |

**Prohibido** (a partir de Sprint 3): clases Tailwind crudas `bg-blue-*`, `text-purple-*`, `bg-indigo-*`, `text-cyan-*`, `bg-sky-*`, `bg-violet-*`, `bg-amber-*`, `bg-orange-*`, `bg-pink-*`, `bg-rose-*`, `bg-emerald-*`, `bg-teal-*`, `bg-fuchsia-*`, `bg-lime-*`, `text-gray-*` (excepto `text-gray-100/300/600/800` que son casi-neutrales y se usan para texto deshabilitado).

**Excepciones permitidas** (con comentario inline `<!-- uso intencional: ... -->`):
- Verde de success explícito (`bg-emerald-500`) — no aplica, usar `bg-success-500`.
- Amarillo de warning (`bg-amber-500`) — no aplica, usar `bg-warning-500`.

### 4.2 Tipografía Apple/iCloud

`tailwind.config.js` ya tiene la escala correcta (xs=11px, sm=13px, base=15px, lg=17px, xl=20px, 2xl=24px, 3xl=28px, 4xl=34px). **Patrones de uso**:

| Tamaño | Caso |
|---|---|
| `text-xs` (11px) | Labels pequeños, captions, kbd shortcuts |
| `text-sm` (13px) | Body secundario, tabla de datos |
| `text-base` (15px) | Body principal, descripciones |
| `text-lg` (17px) | Subtítulos, card title |
| `text-xl` (20px) | h3, dialog title |
| `text-2xl` (24px) | h2, modal title grande |
| `text-3xl` (28px) | h1, page title |
| `text-4xl` (34px) | Display (hero) |

Font family: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto` (ya configurado en `tailwind.config.js`).

Letter spacing: usar `tracking-tight` (-0.01em) en títulos `h1`/`h2`/`h3`, `tracking-normal` en body.

Line height: el `tailwind.config.js` ya tiene los LH correctos (1.45 en xs, 1.4 en sm, 1.47 en base, 1.41 en lg, 1.4 en xl, 1.33 en 2xl, 1.29 en 3xl, 1.18 en 4xl). Respetar.

### 4.3 Sombras Apple/iCloud

5 niveles (ya en `tailwind.config.js`):

| Token | Valor | Caso |
|---|---|---|
| `shadow-subtle` | `0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)` | Cards en reposo |
| `shadow-soft` | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` | Cards hover, dropdowns |
| `shadow-medium` | `0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)` | Botones hover, popovers |
| `shadow-large` | `0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)` | Modales, sheets |
| `shadow-elevated` | `0 25px 50px -12px rgba(0,0,0,0.25)` | Elementos que sobresalen (toasts, tooltips) |
| `shadow-glass` | `0 8px 32px rgba(31,38,135,0.37)` | Glass morphism (cards con backdrop-blur) |

### 4.4 Border radius Apple/iCloud

| Token | Valor | Caso |
|---|---|---|
| `rounded-md` | 10px | Inputs |
| `rounded-lg` | 12px | Botones, badges |
| `rounded-xl` | 16px | Cards |
| `rounded-2xl` | 20px | Modales |
| `rounded-3xl` | 24px | Sheets (side panel) |
| `rounded-full` | 9999px | Avatares, pills, status badges |

### 4.5 Animaciones (cuándo usar cada una)

| Animación | Cuándo | Duración |
|---|---|---|
| `animate-fade-in` | Carga inicial de página / sección | 0.3s |
| `animate-scale-in` | Modal abre, badge aparece, ripple button | 0.2s |
| `animate-slide-up` | Modal abre (contenido), drawer abre | 0.3s |
| `animate-slide-down` | Toast entrando, dropdown | 0.3s |
| `animate-bounce-subtle` | Llamar atención (cita próxima en 30 min) | 0.6s loop |
| `animate-pulse-subtle` | Indicador "live" / "sincronizando" / Reverb conectado | 2s loop |
| Ripple (Button.vue) | Click en cualquier botón primario | 0.6s |

**Regla de oro**: animar `transform` y `opacity` solo, NUNCA `width`/`height`/`top`/`left`. Las animaciones de transform usan GPU, las de layout no.

### 4.6 Accesibilidad (a11y)

- **Focus ring**: ya está en `app.css` (`box-shadow: 0 0 0 2px var(--color-accent)`). NUNCA usar `outline: none` sin reemplazo.
- **prefers-reduced-motion**: ya respetado en `animations.css` y `LoadingSpinner.vue`. Propagar al resto.
- **ARIA**: `aria-label` en icon-buttons, `aria-live="polite"` en toasts y contadores, `role="status"` en spinners, `aria-haspopup/expanded` en menús (ya hay ejemplo en `TreatmentPlanCard.vue:111`).
- **Touch targets**: `min-h-[44px]` en botones, `min-h-[36px]` en botones pequeños. Ya en `tailwind.config.js` `sizes`.

### 4.7 Router key — por qué fullPath y no path

`$route.fullPath` incluye query string (`/patients?status=active`), `$route.path` solo la ruta. Si el usuario está en `/patients?status=active` y va a `/patients/123` y vuelve, con `path` el componente no remonta porque la ruta es la misma. Con `fullPath`, cualquier cambio remonta.

Trade-off aceptado: se pierde scroll position y estado de forms al cambiar de página. Para una app de gestión clínica, aceptable. Si en el futuro se quiere preservar, agregar `<keep-alive :include="['DashboardPage', 'CalendarPage']">` selectivo.

---

## 5. Roadmap de implementación (8 sprints)

> Estimaciones en **días-hombre** de Arnold (1 d-h ≈ 4-5 h reales).
> Cada sprint es mergeable independientemente.

---

### Sprint 0 — Quick wins UX (0.5 d-h) — ✅ **HECHO 2026-06-11** (commit `7626181`)

**Objetivo**: arreglar los 2 bugs que rompen la experiencia diaria.

**Branch**: `fix/ux-sprint-0-quick-wins` → mergeada a `feat/ux-sprint-1-design-system` (donde se continuó el trabajo).

**Tareas**:
- [x] **C-UX-1**: `CalendarPage.vue` línea 437-440 — agregar `@created="handleAppointmentSaved" @updated="handleAppointmentSaved" @saved="handleAppointmentSaved"` al `<NewAppointmentModal>`. Verificar que `handleAppointmentSaved` está en el `return` del `setup()` (línea 968-1011).
- [x] **C-UX-2**: `app.js` línea 172 — cambiar `template: '<router-view />'` a `template: '<router-view :key="$route.fullPath" />'`.

**Verificación** (Sprint 0):
- `pnpm build` → 0 errores.
- **C-UX-1**: crear cita desde modal en vista semana → la cita aparece en la grilla sin recargar. Editar cita existente → cambios se reflejan.
- **C-UX-2**: navegar a `/calendar` → click en un paciente (`/patients/1`) → botón "atrás" del browser → vuelve a `/calendar` y el componente remonta (spinner de carga visible, listeners WS re-suscritos, filtros reinicializados a "hoy").

**Commit real**: `fix(ux): C-UX-1 modal refresh + C-UX-2 router-view key` (hash `7626181`).

---

### Sprint 1 — Design system: guía + componentes faltantes (1.5 d-h) — ✅ **HECHO 2026-06-12** (commits `2a246a3` + `7e3bdd3` + `8490240` + `913db23`)

**Objetivo**: cerrar la deuda de "no hay guía + faltan componentes base". Este sprint **desbloquea** todos los demás (los sprints 2-7 consumen los componentes que se crean acá).

**Branch**: `feat/ux-sprint-1-design-system`

**Tareas**:
- [ ] **I-UX-4 (5 componentes nuevos)**:
  - `resources/js/components/layout/PageHeader.vue` — props: `title`, `subtitle`, `breadcrumbs?`, slot para acciones. Layout: título h1 + subtítulo p + slot a la derecha. Sigue patrón de `TreatmentPlansPage.vue:5-32`.
  - `resources/js/components/ui/StatusPill.vue` — props: `status` (string), `variant?` (auto si se omite). Mapea status → color semántico. Mínimo: 8 status predefinidos (scheduled, confirmed, in_consultation, completed, cancelled, no_show, draft, proposed, approved, in_progress). Sigue patrón de `PlanStatusBadge.vue`.
  - `resources/js/components/ui/ProgressBar.vue` — props: `value` (0-100), `thresholds?` (default `{low:30, mid:60, high:90}`). Color dinámico: rojo < 30, ámbar 30-60, verde claro 60-90, verde fuerte ≥ 90. Patrón de `TreatmentPlanCard.vue:319-343`.
  - `resources/js/components/ui/ConfirmDialog.vue` — usa `Teleport` + `UiModal` internamente. Props: `modelValue`, `title`, `message`, `confirmText?` (default "Confirmar"), `cancelText?` (default "Cancelar"), `variant?` (default/danger). Emite `confirm`/`cancel`. Reemplaza los 6 `window.confirm()` nativos.
  - `resources/js/components/ui/FilterBar.vue` — slot-based, layout grid. Props: `columns?` (default 4). Sigue patrón de `TreatmentPlansPage.vue:36-75`.
- [ ] **I-UX-1**: crear `docs/ux-guidelines.md` con la guía transcrita de §4.1-§4.6. **Una sola fuente de verdad**.
- [ ] **M-UX-4**: eliminar `resources/js/design-system/tokens.js` (no se usa, `tailwind.config.js` es la única fuente). Dejar `resources/js/design-system/` con solo el README.
- [ ] **I-UX-3**: propagar `LoadingSpinner` existente en al menos 1 vista crítica (Dashboard o BI) para validar la integración.

**Verificación**:
- `pnpm build` → 0 errores.
- Storybook manual (sin tool): abrir cada componente nuevo en una vista de prueba (puede ser un page temporal `/dev/components` o un test page) y verificar render correcto. **O** al menos importar cada uno en una página existente y verificar que renderiza.
- `grep -r "design-system/tokens" resources/js` → 0.
- C-UX-1 y C-UX-2 siguen funcionando (regresión 0).

**Commit**: `feat(ux): I-UX-1 design system guide, I-UX-3 LoadingSpinner propagation, I-UX-4 5 nuevos componentes, M-UX-4 eliminar tokens.js`.

---

### Sprint 2 — Layouts base: PageHeader + patrones de página (1.5 d-h) — ✅ **HECHO 2026-06-12** (commits `0944584` + `fa61ccb`)

**Objetivo**: migrar las 5 páginas más usadas al patrón `PageHeader` + `FilterBar` (cuando aplique) + `EmptyState` + `Skeleton`.

**Branch**: `feat/ux-sprint-2-page-headers` (mergeada a `feat/ux-sprint-1-design-system`)

**Páginas target** (5 de 17 — las más usadas, en orden de impacto):

| # | Página | LOC actual | Esfuerzo | Estado |
|---|---|---|---|---|
| 1 | `DashboardPage.vue` | 754 | 0.4 d-h | ✅ migrada (header omitido por diseño, sí LoadingSpinner + paleta) |
| 2 | `CalendarPage.vue` | 1023 | 0.3 d-h (sin FilterBar — usa view-controls propios) | ✅ migrada |
| 3 | `PatientsPage.vue` | 1195 | 0.4 d-h (es la más grande) | ✅ migrada |
| 4 | `CashRegisterPage.vue` | 663 | 0.2 d-h | ✅ migrada |
| 5 | `BusinessIntelligencePage.vue` | 839 | 0.2 d-h | ✅ migrada |

**Tareas**:
- [x] Reemplazar header ad-hoc de cada página por `<PageHeader title="..." subtitle="...">` + slot para acciones. Usar `Breadcrumbs` cuando aplique (rutas anidadas: `/patients/:id`, `/procedure-catalog/:id`, etc.).
- [x] En `PatientsPage`, `BusinessIntelligencePage`, `DashboardPage`: extraer la sección de filtros (si la hay) a `<FilterBar>`. Si ya tienen, dejar como está y solo ajustar spacing.
- [x] Reemplazar loading inline (spinner morado o "Cargando...") por `<Skeleton type="card" :count="6" />` o `<LoadingSpinner size="lg" text="Cargando..." />`.
- [x] Reemplazar empty state ad-hoc por `<EmptyState icon="..." title="..." description="..." action-label="..." @action="..." />`.

**Verificación** (real, no estimada):
- `pnpm build` → 0 errores (8.24s).
- 5 vistas con header consistente, mismo espaciado (p-6), misma altura (h-16 vía PageHeader).
- Loading states visibles al recargar (LoadingSpinner migrado en 3 vistas: Calendar, Patients, Dashboard).
- Empty states: solo PatientsPage recibió `<EmptyState>` (las otras 4 no lo necesitaban o no aplicaba por contexto de tabla/grid).
- 0 colores crudos en las 5 vistas (migración combinada con Sprint 3).
- Diff stats: 5 archivos, +117 / -128 líneas.

**Commit real**: `refactor(ux): Sprint 2 - PageHeader + LoadingSpinner + EmptyState + paleta semantica en 5 vistas top` (hash `0944584`) + `docs(ux): Sprint 2 deliverable` (hash `fa61ccb`).

**Deviations documentadas en `docs/mejoras/sprint-2-deliverable.md`**: DashboardPage no recibió PageHeader (entra directo a stat cards), BusinessIntelligencePage conserva 2 "No hay datos" inline (contexto tabla), CashRegisterPage sigue usando `Button` (no `UiButton`), CalendarPage no recibió EmptyState (las celdas "Sin citas" son parte del grid).

---

### Sprint 3 — Migrar paleta a la canónica (1.0 d-h) — ✅ **HECHO 2026-06-12** (commits `c7010bb` + `fe77929`)

**Objetivo**: 0 colores `blue/purple/indigo/cyan/violet` en `resources/js` (excepto ≤5 con justificación).

**Branch**: `refactor/ux-sprint-3-palette` (mergeada a `feat/ux-sprint-1-design-system`)

**Tareas**:
- [x] **I-UX-2**: para cada uno de los 18 archivos offenders (verificados en §3 I-UX-2), reemplazar las clases Tailwind crudas por utilities semánticas. Reglas aplicadas:
  - `bg-purple-600` → `bg-accent` (o semántico según contexto: success/warning/error)
  - `text-purple-600` → `text-accent`
  - `bg-purple-50/100` → `bg-primary-50` (azul muy claro)
  - `border-purple-200` → `border-primary-200`
  - `text-blue-600/900` → `text-accent` / `text-accent-hover`
  - `bg-blue-50/100/200` → `bg-primary-50` / `border-primary-200`
  - `bg-indigo-500/600` → `bg-accent`
  - `text-indigo-600/700` → `text-accent`
  - `ring-indigo-500` → `ring-accent`
  - `text-orange-600` → `text-warning-600`
  - `border-amber-200 bg-amber-50` → `border-warning-100 bg-warning-50`
  - `border-green-200 bg-green-50` → `border-success-100 bg-success-50`
  - `bg-emerald-100 text-emerald-800` → `bg-success-badge text-success-text`
- [x] **I-UX-2 prioridad aplicada** (orden de migración):
  1. `RadioGroup.vue` (componente UI — reusado en 4+ páginas, máxima propagación).
  2. `MovementList.vue` (4 ocurrencias).
  3. `EnvironmentsPage.vue`, `CashReports.vue`, `ProcedureCatalogPage.vue`, `AiAnalysisPage.vue` (2-3 c/u).
  4. Resto (1 ocurrencia c/u).
- [ ] **M-UX-5**: alinear `app.blade.php` con `var(--color-accent)`. **PENDIENTE** — `app.blade.php` sigue con `bg-blue-500` en 3 ocurrencias del loading skeleton. Decidido dejarlo para un sprint dedicado de pulido (no es bloqueador; el loading skeleton se ve ~200ms antes de montar Vue).

**Verificación** (real, no estimada):
- `pnpm build` → 0 errores (9.41s).
- `grep -rE "(bg|text|border|ring|hover:bg|hover:text|hover:border|focus:ring|focus:border|from|to|via)-(blue|sky|cyan|indigo|purple|violet|emerald|amber|orange|pink|rose|teal|lime|fuchsia)-[0-9]+" resources/js --include="*.vue" --include="*.js"` → **0 ocurrencias, 0 archivos** (antes: 52 ocurrencias, 18 archivos).
- Recorrido visual de las 17 páginas: sin manchas moradas/índigo/naranjas.
- C-UX-1, C-UX-2, Sprints 1-2 siguen funcionando (regresión 0).
- Diff stats: 18 archivos, +32 / -35 líneas.

**Commit real**: `refactor(ux): Sprint 3 - paleta global, 0 colores crudos en resources/js` (hash `c7010bb`) + `docs(ux): Sprint 3 deliverable` (hash `fe77929`).

**Observaciones documentadas en `docs/mejoras/sprint-3-deliverable.md`**:
1. `AccessibleButton.vue` es código muerto (0 imports). Marcado para eliminar en sprint de limpieza.
2. `total_difference` en CashReports migrado a `text-accent` (no `warning`) por convención.
3. "Morado de marca de IA" en AiAnalysisPage migrado a accent. Si se quiere reintroducir, documentar como excepción en `docs/ux-guidelines.md` §2.

---

### Sprint 4 — Apple animations: aplicar scale/slide/ripple (1.0 d-h)

**Objetivo**: las animaciones definidas se aplican en los lugares correctos.

**Branch**: `feat/ux-sprint-4-animations`

**Tareas**:
- [ ] **M-UX-1 (4 aplicaciones concretas)**:
  1. `UiModal.vue`: cambiar entrada de `transition-opacity` a `transition-all` con `scale-95 → scale-100` + `translate-y-4 → translate-y-0` + `opacity-0 → opacity-100`. **Ya está implementado** (líneas 27-34), solo verificar que en todos los modales se aplica.
  2. `Toast.vue` / `NotificationToast.vue`: aplicar `animate-slide-down` al entrar, `animate-slide-up` al salir.
  3. `BusinessIntelligencePage.vue`: agregar dot `animate-pulse-subtle` junto al título "Actualizado en vivo" cuando WS está conectado.
  4. `CalendarPage.vue`: badge "En vivo" / "Tiempo real" con `animate-pulse-subtle`.
- [ ] Verificar que `Button.vue` ya tiene ripple (sí, está implementado en líneas 143-164). **Sin cambios**.
- [ ] **Hover lift** sutil: agregar `hover-lift` utility class (ya existe en `utilities.css` línea ~25) a todas las cards clickeables (`TreatmentPlanCard`, `PatientCard`, `QuotationCard`, etc.). Hoy tienen `hover:shadow-md` que es similar pero no idéntico. Migrar a `hover-lift` para consistencia.

**Verificación**:
- `pnpm build` → 0 errores.
- Abrir un modal → ver scale-up + fade-in (no solo fade).
- Lanzar un toast → ver slide-down.
- Vista de BI con WS conectado → ver dot pulsando.
- Card hover → ver lift sutil (-1px translateY).
- Sprints 0-3 siguen funcionando (regresión 0).

**Commit**: `feat(ux): M-UX-1 aplicar scale/slide/pulse/ripple animations, propagar hover-lift a cards`.

---

### Sprint 5 — App-wide UX: WebSocket feedback + ConfirmDialog (0.75 d-h) — ✅ **HECHO 2026-06-12** (commit `a075616`)

**Objetivo**: feedback de WebSocket + eliminar `window.confirm()` nativos.

**Branch**: `feat/ux-sprint-5-feedback` (mergeada a `main` via `--no-ff`)

**Tareas**:
- [x] **M-UX-2**: `useEcho.js` — reescrito con:
  - State reactivo singleton `connectionStatus` ('connecting'|'connected'|'disconnected'|'unavailable')
  - `console.warn` en todos los `catch` vacíos (PusherError, TransportError, state_change, unavailable, failed)
  - Backoff exponencial en reconexion: 5s → 10s → 20s → 40s → 60s (cap), max 10 reintentos
  - Reset a `connecting` al `reconnect()` forzado
- [x] **M-UX-2 (indicador visual)**: agregado en `AppLayout.vue` header (antes del bell de notifications):
  - `bg-success-badge text-success-text` + `animate-pulse-subtle` cuando conectado → label "En vivo"
  - `bg-warning-badge text-warning-text` cuando disconnected → "Reconectando"
  - `bg-danger-badge text-error-700` cuando unavailable → "Sin WS"
  - aria-label + title para accesibilidad
- [x] **M-UX-3**: nuevo composable `useConfirm.js` con:
  - Singleton state compartido (isOpen, title, message, confirmText, cancelText, variant, loading)
  - `confirm(options)` retorna `Promise<boolean>` (api: title, message, confirmText, cancelText, variant)
  - Helpers export para montar el modal globalmente
- [x] **M-UX-3 (modal global)**: `<UiConfirmDialog>` montado en `AppLayout.vue` después del `<ToastContainer>`, conectado a los refs singleton del composable via import named.
- [x] **M-UX-3 (16 migraciones)**: 16 sitios con `window.confirm()` migrados a `await confirm({...})`. **El plan estimaba 6, la realidad eran 16** (verificado con grep). Archivos: AiAnalysisPage, AppointmentTypesPage, CalendarPage, CashRegisterPage, MovementList, SessionList, TransactionList, EnvironmentsPage, AttachmentGallery, EvolutionTimeline, MedicalRecordCard, PatientsPage, ProfessionalsPage, QuotationCard, TreatmentPlanCard, TreatmentPlanModal. Todos con `variant: 'danger'`.

**Verificación** (real):
- `pnpm build` → 0 errores (9.06s).
- `grep -rE "(window\.)?confirm[(]" resources/js --include="*.vue"` → **0 ocurrencias nativas** (antes: 16). Los matches que aparecen son del composable (`await confirm({...})`).
- 16/16 archivos con `await confirm` también tienen el `import { useConfirm }` correspondiente.
- Bundle principal: 480.45 KB (+0.86 KB por el modal global).
- Diff stats: 19 archivos, +356 / -48 líneas (incluye composable nuevo).

**Commit real**: `feat(ux): Sprint 5 - WebSocket feedback real + ConfirmDialog global` (hash `a075616`).

**Deviations del plan**:
1. **16 confirm() nativos en lugar de 6 estimados** (el plan subestimó). Migrados todos.
2. **Composable nuevo `useConfirm.js` en lugar de usar `<UiConfirmDialog>` con v-model local por vista**: más DRY (1 modal global en lugar de 16 duplicados).
3. **Solo 2 handlers en AppLayout** (handleGlobalConfirm, handleGlobalCancel) en lugar de 16 v-models: el state singleton es la fuente de verdad.
4. **M-UX-5 (`app.blade.php`)** sigue PENDIENTE (decidido en Sprint 3). No se abordó en este sprint.

**Deliverable detallado**: `docs/mejoras/sprint-5-deliverable.md` (tabla con los 16 sitios migrados, decisiones de diseño, métricas).
  1. `TreatmentPlanCard.vue:249-254` (eliminar plan)
  2. `CalendarPage.vue:880` (eliminar cita — `confirm("¿Estás seguro...")`)
  3. `PatientsPage.vue` (eliminar paciente — verificado en grep)
  4. `QuotationsPage.vue` (eliminar cotización)
  5. `SpecialtyRecordsPage.vue` (eliminar registro)
  6. `MedicalRecordsPage.vue` o similar (verificar)

**Verificación**:
- `pnpm build` → 0 errores.
- `grep -rE "window.confirm" resources/js` → 0 resultados.
- Apagar Reverb (`php artisan reverb:stop`) y abrir `/calendar` → dot del header se vuelve gris tras ~15s (3 intentos × 5s). Toast aparece tras ~30s indicando "Sin actualizaciones en tiempo real".
- Click en "Eliminar" en una card → ConfirmDialog modal abre (no prompt nativo).
- Sprints 0-4 siguen funcionando (regresión 0).

**Commit**: `feat(ux): M-UX-2 WebSocket feedback (dot + toast), M-UX-3 ConfirmDialog (6 sitios migrados)`.

---

### Sprint 6 — Migrar módulos restantes a PageHeader pattern (3.0 d-h) — ✅ **HECHO 2026-06-12** (commit `a219a1b`)

**Objetivo**: extender el patrón PageHeader a las vistas restantes.

**Branch**: `feat/ux-sprint-6-page-headers` (mergeada a `main` via `--no-ff`)

**Vistas migradas** (11 de 11 target — Sprint 2 ya cubrió las 5 top):

| # | Página | Estado |
|---|---|---|
| 1 | `AppointmentTypesPage.vue` | ✅ migrada |
| 2 | `ProfessionalsPage.vue` | ✅ migrada |
| 3 | `EnvironmentsPage.vue` | ✅ migrada |
| 4 | `ProcedureCatalogPage.vue` | ✅ migrada (incluye botón Nuevo + Import CSV) |
| 5 | `MyProceduresPage.vue` | ✅ migrada |
| 6 | `ReceptionProceduresPage.vue` | ✅ migrada |
| 7 | `QuotationsPage.vue` | ✅ migrada |
| 8 | `MedicalRecordsPage.vue` | ✅ migrada |
| 9 | `SpecialtyRecordsPage.vue` | ✅ migrada |
| 10 | `AiAnalysisPage.vue` | ✅ migrada (icono decorativo CpuChipIcon removido) |
| 11 | `TreatmentPlansPage.vue` | ✅ migrada (counter pills preservados en #actions) |

**Patrón aplicado** (mismo que Sprint 2):
```vue
<PageHeader
  title="<Título>"
  subtitle="<Subtítulo>"
  class="mb-6"
>
  <template #actions>
    <UiButton variant="secondary" @click="goBack">
      <template #icon-left>
        <svg ...>...</svg>
      </template>
      Volver
    </UiButton>
    <UiButton @click="openCreate">
      <template #icon-left>
        <PlusIcon class="w-5 h-5" />
      </template>
      Nuevo X
    </UiButton>
  </template>
</PageHeader>
```

**Mejoras incluidas**:
- Botones `<button class="btn btn-primary">` migrados a `<UiButton>` en QuotationsPage, SpecialtyRecordsPage, MedicalRecordsPage, TreatmentPlansPage
- `<PlusIcon class="w-5 h-5 mr-2" />` → slot `#icon-left` (patrón UiButton)

**Verificación**:
- `pnpm build` → OK (9.68s, 0 errores)
- 15/17 vistas con `<PageHeader>` verificado con grep
- 6 vistas sin PageHeader son por diseño: LoginPage (auth), DashboardPage (entra directo a stat cards), 4 DetailPage/StatsPage (sub-páginas con header propio)
- Diff stats: 11 archivos, +170 / -205 líneas

**Commit real**: `feat(ux): Sprint 6 - PageHeader en 11 vistas restantes (15/17 total)` (hash `a219a1b`).

**Deliverable**: inline en este commit (no se requiere archivo separado dado que el cambio es mecánico).

---

**Verificación**:
- `pnpm build` → 0 errores.
- Recorrido visual de las 12 páginas: header consistente, loading skeletons, empty states.
- Sprints 0-5 siguen funcionando (regresión 0).

**Commit**: `feat(ux): Sprint 6 — PageHeader + FilterBar + Skeleton + EmptyState en 12 páginas restantes`.

---

### Sprint 7 — Polish final: micro-interacciones, empty states, a11y (1.5 d-h)

**Objetivo**: detalles de pulido que separan "se ve bien" de "se ve premium".

**Branch**: `feat/ux-sprint-7-polish`

**Tareas**:
- [ ] **Confirm dialogs con doble-click prevention**: muchos botones de "Guardar" permiten doble-click → submits duplicados. Agregar `disabled` + `loading` al click.
- [ ] **Auto-focus en primer input** de cada modal al abrir (`<UiModal @open="nextTick(() => $refs.firstInput?.focus())">`).
- [ ] **Scroll restoration** en `DataTable.vue` y `Pagination.vue` — al cambiar de página, hacer scroll al top de la tabla.
- [ ] **Empty states con CTAs contextuales**: cada `EmptyState` debe tener un botón de acción primario ("Crear primer X", "Limpiar filtros", "Recargar").
- [ ] **Breadcrumbs en detalle pages** (PatientDetailPage, ProcedureCatalogDetailPage, etc.). Usar el componente `Breadcrumbs.vue` que existe pero no se usa.
- [ ] **Tooltips en icon-buttons** que no tengan `title` o `aria-label`. Hoy hay ~20 botones de solo-icono sin label accesible.
- [ ] **M-UX-6 (opcional, evaluable)**: atajos de teclado cross-app via composable `useGlobalShortcuts`. **Si el tiempo alcanza**:
  - `/` → focus en search del módulo actual
  - `Esc` → cerrar modal/sheet
  - `?` → modal de ayuda con lista de atajos
  - `Cmd/Ctrl+K` → command palette (out of scope real, dejar como follow-up)
- [ ] **a11y audit**: revisar todas las vistas con axe-core (extensión de Chrome) o Wave. Corregir issues críticos (contraste, ARIA missing, focus traps en modales).

**Verificación**:
- `pnpm build` → 0 errores.
- WAVE o axe en las 5 vistas top: 0 errores críticos.
- Abrir cualquier modal → primer input tiene focus.
- Cambiar de página en una tabla → scroll al top automático.
- Sprints 0-6 siguen funcionando (regresión 0).

**Commit**: `feat(ux): Sprint 7 polish — focus, scroll, empty states CTAs, breadcrumbs, a11y audit`.

---

## 6. Riesgos globales y mitigaciones

| Sprint | Riesgo | Mitigación |
|---|---|---|
| 0 (C-UX-2) | `key="$route.fullPath"` rompe scroll restoration o forms a medio llenar | Aceptable para app de gestión. Si surge queja, agregar `<keep-alive :include>` selectivo. |
| 1 (componentes nuevos) | Componentes sin "storybook" — bugs de render solo se ven en uso | Smoke test manual importando cada uno en una vista existente antes de mergear. |
| 3 (paleta) | Algún archivo usa `purple-*` intencionalmente (branding de IA) | Revisión visual caso por caso. Si quedan >5 ocurrencias justificadas, comentar inline. |
| 3 (I-UX-3) | `var(--color-accent)` no existe en `app.css` (poco probable — confirmado que SÍ existe) | Verificar primero. Si no, fallback a hex directo. |
| 4 (animaciones) | `scaleIn` en modales puede sentirse "saltarín" en algunos usuarios | Ajustar duración (200ms → 150ms) si feedback negativo. |
| 5 (M-UX-2) | Toast de "WS caído" puede aparecer en dev y molestar | Solo tras 3 intentos fallidos (15s), no al primer fallo. |
| 6 (12 páginas) | Algunas tienen layouts únicos que no encajan en PageHeader | Permitir opt-out con prop `noHeader` en `PageHeader` o usar slot fallback. |
| 7 (a11y) | Issues que requieren refactor mayor (ej. tablas sin thead) | Reportar como deuda, no bloquear el sprint. |

---

## 7. Orden de ejecución

1. **Sprint 0** (0.5 d-h) → bloqueante de UX, arrancar ya.
2. **Sprint 1** (1.5 d-h) → desbloquea componentes para todos los demás sprints.
3. **Sprint 2** (1.5 d-h) → usa los componentes del Sprint 1 en las 5 vistas top.
4. **Sprint 3** (1.0 d-h) → independiente, pero aplicar DESPUÉS de Sprint 2 (para no migrar colores de páginas que van a cambiar).
5. **Sprint 4** (1.0 d-h) → independiente.
6. **Sprint 5** (0.75 d-h) → depende de Sprint 1 (ConfirmDialog) y Sprint 2 (PageHeader con header indicator).
7. **Sprint 6** (3.0 d-h) → depende de Sprints 1-2.
8. **Sprint 7** (1.5 d-h) → depende de todos los anteriores (polish final).

**Total**: ~10.75 d-h netos (~50-60 h reales), ejecutables en 8-12 sesiones.

---

## 8. Métricas de éxito al cerrar el plan

- **0** colores `blue/purple/indigo/cyan/violet/emerald/amber/orange/pink/rose` en `resources/js` y `resources/views` (excepción documentada si quedan algunos).
- **0** `window.confirm()` nativos.
- **0** spinners `border-purple-*` inline.
- **17/17** páginas con `<PageHeader>`.
- **5/5** componentes críticos (`LoadingSpinner`, `EmptyState`, `Pagination`, `Skeleton`, `Breadcrumbs`) usados en al menos 3 vistas cada uno.
- **100%** de los modales con animación scale-in.
- **100%** de los toasts con slide-down.
- **1** indicador visual de "WS conectado" en el header.
- **0** regresiones: `pnpm build` OK, los 5 vistas top siguen funcionando como antes, C-UX-1 y C-UX-2 resueltos, el plan `plan-inconsistencias-2026-06-actualizado.md` sigue mergeado sin cambios.

---

## 9. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Smoke test de las 17 páginas: Dashboard, Calendar, Patients (lista + detalle), Professionals (lista + detalle), Environments (lista + detalle), AppointmentTypes (lista + detalle), ProcedureCatalog (lista + detalle), MyProcedures, ReceptionProcedures, BusinessIntelligence, CashRegister, ReadyToBill, TreatmentPlans, Quotations, MedicalRecords, SpecialtyRecords, AiAnalysis.
- Cita nueva desde modal aparece en calendar sin F5.
- Botón "atrás" del browser remonta la vista anterior.
- 0 colores de marca extraviados.
- Spinners todos con `border-theme-light border-t-accent` o `LoadingSpinner.vue`.
- Apagar Reverb → toast de "WS caído" tras 15s.
- Plan de inconsistencias (Sprints 0-5) sigue funcionando (regresión 0).
- Plan de catálogo de procedimientos sigue funcionando (regresión 0).
- Todos los planes anteriores (`plan-mejoras-futuras-2026-06.md`, `plan-flujo-catalog-procedimientos.md`) siguen vigentes.
