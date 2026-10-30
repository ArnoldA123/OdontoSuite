# Sistema de diseño — OdontoSuite V2

> **Qué es esto.** La captura en un solo documento del sistema de diseño que el
> repositorio ya implementa: tokens canónicos, tipografía, espaciado, radios,
> sombras, movimiento y el contrato visual de las primitivas de
> `resources/js/components/ui/`. No es una propuesta ni un rediseño: cada valor
> de este documento se leyó del árbol y nombra su archivo de origen. Cuando un
> componente declara algo distinto de un token, se dice cuál es la diferencia y
> se mide su consecuencia (ver §10).
>
> **Fecha de captura:** 2026-10-29.
>
> **Fuentes leídas (solo lectura, ninguna modificada):**
>
> | Ruta                                                                                 | Qué se tomó                                                                                                        |
> | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ |
> | `resources/js/design-system/tokens.js`                                               | Paletas, tipografía, spacing, radius, shadow, motion, focusRing, elevation, topbar                                 |
> | `tailwind.config.js`                                                                 | Consumo de `tokens.js`, `zIndex`, `transitionDuration`, `transitionTimingFunction`, keyframes y utilidades de tema |
> | `resources/css/tokens.generated.css`                                                 | Variables CSS emitidas, alias semánticos, `.surface-glass` y media queries                                         |
> | `resources/css/app.css`                                                              | Orden de imports, utilidades `.apple-shadow*`, `.smooth-transition`, foco base y print                             |
> | `resources/css/utilities.css`                                                        | `.focus-ring`, `.hover-lift`, `.skeleton`, `.spinner-ring`, `.pulse-subtle`, scrollbar y `prefers-reduced-motion`  |
> | `resources/js/components/ui/` — 34 primitivas; las 18 documentadas en §7 (16 reglas) | Variantes, tamaños y contrato visual                                                                               |
> | `docs/ux-guidelines.md`                                                              | §9 vocabulario de acciones y §10 anti-patrones                                                                     |
>
> **Fuera de este documento:** la explicación arquitectónica de por qué el
> sistema está partido así vive en `docs/architecture/system-design.md`; el
> inventario de pantallas y la guía de UX completa viven en
> `docs/ux-guidelines.md`.

---

## 1. Colores

La cadena de autoridad es una sola: `tokens.js` define, `tailwind.config.js`
consume para las utilidades y `tokens.generated.css` emite las variables CSS
(`--color-…`). Ningún archivo de `resources/js` debe declarar un color crudo.
Convención de lectura: cada tabla cierra con su línea **Fuente**, que nombra el
archivo y la clave exacta de los que salen **todas** sus filas; cuando una fila
tiene un origen distinto, lo dice en su propia celda.

### 1.1 Rampa de acento (`accent`)

Es la rampa canónica de marca: verde clínico profundo. La neutra carga la
identidad y el acento es **un** token intercambiable, así que un despliegue
multi-clínica puede repuntarla sin tocar componentes.

| Token        | Valor     | Uso                                                                                 |
| ------------ | --------- | ----------------------------------------------------------------------------------- |
| `accent-50`  | `#f2f8f5` | Superficie teñida más tenue (fondo de login, `--color-accent-light`)                |
| `accent-100` | `#c9e7dc` | Fondo de estado suave, barras de progreso                                           |
| `accent-200` | `#93cdb9` | Borde de chips primary, trazos de motivo dental                                     |
| `accent-300` | `#5cb195` | Acento decorativo, series de gráfico                                                |
| `accent-400` | `#2a9070` | Acento decorativo sobre canvas                                                      |
| `accent-500` | `#0f7a5f` | **Acento canónico**: fondo de CTA primaria, texto de enlace, foco, `--color-accent` |
| `accent-600` | `#0a5f49` | Hover del CTA primario (`--color-accent-hover`)                                     |
| `accent-700` | `#084c3b` | Active del CTA (`--color-accent-active`), texto sobre `accent-50`                   |
| `accent-800` | `#063a2d` | Superficie oscura de marca                                                          |
| `accent-900` | `#04281f` | Fondo de máxima profundidad de la rampa                                             |

Fuente: `resources/js/design-system/tokens.js` (`colors.accent`) y
`resources/css/tokens.generated.css` (`--color-accent-50` …
`--color-accent-900`). El comentario de `tokens.js` fija además la medición que
hizo aceptable la rampa: blanco sobre `500` = 5.29:1, blanco sobre `600` =
7.65:1, `500` como texto sobre blanco = 5.29:1 y sobre canvas = 4.90:1.

**Cambio de esta revisión (2026-10-30).** `accent-50` pasa de `#e6f4ef` a
`#f2f8f5`, el paso que se probó en el login: el color se retira y la separación
de la tarjeta queda en la sombra por capas. El valor anterior se retira de la
paleta. Consecuencia asumida: un input enfocado ya no gana un lavado de fondo
perceptible, así que la señal de foco descansa en el borde y el anillo de 3 px.
`tokens.js` y `tokens.generated.css` todavía conservan `#e6f4ef`; este documento
registra el valor aprobado y la implementación queda pendiente.

### 1.2 Rampas de sistema (iOS 13+)

| Rampa          | 50        | 100       | 200       | 300       | 400       | 500       | 600       | 700       |
| -------------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- |
| `systemRed`    | `#ffebea` | `#ffd9d7` | `#ffb8b3` | `#ff9890` | `#ff776c` | `#ff3b30` | `#d70015` | `#a50e10` |
| `systemOrange` | `#fff1e5` | `#ffe2c7` | `#ffd09d` | `#ffbd73` | `#ffab49` | `#ff9500` | `#c93400` | `#9a2700` |
| `systemYellow` | `#fff9d6` | `#fff1ad` | `#ffe57f` | `#ffd84f` | `#ffd11f` | `#ffcc00` | `#a57000` | `#7a5200` |
| `systemGreen`  | `#e8f5e9` | `#cdedcf` | `#a4d8a8` | `#8ed29c` | `#5cc46c` | `#34c759` | `#248a3d` | `#1a6530` |
| `systemIndigo` | `#efe9ff` | `#dfd2ff` | `#c8b6ff` | `#b09aff` | `#997eff` | `#5856d6` | `#3f3dab` | `#2d2b80` |
| `systemPurple` | `#f6e9ff` | `#ecd2ff` | `#dfaeff` | `#d189ff` | `#c365ff` | `#af52de` | `#7a38a1` | `#55276f` |
| `systemPink`   | `#ffe9f0` | `#ffd2dd` | `#ffa9b8` | `#ff7f93` | `#ff556e` | `#ff2d55` | `#c30039` | `#8e0028` |
| `systemGray`   | `#f7f6f4` | `#edebe7` | `#dcd8d2` | `#d2cec7` | `#b5b0a8` | `#908b82` | `#635e57` | `#3b3833` |
| `systemSteel`  | `#eef3f8` | `#dbe5ef` | `#bed0e0` | `#9db7cd` | `#7095b3` | `#4a7191` | `#3a5a75` | `#2b4459` |

Fuente: `resources/js/design-system/tokens.js` (`colors.system*`) y
`resources/css/tokens.generated.css` (`--color-system-<rampa>-<paso>`).

Notas de uso, todas declaradas en el origen:

- `systemRed` es error/peligro, `systemYellow` es advertencia y `systemGreen` es
  éxito. `systemSteel` existe porque `systemBlue` dejó de ser un tono de estado
  al convertirse en alias del acento: `info` y `success` colapsaban a 27.4° de
  matiz y se leían como la misma píldora; `info` sobre acero restituye 69.8°.
- `systemBlue` no es una rampa independiente: es el alias retirado de `accent`
  (§1.5).
- El paso `400` de `systemGreen` se corrigió para que la rampa sea monótona en
  luminancia; `500` sigue siendo `#34c759`, la identidad iOS de éxito.

### 1.3 Fondos, etiquetas, separador, borde y relleno

