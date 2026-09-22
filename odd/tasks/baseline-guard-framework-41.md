# Task: el guard del baseline pregunta al framework (issue #41)

Status: DONE, PR abierto. Pendiente: review nativo del candidato, merge.

## Alcance

`scripts/audit/baseline.sh` (guard de pérdida de datos) + sus tests. Las fuentes
estáticas quedan como segunda red; ningún otro archivo del script cambia.

## Tareas

- [x] Mapear: `database_guard_decision()` enumeraba 4 fuentes; cada ciclo de
      revisión encontraba la siguiente (comillas, inline comment, case, DB_URL)
- [x] Nuevo helper `framework_database()`: corre el probe con DB_DATABASE/DB_URL
      exportadas (exportado gana, archivo si no, vacío si ninguna), pregunta
      `DB::connection("mysql")->getConfig("database")` vía tinker
- [x] La regla del framework es primaria; `unavailable` → refuse (fail closed);
      el camino sin docker rechaza cualquier `refuse:*` (antes solo conocía dos)
- [x] 5 casos sintéticos verificados (procede, rechaza ×2, URL gana, seam prueba
      que sigue al framework, probe caído → unavailable)
- [x] 4 tests nuevos en AuditBaselineGuardTest (34 → 38), incluida la
      falsificación: sin la regla, el test sigue-al-framework FALLA
- [x] Commit + PR + merge (cierra #41)
- [ ] Review nativo del candidato antes de reportar completo (RDD on)

## Evidencia

- Casos a–e con `app_database_framework=` y `decision=` observados
- Suite: 38 passed (88 assertions)
- `bash -n` OK; diff: 79 inserciones, 0 borrados en el script

## Espejo Engram

Observación `baseline-guard-framework-41` (bugfix). Detalles de gotchas ahí.
