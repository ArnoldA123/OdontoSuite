# ADR-0008 — `procedure_catalog.legacy_specialty` deprecation

- **Status:** Accepted (deprecation in force; column drop pending)
- **Decided:** 2026-06-11 (Sprint 2, DM-7 of `plan-mejoras-futuras-2026-06`)
- **Body recorded:** 2026-10-21 (issue #264)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

`procedure_catalog` identified a procedure's specialty with a free-form string
column (`specialty`). When the catalog of specialties became master data
(`specialties`, with `code`, `name`, `is_active`), procedures needed the same
referential integrity users got. The column was renamed to `legacy_specialty`
and `specialty_id` (FK) was added next to it
(`2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php`,
`2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php`).

The string could not be dropped at once: pre-existing rows had no
`specialty_id`, CSV imports arrived with a specialty **code**, and the API
payload still exposed the old field.

## Decision

`procedure_catalog.specialty_id` is the source of truth. `legacy_specialty` is
**deprecated but retained** in the database and in the API response, and it is
read only as a last-resort fallback:

```php
public function getSpecialtyCodeAttribute(): ?string
{
    if ($this->specialty_id && $this->specialty) {
        return $this->specialty->code;
    }

    return $this->legacy_specialty;
}
```

- New writes must resolve a code to `specialty_id` (`ProcedureCsvImportService`
  maps `specialty_code` → `specialty_id` and drops the code from the payload; an
  unknown code leaves `specialty_id` null).
- The column stays because rows with `specialty_id = null` still need a readable
  specialty, and dropping it before a backfill would silently blank them.
- No new index, filter or report may group by `legacy_specialty`: reports join
  `specialties` and read `specialties.code`.

## Planned drop

Dropping the column is a separate change with three preconditions:

1. Backfill: every row with `specialty_id = null` resolved against the catalog
   (script, reviewed against a snapshot).
2. A verification query proving zero rows keep a legacy value without an FK.
3. Removal of the field from `ProcedureCatalogResource`, the store/update
   requests and the `$fillable` list, in the same change that drops the column.

Until those hold, `legacy_specialty` is deliberate debt, not an oversight.

## Alternatives considered

- **Drop the column immediately.** Rejected: rows without `specialty_id` would
  lose their specialty with no way to recover it, and the CSV import path would
  start writing nulls silently.
- **Keep the string as the source of truth.** Rejected: it is the drift ADR-0007
  already rejected for users — no integrity, no queryability, and typos that
  fragment reports.
- **Two parallel reads without a fallback order.** Rejected: without an explicit
  order (FK first, legacy last) two consumers return different specialties for
  the same row.

## Consequences

- The API keeps returning `legacy_specialty`; clients that read it keep working
  and stay coupled to a deprecated field.
- Every consumer that needs a specialty **code** must use `specialty_code`, not
  the raw column.
- The drop is a future migration with backfill, not a cleanup commit.

## References

- `app/Models/ProcedureCatalog.php` — `specialty()`,
  `getSpecialtyCodeAttribute()`
- `app/Http/Resources/ProcedureCatalogResource.php`
- `app/Services/ProcedureCsvImportService.php`
- `app/Services/ProcedureCatalogService.php` —
  `specialties.code as specialty_code`
- `database/migrations/2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php`
- `database/migrations/2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php`
- `docs/database/schema.md` — source-of-truth summary
