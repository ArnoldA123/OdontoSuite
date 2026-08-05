# Architecture Decision Records — OdontoSuite

This index is the entry point to the project's ADRs. Each ADR captures a
material architectural decision, its context, and its consequences.

## Index

| ADR | Title | Status |
|---|---|---|
| [0007](./0007-user-specialty-source-of-truth.md) | User specialty source-of-truth — pivot over JSON | Accepted |
| [0008](./0008-procedure-catalog-legacy-specialty.md) | Procedure catalog `legacy_specialty` deprecation | Accepted |
| 0009 *(pending — slice 05)* | Migration drift: additive-only policy + driver-conditional guards | Accepted 2026-08-05 |

## Active change: `bugfix-2026-08` migration-drift subsection

Per the slice 05 spec, every new migration in `bugfix-2026-08` MUST follow the
additive-only policy enforced by `tests/Unit/SddCheckMigrationsTest.php`. The
driver-conditional guard precedent (set by slice 02 in
`2025_10_14_123001_fix_appointments_status_enum.php`) is now extended to:

- `2026_06_02_173228_fix_appointments_timezone_offset.php` — wrapped in
  `DB::getDriverName() === 'sqlite' return;` guard.

New migrations introduced by slice 05:

- `2026_08_05_030000_drop_legacy_specialties_json_column.php` — drops the
  legacy `users.specialties` JSON column (reversible via `down()`).
  Filename uses the `drop_legacy_*` prefix which is the only carve-out from
  the additive-only policy.

See `openspec/changes/bugfix-2026-08/findings-map.md` for the full per-slice
migration inventory.