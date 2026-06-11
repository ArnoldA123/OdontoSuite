# ADR-0007 — User::specialty source-of-truth

> **Fecha**: 2026-06-11
> **Estado**: Aceptado (Sprint 2 del plan-mejoras-futuras-2026-06.md, hallazgo DM-6)
> **Contexto**: OdontoSuite V2

## Contexto

OdontoSuite V2 arrastraba **tres** formas de representar las especialidades de un usuario:

1. `users.specialty` (string, 1 sola especialidad) — columna en BD, `string(50)` nullable. Agregada en migración `2025_09_20_093115_add_specialty_and_phone_to_users_table.php`. Llenada por seeders legacy.
2. `users.specialties` (JSON, varias) — declarada en el `User::$fillable` y `User::$casts => 'array'`, pero **la columna nunca se creó en BD**. Es código muerto que confunde.
3. `user_specialties` (pivote) — creada en el plan de catálogo (`2026_06_10_100200_create_user_specialties_table.php`). Modelo `Specialty` con `belongsToMany`. Soporta `is_primary` para indicar la especialidad principal.

Esto significa que una consulta como `$user->specialty` puede devolver el valor legacy desactualizado, no el valor real de la pivote.

## Decisión

**Source-of-truth**: tabla pivote `user_specialties` (con FK a `specialties`).

**`users.specialty` (string)**: se mantiene como **display denormalizado** por compatibilidad con el frontend (`UserController` lo expone en 4 endpoints). Se sincroniza via accessor `getSpecialtyCodeAttribute()`:
- Si la pivote tiene specialty primaria → devuelve su `code`.
- Si no → devuelve la primera specialty de la pivote.
- Si la pivote está vacía → devuelve `null`.

**`users.specialties` (JSON)**: **eliminado** del `$fillable` y del `$casts`. La columna no existe en BD, era código muerto que invitaba a errores.

## Consecuencias

### Positivas
- Una sola fuente de verdad para las especialidades de un usuario.
- El frontend sigue recibiendo el campo `specialty` (string) en las respuestas de `UserController` sin breaking changes.
- El accessor `specialty_code` permite al código nuevo leer de la pivote sin pasar por el campo legacy.

### Negativas / trade-offs
- `specialty` (string) sigue siendo display denormalizado. Si alguien escribe `$user->update(['specialty' => 'X'])` directamente, no se sincroniza con la pivote. La forma correcta es `$user->specialties()->sync([...])`.
- El accessor `specialty_code` ejecuta 1 query cada vez que se accede. Si se necesita en una lista grande, eager-load con `$query->with('specialties')`.

## Alternativas consideradas

### A) Drop completo de `users.specialty` (string)
**Rechazado** porque rompería 4 endpoints de `UserController` que el frontend consume. Habría que migrar el frontend en el mismo sprint. Mayor blast radius sin ganancia proporcional.

### B) Migrar `users.specialty` → `specialties.code` via JOIN en cada query
**Rechazado** porque agrega JOIN a queries simples. La solución del accessor es más performante (1 query cuando se eager-load, o 0 con cache).

### C) Trigger en BD que sincronice `users.specialty` con la pivote
**Rechazado** por complejidad. El accessor en PHP es simple y suficiente.

## Referencias

- Plan-mejoras-futuras-2026-06.md §3 hallazgo DM-6
- Migración pivote: `database/migrations/2026_06_10_100200_create_user_specialties_table.php`
- Plan de catálogo Sprint 1 (`docs/mejoras/plan-flujo-catalog-procedimientos.md`)
- Commits: pendientes en este sprint

## Próximos pasos

1. (Sprint 4) Evaluar si conviene un endpoint API que devuelva `specialties[]` (todas las del usuario, vía pivote) además de `specialty` (la primaria, vía accessor).
2. (Sprint futuro) Considerar un Observer que sincronice `users.specialty` automáticamente cuando se hace `specialties()->sync([...])`. Hoy hay que llamar manualmente al accessor en código de escritura.
