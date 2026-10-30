# Brief de pantallas — `design/interfaces/`

Contrato compartido por todos los artboards. La referencia es
`01-shell-dashboard.html`: **se copia su chrome sin reinterpretarlo**.

## Reglas duras (idénticas en todas las pantallas)

- Un archivo HTML autocontenido: `<style>` e iconos SVG inline. Cero `http`,
  `<link>`, `@import`, `url()`, fuentes remotas ni imágenes externas.
- `<html lang="es">`, `viewport width=1440`, body de 1440 px, sin desborde
  horizontal.
- Un solo `<h1>` visible (el título de la página va en el `hero`; el topbar
  repite el título en 20 px — si el hero ya lo pinta, el topbar lo publica como
  contexto, nunca como segundo `h1`).
- Landmarks: `<nav>` sidebar, `<header>` topbar, `<main>` contenido; tablas con
  `<caption>` y `<th scope="col">`.
- `:focus-visible` con `0 0 0 3px rgba(15,122,95,.2)` + `outline-offset: 2px`.
- `@media (prefers-reduced-motion: reduce)` apaga transiciones y animaciones.
- Contraste AA: nunca texto blanco sobre `#ff3b30`, `#34c759` ni `#ffcc00`.
- Sólo tokens documentados en `design/system.md`. Nada de hex inventados.
- Contenido real de clínica peruana en español; importes `S/ 1,850.00` con
  `font-feature-settings: "tnum" 1, "lnum" 1`; horas 24 h.
- Pie del artboard: `design/interfaces/NN-nombre.html · 1440 px · 2026-10-29`.

## Chrome (copiar de `01-shell-dashboard.html`)

| Pieza             | Medida                                                                     | Nota                                                                            |
| ----------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| Sidebar           | 248 px fija, glass, padding 20/12                                          | El ítem activo cambia por pantalla (`aria-current="page"`)                      |
| Topbar            | 64 px sticky, glass, padding 0 32                                          | Título 20/600 + contexto 12.5 secundario                                        |
| Contenido         | `max-width: 1280px`, padding 32, gap 24, fondo `#f7f6f4`                   | Grilla de 12 columnas disponible                                                |
| Hero              | card blanca radius 22, padding 28, `elevation-2`                           | eyebrow 11/600 uppercase + h1 30/36 + sub 15 secundaria + acciones a la derecha |
| KPI strip         | 4 cards, grid 4, gap 16, radius 16, padding 20                             | label 12 uppercase, valor 30/600 tabular, meta 12.5 con dot                     |
| Card de contenido | radius 16, borde hairline, padding 20–24                                   | header: título 17/600 + acción ghost                                            |
| Tabla             | filas 56 px, header 11 uppercase, separadores hairline                     | importes y horas a la derecha, tabular                                          |
| Pills             | radius 999, 12/500, padding 3×10 + dot 6 px                                | success / warning / danger / info / neutral                                     |
| Botones           | 44 px alto (md), radius 10, primary accent / secondary borde / ghost texto | `type="button"` siempre                                                         |

Composición por defecto: hero → KPI strip → **barra de filtros** (`UiFilterBar`:
búsqueda + selects + «Limpiar filtros») → bloque principal en dos columnas
(`1.55fr 1fr`) con la tabla/workspace a la izquierda y el rail de contexto a la
derecha. Las pantallas de configuración usan una sola columna centrada.

Estados obligatorios cuando la pantalla los tenga: carga (skeleton), vacío
(`UiSectionEmpty` con acción), error (bloque con «Reintentar»).

---

## 02 — Mi día (`02-my-day.html`)

Cola del profesional, no un reporte: es donde se atiende.

- **Hero**: h1 «Mi día», sub «12 citas hoy · 4 en espera · 2 listas para
  cerrar»; acciones: secondary «Actualizar», primary «Nueva cita».
- **KPIs**: En espera 4 (dot warning) · En atención 2 (info) · Próximas 6
  (neutral) · Listas para cerrar 2 (success).
