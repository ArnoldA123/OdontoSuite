# Task: U9 — Administración y configuración (auditoría UI, plan #13)

Status: DONE (sesión de auditoría). Rama `feat/audit-ui-u9` desde `174eb90`
(`main`); PR **#446** abierto, a decisión del dueño. No se toca código de producto: la
sesión mide y documenta. La ejecución pesada del instrumento corre en un job de
CI (`.github/workflows/audit-ui.yml`) y su evidencia se descarga como artefacto.
Hallazgos publicados **#439–#445**.

## Estrategia de entrega

El presupuesto de ~400 líneas aplica a correcciones, no a un instrumento nuevo
completo: un corte sólo es ejecutable cuando su capa y sus fixtures están
completos. Mismo criterio declarado en U0–U8. La entrega va en cortes revisables:
**1** workflow de CI y nota; **2** instrumento de capas 1–3 y 5 (helpers,
fixtures, render, primitivas, matriz); **3** caminatas de los ocho ABM;
**4** informe, rúbrica, triage e issues. El merge y la publicación quedan a
decisión del dueño.

## Specs

- **S1** — Objetivo (issue #372, verbatim): «Verificar los ocho ABM del escritorio
  de administración y que ninguna superficie ofrece una acción que la API
  prohíbe (borrar una sucursal, cambiar el código de una especialidad, decidir el
  propio traslado). Medido en `8b72d37`: el escritorio suma 11 páginas;
  `ProfessionalsPage` y `AppointmentTypesPage` usan `<table>` propio y
  `BranchDetailPage`/`ProfessionalDetailPage` no tienen placeholder de carga.»
- **S2** — Método (issue #372, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=professionals,environments,appointment-types,specialties,settings,branch-transfers,accounting-locks --out=.atl/qa-evidence/audit/ui/U9/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U9
  ```
- **S3** — Superficies, roles y acciones (issue #372, verbatim): «Superficies:
  `/professionals` y `/:id`, `/environments` y `/:id`, `/appointment-types` y
  `/:id`, `/specialties`, `/settings/branches` y `/:id`,
  `/settings/payment-methods`, `/settings/accounting-locks` y
  `/branch-transfers`. Roles: administrador; para traslados, además, un rol que
  solicita y gerencia que decide. Acciones: por cada ABM, listar, filtrar, crear,
  enviar vacío, editar, desactivar si no hay borrado y abrir detalle; especialidad
  con `code` inmutable; sucursal sin eliminar; método de pago con familia,
  referencia y pasarela; bloqueo contable fijado y retirado con fecha efectiva y
  origen; traslado solicitado, cancelado, aprobado, rechazado y el intento de
  decidir el propio.»
- **S4** — Entregable (issue #372, verbatim): «`render.json`, `primitives.json`,
  `walkthrough.md` por ABM, `api-matrix.json`, `ux-rubric.md` y
  `odd/tasks/audit-ui-u9.md`.»
- **S5** — Criterio de salida (issue #372, verbatim): «Los ocho ABM se completan
  con datos propios sin callejón; ninguna acción destructiva o inmutable se
  ofrece donde la API la prohíbe; el 409 de solicitud abierta y el 403 de
  decisión propia se explican en pantalla; cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada (por ejemplo, la propia prueba de
  403)»; «Longitud de contenido renderizado por encima del umbral del módulo»;
  «Captura `desktop 1440×900` y `mobile 390×844`, **siempre y explícita**»;
  «Sin scroll horizontal de documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): «Centrado horizontal
  (`|box.x + box.width/2 − viewportWidth/2| < 8`)»; «Un solo `h1`»; «Escape
  cierra»; «Foco atrapado»; «Scroll bloqueado»; «Etiqueta accesible». «Un
  diálogo que no cumple **una** fila es hallazgo, no ruido: es la clase
  "primitiva mal usada".»
- **S8** — Plan #13 §4 capa 4 (verbatim): los veredictos `OK`, `DEFECTO`,
  `NO-OP`, `CONFUSO`, `NO PROBADO`; «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»
- **S9** — Plan #13 §4 capa 5 y 6, §5 (verbatim): «Para los endpoints que la
  sesión consume: matriz `rol × endpoint` con el status esperado derivado de
  `App\Authorization\Permissions` y el observado con un token real»; «Doce
  criterios, 0/1/2 cada uno, por superficie primaria y una vez por sesión para
  su conjunto».
- **S10** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo; `.env` intacto»;
  «`.atl/` está en `.gitignore` (verificado), así que las capturas y JSON **no se
  commitean**»; «La sesión que audita no escribe código de producto».
- **S11** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».
- **S12** — Operación en CI (instrucción del dueño, L1): la corrida de navegador
  se ejecuta como job de GitHub Actions para no bloquear la sesión; la evidencia
  (`render.json`, `primitives.json`, capturas, caminatas, rúbrica) viaja como
  artefacto descargable. La matriz de contrato y el reconocimiento de API pueden
  correr localmente contra `odontosuite_audit`.

## Tasks

- [x] **T1** · S2, S10, S12 · `inline` · Precondiciones: rama
  `feat/audit-ui-u9`; base `odontosuite_audit` recreada y sembrada; capa 1 →
  `manifest.json` con 12 superficies, 82 acciones (0 sin clasificar), 7 diálogos,
  24 endpoints y 0 permisos sin resolver. Reconocimiento de contrato local de los
  ocho ABM (login por rol, puertas de escritura y negativas).
- [x] **T2** · S1, S3–S9, S12 · `delegated` · Instrumento `@U9` en
  `tests/e2e/audit/u9/` (13 módulos): fixtures propios, render por rol, contrato
  de primitivas, ocho caminatas, matriz `rol × endpoint` e informe. Delegado en
  tres cortes y verificado por reejecución propia.
- [x] **T3** · S2, S5, S12 · `inline` · Corrida en CI: workflow
  `.github/workflows/audit-ui.yml` verde en el corte A (12/12 superficies, 0
  `pageerror`, 21 diálogos, 188/188 filas) y corrida definitiva `40447188113`
  (8m26s, verde) con las 48 filas de la sesión y el informe: `gate.json` ok,
  12/12 superficies, 0 `pageerror`, 81/81 acciones, 18 filas crudas.
- [x] **T4** · S1, S5, S8–S11 · `inline` · Triage por clase raíz y **siete
  issues publicados (#439–#445)**; `walkthrough.md`, `ux-rubric.md` (156/192 y
  16/24), `findings.raw.json` (18 filas → 7 clases), `gate.json` y galería.
- [x] **T5** · S5, S10, S11 · `inline` · Cierre: Prettier y ESLint en verde,
  `playwright test --list` → 48 tests, commits por corte, esta nota y PR #446.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 372-U9» y, al arrancar la
  sesión: «podes hacerlo ci nun job dado que eso que limita y si no da resultadop
  parecee que te quedaste esperando y no hay nada devuelta» (S12: la corrida de
  navegador se ejecuta en CI y la evidencia vuelve como artefacto).
- **L2** — Cuerpo de #372 (S1–S5 verbatim), plan #13 §4 (capas 2–6), §5
  (rúbrica), §6 (anatomía y evidencia), §8 (protocolo de issues) y §9
  (guardarraíles).
- **L3** — Estado al abrir (`174eb90`, `main` limpio): 12 superficies medidas por
  la capa 1 para los siete módulos del alcance, 82 acciones (0 sin clasificar),
  7 diálogos, 24 endpoints, 0 permisos sin resolver; 2 rutas `excluded` por
  `redirect`.
- **L4** — Reconocimiento de contrato (base `odontosuite_audit` sembrada, app
  local `127.0.0.1:8000`): usuarios (`apiResource`; borrado real sólo si la
  cuenta no tiene historia), ambientes (`destroy` 409 con citas/bloqueos),
  tipos de cita (CRUD completo), especialidades (`PUT` ignora `code`; `DELETE`
  405), sucursales (`DELETE` permitido sin referencias; la superficie no lo
  ofrece), métodos de pago (`canDelete` depende de `is_system` y
  `transactions_count`; el sistema responde 403 con mensaje), bloqueo contable
  (`PUT`/`DELETE` por módulo; fecha futura aceptada), traslados (409 con
  solicitud abierta, 403 al decidir la propia, 404 en lectura ajena).
- **L5** — Desviaciones estáticas a confirmar en la caminata:
  `ProfessionalsPage` ofrece «Eliminar» en todas las filas sin conocer la
  historia de la cuenta; `EnvironmentsPage` ofrece «Eliminar» sin conocer citas
  o bloqueos; `PaymentMethodsPage` ya oculta el borrado con `canDelete`.


## Línea base medida

- Revisión medida: `390e339` sobre `174eb90`; árbol limpio salvo el instrumento y
  esta nota. Base `odontosuite_audit` recreada con `migrate --seed` (15 usuarios,
  3 sedes, 9 ambientes, 9 tipos de cita, 8 especialidades, métodos del sistema).
- **Capa 1** · 12 superficies, **82 acciones (0 sin clasificar)**, **7 diálogos**,
  **24 endpoints**, **0 permisos sin resolver**; 2 rutas `excluded` por
  `redirect`.
- **Capas 2–3** · 24 filas de render (12 superficies × sus roles), **0
  `pageerror`**, **0 scroll horizontal a 390 px**, una sola `h1` en 23 de 24
  filas (la excepción es un estado 403 de detalle); **21 diálogos** con **0
  fallas de contrato** (centrado, docking, `h1` única, foco atrapado, scroll
  bloqueado, nombre accesible y Escape).
- **Capa 4** · 8 caminatas, **81 acciones clasificadas: 69 OK / 8 DEFECTO / 3
  CONFUSO / 1 NO PROBADO**, 0 `pageerror`, 2 respuestas ≥ 400 declaradas (el 409
  de la solicitud abierta y un 500 provocado).
- **Capa 5** · **188 filas sobre 47 definiciones endpoint × método** y 4 roles,
  **188/188 conformes**, 0 desvíos; 9 hechos de contenido comprobados (código de
  especialidad inmutable, borrado propio 200, métodos del sistema marcados,
  aprobación y rechazo de traslados consumidos, decisión propia 403, retiro
  propio 200, segunda solicitud abierta 409, permisos del login iguales al
  catálogo).
- **Rúbrica** · profesionales 12/24, ambientes 22/24, tipos de cita 15/24,
  especialidades 22/24, sucursales 21/24, métodos de pago 20/24, bloqueo
  contable 23/24, traslados 21/24; sesión **16/24** (`ux-rubric.md`).
- **Gate de sesión** · `gate.json` → `ok: true`, 0 motivos, 0 `pageerror`, 0
  solicitudes inesperadas, 81/81 acciones clasificadas, 21/21 diálogos probados.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F01 | profesionales | «Eliminar» sobre una cuenta con historia | la acción no se ofrece, o el motivo de la API se lee | DELETE 409 con motivo accionable → toast genérico; la fila sigue | 4 | MEDIDO | `crit:media` | `ProfessionalsPage.vue:821-834` | #442 |
| F02 | profesionales | error 500 al cargar | bloque de error con reintento | se pinta «Sin profesionales registrados», 0 «Reintentar» | 4 | MEDIDO | `crit:media` | `ProfessionalsPage.vue:748` | #441 |
| F03 | ambientes | alta con el formulario vacío | mensaje en el campo | 422 con `errors.name`, 0 mensajes en el campo, toast | 4 | MEDIDO | `crit:media` | `EnvironmentsPage.vue:615` | #440 |
| F04 | tipos de cita | alta con el formulario vacío | mensaje en el campo | validación nativa, 0 mensajes escritos, 0 POST | 4 | MEDIDO | `crit:media` | `AppointmentTypesPage.vue` (`createType`) | #440 |
| F05 | tipos de cita › detalle | pulsar «Editar» | abre el formulario precargado | vuelve a la lista con el modal cerrado y «—» | 4 | MEDIDO | `crit:media` | `AppointmentTypeDetailPage.vue:491`; `app.js:323` | #443 |
| F06 | tipos de cita › edición | marcar «Requiere materiales» y guardar | lo guardado es lo publicado | PUT 200; la API publica `false` y la ficha pinta «No» | 4 | MEDIDO | `crit:media` | `AppointmentType.php:78` | #444 |
| F07 | sucursales | filtrar por estado y limpiar | el control dice la población aplicada | el selector pinta «Seleccionar...» con «Todas» aplicado | 4 | MEDIDO | `crit:baja` | `BranchesPage.vue` (`UiSelect` sin etiqueta) | backlog §8.4 |
| F08 | sucursales | volver con el navegador | el filtro sigue legible | mismo rótulo «Seleccionar...» | 4 | MEDIDO | `crit:baja` | el mismo que F07 | backlog §8.4 |
| F09 | métodos de pago | abrir la lista | publica la referencia que el ABM administra | 7 columnas; 0 marcas de referencia | 4 | MEDIDO | `crit:media` | `PaymentMethodsPage.vue:353` | #445 |
| F10 | métodos de pago | filtrar por familia y estado | filtro de familia y KPI de la población | no hay filtro de familia; los KPI caen a 0 con el filtro | 4 | MEDIDO | `crit:media` | `PaymentMethodsPage.vue:368-396` | #445 |
| F11 | traslados | enviar sin motivo | el rechazo se explica en el formulario | botón apagado sin motivo; la API responde 422 con el texto | 4 | MEDIDO | `crit:media` | `BranchTransfersPage.vue:420` | #440 |
| F12 | tipos de cita › detalle (recepción) | renderizar como rol excluido | sin controles de escritura | «Editar» visible; `POST`/`PUT` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F13 | tipos de cita (recepción) | renderizar como rol excluido | sin controles de escritura | «Nuevo tipo» visible; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F14 | ambientes (recepción) | renderizar como rol excluido | sin controles de escritura | «Nuevo ambiente» visible; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F15 | profesionales (recepción) | renderizar como rol excluido | sin controles de escritura | «Nuevo Profesional» visible; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F16 | sucursales (recepción) | renderizar como rol excluido | sin controles de escritura | «Nueva sucursal» visible; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F17 | métodos de pago (recepción) | renderizar como rol excluido | sin controles de escritura | «Nuevo método» visible; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |
| F18 | especialidades (recepción) | renderizar como rol excluido | sin controles de escritura | «Nueva especialidad» y «Editar» visibles; `POST` 403 | 2 | MEDIDO | `crit:media` | sin `usePermissions` | #439 |

### Backlog del programa (§8.4)

- **Rótulo del selector de estado de sucursales** (F07/F08, `crit:baja`): el
  control no publica la población aplicada; se revisa en U11 junto con el resto
  de la consistencia de `UiSelect`.
- **Estado 403 de detalle sin `h1`** (`crit:baja`): `/appointment-types/:id` como
  rol excluido pinta su bloque de error sin encabezado; única fila de 24 sin un
  solo `h1`.
- **Sobre del 403 sin el motivo de dominio** (`crit:baja`, `area:backend`): la
  decisión propia de un traslado devuelve `message` genérico y el texto de
  dominio sólo en `error`; hoy la pantalla oculta el control, así que no hay
  ruta de usuario que lo muestre.

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Los ocho ABM se completan con datos propios sin callejón | **No cumple**: 8 `DEFECTO` y 3 `CONFUSO` en 81 acciones; callejón medido en «Editar» del detalle (#443) y en el alta de traslados sin motivo (#440) |
| Ninguna acción destructiva o inmutable se ofrece donde la API la prohíbe | **No cumple en destructivo**: siete filas negativas con escrituras y puerta 403 (#439) y «Eliminar» sobre cuentas protegidas (#442). **Cumple en inmutable**: el código de especialidad no se edita y su `PUT` lo ignora; la sucursal no ofrece borrado en lista ni detalle |
| El 409 de solicitud abierta y el 403 de decisión propia se explican en pantalla | **Parcial**: el 409 sí (`06-second-open`, con su mensaje); el 403 propio no tiene control en pantalla (paso `NO PROBADO`) y el sobre devuelve el texto genérico |
| Cero `pageerror` | **Cumple**: 0 en las 24 filas de render, los 21 diálogos y las 8 caminatas |
| Rúbrica puntuada | **Cumple**: 156/192 en las ocho superficies y 16/24 en la sesión (`ux-rubric.md`) |

## Revisión nativa (RDD)

**Sin veredicto: el proveedor no produjo autoridad y no hay nada que consumir.**

| Paso | Respuesta |
| --- | --- |
| `assess`/`inspect` inicial | `ready`, `action: start`, candidato `base-diff` `174eb90..HEAD` con 17 archivos. |
| `start` del candidato completo (3 intentos, todos nuevos) | `blocked` en `preflight`, `invalid_request`: el inventario de archivos no rastreados cambiaba mientras el instrumento se escribía; `mutation_outcome: none`, sin linaje ni autoridad. |
| `inspect` + `start` con el árbol limpio | `blocked` en `preflight`, `lens_context_budget_exceeded`: «retrying this exact candidate cannot succeed; split it into a chained sequence of smaller reviewable commits». |
| `start` del candidato reducido (`0391b54..HEAD`, 4 archivos, 1 980 líneas, sin lentes opcionales) | `created`, linaje `review-8511e71d8528d3ab`, lente `review-reliability`, presupuesto de corrección 200 líneas. |
| `capture` del revisor | Primero `pi-failed` («Stream ended without finish_reason», 104 s); el `STATUS` volvió a ofrecer el mismo slot; el segundo intento terminó en `unachievable_lens_slot`: «killed after 980315ms against a 980299ms relay bound» sobre un prompt materializado de 93 555 bytes. |

Consecuencia: no hay aprobación, corrección ni autoridad consumida; el
proveedor declaró el slot no alcanzable y el `STATUS` detuvo la revisión. Se
descartó reintentar el mismo alcance (el proveedor declara que no puede
prosperar) y reducir otra vez habría repetido un bloqueo silencioso de más de
15 minutos. La verificación independiente la ejecutó el padre sobre el mismo
árbol: reproducción de dos caminatas, lectura de la evidencia cruda, `gate.json`
y la corrida de CI como evidencia externa. Queda declarado como verificación
externa no obtenida.

## Delegación

Instrumento delegado a `gentle-ai-worker` en tres cortes (tasks
`mvppvkw3-1-cbna`, `mvprk8ip-2-ugo9`, `mvpxo0w0-3-bik8`, `mvq120iz-4-liy1`):
capas 1–3 y 5; caminatas clínicas; caminatas de configuración; traslados e
informe. El padre verificó el registro en `surface.spec.js`, reejecutó
`fixtures + walk professionals`, `fixtures + walk sucursales` y
`fixtures + walk traslados + informe`, leyó la evidencia cruda y comprobó que la
base de desarrollo (`odontosuite`) tuviera **0** filas de la sesión: el servidor
corre con `DB_DATABASE=odontosuite_audit`.

## Declarado como no observable

1. La decisión propia de un traslado **desde la pantalla**: la lista filtra la
   solicitud propia de «Solicitudes por decidir» y sólo le ofrece «Cancelar», así
   que el 403 sólo es medible contra la API (paso `10-self-decision`, `NO
   PROBADO`). Además, el cuerpo del 403 devuelve el sobre genérico y el motivo de
   dominio sólo viaja en `error` (debug).
2. El 409 de borrado de un **tipo de cita** con citas: la semilla no tiene tipos
   con historial, así que el guard se mide en usuarios y ambientes, no ahí.
3. El detalle de **métodos de pago**: no existe superficie de detalle; se
   registra como hecho, no como defecto.
4. La densidad de texto por superficie no tuvo medición dedicada: la rúbrica la
   apoya en el contenido renderizado y las capturas, y lo declara en cada fila.

## Residuos de datos

La sesión escribió sólo en `odontosuite_audit`. Los fixtures se retiran en su
`afterAll` (usuarios, ambientes, tipos, sucursales, métodos y solicitudes) y cada
caminata borra lo que crea; la cuenta de la caminata de traslados se retira en su
`finally`. La única fila que no puede retirarse es la **especialidad**, porque
`DELETE /api/specialties/{id}` responde 405: queda declarada como inactiva una
`AUDIT-U9-*` por corrida. El bloqueo contable se deja retirado (`source: none`) y
la matriz usa únicamente ids de la sesión. La corrida de CI usa una base nueva y
desechable.

- **L6** — Ejecución en CI (S12): `.github/workflows/audit-ui.yml` sirve la app
  con `pnpm build` y una base MySQL de servicio, siembra `odontosuite_audit` y
  sube la evidencia como artefacto. Dos corridas verdes: la del corte A (12/12
  superficies, 0 `pageerror`, 21 diálogos, 188/188 filas de matriz) y la del
  instrumento completo. El servidor local de la sesión corre con
  `DB_DATABASE=odontosuite_audit` (leído del proceso) y la base de desarrollo
  `odontosuite` terminó con **0** filas `AUDIT-U9-*`.
- **L7** — Revisión nativa: el candidato completo no se pudo congelar mientras el
  instrumento se escribía (tres `start` con `invalid_request` por inventario de no
  rastreados; `mutation_outcome: none`), después se rechazó por
  `lens_context_budget_exceeded`, y el candidato reducido (`0391b54..HEAD`) creó
  linaje pero su único revisor quedó **no alcanzable** por el tope del relay
  (980 s sobre un prompt de 93 555 bytes). Sin aprobación ni autoridad consumida;
  se declara como verificación externa no obtenida.
- **L8** — Verificación propia: se reejecutaron `fixtures + walk profesionales`,
  `fixtures + walk sucursales` y `fixtures + walk traslados + informe` antes de
  cada commit, se leyó la evidencia cruda de los 18 hallazgos y se comprobó el
  `gate.json` (0 `pageerror`, 0 solicitudes inesperadas, 81/81 acciones
  clasificadas).
- **L9** — Triage por clase raíz: permisos de escritura (#439), validación sin
  mensaje en el campo (#440), error de carga como vacío (#441), borrado sobre
  cuentas protegidas (#442), «Editar» del detalle (#443), bandera derivada
  (#444) y lista de métodos de pago (#445). Tres observaciones de `crit:baja`
  quedaron en backlog §8.4.

- **L10** — Corrida definitiva `40447188113` (job `Audit UI`): **verde, 8m26s**.
  El artefacto confirma sobre base limpia lo medido en local: `gate.json` con
  `ok: true` y 0 motivos, 12/12 superficies, 0 `pageerror`, 0 solicitudes
  inesperadas, 21/21 diálogos probados con 0 fallas de contrato, 81/81 acciones
  clasificadas (69 OK / 8 DEFECTO / 3 CONFUSO / 1 NO PROBADO), 18 filas en
  `findings.raw.json` y 8 secciones en `walkthrough.md`.
- **L11** — PR **#446** abierto con `Closes #372` (misma instrucción del dueño que
  en U8: cerrar el issue de sesión al abrir el PR). La sesión no publica ni
  fusiona nada más: el merge y el cierre quedan a decisión del dueño.
