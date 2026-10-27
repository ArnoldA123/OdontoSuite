# Task: U10 — BI, continuidad y laboratorio (auditoría UI, plan #13)

Status: IN PROGRESS (sesión de auditoría). Rama `feat/audit-ui-u10` desde
`c6180b3` (`main`). No se toca código de producto: la sesión mide y documenta en
`tests/e2e/audit/u10/` y deja la evidencia en `.atl/qa-evidence/audit/ui/U10/`.

## Estrategia de entrega

El presupuesto de ~400 líneas aplica a correcciones, no a un instrumento nuevo
completo: un corte sólo es ejecutable cuando su capa y sus fixtures están
completos. Mismo criterio declarado en U0–U9. La entrega va en cortes revisables:
**1** instrumento de capas 0–3 y 5 (helpers, fixtures, render, matriz) y el
workflow de CI; **2** las tres caminatas de capa 4 y el generador de evidencia;
**3** informe, rúbrica, triage e issues; **4** esta nota y el PR. El merge y la
publicación quedan a decisión del dueño.

Frontera del primer corte: commit `fc4285e` (`helpers.js`, `fixtures.js`,
`render.js`, `api-matrix.js`, el registro en `surface.spec.js` y
`.github/workflows/audit-ui.yml`).

## Specs

- **S1** — Objetivo (issue #373, verbatim): «Verificar los tres escritorios de
  lectura y coordinación: que un reporte sin datos explique qué hacer, que el
  export coincida con la pantalla, que la cola de continuidad se pueda resolver y
  que la fase del laboratorio solo avance. Medido en `8b72d37`:
  `BusinessIntelligencePage` es la página más larga (1 115 líneas) y la única con
  consolidado por sede; continuidad y laboratorio usan `UiDataTable` y modales
  propios.»
- **S2** — Método (issue #373, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=business-intelligence,continuity,lab-cases --out=.atl/qa-evidence/audit/ui/U10/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U10
  ```
- **S3** — Superficies, roles y acciones (issue #373, verbatim): «Superficies:
  `/business-intelligence` (6 reportes, presets 7/30/90, filtros de sede,
  profesional y ambiente, consolidado por sede, export, gráficos), `/continuity`
  (cola mixta, métricas, agendar con fecha preferida, descartar con motivo) y
  `/lab-cases` (filtros por fase, técnico y vencido; alta, edición, detalle,
  avance de fase). Roles: administrador y finanzas (BI); recepción y clínicos
  (continuidad y laboratorio). Acciones: cambiar de reporte y de rango; exportar;
  redimensionar con gráficos; agendar con conflicto; descartar sin motivo;
  avanzar y retroceder fase; intentar editar un caso de otro técnico.»
- **S4** — Entregable (issue #373, verbatim): «`render.json`, `walkthrough.md`
  con la matriz reporte × estado (datos, vacío, error), `api-matrix.json`,
  `ux-rubric.md` y `odd/tasks/audit-ui-u10.md`.»
- **S5** — Criterio de salida (issue #373, verbatim): «Cada reporte tiene sus
  tres estados y su export coincide con la pantalla; el conflicto de agenda deja
  la solicitud pendiente y lo dice; la fase de laboratorio no retrocede y el
  mensaje cae en su campo; cero `pageerror`; rúbrica puntuada.»
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

## Tasks

- [ ] **T1** · S2, S10 · `inline` · Precondiciones: rama `feat/audit-ui-u10`;
  base `odontosuite_audit` recreada y sembrada; servidor local con
  `DB_DATABASE=odontosuite_audit`; capa 1 → `manifest.json` de las tres rutas.
- [ ] **T2** · S1, S3–S9 · `delegated` · Instrumento `@U10` en
  `tests/e2e/audit/u10/`: fixtures propios, render por rol, caminata de BI
  (reporte × estado + export), caminata de continuidad, caminata de laboratorio,
  matriz `rol × endpoint` e informe.
- [ ] **T3** · S2, S5 · `inline` · Corrida del instrumento y `gate.json` con cero
  `pageerror`.
- [ ] **T4** · S1, S5, S8–S11 · `inline` · Triage por clase raíz, issues
  publicados, `walkthrough.md`, `ux-rubric.md` y `findings.raw.json`.
- [ ] **T5** · S5, S10 · `inline` · Cierre: Prettier y ESLint en verde,
  `playwright test --list`, commits por corte, esta nota y PR.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 373-U10».
- **L2** — Cuerpo de #373 (S1–S5 verbatim), plan #13 §4 (capas 2–6), §5
  (rúbrica), §6 (anatomía y evidencia), §8 (protocolo de issues) y §9
  (guardarraíles).
- **L3** — Precondiciones en `c6180b3`: rama `feat/audit-ui-u10`; base
  `odontosuite_audit` recreada con `migrate:fresh --seed` (15 usuarios, 3 sedes,
  106 citas, 372 transacciones, 100 pacientes, 20 diagnósticos CIE-10, 0 casos de
  laboratorio, 0 recalls, 0 solicitudes de continuidad); app servida en
  `127.0.0.1:8000` con `DB_DATABASE=odontosuite_audit` y assets construidos
  (`pnpm build`; `public/hot` retirado para no depender del dev server). Capa 1 →
  **3 superficies, 25 acciones (0 sin clasificar), 3 diálogos, 16 endpoints, 0
  permisos sin resolver**; 2 rutas `excluded` por `redirect`.
- **L4** — Reconocimiento de contrato (token real por rol, base de auditoría):
  administrador 200 en toda lectura y 201/422 en las puertas de escritura;
  recepcionista 403 en los siete reportes y en `POST /api/patient-recalls`, 200 en
  continuidad y laboratorio; finanzas 200 en los reportes y 403 en los otros dos
  escritorios; los roles clínicos leen continuidad y laboratorio y no crean
  recalls. `PUT`/`POST` de laboratorio con id inexistente responden **404 antes**
  del middleware de permiso; con id real, 403 al rol sin `manage.lab_case`. Dos
  hechos de datos que la matriz debe declarar: las 372 transacciones sembradas
  son de tipos legacy `income`/`expense` (fuera de `MoneyVocabulary`), así que sin
  fixtures propios el reporte de ingresos sale vacío en todo período; y la
  semilla no crea casos de laboratorio, recalls ni solicitudes de continuidad.
- **L5** — Instrumento: `helpers.js` (1 369 líneas, set de U9 + siete auxiliares
  de BI), `fixtures.js` (1 291: dinero, continuidad por cierre real de atención y
  cuatro casos de laboratorio con residuo declarado), `render.js` (272),
  `api-matrix.js` (612, 24 endpoints × 7 roles) y `walk-bi.js` (2 024, siete
  filas). Corrida local de capas 0/2: **9 filas de render, 0 `pageerror`, 0
  scroll horizontal a 390 px**, y la puerta negativa de cada superficie (403).
  Corrección del instrumento: la página de BI pinta su armazón antes del reporte,
  así que la fila espera el gancho de estado (`[data-bi-kpis]`,
  `[data-report-table-section]`, `[data-report-empty]`, `[data-state="error"]`)
  antes de medir; sin esa espera la fila registraba el estado de carga como si
  fuera el reporte.