| Token                            | Valor                       | Uso                                                                                                                                                                              |
| -------------------------------- | --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `background.systemBackground`    | `#ffffff`                   | Superficie base de página, cards y modales (`--color-background`, `--color-surface-elevated`)                                                                                    |
| `background.secondaryBackground` | `#f7f6f4`                   | Fondo de área inactiva, header de tabla de filtros (`--color-background-secondary`, `--color-surface`)                                                                           |
| `background.tertiaryBackground`  | `#ffffff`                   | Tercera capa, hoy idéntica a la base                                                                                                                                             |
| `background.groupedBackground`   | `#f7f6f4`                   | Fondo agrupado de listas                                                                                                                                                         |
| `background.canvas`              | `#f7f6f4`                   | Lienzo de la app y de los heroes (`--color-canvas`)                                                                                                                              |
| `label.label`                    | `#1a1917`                   | Texto primario, títulos, cifras (`--color-text-primary`)                                                                                                                         |
| `label.secondaryLabel`           | `#5c5a55`                   | Texto secundario, captions, labels de formulario (`--color-text-secondary`)                                                                                                      |
| `label.tertiaryLabel`            | `rgba(26, 24, 20, 0.30)`    | Texto terciario: icono del vacío de sección (`UiSectionEmpty`), marca de agua de login y 404 (`--color-text-tertiary`)                                                           |
| `label.quaternaryLabel`          | `rgba(26, 24, 20, 0.18)`    | Definido y emitido; **sin consumidor medido** en `resources/js`                                                                                                                  |
| `separator.separator`            | `#c9c5bd`                   | Borde de 1 px de todo control y divisor de filas (`--color-border`); bajo `prefers-contrast: more`, `--color-border` sube a `secondaryLabel` y `--color-border-strong` a `label` |
| `border.hairline`                | `rgba(26, 24, 20, 0.10)`    | Hairline de superficies y bordes de card elevada (`--color-hairline`, utilidad `border-hairline`)                                                                                |
| `fill.systemFill`                | `rgba(120, 116, 108, 0.20)` | Relleno de fila agrupada                                                                                                                                                         |
| `fill.secondarySystemFill`       | `rgba(120, 116, 108, 0.16)` | Relleno secundario                                                                                                                                                               |
| `fill.tertiarySystemFill`        | `rgba(120, 116, 108, 0.12)` | Relleno terciario                                                                                                                                                                |

Fuente: `resources/js/design-system/tokens.js` (`colors.background`,
`colors.label`, `colors.separator`, `colors.border`, `colors.fill`) y
`resources/css/tokens.generated.css`. El comentario de `tokens.js` advierte que
un hairline de 1 px usado como **único** borde de un control interactivo sí cae
bajo WCAG 1.4.11 (3:1), aunque un divisor decorativo esté exento.

### 1.4 Alias semánticos (variables CSS)

`tokens.generated.css` publica la capa que consumen las utilidades de
`tailwind.config.js`. Son 37 alias `--color-*`, más los tres del vidrio.

| Variable                       | Resuelve a                                | Valor final              |
| ------------------------------ | ----------------------------------------- | ------------------------ |
| `--color-accent`               | `--color-accent-500`                      | `#0f7a5f`                |
| `--color-accent-hover`         | `--color-accent-600`                      | `#0a5f49`                |
| `--color-accent-active`        | `--color-accent-700`                      | `#084c3b`                |
| `--color-accent-light`         | `--color-accent-50`                       | `#f2f8f5`                |
| `--color-primary`              | `--color-accent-500`                      | `#0f7a5f`                |
| `--color-primary-hover`        | `--color-accent-600`                      | `#0a5f49`                |
| `--color-primary-active`       | `--color-accent-700`                      | `#084c3b`                |
| `--color-primary-light`        | `--color-accent-50`                       | `#f2f8f5`                |
| `--color-primary-dark`         | `--color-accent-700`                      | `#084c3b`                |
| `--color-background`           | `--color-background-system-background`    | `#ffffff`                |
| `--color-background-secondary` | `--color-background-secondary-background` | `#f7f6f4`                |
| `--color-surface`              | `--color-background-secondary-background` | `#f7f6f4`                |
| `--color-surface-elevated`     | `--color-background-system-background`    | `#ffffff`                |
| `--color-canvas`               | `--color-background-canvas`               | `#f7f6f4`                |
| `--color-text-primary`         | `--color-label-label`                     | `#1a1917`                |
| `--color-text-secondary`       | `--color-label-secondary-label`           | `#5c5a55`                |
| `--color-text-tertiary`        | `--color-label-tertiary-label`            | `rgba(26, 24, 20, 0.30)` |
| `--color-text-inverse`         | `--color-background-system-background`    | `#ffffff`                |
| `--color-border`               | `--color-separator-separator`             | `#c9c5bd`                |
| `--color-border-light`         | `--color-system-gray-100`                 | `#edebe7`                |
| `--color-border-strong`        | `--color-system-gray-500`                 | `#908b82`                |
| `--color-hairline`             | literal                                   | `rgba(26, 24, 20, 0.10)` |
| `--color-danger`               | `--color-system-red-500`                  | `#ff3b30`                |
| `--color-danger-bg`            | `--color-system-red-50`                   | `#ffebea`                |
| `--color-danger-text`          | `--color-system-red-700`                  | `#a50e10`                |
| `--color-danger-light`         | `--color-system-red-50`                   | `#ffebea`                |
| `--color-danger-dark`          | `--color-system-red-700`                  | `#a50e10`                |
| `--color-success`              | `--color-system-green-500`                | `#34c759`                |
| `--color-success-bg`           | `--color-system-green-50`                 | `#e8f5e9`                |
| `--color-success-text`         | `--color-system-green-700`                | `#1a6530`                |
| `--color-success-light`        | `--color-system-green-50`                 | `#e8f5e9`                |
| `--color-success-dark`         | `--color-system-green-700`                | `#1a6530`                |
| `--color-warning`              | `--color-system-yellow-500`               | `#ffcc00`                |
| `--color-warning-bg`           | `--color-system-yellow-50`                | `#fff9d6`                |
| `--color-warning-text`         | `--color-system-yellow-700`               | `#7a5200`                |
| `--color-warning-light`        | `--color-system-yellow-50`                | `#fff9d6`                |
| `--color-warning-dark`         | `--color-system-yellow-700`               | `#7a5200`                |

Fuente: `resources/css/tokens.generated.css` (bloque
`semantic aliases (iOS clinical)`), consumido por las utilidades `bg-theme-*`,
`text-theme-*`, `border-theme*`, `bg-accent*`, `bg-*-badge`, `bg-canvas`,
`text-label`, `border-hairline` y `ring-accent` de `tailwind.config.js`.

### 1.5 Alias deprecados

Se conservan para que las clases Tailwind de los módulos sin migrar sigan
resolviendo. **No se agregan consumidores nuevos** (lo dice el comentario de
`tokens.js`; el guard `LegacyAliasForbiddenTest` vigila la superficie).

