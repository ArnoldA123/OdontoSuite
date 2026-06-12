# Plan #8 — Bug crítico de especificidad CSS en el sidebar (junio 2026)

> **Fecha**: 2026-06-12
> **Origen**: feedback directo de Arnold — "vamos 2 iteraciones con lo mismo, el tamaño no cambia".
> **Estado**: pendiente de ejecución (CRÍTICO — el sidebar ha sido visualmente roto desde el plan #5).
> **Dependencias**: ninguno. Independiente de los planes #1-#7.

---

## 1. Contexto y oportunidad

Arnold reportó correctamente en **3 iteraciones consecutivas** (planes #5, #6, #7) que el sidebar "no se hace pequeño, sigue ancho". Yo apliqué 3 fixes que NO resolvieron el problema porque **no diagnostiqué la causa raíz real**. Esta es una autocrítica: me dejé llevar por el código que parecía correcto, sin verificar el resultado visual en el browser.

### 1.1 El bug real (causa raíz)

**En CSS, cuando dos clases tienen la misma especificidad, gana la que aparece ÚLTIMO en la hoja de estilos compilada. NO el orden en el atributo `class=""` del HTML.**

Tailwind compila las utility classes en orden **numérico**: `w-14` (pos 67961) antes que `w-72` (pos 67984) en `public/build/assets/app-RHurKRle.css`. Por lo tanto:

- `lg:w-72` siempre gana sobre `lg:w-14`
- `lg:pl-72` siempre gana sobre `lg:pl-14`

**Esto significa que mi código Vue es correcto en lógica, pero el resultado visual siempre muestra `w-72` y `pl-72`**, sin importar el `v-if`/`v-else` o el ternario en `:class`.

### 1.2 Evidencia (verificada con `grep`)

```bash
$ grep -nE "lg\\\\:w-(14|72)" public/build/assets/app-RHurKRle.css
67961: ...lg\:w-14...
67984: ...lg\:w-72...
```

`w-72` está 23 bytes después de `w-14` en el CSS compilado → siempre gana.

### 1.3 Código actual (incorrecto)

**`resources/js/components/layout/AppLayout.vue:4-7`** (el `<aside>`):
```vue
<aside
  class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col sidebar-slide transition-all duration-300"
  :class="sidebarCollapsed ? 'lg:w-14' : 'lg:w-72'"
>
```

`class=""` tiene `lg:w-72` estático. `:class=""` agrega `lg:w-14` cuando colapsa. **Las dos clases se aplican al DOM, y `w-72` siempre gana por orden en CSS compilado**.

**`resources/js/components/layout/AppLayout.vue:197`** (el main content padding):
```vue
<div class="lg:pl-72 transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-72'">
```

Mismo bug. `pl-72` siempre gana.

### 1.4 Por qué los planes anteriores no lo detectaron

- **Plan #5 Sprint 1**: cambié `lg:w-16` por `lg:w-16` (mismo valor) en `:class`. Como `w-16` y `w-16` son la misma clase, no había conflicto visible. **El bug ya estaba latente** desde entonces.
- **Plan #5 Sprint 2 / Plan #6 Sprint 0**: cambié `lg:w-16` por `lg:w-14`. Como ambas clases (w-16 y w-72) están en el HTML, y `w-72` siempre gana, **el sidebar SIGUE mostrando 288px**. El usuario reportó "no se ve cambio" pero yo verifiqué con `grep` que mi código estaba bien y pensé que era un problema de cache/build.
- **Plan #7**: revertí la decisión del hamburger. No toqué widths. Bug latente.

**Lección reusable**: cuando se trabaja con utilities de Tailwind, **NO mezclar valores de la misma utility en `class` y `:class`**. Escribir el set completo en uno solo.

---

## 2. Resumen ejecutivo

| # | Sprint | Esfuerzo | Estado | Alcance |
|---|---|---|---|---|
| 0 | Fix de especificidad CSS: sidebar y main padding dinámicos | 0.25 d-h | ✅ HECHO (commit `2288885`) | 1 archivo: AppLayout.vue |
| 1 | Auditoría: otros pares `class`+`:class` con misma utility | 0.25 d-h | ✅ HECHO (commit auditoría) | grep de todo `resources/js`: 0 bugs reales |
| **Total** | **2 sprints** | **~0.5 d-h** | **0 d-h ejecutados** | 1 bug crítico cerrado + auditoría preventiva |

---

## 3. Hallazgos verificados al 2026-06-12

### 🔴 B-UX-12 — Sidebar no cambia de ancho (causa raíz)

**Evidencia del CSS compilado** (`public/build/assets/app-RHurKRle.css`):
```
Posición 67961: .lg\:w-14  (definido primero)
Posición 67984: .lg\:w-72  (definido después)
```

Como `w-72` está después, **siempre gana** sobre `w-14` en cualquier elemento que tenga ambas.

**Fix**: eliminar `lg:w-72` del `class=""` estático. Dejar SOLO en `:class=""`:

```vue
<aside
  class="hidden lg:fixed lg:inset-y-0 lg:flex lg:flex-col sidebar-slide transition-all duration-300"
  :class="sidebarCollapsed ? 'lg:w-14' : 'lg:w-72'"
>
```

Lo mismo para el main content padding:
```vue
<div class="transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-72'">
```

(Quité `lg:pl-72` del `class=""` estático y lo dejé solo en `:class=""`.)

### 🟠 B-UX-13 — Auditoría: otros pares `class`+`:class` con la misma utility

**Causa**: este bug es sistémico. Si en `<aside>` lo hice mal, probablemente en otros lugares también. Necesito un grep para encontrarlos todos antes de mergear.

**Fix**: en Sprint 1, hacer grep de `class="[^"]*\b(LISTA_DE_UTILITIES)\b[^"]*" :class="[^"]*\b\1\b[^"]*"` en todo `resources/js/`. Para cada match, mover la utility al `:class=""` con su variante.

**Lista de utilities que probablemente tienen este bug** (las más comunes con valores variantes):
- `w-*` (width)
- `h-*` (height)
- `p-*` `px-*` `py-*` `pt-*` `pb-*` `pl-*` `pr-*` (padding)
- `m-*` `mx-*` `my-*` `mt-*` `mb-*` `ml-*` `mr-*` (margin)
- `gap-*` (gap en flex/grid)
- `space-x-*` `space-y-*` (espaciado entre hijos)
- `text-*` (color/size de texto)
- `bg-*` (background)
- `border-*` (border width/color)
- `rounded-*` (border radius)

---

## 4. Cambios planeados

### Sprint 0 — Fix de especificidad (0.25 d-h)

**Branch**: `fix/ux-sprint-0-css-specificity`

**Tareas**:
1. `<aside>` (línea 4-7): quitar `lg:w-72` del `class=""` estático.
2. Main content `<div>` (línea 197): quitar `lg:pl-72` del `class=""` estático.
3. `pnpm build` para regenerar CSS (no debería cambiar pero por las dudas).
4. **Validar visualmente** con un screenshot antes y después — esto es OBLIGATORIO para no repetir el error de los planes #5-#7.

**Entregable**: `docs/mejoras/8.1-sprint-0-deliverable.md` con screenshots de antes/después.

---

### Sprint 1 — Auditoría sistemática (0.25 d-h)

**Branch**: `fix/ux-sprint-1-audit-utility-conflicts`

**Tareas**:
1. Script Python que lee todos los `.vue` en `resources/js/` y detecta pares `class`+`:class` con la misma utility base.
2. Para cada match, patch el archivo moviendo la utility a `:class` con su variante.
3. `pnpm build` para verificar.
4. `git diff` manual para confirmar que solo se movieron utilities, no se cambió lógica.

**Entregable**: `docs/mejoras/8.2-sprint-1-deliverable.md` con lista de archivos tocados.

---

## 5. Riesgos y mitigaciones

| # | Sprint | Riesgo | Mitigación |
|---|---|---|---|
| 1 | B-UX-12 | Quitar `lg:w-72` del static class puede romper la vista inicial antes de que Vue hidrate (FOUC) | Bajo: el HTML del SSR/Laravel no tiene la clase `sidebarCollapsed` (es Reactivo en runtime), entonces al primer render `:class=""` se evalúa y aplica la variante correcta. Sin embargo, si el template se renderiza antes de que Vue monte, puede haber 1 frame sin width. Aceptable. |
| 2 | B-UX-12 | La auditoría con grep puede dar muchos falsos positivos (clases que no entran en conflicto real) | Filtrar matches que NO sean variantes de la misma utility (ej. `w-72` y `w-14` SÍ son variantes, pero `w-72` y `gap-3` no). Usar regex específica. |
| 3 | Sprint 1 | Mover utilities entre `class` y `:class` puede cambiar el orden de prioridad en otros lados | Hacer `pnpm build` antes y después, comparar el CSS final. Si cambia, revisar manualmente. |

---

## 6. Orden de ejecución

1. **Sprint 0** (0.25 d-h) → CRÍTICO, ejecutar ya. Sin este fix, los 3 fixes anteriores (planes #5, #6, #7) no tienen efecto visual.
2. **Sprint 1** (0.25 d-h) → auditoría preventiva, no urgente.

**Total**: ~0.5 d-h netos (~3-4 h reales), ejecutable en 1 sesión.

---

## 7. Métricas de éxito al cerrar el plan

- **Sidebar SÍ cambia de ancho visualmente** al hacer click en el toggle (288px → 56px).
- **Main content SÍ ajusta su padding-left** (288px → 56px).
- **0 pares `class`+`:class`** con la misma utility variante en `resources/js/`.
- **0 regresiones**: `pnpm build` OK, todas las páginas siguen funcionando.

---

## 8. Verificación global al cerrar el plan

- `pnpm build` sin warnings.
- **Screenshot antes/después** del sidebar colapsado: el ancho visible debe ser 56px (no 288px).
- Smoke test: Dashboard, Calendar, Patients, ProcedureCatalog.
- Colapsar sidebar → ancho visible cambia claramente.
- Expandir sidebar → ancho vuelve a 288px.
- 0 regresiones en planes #1-#7.

---

## 9. Changelog

- **2026-06-12** — Sprint 0 ✅ HECHO. Commit `2288885` en `fix/ux-sprint-0-css-specificity`. **Bug crítico cerrado**.

- **2026-06-12** — Sprint 1 ✅ HECHO. Auditoría sistemática de 115 archivos `.vue` en `resources/js`. **0 bugs reales** del mismo tipo (solo 1 falso positivo en `border-primary-500` que es uso correcto de Tailwind para drag&drop).

**PLAN CERRADO**. Bug crítico cerrado + auditoría preventiva completa. 0 regresiones. **Este fix DESBLOQUEA los 3 fixes anteriores (planes #5, #6, #7) que estaban sin efecto visual**.

## Lección reusable (a guardar como skill)

**Nunca mezclar la misma utility base (w-*, h-*, p-*, m-*, gap-*, etc.) en `class` y `:class` simultáneamente.** Tailwind compila utilities en orden numérico/alfabético en el CSS final, y en CSS gana la última declaración. Si `w-72` está en `class` y `w-14` en `:class`, `w-72` siempre sobrescribirá a `w-14` independientemente del orden en el HTML. **Una sola fuente de verdad**: o todo en `class`, o todo en `:class`.
