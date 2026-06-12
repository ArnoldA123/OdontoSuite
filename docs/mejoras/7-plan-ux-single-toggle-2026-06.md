# Plan #7 — Botón hamburguesa del header es redundante (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback directo de Arnold tras validar plan #6 en Chrome.
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno. Independiente del plan #6 (ya cerrado) y de los planes #1-#5.

---

## 1. Contexto y oportunidad

El plan #5 (Sprint 1) introdujo un botón hamburguesa **siempre visible** en el header desktop con la intención de "dar al usuario 2 affordances: desde el sidebar colapsa, desde el header expande". En la práctica, esto creó **3 problemas** que el usuario detectó manualmente:

### 1.1 Problemas reportados

| # | Severidad | Síntoma reportado | Causa raíz verificada |
|---|---|---|---|
| B-UX-9 | 🟠 UX duplicado | Hay **2 botones que hacen `toggleSidebar`**: el del header (3 rayas) y el del sidebar (chevron `<<`). El usuario lo ve como redundante e inconsistente. | `AppLayout.vue:203-212` (header) y `AppLayout.vue:29-38` (sidebar). Ambos llaman al mismo `toggleSidebar`. |
| B-UX-10 | 🟠 Visual | Al hacer click en el hamburguesa del header, queda con un "marco azul" persistente (focus ring). | `<button class="p-2 rounded-lg hover:bg-theme-surface ..."` no tiene `focus:ring`, pero como `<button>` recibe focus al click, el outline nativo del browser queda visible. Combinado con el `border` del header, parece "remarcado". |
| B-UX-11 | 🟠 UX mobile/desktop | El botón hamburguesa del header está siempre visible en desktop (decisión del plan #5), pero el usuario lo lee como "el botón de menú que sale con el responsive" — mal posicionado en pantallas grandes. | `AppLayout.vue:203-212` no tiene `lg:hidden` ni `lg:flex` condicional, está siempre visible. |

### 1.2 Diagnóstico

El plan #5 Sprint 1 decía: *"el botón de colapsar DENTRO del sidebar expandido (líneas 25-34) Y agregar el hamburger en el header (líneas 197-207) siempre visible (no v-if="sidebarCollapsed")"*. Esa decisión fue errónea: el patrón UX estándar es **un solo botón, su posición depende del estado**:

- **Sidebar expandido**: el botón está DENTRO del sidebar (junto al logo) → colapsar.
- **Sidebar colapsado**: el botón está EN EL HEADER → expandir.
- **Nunca los 2 a la vez**.

Esto es lo que hace Linear, Notion, GitHub, etc.

### 1.3 Decisión de fix

**Revertir la decisión del plan #5 Sprint 1**: el botón del header debe ser `v-if="sidebarCollapsed"` (solo visible cuando colapsado). El del sidebar sigue siendo `v-if="!sidebarCollapsed"`. Resultado: **siempre hay exactamente 1 botón toggle visible, su posición cambia según estado**.

Adicional: agregar `focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500` al hamburguesa del header (y revisar el del sidebar) para que el ring solo aparezca con teclado, no con click.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Un solo toggle de sidebar (header condicional + focus visible) | 0.25 d-h | ⏳ Pendiente | 1 archivo: AppLayout.vue |
| **Total** | **1 sprint** | **~0.25 d-h** | **0 d-h ejecutados** | 3 bugs cerrados |

---

## 3. Hallazgos verificados al 2026-06-12

### 🟠 B-UX-9 — Dos botones hacen `toggleSidebar`

**Evidencia** (`resources/js/components/layout/AppLayout.vue:29-38` y `203-212`):
```vue
<!-- Botón DENTRO del sidebar (líneas 29-38) -->
<button
  v-if="!sidebarCollapsed"
  @click="toggleSidebar"
  class="ml-auto p-1.5 rounded-lg hover:bg-theme-surface ..."
  aria-label="Colapsar sidebar"
>
  <svg ...>  <!-- chevron `<<` -->
</button>

<!-- Botón EN EL HEADER (líneas 203-212) -->
<button
  @click="toggleSidebar"  <!-- SIN v-if condicional -->
  class="p-2 rounded-lg hover:bg-theme-surface ..."
  :aria-label="sidebarCollapsed ? 'Expandir sidebar' : 'Colapsar sidebar'"
  :aria-expanded="!sidebarCollapsed"
>
  <svg ...>  <!-- 3 rayas horizontales -->
</button>
```

**Síntoma**: 2 affordances para la misma acción, redundantes. El usuario lo lee como inconsistencia.

**Fix**: agregar `v-if="sidebarCollapsed"` al botón del header. Así:
- Sidebar expandido: solo botón chevron dentro del sidebar.
- Sidebar colapsado: solo botón hamburguesa en el header.
- Cero redundancia.

---

### 🟠 B-UX-10 — Focus ring persistente en el header

**Causa**: el `<button>` del header no tiene `focus:outline-none` ni `focus-visible:ring-*`. Cuando el usuario hace click, el `<button>` recibe `:focus` (no `:focus-visible`), y como el browser default para `<button>` es tener un outline visible, queda con un marco persistente. Combinado con el fondo del header, el outline del browser se ve como un "marco azul".

**Fix**: agregar `focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2` a ambos botones (header + sidebar). Esto:
- `focus:outline-none`: oculta el outline nativo en focus (mouse o teclado).
- `focus-visible:ring-2`: muestra un ring custom SOLO cuando el navegador detecta navegación por teclado (Tab), no en click.

`focus-visible` es el estándar moderno (Chrome 86+, Firefox 85+, Safari 15.4+).

---

### 🟠 B-UX-11 — Botón hamburguesa mal posicionado en desktop

**Causa**: el botón del header (líneas 203-212) está **siempre visible**, sin `lg:hidden` ni condicional. En desktop se ve como "el botón mobile que se coló", mal posicionado porque no es el patrón desktop estándar.

**Fix**: cubierto por B-UX-9 (`v-if="sidebarCollapsed"`). Cuando el sidebar está expandido en desktop, el header no muestra el hamburguesa (el control está dentro del sidebar). Cuando el sidebar está colapsado, el hamburguesa del header aparece como "puerta" para expandir. Patrón estándar.

---

## 4. Cambios planeados

### Sprint 0 — Un solo toggle + focus visible (0.25 d-h)

**Branch**: `fix/ux-sprint-0-single-toggle`

**Tareas**:
1. **B-UX-9 + B-UX-11**: agregar `v-if="sidebarCollapsed"` al `<button>` del header (línea 203-212).
2. **B-UX-10**: agregar `focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2` al `<button>` del header.
3. Mismo focus-visible al `<button>` del sidebar (línea 29-38) para consistencia.

**Entregable**: `docs/mejoras/7.1-sprint-0-deliverable.md`.

---

## 5. Riesgos y mitigaciones

| # | Sprint | Riesgo | Mitigación |
|---|---|---|---|
| 1 | B-UX-9 | Alguien espera ver el botón del header siempre | Verificar que el patrón es estándar (Linear/Notion/GitHub). Si el usuario lo extraña en una sesión, se revierte. |
| 2 | B-UX-10 | `focus-visible` no funciona en browsers viejos | Chrome 86+ (oct 2020), Firefox 85+ (ene 2021), Safari 15.4+ (mar 2022). Cobertura >97% en 2026. Aceptable. |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.25 d-h) → ejecutar ya.

**Total**: ~0.25 d-h netos (~2-3 h reales), ejecutable en 1 sesión corta.

---

## 7. Métricas de éxito al cerrar el plan

- **1 solo botón** de toggle de sidebar visible a la vez (header O sidebar, según estado).
- **0** outlines nativos de browser persistentes al hacer click.
- **Focus ring** aparece solo con navegación por teclado (Tab).
- **0 regresiones**: `pnpm build` OK, sidebar sigue colapsando/expandiendo bien.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Smoke test: Dashboard, Calendar, Patients.
- Click en chevron del sidebar (expandido) → sidebar colapsa + aparece hamburguesa en header.
- Click en hamburguesa del header (colapsado) → sidebar expande + aparece chevron dentro del sidebar.
- Tab desde el navegador: focus ring aparece en el botón (no se queda pegado al click).
- 0 regresiones en planes #1-#6.
