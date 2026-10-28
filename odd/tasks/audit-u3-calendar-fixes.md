# Task: cierre de hallazgos U3 — agenda y asistente (#391/#392/#394/#395/#396/#397)

Status: OPEN. Rama `fix/audit-u3-calendar` en el worktree
`/home/chomb/proyectos/odontosuite-u3` desde `90c732be`. Work units en commits
con Conventional Commits; la revisión nativa y el push quedan como decisión del
señor Arnold.

## Specs

- **S1** — Pedido del usuario, verbatim: «haz las issues #391 #392 #394 #395
  #396 #397 en un worktree nuevo».
- **S2** — #391 «El asistente de cierre es un overlay propio: sin rol de
  diálogo, foco atrapado ni Escape», criterios de aceptación verbatim:
  ```
  - El asistente declara `role="dialog"`, `aria-modal="true"` y nombre accesible
    (el `h2` vía `aria-labelledby`).
  - Foco atrapado dentro del panel al abrir y tras recorrer con `Tab`; Escape
    cierra (con guardia si hay contenido) y el scroll queda bloqueado mientras el
    asistente vive.
  - Un clic en el fondo no descarta el acto sin confirmación.
  - Con un `UiModal` encima, cerrarlo con Escape devuelve el foco al asistente y
    **mantiene** el bloqueo de scroll.
  - `pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep "@U3 wizard · contrato"`
    deja los checks de primitiva en verde.
  ```
- **S3** — #392 «Reprogramar una cita a un horario liberado por una cancelación
  responde 500», criterios de aceptación verbatim:
  ```
  - Reprogramar (y crear) sobre un horario que ocupaba una cita cancelada
    responde 200 y mueve la cita, o responde 422 con `conflict` y un mensaje
    accionable; en ningún caso 500.
  - El índice único y el pre-chequeo de conflicto declaran la misma regla (una
    sola fuente), con prueba de regresión que cancele una cita y reutilice su
    horario por las dos puertas (crear y reprogramar).
  ```
- **S4** — #394 «Cancelar una cita no pide confirmación ni motivo», criterios de
  aceptación verbatim:
  ```
  - «Cancelar Cita» abre una confirmación que exige un motivo (y lo guarda en el
    acto de la cita, no en un `notes` genérico que otra edición pise).
  - El aviso de éxito nombra la consecuencia cuando hay recordatorios pendientes.
  - Cancelar sigue siendo posible desde el teclado y con el foco dentro del
    diálogo de confirmación.
  ```
- **S5** — #395 «La agenda no ofrece filtros por profesional, sillón ni sede»,
  criterios de aceptación verbatim:
  ```
  - La agenda ofrece filtros por profesional, sillón y sede, visibles solo para
    quien puede leer esas poblaciones, y los aplica a la consulta del rango (sin
    recargar la página completa).
  - `GET /api/appointments` acepta y aplica `dental_chair_id`, con el mismo
    alcance por sede que ya rige para el resto de filtros.
  - Con filtros activos, el periodo, el vacío y la navegación de fechas siguen
    siendo coherentes, y hay una prueba de que filtrar por un profesional sin
    citas deja el vacío de ese filtro (no el de la agenda completa).
  ```
- **S6** — #396 «La agenda no trata sus errores: un fallo de lectura se ve como
  agenda vacía», criterios de aceptación verbatim:
  ```
  - Un fallo de la lectura del rango muestra un bloque de error con reintento y
    **no** el vacío de la agenda; el reintento recupera las citas sin recargar.
  - Abrir «Nueva cita» con un rol sin `manage.procedure_favorite` no produce 403
    ni promesas rechazadas (se omite la consulta de favoritos o se maneja).
  - El criterio de sesión «cero `pageerror`» se cumple también en el alta, no solo
    al cargar la página.
  ```
- **S7** — #397 «En móvil la vista semanal corta las columnas y los chips quedan
  en «1.»», criterios de aceptación verbatim:
  ```
  - A 390 px la agenda abre en una vista legible (día o lista) o la rejilla semanal
    ofrece desplazamiento horizontal con affordance; ninguna columna baja de un
    ancho que permita leer hora y paciente.
  - La cabecera de días no se corta en el borde.
  - Hay una prueba de navegador que mide el ancho de la columna y que el texto del
    chip no queda reducido a un carácter a 390 px.
  ```

## Estrategia

