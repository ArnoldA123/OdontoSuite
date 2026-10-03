# Task: saldar el backlog completo de issues

Status: CERRADO (programa saldado). Medido 2026-10-03: 62 issues en el repo,
0 abiertos. Documentos de features cerradas: `ci-quality-gates-15.md`,
`cierre-evidencia-14-19-20.md`, `remove-npm-lockfile-34.md` (con espejos Engram).

## Inventario (0 abiertas: #33 y #45 cerradas como ajenas al repo; el resto del programa cerrado)

| # | Tema | Paquete | Estado |
|---|---|---|---|
| #15 | Gates CI que no fallan | P0 hecho (PR #80 merge) | Cerrada (PR #80 merge) |
| #34 | Lock npm sobrante | P0 hecho (PR #81) | Cerrada (PR #81, cierra sola) |
| #17 | AGENTS.md describe CI inexistente | P1 docs CI | Cerrada (PR #82) |
| #71 | 3 suites guards a la deriva (lote 2) | P1 tests chicos | Cerrada (PR #83) |
| #41 | Guard baseline enumera fuentes | P1 tooling script | Cerrada (PR #84) |
| #79 | SQLite choca con dropColumn indexada | P1 datos | Cerrada (PR #85) |
| #32 | Decisión package-correct.json | P1 decisión dueño | Cerrada (issue cerrado) |
| #23 | Frontend sin verificación ejecutable | P3 vitest + smoke + CI | Cerrada (issue cerrado; doc `frontend-smoke-23.md`) |
| #18 | MySQL: 143 fallos restantes | P3 suite con MySQL local | Cerrada (issue cerrado; doc `engine-suite-3-failures-18.md`) |
| #27 | Eje A6 integridad/portabilidad | P3 (bloqueada por #79/#22) | Cerrada (issue cerrado) |
| #25 | Eje A3 contratos API | P4 probes | Cerrada (issue cerrado; doc `live-probes-25-26.md`) |
| #26 | Eje A4 matriz RBAC | P4 probes | Cerrada (issue cerrado; doc `live-probes-25-26.md`) |
| #28 | Eje A8 realtime | P4 probe realtime | Cerrada (issue cerrado; doc `realtime-a8-28.md`) |
| #30 | Eje A11 auditoría guards | P4 mutación | Cerrada (issue cerrado; doc `guard-mutation-a11-30.md`) |
| #29 | Eje A10 deuda estructural | P5 (último a propósito) | Cerrada (issue cerrado; doc `structural-debt-a10-29.md`) |
| #31 | Índice del programa (eje) | P5 mantener al día | Cerrada (PR #101; doc `ci-conformance.md`) |

## Orden

P0 (cierre/merge) → P1 (chico y medible) → P2 (verificar externo + documentar) →
P3 (trabajo real: vitest, MySQL, A6) → P4 (probes y matrices) → P5 (deuda + índice).

## Reglas

- Un paquete = una rama + un PR + evidencia medida. Nada se pisa: superficies
  disjuntas por paquete.
- Sin `|| echo`, sin reglas degradadas, sin números a mano en docs.
- Cada feature cerrada deja su `odd/tasks/*.md` + espejo Engram (topic_key).
