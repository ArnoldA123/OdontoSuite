# Plan #5 — Bugfixes UX críticos detectados manualmente (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback directo de Arnold tras sesión de uso manual de la app.
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno (los hallazgos son bugs puntuales independientes del `plan-ux-ui-2026-06.md` ya cerrado y de `plan-inconsistencias-2026-06-actualizado.md`).

---

## 1. Contexto y oportunidad

Arnold testeó la app manualmente como usuario final y reportó **5 problemas concretos** de UX y funcionalidad, algunos con errores JS visibles en consola. Todos están verificados contra el código real de `main` al 2026-06-12. Este plan es **estrictamente bugfix + pulido visual** (no redesign), con foco en cerrar defectos que el plan #4 no cubrió porque estaba enfocado en propagar el design system.

### 1.1 Bugs reportados

| # | Severidad | Síntoma reportado | Causa raíz verificada |
|---|---|---|---|
| B-1 | 🔴 Crítico | Click en "Test Components" del sidebar → ventana en blanco | Ruta `/test` apunta a un componente que no existe (o está roto) — `find resources/js -name "Test*"` devuelve 0 resultados |
| B-2 | 🔴 Crítico | Click en logo "OdontoSuite" no hace nada | El logo en `AppLayout.vue:11-23` es un `<img>` + `<span>` sin `<router-link>` ni `@click` — no es interactivo |
| B-3 | 🟠 Visual | Sidebar colapsa pero el "campo o columna" del main content queda del mismo tamaño (solo se ocultan los nombres) | `<nav class="px-4 py-6">` mantiene padding 16px en ambos lados, y `<aside>` línea 6 cambia a `lg:w-16` (64px) → el contenido del nav queda con `64 - 32 = 32px` apretado y los iconos pueden cortarse; el hamburger aparece en el header pero la transición no se ve fluida |
| B-4 | 🟠 Visual + Funcional | Click en campana de notificaciones abre modal con inconsistencia visual y sin funcionalidad clara | `NotificationCenter.vue` existe y se monta en `AppLayout.vue:361-363` pero: (a) solo abre/cierra, no filtra por tabs; (b) las tabs "Todas / No leídas / Citas / Pacientes / Planes / Presupuestos" son botones que no hacen nada |
| B-5 | 🔴 Crítico | `http://localhost:8000/patients` y `http://localhost:8000/procedure-catalog` renderizan en blanco con error en consola | `EmptyState.vue:118` usa `computed()` sin importarlo. **Todos los EmptyState de la app explotan al renderizar**. Solo afecta a páginas cuyo estado es "sin datos" (lista vacía, sin permisos, sin filtros), pero el impacto es total cuando aplica |

### 1.2 Otros hallazgos detectados durante la auditoría

- `EmptyState.vue` también carece de `import { computed }` (solo importa `UiButton` línea 66). Es el mismo bug en una sola línea.
- El logo del sidebar debería navegar a `/dashboard` (consistente con el resto de la app). Hoy es un `<div>` inerte.
- El item "Test Components" del sidebar debería **eliminarse** (era un scratch page para QA visual, no tiene valor de producto).

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Bugfixes críticos: EmptyState + Test Components + Logo | 0.5 d-h | ⏳ Pendiente | 1 fix de 1 línea + 1 ruta eliminada + 1 link agregado |
| 1 | Sidebar colapsable + notificaciones funcionales | 1.0 d-h | ⏳ Pendiente | 2 componentes tuneados + 1 composable de notificaciones |
| 2 | (Reservado) Polish visual: hamburger, animaciones, a11y | 0.5 d-h | ⏳ Pendiente | si quedan items del feedback tras Sprint 1 |
| **Total** | **2-3 sprints** | **~1.5-2.0 d-h** | **0 d-h ejecutados** | 5 bugs cerrados + 0 regresiones |

---

## 3. Hallazgos verificados al 2026-06-12

### 🔴 CRÍTICOS (rompen funcionalidad, Sprint 0)

#### B-UX-5 — `EmptyState.vue:118` tira `ReferenceError: computed is not defined`

**Evidencia** (`resources/js/components/ui/EmptyState.vue:65-118`):
```vue
<script setup>
import UiButton from './Button.vue'   // ← único import

const props = defineProps({...})
const emit = defineEmits(['action'])

// Computed
const containerClasses = computed(() => {   // ← LÍNEA 118: computed no importado
  const base = [
    'empty-state',
    ...
  ]
```

