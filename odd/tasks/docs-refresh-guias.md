# Task: guías verificadas + tokens success/warning (docs refresh)

Status: DONE, PR abierto. Pendiente: merge.

## Guías (verificadas contra medición)

- README/INSTALACION: pnpm-only, Node 22, `migrate --seed`, login por username
  demo, `composer dev`, suite MySQL, core.longpaths, sin EssentialDataSeeder.
- ux-guidelines: valores del código (acento #0f7a5f, shadows, radius, rutas).
- AGENTS §10: Vitest bloqueante.
- CREDENTIALS.md NO tocado (cambio preexistente del dueño preservado).

## Fix real hallado en la pasada

`--color-success-bg/-text` y `--color-warning-bg/-text` no existían (un barrido
"dead token" los eliminó mirando solo `resources/`); 5 componentes con badges
rotos. Restauradas las familias en el generador + rebuild (no a mano).

## Evidencia

- CredentialsDocumentationTest 9 passed, AgentsDocsSyncTest 5 passed,
  GeneratedTokensCssTest 22 passed, doc-drift 12 checks, build exit 0.

## Espejo Engram

Observación pendiente al mergear.