| Alias          | Valores                                                                                                                                             | Resuelve a                                                                              |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| `systemBlue`   | `50 #f2f8f5`, `100 #c9e7dc`, `200 #93cdb9`, `300 #5cb195`, `400 #2a9070`, `500 #0f7a5f`, `600 #0a5f49`, `700 #084c3b`                               | `accent-50` … `accent-700`                                                              |
| `cream`        | `50 #f7f6f4`, `100 #edebe7`, `200 #dcd8d2`                                                                                                          | `systemGray-50`, `-100`, `-200`                                                         |
| `terracotta`   | `500 #0f7a5f`, `600 #0a5f49`                                                                                                                        | `accent-500`, `accent-600`                                                              |
| `clinicalTeal` | `50 #f2f8f5`, `500 #0f7a5f`, `600 #0a5f49`                                                                                                          | `accent-50`, `accent-500`, `accent-600`                                                 |
| `info`         | `500 #4a7191`                                                                                                                                       | `systemSteel-500` (estado semántico, no la marca)                                       |
| `primary`      | `50 #f2f8f5`, `100 #c9e7dc`, `200 #93cdb9`, `300 #5cb195`, `400 #2a9070`, `500 #0f7a5f`, `600 #0a5f49`, `700 #084c3b`, `800 #063a2d`, `900 #04281f` | `accent-50` … `accent-900`                                                              |
| `neutral`      | `50 #ffffff`, `100 #f7f6f4`, `200 #edebe7`, `500 #908b82`, `700 #3b3833`, `900 #1a1917`                                                             | Blanco, `systemGray-50`, `-100`, `-500`, `-700`, y `label`                              |
| `success`      | `50 #e8f5e9`, `100 #cdedcf`, `300 #a4d8a8`, `500 #34c759`, `600 #248a3d`, `700 #1a6530`, `900 #0f4520`                                              | `systemGreen-50`, `-100`, `-200`, `-500`, `-600`, `-700` y un verde legacy de contraste |
| `warning`      | `50 #fff9d6`, `100 #fff1ad`, `300 #ffe07a`, `500 #ffcc00`, `600 #a57000`, `700 #7a5200`, `900 #4d3300`                                              | `systemYellow-50`, `-100`, `-200`, `-500`, `-600`, `-700` y un ámbar legacy             |
| `error`        | `50 #ffebea`, `100 #ffd9d7`, `300 #ffaaa6`, `500 #ff3b30`, `600 #d70015`, `700 #a50e10`, `900 #5a0608`                                              | `systemRed-50`, `-100`, `-200`, `-500`, `-600`, `-700` y un rojo legacy                 |

Fuente: `resources/js/design-system/tokens.js` (`colors.systemBlue`,
`colors.cream`, `colors.terracotta`, `colors.clinicalTeal`, `colors.info`,
`colors.primary`, `colors.neutral`, `colors.success`, `colors.warning`,
`colors.error`) y su emisión en `resources/css/tokens.generated.css`. Nótese que
`systemBlue`/`terracotta`/`clinicalTeal`/`primary` ya **no son azul ni
terracota**: apuntan al verde de acento.

### 1.6 Vidrio de chrome (`surface-glass`)

El vidrio es **solo para el chrome** (sidebar y topbar de `AppLayout`, y el
wrapper del menú móvil). Las cards de datos son opacas: `Card.vue` conserva el
nombre `glass` por compatibilidad, pero no difumina.

| Token              | Valor                                      | Uso                        |
| ------------------ | ------------------------------------------ | -------------------------- |
| `--glass-bg`       | `rgba(255, 255, 255, 0.78)`                | Fondo del chrome           |
| `--glass-border`   | `rgba(255, 255, 255, 0.22)`                | Borde interior del chrome  |
| `--glass-backdrop` | `blur(20px) saturate(180%) contrast(1.04)` | Filtro de fondo del chrome |

La clase `.surface-glass` de `resources/css/tokens.generated.css` compone además
un gradiente de `rgb(255 255 255 / 0.78)` a `rgb(255 255 255 / 0.62)` (sintaxis
moderna de `rgb`), borde derecho `rgb(0 0 0 / 0.06)`, tres sombras
internas/externas y un `::after` con `border: 1px solid rgb(255 255 255 / 0.18)`
en `mix-blend-mode: overlay`. Bajo `prefers-reduced-transparency: reduce`
colapsa a `#ffffff` sin blur ni sombra; bajo `prefers-contrast: more`
`--color-border` pasa a `secondaryLabel` y `--color-border-strong` a `label`
(`--color-text-primary` se reafirma en `label`).

---

## 2. Tipografía

**Una sola familia, sin serif y sin fuentes remotas.** `tokens.js` declara la
pila del sistema y eliminó la superficie serif; no hay `@font-face` ni import de
Google Fonts.

```css
font-family:
  -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue',
  Arial, sans-serif;
```

Fuente: `resources/js/design-system/tokens.js` (`typography.fontFamily.sans`) y
`resources/css/tokens.generated.css` (`--font-sans`). No se importan fuentes
externas (lo prohíbe §3 de `docs/ux-guidelines.md`).

### 2.1 Escala de tamaño

Diez pasos. El `line-height` y el `letter-spacing` viajan con el paso; las
utilidades `text-*` los aplican juntos y no se sobreescriben con
`leading-*`/`tracking-*` salvo justificación.

| Token          | Tamaño | Line-height | Letter-spacing | Uso                                            |
| -------------- | ------ | ----------- | -------------- | ---------------------------------------------- |
| `text-xs`      | `12px` | `16px`      | `0`            | Labels de tabla, captions, atajos, metadatos   |
| `text-sm`      | `13px` | `18px`      | `0`            | Body secundario, celdas, ayuda de campo        |
| `text-base`    | `15px` | `22px`      | `0`            | Body principal, párrafos, labels de formulario |
| `text-lg`      | `17px` | `24px`      | `0`            | Subtítulos y título de card                    |
| `text-xl`      | `20px` | `28px`      | `-0.01em`      | `h3`, título de diálogo, cabecera de sección   |
| `text-2xl`     | `24px` | `32px`      | `-0.015em`     | `h2`, cifra de KPI, título de modal grande     |
| `text-3xl`     | `30px` | `36px`      | `-0.02em`      | Cifra grande de KPI (`/reception-procedures`)  |
| `text-4xl`     | `36px` | `40px`      | `-0.022em`     | Título visible de página en el hero            |
| `text-display` | `48px` | `48px`      | `-0.022em`     | Definido; **sin consumidor medido** en `js`    |
| `text-hero`    | `64px` | `64px`      | `-0.022em`     | Definido; **sin consumidor medido** en `js`    |

Fuente: `resources/js/design-system/tokens.js` (`typography.fontSize`),
consumido en `tailwind.config.js` (`fontSize`) y emitido por paso en
`resources/css/tokens.generated.css` (`--font-sans`; los tamaños viven en
Tailwind). `docs/ux-guidelines.md` §3 documenta el mismo cuadro y prohíbe
sobreescribir el line-height.

### 2.2 Cifras tabulares

`fontFeatures.tabularNums` = `'"tnum" 1, "lnum" 1'`, emitido como
`--font-features-tabular-nums` en `resources/css/tokens.generated.css`. Se usa
en columnas de dinero, fechas, documentos y cualquier cifra que se compare
verticalmente; la utilidad Tailwind es `tabular-nums`. El valor literal de la
utilidad **no** es un `font-feature-settings` válido: el token es la
declaración.

### 2.3 La excepción `font-mono`

`tokens.js` no declara familia monoespaciada, así que `font-mono` resuelve por
la **escala por omisión de Tailwind**:
`ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace`.
Quince archivos la usan para códigos y no para texto de lectura (código CIE-10,
código de procedimiento, código de método de pago). No es una segunda familia
del sistema: es el defecto de Tailwind que nadie sobreescribió, y no se usa en
prosa, títulos, cifras ni dinero.

Fuente: `resources/js/design-system/tokens.js` (no existe `fontFamily.mono`),
`tailwind.config.js` (`fontFamily` sólo extiende `sans`) y su uso en
`resources/js/components/procedures/*.vue` y
`resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue`.

---

## 3. Espaciado

Rejilla de 4 px. Trece pasos; son los que `tailwind.config.js` publica como
`spacing` (con los alias canónicos 0–24 conservados).

| Token | Valor   | Uso típico                                     |
| ----- | ------- | ---------------------------------------------- |
| `0`   | `0px`   | Reset                                          |
| `1`   | `4px`   | Separación mínima icono–texto, margen de ayuda |
| `2`   | `8px`   | Gap de chips, separación interna de badges     |
| `3`   | `12px`  | Gap de botones, padding compacto               |
| `4`   | `16px`  | Gap de grid de filtros, padding de card `sm`   |
| `5`   | `20px`  | Separación de bloques cortos                   |
| `6`   | `24px`  | Padding de card `md`, gap de secciones         |
| `8`   | `32px`  | Separación entre bloques de página             |
| `12`  | `48px`  | Padding de card `xl`, margen de hero           |
| `16`  | `64px`  | Separación de secciones mayores                |
| `24`  | `96px`  | Aire de portada                                |
| `32`  | `128px` | Aire de portada grande                         |
| `48`  | `192px` | Separación de máxima jerarquía                 |

