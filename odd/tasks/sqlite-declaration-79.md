# Task: `migrate:fresh` declarado solo-MySQL + derivas de seeders (issue #79)

Status: DONE, PR abierto. Pendiente: merge (cierra #79).

## Alcance

Declaración ejecutable (no solo prosa) + 2 derivas de conteo encontradas al medir.

## Tareas

- [x] Reproducir el fallo en SQLite temporal: mensaje exacto de #79
- [x] AGENTS.md §6: bullet SQLite → declaración solo-MySQL (#79), sin cifras,
      apuntando al test que la fija
- [x] AGENTS.md §8: fila DDL→camino soportado (conserva `docker compose` y
      `--group=mysql` para BF-027); fila seeders 11/24 → 14/22
- [x] AGENTS.md §4: `_legacy` 24 → 22 (medido: 22 archivos)
- [x] doc-drift.mjs: nueva fila `legacy-seeders` (patrón ajustado a ambos
      órdenes `N legacy` / `Legacy: N`, sin falsos positivos)
- [x] Test nuevo `SqliteMigrateFreshDeclarationTest` (2 passed): fija la
      limitación observada y exige la declaración de §6
- [x] Verificar: doc-drift exit 0 (12 checks), AgentsDocsSyncTest 5 passed
- [ ] Merge (cierra #79)

## Espejo Engram

Observación pendiente al mergear.
