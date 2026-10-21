# ADR-0007 — User specialty source-of-truth: pivot over JSON

- **Status:** Accepted
- **Decided:** 2026-06-11 (Sprint 2, DM-6 of `plan-mejoras-futuras-2026-06`)
- **Body recorded:** 2026-10-21 (issue #264)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

A user's specialty lived in two places: `users.specialty`, a free-form string,
and `users.specialties`, a JSON column holding a list. Both were written by the
user form and read by reports, the calendar and the appointment filters.

Three problems:

1. **No integrity.** A JSON array cannot hold a foreign key, so nothing stopped
   `"ortodoncia"`, `"Ortodoncia"` and `"ortodoncia "` from coexisting as three
   different specialties. Every consumer that grouped or filtered by specialty
   inherited the drift.
2. **No multi-specialty model.** The catalog of specialties had become master
   data (`specialties`, with `code`, `name`, `is_active`) and a professional can
   hold more than one, with one of them primary.
3. **No queryability.** Filtering professionals by specialty had to scan JSON in
   PHP or use database-specific JSON operators.

## Decision

`user_specialties` is the source of truth. It is a pivot between `users` and the
`specialties` catalog:

| Column         | Purpose                                              |
| -------------- | ---------------------------------------------------- |
| `user_id`      | the professional                                     |
| `specialty_id` | foreign key to `specialties` (referential integrity) |
| `is_primary`   | marks the primary specialty (no database constraint) |

The pair `(user_id, specialty_id)` is unique, so a user can hold several
specialties but not the same one twice. A single primary is an application
convention, not a database constraint: nothing stops two rows from carrying
`is_primary = true`, and the accessor resolves that by taking the first.

Consequences for the two fields that existed before:

- `users.specialties` (JSON) was **dropped** in
  `2026_08_05_030000_drop_legacy_specialties_json_column.php` (slice 05 of
  `bugfix-2026-08`). It had never been populated by application code —the pivot
  was seeded from `users.specialty`, not from the JSON— and its `down()` only
  recreates an empty nullable column, so no data lives there.
- `users.specialty` (string) is **kept as a deprecated, denormalized display
  field**. It is not the source of truth and no read path may depend on it
  alone.

### Read contract

`User::getSpecialtyCodeAttribute()` is the only supported read of "the user's
specialty as a code": it returns the code of the row with `is_primary = true`,
falls back to the user's first pivot row, and returns `null` when the user has
none. API responses expose it as `specialty`:

```php
'specialty' => $user->specialty_code ?? $user->specialty,
```

### Write contract

Writes go to the pivot. `UserSpecialtySeeder` (#211) populates it from the
legacy string with an explicit map (`general` → `general`, `orthodontics` →
`ortodoncia`, `implantology` → `implantologia`) for clinical roles only, so a
fresh `migrate --seed` shows professionals in the catalog. The original pivot
migration already seeded `is_primary = 1` for every user whose `users.specialty`
matched a code in the catalog.

## Alternatives considered

- **Keep the JSON column.** Rejected: no referential integrity, no portable
  queries, and the catalog already existed as master data.
- **Keep only the string.** Rejected: a professional can hold several
  specialties and one of them must be primary; a string cannot express that
  without a parsing convention nobody would enforce.
- **Drop `users.specialty` at the same time.** Rejected: it is read by legacy
  screens and by the seeder mapping. Removing it requires the pivot to be
  complete in production data first, and that was not proven.

## Consequences

- Aggregations and filters read the pivot (`specialties.code`), not the string.
- `users.specialty` may drift from the pivot; that drift is a display issue, not
  a data-integrity issue, and it is what the seeder repairs.
- Consumers that still read `$user->specialty` directly keep working but keep
  reading the deprecated field; they should move to `specialty_code`.

## References

- `app/Models/User.php` — `specialties()`, `getSpecialtyCodeAttribute()`
- `database/migrations/2026_06_10_100000_create_specialties_table.php`
- `database/migrations/2026_06_10_100200_create_user_specialties_table.php`
- `database/migrations/2026_08_05_030000_drop_legacy_specialties_json_column.php`
- `database/seeders/UserSpecialtySeeder.php`
- `docs/database/schema.md` — source-of-truth summary
- `tests/Feature/Database/UserSpecialtySeederTest.php`
- `tests/Feature/Api/UserSpecialtySourceOfTruthTest.php`