Fuente: `resources/js/design-system/tokens.js` (`spacing`) y
`resources/css/tokens.generated.css` (`--spacing-0` … `--spacing-48`).

---

## 4. Radios

La escala escala con el tamaño del contenedor: control interno → card → modal →
panel → shell.

| Token     | Valor    | Intención                                          |
| --------- | -------- | -------------------------------------------------- |
| `none`    | `0`      | Superficie a sangre                                |
| `sm`      | `4px`    | Chips pequeños, muestras de color                  |
| `md`      | `8px`    | Inputs y botones `xs` (inset leve)                 |
| `ios`     | `10px`   | Cards, botones, chips de estado (estándar iOS)     |
| `modal`   | `14px`   | Modal, Sheet y bottom pickers                      |
| `cardLg`  | `16px`   | Superficie externa de card (KPI, hero)             |
| `control` | `12px`   | Elemento interactivo interno (input, botón)        |
| `panel`   | `22px`   | Superficie anidada (panel)                         |
| `shell`   | `32px`   | Contenedor externo (shell)                         |
| `full`    | `9999px` | Píldoras, avatares, badges de estado, icon-buttons |

Fuente: `resources/js/design-system/tokens.js` (`radius`), emitido como
`--radius-*` en `resources/css/tokens.generated.css` y documentado con la misma
intención en `docs/ux-guidelines.md` §5. Nótese que `cardLg` se emite en kebab
(`--radius-card-lg`) y que `lg`/`2xl`/`3xl` **no existen** (se retiraron por
decisión).

---

## 5. Sombras y elevación

Hay dos rampas y no son intercambiables: la de `shadow` (negro puro, utilidades
`shadow-*`) y la de `elevation` (gris cálido, `--elevation-*`, para
superficies).

### 5.1 Rampa `shadow`

| Token      | Valor                                                                       | Uso medido en las primitivas                                                           |
| ---------- | --------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `subtle`   | `0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)`           | `Button` secondary; `Card` flat en hover; toque de `AppLayout` y del badge dismissible |
| `soft`     | `0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)`     | `Card` default en reposo; base de `Button` danger/success/warning                      |
| `medium`   | `0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)`   | `Card` default en hover y `Card` glass; hover de danger/success/warning                |
| `large`    | `0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)` | `FloatingActionButton`; hover de `Card` glass y de `Toast`                             |
| `elevated` | `0 25px 50px -12px rgba(0, 0, 0, 0.25)`                                     | Hover de `Card` elevated                                                               |
| `glass`    | `0 8px 32px 0 rgba(0, 0, 0, 0.18)`                                          | Sin consumidor en `resources/js`; el chrome usa su sombra compuesta propia             |

`tailwind.config.js` añade además `glass-inset`
(`inset 0 1px 0 0 rgba(255, 255, 255, 0.05)`), que no está en `tokens.js`. Ojo
con la escala por omisión de Tailwind: `Modal` usa `shadow-2xl` y
`Sheet`/`Select`/`Toast` usan `shadow-lg`; esos nombres no son de esta rampa (el
`2xl` coincide numéricamente con `elevated`).

### 5.2 Rampa `elevation`

Cinco peldaños con el matiz cálido de la familia de labels
(`rgba(92, 90, 85, α)`); los peldaños 2–4 llevan dos capas porque una superficie
más grande debe leerse más gruesa.

| Token         | Valor                                                                  |
| ------------- | ---------------------------------------------------------------------- |
| `elevation-0` | `none`                                                                 |
| `elevation-1` | `0 1px 3px rgba(92, 90, 85, 0.04)`                                     |
| `elevation-2` | `0 2px 8px rgba(92, 90, 85, 0.06), 0 1px 2px rgba(92, 90, 85, 0.04)`   |
| `elevation-3` | `0 8px 16px rgba(92, 90, 85, 0.08), 0 2px 6px rgba(92, 90, 85, 0.06)`  |
| `elevation-4` | `0 16px 24px rgba(92, 90, 85, 0.12), 0 4px 8px rgba(92, 90, 85, 0.08)` |

Fuente: `resources/js/design-system/tokens.js` (`shadow`, `elevation`) y
`resources/css/tokens.generated.css` (`--shadow-*`, `--elevation-*`).

### 5.3 Anillo de foco

| Token                  | Valor                              | Uso                                                          |
| ---------------------- | ---------------------------------- | ------------------------------------------------------------ |
| `focusRing.width`      | `3px`                              | Grosor del anillo                                            |
| `focusRing.color`      | `#0f7a5f`                          | Sigue a `accent-500`; un test de contrato afirma la relación |
| `focusRing.alpha`      | `0.2`                              | Opacidad del halo                                            |
| `focusRing.offset`     | `2px`                              | Separación del borde                                         |
| `--focus-ring-default` | `0 0 0 3px rgba(15, 122, 95, 0.2)` | Composición final emitida                                    |

Regla: el anillo se declara en `:focus-visible`, nunca en `:focus`, para que un
clic de puntero no lo pinte. `app.css` conserva además un
`box-shadow: 0 0 0 2px var(--color-accent)` en `:focus` como base, y
`utilities.css` expone `.focus-ring` con `outline` de 2 px. Las tres
construcciones existen; la canónica de las primitivas nuevas es
`--focus-ring-default`.

Fuente: `resources/js/design-system/tokens.js` (`focusRing`),
`resources/css/tokens.generated.css` y `resources/css/app.css`.

---

## 6. Movimiento

Once tokens: cuatro parámetros de muelle, tres duraciones y cuatro curvas.

| Token                     | Valor                                  | Uso                                                                      |
| ------------------------- | -------------------------------------- | ------------------------------------------------------------------------ |
| `motion.response`         | `0.35` s                               | Tiempo al objetivo del muelle                                            |
| `motion.damping`          | `1.0`                                  | Críticamente amortiguado (sin rebote)                                    |
| `motion.dampingBounce`    | `0.8`                                  | **Deliberadamente sin consumidor**; ningún gesto del slice es de momento |
| `motion.stiffness`        | `1.0`                                  | Rigidez del muelle                                                       |
| `motion.duration.fast`    | `120ms`                                | Transform en hover/press, foco                                           |
| `motion.duration.normal`  | `200ms`                                | Lavados de color, borde y sombra                                         |
| `motion.duration.slow`    | `320ms`                                | Entradas de superficie                                                   |
| `motion.easings.standard` | `cubic-bezier(0.4, 0.0, 0.2, 1)`       | Curva estándar                                                           |
| `motion.easings.decel`    | `cubic-bezier(0.0, 0.0, 0.2, 1)`       | Entrada (decelera)                                                       |
| `motion.easings.accel`    | `cubic-bezier(0.4, 0.0, 1, 1)`         | Salida (acelera)                                                         |
| `motion.easings.ios`      | `cubic-bezier(0.25, 0.46, 0.45, 0.94)` | Curva iOS, la de los transforms                                          |

Reglas que ya aplica el código:

- Solo se animan `transform` y `opacity`; `width`, `height`, `top`, `left`,
  `margin` y `padding` no (`docs/ux-guidelines.md` §6, regla de oro).
- `tailwind.config.js` expone `duration-fast|normal|slow` desde
  `motion.duration` y añade las curvas `bounce-soft`
  (`cubic-bezier(0.68, -0.55, 0.265, 1.55)`) y `ease-ios`, más 11 keyframes
  (`fadeIn`, `fadeOut`, `slideUp`, `slideDown`, `slideLeft`, `slideRight`,
  `scaleIn`, `scaleOut`, `bounceSubtle`, `pulseSubtle`, `ripple`).
- `resources/css/tokens.generated.css` conserva tres duraciones heredadas
  (`--transition-fast: 150ms`, `--transition-normal: 200ms`,
  `--transition-slow: 300ms`) que **no** son `motion.duration`; conviven con la
  rampa canónica.