- **Principal**: tablero de tres columnas (`En espera`, `En atención`,
  `Próximas`) con 2–3 tarjetas cada una. Tarjeta: hora 15/600 tabular, paciente
  13.5/600, procedimiento 12.5 secundaria, meta «Sillón 2 · Resina 26», pill de
  estado y acción primaria/ghost. La columna «En espera» muestra el tiempo de
  espera («15 min»).
- **Rail**: «Cierre pendiente» (2 atenciones sin firmar, con acción «Firmar») y
  «Laboratorio listo» (2 casos con paciente y pieza).
- **Nota de diseño**: el tablero reemplaza la lista plana; la decisión se
  registra en el `tradeoff` del manifiesto.

## 03 — Calendario (`03-calendar.html`)

- **Hero**: h1 «Calendario», sub «Semana del 27 de octubre al 2 de noviembre ·
  Sede San Isidro»; acciones: conmutador segmentado «Día / Semana / Mes» (Semana
  activo) + primary «Nueva cita».
- **KPIs**: Citas de la semana 58 · Ocupación 78 % · Canceladas 3 · No
  asistió 2.
- **Filtros**: Profesional (todos), Sillón (todos), Sede (San Isidro), «Limpiar
  filtros».
- **Principal**: rejilla semanal con columna de horas 06:00–21:00 (rango visible
  08:00–18:00) y 6 columnas de día de 112 px; chips de cita con hora, paciente y
  procedimiento; dos chips solapados en un día deben mostrar el aviso de scroll
  horizontal con degradado y «Desliza para ver más días». Sábado con media
  jornada.
- **Rail**: «Lista de espera» (3 solicitudes con fecha preferida y acción
  «Agendar») y «Conflictos» (1 sillón ocupado con aviso warning).

## 04 — Pacientes (`04-patients-list.html`)

- **Hero**: h1 «Pacientes», sub «1,284 fichas · 12 nuevas este mes»; primary
  «Nuevo paciente».
- **KPIs**: Total 1,284 · Nuevos este mes 12 · Con saldo pendiente S/ 8,420.00 ·
  Inactivos 96.
- **Filtros**: búsqueda «Buscar por nombre, documento o teléfono», Estado
  (Todos), Sede (San Isidro), Orden (Última visita), «Limpiar filtros».
- **Tabla**: Paciente (avatar + nombre + DNI), Contacto (teléfono + correo),
  Última visita, Saldo (tabular, rojo si > 0), Estado (pill), Acciones (ghost
  «Ver»
  - «Agendar»). 8 filas realistas; una con saldo cero, una inactiva.
- **Paginación**: «Mostrando 1 a 20 de 1,284» + páginas 1 2 3 … 65 con
  `aria-current="page"`.
- **Rail**: «Pacientes con saldo» (3 filas con importe y acción «Cobrar») y
  «Recordatorios de continuidad» (2 recalls vencidos).

## 05 — Ficha del paciente (`05-patient-file.html`)

- **Hero**: identidad (avatar 56 «AT»), h1 «Ana Lucía Torres Vega», meta «DNI
  45872103 · 34 años · +51 987 654 321»; pills «Alergia: penicilina» (warning) y
  «Saldo S/ 180.00» (danger); acciones: secondary «Nuevo cargo», primary
  «Agendar cita».
- **Tabs**: Resumen (activo), Historia clínica, Planes, Presupuestos,
  Especialidades, Laboratorio, Cuenta, Historial.
- **Principal (Resumen)**: card «Datos del paciente» (2 columnas de pares
  etiqueta/valor), card «Últimas atenciones» (tabla 4 filas: fecha, profesional,
  procedimiento, importe, pill), card «Estado de cuenta» con resumen (cargos −
  descuentos − pagos + devoluciones − condonaciones + ajustes) y saldo.
- **Rail**: «Próxima cita» (fecha grande, hora, sillón, profesional, acciones),
  «Plan de tratamiento activo» (progreso 8/12 ítems, importe), «Laboratorio» (1
  caso en `try_in`).

## 06 — Historias clínicas (`06-medical-records.html`)

- **Hero**: h1 «Historias clínicas», sub «Workspace del paciente · lectura
  auditada»; primary «Nueva historia».
- **KPIs**: Historias 412 · Evoluciones del mes 86 · Con addenda 7 · Sin
  firmar 0.
