# Auditoría/Pacientes — #399 #400 #401 #403 #404

Rama `fix/audit-u4-patients`, worktree `.worktrees/audit-u4-patients`.
Cinco hallazgos de la auditoría UI U4 (#367) sobre `/patients` y `/patients/:id`.
Una rama, un commit de unidad por issue, más el del instrumento.

## Specs

### #399 — tabs de la ficha que el rol no puede leer

- S1: «Recepción y finanzas no ven «Planes» ni «Historia Clínica», o las ven sin
  pedir su endpoint: cero 403 de `treatment-plans`/`medical-records` al abrir la
  ficha.»
- S2: ««Historial» sólo aparece con `view.audit_log`; sin el permiso no hay
  petición ni vacío, y con el permiso un 403/500 se pinta como error con
  reintento.»

### #400 — tarjetas fantasma de Especialidades

- S5: «Con cero registros, la tab muestra su estado vacío (`empty-specialties`) y
  **cero** tarjetas.»
- S6: «Con registros, una tarjeta por registro con su `id` y su tipo, y
  «Ver»/«Editar» navegan a una ruta real.»
- S7: «`detail-tabs.json` deja `specialties.state === 'empty'` en el paciente sin
  registros y `content` con el número de filas igual a `meta.total` cuando los
  hay.»

### #401 — filtros de la lista y nombre accesible

- S8: «Volver de la ficha a la lista devuelve la misma búsqueda, estado, orden,
  tamaño de página y página (query en la URL o el `keep-alive` selectivo que el
  canon permite), y la lista muestra los mismos resultados que antes de entrar.»
- S9: «El botón de limpiar filtros expone un nombre accesible (`aria-label` o
  texto) y su acción se puede anunciar.»
- S11: «Si el dueño decide no conservar el estado, `docs/ux-guidelines.md` §8 lo
  declara para los filtros y el criterio de #367 se corrige en el mismo cambio.»
  (No aplica: se conserva el estado; §8 documenta la vía elegida.)

### #403 — el alta no comunica

- S12: «Cada 422 de alta se pinta junto a su campo (mensaje en español,
  `aria-invalid` y texto asociado) y no sólo como toast; el envío vacío usa el
  mismo canal.»
- S13: «Los mensajes de validación de campo de la API llegan traducidos al
  español (catálogo `lang/es` o validación equivalente en el formulario).»
- S14: «El toast de éxito no imprime `null` ni claves técnicas: los campos
  opcionales ausentes se omiten.»

### #404 — Exportar visible para finanzas

- S16: «La ficha no ofrece «Exportar» a un rol que `PatientPolicy::export()`
  rechaza, o el botón explica la denegación antes de pedir y no produce un 403.»
- S17: «La decisión documentada de mantener `export.patient` inerte para finanzas
  **no** se revierte: el arreglo vive en la superficie (o en una capacidad
  derivada), no en el catálogo de concesiones.»

### Instrumento y evidencia (transversal)

- S3: «`pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep "@U4
  detail · render"` deja `failedRequests.unexpected` vacío para los cuatro
  roles.»
- S4: «`node scripts/audit/ui-pass.mjs --module=patients
  --out=.atl/qa-evidence/audit/ui/U4 --no-run` clasifica `/patients/:id` como
  `renders`.»
- S10: «`list-return.json` deja `verdict: "OK"` con `returned.request.search` y
  `returned.request.active` presentes.»
- S15: «`list-create.json` deja `duplicateDocument.inlineFieldError: true`,
  `futureBirthDate.inlineFieldError: true` y un `happyPath.toast` sin `null`.»
- S18: «`detail-export.json` deja `roles.finanzas.buttonVisible: false` (o
  `click.status !== 403`); administración sigue en 200 `application/pdf`.»
- S19: «`api-matrix.json` sigue con 0 desajustes.»

## Tasks

- T1 · #399 · S1-S2 · inline (un solo escritor del módulo) · `6c4103c5`
- T2 · #400 · S5-S7 · inline · `34aa15a2`
- T3 · #401 · S8-S9-S11 · inline · `6c3e4178` + `fcedaccb`
- T4 · #403 · S12-S14 · worker (backend i18n) + inline (formulario) · `b37c997e` + `a0642e5f`
- T5 · #404 · S16-S17 · inline · `cf96f864`
- T6 · #399-#404 · S3-S4-S10-S15-S18-S19 · inline · `b2da7eca`
- T7 · verificación · Vitest 1373/158, MySQL 2855/17373, Pint 909, ESLint
  0/36, Prettier, build, doc-drift 14/14, sondas U4 23/23 (14 evidencias OK,
  api-matrix 0/119, ui-pass `renders`) · cierre en el commit final de la rama

### Rebanadas de revisión

- Rebanada 1: T1 + T2 (la ficha lee lo que puede leer y Especialidades respeta
  su contrato). Commits `6c4103c5` y `34aa15a2`.
- Rebanada 2: T3 + T4 (la lista conserva su estado y el alta comunica).
  Commits `6c3e4178`, `fcedaccb`, `b37c997e` y `a0642e5f`.
- Rebanada 3: T5 + T6 (capacidad efectiva de exportación, sondas y docs).
  Commits `cf96f864` y `b2da7eca`.
- T7 corre al final de las tres. Los PR siguen siendo decisión del dueño.

## Log

- L1: «haz las issues #399 #400 #401 #403 #404 en un worktree nuevo»
- L2: Worktree `.worktrees/audit-u4-patients` sobre `origin/main` (`56103c5d`);
  base de test `odontosuite_audit_u4_test`; `.env` propio con
  `APP_LOCALE=es`.
- L3: Ruteo: un solo escritor para el módulo `patients` (los cinco issues
  comparten `PatientDetailPage.vue` y `PatientsPage.vue`); el backend de #403
  (catálogo `lang/es`) y el de #404 (`PatientResource`) son superficies
  disjuntas y se delegan en un worker. Sin escritores en paralelo sobre el mismo
  archivo.
- L4: El catálogo `lang/es` lo crea un worker en background (superficies
  `lang/es/`, `config/app.php`, `.env.example`) mientras el padre escribe la
  superficie. Tinker confirma `es|El campo nombre es obligatorio.|El campo
  número de documento ya ha sido tomado.`
- L5: `before:today` sustituye el literal `today` en `:date`, así que el
  catálogo solo respondería «anterior a today». `PatientController` declara el
  mensaje de `birth_date.before` en español; el test lo fija exacto.
- L6: Rebanada 1 cerrada con 401 líneas autoradas (aviso de presupuesto de
  revisión). Las tres rebanadas declaradas arriba son la estrategia de entrega;
  los PR siguen siendo decisión del dueño.
- L7: Las sondas U4 se alinean al contrato corregido: `detail-tabs` exige las 8
  tabs del administrador y el conjunto exacto de finanzas (data, quotations,
  cuenta) con sus tres ausencias en 403; `detail-export` exige
  `roles.finanzas.buttonVisible === false`; `list-create` mide
  `inlineFieldError` en el envío vacío y en la fecha futura; `list-return` ya
  mide búsqueda y estado en la petición de vuelta.
- L8: La primera versión de #401 escribía el query con
  `history.replaceState` para no remontar la vista. La sonda `list · vuelta`
  devolvió DEFECTO: el `push` de Vue Router 4 reescribe la entrada actual con
  `historyState.current` (su copia en memoria, sin query) antes de empujar la
  siguiente, así que el query se perdía al volver. Corrección: `router.replace({
  query })` y `meta.preserveOnQuery` en la ruta, con el shell keteando por
  `$route.path` sólo en esa ruta. `list · vuelta` queda OK con
  `?active=true&search=AUDIT-U4`.
- L9: `origin/main` avanzó a `cb775770` (#474, auditoría U8 del catálogo
  protésico) durante la sesión; la rama se fusionó sin conflictos y las sondas
  se re-midieron después del merge.
- L10: Sondas U4 completas (23/23) sobre la base sembrada `odontosuite_audit_u4`
  servida en `:8000`: 14 archivos de evidencia con `verdict: OK`,
  `api-matrix` 0 desajustes en 119 filas, `ui-pass` clasifica `/patients` y
  `/patients/:id` como `renders` (0 page errors, 0 http≥400).
