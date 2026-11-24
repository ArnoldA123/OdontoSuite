# Rediseño de la superficie de calendario

## S1 · Objetivo

Arnold no está conforme con la interfaz actual de `/calendar`
(`resources/js/modules/appointments/CalendarPage.vue`): una rejilla de semana con
leyenda propia, barra de filtros de cuatro selects y chips que cargan el estado
con badges. Se exploran direcciones de diseño en `design/calendario/` para elegir
una antes de tocar producción. Nada de este documento cambia código de la app.

## S2 · Restricciones

- Solo `design/system.md`: rampa `accent`, rampas `system*`, canvas `#f7f6f4`,
  radios (`ios` 10, `control` 12, `modal` 14, `card` 16), `elevation-1..4` y
  escala tipográfica 12/13/15/17/20/24/30/36.
- Cada artboard es un HTML autocontenido: CSS, SVG y JS inline; pila de fuentes
  del sistema; sin URLs externas, CDN, fuentes remotas ni imágenes.
- Contenido real de la clínica (pacientes peruanos, profesionales, sillones,
  procedimientos y estados del dominio: `scheduled`, `confirmed`, `arrived`,
  `in_consultation`, `completed`, `cancelled`, `no_show`).
- Accesibilidad: un `h1` por artboard, landmarks (`main`, `nav`, `aside`,
  `section` con `aria-label`), foco visible con
  `0 0 0 3px rgba(15,122,95,.2)`, contraste AA y `prefers-reduced-motion`.
- Reflujo real: media queries a 1240/900 px, sin desborde de documento.

## S3 · Direcciones

- `01-tablero-de-salas.html` — tablero por sillón del día: columnas = sillones,
  filas = horas, panel lateral de pendientes (en sala, sin confirmar, laboratorio
  listo). El estado se codifica en la arista izquierda de la cita.
- `02-agenda-de-la-semana.html` — semana cronológica sin rejilla: cada día es una
  columna tipográfica con horas sobre tarjetas; el color identifica al
  profesional y el estado solo aparece cuando pide acción.
- `03-semana-foco.html` — semana clásica depurada: mini-mes y profesionales en
  una barra lateral, píldoras de estado en lugar de la barra de filtros, línea de
  «ahora», encabezados de día fijos y bloques con arista de estado.

## S4 · Tareas

- T1 — Escribir `design/calendario/01-tablero-de-salas.html`. Hecho.
- T2 — Escribir `design/calendario/02-agenda-de-la-semana.html`. Hecho.
- T3 — Escribir `design/calendario/03-semana-foco.html` y `design.json` del
  conjunto.
- T4 — Implementar la dirección elegida `01-tablero-de-salas.html` en la app
  real: la vista **Día** de `/calendar` pasa a tablero por sillón (o por
  profesional) con franja de días, filtros por profesional, línea de «ahora»,
  huecos libres agendables y panel de pendientes del turno. Semana y mes no
  cambian; sin rutas, permisos ni migraciones nuevas.
- T5 — Armonizar las tres vistas y dar aire. Medido en la app servida
  (1440 × 1000): el contenido empieza a 330 px en Día y a 438 px en Semana y
  Mes; seis bandas de chrome antes de la rejilla; Día filtra con pastillas y
  Semana/Mes con una barra de tres selects; la leyenda de ocho estados ocupa
  una banda; un hueco de 330 min se pinta como un botón gigante; el panel
  lateral deja tres columnas y media y corta nombres; y el documento crece a
  1.738 px porque el tablero no tiene scroll propio.
  - T5a — Marco único de página (cabecera compacta, una sola fila de
    herramientas, pastillas compartidas, sin banda de leyenda), scroll interno
    del tablero con gutter y cabeceras fijas, regla de huecos (botón solo hasta
    120 min) y cabecera de columna en dos líneas.
  - T5b — Semana y Mes con el mismo bloque y las mismas pastillas que Día.

## Log

- 2026-11-24 — Direcciones nuevas en `design/calendario/`: 01 tablero de salas,
  02 agenda de la semana y 03 semana con foco. Pendiente la elección del usuario
  y el handoff a implementación.
- 2026-11-24 — Arnold elige `design/calendario/01-tablero-de-salas.html` (la
  base le gusta) y pide mejorarla: jerarquía con la fecha como título, filtros
  por profesional, agrupación sillón/profesional, huecos libres agendables,
  estado de cada sillón y línea de «ahora». Artboard actualizado y aprobado.
- 2026-11-24 — Handoff de `01-tablero-de-salas.html` a implementación (tarea T4).
  La vista Día se reescribe como tablero; 02 y 03 quedan como alternativas.
- 2026-11-24 — Implementación verificada: `CalendarPage.vue` (vista Día como
  tablero por ambiente o profesional) más el módulo puro `calendarBoardMath.js`;
  186 pruebas de Vitest y 110 guards de PHPUnit en verde. Handoff cerrado como
  implementado. En la misma sesión se unificó el vocabulario visible a
  «Ambiente» en alta de cita, continuidad, agenda, dashboard, ambientes y el
  comando de diagnóstico (#526); quedan solo docblocks internos con «sillón».
- 2026-11-24 — T5 cerrada con medición en navegador (1440×1000): contenido a
  285 px en Día y 241 px en Semana y Mes (antes 330 y 438), documento a
  1.139/1.025/1.000 px (antes 1.738), leyenda de cinco estados, franja de días
  de 40 px sin solape, cabeceras de columna completas, Semana con bloques por
  minuto y Mes con tres bloques y «+N más». Suite enfocada: 138 pruebas de
  Vitest en verde; guards de PHPUnit y doc-drift sin cambios.
- Pendiente de decisión: commit y PR del conjunto, entrada en el §12 de
  AGENTS.md y precarga de ambiente en el alta desde un hueco libre.
- 2026-11-24 — T6 (ancho, vista y esquinas), T6b (aire y ruido) y T7 (auditoría
  visual + capacidad) cerradas sobre la app servida:
  · `/calendar` usa ancho completo por ruta, abre en Día y recuerda la vista;
    radios 16 en la tarjeta y 10 en bloques, chips y huecos.
  · Aire medido: franja → herramientas 12 px, herramientas → leyenda 8 px,
    banda → tablero 20 px. Una sola aparición de «Sin citas» por superficie y
    el bloque de vacío sin tarjeta propia (un solo marco).
  · Capacidad con 40 citas en un día: el Mes cierra con «+37 más» y salta al
    día; Día y Semana reparten los solapes en carriles (máximo 3 visibles) con
    cero cajas intersecadas; sin desborde horizontal a 2559, 1440, 1024, 768 y
    390.
  · Auditoría de 18 combinaciones (5 anchos × 4 estados): 0 textos recortados
    reales a 390, 0 controles por debajo de 32 px salvo el segmentado de
    escritorio (28), y el «Calendario» recortado resultó ser el `h1.sr-only` del
    topbar.
  · Suite: 232 pruebas de Vitest en 12 archivos y 208 guards de PHPUnit en
    verde; ESLint, Prettier y Pint limpios.

## Entrega

Tres PRs encadenados, en este orden, porque el conjunto supera las 10.000 líneas
contando artboards:

1. `docs/calendario-direcciones` — direcciones en `design/calendario/` y esta bitácora.
2. `fix/agenda-vocabulario-ambiente` — copia visible «ambiente» en alta, continuidad, dashboard, ambientes y comando (#526).
3. `feat/calendario-tablero-ambiente` — tablero por ambiente, semana por bloques, capacidad, ancho, aire y §12 de `AGENTS.md`.
