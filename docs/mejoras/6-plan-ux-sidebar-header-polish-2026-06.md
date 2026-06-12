# Plan #6 — Sidebar y header inconsistentes (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback directo de Arnold tras validar plan #5 en Chrome.
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno. Independiente del plan #5 (ya cerrado) y de los planes #1-#4.

---

## 1. Contexto y oportunidad

El plan #5 cerró 5 bugs y 3 polishes, pero al validar visualmente surgieron **3 nuevos problemas** en la barra lateral (sidebar) y la cabecera (header) que el usuario detectó manualmente. El plan #5 se enfocó en hacer que el sidebar colapsara, no en que colapsara **bien**. Este plan es el polish visual fino que se detectó post-fix.

### 1.1 Problemas reportados

| # | Severidad | Síntoma reportado | Causa raíz verificada |
|---|---|---|---|
| B-UX-6 | 🟠 Visual | Sidebar colapsado queda "demasiado ancho" — al minimizarlo se ocultan los nombres pero el "campo o columna" mantiene casi el mismo tamaño. El usuario lo lee como si no hubiera colapsado bien. | `<aside>` usa `lg:w-16` (64px) y el padding/gap interno del nav + logo + user no se reduce lo suficiente. Items del nav con `px-2` aún consumen mucho. Necesita un valor más agresivo (`lg:w-14` = 56px) o padding-cero + iconos más chicos. |
| B-UX-7 | 🟠 Visual + UX | "Botón fantasma de línea azul" a la derecha del título "Dashboard" en el header. Parece otro control inconsistente. | El indicador WS "En vivo" (verde) está siempre visible en el header y se lee como un botón más. El hamburger (3 rayas) del header + el indicador WS + el bell + el avatar = 4 controles consecutivos en la zona derecha, lo cual satura. El usuario pide **reducir la densidad visual** del header. |
| B-UX-8 | 🟠 Visual | En responsive (mobile), la cabecera con franjas/transparencia se ve mal y el botón de menú hamburguesa no se distingue bien del fondo. | `<div class="lg:hidden fixed top-0... bg-theme-surface-elevated/80 backdrop-blur-md">` — el `80` opacity causa la franja traslúcida que se ve mal. El border + backdrop-blur叠加 sobre el contenido se ve "raro" según feedback. |

### 1.2 Contexto adicional

- El plan #5 dejó el sidebar colapsando funcionalmente (Sprint 1 + 2). Ahora el polish fino.
- El header desktop tiene 4 elementos a la derecha: indicador WS, bell de notificaciones, avatar+dropdown. El hamburger del header (siempre visible desde Sprint 1) hace 4+1=5. Demasiado.
- En mobile, el header está separado del desktop (`lg:hidden`) y tiene su propio hamburger.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Sidebar colapsado más compacto + header menos denso | 0.5 d-h | ⏳ Pendiente | 2 archivos: AppLayout.vue |
| 1 | Header mobile opaco + a11y + consistencia visual | 0.5 d-h | ⏳ Pendiente | 1 archivo: AppLayout.vue |
| **Total** | **2 sprints** | **~1.0 d-h** | **0 d-h ejecutados** | 3 bugs cerrados |

---

## 3. Hallazgos verificados al 2026-06-12

### 🟠 B-UX-6 — Sidebar colapsado queda demasiado ancho

**Evidencia** (`resources/js/components/layout/AppLayout.vue:4-8`):
```vue
<aside
  class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col sidebar-slide transition-all duration-300"
  :class="{ 'lg:w-16': sidebarCollapsed }"
>
```

**Causa**: `lg:w-16` = 64px. El contenido interior:
- Logo con `mx-auto` + `h-8 w-8` (32px) → queda centrado pero el gap-3 lateral suma espacio.
- Nav con `px-2` (8px) + `space-y-2` (8px) → cada item queda en `64 - 16 = 48px` para un icono de 20px (`w-5 h-5`).
- User section: avatar 32px con `justify-center` queda alineado.
- Botón "Cerrar Sesión" (line 90-108) tiene `full-width` condicional, pero con `px-2` queda en 48px.

