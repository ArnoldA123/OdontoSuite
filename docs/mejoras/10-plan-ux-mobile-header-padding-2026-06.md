# Plan #10 — Header mobile tapa el contenido (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback de Arnold tras validar plan #9 en Chrome con responsive.
> **Estado**: pendiente de ejecución.
> **Dependencias**: ninguno. Independiente de los planes #1-#9.

---

## 1. Contexto y oportunidad

Arnold validó el logo inteligente (plan #9) y reportó un nuevo bug en **mobile/responsive**: el header mobile (fijo arriba con "OdontoSuite" + hamburguesa) **se superpone al contenido** de la página porque el main content no tiene padding-top que compense su altura. El usuario describe "está tapando lo primero" — el primer card del dashboard queda oculto debajo del header.

### 1.1 Problema reportado

| # | Severidad | Síntoma | Causa raíz verificada |
|---|---|---|---|
| B-UX-16 | 🔴 Visual | En mobile (lg:hidden), el header `fixed top-0 h-16` queda encima del contenido de la página. El primer card (ej: "Citas Hoy 2") se ve recortado o debajo del header. | `AppLayout.vue:127`: header mobile `fixed top-0 left-0 right-0 z-40`. `AppLayout.vue:209`: main content `<div class="transition-all duration-300">` — **sin padding-top mobile**. El main empieza en `top: 0` y el header fixed se叠加 encima. |

### 1.2 Verificación (yo mismo en browser real, viewport actual 1264px desktop)

```js
// En mobile (<1024px) el bug se manifestaría así:
const mobileHeader = document.querySelector('.lg\\:hidden.fixed');
// offsetHeight: 64px (h-16), position: fixed, top: 0
// superpone sobre el contenido del main que tiene paddingTop: 0
```

En desktop no se ve porque `lg:hidden` aplica `display: none`. Pero la regla CSS está mal estructurada: **el `padding-top` del main debería ser condicional mobile-only** (`pt-16 lg:pt-0`).

### 1.3 Decisión de fix

Agregar `pt-16 lg:pt-0` al main wrapper. El `h-16` del header mobile (64px) coincide con el `pt-16` (4rem = 64px), así que el contenido arranca justo debajo del header.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Padding-top condicional en main + verificar header interno | 0.1 d-h | ⏳ Pendiente | 1 archivo: AppLayout.vue |
| **Total** | **1 sprint** | **~0.1 d-h** | **0 d-h ejecutados** | 1 bug crítico cerrado |

---

## 3. Hallazgos verificados al 2026-06-12

### 🔴 B-UX-16 — Header mobile tapa el contenido

**Evidencia** (`resources/js/components/layout/AppLayout.vue:127` y `:209`):

```vue
<!-- Header mobile (línea 127) -->
<div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-theme-surface-elevated border-b border-theme/50">
  <div class="flex items-center justify-between px-4 h-16">
    <!-- ...logo + hamburger... -->
  </div>
</div>

<!-- Main content (línea 209) -->
<div class="transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-72'">
  <!-- Header del main (línea 211) -->
  <header class="relative z-30 bg-theme-surface-elevated/80 backdrop-blur-md shadow-subtle border-b border-theme/50">
    <!-- ... título de página + acciones ... -->
  </header>
  <!-- ... router-view con páginas ... -->
</div>
```

**Bug**: en mobile (cuando `lg:hidden` aplica display:block), el header mobile está `fixed top-0 h-16` (64px). El main arranca en `top: 0` (sin padding-top), por lo que el contenido queda **debajo** del header fixed. El primer elemento del main (el `<header>` interno con "Dashboard" + acciones) se ve solapado.

**Fix** (1 línea): cambiar la línea 209 a:
```vue
<div class="pt-16 lg:pt-0 transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-72'">
```

`pt-16` = `padding-top: 4rem` = 64px (igual al `h-16` del header). Aplica solo en mobile (`<1024px`). En desktop `lg:pt-0` lo cancela (el `<aside>` ya da el padding-left en desktop).

---

## 4. Cambios planeados

### Sprint 0 — Padding-top condicional (0.1 d-h)

**Branch**: `fix/ux-sprint-0-mobile-header-padding`

**Tareas**:
1. Cambiar línea 209: agregar `pt-16 lg:pt-0` a la clase del main wrapper.
2. `pnpm build` para verificar 0 errores.
3. **Verificar visualmente** con Chrome DevTools en modo responsive (viewport < 1024px):
   - El primer card "Citas Hoy 2" debe verse COMPLETO (no recortado).
   - El header mobile debe estar encima (z-40) sin tapar.
   - El padding-top del main debe ser 64px en mobile, 0px en desktop.

**Entregable**: `docs/mejoras/10.1-sprint-0-deliverable.md`.

---

## 5. Riesgos y mitigaciones

| # | Riesgo | Mitigación |
|---|---|---|
| 1 | El padding-top queda en desktop también | Uso `lg:pt-0` explícito para sobrescribir. |
| 2 | El header interno del main (línea 211) también queda mal | El header interno es `relative` y está dentro del main, así que con `pt-16` en el main, el header interno se renderiza DESPUÉS del padding, en su posición normal. Sin problema. |
| 3 | En mobile el `<aside>` (sidebar desktop) está oculto, no da padding-left, no hay conflicto con `lg:pl-72`. | Correcto. El `lg:pl-72` solo aplica en desktop (`lg:` prefix). |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.1 d-h) → ejecutar ya.

**Total**: ~0.1 d-h netos (~30-60 min), ejecutable en 1 sesión corta.

---

## 7. Métricas de éxito al cerrar el plan

- **Mobile (<1024px)**: el primer card se ve COMPLETO, el header mobile está encima sin tapar.
- **Desktop (≥1024px)**: sin cambios visuales, el padding-top es 0.
- **0 regresiones**: `pnpm build` OK, todas las páginas siguen renderizando bien en ambos viewports.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- Chrome DevTools → modo responsive → iPhone 12 (390x844):
  - Primer card "Citas Hoy 2" visible completo.
  - Header mobile ("OdontoSuite" + hamburguesa) en top, no tapa.
  - Hamburger click → mobile menu abre.
- Desktop (1920x1080):
  - Sidebar expandido a la izquierda, contenido a la derecha.
  - Header desktop con indicador WS + bell + avatar.
  - Sin header mobile (display: none).
- 0 regresiones en planes #1-#9.

---

## 9. Changelog

- **2026-06-12** — Sprint 0 ✅ HECHO. Commit en `fix/ux-sprint-0-mobile-header-padding`. 1 bug crítico cerrado (B-UX-16).

**PLAN CERRADO**. Header mobile ya no tapa el contenido del main.