- **Principal**: selector de paciente a la izquierda (lista con 5 pacientes, el
  activo con borde accent) y workspace a la derecha con tabs (General,
  Evoluciones activo, Adjuntos, Estadísticas). En Evoluciones: dos tarjetas de
  evolución con bloques S/O/A/P etiquetados, firma («Firmado por Dra. Salazar ·
  hash 9f2c…»), pill de especialidad y acción ghost «Nuevo addendum»; un
  addendum inline con borde dashed y autor/fecha/motivo.
- **Rail**: «Datos de la historia» (HC-DEMO-001, apertura, profesional) y
  «Advertencia de inmutabilidad» (card info: no se edita ni se borra, se corrige
  con addendum).

## 07 — Planes de tratamiento (`07-treatment-plans.html`)

- **Hero**: h1 «Planes de tratamiento», sub «34 activos · S/ 42,650.00 en
  curso»; acciones: conmutador «Lista / Kanban» (Lista activo) + primary «Nuevo
  plan».
- **KPIs**: Activos 34 · Por vencer 6 · Aceptados este mes 11 · Importe en curso
  S/ 42,650.00.
- **Filtros**: Estado (presets «Activos», «Por vencer», «Todos»), Paciente,
  Profesional, «Limpiar filtros».
- **Tabla**: Plan (PLAN-2026-0142), Paciente, Estado (pill), Progreso (texto «8
  de 12 ítems» + barra fina), Importe, Vence, Acciones (ghost «Ver», «Editar»).
  6 filas.
- **Panel de detalle** (segunda columna, card): plan seleccionado con badge «v3
  aceptada», tres líneas con prioridad (urgente/electivo/opcional), sitio (pieza
  26, superficie O) e importe; pie con total y acciones «Aprobar» (primary) y
  «Rechazar» (ghost).
- **Rail**: «Alertas» (2 ítems vencidos + 1 alternativa A/B pendiente de
  decisión).

---

## 08 — Presupuestos (`08-quotations.html`)

- **Hero**: h1 «Presupuestos», sub «46 emitidos · S/ 128,400.00 · 6 por vencer»;
  acciones: secondary «Exportar», primary «Nuevo presupuesto».
- **KPIs**: Emitidos 46 · Aprobados 18 · Por vencer 6 · Importe aprobado
  S/ 62,300.00.
- **Filtros**: búsqueda «Número, paciente o documento», Estado (Todos), rango de
  fechas (01 oct – 31 oct), «Limpiar filtros».
- **Tabla**: Número, Paciente, Estado (borrador/enviado/visto/aprobado/rechazado/
  vencido), Total, Emitido, Vence, Acciones (ghost «Ver», «PDF»). 6 filas.
- **Panel de detalle**: presupuesto seleccionado con desglose **Subtotal /
  Descuento / Base imponible / IGV 18 % / Total** (el IGV se descompone, la suma
  es el total), pie con «Descargar PDF» (ghost) y «Enviar» (primary, sólo desde
  borrador).
- **Rail**: «Por vencer» (3 con días restantes y acción «Recordar») y «Aprobados
  sin plan» (1 aviso info).

## 09 — Caja (`09-cash-register.html`)

- **Hero**: h1 «Caja», sub «Turno abierto 08:05 · Sede San Isidro»; acciones:
  secondary «Depositar», primary «Nuevo cobro».
- **KPIs**: Apertura S/ 200.00 · Cobros S/ 2,660.50 · Egresos S/ 0.00 · Saldo
  esperado S/ 2,860.50.
- **Tabs**: Movimientos (activo), Cobros, Sesiones, Depósitos, Reportes.
- **Principal**: tabla de movimientos (Hora, Tipo, Concepto, Método, Paciente,
  Importe) con 6 filas; una con método «Yape» debe llevar la nota «electrónico · no
  mueve gaveta»; una devolución en `danger` con su motivo.
- **Estados del hero** (tira bajo los KPIs, tres miniaturas rotuladas): «Sin
  sesión» (KPIs `N/D` + «Abrir caja»), «Sesión abierta» (la del hero), «Sesión
  cerrada» (arqueo: contado, esperado, diferencia).