**Síntoma del usuario**: al colapsar, el sidebar sigue ocupando casi el mismo ancho visual. El icono `<` (chevron) que aparece a la derecha del logo, en la imagen 1, ocupa `h-4 w-4` (16px) — pequeño, no balancea el "ancho" del sidebar.

**Fix** (2 opciones, decidir con usuario):
- **Opción A** (mínima): `lg:w-16` (64px) → `lg:w-14` (56px). Reduce 8px = ~12% más compacto. Mantiene padding.
- **Opción B** (más agresiva): `lg:w-12` (48px). Iconos de 20px quedan en `48 - 16 = 32px` justos. Sin padding lateral en el nav (`px-0` colapsado).

Recomiendo **Opción A** (w-14) para no romper el balance visual con el padding.

**Adicional**: el `<div>` del logo (línea 10) tiene `px-6 py-6` (24px padding). Cuando colapsa, ese padding lateral se mantiene. Fix: agregar `transition-all` al padding del logo + reducirlo cuando colapsa (`px-2`).

---

### 🟠 B-UX-7 — Header saturado de controles (hamburger + WS + bell + avatar)

**Evidencia** (`resources/js/components/layout/AppLayout.vue:202-280`):
```vue
<div class="flex items-center gap-4">
  <button @click="toggleSidebar" ...>  <!-- hamburger -->
    <svg ...>  <!-- 3 rayas -->
  </button>
  <div>
    <h1>...</h1>
    <p>...</p>
  </div>
</div>

<div class="flex items-center gap-3">
  <div v-if="wsStatus !== 'connecting'" class="flex items-center gap-1.5 px-2 py-1 rounded-full text-xs">
    <!-- Indicador "En vivo" con dot animado -->
  </div>
  <UiButton @click="toggleNotificationCenter" variant="ghost" size="sm" class="relative">
    <!-- BellIcon + badge de unread count -->
  </UiButton>
  <div ref="userMenuContainerRef">
    <UiButton @click="toggleUserMenu" variant="ghost" size="sm">
      <!-- Avatar + nombre + chevron -->
    </UiButton>
  </div>
</div>
```

**Síntoma del usuario**: el header tiene **4 controles consecutivos en la zona derecha** (hamburger siempre visible desde Sprint 1 + indicador WS "En vivo" verde + bell + avatar). El usuario describe el indicador WS como "botón fantasma de línea azul" — quizás porque tiene un dot animado `animate-pulse-subtle` que llama la atención y se confunde con un botón interactivo.

**Fix** (2 opciones):
- **Opción A** (mínima): reducir el indicador WS a **solo el dot** (sin texto "En vivo"). Ya tiene `aria-label` para screen readers y `title` para tooltip. El texto era redundante visualmente.
- **Opción B** (más agresiva): quitar el indicador WS del header. Mostrarlo solo cuando hay error (disconnected/unavailable). El caso feliz no necesita feedback visible constante.

Recomiendo **Opción A** para no perder feedback de debugging. El usuario verá el dot verde y podrá hacer hover/click para ver el estado si quiere.

Adicional: el hamburger siempre visible (Sprint 1) + el logo `<router-link>` (Sprint 0) + el botón "Cerrar Sesión" del sidebar + los items del nav son **5+ affordances de navegación**. Es UX denso.

---

### 🟠 B-UX-8 — Header mobile traslúcido se ve mal

**Evidencia** (`resources/js/components/layout/AppLayout.vue:114-131`):
```vue
<!-- Mobile Header -->
<div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-theme-surface-elevated/80 backdrop-blur-md border-b border-theme/50">
  <div class="flex items-center justify-between px-4 h-16">
    <div class="flex items-center gap-3">
      <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-8" />
      <span class="text-lg font-semibold text-theme-primary">OdontoSuite</span>
    </div>
    <button
      @click="mobileMenuOpen = true"
      class="p-2 rounded-lg hover:bg-theme-surface transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
      aria-label="Abrir menú"
    >
      <svg class="w-6 h-6" ...>
        <path ... d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
  </div>
</div>
```

