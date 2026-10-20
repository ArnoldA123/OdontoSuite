# UI primitives polish — #177 / #208 / #209

Rama: `fix/ui-primitives-177-208-209` (desde `origin/main` @ 3cc3bd9)
Worktree: `/home/chomb/proyectos/odontosuite-ui-primitives`
Creado: 2026-10-21 · Orquestador: el Gentleman

## Specs

- **S1.** "quiero hacer issues #209 #208 #177 desarrolles, en un worktree nuevo" — las tres correcciones se desarrollan juntas, en un worktree aislado y una rama nueva desde `origin/main`.
- **S2.** #209 — "fix(ui): UiDataTable no ofrece affordance de scroll horizontal en móvil". Criterios: "A 390 px existe una affordance visible de scroll horizontal (o las acciones son alcanzables sin scroll)."; "Ninguna página gana overflow a nivel de documento."; "Cobertura en el smoke del primitivo o en un guard de diseño." La propuesta manda resolverlo en `DataTable.vue` ("no por página") manteniendo el scroll interno.
- **S3.** #208 — "fix(ui): el topbar duplica el título del hero en las páginas rediseñadas". Decisión del dueño del título (respuesta del usuario en esta sesión): "Hero (recomendado)" — "El topbar no pinta el título visible en rutas con hero band; conserva un h1 sr-only y muestra la descripción corta como contexto. Cero cambios en páginas de módulo; los contratos de jerarquía siguen verdes." Criterios: "Una superficie rediseñada no muestra el mismo título dos veces."; "Los tests de jerarquía (`h1`/`h2`, `data-hero-title`) siguen verdes."; "Sin cambios de ruteo ni de navegación funcional."
- **S4.** #177 — "refactor(ui): UiModal no admite guardia de cambios ni hooks de raíz". Propuesta: "Reenviar atributos al nodo raíz teleportado (`inheritAttrs` + `v-bind=\"$attrs\"`)."; "Añadir un hook de cierre cancelable (`before-close` o evento equivalente) que el consumidor pueda interceptar."; "Cubrir ambas con un test de la primitiva y documentarlas en su contrato." Criterio de salida: "`TreatmentPlanModal` y `CreatePatientInline` usan `UiModal` sin perder la guardia de cambios ni el hook de raíz, y sus smokes del módulo siguen en verde."
- **S5.** Entrega elegida por el usuario: "Un PR con 3 commits (recomendado)" — "Tres work-unit commits, uno por issue, en la rama del worktree nuevo; push y un PR que referencia #177 #208 #209."
- **S6.** Convenciones heredadas de `AGENTS.md` §7: código, tests, commits y docs de repositorio en inglés; copy de UI en español; `pnpm` siempre; commits semánticos sin emojis; "UI: consumir SIEMPRE de `components/ui/`. NO botones/modales ad-hoc."; sin comentarios pedagógicos.

## Tasks

| ID  | Módulo            | Specs | Ruta   | Commit                                                      |
| --- | ----------------- | ----- | ------ | ----------------------------------------------------------- |
| T1  | #209 `UiDataTable` | S2    | inline | `4d03eb8` `fix(ui): signal horizontal scroll in the data table` |
| T2  | #208 `AppLayout`   | S3    | inline | `d186ed7` `fix(ui): let the hero own the visible page title`    |
| T3  | #177 `UiModal`     | S4    | delegado (worker) | `refactor(ui): support root hooks and a cancellable close in UiModal` |

Presupuesto de revisión: el pronóstico ronda 500-700 líneas autoradas; el usuario ya eligió
la estrategia `single-pr` con tres commits (S5), así que no se re-pregunta.

## Log

- **L1.** "quiero hacer issues #209 #208 #177 desarrolles, en un worktree nuevo"
- **L2.** Decisión #208: hero dueño del título visible; topbar conserva h1 sr-only + descripción como contexto.
- **L3.** Entrega: un PR con tres commits; tras crear el worktree se instalan `vendor/` y `node_modules/` locales.
- **L4.** Slice boundaries (heuristic only, strategy `single-pr` already chosen): T1 470 líneas autoradas, T2 acumulado 682; el re-indentado del template de `DataTable.vue` explica la mayor parte del primer slice. Sin re-trabajo por tamaño.
- **L5.** Evidencia T1: RED `data-table-scroll-affordance.test.js` (5 fallos por hooks ausentes), GREEN 5/5; suite JS 93 archivos / 777 tests. Evidencia T2: RED 26/26, GREEN 26/26; suite JS 93/777; `tests/Unit/DesignSystem` 874 tests.
- **L6.** T3: RED de la primitiva 8 fallos / 2 pasan, GREEN 15/15 (10 guards + 5 static mount). El worker detectó que `TreatmentPlansPage` cerraba `showModal` con Escape de documento y saltaba la guardia de cambios: se retiró esa rama (la página ya no cierra overlays) y se añadió el guard de página a `treatment-plans.test.js`; RED verificado revirtiendo el archivo (1 fallo), GREEN 8/8. Suite JS final 95 archivos / 791 tests; DesignSystem 874; doc-drift 12/12.
- **L7.** Verificación independiente (`gentle-ai-verify`) sobre `origin/main...HEAD`: 4 hallazgos bajos. F1 (regresión real): Enter dejó de enviar los formularios migrados porque el botón pasó a `type="button"` fuera del `<form>`; se corrigió con `type="submit"` + `form="treatment-plan-form"` / `form="create-patient-form"` y tests de asociación. F4: `DataTable` no remedía con cambios de columnas; se añadió el watcher y su test. F2/F3 son preexistentes (`ProfessionalDetailPage` con h1 propio fuera de la convención; rutas de detalle con h1 de página + h1 sr-only del topbar) y quedan como follow-ups. Se añadió cobertura para guardia que lanza, guardia con retorno no booleano y balance del bloqueo de scroll al desmontar con guardia pendiente. El flag `destroyed` propuesto para evitar emisiones tras el unmount se descartó: Vue ya no emite en instancias desmontadas, verificado con una corrida instrumentada.
