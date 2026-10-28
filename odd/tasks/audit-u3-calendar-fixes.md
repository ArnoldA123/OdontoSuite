# Task: cierre de hallazgos U3 — agenda y asistente (#391/#392/#394/#395/#396/#397)

Status: OPEN. Rama `fix/audit-u3-calendar` en el worktree
`<ruta-local>` desde `90c732be`. Work units en commits
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

- [x] **T1** · S3 · `delegated` · #392: el índice único y el pre-chequeo
  declaran la misma regla (una cita cancelada libera su horario de profesional y
  de sillón), y la violación de unicidad se traduce a 422, nunca 500. Migración,
  pruebas de regresión por las dos puertas (crear y reprogramar) y conteo de
  migraciones de §4. Commit `c09e5503`; `OK (7 tests, 51 assertions)`.
- [x] **T2** · S2 · `delegated` · #391: el asistente de cierre monta sobre la
  primitiva de diálogo (rol, `aria-modal`, nombre accesible, foco atrapado,
  scroll bloqueado, Escape con guardia de contenido y pila compartida).
  Commit `d226ace0`; 20 pruebas del asistente y suite completa en verde.
- [x] **T3** · S4 · `delegated` · #394: cancelar pide confirmación con motivo
  obligatorio, lo guarda en la cita y el aviso nombra los recordatorios
  cancelados. Commits `bff4bba5` y `76cbbd31`; `OK (34 tests, 200 assertions)`
  en el clúster de citas.
- [x] **T4** · S5 · `delegated` · #395: filtros de profesional, sillón y sede en
  la agenda, `dental_chair_id` aplicado por la API y vacío propio del filtro.
  Backend en `aeb73981` (`OK (3 tests, 7 assertions)`); superficie en
  `e7ff8937` (52 pruebas del clúster de agenda).
- [x] **T5** · S6 · `delegated` · #396: bloque de error con reintento en la
  agenda y alta sin 403 ni promesas rechazadas por favoritos. Commit
  `31a67afd`; suite completa en 152 archivos / 1293 pruebas.
- [x] **T6** · S7 · `delegated` · #397: vista móvil legible (vista inicial y
  rejilla con ancho mínimo/desplazamiento), cabecera sin corte y prueba de
  ancho. Commit `7af36232`; 103 pruebas del clúster de agenda y la medición de
  navegador queda para T7.
- [x] **T7** · S1–S7 · `inline` · Cierre: `AGENTS.md` (§4/§12), esta nota,
  suite MySQL, Vitest, lint, formato, build, guard de deriva y verificación de
  navegador aplicable. Ver «Verificación» más abajo.

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
- **L4** — Entorno: `node_modules` enlazado al checkout principal funciona para
  Vitest, pero `vendor` enlazado hacía que PHPUnit ejecutara el código, las
  pruebas y las migraciones de `<ruta-local>`
  (`$baseDir` de Composer y `Application::inferBasePath()`). Se sustituyó por un
  `vendor` propio (`composer install`) y desde entonces el comando canónico
  valida el worktree. `pnpm` aborta por el enlace de `node_modules`
  (`ERR_PNPM_ABORTED_REMOVE_MODULES_DIR_NO_TTY`): se usan los binarios de
  `./node_modules/.bin`.
- **L5** — #392 verificado: RED 5 fallos + 1 error de fixture con el 500 y el
  `Duplicate entry … unique_chair_time_slot` registrados; GREEN
  `OK (7 tests, 51 assertions)`. La migración `slot_reservation` mueve la regla
  al esquema y las cuatro puertas traducen la violación de unicidad a 422.
- **L6** — #391 verificado: el asistente monta sobre `UiModal`; 8 casos nuevos
  del guardia de descarte, 20 pruebas del asistente y suite completa
  (150 archivos / 1290 pruebas) en verde.
- **L7** — #396 verificado: bloque `[data-calendar-error]` con reintento,
  favoritos consultados solo con `manage.procedure_favorite` y cada búsqueda
  liquidada por separado. Suite completa 152 archivos / 1293 pruebas.
- **L8** — #394: el motivo es obligatorio en las tres puertas (estado, edición
  y alta ya cancelada), se guarda en `appointments.cancellation_reason` y se
  limpia al reactivar; el aviso publica `meta.reminders_cancelled`. El clúster
  de citas quedó `OK (34 tests, 200 assertions)`.
- **L9** — #395 superficie: barra `[data-calendar-filters]` con profesional,
  sillón y sede, gobernada por los permisos de lectura de cada población,
  relectura del rango al cambiar de filtro y vacío propio del filtro. 52
  pruebas del clúster de agenda en verde; backend ya estaba en `aeb73981`.