- Un commit por issue (T1–T6), con sus pruebas al lado del comportamiento, más
  un commit de documentación al cierre. La revisión nativa se corre por unidad
  de riesgo cuando el interruptor RDD esté activo.
- **Límite de slice declarado (T2, commit `d226ace0`)**: la unidad de #391
  reporta 1 711 líneas autoradas porque los siete pasos del asistente suben un
  nivel al montar sobre `UiModal` y Prettier reindenta el archivo completo; el
  cambio real es de ~170 líneas (`git diff -w`). El asistente es una sola
  superficie de diálogo y partirla dejaría el componente a medio migrar, mismo
  criterio que el instrumento de U3 en `audit-ui-u3.md`.
- **Límite de slice declarado (T1, commit `c09e5503`)**: la unidad de #392 suma
  843 líneas autoradas, de las que 410 son la migración + su prueba de siete
  casos; el comportamiento es una sola regla y separar prueba y esquema dejaría
  la mitad de la unidad sin verificar. Se entrega entera, como en U3.
- Las migraciones nuevas suben el conteo de `AGENTS.md` §4 y el guard
  `scripts/audit/doc-drift.mjs` debe quedar verde en cada commit.
- Suite con base de datos real (MySQL/MariaDB de XAMPP por socket, base
  `odontosuite_u3_test`):
  `DB_HOST=localhost DB_PORT=3306 DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_USERNAME=root DB_PASSWORD=<.env> DB_DATABASE=odontosuite_u3_test vendor/bin/phpunit -c phpunit.mysql.xml`.
- Frontend: `pnpm test:js`, `pnpm lint:check`, `pnpm format:check`,
  `pnpm build`.
- La verificación de navegador del instrumento `@U3` se intenta sobre la base
  `odontosuite_audit` ya sembrada; si el entorno no la permite, se declara como
  no disponible en el cierre y se sostienen las pruebas unitarias/de API.

## Tasks

- [ ] **T1** · S3 · `delegated` · #392: el índice único y el pre-chequeo
  declaran la misma regla (una cita cancelada libera su horario de profesional y
  de sillón), y la violación de unicidad se traduce a 422, nunca 500. Migración,
  pruebas de regresión por las dos puertas (crear y reprogramar) y conteo de
  migraciones de §4.
- [ ] **T2** · S2 · `delegated` · #391: el asistente de cierre monta sobre la
  primitiva de diálogo (rol, `aria-modal`, nombre accesible, foco atrapado,
  scroll bloqueado, Escape con guardia de contenido y pila compartida).
- [ ] **T3** · S4 · `delegated` · #394: cancelar pide confirmación con motivo
  obligatorio, lo guarda en la cita y el aviso nombra los recordatorios
  cancelados.
- [ ] **T4** · S5 · `delegated` · #395: filtros de profesional, sillón y sede en
  la agenda, `dental_chair_id` aplicado por la API y vacío propio del filtro.
- [ ] **T5** · S6 · `delegated` · #396: bloque de error con reintento en la
  agenda y alta sin 403 ni promesas rechazadas por favoritos.
- [ ] **T6** · S7 · `delegated` · #397: vista móvil legible (vista inicial y
  rejilla con ancho mínimo/desplazamiento), cabecera sin corte y prueba de
  ancho.
- [ ] **T7** · S1–S7 · `inline` · Cierre: `AGENTS.md` (§4/§12), esta nota,
  suite MySQL, Vitest, lint, formato, build, guard de deriva y verificación de
  navegador aplicable.

## Log

- **L1** — Pedido del usuario, verbatim: «haz las issues #391 #392 #394 #395
  #396 #397 en un worktree nuevo».
- **L2** — Contexto de origen: sesión de auditoría U3 (`odd/tasks/audit-ui-u3.md`,
  issue #366), que publicó los hallazgos #391–#397. #393 queda fuera de este
  encargo.
- **L3** — Medido al abrir esta sesión (`90c732be`): `CalendarPage.vue` (1 372
  líneas) no tiene estado de filtros ni bloque de error; `changeStatus` hace un
  `PATCH` directo para cualquier estado; `ConsultationWizard.vue` (1 131 líneas)
  conserva su `Teleport` con `z-[100]` propio; `AppointmentRepository::findConflicts`
  excluye `cancelled` mientras `unique_user_time_slot` y `unique_chair_time_slot`
  cubren todas las filas.
