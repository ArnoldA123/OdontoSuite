# Architecture Decision Records — OdontoSuite

This index is the entry point to the project's ADRs. Each ADR captures a
material architectural decision, its context, and its consequences.

## Index

| ADR                                                  | Title                                                              | Status                          |
| ---------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------- |
| [0007](./0007-user-specialty-source-of-truth.md)     | User specialty source-of-truth — pivot over JSON                   | Accepted                        |
| [0008](./0008-procedure-catalog-legacy-specialty.md) | Procedure catalog `legacy_specialty` deprecation                   | Accepted (deprecation in force) |
| 0009                                                 | Migration drift: additive-only policy + driver-conditional guards  | Accepted 2026-08-05 — see below |
| [0010](./0010-branch-scope-authorization.md)         | La sede es una dimensión de autorización                           | Accepted                        |
| [0011](./0011-quotation-igv-policy.md)               | El IGV de un presupuesto se descompone del precio                  | Accepted                        |
| [0012](./0012-catalog-applicability-and-tariff.md)   | El catálogo declara sobre qué se hace y a qué precio, con vigencia | Accepted                        |

0009 has no separate body yet. Its decision is enforced by
`tests/Unit/SddCheckMigrationsTest.php`, which scans `database/migrations/` and
fails on the forbidden patterns the policy names. The policy belongs to the
closed change `bugfix-2026-08` (slice 05); treat its spec as the historical
record, not as pending work.

## Historical notes

The additive-only policy arrived with `bugfix-2026-08`, archived under
`openspec/changes/archive/bugfix-2026-08/`. Those migrations are already applied
and are not rewritten; the per-slice inventory lives in the archived change, not
here.

## Conventions

- Files are numbered and named `NNNN-kebab-case-title.md`.
- Status is one of `Proposed`, `Accepted`, `Accepted (deprecation in force)`,
  `Superseded by ADR-NNNN`, `Rejected`.
- Every decision recorded here describes the code as it is. A decision that is
  planned but not implemented belongs in an issue, not in an ADR.
- `docs/architecture/decisions/README.md` is the only index. A row may point to
  a file that must exist, or carry no link when the decision is enforced by code
  or tests.