- **L10** — #397: la vista inicial sale del ancho del viewport (día por debajo
  de 768 px) y la semana elegida a mano vive en scroll horizontal propio
  (64 px de hora + 112 px por día, piso de 848) con gradiente y aviso medidos.
  El chip separa hora y paciente en dos líneas.
- **L11** — Instrumentos corregidos para medir lo que la aceptación pide: la
  sonda del fondo del asistente reabre el diálogo que Escape cerró, y la
  medición móvil de la agenda registra la vista inicial y luego mide la semana
  a propósito (ancho de columna, contenedor con scroll y chip no colapsado).

## Verificación

- **Suite MySQL** (`vendor/bin/phpunit -c phpunit.mysql.xml` contra
  `odontosuite_u3_test` por socket): **OK (2799 tests, 17073 assertions)**.
- **Vitest**: `155 archivos / 1314 pruebas` en verde.
- **Build**: `vite build` en `8.34 s`, artefactos en `public/build`
  (gitignored).
- **Formato y estilo**: `pint --test` PASS (896 archivos), Prettier en verde,
  ESLint `0 errores` (2 avisos preexistentes en
  `tests/js/unit/domain-channels-private.test.js`).
- **Guard de deriva**: `node scripts/audit/doc-drift.mjs` → «All 14 doc-drift
  checks hold».
- **Guard de rutas personales**: `PersonalPathLeakGuardTest` en verde tras
  sustituir las rutas locales de esta nota por `<ruta-local>`.
- **Navegador** (base `odontosuite_audit` migrada a 155, app en `:8001` y Vite
  con `VITE_APP_URL=http://127.0.0.1:8001`):
  `@U3 wizard · contrato` **OK** (diez filas de la primitiva verdes, puerta
  `my-day` porque la rejilla sólo pinta de 06:00 a 21:00 y la sesión corrió de
  noche), `@U3 wizard · pila` **OK** (Escape cierra sólo el modal superior,
  scroll bloqueado y foco de vuelta en el asistente), `@U3 calendar · filtros`
  **OK** (tres controles y textos), `@U3 calendar · error de carga` **OK**
  (bloque + reintento, vacío ausente, 15 chips al recuperar),
  `@U3 calendar · cancelar cita` **OK** (diálogo, motivo obligatorio, PATCH con
  `cancellation_reason`, `meta.reminders_cancelled: 0`, motivo guardado y aviso),
  `render for administrador|odontologo` **OK** con la aserción nueva a 390 px
  (columna 112 px, contenedor 848/306, hora del chip sin recortar y documento
  sin desborde).
- **NO PROBADO en navegador**: la reprogramación por drag sobre un horario
  liberado (`@U3 calendar · reprogramar por drag` se salta a las 21:55 porque no
  queda franja futura en la rejilla del día). La regla de #392 la fijan las siete
  pruebas de API, incluidas las dos puertas.

## Revisión nativa (RDD)

- Interruptor encendido (`global: on`). `assess` sobre el rango comprometido
  `90c732be..HEAD` (28 archivos, 1870 líneas): **risk medium**, `reviewDue:
  false` (`under_budget`), `outcome_source: unknown`, plan de **autoverificación
  del escritor** sin verificador independiente. No se inicia linaje: la regla
  de entrada sólo pide `inspect` cuando la revisión es debida o la pide el
  usuario. La verificación funcional (suite completa y sondas de navegador)
  corrió por su cuenta y no se sustituye por este dictamen.

## Verificación independiente

- Un verificador de solo lectura recorrió criterios, código y suites sobre la
  rama: **PASS** en las seis issues, con `OK (2799 tests, 17073 assertions)` en
  MySQL, `155 archivos / 1314 pruebas` en Vitest, Pint (896 archivos), ESLint
  (0 errores), Prettier y los 14 chequeos de deriva. Leyó los riesgos
  transversales (escritores de `appointments`, reactivación contra el índice
  único, exposición del recurso y descarte silencioso en el alta) sin hallazgos
  bloqueantes.
- Dos cierres posteriores al dictamen: `cancelAppointment()`, la puerta heredada
  sin ruta HTTP, escribía el motivo en `notes` —ahora usa la columna— y la sonda
  de cancelación conserva el motivo del salto en su evidencia (su `finally` la
  sobrescribía). Suite final: `OK (2799 tests, 17073 assertions)`.
- Prueba de navegador dirigida sobre la API del worktree (cita creada por API,
  agenda abierta, cancelación por la interfaz): diálogo con `role="dialog"` y
  `aria-modal="true"`, envío vacío bloqueado, **cero** PATCH antes del motivo,
  PATCH 200 con `cancellation_reason`, `meta.reminders_cancelled: 0`, estado y
  motivo guardados en la base de auditoría, y aviso «Cita cancelada».