- `prefers-reduced-motion: reduce` apaga `pulse-subtle`, `ripple`, `skeleton` y
  `spinner-ring` y anula las transiciones de `.smooth-transition`, `.hover-lift`
  y `.focus-ring` (`resources/css/utilities.css`). Las primitivas bajan el
  movimiento a un cambio de opacidad y **no** ponen la duración a cero.

Fuente: `resources/js/design-system/tokens.js` (`motion`), `tailwind.config.js`,
`resources/css/tokens.generated.css` y `resources/css/utilities.css`.

### 6.1 Otros tokens del mismo archivo

| Grupo                              | Valores                                                                                                          | Uso                                                                                                         |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `breakpoint`                       | `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px, `2xl` 1536px                                                   | Cortes responsive; `app.css` los usa a mano (640/1024/1280) en `.container-responsive` y `.grid-responsive` |
| `zIndex` (en `tailwind.config.js`) | `dropdown` 1000, `sticky` 1020, `fixed` 1030, `modalBackdrop` 1040, `modal` 1050, `popover` 1060, `tooltip` 1070 | Capas; los overlays reales se apilan con `useOverlayStack`, no con esta tabla                               |
| `topbar`                           | `iconSize` 20px, `iconWeight` 1.5, `control` 20px, `controlLg` 32px                                              | Peso óptico único de los tres controles del topbar (icono, campana y avatar)                                |

Fuente: `resources/js/design-system/tokens.js` (`breakpoint`, `topbar`),
`tailwind.config.js` (`zIndex`) y `resources/css/utilities.css` (`.z-dropdown` …
`.z-tooltip`, que repiten los mismos números).

---

## 7. Reglas de componentes

Contrato visual de las primitivas de `resources/js/components/ui/`. Todas las
superficies de dominio deben consumirlas: la auditoría UI mide los ejes
`buttons`, `modals`, `badges`, `states` y `filters` y marca `DIV` cuando una
página pinta su propia versión.

### 7.1 `Button.vue`

- **Para qué:** cualquier acción del usuario. Es la única fuente de botones.
- **Variantes (7):** `primary` (fondo `accent`, texto `#ffffff`, borde
  transparente, `elevation-3` + `inset 0 1px 0 rgba(255,255,255,0.32)` +
  `0 0 0 1px rgba(0,0,0,0.04)`); `secondary` (fondo transparente, borde
  `--color-border`, `shadow-subtle`); `ghost` (transparente, texto secundario);
  `danger` (`error-500` → `600` → `700`); `success` (`success-500` → `600` →
  `700`); `warning` (`warning-500` → `600` → `700`); `icon` (ghost circular).
- **Tamaños (5):** `xs` 28 px de alto con `px-2 py-1` (8/4) y `rounded-md` (8
  px); `sm` 36 px con `px-3 py-1.5` (12/6), `md` 44 px con `px-4 py-2` (16/8),
  `lg` 52 px con `px-6 py-3` (24/12) y `xl` 60 px con `px-8 py-4` (32/16), todos
  con `rounded-ios` (10 px). `icon` es cuadrado (`aspect-ratio: 1`) y su lista
  de tamaño fija el ancho igual a la altura (`p-1` a `p-4`).
- **Contrato:** `type` por defecto `button` (nunca envía un formulario por
  accidente); `font-medium`; hover `translateY(-1px)`; `primary` en `:active`
  baja 1 px como respuesta al puntero; `:focus-visible` pinta
  `--focus-ring-default`; `disabled` baja a `opacity 0.6`; el ripple blanco de
  600 ms se apaga bajo `prefers-reduced-motion`.
- **Source:** `resources/js/components/ui/Button.vue`.

### 7.2 `Input.vue`

- **Para qué:** campo de texto, email, password, número, teléfono, fecha, hora,
  búsqueda y textarea (`type="textarea"`).
- **Variantes (3):** `default` (fondo `surface-elevated`, borde `theme`),
  `filled` (fondo `surface`), `outlined` (transparente, borde de 2 px).
- **Tamaños (3):** `sm` 36 px / `rounded-md` / `px-3 py-2`; `md` 44 px /
  `rounded-ios` / `px-4 py-3`; `lg` 52 px / `rounded-ios` / `px-5 py-4`. Borde
  de 1 px `--color-border`; sin sombra en reposo (sólo el anillo de foco).
- **Contrato:** label de 13 px `font-medium` en `text-theme-primary` (o flotante
  con `scale-75` + `accent` al enfocar); ayuda y error a 12 px,
  `margin-top: 4px`, `min-height: 20px`; error pinta borde y anillo
  `rgba(255, 59, 48, 0.2)` compuesto con las partes del `focusRing`; el foco
  pinta `--focus-ring-default`; `aria-invalid` y `aria-describedby` siempre
  presentes; botón «Limpiar campo» opcional.
- **Source:** `resources/js/components/ui/Input.vue`.

### 7.3 `Select.vue`

- **Para qué:** desplegable de opciones, con búsqueda, selección múltiple y
  grupos.
- **Variantes (3):** las mismas `default`/`filled`/`outlined` del `Input`.
- **Tamaños (3):** `sm` 36 px / `px-3 py-2` / `rounded-md`; `md` 44 px /
  `px-4 py-3` / `rounded-ios`; `lg` 52 px / `px-5 py-4` / `rounded-ios`.
- **Contrato:** disparador `text-left`, borde `theme`, `rounded-ios` desde `md`;
  `aria-haspopup="true"`, `aria-expanded`, `aria-invalid`; panel
  `absolute z-dropdown mt-1 w-full`, fondo `surface-elevated`, borde `theme`,
  `rounded-ios`, `shadow-lg`; opción seleccionada con fondo `primary-50`; el
  placeholder se trunca en una línea (incluida la opción «Todos» con valor
  vacío).
- **Source:** `resources/js/components/ui/Select.vue`.

### 7.4 `Card.vue`

- **Para qué:** contenedor visual de datos y bloques de página.
- **Variantes (5):** `default` (blanco + `shadow-soft`, hover `medium`); `glass`
  (**opaca**: `systemBackground` + borde `separator` + `shadow-medium`, sin
  blur); `flat` (fondo `surface`, sin sombra); `elevated` (`--elevation-2` +
  hairline de 1 px + `inset 0 1px 0 rgba(255,255,255,0.5)`); `outlined` (borde
  de 2 px).
- **Padding (5):** `none` 0, `sm` 16, `md` 24, `lg` 32, `xl` 48 px.
- **Contrato:** radio `ios` (10 px) siempre; header y footer con borde de 1 px y
  padding de 24 px; `clickable` agrega `cursor-pointer`, `:active` escala a 0.98
  y `:focus-visible` usa el anillo; `hover` eleva 1 px. Bajo
  `prefers-reduced-motion` el lift desaparece y la respuesta pasa a opacidad.
- **Source:** `resources/js/components/ui/Card.vue`.

### 7.5 `Badge.vue`

- **Para qué:** etiqueta corta con color semántico (estado, contador,
  categoría).
- **Variantes (7):** `default`, `primary`, `success`, `warning`, `error`,
  `info`, `neutral`. Patrón iOS relleno: fondo del paso `100`, texto del `700` y
  borde del `200`.
- **`info` no usa acero**: la variante resuelve `systemBlue`, que es el alias
  retirado del acento, así que se lee `#c9e7dc` / `#084c3b` / `#93cdb9`. El tono
  acero (`systemSteel`) lo usan `StatusBadge` y el Kanban de planes; el `Toast`
  informativo también resuelve `systemBlue`. Usar un tono distinto por primitiva
  es lo que evita que `info` se confunda con `success`.
- **Tamaños (3):** `sm` 20 px con `px-2 py-0.5` (8/2), `md` 24 px con
  `px-2.5 py-1` (10/4), `lg` 28 px con `px-3 py-1.5` (12/6).
