# UX/UI Guidelines - OdontoSuite V2

> **Version**: 1.0 (Sprint 1 - I-UX-1)
> **Fecha**: 2026-06-12
> **Estado**: fuente unica de verdad del design system
> **Plan origen**: `docs/mejoras/plan-ux-ui-2026-06.md` (secciones §4.1 a §4.6)
> **Benchmark visual**: `TreatmentPlansPage.vue` y sub-componentes (zona mejor lograda del proyecto)

## 1. Introduccion

Este documento es la **fuente unica de verdad** del design system de OdontoSuite V2. Cualquier decision de estilo, color, tipografia, sombra, animacion o accesibilidad que se tome al construir o refactorizar una vista debe partir de aqui. Si la guia y el codigo existente discrepan, **la guia gana**: el codigo se actualiza en el siguiente sprint de migracion (Sprints 3-6 del plan).

El look & feel objetivo es **Apple/iCloud**: blanco, mucho espacio, sombras sutiles, tipografia system, animaciones discretas con aceleracion GPU y foco visible. La zona del proyecto que mejor refleja este estilo es `TreatmentPlansPage.vue` (modulo de planes de tratamiento); usala como referencia visual al auditar otras paginas.

El design system esta implementado en tres capas que se complementan:

| Capa | Archivo | Que aporta |
|---|---|---|
| Tokens CSS | `resources/css/themes.css` | Variables CSS (`--color-accent`, `--color-text-primary`, `--shadow-md`, etc.) y utilities `.bg-theme-*` / `.text-theme-*` |
| Configuracion Tailwind | `tailwind.config.js` | Escala `primary` / `success` / `warning` / `error` / `info`, `fontSize`, `borderRadius`, `boxShadow`, `animation` y `keyframes` |
| Animaciones | `resources/css/animations.css` | Keyframes globales (`fadeIn`, `slideInUp`, `scaleIn`, `bounceIn`) y clases `.animate-*` |

Componentes UI reusables viven en `resources/js/components/ui/` (31 archivos, ver §9) y en `resources/js/components/layout/` (3 archivos). Antes de crear un componente nuevo, busca aqui.

## 2. Paleta canonica

Toda la aplicacion usa la paleta semantica definida en `tailwind.config.js` y `resources/css/themes.css`. Las utility classes se resuelven a variables CSS, lo que permite ajustar el color en un solo lugar.

| Caso de uso | Utility CSS | Hex | Ejemplo |
|---|---|---|---|
| Accion primaria (boton principal) | `bg-accent` | `#0066CC` | Boton "Guardar", "Crear cita" |
| Accion en hover | `bg-accent-hover` | `#0052a3` | Estado hover del boton primario |
| Accion active / pressed | `bg-accent-active` | `#003d7a` | Estado active del boton primario |
| Acento fondo suave | `bg-primary-50` / `bg-accent-light` | `#e6f0ff` | Fondo de un input enfocado, badge informativo |
| Texto sobre acento | `text-primary-700` | `#003d7a` | Texto dentro de un fondo `bg-primary-50` |
| Texto link | `text-accent` | `#0066CC` | Links inline, iconos de accion |
| Exito | `bg-success-500` / `bg-success-badge` | `#10b981` / `#d1fae5` | Badge de estado "Completado" |
| Advertencia | `bg-warning-500` / `bg-warning-badge` | `#f59e0b` / `#fef3c7` | Badge "Pendiente", alerta de stock bajo |
| Error / peligro | `bg-error-500` / `bg-danger-badge` | `#ef4444` / `#fee2e2` | Badge "Cancelado", mensaje de error |
| Informacion | `bg-info-500` | `#0066CC` | Tooltips, banners informativos |
| Texto principal | `text-theme-primary` | `#1D1D1F` | Parrafos, titulos |
| Texto secundario | `text-theme-secondary` | `#86868B` | Subtitulos, captions, labels de formulario |
| Borde | `border-theme` | `#d2d2d7` | Borde de inputs, separadores |
| Borde sutil | `border-theme-light` | `#e5e5e7` | Divisores dentro de cards |
| Surface (cards) | `bg-theme-surface-elevated` | `#FFFFFF` | Fondo de cards y modales |
| Background | `bg-theme-background` | `#FFFFFF` | Fondo de pagina |
| Fondo secundario | `bg-theme-background-secondary` | `#F5F5F7` | Fondo de areas inactivas, headers laterales |

