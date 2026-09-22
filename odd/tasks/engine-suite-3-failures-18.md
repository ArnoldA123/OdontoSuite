# Task: suite en motor a verde — los 3 fallos restantes (issue #18)

Status: DONE, PR abierto. Pendiente: merge (cierra #18).

## Medición

Suite contra MariaDB 10.4 por socket (`phpunit.mysql.xml` + exports, DB
`odontosuite_test` separada): **3 failed / 1094 passed** (la issue citaba 177
pre-fix; el resto cayó con PRs intermedios). Post-fix: **1097 passed, 0 failed**.

## Veredictos (medidos, no adivinados)

1. `EnvironmentsCanvasRoutesPrefixTest`: gana el source (template literal ≡
   canónico). Test ampliado a ambas formas.
2. `PatientsModalAppShellTest`: gana el source (renombre intencional 19a4542
   `can.updatePatient`→`can.editPatient`; el composable expone `editPatient`).
   Test actualizado.
3. `HotfixDashboardStaggerTest`: gana el test. `ab3d3ca` eliminó import + 4
   `useSpring` alcanzables (el riesgo que #14 advertía). Restaurados verbatim
   y, por decisión del usuario (opción A), cableados: 4 refs + onMounted con
   stagger 0/60/120/180ms. Hallazgo: eran dead code desde el hotfix (nunca
   cableados); cablearlos recupera el comportamiento HOTFIX-DASH-009.

## Evidencia

- 3 suites objetivo verdes + suite completa en motor: 1097 passed.
- `pnpm lint:check` exit 0, `pnpm build` exit 0.
- Nota: `php artisan test` sale exit 1 por warnings preexistentes de PHPUnit
  (metadata en doc-comments, deprecado hacia PHPUnit 12), no por fallos.
  Idéntico en main; follow-up separado (migrar a atributos).

## Espejo Engram

Observación pendiente al mergear.