- **Formas (3):** `rounded` (`md` 8 px), `pill` (`full`), `square` (`sm` 4 px).
- **Contrato:** `inline-flex`, `gap-1.5`, `font-medium`; opcional `dot` (8 px,
  `w-2 h-2 p-0`), `dismissible` con botón de 12 px y anillo de foco. El lift de
  hover no se aplica (ver §10).
- **Source:** `resources/js/components/ui/Badge.vue`.

### 7.6 `StatusBadge.vue`

- **Para qué:** estado de una entidad, legible por lector de pantalla.
- **Variantes (5):** `success` (`systemGreen-50` / `systemGreen-700`), `warning`
  (`systemYellow-50` / `systemYellow-700`), `error` (`systemRed-50` /
  `systemRed-700`), `info` (`systemSteel-50` / `systemSteel-700`), `neutral`
  (`systemGray-100` / `systemGray-700`).
- **Tamaños (2):** `sm` 20 px con `px-2 py-0.5`, `md` 24 px con `px-2.5 py-1`;
  borde transparente y sin sombra.
- **Contrato:** siempre `rounded-full`, `font-medium`, `role="status"` y
  `aria-label="Estado: <label>"`; `showDot` pinta un punto de 6 px con el paso
  `500` de la misma rampa; sin transform (es decorativo) y transición de 200 ms.
- **Source:** `resources/js/components/ui/StatusBadge.vue`.

### 7.7 `Modal.vue`

- **Para qué:** diálogo modal. Es la base de `ConfirmDialog` y de todos los
  formularios de página.
- **Tamaños (8):** `sm` `max-w-sm`, `md` `max-w-md`, `lg` `max-w-lg`, `xl`
  `max-w-xl`, `2xl` `max-w-2xl`, `3xl` `max-w-3xl`, `4xl` `max-w-4xl`, `full`.
- **Variantes (4):** `default`, `centered`, `top` (`pt-16`), `bottom` (`pb-16`).
- **Contrato:** backdrop `bg-black/50 backdrop-blur-sm` (literal
  `rgba(0, 0, 0, 0.5)` + `blur(4px)`) teleportado a `<body>` con
  `data-overlay-backdrop="modal"`; panel `rounded-modal` (14 px), fondo
  `surface-elevated`, `shadow-2xl` (el `2xl` por omisión de Tailwind, cuyo valor
  coincide con `--shadow-elevated`: `0 25px 50px -12px rgba(0, 0, 0, 0.25)`),
  `max-h-screen`; header, cuerpo y footer con padding de 24 px (`p-6`) y borde
  `--color-border` arriba y abajo; el panel no declara borde propio; entrada de
  300 ms (scale 0.95 + translateY 16 px) y salida de 200 ms; `role="dialog"` +
  `aria-modal` + `aria-labelledby` (o `aria-label` si el consumidor reemplaza el
  header); trampa de foco y Escape/backdrop solo en el overlay superior de la
  pila; `beforeClose` puede vetar el cierre; bloqueo de scroll contado por
  superficie.
- **Source:** `resources/js/components/ui/Modal.vue`.

### 7.8 `Sheet.vue`

- **Para qué:** panel lateral o inferior deslizante (menú móvil, detalle de
  análisis, formularios largos).
- **Posiciones (4):** `top` (`rounded-b-modal`), `bottom` (`rounded-t-modal`),
  `left` (`rounded-r-modal`), `right` (`rounded-l-modal`).
- **Tamaños (3):** `sm`, `md`, `lg` (`max-w-*` en laterales, `max-h-*` en
  verticales).
- **Contrato:** fondo `surface-elevated`, `shadow-lg`, radio `modal` (14 px)
  solo en el lado que toca el borde; handle de 48 × 4 px `w-12 h-1` centrado;
  header y footer de 24 px con borde, cuerpo con scroll propio; misma pila de
  overlays, trampa de foco, Escape y `labelledby` que `Modal`.
- **Source:** `resources/js/components/ui/Sheet.vue`.

### 7.9 `DataTable.vue`

- **Para qué:** listas tabulares con orden, búsqueda, selección, paginación y
  acciones por fila. Obligatoria en listas de más de 10 elementos.
- **Contrato:** contenedor
  `bg-theme-surface-elevated rounded-ios border border-theme overflow-hidden`;
  header con `px-6 py-4` y borde inferior; `<th>` de 12 px `font-medium`
  `uppercase` `tracking-wider` en `text-theme-secondary`, `px-6 py-3`; celdas de
  13 px `px-6 py-4`; filas separadas con `divide-y divide-theme`; estado de
  carga con spinner `accent` y texto; vacío delegado a `EmptyState`; pie de
  paginación con «Mostrando X-Y de Z elementos». Sin sombra propia: el borde de
  1 px es toda su elevación.
- **Affordance de scroll:** el viewport interno (`data-table-scroll-viewport`)
  mide su overflow, publica `data-table-scroll` (`start|middle|end|none`), pinta
  un degradado de 40 px en el borde oculto y, mientras quede contenido a la
  derecha, muestra «Desliza para ver más columnas»
  (`data-table-scroll-affordance`). El scroll siempre es interno: ninguna página
  gana overflow de documento.
- **Source:** `resources/js/components/ui/DataTable.vue`.

### 7.10 `FilterBar.vue`

- **Para qué:** filtros siempre visibles, en grid, bajo el hero y sobre la
  lista.
- **Columnas (5):** 2, 3, 4, 5 o 6 (`grid-cols-1` en móvil, hasta
  `lg:grid-cols-6`).
- **Contrato:** `role="search"` con `aria-label` («Filtros» o «Filtros:
  <title>»); caja `p-4 rounded-xl border` (`rounded-xl` = 12 px, el mismo valor
  que `--radius-control`), fondo `--color-background-secondary` (`#f7f6f4`) y
  borde `--color-border-light` (`#edebe7`); gap de 16 px; título opcional de
  13 px `font-medium`; sin sombra. El único verbo de limpieza es **«Limpiar
  filtros»**.
- **Source:** `resources/js/components/ui/FilterBar.vue` y
  `docs/ux-guidelines.md` §9.

### 7.11 `Tabs.vue`

- **Para qué:** navegación entre vistas de una misma entidad.
- **Variantes (4):** `default`, `pills`, `underline`, `cards`.
- **Tamaños (3):** `sm` `px-3 py-1.5 text-xs`, `md` `px-4 py-2 text-sm`, `lg`
  `px-6 py-3 text-base`.
- **Contrato:** `role="tablist"` con `aria-label`; cada tab `role="tab"` con
  `aria-selected`, `aria-controls` y `tabindex` roving; panel `role="tabpanel"`
  con `aria-labelledby`; indicador de 2 px (`h-0.5`) en `bg-systemBlue-500` (que
  resuelve a `#0f7a5f`); navegación con flechas, Home y End; `pills` y `cards`
  usan fondo `surface` y radio `ios`.
- **Source:** `resources/js/components/ui/Tabs.vue`.

### 7.12 `Pagination.vue`

- **Para qué:** paginación numerada de listas de servidor. No se reinventa.
- **Contrato:** `role="navigation"` con `aria-label="Paginación"`; leyenda
  «Mostrando X a Y de Z resultados»; botones de página con `aria-current="page"`
  en la activa; activa `bg-accent text-white` con `outline` de foco; navegación
  anterior/siguiente con `aria-label`; en móvil se reduce a
  «Anterior»/«Siguiente». Contenedor `px-4 py-3 sm:px-6` con borde superior de 1
  px sobre `surface-elevated`; cada página lleva `ring-1 ring-inset` de
  `--color-border` y sólo la activa rellena con acento.
- **Source:** `resources/js/components/ui/Pagination.vue`.

### 7.13 `EmptyState.vue` y `UiSectionEmpty.vue`

- **Dos trabajos distintos.** `EmptyState` es el vacío de una **página** cuyo
  contenido es la lista: icono de 64 px, título `text-lg` semibold, descripción
  `text-sm` secundaria (`max-w-md`) y la acción que arranca el contenido.
  `UiSectionEmpty` es el vacío de una **sección** que ya publica su CTA en el
  héroe: caja compacta `rounded-ios p-6` con fondo canvas y borde hairline,
  título de 13 px y una frase de 12 px; acción propia solo si el destino no
  tiene otro CTA en pantalla.