### Prohibido

A partir de Sprint 3, las siguientes clases Tailwind crudas estan **prohibidas** en `resources/js/**/*.vue` y `resources/views/**/*.blade.php` (la lista completa la mantiene la verificacion automatica del Sprint 3):

- `bg-blue-*` / `text-blue-*` / `border-blue-*` (cualquier shade)
- `bg-indigo-*` / `text-indigo-*`
- `bg-purple-*` / `text-purple-*` / `border-purple-*` (el ofensor #1 historico del proyecto)
- `bg-violet-*` / `text-violet-*`
- `bg-cyan-*` / `text-cyan-*`
- `bg-sky-*`
- `bg-amber-*` / `text-amber-*`
- `bg-orange-*`
- `bg-pink-*` / `bg-rose-*` / `bg-fuchsia-*`
- `bg-emerald-*` / `bg-teal-*` / `bg-lime-*`
- `text-gray-*` excepto `text-gray-100/300/600/800` (casi-neutrales, se usan para texto deshabilitado)

Reemplazo directo: `bg-purple-600` -> `bg-accent`; `bg-purple-50` -> `bg-primary-50`; `border-purple-200` -> `border-theme-light`; `bg-emerald-500` -> `bg-success-500`; `bg-amber-500` -> `bg-warning-500`.

### Excepciones permitidas

Solo se permite usar una clase cruda prohibida si lleva un **comentario inline** que documente la intencion:

```vue
<!-- uso intencional: ilustrar un badge "deprecado" en el modulo de migracion -->
<span class="bg-amber-500 text-white">Legacy</span>
```

Las dos excepciones que la guia reconoce sin discusion:

1. Verde de success explicito (`bg-emerald-500`) - en la practica, no hay una razon valida; usar `bg-success-500`.
2. Amarillo de warning (`bg-amber-500`) - idem; usar `bg-warning-500`.

Si necesitas una tercera excepcion, documentala aqui antes de mergear.

## 3. Tipografia

Familia unica, definida en `tailwind.config.js`:

```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
```

Esta pila prioriza `-apple-system` en Mac/iOS, `Segoe UI` en Windows y `Roboto` en Android. En Linux cae a `Helvetica Neue` o `Arial`. **No importar Google Fonts ni Noto** - rompe la carga y la consistencia entre plataformas.

### Escala de tamano

Tamano y line-height ya estan configurados en `tailwind.config.js` (verificar antes de sobreescribir):

| Token | px / line-height | Caso de uso |
|---|---|---|
| `text-xs` | 11px / 16px | Labels pequenos, captions, atajos de teclado |
| `text-sm` | 13px / 18px | Body secundario, contenido de tablas, metadata |
| `text-base` | 15px / 22px | Body principal, descripciones, parrafos |
| `text-lg` | 17px / 24px | Subtitulos, titulo de card |
| `text-xl` | 20px / 28px | h3, titulo de dialog |
| `text-2xl` | 24px / 32px | h2, titulo de modal grande |
| `text-3xl` | 28px / 36px | h1, titulo de pagina |
| `text-4xl` | 34px / 40px | Display (hero, splash) |

### Letter spacing

- `tracking-tight` (-0.01em) en titulos `h1` / `h2` / `h3` (jerarquia visual tipo Apple).
- `tracking-normal` en body, botones, captions.

### Line height

Los line-heights ya estan en `tailwind.config.js` y son Apple-correct (mas compactos a medida que el tamano crece: 1.45 en xs, 1.18 en 4xl). **No sobreescribir** con clases como `leading-tight` salvo caso muy justificado.

## 4. Sombras

Escala de 6 niveles, definida en `tailwind.config.js` y duplicada como variables CSS en `themes.css` (`--shadow-sm/md/lg/xl/glass`). La migracion entre tokens se hace con `shadow-{token}` en clases Tailwind.

| Token | Valor CSS | Caso de uso |
|---|---|---|
| `shadow-subtle` | `0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)` | Cards en reposo |
| `shadow-soft` | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` | Cards en hover, dropdowns |
| `shadow-medium` | `0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)` | Botones en hover, popovers |
| `shadow-large` | `0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)` | Modales, sheets (side panel) |
| `shadow-elevated` | `0 25px 50px -12px rgba(0,0,0,0.25)` | Elementos que sobresalen (toasts, tooltips) |
| `shadow-glass` | `0 8px 32px rgba(31,38,135,0.37)` | Glass morphism (cards con `backdrop-blur`) |

Regla practica: a mayor elevacion, mayor sombra. Una card en reposo usa `shadow-subtle`; al hacer hover pasa a `shadow-soft`; al abrir un modal hijo usa `shadow-large`.

## 5. Border radius

Tokens en `tailwind.config.js` (alineados con los valores de iOS/macOS):

| Token | px | Caso de uso |
|---|---|---|
| `rounded-md` | 10px | Inputs, textareas, selects |
| `rounded-lg` | 12px | Botones, badges, tags |
| `rounded-xl` | 16px | Cards, contenedores principales |
| `rounded-2xl` | 20px | Modales, dialogs |
| `rounded-3xl` | 24px | Sheets (side panels), contenedores hero |
| `rounded-full` | 9999px | Avatares, pills, status badges, icon-buttons |

Regla practica: el radius escala con el tamano del contenedor. Un input pequeno usa `rounded-md`, una card mediana usa `rounded-xl`, un modal grande usa `rounded-2xl`.

## 6. Animaciones

Las animaciones se definen en `tailwind.config.js` (10 keyframes) y se complementan con clases globales en `resources/css/animations.css`. Todas usan `transform` u `opacity` (aceleracion GPU, no causan layout reflow).

| Animacion | Cuando usarla | Duracion |
|---|---|---|
| `animate-fade-in` | Carga inicial de pagina o seccion | 0.3s |
| `animate-scale-in` | Apertura de modal, aparicion de badge, ripple button | 0.2s |
| `animate-slide-up` | Contenido de modal al abrir, drawer desde abajo | 0.3s |
| `animate-slide-down` | Toast entrante, dropdown que cae | 0.3s |
| `animate-bounce-subtle` | Llamar atencion (cita en los proximos 30 min) | 0.6s loop |
| `animate-pulse-subtle` | Indicador "live" / "sincronizando" / Reverb conectado | 2s loop infinite |
| Ripple (interno de `Button.vue`) | Click en cualquier boton con variant `primary` | 0.6s |

### Regla de oro

Animar **solo** `transform` y `opacity`. Nunca `width`, `height`, `top`, `left`, `margin`, `padding`. Las animaciones de transform usan compositing en GPU; las de layout disparan reflow y se ven entrecortadas, especialmente en pantallas grandes o con poco refresco.

Anti-patron tipico que aparece en PRs:

```vue
<!-- MAL: anima width, causa reflow -->
<div :style="{ width: `${progress}%`, transition: 'width 0.3s' }" />
```

Forma correcta:

```vue
<!-- BIEN: anima transform: scaleX, GPU -->
<div :style="{ transform: `scaleX(${progress / 100})`, transformOrigin: 'left', transition: 'transform 0.3s ease-out' }" />
```

## 7. Accesibilidad

### Focus ring

Ya esta en `app.css` y replicado en `Button.vue`:

```css
box-shadow: 0 0 0 2px var(--color-accent);
```

**Nunca** usar `outline: none` o `outline: 0` sin reemplazo. Si necesitas esconder el outline en el estado por defecto, agregale un equivalente en `:focus-visible` (no en `:focus` - el primero solo aplica a teclado, no a click).

### prefers-reduced-motion

`animations.css` y `LoadingSpinner.vue` ya respetan la media query `prefers-reduced-motion: reduce`. Al crear componentes nuevos, agregar el override:

```css
@media (prefers-reduced-motion: reduce) {
  .mi-animacion { animation: none; transition: none; }
}
```

### ARIA

- `aria-label` obligatorio en icon-buttons (botones que solo muestran un icono sin texto visible).
- `aria-live="polite"` en regiones de toasts y contadores en tiempo real (BI, caja).
- `role="status"` en spinners y skeletons de carga para que el lector de pantalla anuncie "cargando".
- `aria-haspopup` + `aria-expanded` en menus y dropdowns. Hay un ejemplo vivo en `TreatmentPlanCard.vue:111`.

### Touch targets

- Botones en general: `min-h-[44px]` (recomendacion WCAG 2.5.5 / Apple HIG).
- Botones pequenos o icon-buttons en areas densas: `min-h-[36px]` permitido.
- Links inline: asegurar que la zona clickeable cubre al menos 24px de alto via padding.

Estas alturas ya estan en las clases `size` de `Button.vue` (ver `min-h-[44px]` en `md`, `min-h-[36px]` en `sm`).

## 8. Router key

`resources/js/app.js` usa `template: '<router-view :key="$route.fullPath" />'`. Esta decision se documento en el Sprint 0 (hallazgo C-UX-2) y se mantiene por las siguientes razones:

- `$route.fullPath` incluye el query string (`/patients?status=active`); `$route.path` solo la ruta.
- Sin la `key`, Vue Router **reusa la misma instancia** del componente al ir/volver. `onMounted` no se vuelve a llamar, los listeners WebSocket quedan colgados, los filtros de pagina no se reinicializan.
- Con `:key="$route.fullPath"`, cualquier cambio de ruta (path, query, hash) remonta el componente y dispara el ciclo de vida completo.

### Trade-off aceptado

Se pierde la **posicion de scroll** y el **estado de forms** al cambiar de pagina. Para una app de gestion clinica (donde cada vista es un destino discreto, no un paso de un wizard largo), es aceptable. Los usuarios no esperan volver al mismo scroll de hace 5 minutos.

### Si en futuro se quiere preservar estado

Agregar `<keep-alive :include="['DashboardPage', 'CalendarPage']">` selectivo, con la lista explicita de componentes que vale la pena cachear. No usar `keep-alive` global: reintroduce el bug original de listeners colgados en modulos con WebSocket.

## 9. Componentes UI disponibles

`resources/js/components/ui/` contiene 31 componentes. Antes de crear un componente nuevo o un spinner/spinner/empty-state ad-hoc, busca aqui:

| Componente | Cuando usarlo |
|---|---|
| `Avatar.vue` | Mostrar iniciales o imagen de un usuario/paciente en cards y listas |
| `Badge.vue` | Etiqueta pequena con color semantico (estado, contador) |
| `Breadcrumbs.vue` | Navegacion jerarquica (Patient > PatientDetail > Edit) |
| `Button.vue` | Cualquier accion del usuario. 7 variants: primary / secondary / ghost / danger / success / warning / icon. Lleva ripple por defecto |
| `Card.vue` | Contenedor visual. 5 variants: default / glass / flat / elevated / outlined |
| `CurrencyInput.vue` | Input de monto con formato moneda (PEN/USD) y validacion |
| `DataTable.vue` | Tabla con sort, paginacion, seleccion multiple. Para listas > 10 items |
| `EmptyState.vue` | Estado vacio con icono + titulo + descripcion + CTA |
| `FileUpload.vue` / `FileUploader.vue` | Subida de archivos (imagenes, PDFs). Usar `FileUploader` para drag & drop |
| `Input.vue` | Input de texto con floating label y validacion inline |
| `LazyImage.vue` | Imagen con lazy-loading nativo y placeholder |
| `LoadingSpinner.vue` | Spinner de 3 anillos concentricos con la paleta correcta. Usar en vez de `border-purple-*` inline |
| `Modal.vue` | Dialog modal con `Teleport` y animacion scale+fade. Es la base de `ConfirmDialog` |
| `NotificationToast.vue` / `Toast.vue` | Notificaciones efimeras. `Toast` para uso generico, `NotificationToast` para variantes con icono y color semantico |
| `Pagination.vue` | Paginacion numerada, no reinventar |
| `PatientSelector.vue` | Selector buscable de paciente (reusado en citas, cotizaciones, historias) |
| `ProcedureSelector.vue` | Selector buscable de procedimiento del catalogo |
| `RadioGroup.vue` | Grupo de radios con label y descripcion |
| `ReceiptPreview.vue` | Vista previa de comprobante de pago (PDF) |
| `RichTextEditor.vue` | Editor WYSIWYG para notas clinicas |
| `Select.vue` | Dropdown nativo estilado con la paleta |
| `Sheet.vue` | Panel lateral deslizante (side panel) |
| `Skeleton.vue` | Placeholder de carga animado. Usar en listas, cards, dashboards |
| `Tabs.vue` | Tabs horizontales con contenido lazy |
| `ThemeSelector.vue` | Selector de tema (actualmente solo hay tema claro) |
| `ToothSelector.vue` | Selector de piezas dentales (odontograma) |
| `TreatmentPlanSelector.vue` | Selector buscable de plan de tratamiento |
| `UiTextarea.vue` | Textarea multilinea con validacion y contador |
| `Pagination.vue` | Paginacion (ya listada, no duplicar) |
| `ReceiptPreview.vue` | Vista previa de comprobante (ya listada) |

`resources/js/components/layout/` tiene 3 adicionales:

| Componente | Cuando usarlo |
|---|---|
| `AppLayout.vue` | Layout raiz: header + sidebar + main. Lo usan todas las paginas autenticadas |
| `FloatingActionButton.vue` | FAB para accion primaria en mobile (ej. "Nueva cita") |
| `MobileMenu.vue` | Menu hamburguesa para resolucion mobile |

### Componentes nuevos del Sprint 1

Estos se crean en este sprint (no existian antes) y se documentan en la guia para que cualquier dev los encuentre:

| Componente | Cuando usarlo | Ubicacion |
|---|---|---|
| `PageHeader` | Cabecera de pagina (titulo + subtitulo + acciones + breadcrumbs) | `components/layout/PageHeader.vue` |
| `StatusPill` | Badge con color semantico mapeado por string (`scheduled`, `completed`, etc.) | `components/ui/StatusPill.vue` |
| `ProgressBar` | Barra de progreso con threshold de color (rojo < 30, ambar 30-60, verde 60+) | `components/ui/ProgressBar.vue` |
| `ConfirmDialog` | Modal de confirmacion (reemplaza `window.confirm()`) | `components/ui/ConfirmDialog.vue` |
| `FilterBar` | Filtros siempre visibles tipo iCloud (grid con N columnas) | `components/ui/FilterBar.vue` |

## 10. Anti-patrones comunes

Lista corta de errores que se repiten en PRs y code reviews. Si encontras alguno, el fix inmediato es migrar a la alternativa; el fix sistémico se hace en los Sprints 3-6.

| Anti-patron | Por que esta mal | Alternativa |
|---|---|---|
| Colores Tailwind crudos (`bg-blue-*`, `text-purple-600`, `border-indigo-*`) | Rompe la paleta, da look "Bootstrap con Tailwind" en vez de Apple | `bg-accent` / `text-accent` / `border-theme` (ver §2) |
| `window.confirm()` nativo en handlers de "Eliminar" | Rompe el look, no se puede personalizar, no es accesible | `<ConfirmDialog>` (ver §9) |
| Spinner inline con `border-purple-200 border-t-purple-600` | Color crudo, animacion sin GPU, inconsistente con la paleta | `<LoadingSpinner>` (3 anillos, paleta correcta) |
| Spinner con texto "Cargando..." sobre fondo blanco | El usuario no percibe el progreso, la pagina "salta" al terminar | `<Skeleton type="card" :count="6" />` para listas, o `<LoadingSpinner>` con `animate-fade-in` |
| Empty state ad-hoc (icono + parrafo centrados a mano) | Cada vista lo hace diferente, sin CTA | `<EmptyState icon="..." title="..." description="..." action-label="..." @action="..." />` |
| Loading inline sin animacion (`v-if="loading"` que aparece y desaparece seco) | Sensacion de "salto" | `<LoadingSpinner class="animate-fade-in" />` o `<Skeleton>` |
| `outline: none` o `outline: 0` sin reemplazo | Quita el foco visible, rompe accesibilidad de teclado | `focus-visible:ring-2 focus-visible:ring-accent` o `box-shadow: 0 0 0 2px var(--color-accent)` |
| `<router-view />` sin `:key` | El componente no remonta al cambiar de ruta, listeners WebSocket quedan colgados | `<router-view :key="$route.fullPath" />` (Sprint 0) |
| Animar `width` / `height` / `top` / `left` | Causa reflow, se ve entrecortado, peor en pantallas grandes | Animar `transform` y `opacity` (ver §6 regla de oro) |

Si encuentras un anti-patron que no esta en esta lista, anotalo en el siguiente sprint de pulido (Sprint 7) o en un code review de seguimiento.