- **Rail**: «Cierre del turno» con Efectivo / Electrónico / Depósitos /
  Pendiente de depositar (aritmética visible) y «Sesión» (usuario, apertura,
  movimientos).

## 10 — Listos para facturar (`10-ready-to-bill.html`)

- **Hero**: h1 «Listos para facturar», sub «8 atenciones cerradas sin cobrar ·
  S/ 3,180.00»; acciones: secondary «Exportar», primary «Cobrar».
- **KPIs**: Atenciones 8 · Importe S/ 3,180.00 · Con seguro 2 · Vencidas +48 h 1.
- **Tabla**: Paciente, Atención (fecha + código), Cierre, Concepto, Saldo,
  Acciones («Cobrar»). 6 filas; la vencida con pill warning.
- **Panel de cobro** (card derecha, reproduce el modal real): paciente, importe
  editable, método (Efectivo), referencia (deshabilitada con nota «el método no
  exige referencia»), descuento, y el aviso de bloqueo contable con su campo de
  motivo de administrador; pie con «Cancelar» y «Registrar cobro».
- **Rail**: «Del turno» (cobrado hoy, pendiente) y «Bloqueo contable» (fecha de
  corte y estado).

## 11 — Business intelligence (`11-business-intelligence.html`)

- **Hero**: h1 «Business intelligence», sub «Octubre 2026 · Sede San Isidro»;
  acciones: select de período («Últimos 30 días»), primary «Exportar».
- **KPIs**: Ingresos S/ 62,300.00 · Atenciones 184 · Ticket promedio S/ 338.59 ·
  Ocupación 78 %.
- **Filtros**: Sede (San Isidro), Período, Profesional (todos), «Limpiar filtros».
- **Principal**: gráfico de barras inline SVG (ingresos por día, 30 barras con
  eje y rótulos) y debajo el reporte tabular (Pacientes / Ingresos / Atenciones)
  con el corte «Por sede» y el bloque de conciliación (efectivo, electrónico,
  depósitos, pendiente de depositar).
- **Rail**: «Consolidado» (tres sedes con importe y variación) y aviso info
  «Sin sede» (2 movimientos sin cajón).

## 12 — Continuidad (`12-continuity.html`)

- **Hero**: h1 «Continuidad», sub «18 en cola · 6 recalls vencidos»; primary
  «Agendar».
- **KPIs**: Recalls vencidos 6 · Solicitudes pendientes 12 · En cola 18 ·
  Cumplimiento 72 %.
- **Filtros**: Tipo (Todos), Intervalo (Todos), Sede (San Isidro), «Limpiar
  filtros».
- **Tabla**: Paciente, Origen (plan / atención / cita), Motivo (control 6 meses),
  Vence, Estado (pill), Acciones (primary «Agendar», ghost «Descartar» sólo en
  solicitudes). 6 filas.
- **Rail**: «Cumplimiento» (barra 72 % con la fórmula `completados /
  (completados + vencidos)` y los dos operandos) y «Sin contacto» (2 con teléfono
  y acción «Llamar»).

## 13 — Laboratorio protésico (`13-lab-cases.html`)

- **Hero**: h1 «Laboratorio protésico», sub «14 casos activos · 3 entregados hoy»;
  primary «Nuevo caso».
- **KPIs**: En preparación 4 · En laboratorio 6 · Prueba en boca 3 · Entregados
  hoy 3.
- **Filtros**: Fase (Todas), Técnico («Mis casos»), checkbox «Atrasados», «Limpiar
  filtros».
- **Tabla**: Caso, Paciente, Trabajo / Pieza, Material, Enviado, Prometido, Fase,
  Acciones. 6 filas con fases distintas; una atrasada en warning.
- **Rail**: «Fases» (escalera vertical de cinco fases, la actual marcada, con la
  regla «sólo avanza») y «Atrasados» (1 caso con días de atraso y acción
  «Avanzar fase»).

---

## 14 — Análisis IA (`14-ai-analysis.html`)

- **Hero**: h1 «Análisis IA», sub «Apoyo a la decisión · 46 análisis este mes»;
  primary «Nuevo análisis».
- **KPIs**: Del mes 46 · Pendientes de revisión 5 · Citados en el registro 12 ·
  Anulados 2.