- **Tamaños y variantes:** `EmptyState` `sm` `py-8 px-4`, `md` `py-12 px-6`,
  `lg` `py-16 px-8`, `xl` `py-20 px-10`; variante `default` (blanco + borde
  `theme` + `rounded-ios`), `minimal` (transparente, sin borde) y `centered`
  (fondo `surface`). `UiSectionEmpty` no tiene tamaños ni variantes:
  `rounded-ios p-6`, fondo canvas y borde hairline fijos.
- **Source:** `resources/js/components/ui/EmptyState.vue`,
  `resources/js/components/ui/UiSectionEmpty.vue` y `docs/ux-guidelines.md` §9
  («Estados vacíos»).

### 7.14 `Skeleton.vue`

- **Para qué:** espera del primer frame y de recargas; nunca texto «Cargando…»
  suelto.
- **Variantes (6):** `text`, `rectangular`, `circular`, `card`, `table`, `list`.
- **Contrato:** ítems `bg-systemGray-100` + `rounded-ios`; animación `pulse` o
  `wave` (`animation: none` bajo reduced motion); `aria-label` = «Cargando
  contenido...» y **sin `role` propio**: la página que necesita anunciarlo lo
  envuelve en `role="status"`. `width`/`height` se respetan (la variante
  rectangular no colapsa a ancho cero dentro de un flex).
- **Source:** `resources/js/components/ui/Skeleton.vue` y
  `resources/css/utilities.css` (`.skeleton`).

### 7.15 `Toast.vue`

- **Para qué:** aviso efímero de una acción que no tiene otra superficie de
  error.
- **Tipos (4):** `success` (`systemGreen-50/200/700`), `error`
  (`systemRed-50/200/700`), `warning` (`systemYellow-50/200/700`), `info`
  (`systemBlue` → acento `#f2f8f5/#93cdb9/#084c3b`).
- **Contrato:** `role="status"` con `aria-live="polite"` para éxito e info, y
  `role="alert"` con `aria-live="assertive"` para error y advertencia;
  `aria-atomic="true"`; `rounded-ios`, `shadow-lg` (Tailwind) en reposo y
  `var(--shadow-large)` en hover, `p-4`, `gap-3`, `max-w-sm`; entrada de 300 ms
  (translateY 8 px + scale 0.95) y salida de 200 ms; autodismiss de 5000 ms
  salvo `persistent`; botón «Cerrar notificación». El `info` del toast resuelve
  el alias `systemBlue`, es decir el acento (`#f2f8f5` / `#93cdb9` / `#084c3b`),
  no el acero.
- **Source:** `resources/js/components/ui/Toast.vue`.

### 7.16 `ConfirmDialog.vue` y `LoadingSpinner.vue`

- **`ConfirmDialog`:** envuelve `Modal` en tamaño `sm` con `role="alertdialog"`;
  variantes `default` y `danger`; cuerpo con icono de 40 px (`danger-light` de
  fondo, `danger-dark` de color) y mensaje `text-base`; pie con «Cancelar»
  (secondary) y el envío en `primary`/`danger`; `loading` bloquea backdrop,
  Escape y ambos botones. Reemplaza a `window.confirm()`.
- **`LoadingSpinner`:** tres anillos concéntricos, tamaños `xs` 16 px a `xl` 64
  px, variantes `primary`/`secondary`/`white`/`gray`; `aria-label` =
  «Cargando...»; animación de 0.8 s lineal definida en
  `resources/css/utilities.css` (`.spinner-ring`), que toma su color del token
  `--spinner-color` (= `--color-accent`), y se apaga por reduced motion.
- **Source:** `resources/js/components/ui/ConfirmDialog.vue`,
  `resources/js/components/ui/LoadingSpinner.vue`.

---

## 8. Receta de página

Toda superficie de dominio del rediseño sigue el mismo orden. Es la anatomía
medida por la auditoría UI, no una sugerencia.

1. **Hero band.** `<header>` con fondo `--color-canvas`, borde de 1 px
   `--color-hairline`, `border-radius: var(--radius-panel)` (22 px) y
   `box-shadow: var(--elevation-2)`. Dentro: lockup de marca
   (`DentalMotifLayer variant="hero"`), fila superior con el CTA, eyebrow de
   contexto y el título visible de página a `text-4xl` (`36px/40px`,
   `-0.022em`). En las superficies listadas en `pageTitledRoutes` ese título es
   un `<p>`: el único `<h1>` del documento lo pinta `AppLayout` como `sr-only`,
   para no duplicar encabezados. Fuente:
   `resources/js/modules/professionals/ProfessionalsPage.vue` (bloque
   `data-professionals-hero`) y `resources/js/components/layout/AppLayout.vue`.
2. **KPI strip.** Cuatro `Card` `glass` con `padding="sm"`, `--elevation-2` y
   borde hairline, en grid de 1/2/4 columnas. Cada una: label de 12 px
   uppercase, cifra `text-2xl font-bold tabular-nums` y una caption de alcance.
   Sin chips de comparación ni huecos reservados; con error de carga las cifras
   se leen `N/D`.
3. **Filtros.** `UiFilterBar` de 4 columnas con búsqueda, select y el botón
   secundario **«Limpiar filtros»** (solo visible con filtros activos).
4. **Lista.** `UiDataTable` con su affordance de scroll y las acciones por fila
   gobernadas por permiso; paginación de servidor cuando el endpoint pagina.
5. **Estados.** En este orden y nunca mezclados: `Skeleton` en el primer frame,
   bloque de error con «Reintentar» (y cifras `N/D`), vacío (`UiSectionEmpty` si
   el héroe ya ofrece el CTA, `EmptyState` si la página es la lista), contenido.
6. **Diálogos.** `UiModal` para alta/edición/detalle y `UiSheet` para flujos
   laterales; `ConfirmDialog` para toda confirmación destructiva. La pila de
   overlays decide qué Escape cierra.

Fuente de la receta: `resources/js/modules/professionals/ProfessionalsPage.vue`,
`resources/js/modules/dashboard/DashboardPage.vue`,
`resources/js/components/ui/{FilterBar,DataTable,Modal,Sheet}.vue` y
`docs/ux-guidelines.md` §9.

---

## 9. Vocabulario y anti-patrones

### 9.1 Vocabulario de acciones (`docs/ux-guidelines.md` §9)

1. El disparador de un alta es **«Nuevo/Nueva <sustantivo>»**: «Nueva cita»,
   «Nuevo cargo», «Nuevo procedimiento», «Nueva historia». Nunca «Crear»,
   «Agregar», «Registrar» ni «Alta» a secas.
2. El envío de un formulario lleva **verbo con objeto**: «Guardar cambios»,
   «Guardar evolución», «Confirmar cita», «Confirmar cobro». Nunca «Aceptar»,
   «Confirmar» u «OK» a secas.
3. **«Cancelar», «Volver» y «Cerrar»** son para abortar, salir y cerrar; no son
   envíos ni altas.
4. El título del modal se alinea al sustantivo del disparador («Nuevo cargo»), y
   el envío no se disfraza de alta («Guardar cargo»).

### 9.2 Anti-patrones ya documentados

