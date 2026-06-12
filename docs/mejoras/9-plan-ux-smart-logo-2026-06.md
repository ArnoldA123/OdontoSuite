# Plan #9 — Logo del sidebar inteligente: expande cuando colapsado (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback de Arnold tras validar plan #8 (CSS specificity fix).
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno. Independiente de los planes #1-#8.

---

## 1. Contexto y oportunidad

Arnold validó que el fix de CSS specificity del plan #8 funciona (sidebar sí cambia de ancho). Pero notó un **patrón de UX inconsistente**: cuando el sidebar está colapsado, el logo "OdontoSuite" (sidebar) y el botón hamburguesa del header son **2 affordances** que hacen cosas distintas (navegar vs expandir). El usuario cita el patrón de **Gemini** como referencia: cuando el sidebar está colapsado, **solo el logo del sidebar** funciona como botón para expandir, **sin botones adicionales en el header**.

### 1.1 Problemas reportados

| # | Severidad | Síntoma | Causa raíz verificada |
|---|---|---|---|
| B-UX-14 | 🟠 UX | Cuando colapsado hay 2 affordances: hamburguesa del header + logo del sidebar. El usuario quiere patrón Gemini: 1 sola (el logo). | `AppLayout.vue:11-28` (logo `<router-link>` siempre presente) + `AppLayout.vue:202-212` (hamburguesa `v-if="sidebarCollapsed"`). El logo es link estático a `/dashboard`, no tiene estado condicional. |
| B-UX-15 | 🟠 UX | Cuando colapsado, el logo no hace nada útil (ir a /dashboard es irrelevante si ya estás en /dashboard). Debería **expandir el sidebar** (acción primaria) con tooltip "Abrir barra lateral". | Logo siempre apunta a `/dashboard`. No tiene `@click` para `toggleSidebar` cuando `sidebarCollapsed`. |

### 1.2 Patrón de referencia (Gemini)

- **Colapsado**: solo el logo del sidebar (ícono redondo pequeño). Al hacer hover se ilumina y aparece tooltip "Abrir barra lateral". Click → expande.
- **Expandido**: logo + texto "Gemini" + botón "Cerrar barra lateral" (chevron `<<`) + lista de navegación.
- **Nunca** hay un botón hamburguesa en el header (desktop).

### 1.3 Decisión de fix