- **Filtros**: búsqueda por paciente, Estado (Todos / Pendiente / Revisado /
  Anulado), Sede, «Limpiar filtros».
- **Tabla**: Paciente, Adjunto (miniatura con nombre de archivo), Atención, Estado
  (pill), Costo (`USD 0.0042`), Fecha, Acciones («Revisar»). 6 filas; una anulada
  con motivo y una con el bloque `data-ai-failure` → «El análisis no se pudo
  completar.» en línea.
- **Panel de alta** (card derecha, reproduce el sheet): zona de carga con
  arrastrar y soltar, nombre y peso del archivo, checkbox obligatorio «Declaro que
  informé al paciente sobre el uso de IA», nota del tope diario por paciente y
  primary «Analizar imagen».
- **Rail**: «Aviso asistivo» (card info: no sustituye el juicio clínico ni entra al
  registro sin revisión) y «Consumo del mes» (tokens y costo estimado).

## 15 — Registros de especialidad (`15-specialty-records.html`)

- **Hero**: identidad del paciente en el sub «Ana Lucía Torres Vega · DNI
  45872103 · 4 registros»; h1 «Registros de especialidad»; primary «Nuevo
  registro».
- **KPIs**: Ortodoncia 1 · Endodoncia 1 · Implantología 1 · Rehabilitación 1.
- **Tabs** con badge de conteo: Ortodoncia, Endodoncia, Implantología,
  Rehabilitación, Cirugía oral (la activa con su panel).
- **Principal**: dos tarjetas de registro con fecha, pieza, procedimiento
  vinculado, estado (pill), notas quirúrgicas y, en rehabilitación, «Prueba en
  boca 22 oct 2026»; cada una con ghost «Editar» y «Ver».
- **Rail**: «Paciente» (datos y alergia) y «Piezas tratadas» (mini odontograma
  esquemático con 4 piezas marcadas).

## 16 — Catálogo de procedimientos (`16-procedure-catalog.html`)

- **Hero**: h1 «Catálogo de procedimientos», sub «128 procedimientos · 96 activos»;
  primary «Nuevo procedimiento».
- **KPIs**: Total 128 · Activos 96 · Con receta 38 · Con tarifa por sede 24.
- **Filtros**: búsqueda («Nombre, código o descripción»), Especialidad, Estado,
  Sitio (Todos), «Limpiar filtros».
- **Tabla**: Código (`CPT-0001`), Nombre, Especialidad, Sitio (pill
  diente/superficie), Precio aplicado (con su origen «Sede» / «Convenio»), Insumos,
  Estado, Acciones. 6 filas.
- **Panel de detalle**: tabs «Detalle / Tarifario / Historial». Detalle:
  aplicabilidad, códigos externos (RM 550-2023/MINSA y SIS) y receta («Resina A2 ·
  0,5 jeringa»). Tarifario: fila por sede con vigencia y estado (vigente / futuro /
  vencido). Historial: dos filas de auditoría.
- **Rail**: «Calidad del catálogo» (12 sin receta, 8 sin código externo) con barras
  y aviso.

## 17 — Estadísticas del catálogo (`17-procedure-stats.html`)

- **Hero**: h1 «Estadísticas del catálogo», sub «01–31 oct 2026 · Sede San Isidro»;
  primary «Exportar CSV».
- **KPIs**: Usos 412 · Procedimientos usados 38 · Ingreso generado S/ 48,900.00 ·
  Ticket promedio S/ 118.69.
- **Filtros**: presets 7 / 30 / 90 días, rango de fechas, Especialidad, Top N (10),
  «Limpiar filtros».
- **Principal**: ranking (posición, procedimiento, usos, ingreso, % del total con
  barra proporcional) y distribución por especialidad como lista con barras (sin
  librería de gráficos).
- **Rail**: «Top 3» y «Sin uso» (procedimientos activos sin uso en el período).

## 18 — Mis procedimientos (`18-my-procedures.html`)

- **Hero**: h1 «Mis procedimientos», sub «Favoritos de Dra. Salazar · 18»;
  acciones: secondary «Explorar catálogo».
