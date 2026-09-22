# Task: saldar el backlog completo de issues

Status: EN CURSO. Documentos de features cerradas: `ci-quality-gates-15.md`,
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
| #32 | Decisión package-correct.json | P1 decisión dueño | Preguntada |
| #23 | Frontend sin verificación ejecutable | P3 vitest + smoke + CI | Pendiente |
| #18 | MySQL: 143 fallos restantes | P3 suite con MySQL local | Pendiente |
| #27 | Eje A6 integridad/portabilidad | P3 (bloqueada por #79/#22) | Pendiente |
| #25 | Eje A3 contratos API | P4 probes | Pendiente |
| #26 | Eje A4 matriz RBAC | P4 probes | Pendiente |
| #28 | Eje A8 realtime | P4 probe realtime | Pendiente |
| #30 | Eje A11 auditoría guards | P4 mutación | Pendiente |
| #29 | Eje A10 deuda estructural | P5 (último a propósito) | Pendiente |
| #31 | Índice del programa (eje) | P5 mantener al día | Pendiente |

## Orden

P0 (cierre/merge) → P1 (chico y medible) → P2 (verificar externo + documentar) →
P3 (trabajo real: vitest, MySQL, A6) → P4 (probes y matrices) → P5 (deuda + índice).

## Reglas

- Un paquete = una rama + un PR + evidencia medida. Nada se pisa: superficies
  disjuntas por paquete.
- Sin `|| echo`, sin reglas degradadas, sin números a mano en docs.
- Cada feature cerrada deja su `odd/tasks/*.md` + espejo Engram (topic_key).
