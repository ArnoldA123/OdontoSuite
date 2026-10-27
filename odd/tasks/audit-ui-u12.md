# Task: U12 — Consolidación, deduplicación y publicación (auditoría UI, plan #13)

Status: IN PROGRESS (sesión de consolidación). Rama `feat/audit-ui-u12` desde
`da9db08` (`main`). No se toca código de producto: la sesión cruza los doce
informes, deduplica contra el repositorio, publica lo que falte y escribe el
informe final en `docs/mejoras/13-informe-final-2026-10.md`. La evidencia vive en
`.atl/qa-evidence/audit/ui/` y no se commitea (§9 del plan).

## Estrategia de entrega

La sesión no mide producto de nuevo: consolida lo medido por U0–U11. El
presupuesto de ~400 líneas de revisión aplica al informe y a la nota, que son la
entrega. Se entrega en cortes: **1** la nota y el cruce por clase raíz; **2** las
publicaciones y aceptaciones; **3** el informe final; **4** el cierre (PR).

## Specs

- **S1** — Objetivo (issue #375, verbatim): «Cerrar el programa con una respuesta
  verificable: qué superficies están sanas, qué defectos quedaron registrados y
  qué se decidió no hacer. Sin esta sesión, el programa produce once informes y
  ninguna conclusión.»
- **S2** — Método (issue #375, verbatim):
  ```
  gh issue list --state all --limit 200 --json number,title,labels,state
  # cruce de .atl/qa-evidence/audit/ui/U1..U11/findings.md por clase raíz
  # dedupe contra issues abiertos y cerrados; publicación con tope global de 25
  ```
- **S3** — Pasos (issue #375, verbatim): «agrupar hallazgos por clase raíz (no
  por módulo); buscar duplicados en abiertos y cerrados; publicar lo que falte;
  puntuar el tablero final (superficies auditadas, veredictos, criterios en 0);
  escribir el informe final con el veredicto por módulo y la lista explícita de
  "sin defectos" con su evidencia.»
- **S4** — Entregable (issue #375, verbatim):
  «`docs/mejoras/13-informe-final-2026-10.md`, issues publicados o aceptados con
  motivo, y `odd/tasks/audit-ui-u12.md` con el cierre del programa.»
- **S5** — Criterio de salida (issue #375, verbatim): «Cero hallazgos sin issue
  ni aceptación escrita; el informe declara el veredicto de cada una de las 36
  rutas; el backlog del programa (§8.4 del plan) queda vacío o con decisión; la
  etiqueta `auditoria-2026-10` queda aplicada a todo lo publicado.»
- **S6** — Plan #13 §8.1 (verbatim): «**Un issue por clase raíz**, nunca por
  síntoma ni por módulo.» · «**Evidencia mínima**: revisión + base + ruta +
  secuencia + captura. Sin eso, el hallazgo queda en backlog.» · «**Separar
  naturaleza**: defecto funcional (`type:defecto`), inconsistencia de diseño
  (`type:defecto`, `area:frontend`), superficie ausente (`type:no-terminado`), o
  verificación que falta (`type:verificacion-ausente`).» · «**Causalidad
  declarada**: preexistente o introducido, con sha.» · «**Tope por sesión**:
  3–7 issues.» · «**La sesión no cierra sus propios issues**.» · «**"Sin
  defectos" es un resultado válido** y exige: render limpio en todos los roles,
  contrato de primitivas cumplido, rúbrica con todos los criterios ≥ 1 y las
  superficies primarias en 2, y walkthrough con todas las acciones `OK`. Se
  publica en el informe, no se calla.»
- **S7** — Plan #13 §8.4 (verbatim): «Hallazgos que no entran en el tope de 7 de
  su sesión, con motivo: `bajo impacto`, `requiere decisión de producto`,
  `duplica #N` o `fuera de alcance`. Se revisan en U12 y se publican o se
  aceptan por escrito.»
- **S8** — Plan #13 §11 (verbatim): «1. Las 36 rutas clasificadas y auditadas al
  menos una vez con sesión real. 2. Las 13 sesiones con su artefacto, su nota ODD
  y su veredicto. 3. Cada superficie con la rúbrica §5 puntuada; cada criterio en
  `0` con issue. 4. Cada hallazgo en un issue con evidencia, o `aceptado` con
  motivo escrito. 5. Un issue por clase raíz; sin duplicados ni issues por
  síntoma. 6. `ui-pass --all` reproducible: cualquier sesión futura repite la
  línea base con un comando y compara contra el artefacto anterior. 7. Informe
  final con veredicto por módulo y la lista explícita de "sin defectos".»
- **S9** — Plan #13 §9 (verbatim, guardarraíles que esta sesión respeta): «La
  sesión que audita no escribe código de producto»; «`.atl/` es gitignored: no se
  commitean capturas ni JSON. Sobrevive la nota ODD y el issue»; «Commits: Solo
  la nota ODD de la sesión (prefijo `docs/`), si el dueño quiere versionarla.»
- **S10** — Issue #375 «Relacionado con» (verbatim): «Plan #13 completo, §8 y
  §11»; «Plan #12 §4 (protocolo de issues y formularios)».

## Tasks

- [x] **T1** · S1, S9 · `inline` · Nota de sesión y rama `feat/audit-ui-u12`.
- [x] **T2** · S2, S3, S6 · `inline` · Cruce de los doce informes por clase raíz y
  dedupe contra abiertos y cerrados.
- [x] **T3** · S3, S5, S6, S7 · `inline` · Publicación de lo que falte y
  aceptaciones escritas con motivo; etiqueta `auditoria-2026-10`.
- [x] **T4** · S3, S4, S5, S8 · `inline` · `13-informe-final-2026-10.md`:
  tablero, veredicto de las 36 rutas, «sin defectos», criterios en `0`, backlog.
- [x] **T5** · S5, S8 · `delegated` · Verificación independiente de completitud
  (cero hallazgo sin dueño) y cierre de la nota.
- [ ] **T6** · S4, S9 · `inline` · Prettier en verde, commits por corte y PR.

## Cruce por clase raíz

- **Población**: 170 filas medidas —108 en las tablas de U0–U10 y 62 en la
  matriz de U11 (36 rutas × 12 ejes)—, más las 12 clases transversales que U11
  agrupa. La rúbrica puntuó 29 superficies.
- **Tablero**: 36 superficies, 509 acciones (0 sin clasificar), 59 diálogos, 140
  endpoints; 36/36 filas de render, 0 `pageerror`, 0 scroll horizontal a 390 px,
  35/36 con un solo `h1`; 432 celdas de eje con 0 `NO PROBADO`; 1 049 filas de
  contrato API por rol en 7 sesiones con 0 desajustes; rúbrica 506/696 (72,7 %)
  con 19 celdas en 0.
- **Dedupe**: los doce informes citan 66 issues en sus tablas y uno más (#422)
  nace de una observación de U6; en total, **67 hallazgos publicados por las
  sesiones**. Tres issues abiertos no están en ninguna tabla (#384 duplicado de
  #378, #386 del instrumento y #422 de la observación de U6), y la verificación
  independiente encontró **cinco relaciones de solapamiento** (#378/#384, #420/#439, #436/#453, la familia del
  422 perdido y la del `h1` del shell). Todas quedan declaradas en el informe
  §5.1, sin cerrar ninguna (§8.1.6).
- **Tope global**: el §7 U12 pedía ≤ 25 issues para todo el programa. Las
  sesiones publicaron 67 antes de llegar aquí. U12 lo registra como desviación y
  publica sólo lo que no tenía dueño.

## Publicación y aceptaciones

- **Publicado**: **#459** `[Auditoría/Dinero] El desglose de una cita sin ítems
  dice Subtotal S/ 0,00 y Total a cobrar S/ 90,00` — `crit:baja`,
  `area:frontend`, `type:defecto`, `auditoria-2026-10`. Leído de vuelta: título,
  cuerpo y cuatro etiquetas correctas. Medido en U5 (`ready-to-bill.json`);
  causa en `BillingService::paymentPreview:102-104` y
  `ReadyToBillPage.vue:447-454`.
- **Aceptaciones escritas** (informe §7.4): A1 `UiSheet` de Análisis IA; A2
  estados de `/login`, `/reset-password` y 404; A3 badge «En vivo» de
  `/calendar`; A4 bloque de error sin `h1` en `/appointment-types/:id`; A5 filas
  de IA que abren el detalle y KPIs globales.
- **Sin issue porque no son defectos**: el `h1` oculto de caja, el botón de
  depósito sobre el efectivo (valida y bloquea en el submit), el panel que
  imprime el mensaje de dominio de la API y la cotización `sent` que no se borra
  (dueño: #422).

## Verificación independiente (S5)

Un `gentle-ai-verify` read-only cruzó las doce tablas contra los issues. Informe:

| Letra | Resultado |
| ----- | --------- |
| (a) filas sin issue ni decisión | **ninguna**; las cuatro filas sin número de U0 llevan decisión escrita en su nota |
| (b) issues citados inexistentes o cerrados | **ninguno**; todos los citados existen y están abiertos, y #363–#374 cerradas como se espera |
| (c) issues huérfanos | **3**: #384 (duplicado de #378), #386 (instrumento) y #422 (observación de U6) — los tres con origen trazable en una nota |
| (d) pares duplicados | **3 pares** (#378/#384, #420/#439, #436/#453) y **2 familias** (#415/#440/#437, #381/#405); declarados en el informe §5.1 |
| (e) `INFERIDO` con issue | **1**: U8 F10 viaja dentro de #434 entre filas `MEDIDO`; declarado en el informe §8.6 |

La verificación no encontró ningún hallazgo sin dueño ni ninguna aceptación
faltante; la fricción que encontró es de solapamiento, no de cobertura.

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Cero hallazgos sin issue ni aceptación escrita | **Cumple**: 67 issues de hallazgo + #459 publicado + 5 aceptaciones; la verificación independiente devolvió «none» en (a) y (b) |
| El informe declara el veredicto de las 36 rutas | **Cumple**: informe §2.2, una fila por ruta con rúbrica, dueños y veredicto; 2 sanas y 34 con defecto |
| El backlog del §8.4 queda vacío o con decisión | **Cumple**: informe §6, nueve ítems con decisión (4 duplicados, 3 aceptados, 2 límites) |
| La etiqueta `auditoria-2026-10` en todo lo publicado | **Cumple**: 13 unidades + 67 hallazgos + #384, #386, #458 y #459; 72 abiertos y 12 cerrados al cierre |

## Límites declarados

1. **La rúbrica de U1 no está en disco**: su carpeta de evidencia no sobrevive y
   la nota no incluye la tabla de 12 criterios; U11 sí mide sus 12 ejes.
2. **Criterios `NO MEDIDO`** de U10 y U9: puntúan `1`, no `0`.
3. **Caminos no recorridos**: descuento > 0 %, reprogramación por teclado, vacío
   del laboratorio en base limpia y el canal en vivo (fuera de alcance por el
   plan #12 A8).
4. **Una fila `INFERIDO`** publicada dentro de #434 (U8 F10), declarada en el
   informe §8.6.
5. **La evidencia no se versiona**: `.atl/` es gitignored; sobreviven la nota,
   el informe y los issues.

## Cortes de entrega

El presupuesto de ~400 líneas aplica a correcciones; aquí la entrega es
documentación de cierre cuyas secciones se citan entre sí (tablero ↔ veredicto
por ruta ↔ criterios en 0 ↔ backlog). Se corta en dos piezas revisables:

1. `2a1ffc8` — `docs(audit): publica el informe final del programa de auditoría
   UI`: el informe y la línea del changelog del plan, **435 líneas**. Es una
   sola pieza; partirla dejaría un informe a medias con tablas que se
   referencian.
2. `docs(audit): registra la sesión U12 y el cierre del programa` — esta nota,
   **188 líneas**. No se cita su hash: es el commit que la contiene.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 375-U12».
- **L2** — La unidad es la sesión de cierre del plan #13. Se ejecuta sin tocar
  producto y sin cerrar issues de hallazgos (§8.1.6 y §9 del plan).
- **L3** — Base `da9db08` (`main`); rama `feat/audit-ui-u12`. La nota y el espejo
  de Engram (`odd/audit-ui-u12/tasks`) se crean antes del primer commit.
- **L4** — Cruce: 170 filas medidas, 67 issues de hallazgo citados y 5 relaciones
  de solapamiento. El tope global de 25 issues del §7 U12 se declara incumplido
  por las sesiones (67 publicados antes de esta), no por U12.
- **L5** — Publicación: #459 creado y leído de vuelta con sus cuatro etiquetas;
  primera tentativa citó #457 (un PR), corregida a #459 en informe y nota.
- **L6** — Verificación independiente: `gentle-ai-verify` devolvió «none» en
  hallazgos sin dueño y en issues citados inexistentes; sus hallazgos (c)–(e) se
  reconciliaron en el informe §5.1 y §8.6.
- **L7** — Cierre: `prettier --check` y `scripts/audit/doc-drift.mjs` (14/14) en
  verde; commits por corte y PR quedan en T6.

