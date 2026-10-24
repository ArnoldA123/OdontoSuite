# Issue #157 — pendientes de Estadísticas del Catálogo

Siete items abiertos al cerrar #151/#155: aserciones frágiles, una regla visual sin fijar, cobertura de borde del umbral de etiquetas, jerarquía de encabezados, guard responsive y housekeeping de rama. Rama `chore/procedure-stats-pending-157`, worktree `~/proyectos/odontosuite-issue-157`.

## Specs

S1 (issue #157, item 1). "parsear el porcentaje y comparar numéricamente, o exponer el valor por `data-*` y afirmarlo." Decidido: exponer `data-specialty-bar-percent` en la barra y afirmar igualdad numérica con el badge.

S2 (issue #157, item 2). "decidir la regla (piso solo cuando el badge redondea a 0, o mínimo visible menor) y fijarla con un caso." Decidido: una participación no nula nunca se lee "0 %" junto a una barra dibujada; el badge muestra `<1%` cuando el redondeo da 0 y la barra conserva el piso visible de 2 %.

S3 (issue #157, item 3). "una sola derivación (`share` redondeado una vez) y `barWidth` como función explícita de ella."

S4 (issue #157, item 4). "cubrir 419/421 y documentar o resolver el caso resize." Decidido: resolver — el ancho se reevalúa en `options.onResize` y las etiquetas se recalculan al cambiar de tramo.

S5 (issue #157, item 5). "decidir jerarquía (shell con rol de texto o página con `<h2>`) y aplicarla a las páginas con hero band." Decidido: el `<h1>` del shell sigue siendo el único del documento; el hero band pasa a `<h2>` en las 8 páginas con hero.

S6 (issue #157, item 6). "un spec de Playwright en `tests/e2e/` (ya existen `smoke.spec.js` y la dependencia `playwright`) que a 390 px verifique: etiquetas del eje sin recorte, `scrollWidth <= clientWidth + 1` en los botones del hero, y dos `canvas` con píxeles dibujados (`getImageData`)."

S7 (issue #157, item 7). "`git branch -d feat/appointment-types-interface-redesign`." Se ejecuta en el worktree principal, no en la rama de la feature.

Fuera de alcance: `chore/close-format-debt`.

## Tasks

| ID  | S#      | Route  | Commit                                            |
| --- | ------- | ------ | ------------------------------------------------- |
| T1  | S1-S3   | inline | `435e8d9 fix(procedure-stats): align specialty badge with its bar` |
| T2  | S4      | inline | `669873c fix(procedure-stats): recalculate chart labels on resize` |
| T3  | S5      | inline | `8604bd2 fix(a11y): one h1 per document on hero pages` |
| T4  | S6      | inline | `41ebca4 test(e2e): guard procedure-stats responsive layout` |
| T5  | S7      | inline | n/a (main worktree; branch deleted, was 862f416)   |
| T6  | S1-S6   | inline | verification: vitest 543/543, lint 0 errors (44 baseline warnings), format, build, doc-drift, Playwright |

## Log

L1 (usuario, verbatim): "haz la issue 157 en un worktree nuevo"
L2 (decisión, item 1): `data-specialty-bar-percent` en la barra; el test compara números, no subcadenas de `style`.
L3 (decisión, item 2/3): `sharePct` se deriva una sola vez; `barWidth` es función explícita (`barWidthFor`); el badge de una participación no nula con `sharePct === 0` muestra `<1%`.
L4 (decisión, item 4): el tramo de etiqueta se recalcula en `options.onResize` de Chart.js; el test cubre 419 y 421 e invoca el callback.
L5 (decisión, item 5): el `<h1>` del shell es el título del documento (varias páginas no tienen otro); el hero band baja a `<h2>` y conserva sus clases visuales.
L6 (alcance): el spec de Playwright sigue el patrón de `smoke.spec.js` (login por API + token en `localStorage`); solo cubre `/procedure-stats` a 390 px.
L7 (evidencia, RED/GREEN): el badge `<1%` falló primero con `0%`; `onResize` falló con `usageConfig.options.onResize is not a function`; el `<h2>` del hero falló con 2 `<h1>`; el guard de recorte falló con 33 píxeles pintados en las columnas 0-1 al subir el presupuesto angosto a 60.
L8 (dato): el seeder local no tiene usos en ningún período (`top_procedures` y `by_specialty` vacíos incluso 2020-2030), así que el spec sirve un payload fijo con `page.route` en vez de depender de la base local.
L9 (observación fuera de alcance): `components/layout/PageHeader.vue` también renderiza un `<h1>` bajo el shell, así que las 9 páginas que lo usan conservan dos `<h1>`. El issue acotó S5 a las páginas con hero band; queda como follow-up.