- **KPIs**: Favoritos 18 · Usados este mes 9 · Con receta 7 · Compartidos 3.
- **Filtros**: búsqueda, Especialidad, «Limpiar filtros».
- **Principal**: rejilla de tarjetas de procedimiento (nombre, código, precio,
  pill de especialidad, estrella de favorito, «Agregar al plan»), 6 tarjetas.
- **Rail**: «Sugeridos para ti» (3, con motivo) y «Usados recientemente» (3).

## 19 — Recepción de procedimientos (`19-reception-procedures.html`)

- **Hero**: h1 «Recepción de procedimientos», sub «Consulta rápida · sólo lectura»;
  sin acción primaria.
- **KPIs**: Procedimientos 128 · Con precio de sede 24 · Especialidades 8 ·
  Actualizado hoy 09:12.
- **Principal**: buscador grande y prominente («¿Qué procedimiento busca?») + 6
  resultados en tarjetas con nombre, código, precio de la sede, duración estimada y
  sitio; acciones «Ver precio» y «Agendar».
- **Rail**: «Qué puede hacer recepción» (card info: consultar y agendar, no editar
  el catálogo) y «Más buscados» (3).

## 20 — Profesionales (`20-professionals.html`)

- **Hero**: h1 «Profesionales», sub «12 activos · 3 especialidades»; primary «Nuevo
  profesional».
- **KPIs**: Activos 12 · Odontólogos 7 · Implantólogos 3 · Asistentes 2.
- **Filtros**: búsqueda, Especialidad, Estado, «Limpiar filtros».
- **Tabla**: Profesional (avatar + nombre + rol), Especialidades (pills), Sede,
  Contacto, Próxima disponibilidad, Acciones («Ver ficha»). 6 filas.
- **Panel de ficha**: profesional seleccionado con horario semanal (mini rejilla de
  5 días × 3 bloques), sus especialidades y su próxima cita; pie con «Editar».
- **Rail**: «Cobertura por especialidad» (barras) y «Sin horario» (1 aviso).

---

## 21 — Tipos de cita (`21-appointment-types.html`)

- **Hero**: h1 «Tipos de cita», sub «18 tipos · 12 activos»; primary «Nuevo tipo».
- **KPIs**: Total 18 · Activos 12 · Requieren confirmación 7 · Con insumos 9.
- **Filtros**: búsqueda, Estado, «Limpiar filtros».
- **Tabla**: Nombre, Duración, Precio base, Requiere confirmación (pill sí/no),
  Insumos (n, derivado de la receta), Estado, Acciones. 6 filas.
- **Panel de detalle**: tipo seleccionado con duración, color, precio y la nota
  «Los insumos se derivan del recetario del procedimiento; no se marcan a mano».
- **Rail**: «Más usados este mes» (3 con conteo) y aviso info.

## 22 — Ambientes (`22-environments.html`)

- **Hero**: h1 «Ambientes», sub «6 sillones · 4 en uso ahora»; primary «Nuevo
  ambiente».
- **KPIs**: Activos 6 · En uso ahora 4 · En mantenimiento 1 · Sede San Isidro 4.
- **Filtros**: Sede, Estado, «Limpiar filtros».
- **Tabla**: Ambiente, Sede, Tipo, Estado (pill), Ocupación actual (paciente y hora
  o «Libre»), Acciones. 6 filas.
- **Rail**: «Ocupación de hoy» (barra 4/6) y «Mantenimiento» (1 con nota).

## 23 — Catálogo de especialidades (`23-specialties.html`)

- **Hero**: h1 «Catálogo de especialidades», sub «8 especialidades · 6 activas»;
  primary «Nueva especialidad».
- **KPIs**: Total 8 · Activas 6 · Procedimientos 128 · Profesionales 12.
- **Tabla**: Código, Nombre, Procedimientos, Profesionales, Estado, Acciones.
  6 filas.
- **Panel de detalle**: modal de edición con código **de sólo lectura** (el `code`
  es inmutable en `PUT`) y la nota «El código no se modifica; las especialidades se
  desactivan, no se eliminan».
- **Rail**: «Uso» (top 3 por procedimientos) y aviso.

## 24 — Sucursales (`24-settings-branches.html`)

