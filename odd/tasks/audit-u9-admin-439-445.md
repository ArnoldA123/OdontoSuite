# Auditoría de administración U9 — #439, #440, #441, #442, #443, #444 y #445

Siete hallazgos de la auditoría UI U9 (#372, plan #13) sobre el escritorio de
administración. Rama `fix/audit-u9-admin-439-445`, worktree
`../odontosuite-u9-audit`, base `main` @ `5e171e61`. Un commit de unidad por
issue.

## Specs

S1. #439: «El comando de Evidencia deja las filas negativas con
`denial.writeAffordances: []`, y la puerta de escritura de cada superficie sigue
respondiendo 403 con el mismo token.» Medido hoy con rol `recepcionista`:
`/specialties` («Nueva especialidad», «Editar»), `/professionals` («Nuevo
Profesional»), `/appointment-types` («Nuevo tipo») y su detalle («Editar»),
`/environments` («Nuevo ambiente»), `/settings/branches` («Nueva sucursal») y
`/settings/payment-methods` («Nuevo método») pintan la acción de escritura y la
API responde 403.

S2. #439: cada control se deriva de `usePermissions()` con la misma clave que
exige su ruta (`manage.specialty`, `manage.user`, `manage.appointment_type`,
`manage.dental_chair`, `manage.branch`, `manage.payment_method`). No se toca la
API, la matriz de permisos ni las guardas de router.

S3. #440: «Con el formulario vacío, cada campo obligatorio muestra su mensaje
visible, el botón deshabilitado declara qué falta y ningún rechazo queda sólo en
un toast.» Medido hoy: ambientes (`/api/dental-chairs` 422 con `errors.name`,
cero mensajes en el campo y toast «Error al crear el ambiente»), tipos de cita
(envío vacío → validación nativa del navegador, sin mensaje escrito y sin
`POST`) y traslados de sede (motivo vacío, «Enviar solicitud» deshabilitado sin
explicar qué falta; la API responde 422
`El motivo del traslado es requerido.`).

S4. #441: «Forzando un 500 en `/api/users`, la lista muestra un bloque de error
con «Reintentar», oculta «Sin profesionales registrados» y el reintento
recupera las filas.» Medido hoy: toast y «Sin profesionales registrados», con
cero botones «Reintentar».

S5. #442: «La fila que la API protege no ofrece «Eliminar», o al pulsarlo el
usuario lee el mensaje de la API; la cuenta creada por la sesión se sigue
borrando (200) y desaparece de la lista.» Medido hoy: `DELETE 409` con
«…tiene 11 citas, 23 movimientos de caja, 4 sesiones de caja y 43 transacciones.
Desactívalo en su lugar.» sustituido por el toast «Error al eliminar el
profesional».

S6. #443: «Desde el detalle, «Editar» abre el formulario precargado con ese tipo
y, al cerrarlo, la lista queda visible sin perder el contexto.» Medido hoy:
`Editar` navega a `/appointment-types` con el formulario cerrado y el nombre
«—».

S7. #444: «Lo que el formulario permite fijar y lo que la API publica
coinciden, o el control declara que el valor es derivado y de dónde sale.»
Medido hoy: se elige «Requiere materiales: Sí», el `PUT` responde 200 y la
respuesta y la ficha dicen «No», porque `AppointmentType` publica el valor
derivado de la receta del procedimiento por defecto (#295).

S8. #445: «La lista distingue los métodos que exigen referencia, permite aislar
una familia y su tira de KPI no cae a cero por efecto de un filtro.» Medido hoy:
la tabla publica 7 columnas sin `requires_reference`, no hay filtro por `type` y
con un filtro sin coincidencias los KPI publican `TOTAL 0 · ACTIVOS 0 ·
INACTIVOS 0 · DEL SISTEMA 0`.

S9. Cada issue trae su prueba Vitest y las suites existentes siguen en verde;
`AGENTS.md` §4/§5 no cambian (no hay rutas, migraciones, modelos ni primitivas
nuevas) y §12 recibe su entrada.

## Tasks

- T1 — #439: derivar de `usePermissions()` los controles de escritura de
  `/specialties`, `/professionals`, `/appointment-types` (+ detalle),
  `/environments`, `/settings/branches` y `/settings/payment-methods`.
  Spec: S1-S2. Commits `63d7234c` (claves), `06aeb1ac` (especialidades),
  `0147a92e` (sucursales), `90c1102e` (métodos de pago), `e5e54dba`
  (ambientes), `fcf7c6a5` (profesionales).
- T2 — #440: mensajes de campo y motivo del botón deshabilitado en ambientes,
  tipos de cita y traslados de sede. Spec: S3. Commit `fix(forms): …`.
- T3 — #441: separar carga, error y vacío en la lista de profesionales.
  Spec: S4. Commit `fcf7c6a5`.
- T4 — #442: el rechazo del borrado de un profesional llega con el motivo de la
  API. Spec: S5. Commit `fcf7c6a5`.
- T5 — #443: el alta del detalle de tipo de cita abre el formulario sin remontar
  la vista. Spec: S6. Commit `fix(appointment-types): …`.
- T6 — #444: el formulario de tipo de cita deja de ofrecer la bandera derivada.
  Spec: S7. Commit `fix(appointment-types): …`.
- T7 — #445: columna de referencia, filtro de familia y KPI sobre la población
  sin filtros en métodos de pago. Spec: S8. Commit `90c1102e`.
- T8 — Sincronizar `AGENTS.md` §12 y correr la verificación completa (Vitest,
  ESLint, Prettier, `doc-drift`). Spec: S9. Commit `docs: …`.

## Log

L1. «haz las issues #439 #440 #441 #442 #443 #444 #445 en un worktree nuevo»

L2. Rutas del worktree y base: el primer worktree se creó en
`../odontosuite-u9-admin` sobre `main` @ `46e05b8b`, pero otro proceso del
entorno lo eliminó (junto con su rama) a los pocos minutos, mientras cuatro
sesiones Pi hermanas seguían activas en otros worktrees. Se recreó en
`../odontosuite-u9-audit` (ruta distinta, rama `fix/audit-u9-admin-439-445`
reutilizada) sobre `main` @ `5e171e61`, ya con `session_worktree_register` para
que la sesión lo reconozca, y `node_modules`, `vendor` y `.env` enlazados desde
el repo principal.

L3. Decisión de alcance por issue: #439 deriva cada acción de `can.*` (las dos
claves nuevas `manageSpecialties` y `managePaymentMethods` se añaden a
`usePermissions.js`); #440 enlaza `errors.*` al campo y publica una ayuda junto
al botón deshabilitado de traslados; #441 añade `loadError` + «Reintentar» a la
lista de profesionales; #442 deja que el 409 llegue con su `message` en vez de
un texto fijo; #443 usa `meta.preserveOnQuery` en `/appointment-types` (el mismo
opt-out por ruta que ya usan `/patients` y `/procedure-catalog`, sin tocar la
clave global de `router-view`); #444 retira el interruptor de las dos modales y
declara el valor derivado como dato de sólo lectura; #445 añade la columna, el
filtro de familia cliente y carga la población sin filtros para los KPI.

L4. Corte de entrega y commits. La rama acumula ocho commits de unidad, uno por
superficie más el andamiaje compartido (`63d7234c` claves de permiso,
`a8b15c94` este documento). El primer corte de revisión queda en #439 (las seis
superficies ocultando la escritura que la API niega); el segundo en los estados
y formularios de #440, #441 y #442; el tercero en el catálogo de tipos de cita
(#443, #444) y en el directorio de métodos de pago (#445). Con más de 400 líneas
autoradas, la entrega se propone como cadenas de PR por corte, no como un único
PR.
