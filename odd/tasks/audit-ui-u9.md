# Task: U9 — Administración y configuración (auditoría UI, plan #13)

Status: IN PROGRESS. Rama `feat/audit-ui-u9` desde `174eb90` (`main`). No se toca
código de producto: la sesión mide y documenta. La ejecución pesada del
instrumento corre en un job de CI (`.github/workflows/audit-ui.yml`) y su
evidencia se descarga como artefacto.

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

- [ ] **T1** · S2, S10, S12 · `inline` · Precondiciones: rama
  `feat/audit-ui-u9`; base `odontosuite_audit` recreada y sembrada; capa 1 →
  `manifest.json` con 12 superficies, 82 acciones (0 sin clasificar), 7 diálogos,
  24 endpoints y 0 permisos sin resolver. Reconocimiento de contrato local de los
  ocho ABM (login por rol, puertas de escritura y negativas).
- [ ] **T2** · S1, S3–S9, S12 · `delegated` · Instrumento `@U9` en
  `tests/e2e/audit/u9/`: fixtures propios, render por rol, contrato de
  primitivas, caminatas de los ocho ABM, matriz `rol × endpoint` e informe.
- [ ] **T3** · S2, S5, S12 · `inline` · Corrida definitiva en CI sobre base
  limpia: suite `@U9` en verde, gate OK, 0 `pageerror`, evidencia descargada.
- [ ] **T4** · S1, S5, S8–S11 · `inline` · Triage por clase raíz, 3–7 issues
  publicados, `walkthrough.md`, `ux-rubric.md`, `api-matrix.json`,
  `findings.md` y galería.
- [ ] **T5** · S5, S10, S11 · `inline` · Cierre: Prettier/ESLint en verde,
  `playwright test --list`, commits por corte, nota ODD y PR.

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

## Pendiente de medir

- Contrato de diálogos de los siete modales del manifiesto.
- Caminatas de los ocho ABM con datos propios (alta, envío vacío, edición,
  desactivación, detalle y tabs).
- Matriz `rol × endpoint` de los 24 endpoints.
- Rúbrica por superficie.
