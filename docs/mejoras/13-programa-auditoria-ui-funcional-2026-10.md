# Plan #13 — Programa de auditoría UI/funcional por escritorio (octubre 2026)

> **Fecha**: 2026-10-23 · **Revisión de referencia de todos los conteos**:
> `8b72d37` (árbol limpio, `main`) · **Origen**: el proyecto tiene 36 rutas con
> pantalla, 34 componentes de página y 108 smokes de Vitest, pero **ningún
> recorrido funcional en navegador** de esas superficies. El plan #12 auditó la
> infraestructura (datos, guards, RBAC, documentación); este programa audita lo
> que el usuario ve y pulsa.
>
> **Alcance**: inventariar, recorrer, probar y puntuar cada interfaz; convertir
> cada defecto en un issue con evidencia. **No corrige nada**: la sesión que
> audita no escribe código de producto.

---

## 1. Qué audita este programa y qué no

| Pregunta                                                          | Plan #12 (anterior) | Plan #13 (este) |
| ----------------------------------------------------------------- | ------------------ | --------------- |
| ¿El esquema migra, los guards fallan, el RBAC coincide?           | sí                 | heredado        |
| ¿La pantalla renderiza, el botón hace lo que promete?             | no                 | **sí**          |
| ¿La acción principal (crear, cobrar, cerrar, anular) se completa? | no                 | **sí**          |
| ¿El modal aparece donde debe, el estado vacío guía, el texto sobra? | no               | **sí**          |
| ¿Dos módulos usan la misma primitiva para la misma tarea?         | no                 | **sí**          |
| ¿Existe forma automática de detectar la regresión de todo esto?   | parcial (A7/#23)   | **sí, U0**      |

Relación con lo existente: el eje **A7** de #12 dejó un browser pass de 6 rutas
y el issue **#23** quedó cubierto módulo a módulo en Vitest. Este programa no
los repite: los convierte en la línea base y los extiende a las 36 rutas.

**Regla de frontera**: el plan #12 respondía "¿el mecanismo funciona?"; este
responde "¿la persona puede trabajar sin dudas, sin callejones y sin sorpresas
visuales?".

---

## 2. Regla de evidencia

Se hereda la de #12 (§1) con tres precisiones propias de la superficie:

| Regla                                   | Significado operativo en este programa                                                                                                                          |
| --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Sin artefacto, no hay hallazgo**      | Todo hallazgo cita el spec/CLI ejecutado, su salida y el PNG/JSON en `.atl/qa-evidence/audit/ui/<sesión>/`.                                                     |
| **Etiqueta de origen**                  | `MEDIDO` (observado en navegador o salida de comando) · `INFERIDO` (grep/lectura) · `NO VERIFICADO` (hipótesis). Un mismo hallazgo puede ser medido e inferido a la vez: se etiqueta por capa. |
| **Atado a revisión y a datos**          | Cada corrida anota `git rev-parse HEAD`, la base de auditoría usada y el seed aplicado.                                                                         |
| **Acción, no adjetivo**                 | "Mucho texto" no es hallazgo. Lo es: "el hero de X tiene 63 palabras y el de Y 12; el usuario debe leer 4 líneas antes del primer control".                     |
| **Esperado vs obtenido**                | Todo hallazgo declara la expectativa (del canon, de `docs/ux-guidelines.md` o de la convención del módulo hermano) y lo observado.                              |
| **Una clase raíz, un issue**            | Cinco modales desalineados son un issue, no cinco.                                                                                                              |
| **Causalidad declarada**                | `preexistente` o `introducido por <cambio>` en `<sha>`. El defecto preexistente no se descarta: se decide.                                                       |
| **Reproducible desde cero**             | El hallazgo se reproduce con: base de auditoría recreada + seed + ruta + secuencia de clics. Sin esa receta, no entra.                                          |
| **La sesión no corrige**                | Auditar y arreglar en la misma sesión destruye la evidencia y mezcla autorías. Si el defecto es trivial, igual se registra: se corrige en su propio issue.      |

**Criterio de calidad de una prueba** (heredado): una aserción cuenta solo si
**puede fallar**. Un `expect(true)`, un `waitForTimeout` como aserción o un
`if` que captura la excepción no prueban nada.

---

## 3. Inventario medido de superficies

Medido en `8b72d37` sobre el árbol limpio.

| Signo vital                              | Valor              | Comando                                                        |
| ---------------------------------------- | ------------------ | -------------------------------------------------------------- |
| Directorios de módulo                    | 25                 | `ls -d resources/js/modules/*/ \| wc -l`                        |
| Componentes `*Page.vue`                  | 34                 | `find resources/js/modules -name '*Page.vue' \| wc -l`          |
| Rutas declaradas en `app.js`             | 38                 | `grep -cE "^    path: '" resources/js/app.js`                   |
| Rutas con pantalla (sin 2 redirects)     | 36                 | lectura de `app.js`                                             |
| Primitivas en `components/ui`            | 35                 | `ls resources/js/components/ui \| wc -l`                        |
| Componentes de `components/layout`       | 4                  | `ls resources/js/components/layout`                             |
| Módulos que usan `UiModal`               | 18 de 25          | `grep -rl UiModal resources/js/modules` (por directorio de módulo) |
| Overlays propios (`fixed inset-0` fuera de primitivas) | 1    | `grep -rln 'fixed inset-0' resources/js/modules`                |
| Usuarios de `UiSheet`                    | 2 (nav móvil, alta IA) | `grep -rln UiSheet resources/js/modules resources/js/components` |
| `window.confirm` / `window.alert`        | 0                  | `grep -rn 'window.confirm\|window.alert' resources/js`          |
| Páginas con `<table>` propio             | 4                  | `grep -l '<table' $(find resources/js/modules -name '*Page.vue')` |
| Páginas sin `Skeleton` ni `LoadingSpinner` | 4 (Caja, Login, Ficha de paciente, Detalle de profesional) | ver §12 |
| Smokes de Vitest                         | 108 archivos       | `ls tests/js/smoke \| wc -l`                                    |
| Unitarios de Vitest                      | 31 archivos        | `ls tests/js/unit \| wc -l`                                     |
| Specs de navegador                       | 2 archivos, 8 tests | `pnpm exec playwright test --list`                             |
| Rutas cubiertas en navegador             | 7 de 36 (19 %)     | `tests/e2e/smoke.spec.js` (6 páginas + login real)              |
| Módulos con al menos un smoke            | 25 de 25 (mínimo 1)| cruce `tests/js/smoke` × `resources/js/modules`                 |

**Lectura**: la cobertura estática es alta y la cobertura de **recorrido** es
baja. El smoke monta la página y comprueba piezas; no inicia sesión, no pulsa
"Cobrar", no abre el modal de devolución, no envía un formulario vacío ni mira
dónde quedó el diálogo. Eso es exactamente lo que este programa añade.

### 3.1 Mapa de superficies (`A`=admin, `R`=recepción, `O`=odontólogo, `I`=implantólogo, `T`=técnico, `S`=asistente, `F`=finanzas, `★`=todos)

| #  | Módulo                    | Ruta(s)                                                        | Roles        | Superficies (páginas / modales / wizards)                                                               |
| -- | ------------------------- | -------------------------------------------------------------- | ------------ | ------------------------------------------------------------------------------------------------------- |
| 1  | auth                      | `/login`, `/reset-password`                                    | anónimo      | LoginPage + `ForgotPasswordModal` + `ResetPasswordModal`                                                |
| 2  | dashboard                 | `/dashboard`                                                   | ★            | KPIs, agenda del día, pendientes, próximas, accesos                                                      |
| 3  | my-day                    | `/my-day`                                                      | A,O,I,T,S    | Cola en espera / atendiendo / próximos; laboratorio listo                                               |
| 4  | appointments              | `/calendar`                                                    | A,R,O,I,T,S  | Calendario (día/semana/mes), alta/reprogramación por drag, custodia, `ConsultationWizard` (5 pasos)       |
| 5  | continuity                | `/continuity`                                                  | A,R,O,I,T,S  | Cola de recalls y solicitudes + `ScheduleContinuityModal` + `DismissContinuityModal`                     |
| 6  | patients                  | `/patients`, `/patients/:id`                                    | A,R,O,I,T,S,F| Lista, `PatientFormModal`, ficha con tabs (Datos, Atenciones, Cuenta, Laboratorio…), cargo manual, estado de cuenta, exportar ficha |
| 7  | professionals             | `/professionals`, `/:id`                                        | A            | Lista + `ProfessionalFormModal` + detalle                                                               |
| 8  | environments              | `/environments`, `/:id`                                         | A            | Lista, formulario, detalle con tabs (Información, Equipamiento, Auditoría)                              |
| 9  | appointment-types         | `/appointment-types`, `/:id`                                    | A            | Lista, formulario, detalle con tabs (Datos, Historial)                                                  |
| 10 | specialties               | `/specialties`                                                  | A            | Catálogo maestro + formulario                                                                            |
| 11 | settings/branches         | `/settings/branches`, `/:id`                                    | A            | Lista, formulario, detalle                                                                              |
| 12 | branch-transfers          | `/branch-transfers`                                             | ★            | Solicitud, historia, decisión de gerencia                                                               |
| 13 | settings/payment-methods  | `/settings/payment-methods`                                     | A            | Lista + formulario (familia, referencia, pasarela)                                                      |
| 14 | accounting-locks          | `/settings/accounting-locks`                                    | A            | Cuatro módulos × fecha efectiva × origen; fijar y retirar                                                |
| 15 | procedure-catalog         | `/procedure-catalog`, `/:id`                                    | A            | Lista, formulario, detalle (Datos, Tarifario, Versiones, Historial), duplicar                            |
| 16 | procedure-catalog         | `/procedure-stats`                                              | A,F          | KPIs, ranking, distribución, CSV, gráficos                                                              |
| 17 | my-procedures             | `/my-procedures`                                                | O,I,T,S      | Favoritos por profesional                                                                               |
| 18 | reception-procedures      | `/reception-procedures`                                         | R,F          | Consulta de catálogo por recepción                                                                       |
| 19 | business-intelligence     | `/business-intelligence`                                        | A,F          | 6 reportes, filtros, presets, consolidado por sede, gráficos, export                                    |
| 20 | cash-register             | `/cash-register`                                                | A,F,R        | Tabs Sesión/Cobros/Transacciones/Movimientos/Historial/Reportes + 6 modales de dinero + `ReceiptPreview` |
| 21 | cash-register             | `/cash-register/ready-to-bill`                                  | A,F,R        | Lista de atenciones por cobrar + cobro                                                                  |
| 22 | treatment-plans           | `/treatment-plans`                                              | A,O,I,T      | Lista/kanban, `TreatmentPlanModal`, `TreatmentPlanDetail`, versiones, alternativas, consumos             |
| 23 | quotations                | `/quotations`                                                   | A,F,O,I,R    | Lista, alta/edición, detalle con IGV, aprobar/rechazar, PDF, vencimiento                                |
| 24 | medical-records           | `/medical-records`, `/:id`                                      | A,O,I,S      | Workspace por paciente, 4 tabs, evolución, adjunto, addendum, estadísticas                               |
| 25 | specialty-records         | `/specialty-records`                                            | A,O,I        | Selector de paciente + 5 especialidades × CRUD + detalle                                                |
| 26 | ai-analysis               | `/ai-analysis`                                                  | A,O,I        | Lista con KPIs, `AiAnalysisSheet` (alta), detalle, revisión, anulación                                   |
| 27 | lab-cases                 | `/lab-cases`                                                    | A,R,O,I,T,S  | Lista con filtros, `LabCaseFormModal`, `LabCaseDetailModal`, avance de fase                              |
| 28 | errors                    | catch-all                                                       | ★            | NotFound dentro del shell                                                                               |

`#4` incluye el asistente de cierre; `#6` incluye la tab Cuenta; `#24` incluye
los addenda firmados. Los roles de la tabla son los que **ven** la entrada de
navegación (`AppLayout.navigation`); el permiso efectivo lo decide la API.

### 3.2 Cobertura actual, por módulo

| Módulo                    | Smokes | Unit | Navegador |
| ------------------------- | ------ | ---- | --------- |
| cash-register             | 11     | 1    | 1 ruta    |
| dashboard                 | 14     | 1    | 1 ruta    |
| patients                  | 8      | 1    | 1 ruta    |
| procedure-catalog         | 8      | 2    | 0         |
| auth                      | 7      | 0    | login     |
| quotations                | 6      | 1    | 1 ruta    |
| treatment-plans           | 6      | 0    | 0         |
| settings (branches+pagos) | 6      | 1    | 0         |
| medical-records           | 5      | 0    | 0         |
| ai-analysis               | 4      | 0    | 0         |
| appointment-types         | 4      | 0    | 0         |
| business-intelligence     | 4      | 1    | 1 ruta    |
| professionals             | 4      | 0    | 0         |
| appointments              | 3      | 3    | 1 ruta    |
| environments              | 3      | 0    | 0         |
| specialties               | 3      | 0    | 0         |
| lab-cases                 | 2      | 1    | 0         |
| accounting-locks          | 1      | 1    | 0         |
| branch-transfers          | 1      | 0    | 0         |
| continuity                | 1      | 1    | 0         |
| errors                    | 1      | 0    | 0         |
| my-day                    | 1      | 0    | 0         |
| my-procedures             | 1      | 0    | 0         |
| reception-procedures      | 1      | 0    | 0         |
| specialty-records         | 1      | 1    | 0         |

---

## 4. Método de audit-test (seis capas)

Cada sesión ejecuta las capas que apliquen. Las capas 1, 2, 3 y 6 son
automáticas; la 4 es exploratoria dirigida; la 5 es contrato.

### Capa 1 — Manifiesto de superficie (estático, automático)

Extrae del código la lista de superficies de la sesión: ruta → componente →
tabs → modales/sheets → botones visibles → endpoints consumidos → permiso de
ruta. La sesión **no inventa** su checklist: la genera.

```bash
node scripts/audit/ui-manifest.mjs --module=<módulo> \
  --out=.atl/qa-evidence/audit/ui/<sesión>/manifest.json
```

Salida (`manifest.json`): `[{ route, name, page, roles, tabs[], dialogs[],
actions[{label, kind, target}], endpoints[], permission }]`.

**Criterio de capa**: 0 superficies sin clasificar. Si un botón no se puede
clasificar (no se sabe qué hace), es el primer hallazgo de la sesión.

### Capa 2 — Render y salud (Playwright, automático)

Por cada ruta y por cada rol que la ve (máximo 3 roles: el dueño del escritorio,
administración y un rol excluido para la puerta negativa):

1. Respuesta 200 y render del marcador de la página.
2. Cero `console.error`, cero `pageerror`, cero request ≥ 400 salvo allowlist
   declarada (por ejemplo, la propia prueba de 403).
3. Longitud de contenido renderizado por encima del umbral del módulo (el truco
   que destapó `QuotationCard` en #12 A7).
4. Captura `desktop 1440×900` y `mobile 390×844`.
5. Sin scroll horizontal de documento en 390 px.

Artefacto: `render.json` + `shots/<slug>-<rol>-<viewport>.png`.

### Capa 3 — Contrato de primitivas (Playwright, automático)

Por cada diálogo abierto desde una superficie:

| Aserción             | Cómo se mide                                                                                  | Canon                                      |
| -------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Centrado horizontal  | `\|box.x + box.width/2 − viewportWidth/2\| < 8`                                                | `UiModal` es centrado (`items-center justify-center`) |
| Docking              | Un `UiSheet` debe tocar el borde declarado (`position="right"` ⇒ `\|right − vw\| < 8`)         | `Sheet` solo donde el canon lo autoriza    |
| Un solo `h1`         | `expect(page.locator('h1')).toHaveCount(1)`                                                    | Jerarquía de títulos                       |
| Escape cierra        | `press('Escape')` ⇒ diálogo ausente                                                            | `Modal`/`Sheet`                            |
| Foco atrapado        | `document.activeElement` dentro del diálogo al abrir; `Tab` no sale                            | `useFocusTrap`                             |
| Scroll bloqueado     | `body` con overflow oculto mientras el diálogo vive                                            | Pila compartida de modales                 |
| Etiqueta accesible   | `aria-labelledby` presente o `aria-label`                                                      | a11y                                       |

Un diálogo que no cumple **una** fila es hallazgo, no ruido: es la clase
"primitiva mal usada" y se agrupa con sus hermanos.

### Capa 4 — Recorrido funcional dirigido (exploratorio, con evidencia)

Es el núcleo estilo TestSprite: por cada superficie, ejecutar la **lista de
acciones** y clasificar cada una.

| Veredicto     | Definición                                                                                     |
| ------------- | ---------------------------------------------------------------------------------------------- |
| `OK`          | La acción produce el efecto observable que promete, con feedback y sin error de consola.        |
| `DEFECTO`     | Produce un efecto distinto, ninguno, o uno destructivo; o el feedback desaparece.               |
| `NO-OP`       | Elemento pulsable sin efecto observable (sin navegación, sin diálogo, sin request, sin mensaje). |
| `CONFUSO`     | Funciona, pero el usuario no puede predecir qué hará (etiqueta, icono, ubicación, estado).      |
| `NO PROBADO`  | Requiere un dato o rol que la sesión no tiene; se declara con motivo.                            |

Toda acción con `DEFECTO`, `NO-OP` o `CONFUSO` produce fila en `findings.md`
con captura y, si hay request, su status.

Lista mínima de acciones por superficie (ampliada por el manifiesto):

1. **Listar** y paginar; 2. **filtrar** y limpiar filtros; 3. **buscar**;
4. **crear** (camino feliz + envío vacío + un campo inválido);
5. **editar**; 6. **eliminar/anular/cerrar** con confirmación;
7. **abrir detalle y cada tab**; 8. **exportar/imprimir** cuando exista;
9. **permiso negativo** (rol sin acceso: 403 de API y entrada oculta);
10. **navegación de vuelta** (back del navegador, breadcrumb, cierre de modal).

### Capa 5 — Contrato API por rol (sonda)

Para los endpoints que la sesión consume: matriz `rol × endpoint` con el status
esperado derivado de `App\Authorization\Permissions` y el observado con un token
real. Reusa el patrón de #12 A3/A4 y solo cubre los endpoints de la sesión.

Artefacto: `api-matrix.json` (`{endpoint, method, role, expected, observed}`).

### Capa 6 — Rúbrica UX/UI (puntuada)

Doce criterios, 0/1/2 cada uno, por superficie primaria y una vez por sesión para
su conjunto. La tabla completa está en §5. La puntuación va a `ux-rubric.md` con
la cita de lo observado.

**Regla de oro**: una capa no sustituye a otra. Un render limpio con un botón
muerto es un módulo roto.

---

## 5. Rúbrica UX/UI (12 criterios)

Puntuación: `0` ausente · `1` parcial o inconsistente · `2` cumple el canon.

| #  | Criterio                     | Qué se observa                                                                                          | 0                                              | 2                                                     |
| -- | ---------------------------- | ------------------------------------------------------------------------------------------------------- | ---------------------------------------------- | ----------------------------------------------------- |
| 1  | Jerarquía de títulos         | Un `h1` visible, hero de página, topbar sin duplicarlo                                                  | dos `h1` o título repetido                     | un `h1`, hero y topbar en su rol                      |
| 2  | Densidad de texto            | Palabras del hero/subtítulo y párrafos explicativos antes del primer control                            | ≥ 40 palabras o párrafo > 2 líneas por bloque  | ≤ 20 palabras, el control manda                      |
| 3  | Información repetida         | Mismo dato dos veces en la misma vista sin función distinta                                             | duplicado en la misma tarjeta/sección          | un solo lugar, o resumen vs detalle justificado        |
| 4  | Acciones con efecto          | Toda acción visible hace lo que promete; el deshabilitado explica por qué                               | hay `NO-OP` o acción engañosa                  | toda acción responde con efecto o motivo              |
| 5  | Estados completos            | Carga (skeleton/spinner), vacío con CTA, error con reintento                                             | falta uno de los tres                          | los tres, sin salto de layout                         |
| 6  | Primitivas                   | `UiButton`, `UiModal`, `UiDataTable`, `UiEmptyState`, `UiTabs`; sin overlay propio                       | overlay o botón ad-hoc                         | primitivas del sistema                                |
| 7  | Feedback                     | Toast para acción sin superficie; error inline en el campo; confirmación en destructivo                 | silencio o toast para error de campo           | cada caso en su canal                                 |
| 8  | Navegación                   | Volver, breadcrumb, cierre de modal, filtros conservados, deep link                                     | callejón sin salida                            | siempre hay retorno                                   |
| 9  | Accesibilidad                | Foco, `aria-label`, teclado (Esc/Enter/Tab), contraste, `prefers-reduced-motion`                        | foco perdido o control solo-mouse              | teclado completo y foco visible                       |
| 10 | Responsive                   | 390 px: sin solapamiento, sin scroll horizontal de documento, tabla con affordance                      | contenido cortado                              | layout legible y navegable                            |
| 11 | Formato de datos             | Fecha, hora, moneda y estado con un solo formato y un solo badge                                        | dos formatos para el mismo dato                | formato único y compartido                            |
| 12 | Honestidad de la superficie  | Nada visible que la API no soporte; nada soportado sin superficie declarada como follow-up               | botón que llama a un endpoint inexistente      | superficie y contrato alineados                       |

**Veredicto de sesión**: cualquier criterio en `0` sobre una superficie primaria
es defecto (`crit:alta` si bloquea la tarea; `crit:media` si la degrada). Un
criterio en `1` en dos o más módulos es clase raíz del issue transversal (U11).

---

## 6. Anatomía de una sesión

Toda sesión sigue el mismo guion. Lo que cambia es el alcance.

### 6.1 Precondiciones (una vez por sesión)

```bash
# 1. Árbol limpio y revisión anotada
git rev-parse HEAD && git status --porcelain

# 2. Base de auditoría aislada (nunca la base de desarrollo)
mysql -h localhost --socket=/opt/lampp/var/mysql/mysql.sock -u root -p \
  -e "DROP DATABASE IF EXISTS odontosuite_audit; CREATE DATABASE odontosuite_audit;"
APP_ENV=local DB_DATABASE=odontosuite_audit BROADCAST_CONNECTION=null MAIL_MAILER=log \
  php artisan migrate --seed --force

# 3. App y Vite arriba (dos terminales o `composer dev`)
php artisan serve      # :8000
pnpm dev               # :5173

# 4. Chromium de Playwright ya instalado (verificado: 1.63.0)
pnpm exec playwright --version
```

Guardarraíles de datos: la sesión **solo** escribe en `odontosuite_audit`; los
borrados se hacen sobre filas que la propia sesión creó; nunca
`migrate:fresh` sobre la base de desarrollo; `.env` intacto (se pasa por
entorno, no se edita).

### 6.2 Pasos

| Paso | Acción                                                                                  | Producto                          |
| ---- | --------------------------------------------------------------------------------------- | --------------------------------- |
| 0    | Anotar revisión, base y seed.                                                           | cabecera de `findings.md`         |
| 1    | Generar el manifiesto (capa 1).                                                         | `manifest.json`                   |
| 2    | Correr render y salud por ruta y rol (capa 2).                                          | `render.json` + `shots/`          |
| 3    | Abrir cada diálogo y correr el contrato de primitivas (capa 3).                         | `primitives.json`                 |
| 4    | Recorrido funcional: ejecutar la lista de acciones y clasificar (capa 4).               | `walkthrough.md`                  |
| 5    | Sonda de contrato por rol para los endpoints de la sesión (capa 5).                    | `api-matrix.json`                 |
| 6    | Puntuar la rúbrica (capa 6).                                                            | `ux-rubric.md`                    |
| 7    | Triage: agrupar por clase raíz, deduplicar contra issues abiertos y cerrados.           | `findings.md` consolidado         |
| 8    | Publicar 3–7 issues (o cero, si el veredicto es `sin defectos`).                        | issues + enlace en el informe     |
| 9    | Escribir la nota ODD de la sesión con el resumen y los enlaces.                         | `odd/tasks/audit-ui-<sesión>.md`  |

### 6.3 Evidencia: dónde vive

`.atl/` está en `.gitignore` (verificado), así que las capturas y JSON **no se
commitean**. Cada sesión deja:

- `.atl/qa-evidence/audit/ui/<sesión>/` — artefactos crudos (local, durante la
  vida del issue).
- `odd/tasks/audit-ui-<sesión>.md` — resumen versionado: revisión, base, rutas,
  tabla de hallazgos, enlaces a issues y veredicto. Es lo que sobrevive.
- El cuerpo de cada issue — la evidencia mínima reproducible.

### 6.4 Formato de `findings.md`

| Campo        | Contenido                                                  |
| ------------ | ---------------------------------------------------------- |
| `F#`         | Correlativo de la sesión                                   |
| Superficie   | Ruta + componente                                          |
| Acción       | "Pulsar «Cobrar» en una cita sin método de pago"           |
| Esperado     | Del canon o del módulo hermano, citado                     |
| Obtenido     | Lo observado, con captura y status si hay request          |
| Capa         | 2, 3, 4, 5 o 6                                             |
| Origen       | MEDIDO / INFERIDO / NO VERIFICADO                          |
| Severidad    | `crit:alta` / `crit:media` / `crit:baja`                   |
| Causa        | `archivo:línea` medida, o "hipótesis a confirmar"          |
| Issue        | número publicado, o `backlog` con motivo                   |
| Reproducción | comando + secuencia de clics                               |

### 6.5 Criterios de aborto

La sesión se detiene y reporta sin publicar issues si: la app no arranca o el
`migrate --seed` falla (problema de entorno, no de interfaz); la base de
auditoría no se puede recrear; o más de la mitad de las rutas del alcance no
renderizan (indica un defecto de plataforma que merece su propio issue, no 20
hallazgos de superficie).

---

## 7. Backlog de sesiones

Trece sesiones (`U0`–`U12`). Cuatro son divisibles si no caben en una jornada:
`U3`, `U5`, `U7`, `U9`. El orden minimiza setup repetido y maximiza hallazgos
tempranos: primero el arnés, después el shell (todo depende de él), después los
escritorios por volumen de uso.

| Fase | Sesiones        | Por qué en este orden                                                             |
| ---- | --------------- | --------------------------------------------------------------------------------- |
| 0    | **U0**          | Sin arnés y sin manifiesto, cada sesión reinventa la evidencia y las cifras derivan. |
| 1    | **U1**, **U2**  | Sesión y shell: si el login o el layout fallan, los demás recorridos no son comparables. |
| 2    | **U3**, **U4**  | Agenda y pacientes: el flujo de trabajo diario y la ficha, la mayor superficie.   |
| 3    | **U5**, **U6**  | Dinero: caja y presupuestos; acá el defecto duele.                                |
| 4    | **U7**          | Clínica: historia, especialidades e IA.                                           |
| 5    | **U8**          | Catálogo: cuatro superficies que comparten catálogo y tarifario.                  |
| 6    | **U9**          | Administración y configuración: escritorio de administración completo.            |
| 7    | **U10**         | BI, continuidad y laboratorio.                                                    |
| 8    | **U11**         | Consistencia transversal: compara artefactos, no vuelve a recorrer todo.          |
| 9    | **U12**         | Consolidación, deduplicación y publicación final.                                 |

**Presupuesto**: una sesión por jornada; entre 3 y 7 issues por sesión; ninguna
corrección de más de 400 líneas (las correcciones no son de este programa).
Total estimado: 13 jornadas + hasta 4 divisiones.

---

### U0 — Arnés de audit-test y línea base de superficies

**Superficies**: las 36 rutas, todas las primitivas, el shell. No audita UX: crea
el instrumento.

**Objetivo**: un comando produce evidencia de las 36 rutas y el manifiesto de
superficies; cualquier sesión posterior lo reusa sin escribir infraestructura.

**Método**:

1. `scripts/audit/ui-manifest.mjs`: parsea `app.js`, `AppLayout.navigation`,
   primitivas y consumidores de API por página.
2. `tests/e2e/audit/harness.js`: login por API (token en `localStorage`) para no
   chocar con el throttle de 3/min del login, helpers de diálogo, capturas y
   colector de diagnóstico — reusando lo que ya hace `tests/e2e/smoke.spec.js`.
3. `tests/e2e/audit/surface.spec.js`: capas 2 y 3 parametrizadas por manifiesto.
4. `scripts/audit/ui-pass.mjs`: envoltorio CLI al estilo `browser-pass.mjs`
   (resumen por página, exit 0 solo si renderiza y no hay `pageerror`).

**Entregable**: script + spec + manifiesto de las 36 rutas +
`.atl/qa-evidence/audit/ui/U0/` + `odd/tasks/audit-ui-u0.md`.

**Criterio de salida**: `node scripts/audit/ui-pass.mjs --all` deja 36 filas
clasificadas (renderiza / no renderiza / excluida con motivo) y el manifiesto
declara 0 botones sin clasificar.

**Issue (unidad de auditoría)**:

```text
[Auditoría/UI] U0 — Arnés de audit-test y línea base de 36 rutas
Objetivo: un comando produce manifiesto + render + contrato de primitivas de las 36 rutas.
Método: node scripts/audit/ui-manifest.mjs; tests/e2e/audit/surface.spec.js; node scripts/audit/ui-pass.mjs --all.
Entregable: scripts/audit/ui-pass.mjs, tests/e2e/audit/harness.js, manifiesto, evidencia U0, nota ODD.
Criterio de salida: ui-pass --all → 36 rutas clasificadas, 0 pageerror, 0 botón sin clasificar.
Relacionado: plan #13 §3-§4; plan #12 A7; #23.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: M
```

**Hipótesis (INFERIDO)**: solo 7 de 36 rutas tienen cobertura de navegador; las
29 restantes nunca se abrieron con sesión real en un test. El 19 % medido lo
sostiene; U0 lo confirma o lo refuta.

---

### U1 — Sesión, cuenta y shell

**Rutas**: `/login`, `/reset-password`, `/:pathMatch(.*)*`, más `AppLayout`
(sidebar, topbar, menú de usuario, indicador WS, `ConfirmDialog` global, sheet de
navegación móvil).

**Roles**: anónimo, administrador, odontólogo, recepcionista, finanzas (la
matriz completa de entradas visibles es barata: 7 renders).

**Acciones**: login válido; login inválido (mensaje y sin fuga de detalle);
throttle tras 3 intentos en un minuto; bloqueo de 10 minutos tras 5 fallos
(usuario descartable); "olvidé mi contraseña" (correo a `laravel.log`); reset con
token; logout; guard `requireGuest` (ir a `/login` con sesión); `requireAuth`
(abrir ruta protegida sin sesión); 404 dentro del shell; navegar a cada entrada
del sidebar por rol; menú de usuario; indicador de WS; modo móvil del sidebar.

**Hipótesis (INFERIDO)**: `LoginPage.vue` (1 456 líneas) no usa `Skeleton` ni
`LoadingSpinner`; `AppointmentTypesPage`, `CashRegisterPage`,
`PatientDetailPage` y `ProfessionalDetailPage` tampoco usan ninguno de los dos.
Verificar si el usuario ve un salto de layout antes del contenido.

**Criterio de salida**: `render.json` de 7 roles × login/404/shell +
`primitives.json` del sheet de navegación + rúbrica de shell; issues publicados.

```text
[Auditoría/UI] U1 — Sesión, cuenta y shell
Superficies: /login, /reset-password, 404, AppLayout (sidebar, topbar, menú, confirm, sheet móvil).
Método: capas 2-3-4-6; login real + token por rol; contrato del sheet y del confirm.
Entregable: render.json (7 roles), primitives.json, walkthrough.md, ux-rubric.md.
Criterio de salida: toda entrada del sidebar visible solo para sus roles; 0 pageerror; rúbrica de shell puntuada.
Relacionado: plan #13 §3-§5; #280; #305.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: M
```

---

### U2 — Dashboard y Mi día

**Rutas**: `/dashboard`, `/my-day`.

**Roles**: administrador, odontólogo, recepcionista, finanzas (dashboard);
odontólogo/técnico/asistente (Mi día).

**Acciones**: cargar KPIs; abrir cada sección (agenda de hoy, pendientes,
próximas, accesos rápidos); CTA de cada tarjeta; estado vacío de cada sección;
error con reintento (simulado cortando la API); permiso de caja (rol clínico no
pide sesión de caja); actualización en vivo sin Reverb; en Mi día: ver cola,
marcar llegada, iniciar atención, continuar consulta, bloque de laboratorio
listo, contadores.

**Hipótesis (INFERIDO)**: `DashboardPage` usa 21 marcas de reintento/`retry` y
cero `EmptyState`; verificar si un fallo de una sección degrada toda la página o
solo su tarjeta.

**Criterio de salida**: render limpio para 4 roles, walkthrough de las 6
secciones + 4 acciones de Mi día, rúbrica, issues.

```text
[Auditoría/UI] U2 — Dashboard y Mi día
Superficies: /dashboard (KPIs, agenda de hoy, pendientes, próximas, accesos), /my-day (cola, acciones).
Método: capas 2-4-6; roles admin/odontólogo/recepción/finanzas; error simulado por sección.
Entregable: render.json, walkthrough.md, ux-rubric.md.
Criterio de salida: una sección caída no rompe la página; las 4 acciones de Mi día producen su efecto.
Relacionado: plan #13 §3; #281; #345.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: M
```

---

### U3 — Agenda y consulta (divisible: U3a agenda / U3b asistente)

**Rutas**: `/calendar` + `ConsultationWizard`.

**Roles**: administrador, recepcionista, odontólogo, técnico (consulta),
finanzas (no debe ver la agenda).

**Acciones**: cambiar vista día/semana/mes; navegar fechas; filtrar por
profesional/sillón/sede; crear cita (y envío vacío); reprogramar por drag;
cancelar con motivo; check-in; anunciar paciente; iniciar atención; abrir el
asistente; avanzar y retroceder pasos; agregar evolución, diagnósticos, material,
ítem de plan; firmar con contraseña incorrecta y correcta; cerrar; reprogramar
desde el cierre; intentar cerrar desde otro rol.

**Hipótesis (INFERIDO)**: `ConsultationWizard.vue` es el único overlay propio
(`fixed inset-0`) fuera de las primitivas; el módulo `appointments` concentra 13
`<button>` propios. Verificar si el asistente respeta foco, Escape y pila de
modales como `UiModal`.

**Criterio de salida**: una cita recorre crear → check-in → iniciar → cerrar →
cobrar sin callejón; el asistente cumple el contrato de primitivas o es hallazgo;
rúbrica; issues.

```text
[Auditoría/UI] U3 — Agenda y consulta (U3a calendario · U3b asistente)
Superficies: /calendar (vistas, filtros, drag, custodia) y ConsultationWizard (5 pasos, firma).
Método: capas 2-3-4-5-6; roles admin/recepción/odontólogo; cita completa end-to-end.
Entregable: manifest.json, render.json, primitives.json, walkthrough.md, ux-rubric.md.
Criterio de salida: ciclo de vida completo de una cita sin callejón; 0 pageerror; asistente con contrato de foco/Escape.
Relacionado: plan #13 §4-§5; #281; #282; #311; #314.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: L (dividir si el asistente no cabe)
```

---

### U4 — Pacientes

**Rutas**: `/patients`, `/patients/:id`.

**Roles**: administrador, recepcionista, odontólogo, finanzas.

**Acciones**: buscar; filtrar por estado/sede; paginar; ordenar; abrir fila;
crear paciente (vacío, documento duplicado, fecha inválida); editar; eliminar con
confirmación; exportar ficha; en la ficha: recorrer cada tab; registrar cargo
manual; ver estado de cuenta (período, saldos, entradas); abrir presupuesto del
paciente; alta de caso de laboratorio; volver a la lista conservando filtros.

**Hipótesis (INFERIDO)**: la lista usa `<table>` propio y `PatientListRow` en vez
de `UiDataTable`; la ficha no tiene `Skeleton` ni `LoadingSpinner`. Verificar si
la carga se percibe y si la lista comparte el comportamiento de tabla del resto
(affordance de scroll, paginación, empty state).

**Criterio de salida**: alta-edición-borrado verificados sobre filas propias;
las 4 tabs abiertas; estado de cuenta cuadra con el seed; rúbrica; issues.

```text
[Auditoría/UI] U4 — Pacientes (lista y ficha)
Superficies: /patients (búsqueda, filtros, paginación, alta, edición, borrado, export) y /patients/:id (tabs, cuenta, cargo manual, laboratorio).
Método: capas 2-3-4-5-6; roles admin/recepción/odontólogo/finanzas; datos propios de la sesión.
Entregable: manifest.json, render.json, primitives.json, walkthrough.md, ux-rubric.md.
Criterio de salida: CRUD completo sin residuos; ficha con estados de carga/vacío/error; filtros conservados al volver.
Relacionado: plan #13 §3; #287; #320; #321.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: M
```

---

### U5 — Dinero (divisible: U5a caja / U5b cobros y reportes)

**Rutas**: `/cash-register`, `/cash-register/ready-to-bill`.

**Roles**: administrador, finanzas, recepcionista.

**Acciones**: abrir sesión (y abrir dos veces); cerrar con diferencia; agregar
movimiento; historial con filtros; cobrar pendiente; cobrar con cada familia de
método (efectivo, tarjeta, transferencia, billetera) y sin referencia cuando es
obligatoria; devolución con autorizador y motivo; depósito con tope y referencia;
cargo manual; ver recibo; reportes de período (conciliación, depósitos
pendientes, mix por método); intentar cobrar sin sesión abierta; bloqueo
contable con y sin `lock_override_reason`; rol sin permiso de caja.

**Hipótesis (INFERIDO)**: `CashRegisterPage` no tiene `Skeleton` ni
`LoadingSpinner` pese a que su primera carga depende de la sesión y los saldos;
`ReadyToBillPage` usa `LoadingSpinner` y tampoco `Skeleton`. Verificar si la
primera pantalla de caja salta.

**Criterio de salida**: cada tipo de movimiento deja el saldo esperado y la
gaveta cuadra; el 403 del bloqueo se explica en línea; rúbrica; issues.

```text
[Auditoría/UI] U5 — Dinero: caja, cobros y reportes (U5a caja · U5b cobros/reportes)
Superficies: /cash-register (6 tabs, 6 modales de dinero, recibo) y /cash-register/ready-to-bill.
Método: capas 2-3-4-5-6; roles admin/finanzas/recepción; un cobro y una devolución por familia de método.
Entregable: manifest.json, render.json, primitives.json, walkthrough.md, api-matrix.json, ux-rubric.md.
Criterio de salida: aritmética de gaveta verificada contra el reporte; errores de validación en su campo; 0 pageerror.
Relacionado: plan #13 §4-§5; #287; #320-#323; #289.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: L (dividir por tabs si no cabe)
```

---

### U6 — Presupuestos y planes de tratamiento

**Rutas**: `/quotations`, `/treatment-plans`.

**Roles**: administrador, finanzas, odontólogo, recepcionista (presupuestos);
administrador, odontólogo, técnico (planes).

**Acciones**: crear presupuesto manual y desde plan; agregar línea con
procedimiento + sitio + convenio; ver desglose de IGV; editar en borrador;
aprobar; rechazar con motivo; descargar PDF; intentar editar uno aprobado (debe
bloquear y explicar); plan: lista/kanban, alta, edición, prioridad, secuencia,
alternativa A/B, versión nueva al editar aprobado, consumo de ítem, transición de
estado inválida (422 en pantalla).

**Hipótesis (INFERIDO)**: el detalle del plan y el modal comparten `UiModal`
desde #242/#177; verificar que el modal anidado respeta la pila (un formulario
encima cierra solo).

**Criterio de salida**: el dinero del presupuesto cuadra (base + IGV = total) y
la inmutabilidad se explica en la interfaz; rúbrica; issues.

```text
[Auditoría/UI] U6 — Presupuestos y planes de tratamiento
Superficies: /quotations (lista, alta, edición, detalle, IGV, aprobar/rechazar, PDF) y /treatment-plans (lista/kanban, modal, detalle, versiones, alternativas).
Método: capas 2-3-4-5-6; roles admin/finanzas/recepción/odontólogo.
Entregable: render.json, primitives.json (modal anidado), walkthrough.md, ux-rubric.md.
Criterio de salida: base + IGV = total en pantalla y PDF; edición de aprobado bloqueada con mensaje; 0 pageerror.
Relacionado: plan #13 §4-§5; #289; #293; #288; #242.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: M
```

---

### U7 — Clínica: historia, especialidades y Análisis IA (divisible: U7a historia / U7b especialidades+IA)

**Rutas**: `/medical-records`, `/medical-records/:id`, `/specialty-records`,
`/ai-analysis`.

**Roles**: administrador, odontólogo, implantólogo, asistente; técnico debe
recibir 403 y no ver la navegación.

**Acciones**: seleccionar paciente; crear historia; registrar evolución (SOAP +
mediciones); adjuntar archivo (tipo y tamaño); agregar addendum; ver
estadísticas; intentar editar/borrar (no debe existir la acción); especialidades:
elegir especialidad, crear/editar registro de cada una de las 5, ver detalle; IA:
subir imagen (validación 20 MB/tipo), declarar aviso al paciente, revisar,
anular con motivo, intentar anular citado; ver el estado vacío de cada tab.

**Hipótesis (INFERIDO)**: `MedicalRecordsPage` acumula 15 marcas de estado vacío
y 18 de reintento: verificar si el usuario entiende qué hacer en cada tab o si el
texto compite consigo mismo.

**Criterio de salida**: ninguna ruta de borrado clínico visible; cada tab con
estado vacío accionable; 403 del técnico verificada en UI y API; rúbrica; issues.

```text
[Auditoría/UI] U7 — Clínica: historia, especialidades e IA (U7a historia · U7b especialidades/IA)
Superficies: /medical-records(+:id) 4 tabs y 5 modales; /specialty-records 5 especialidades; /ai-analysis lista+sheet+detalle.
Método: capas 2-3-4-5-6; roles admin/odontólogo/asistente + técnico negativo.
Entregable: render.json (4 roles), primitives.json, walkthrough.md, api-matrix.json, ux-rubric.md.
Criterio de salida: cero acciones de borrado clínico; estados vacíos con CTA; 403 del técnico en UI y API.
Relacionado: plan #13 §4-§5; #292; #296; #313; #314.
Criticidad: crit:alta · Área: area:frontend · Esfuerzo: L (dividir si no cabe)
```

---

### U8 — Catálogo de procedimientos (cuatro superficies)

**Rutas**: `/procedure-catalog`, `/:id`, `/procedure-stats`, `/my-procedures`,
`/reception-procedures`.

**Roles**: administrador (catálogo, estadísticas), finanzas (estadísticas,
recepción), clínicos (mis procedimientos), recepción (recepción).

**Acciones**: buscar y filtrar; crear procedimiento (aplicabilidad, códigos
externos, receta de materiales); editar; desactivar; duplicar; detalle con tabs
Datos/Tarifario/Versiones/Historial; alta de precio por sede con vigencia;
estadísticas: presets, Top N, CSV, gráficos; mis procedimientos: favorito
persistente; recepción: consulta sin edición; importar CSV (si la superficie
existe en UI).

**Hipótesis (INFERIDO)**: `ProcedureCatalogPage` usa `<table>` propio y
`ProcedureStatsPage` usa `UiDataTable`; verificar si dos listas del mismo módulo
se comportan distinto (orden, paginación, affordance).

**Criterio de salida**: el precio de sede se refleja en el picker; el CSV
coincide con lo mostrado; rúbrica; issues.

```text
[Auditoría/UI] U8 — Catálogo de procedimientos y tarifario
Superficies: /procedure-catalog(+:id), /procedure-stats, /my-procedures, /reception-procedures.
Método: capas 2-3-4-5-6; roles admin/finanzas/odontólogo/recepción.
Entregable: manifest.json, render.json, primitives.json, walkthrough.md, ux-rubric.md.
Criterio de salida: tarifario por sede aplicado y visible; CSV = pantalla; dos listas del módulo con el mismo comportamiento.
Relacionado: plan #13 §3; #293; #304; #210.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: M
```

---

### U9 — Administración y configuración (divisible: U9a clínica / U9b configuración)

**Rutas**: `/professionals`, `/:id`, `/environments`, `/:id`,
`/appointment-types`, `/:id`, `/specialties`, `/settings/branches`, `/:id`,
`/settings/payment-methods`, `/settings/accounting-locks`, `/branch-transfers`.

**Roles**: administrador; traslados: todos (solicitud) y gerencia (decisión).

**Acciones**: por cada ABM: listar, filtrar, crear, validar, editar, desactivar
(si no hay borrado), abrir detalle; especialidades: `code` inmutable; sucursales:
no eliminar; métodos de pago: familia + referencia + pasarela; bloqueo contable:
fijar y retirar con fecha efectiva y origen; traslados: solicitar, cancelar,
aprobar, rechazar, intentar decidir la propia.

**Hipótesis (INFERIDO)**: `ProfessionalsPage` y `AppointmentTypesPage` usan
`<table>` propio; el resto del escritorio usa `UiDataTable`. `BranchDetailPage` y
`ProfessionalDetailPage` no tienen placeholder de carga.

**Criterio de salida**: cada ABM verificado con datos propios; ninguna acción
destructiva ofrecida donde la API la prohíbe; rúbrica; issues.

```text
[Auditoría/UI] U9 — Administración y configuración (U9a clínica · U9b configuración)
Superficies: profesionales, ambientes, tipos de cita (ABM+detalle); especialidades, sucursales, métodos de pago, bloqueo contable, traslados.
Método: capas 2-3-4-5-6; rol administrador + un rol fuera para la puerta negativa.
Entregable: manifest.json, render.json, primitives.json, walkthrough.md, ux-rubric.md.
Criterio de salida: los 8 ABM sin callejón; acciones prohibidas ausentes; 0 pageerror.
Relacionado: plan #13 §3; #283; #304; #305; #210.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: L (dividir clínica/configuración)
```

---

### U10 — BI, continuidad y laboratorio

**Rutas**: `/business-intelligence`, `/continuity`, `/lab-cases`.

**Roles**: administrador, finanzas (BI); recepción/clínicos (continuidad,
laboratorio).

**Acciones**: BI: cambiar reporte, presets y rango, filtros de sede/profesional/
ambiente, exportar, consolidado por sede, gráficos al redimensionar; continuidad:
cola mixta, agendar (fecha preferida, conflicto), descartar con motivo, métricas;
laboratorio: filtrar por fase/técnico/vencido, crear y editar caso, avanzar fase,
oferta de solo fases posteriores, ver mensaje de fase inválida.

**Hipótesis (INFERIDO)**: BI es la página más larga del proyecto (1 115 líneas) y
la única con doble desglose; verificar si la densidad de filtros oculta el
reporte o si el estado vacío de un reporte sin datos explica qué hacer.

**Criterio de salida**: cada reporte con datos, vacío y error; export coincide
con pantalla; el avance de fase no retrocede; rúbrica; issues.

```text
[Auditoría/UI] U10 — BI, continuidad y laboratorio
Superficies: /business-intelligence (6 reportes, filtros, consolidado, export, gráficos), /continuity (cola, agendar, descartar), /lab-cases (lista, formulario, detalle, fase).
Método: capas 2-3-4-5-6; roles admin/finanzas y recepción/clínico.
Entregable: render.json, walkthrough.md, ux-rubric.md.
Criterio de salida: export = pantalla; estados vacío/error por reporte; fase solo avanza.
Relacionado: plan #13 §3; #303; #310; #316; #285.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: M
```

---

### U11 — Consistencia transversal UI/UX

**Superficies**: las 36 rutas, medida sobre los artefactos de U1–U10 y una
segunda pasada automática de primitivas.

**Objetivo**: encontrar la divergencia entre módulos hermanos, que ninguna sesión
individual puede ver.

**Matriz de comparación** (una fila por par de superficies, con captura):

| Eje                          | Pregunta                                                                                  |
| ---------------------------- | ----------------------------------------------------------------------------------------- |
| Diálogo                      | ¿El modal está centrado en todos los módulos? ¿Alguno abre al costado (`Sheet`)? ¿Por qué? |
| Lista                        | ¿`UiDataTable` o `<table>` propio? ¿Misma paginación, mismo affordance, mismo vacío?        |
| Título                       | ¿Un solo `h1`? ¿El topbar oculta el suyo donde la página ya titula?                         |
| Botonera                     | ¿Misma jerarquía (primario/secundario/terciario)? ¿Mismos verbos ("Guardar" vs "Aceptar")?  |
| Estados                      | ¿Los tres estados en todas? ¿Mismo esqueleto de carga?                                      |
| Feedback                     | ¿Toast vs inline? ¿Dónde cae el 422 de cada formulario?                                     |
| Filtros                      | ¿Se conservan al volver/entrar al detalle? ¿Hay "limpiar"?                                  |
| Badges y estados de dominio  | ¿Un solo `StatusBadge`/`StatusPill` y una sola etiqueta por estado?                          |
| Formato de fecha/hora/moneda | ¿Un solo helper? ¿Hay "S/ 1,200.00" y "S/1200" conviviendo?                                  |
| Responsive                   | Misma estrategia de tabla/desborde a 390 px.                                               |
| Densidad                     | Palabras de hero y subtítulo por módulo; ¿dónde sobra texto?                                |
| Repetición                   | ¿El mismo dato en KPI y tabla? ¿La misma advertencia en dos sitios?                         |

**Método**: `primitives.json` agregado de las 36 rutas + `grep` de vocabulario de
botones + muestreo de capturas + la rúbrica §5 puntuada por módulo en una matriz.

**Entregable**: `consistency-matrix.md` + un issue por clase de divergencia (no
por módulo) + propuesta de canon (qué primitiva gana).

**Criterio de salida**: cada fila de la matriz tiene veredicto y dueño; ningún
eje queda "depende".

```text
[Auditoría/UI] U11 — Consistencia transversal entre módulos
Superficies: las 36 rutas (artefactos de U1-U10 + pasada automática de primitivas).
Método: matriz de 12 ejes; agregación de primitives.json; muestreo de capturas; rúbrica por módulo.
Entregable: consistency-matrix.md + issues por clase de divergencia + canon propuesto.
Criterio de salida: 12 ejes con veredicto y dueño; toda divergencia con issue o aceptación escrita.
Relacionado: plan #13 §5; docs/ux-guidelines.md; #243; #242; #177.
Criticidad: crit:media · Área: area:frontend · Esfuerzo: M
```

---

### U12 — Consolidación y publicación

**Objetivo**: cerrar el programa: deduplicar, priorizar, publicar y dejar el
informe que responde "¿está perfecto o hay que arreglar?".

**Método**:

1. Cruzar `findings.md` de U1–U11 y agrupar por clase raíz (no por módulo).
2. Buscar duplicados en issues abiertos y cerrados (`gh issue list --state all`).
3. Publicar lo que falte (tope global: 25 issues; lo demás al backlog §8.4).
4. Puntuar el tablero final: superficies auditadas, veredictos, criterios en 0.
5. Escribir `docs/mejoras/13-informe-final-2026-10.md` con el veredicto por
   módulo y la lista de "sin defectos" con su evidencia.

**Criterio de salida**: cero hallazgos sin issue ni aceptación; informe con
veredicto por superficie; `odd/tasks/audit-ui-u12.md` con el cierre.

```text
[Auditoría/UI] U12 — Consolidación, deduplicación y publicación
Objetivo: cerrar el programa con todo hallazgo en issue o aceptado, e informe final por módulo.
Método: cruce de findings, dedupe contra gh issue list --state all, publicación (≤25), tablero final.
Entregable: docs/mejoras/13-informe-final-2026-10.md, issues publicados, nota ODD.
Criterio de salida: 0 hallazgo sin decisión; informe con veredicto por superficie; programa cerrado.
Relacionado: plan #13 completo; plan #12 §4.
Criticidad: crit:media · Área: area:docs · Esfuerzo: S
```

---

## 8. Protocolo de hallazgos e issues

### 8.1 Reglas

1. **Un issue por clase raíz**, nunca por síntoma ni por módulo.
2. **Evidencia mínima**: revisión + base + ruta + secuencia + captura. Sin eso,
   el hallazgo queda en backlog.
3. **Separar naturaleza**: defecto funcional (`type:defecto`), inconsistencia de
   diseño (`type:defecto`, `area:frontend`), superficie ausente
   (`type:no-terminado`), o verificación que falta (`type:verificacion-ausente`).
4. **Causalidad declarada**: preexistente o introducido, con sha.
5. **Tope por sesión**: 3–7 issues. Más cantidad = el triage no agrupó.
6. **La sesión no cierra sus propios issues**: cierra con la observación de la
   corrección, en otra sesión.
7. **"Sin defectos" es un resultado válido** y exige: render limpio en todos los
   roles, contrato de primitivas cumplido, rúbrica con todos los criterios ≥ 1 y
   las superficies primarias en 2, y walkthrough con todas las acciones `OK`.
   Se publica en el informe, no se calla.

### 8.2 Etiquetas

Se reutilizan las 27 existentes. **Creada** el 2026-10-23:

| Etiqueta           | Descripción                                                        |
| ------------------ | ------------------------------------------------------------------ |
| `auditoria-2026-10`| Hallazgo o unidad del programa de auditoría UI/funcional (plan #13) |

Las issues de sesión usan `type:eje` + `auditoria-2026-10` +
`crit:*` + `area:frontend` (o `area:docs` en U12). Los hallazgos usan
`type:defecto` / `type:no-terminado` / `type:verificacion-ausente` +
`auditoria-2026-10` + `crit:*` + `area:frontend` (o `area:backend` si la causa es
del contrato).

**Publicado**: la etiqueta `auditoria-2026-10` existe en el repositorio y las 13
unidades de sesión están registradas con el formulario de unidad (§8.5). Los
hallazgos que produzcan usa las etiquetas de naturaleza y el formulario de
defecto.

### 8.3 Plantillas

- **Sesión** → `.github/ISSUE_TEMPLATE/auditoria-unidad.yml` (campos: unidad,
  objetivo, método, entregable, criterio, relacionado, criticidad, área,
  esfuerzo, confirmaciones).
- **Hallazgo** → `.github/ISSUE_TEMPLATE/auditoria-defecto.yml` (11 campos, 10
  requeridos).

Los bloques de §7 ya están escritos en el orden y con los encabezados que el
formulario declara, para que el cuerpo publicado coincida con el render.

### 8.4 Backlog del programa (no publicado)

Hallazgos que no entran en el tope de 7 de su sesión, con motivo:
`bajo impacto`, `requiere decisión de producto`, `duplica #N` o `fuera de
alcance`. Se revisan en U12 y se publican o se aceptan por escrito.

### 8.5 Unidades publicadas (2026-10-23)

Las 13 sesiones quedaron registradas con el formulario de unidad, etiqueta
`auditoria-2026-10`, `type:eje` y la criticidad de su alcance.

| Sesión | Issue | Título                                                                    | Criticidad | Esfuerzo |
| ------ | ----- | ------------------------------------------------------------------------- | ---------- | -------- |
| U0     | #363  | Arnés de audit-test y línea base de las 36 rutas                          | `crit:alta`  | M        |
| U1     | #364  | Sesión, cuenta y shell                                                    | `crit:media` | M        |
| U2     | #365  | Dashboard y Mi día                                                        | `crit:media` | M        |
| U3     | #366  | Agenda y consulta                                                         | `crit:alta`  | L        |
| U4     | #367  | Pacientes: lista y ficha                                                  | `crit:alta`  | M        |
| U5     | #368  | Dinero: caja, cobros y reportes                                           | `crit:alta`  | L        |
| U6     | #369  | Presupuestos y planes de tratamiento                                      | `crit:alta`  | M        |
| U7     | #370  | Clínica: historia, especialidades y Análisis IA                           | `crit:alta`  | L        |
| U8     | #371  | Catálogo de procedimientos y tarifario                                    | `crit:media` | M        |
| U9     | #372  | Administración y configuración                                            | `crit:media` | L        |
| U10    | #373  | BI, continuidad y laboratorio                                             | `crit:media` | M        |
| U11    | #374  | Consistencia transversal entre módulos                                    | `crit:media` | M        |
| U12    | #375  | Consolidación, deduplicación y publicación                                | `crit:media` | S        |

**Verificación de publicación**: cada issue se leyó de vuelta desde GitHub; los
10 encabezados del formulario aparecen en el mismo orden y las etiquetas
coinciden con la tabla.

---

## 9. Guardarraíles de ejecución

| Guardarraíl                    | Detalle                                                                                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Base de datos                  | Solo `odontosuite_audit`, recreada por sesión. Prohibido `migrate:fresh` sobre la base de desarrollo.                                        |
| Escrituras                     | La sesión crea y borra **sus propias** filas. Nunca toca datos sembrados que otro flujo necesite (paciente demo, sesión de caja demo).      |
| Login y throttle               | 3 intentos/min por usuario + bloqueo de 10 min tras 5 fallos (`ThrottleLoginAttempts`): el arnés inicia sesión una vez por rol vía API y reusa el token en `localStorage`; el login real se prueba una sola vez. Probar el bloqueo deja al usuario 10 min fuera: usar un usuario descartable o `php artisan cache:clear` al terminar. |
| Reverb                         | `BROADCAST_CONNECTION=null` por defecto; el canal en vivo no es objeto de este programa (plan #12 A8).                                      |
| Correo                         | `MAIL_MAILER=log`; el reset se verifica leyendo `laravel.log`, no enviando correo.                                                           |
| Evidencia                      | `.atl/` es gitignored: no se commitean capturas ni JSON. Sobrevive la nota ODD y el issue.                                                  |
| Un escritor                    | Una sesión a la vez sobre la base de auditoría. Dos sesiones en paralelo exigen bases y worktrees distintos.                                 |
| Sin correcciones               | La sesión que audita no escribe código de producto; si el defecto bloquea el resto de la sesión, se documenta y se detiene ese tramo.       |
| Commits                        | Solo la nota ODD de la sesión (prefijo `docs/`), si el dueño quiere versionarla. Cero cambios de producto en el programa de auditoría.       |

---

## 10. Presupuesto y orden

| Fase | Sesión | Esfuerzo | Depende de  |
| ---- | ------ | -------- | ----------- |
| 0    | U0     | M        | —           |
| 1    | U1     | M        | U0          |
| 1    | U2     | M        | U0          |
| 2    | U3     | L        | U0, U1      |
| 2    | U4     | M        | U0, U1      |
| 3    | U5     | L        | U0, U1      |
| 3    | U6     | M        | U0, U1, U4  |
| 4    | U7     | L        | U0, U1, U3  |
| 5    | U8     | M        | U0          |
| 6    | U9     | L        | U0, U1      |
| 7    | U10    | M        | U0, U1      |
| 8    | U11    | M        | U1–U10      |
| 9    | U12    | S        | U1–U11      |

Una sesión = una jornada. Las cuatro `L` pueden partirse en dos, con el punto de
corte indicado en su sección. Total: 13 jornadas (17 si se dividen las cuatro).

---

## 11. Definición de terminado

1. Las 36 rutas clasificadas y auditadas al menos una vez con sesión real.
2. Las 13 sesiones con su artefacto, su nota ODD y su veredicto.
3. Cada superficie con la rúbrica §5 puntuada; cada criterio en `0` con issue.
4. Cada hallazgo en un issue con evidencia, o `aceptado` con motivo escrito.
5. Un issue por clase raíz; sin duplicados ni issues por síntoma.
6. `ui-pass --all` reproducible: cualquier sesión futura repite la línea base con
   un comando y compara contra el artefacto anterior.
7. Informe final con veredicto por módulo y la lista explícita de "sin defectos".

---

## 12. Anexo — Desviaciones estáticas medidas (INFERIDO, a confirmar en sesión)

Matriz de grep sobre las 34 páginas en `8b72d37`. No sustituye la medición en
navegador: señala dónde mirar primero.

| Página                      | `Skeleton` | `LoadingSpinner` | `EmptyState` | Error+retry | Lista        | Tabs | `<button>` propio |
| --------------------------- | ---------- | ---------------- | ------------ | ----------- | ------------ | ---- | ----------------- |
| AccountingLocksPage         | 2          | 0                | 2            | sí          | `UiDataTable`| 0    | 0                 |
| AiAnalysisPage              | 3          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| AppointmentTypeDetailPage   | 1          | 1                | 3            | sí          | —            | 4    | 1                 |
| AppointmentTypesPage        | **0**      | 1                | 3            | sí          | `<table>`    | 0    | 0                 |
| CalendarPage                | **0**      | 1                | 0            | sí          | —            | 0    | **7**             |
| LoginPage                   | **0**      | **0**            | 0            | sí          | —            | 0    | **3**             |
| BranchTransfersPage         | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| BusinessIntelligencePage    | 3          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| CashRegisterPage            | **0**      | **0**            | 0            | sí          | —            | 2    | 0                 |
| ReadyToBillPage             | **0**      | 2                | 0            | sí          | `UiDataTable`| 0    | 0                 |
| ContinuityPage              | 4          | 0                | 2            | sí          | `UiDataTable`| 0    | 0                 |
| DashboardPage               | 5          | 0                | **0**        | **21 marcas**| —           | 0    | 0                 |
| EnvironmentDetailPage       | 3          | 0                | 2            | sí          | —            | 3    | 0                 |
| EnvironmentsPage            | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| NotFoundPage                | 0          | 0                | 0            | no          | —            | 0    | 0                 |
| LabCasesPage                | 4          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| MedicalRecordsPage          | **0**      | 2                | **15**       | **18 marcas**| —           | 3    | 1                 |
| MyDayPage                   | 4          | 0                | 2            | sí          | —            | 0    | 0                 |
| MyProceduresPage            | **0**      | 3                | 4            | sí          | —            | 0    | 1                 |
| PatientDetailPage           | **0**      | **0**            | 0            | sí          | `UiDataTable`| 4    | 0                 |
| PatientsPage                | **0**      | 1                | 5            | sí          | `<table>`    | 0    | 0                 |
| ProcedureCatalogDetailPage  | **0**      | 3                | 3            | sí          | —            | 3    | 1                 |
| ProcedureCatalogPage        | **0**      | 2                | 3            | sí          | `<table>`    | 0    | 1                 |
| ProcedureStatsPage          | 4          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| ProfessionalDetailPage      | **0**      | **0**            | 3            | no          | —            | 0    | 1                 |
| ProfessionalsPage            | **0**      | 0                | 4            | no          | `<table>`    | 0    | 0                 |
| QuotationsPage              | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| ReceptionProceduresPage     | **0**      | 2                | 2            | sí          | —            | 0    | 1                 |
| BranchDetailPage            | 2          | 0                | **0**        | sí          | —            | 0    | 0                 |
| BranchesPage                | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| PaymentMethodsPage          | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| SpecialtiesPage             | 2          | 0                | 3            | sí          | `UiDataTable`| 0    | 0                 |
| SpecialtyRecordsPage        | 2          | 0                | 5            | sí          | —            | 3    | 0                 |
| TreatmentPlansPage          | 2          | 0                | 3            | sí          | —            | 0    | 0                 |

**Lecturas provisionales que las sesiones deben confirmar o refutar**:

- **P1 — Estados de carga**: 4 páginas sin `Skeleton` ni `LoadingSpinner`
  (Caja, Login, Ficha de paciente, Detalle de profesional). Si el contenido
  llega tarde, hay salto de layout. → U1, U4, U5, U9.
- **P2 — Listas divergentes**: 4 páginas con `<table>` propio frente a 14 con
  `UiDataTable`. Si la paginación, el vacío o el affordance de scroll difieren,
  es una clase raíz. → U4, U8, U9 y U11.
- **P3 — Diálogos**: 1 overlay propio (`ConsultationWizard`) y 1 flujo de alta en
  `UiSheet` (Análisis IA) frente a 18 módulos con `UiModal` centrado. Verificar
  si el usuario percibe dos lenguajes de "ventana". → U3, U7 y U11.
- **P4 — Densidad**: `MedicalRecordsPage` (15 marcas de vacío, 18 de reintento) y
  `DashboardPage` (21 marcas de reintento, 0 `EmptyState`) son los candidatos a
  "demasiado texto / estados que compiten". → U2, U7.
- **P5 — Vocabulario**: 1 archivo con overlay propio y 18 módulos con la
  primitiva; 0 `window.confirm`/`alert` en todo el frontend (esto último es un
  acierto medido, no una sospecha). → U11.

---

## 13. Changelog

- **2026-10-23** — Creación. Inventario medido en `8b72d37`: 25 módulos, 34
  páginas, 38 rutas (36 con pantalla), 35 primitivas, 108 smokes, 31 unitarios,
  2 specs de navegador con 8 tests y 7 de 36 rutas cubiertas. Se definen 6 capas
  de audit-test, una rúbrica UX de 12 criterios y 13 sesiones (`U0`–`U12`).
- **2026-10-23 (publicación)** — Con autorización del dueño se creó la etiqueta
  `auditoria-2026-10` y se publicaron las 13 unidades de sesión con el
  formulario de unidad: #363 (U0) a #375 (U12). Cada cuerpo se leyó de vuelta:
  los 10 encabezados del formulario coinciden en orden y las etiquetas
  (`type:eje`, `crit:*`, `area:*`) coinciden con §8.5. El repositorio no tenía
  issues abiertas antes de esta publicación.