**Síntoma del usuario**: en mobile, la cabecera con `bg-theme-surface-elevated/80 backdrop-blur-md` se ve como una "franja traslúcida" que se叠加 con el contenido. El borde + el blur hace que se vea "raro", no como un header sólido.

**Fix** (1 línea): cambiar `bg-theme-surface-elevated/80` → `bg-theme-surface-elevated` (opacidad 100%). Quitar `backdrop-blur-md` (en mobile no aporta nada porque el contenido debajo es el sidebar off-canvas). Dejar `border-b` para separación visual.

Adicional: el `focus:ring-2 focus:ring-primary-500 focus:ring-offset-2` del botón mobile es una buena práctica a11y. Mantener.

---

## 4. Cambios planeados por sprint

### Sprint 0 — Sidebar más compacto + header menos denso (0.5 d-h)

**Branch**: `fix/ux-sprint-0-sidebar-compact`

**Tareas**:
1. **B-UX-6**: `lg:w-16` → `lg:w-14` en `<aside>`. Padding del logo container (línea 10) cambia a condicional: `px-6 py-6` expandido, `px-2 py-6` colapsado.
2. **B-UX-7**: Indicador WS del header: dejar solo el dot animado (sin texto "En vivo"). Mantener `aria-label` y `title` para a11y.

**Entregable**: `docs/mejoras/6.1-sprint-0-deliverable.md`.

---

### Sprint 1 — Header mobile opaco + polish (0.5 d-h)

**Branch**: `fix/ux-sprint-1-mobile-header`

**Tareas**:
1. **B-UX-8**: `bg-theme-surface-elevated/80` → `bg-theme-surface-elevated` en header mobile. Quitar `backdrop-blur-md`.
2. Verificar que en mobile (lg:hidden) no queden affordances duplicados (hamburger desktop vs mobile).

**Entregable**: `docs/mejoras/6.2-sprint-1-deliverable.md`.

---

## 5. Riesgos y mitigaciones

| # | Sprint | Riesgo | Mitigación |
|---|---|---|---|
| 1 | B-UX-6 | `lg:w-14` (56px) puede ser demasiado angosto para iconos de 24px | Medir visualmente. Si no entra, fallback a `lg:w-16` con padding-cero (`px-0` colapsado en nav). |
| 2 | B-UX-7 | Quitar texto "En vivo" puede confundir a usuarios que no notan el dot | Mantener `aria-label="Estado de WebSocket: connected"` para screen readers + tooltip nativo `title="WebSocket: connected"`. |
| 3 | B-UX-8 | Header mobile sin blur puede verse "plano" | El borde inferior (`border-b border-theme/50`) sigue dando separación visual. |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.5 d-h) → bloqueante, arrancar ya. B-UX-6 y B-UX-7 son desktop, visibles para todos los usuarios.
2. **Sprint 1** (0.5 d-h) → mobile, menos urgente.

**Total**: ~1.0 d-h netos (~6-8 h reales), ejecutables en 1-2 sesiones.

---

## 7. Métricas de éxito al cerrar el plan

- **0** zonas visuales con "ancho muerto" al colapsar el sidebar.
- **Header desktop** con 3 controles visibles (hamburger + bell + avatar), el indicador WS reducido a dot sin texto.
- **Header mobile** opaco, sin blur ni transparencia.
- **0** regresiones: `pnpm build` OK, sidebar expandido sigue viéndose como antes, mobile menu sigue abriendo bien.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Smoke test: Dashboard, Calendar, Patients (desktop + mobile responsive).
- Colapsar sidebar: ancho se reduce visiblemente, iconos centrados, sin zonas vacías.
- Header desktop: dot WS verde visible, sin texto "En vivo", bell y avatar como antes.
- Header mobile (responsive < 1024px): cabecera opaca, hamburger visible, sin blur raro.
- 0 regresiones en planes #1-#5.