**Síntoma exacto reportado** (consola Chrome):
```
EmptyState.vue:118 Uncaught (in promise) ReferenceError: computed is not defined
  at setup (EmptyState.vue:118:26)
  ...
```

**Páginas afectadas** (grep `<EmptyState` en `resources/js/modules/`):
- `resources/js/modules/patients/PatientsPage.vue:132`
- `resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue`

**Fix** (1 línea): agregar `import { computed } from 'vue'` en el `<script setup>`:
```js
import { computed } from 'vue'
import UiButton from './Button.vue'
```

**Por qué no se detectó antes**: EmptyState solo se renderiza cuando la lista está vacía. Si los seeders siempre dejan datos, la página nunca entra al branch. Cuando el usuario entra a `/procedure-catalog` (sin seeders activos para esa tabla) o a `/patients` con filtros que devuelven 0 resultados, explota.

---

#### B-UX-1 — Click en "Test Components" abre página en blanco

**Evidencia** (`resources/js/components/layout/AppLayout.vue:671-677`):
```js
{
  name: 'Test Components',
  to: '/test',
  icon: CogIcon,
  roles: ['administrador'],
  badge: null
}
```

**Síntoma**: la ruta `/test` no existe como página. `grep "Test" resources/js/app.js` → 0 resultados. No hay `import` ni `component: () => import(...Test...)` definido.

**Fix** (decisión): **eliminar el item del sidebar**. Era un scratch page de QA visual sin valor de producto. Mantener la ruta como 404 sería peor — el item no debe existir.

Si más adelante se necesita una página de test de componentes, crearla en `resources/js/modules/test/UiTestPage.vue` y cablearla en `app.js`. Pero no es prioridad.

---

#### B-UX-2 — Logo "OdontoSuite" del sidebar no es interactivo

**Evidencia** (`resources/js/components/layout/AppLayout.vue:9-35`):
```vue
<!-- Logo -->
<div class="flex items-center flex-shrink-0 px-6 py-6 border-b border-theme/50">
  <div class="flex items-center gap-3">
    <img
      src="/images/easy_dent.png"
      alt="OdontoSuite"
      class="h-8 w-8 transition-all duration-200"
      :class="{ 'mx-auto': sidebarCollapsed }"
    />
    <span v-if="!sidebarCollapsed" class="text-lg font-semibold text-theme-primary">
      OdontoSuite
    </span>
  </div>
  <button
    v-if="!sidebarCollapsed"
    @click="toggleSidebar"
    ...
```

**Síntoma**: ni el `<img>` ni el `<span>` son clickeables. Arnold espera que click → ir a `/dashboard` (es el patrón universal "logo = home").

**Fix** (5 líneas): envolver el bloque del logo en un `<router-link to="/dashboard">` con clase de foco accesible:
```vue
<router-link
  to="/dashboard"
  class="flex items-center gap-3 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-lg"
  :class="{ 'mx-auto': sidebarCollapsed }"
  :aria-label="sidebarCollapsed ? 'Ir al Dashboard' : ''"
>
  <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-8" />
  <span v-if="!sidebarCollapsed" class="text-lg font-semibold text-theme-primary">
    OdontoSuite
  </span>
</router-link>
```

El `aria-label` solo aparece cuando el sidebar está colapsado (cuando no se ve el texto "OdontoSuite").

---

### 🟠 VISUALES (UX degradada, Sprint 1)

#### B-UX-3 — Sidebar colapsa pero deja mal la grilla + la transición es brusca

**Evidencia** (`resources/js/components/layout/AppLayout.vue:4-8`, `191-207`):
```vue
<aside
  class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col"
  :class="{ 'lg:w-16': sidebarCollapsed }"
>
  ...
</aside>

<!-- Main Content -->
<div class="lg:pl-72 transition-all duration-300" :class="{ 'lg:pl-16': sidebarCollapsed }">
  <!-- Header con botón hamburger que solo aparece colapsado -->
  <button v-if="sidebarCollapsed" @click="toggleSidebar" ...>
    <svg ...>  <!-- 3 líneas horizontales -->
    </svg>
  </button>
```