- **Hero**: h1 «Sucursales», sub «3 sedes · 2 activas»; primary «Nueva sucursal».
- **KPIs**: Sedes 3 · Activas 2 · Personal 14 · Facturación del mes S/ 62,300.00.
- **Tabla**: Sede, Dirección, Personal, Cajas, Estado, Acciones. 3 filas.
- **Panel de detalle**: la sede con su alcance («quien tiene sede opera sólo esa
  sede; sin sede, alcance de grupo») y sus conteos.
- **Rail**: «Alcance de grupo» (card info) y «Traslados pendientes» (2).

## 25 — Métodos de pago (`25-payment-methods.html`)

- **Hero**: h1 «Métodos de pago», sub «7 métodos · 3 exigen referencia»; primary
  «Nuevo método».
- **KPIs**: Total 7 · Efectivo 1 · Electrónicos 4 · Exigen referencia 3.
- **Filtros**: búsqueda, Familia (Todos), Estado, «Limpiar filtros».
- **Tabla**: Método, Familia (pill `cash` / `card` / `transfer` / `wallet`),
  Referencia (pill), Estado, Acciones. 6 filas.
- **Rail**: «Regla del cobro» (card info: un método con referencia obligatoria
  rechaza el cobro sin número) y «Uso del mes» (barras por familia).

## 26 — Traslados de sede (`26-branch-transfers.html`)

- **Hero**: h1 «Traslados de sede», sub «2 pendientes · 1 por decidir»; primary
  «Solicitar traslado».
- **KPIs**: Pendientes 2 · Aprobadas este mes 3 · Rechazadas 1 · Mi sede: San
  Isidro.
- **Tabla**: Solicitante, Origen → Destino, Motivo, Solicitado, Estado, Acciones
  (Aprobar / Rechazar / Cancelar según el caso). 5 filas; una es la propia
  solicitud del actor (sin acciones de decisión).
- **Rail**: «Reglas» (una sola solicitud abierta; nadie decide la suya; la
  aprobación mueve la afiliación) y «Historial» (2 decisiones).

## 27 — Bloqueo contable (`27-accounting-locks.html`)

- **Hero**: h1 «Bloqueo contable», sub «4 módulos · 2 bloqueados»; primary
  «Actualizar cierre».
- **KPIs**: Módulos 4 · Bloqueados 2 · Último cierre 31 ago 2026 · Sedes 3.
- **Tabla**: Módulo, Origen (Sede / Global), Vigente hasta, Estado, Acciones
  (Fijar / Retirar). 4 filas.
- **Panel**: formulario «Fijar cierre» con módulo, sede, fecha y la nota del orden
  «la fila de la sede gana sobre la global».
- **Rail**: «Cómo funciona» (card info: 403, motivo de administrador de ≥10
  caracteres y auditoría) y «Autorizaciones recientes» (2).

## 28 — Login (`28-login.html`)

Fuera del shell, 1440×900, sin sidebar ni topbar.

- **Izquierda (60 %)**: panel de marca con el motivo dental en SVG, «OdontoSuite»
  y una frase corta («La clínica, en orden: agenda, acta clínica y dinero en un
  solo lugar»), más tres viñetas discretas.
- **Derecha (40 %)**: tarjeta de formulario (radius 22, `elevation-2`) con h1
  «Iniciar sesión», correo, contraseña con mostrar/ocultar, «¿Olvidaste tu
  contraseña?», primary «Entrar» y el error de credenciales en `danger` (estado
  visible en una segunda tarjeta rotulada «Estado: credenciales inválidas»).
- **Recuperación**: tarjeta pequeña debajo con el enlace de recuperación ya
  aplicado (`data-reset-token-applied`) y el aviso de que el código viene en el
  enlace.

## 29 — Página no encontrada (`29-not-found.html`)

- **Dentro del shell**: sidebar y topbar completos, sin ítem activo.
- **Contenido**: tarjeta centrada con h1 «Página no encontrada», explicación corta
  («La ruta no existe o cambió de nombre»), el código `404` en 12 px tabular y dos
  acciones: «Volver al panel» (primary) y «Ver pacientes» (secondary).
- Sin KPIs, sin filtros, sin rail: es una pantalla de recuperación.
