# ADR-0008 — procedure_catalog.legacy_specialty

> **Fecha**: 2026-06-11
> **Estado**: Aceptado (Sprint 2 del plan-mejoras-futuras-2026-06.md, hallazgo DM-7)
> **Contexto**: OdontoSuite V2

## Contexto

`procedure_catalog` tiene **dos** campos para la especialidad:

1. `legacy_specialty` (string(50), libre) — versión original, agregada en migración inicial.
2. `specialty_id` (FK a `specialties`) — creada en el plan de catálogo (`2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php`) tras el maestro de especialidades.
3. La columna `specialty` original fue **renombrada** a `legacy_specialty` en `2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php` para limpiar la confusión.

Auditoría de lectores (Sprint 2):

| Archivo | Líneas | Tipo de uso |
|---|---|---|
| `app/Http/Controllers/Api/ProcedureCatalogController.php` | 118, 137, 142 | Auditoría (old/new values) |
| `app/Http/Requests/StoreProcedureCatalogRequest.php` | 21 | Validación de input |
| `app/Http/Requests/UpdateProcedureCatalogRequest.php` | 23 | Validación de input |
| `app/Http/Resources/ProcedureCatalogResource.php` | 20 | Respuesta JSON al frontend |
| `app/Models/ProcedureCatalog.php` | 22 | Fillable |
| `app/Services/ProcedureCatalogService.php` | 205 | Fillable en service |
| `database/seeders/ProcedureCatalogSeeder.php` | 112 | Seeder (lee `legacy_specialty` para popular) |
| `database/migrations/2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php` | - | Migración (rename) |

Total: **9 referencias activas**. El campo está vivo, no es código muerto.

## Decisión

**No hacer drop en este sprint.** Razones:

1. El `ProcedureCatalogSeeder` lo usa para popular los 40 procedimientos demo. Eliminarlo rompería el seeding.
2. `ProcedureCatalogResource` lo expone al frontend (consumidores externos pueden leerlo).
3. La FK `specialty_id` debería estar 100% poblada antes de poder hacer drop. La auditoría actual no verifica esto en producción.

**En su lugar**:
- Marcar el campo con `@deprecated` (docblock) en modelo, requests y resource.
- Agregar accessor `getSpecialtyCodeAttribute()` que prioriza `specialty.code` (FK) y cae a `legacy_specialty` como último recurso.
- Dejar la columna en BD sin cambios.
- Documentar el path futuro para drop.

## Consecuencias

### Positivas
- Sin breaking changes para consumidores del frontend.
- El accessor `specialty_code` da una API limpia para código nuevo.
- Riesgo bajo: solo se agregaron comentarios y un accessor.

### Negativas / trade-offs
- La columna `legacy_specialty` sigue en BD, ocupa espacio (~40 filas × varchar(50) = ~2KB, irrelevante).
- Riesgo de drift: si alguien escribe `procedure->update(['legacy_specialty' => 'X'])` y NO actualiza `specialty_id`, queda inconsistente.

## Alternativas consideradas

### A) Drop completo + backfill
**Rechazado** porque:
- No podemos garantizar que `specialty_id` esté 100% poblado en producción sin un script de backfill.
- El seeder no funcionaría sin reescribirlo.
- Riesgo de romper la auditoría que lee del campo.

### B) Drop + nullable FK + data migration
**Rechazado** por blast radius. La forma correcta es B pero necesita un sprint dedicado con testing de regresión.

### C) Solo marcar deprecated (esta decisión)
**Aceptado** por equilibrio entre deuda resuelta y riesgo. El drop se hará en un sprint futuro con script de backfill verificado.

## Plan de drop (futuro)

Para el sprint que haga el drop definitivo:

1. **Backfill**: script que verifique que `COUNT(*) WHERE specialty_id IS NULL = 0`. Si hay nulos, asignar specialty_id vía lookup por `legacy_specialty`.
2. **Migración**: `ALTER TABLE procedure_catalog DROP COLUMN legacy_specialty`.
3. **Cleanup**: quitar referencias en requests, resource, service.
4. **Verificación**: `php artisan test` + smoke test del CRUD de procedure-catalog.

## Referencias

- Plan-mejoras-futuras-2026-06.md §3 hallazgo DM-7
- Plan de catálogo Sprint 1 (`docs/mejoras/plan-flujo-catalog-procedimientos.md`)
- Migración FK: `database/migrations/2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php`
- Migración rename: `database/migrations/2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php`