**Síntomas**:
1. Cuando colapsa, el `<nav>` interno mantiene `px-4` (16px) en ambos lados → el contenido visible queda en `64 - 32 = 32px` y los iconos (w-5 h-5 = 20px) pueden verse apretados/desalineados verticalmente.
2. El main content sí cambia de `pl-72` (288px) a `pl-16` (64px), pero la transición CSS solo aplica al padding-left, no al sidebar mismo → se ve un salto.
3. El botón hamburger (3 líneas horizontales) **solo aparece cuando está colapsado** (línea 199 `v-if="sidebarCollapsed"`). Esto es contraintuitivo: el patrón UX estándar es que el hamburger esté siempre disponible para expandir, y el chevron esté dentro del sidebar expandido para colapsar.
4. Los `<router-link>` del nav mantienen la clase `getNavItemClasses(item)` con padding horizontal fijo, así que cuando colapsa, los iconos quedan descentrados.

**Fix** (estructural):
1. Ajustar el padding del nav cuando colapsa: `class="flex-1 py-6 space-y-2"` + `class="px-2"` condicional vs `px-4` expandido.
2. Agregar `transition-all duration-300` al `<aside>` también (línea 5 ya tiene transition en el main pero no en el aside).
3. **Mantener** el botón de colapsar DENTRO del sidebar expandido (líneas 25-34) Y agregar el hamburger en el header (líneas 197-207) **siempre visible** (no `v-if="sidebarCollapsed"`), pero con `aria-label` diferente. Esto da al usuario 2 affordances: desde el sidebar colapsa, desde el header expande.
4. Ajustar `getNavItemClasses()` para que cuando `sidebarCollapsed === true`, los items sean `justify-center` en vez de tener el gap horizontal con texto.

**Estimación**: 30-45 min. Tests visuales en desktop + mobile.

---

#### B-UX-4 — Notificaciones: modal abre pero los filtros no funcionan

**Evidencia** (`resources/js/components/NotificationCenter.vue` + `AppLayout.vue:248-251`, `361-363`):
```vue
<button @click="toggleNotificationCenter" ...>
  <BellIcon class="w-5 h-5" />
</button>
...
<NotificationCenter :is-open="notificationCenterOpen" @close="closeNotificationCenter" />
```

**Síntomas** (basado en la imagen adjunta):
1. El modal abre y muestra tabs: "Todas / No leídas / Citas / Pacientes / Planes / Presupuestos".
2. Las tabs son botones pero al hacer click no cambian la lista filtrada (probable: el estado interno del componente no está cableado o el handler de tab es decorativo).
3. La lista muestra "No hay notificaciones" con icono de campana — funcional.
4. Los botones "Limpiar leídas" (footer izq) y "Limpiar todas" (footer der) son decorativos, no hacen nada.
5. El diseño visual de las tabs no resalta la tab activa de forma consistente (la "Todas" tiene fondo azul lleno pero las otras son pills planas — inconsistencia).

**Fix** (estructural):
1. **Auditar `NotificationCenter.vue`**: leer el componente completo para entender qué estado tiene, qué handlers están cableados y qué falta.
2. Si el backend tiene endpoint para marcar leídas/limpiar (`/api/notifications/...`): cablear los handlers.
3. Si el backend NO tiene endpoint: stubear con `console.warn('TODO: backend para notificaciones')` y dejar la UI funcional (cliente filtrando local) o eliminar los botones hasta que exista el endpoint.
4. **Patrón tabs activas**: usar el componente `<UiTabs>` del design system (ya existe en `resources/js/components/ui/Tabs.vue`) en vez de pills custom.

**Estimación**: 1-2 horas. Pendiente confirmar si hay backend antes de empezar.

---

## 4. Cambios planeados por sprint

### Sprint 0 — Bugfixes críticos (0.5 d-h, 1 sesión corta)

**Branch**: `fix/ux-sprint-0-critical-bugs`

**Tareas**:
1. `EmptyState.vue`: agregar `import { computed } from 'vue'` (1 línea).
2. `AppLayout.vue:671-677`: eliminar el item de navegación `Test Components` (7 líneas).
3. `AppLayout.vue:9-35`: envolver logo en `<router-link to="/dashboard">` con `aria-label` condicional.
4. `pnpm build` para verificar 0 errores.
5. Smoke test: dashboard, calendar, patients, procedure-catalog (deben renderizar con datos o con EmptyState funcional).

**Entregable**: `docs/mejoras/5.1-sprint-0-deliverable.md` con la lista de commits.

---

### Sprint 1 — Sidebar colapsable + notificaciones funcionales (1.0 d-h)

**Branch**: `feat/ux-sprint-1-sidebar-notifications`