| Anti-patrón                                                                       | Por qué está mal                                         | Alternativa                                                                |
| --------------------------------------------------------------------------------- | -------------------------------------------------------- | -------------------------------------------------------------------------- |
| Botón, modal o badge ad-hoc                                                       | Rompe la consistencia y la auditoría lo mide como `DIV`  | `Button`, `Modal`, `Badge`, `StatusBadge`                                  |
| Colores Tailwind crudos (`bg-blue-*`, `text-purple-600`)                          | Rompe la paleta y evade los tokens                       | `bg-accent`, `text-theme-*`, `border-theme`                                |
| `window.confirm()`                                                                | No es accesible ni personalizable                        | `ConfirmDialog`                                                            |
| Spinner inline con clases crudas                                                  | Color fuera de paleta y animación sin GPU                | `LoadingSpinner`                                                           |
| `outline: none` sin reemplazo                                                     | Quita el foco visible de teclado                         | `focus-visible` con `--focus-ring-default`                                 |
| Animar `width`/`height`/`top`/`left`                                              | Provoca reflow y se ve entrecortado                      | Animar `transform` y `opacity`                                             |
| `Intl.NumberFormat`/`Intl.DateTimeFormat`/`toLocale*` fuera de `useFormatters.js` | Rompe locale y reloj únicos (`es-PE`, 24 h)              | `formatCurrency`, `formatDate`, `formatDateTime`, `formatTime`, `dateOnly` |
| `S/ {{ valor }}` delante del helper                                               | Duplica el prefijo que el helper ya emite                | `{{ formatCurrency(valor) }}`                                              |
| Dos `h1` visibles en una ruta                                                     | Rompe la jerarquía y el lector anuncia dos títulos       | El `h1` pertenece a la página; el topbar publica el suyo como `sr-only`    |
| Importe sin `tabular-nums` en una columna                                         | Las cifras no se alinean y la comparación vertical falla | `tabular-nums` (token `fontFeatures.tabularNums`)                          |
| Vacío ad-hoc o vacío que repite el CTA del héroe                                  | Duplica el destino o deja sin salida                     | `EmptyState` / `UiSectionEmpty` (§7.13)                                    |

Fuente: `docs/ux-guidelines.md` §9 y §10,
`resources/js/composables/useFormatters.js` y `resources/css/utilities.css`.

---

## 10. Hallazgos medidos al capturar

### 10.1 Contraste

El sistema declara pares que no alcanzan WCAG AA cuando el color se usa como
**texto**. Se registran aquí porque la captura los midió; no se inventó ningún
token nuevo para evitarlos, se eligió otro paso de la misma rampa.

| Par                                                             | Medición        | Veredicto                                         |
| --------------------------------------------------------------- | --------------- | ------------------------------------------------- |
| `#ffffff` sobre `error-500` (`#ff3b30`) — `Button` danger       | 3.55:1          | No alcanza AA (4.5:1)                             |
| `#ffffff` sobre `success-500` (`#34c759`) — `Button` success    | 2.22:1          | No alcanza AA                                     |
| `#ffffff` sobre `warning-500` (`#ffcc00`) — `Button` warning    | 1.51:1          | No alcanza AA                                     |
| `#1a1917` sobre `error-500`                                     | 4.95:1          | AA para texto normal                              |
| `#1a1917` sobre `success-500`                                   | 7.91:1          | AA                                                |
| `#1a1917` sobre `warning-500`                                   | 11.62:1         | AA                                                |
| `#ffffff` sobre `error-600` (`#d70015`)                         | 5.38:1          | AA                                                |
| `error-500` (`#ff3b30`) como texto sobre blanco — `Input` error | 3.55:1          | No alcanza AA para 12 px                          |
| `error-700` (`#a50e10`) como texto sobre blanco                 | 7.86:1          | AA                                                |
| `warning-600` (`#a57000`) como texto sobre `warning-50`         | 4.02:1          | No alcanza AA                                     |
| `warning-700` (`#7a5200`) como texto sobre `warning-50`         | 6.52:1          | AA                                                |
| `systemGray-500` (`#908b82`) sobre blanco                       | 3.39:1          | Válido como borde de control (3:1); no como texto |
| `systemGray-600` (`#635e57`) sobre blanco                       | 6.42:1          | AA                                                |
| `text-secondary` (`#5c5a55`) sobre blanco / canvas              | 6.89:1 / 6.38:1 | AA                                                |
| `accent-500` (`#0f7a5f`) sobre blanco / canvas                  | 5.29:1 / 4.90:1 | AA (dato del propio `tokens.js`)                  |

Consecuencia aplicada en `design/system.html`: las etiquetas de los botones
`danger`, `success` y `warning` se pintan con `--color-label-label` (`#1a1917`),
y los mensajes de error con `error-700` (`#a50e10`). El componente en código
conserva `text-white` y `text-error-500`; la diferencia es una corrección de
contraste, no un token nuevo, y queda listada para el día que se toque
`Button.vue`/`Input.vue`.

### 10.2 Toques de CSS sin consumidor

Dos reglas de `Badge.vue` no alcanzan la raíz y por eso no se ven en el
artboard, que las reproduce fielmente al no pintarlas:

- `.badge:hover { transform: translateY(-1px); box-shadow: var(--shadow-subtle); }`
  no coincide: `badgeClasses` publica
  `inline-flex items-center gap-1.5 font-medium select-none` más tamaño, forma y
  variante, y **no** incluye la clase `badge`.
- `[data-variant]:has(.dot)` tampoco coincide, porque `dotClasses` emite
  `w-2 h-2 p-0` y ningún nodo lleva la clase `dot`; el punto de `dot: true` se
  dibuja como un bloque de 8 px sin radio.

Ninguna de las dos cambia el contrato de color ni de tamaño del badge; se
documentan para que el próximo ajuste de la primitiva no las confunda con
comportamiento vivo.

---

## 11. Conteos de la captura

| Grupo                    | Entradas | Detalle                                                                                                                                                           |
| ------------------------ | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Colores (tokens)         | 150      | 10 `accent` + 8 de `systemBlue` + 72 en las nueve rampas de sistema (Red…Steel) + 14 de background/label/separator/border/fill + 46 de alias deprecados restantes |
| Alias semánticos         | 37 + 3   | 37 `--color-*` + `--glass-bg`/`--glass-border`/`--glass-backdrop`                                                                                                 |
| Tipografía               | 10       | `xs` … `hero`                                                                                                                                                     |
| Espaciado                | 13       | `0` … `48`                                                                                                                                                        |
| Radios                   | 10       | `none` … `full`                                                                                                                                                   |
| Sombras                  | 11       | 6 `shadow` + 5 `elevation` (más el anillo de foco)                                                                                                                |
| Movimiento               | 11       | 4 muelles + 3 duraciones + 4 curvas                                                                                                                               |
| Componentes documentados | 18       | 16 reglas, §7.1 – §7.16 (`EmptyState`+`UiSectionEmpty` y `ConfirmDialog`+`LoadingSpinner` comparten regla)                                                        |

Fuente de los conteos: `resources/js/design-system/tokens.js`,
`resources/css/tokens.generated.css` y las dieciocho primitivas nombradas en §7.

---

## 12. Self-check del artboard

`design/system.html` se contrastó contra este documento con un barrido de sus
literales:

| Comprobación                                                | Resultado                                                 |
| ----------------------------------------------------------- | --------------------------------------------------------- |
| Valores hex distintos en el HTML                            | 86                                                        |
| Valores hex del HTML ausentes en `design/system.md`         | **0**                                                     |
| Familias tipográficas declaradas en el HTML                 | 1 — `var(--font-sans)`, la pila del sistema de §2         |
| Familias del HTML ausentes en `design/system.md`            | **0**                                                     |
| `rgba()` del HTML ausentes en `design/system.md`            | **0**                                                     |
| Referencias externas (`http`, `<link>`, `@import`, `url()`) | **0**                                                     |
| Variables CSS usadas sin definir en el propio HTML          | **0**                                                     |
| Ancho del documento                                         | 1440 px exactos, sin overflow horizontal                  |
| Encabezados `h1` visibles                                   | 1                                                         |
| Contraste de texto                                          | todos los pares del artboard ≥ 4.5:1 (ver §10.1)          |
| `prefers-reduced-motion: reduce`                            | apaga las 3 animaciones y deja las transiciones en 200 ms |

Cinco valores de `design/system.md` no aparecen en el artboard: `#ffe07a`,
`#0f4520`, `#4d3300`, `#5a0608` y `#ffaaa6`. Son los pasos `300`/`900` de los
alias deprecados `success`, `warning` y `error` (§1.5), que existen en la tabla
y no se pintan como muestra porque ningún componente vivo los usa; el artboard
enseña la rampa canónica y no sus alias retirados.