**Reemplazar el patrón hamburguesa-en-header (plan #7) por logo-inteligente (Gemini-style)**:

1. **Quitar el botón hamburguesa del header** (líneas 203-212) completamente. En desktop, el header NUNCA tiene un toggle de sidebar.
2. **Hacer el logo del sidebar inteligente**:
   - **Colapsado** (`sidebarCollapsed === true`): el logo es un `<button>` que llama a `toggleSidebar`. Tooltip: "Abrir barra lateral". NO navega.
   - **Expandido** (`sidebarCollapsed === false`): el logo es un `<router-link to="/dashboard">` (patrón actual). NO llama a `toggleSidebar`.

3. **Mantener el botón "Cerrar barra lateral"** (chevron `<<`, líneas 29-38) solo cuando expandido.

Resultado:
- Colapsado: 1 affordance (logo del sidebar).
- Expandido: 2 affordances (logo + chevron, ambos con propósito claro: navegar y colapsar).
- Header desktop: 0 affordances de toggle.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Logo inteligente + quitar hamburguesa del header | 0.25 d-h | ✅ HECHO (commit `a31da44`) | 1 archivo: AppLayout.vue |
| **Total** | **1 sprint** | **~0.25 d-h** | **0 d-h ejecutados** | 2 bugs cerrados |

---

## 3. Hallazgos verificados al 2026-06-12

### 🟠 B-UX-14 — 2 affordances cuando colapsado

**Evidencia** (`resources/js/components/layout/AppLayout.vue`):
- Líneas 11-28: `<router-link to="/dashboard">` (logo)
- Líneas 202-212: `<button v-if="sidebarCollapsed" @click="toggleSidebar">` (hamburguesa header)

Cuando `sidebarCollapsed === true`, ambos están en el DOM:
- Logo: navega a `/dashboard` (acción secundaria)
- Hamburguesa: expande el sidebar (acción primaria)

El usuario tiene que decidir cuál usar. **Patrón Gemini: solo 1 affordance con la acción correcta**.

**Fix**: eliminar el botón hamburguesa del header (líneas 202-212). En desktop, el header nunca tiene toggle de sidebar.

---

### 🟠 B-UX-15 — Logo no expande el sidebar cuando colapsado

**Causa**: el `<router-link to="/dashboard">` (líneas 11-28) es un link estático. No tiene lógica condicional.

**Fix**: cambiar el logo a render condicional:

```vue
<!-- Caso A: sidebar expandido → logo = link a /dashboard -->
<router-link
  v-if="!sidebarCollapsed"
  to="/dashboard"
  class="flex items-center gap-3 ..."
>
  <img ... />
  <span>OdontoSuite</span>
</router-link>

<!-- Caso B: sidebar colapsado → logo = botón toggle -->
<button
  v-else
  @click="toggleSidebar"
  class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-theme-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 transition-colors"
  aria-label="Abrir barra lateral"
  title="Abrir barra lateral"
>
  <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-8" />
</button>
```

**Hover effect** (opcional pero recomendado): el logo colapsado se ilumina con un fondo sutil al hacer hover (`hover:bg-theme-surface`). Tooltip nativo del browser muestra "Abrir barra lateral".

**Criterios de aceptación**:
- Click en logo colapsado → sidebar expande (sin navegar).
- Click en logo expandido → navega a `/dashboard` (sin colapsar).
- Tooltip "Abrir barra lateral" en estado colapsado.
- Tooltip "Ir al Dashboard" en estado expandido.

---

## 4. Cambios planeados

### Sprint 0 — Logo inteligente (0.25 d-h)

**Branch**: `fix/ux-sprint-0-smart-logo`

**Tareas**:
1. **B-UX-14**: Eliminar completamente el `<button v-if="sidebarCollapsed" @click="toggleSidebar">` del header (líneas 202-212, incluyendo el `<svg>` y `</button>`).
2. **B-UX-15**: Cambiar el `<router-link to="/dashboard">` (líneas 11-28) a render condicional con `v-if="!sidebarCollapsed"` + `<button v-else @click="toggleSidebar">` paralelo.
3. `pnpm build` para verificar 0 errores.
4. **Verificar visualmente en el browser** (login con `ever`/`password123`, expandir/colapsar, hacer click en el logo en ambos estados).

**Entregable**: `docs/mejoras/9.1-sprint-0-deliverable.md` con verificación visual.

---

## 5. Riesgos y mitigaciones

| # | Riesgo | Mitigación |
|---|---|---|
| 1 | El usuario extraña la hamburguesa del header en desktop | El logo del sidebar (más visible que un hamburguesa) hace la misma acción. Patrón Gemini/Linear/Slack. |
| 2 | El logo colapsado con `hover:bg-theme-surface` se ve "raro" | Probar con `hover:bg-theme-surface/50` (más sutil). Verificar visualmente. |
| 3 | El tooltip nativo (`title="..."`) se ve feo en Chrome | Es estándar. Alternativa: Vue tooltip custom, fuera de scope. |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.25 d-h) → ejecutar ya.

**Total**: ~0.25 d-h netos (~2-3 h reales), ejecutable en 1 sesión corta.

---

## 7. Métricas de éxito al cerrar el plan

- **Header desktop: 0 affordances de toggle** (solo avatar + bell + WS dot).
- **Sidebar colapsado: 1 sola affordance** (el logo del sidebar).
- **Logo colapsado: click → expande** (no navega).
- **Logo expandido: click → navega a /dashboard** (no colapsa).
- **0 regresiones**: `pnpm build` OK, sidebar sigue colapsando/expandiendo bien.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Login con `ever`/`password123`, ir a `/dashboard`.
- **Estado expandido** (default):
  - Header: NO hay botón hamburguesa. ✓
  - Sidebar: logo "OdontoSuite" + chevron "Cerrar barra lateral" + nav. ✓
  - Click en logo → va a `/dashboard` (sin colapsar). ✓
  - Click en chevron → sidebar colapsa. ✓
- **Estado colapsado** (luego del click en chevron):
  - Header: NO hay botón hamburguesa. ✓
  - Sidebar: solo el ícono del logo (sin texto "OdontoSuite"). ✓
  - Click en logo → sidebar expande (no navega). ✓
  - Hover en logo → fondo sutil, tooltip "Abrir barra lateral". ✓
- 0 regresiones en planes #1-#8.

---

## 9. Changelog

- **2026-06-12** — Sprint 0 ✅ HECHO. Commit `a31da44` en `fix/ux-sprint-0-smart-logo`. 2 bugs cerrados.
  - B-UX-14: Hamburguesa del header eliminada (líneas 202-212 borradas).
  - B-UX-15: Logo del sidebar ahora inteligente — `<router-link>` cuando expandido, `<button @click="toggleSidebar">` cuando colapsado. Chevron del sidebar: aria-label "Cerrar barra lateral" (consistente con Gemini).

**PLAN CERRADO**. Patrón Gemini aplicado. 0 regresiones.