**Tareas**:
1. **Sidebar**:
   - Ajustar `<nav>` padding condicional (`px-2` colapsado vs `px-4` expandido).
   - Agregar `transition-all duration-300` al `<aside>`.
   - Hacer el hamburger del header **siempre visible** (quitar `v-if`).
   - Ajustar `getNavItemClasses()` para `justify-center` cuando colapsado.
2. **NotificationCenter**:
   - Auditar componente completo.
   - Si hay backend: cablear handlers de tabs + marcar leída + limpiar.
   - Si NO hay backend: decidir entre stubear UI o reducir a solo lectura.
   - Migrar tabs a `<UiTabs>`.
3. `pnpm build` + smoke test.

**Entregable**: `docs/mejoras/5.2-sprint-1-deliverable.md`.

---

### Sprint 2 — Polish visual (0.5 d-h, opcional)

**Branch**: `feat/ux-sprint-2-polish`

**Tareas** (las que apliquen según feedback post-Sprint 1):
- Animación de entrada del sidebar al colapsar/expandir (slide + fade).
- Persistir estado `sidebarCollapsed` en `localStorage` para que se mantenga entre sesiones.
- a11y: verificar que el hamburger tiene `aria-expanded`, que los items del nav tienen `aria-current="page"`, etc.
- Quitar el badge "5" de Calendario si ya no hay lógica que lo alimente (verificar).

**Entregable**: `docs/mejoras/5.3-sprint-2-deliverable.md`.

---

## 5. Riesgos y mitigaciones

| # | Sprint | Riesgo | Mitigación |
|---|---|---|---|
| 1 | B-UX-1 | Alguien más espera la página `/test` | Buscar referencias en código antes de borrar (grep `'/test'` en `resources/js/`). Si no hay consumidores, eliminar tranquilo. |
| 2 | B-UX-2 | El logo como `<router-link>` puede romper el layout del botón de colapsar (que está en la misma fila) | El `<router-link>` ocupa solo el bloque del logo+texto; el `<button>` de colapsar queda fuera con `ml-auto`. Verificar responsive. |
| 3 | B-UX-3 | El sidebar colapsado a 64px puede no alcanzar para iconos de 24px + padding | Medir visualmente. Si no entra, considerar 80px (`lg:w-20`) en vez de 64px (`lg:w-16`). |
| 4 | B-UX-4 | El backend de notificaciones puede no existir | Verificar `routes/api.php` antes. Si no hay endpoints, no cablear UI falsa — dejar el modal informativo ("Funcionalidad en desarrollo") o reducir a solo lectura. |
| 5 | Sprint 0 | `EmptyState` con 0 listas llenas oculta el bug | El test post-fix debe incluir una vista donde la lista SÍ esté vacía (limpiar seeders manualmente para `/procedure-catalog` o usar un filtro que devuelva 0). |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.5 d-h) → bloqueante, arrancar ya. B-UX-5 es el más urgente (rompe 2 vistas principales).
2. **Sprint 1** (1.0 d-h) → depende de Sprint 0 (al refactorizar AppLayout no queremos pisar los fixes críticos).
3. **Sprint 2** (0.5 d-h) → opcional, polish tras Sprint 1.

**Total**: ~1.5-2.0 d-h netos (~10-14 h reales), ejecutables en 2-3 sesiones.

---

## 7. Métricas de éxito al cerrar el plan

- **0** errores JS en consola al navegar Dashboard / Calendar / Patients / ProcedureCatalog / cualquier vista con lista vacía.
- **Logo "OdontoSuite"** es clickeable y lleva a `/dashboard`.
- **Sidebar** colapsa/expande con animación fluida, hamburger siempre disponible, iconos centrados cuando colapsado.
- **NotificationCenter** tiene tabs funcionales o reducidas a solo lectura (nunca botones decorativos).
- **0** item "Test Components" en el sidebar.
- **0** regresiones: `pnpm build` OK, los planes #1-#4 siguen mergeados sin cambios.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Smoke test: Dashboard, Calendar, Patients (con datos + con lista vacía), ProcedureCatalog (con datos + con lista vacía), MedicalRecords, Quotations.
- Click en logo → URL cambia a `/dashboard`.
- Sidebar colapsa: el contenido del main se ajusta, los iconos del nav quedan centrados, el hamburger del header funciona.
- Notificaciones: click en campana → modal abre; click en tab "Citas" → la lista se filtra (o se explica que es solo lectura si no hay backend).
- 0 errores en consola Chrome (DevTools → Console limpia).
- 0 regresiones en planes #1, #2, #3, #4.
