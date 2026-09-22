# Task: fixtures live para el probe A3 (issue #25, resto)

Status: DONE, PR abierto. Pendiente: merge (cierra #25).

## Resto acotado resuelto

- `QUERY_FIXTURES`: `patients/search?search=an`, `specialty-records` con specialty
  + patient_id reales (200 verificado contra seed).
- `PATH_FIXTURES`: 6 GET parametrizadas con `resolveFixture()` (ids reales del
  índice en vivo, cacheados; índice vacío → skipped honesto).
- Formato de salida aditivo; CLI intacta.

## Evidencia

- `--fail-on-mismatch`: exit 0. Rutas 101: 65 ok, 0 server-error, 0 unclassified
  (antes 57/2). Celdas 260: 175 ok, 85 denied.
- 36 skipped-needs-fixture restantes = rutas con `{param}` fuera de las 6
  (mutaciones o sin índice obvio): resto documentado, no inventado.

## Espejo Engram

Observación pendiente al mergear.
