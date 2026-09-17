# Task: axis A2 — the MySQL suite failures, grouped by root class

Status: first half measured. This session produced the local distribution on
MariaDB 10.4. The distribution on MySQL 8.0 (the engine CI uses, and the engine
issue #18 was written about) is still unmeasured, because the container engine
cannot start on this machine (see "What is missing").

Plan #12 §2 defines this axis: "los ~143 fallos restantes no son bugs
independientes; se concentran en pocas clases raíz (modo estricto de MySQL,
contexto `$this`, artefactos frontend ausentes en el runner, lecturas de archivo
fuente)". The measurement below confirms the first hypothesis and rejects the
idea that they are independent bugs.

## Method

1. Capture the MySQL-runner output: `bash scripts/audit/baseline.sh`, which runs
   `php artisan test --configuration=phpunit.mysql.xml` and keeps the raw output
   in `.atl/qa-evidence/audit/raw-<date>-<time>/mysql.txt`.
2. Strip the ANSI escapes: `sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g'`.
3. Split the output into `FAILED` blocks (`^ +FAILED +Tests\… > <test name>`).
4. For each block, take the first line that looks like an error, reduce it to a
   signature (SQLSTATE code plus message text up to the `(Connection:` tail,
   digits collapsed to `#`) and group blocks by that signature. Field names
   inside `Field 'X' doesn't have a default value` are counted separately,
   because they distinguish sub-causes of one root class.

The classifier was ad-hoc and lives outside the repository. Turning it into
`scripts/audit/cluster-failures.mjs` is the next step of this axis; until then
these figures are reproducible by following steps 1–4, not by one command.

## Measured distribution

Engine: **MariaDB 10.4.32** (XAMPP, `127.0.0.1:3306`), reached through the
documented overrides. Revision `8f61307`. Totals: **71 failures, 19 signatures**.

| # | Failures | Root class | Example |
| --- | --- | --- | --- |
| 1 | **28** | `SQLSTATE[HY000] 1364 Field 'first_name' doesn't have a default value` | `Unit\Models\AppointmentTest > it can create an appointment` |
| 2 | **15** | `SQLSTATE[HY000] 1364 Field 'city' doesn't have a default value` | `Feature\Api\CashMovementPermissionTest > administrador can post cash movement` |
| 3 | 3 | `Expected 4/5 but got 5/4` (audit-log middleware) | `Feature\Api\AuditLogControllerTest > post audit logs returns 405 not 500` |
| 4 | 3 | `Call to a member function info() on null` | `Feature\Api\SpecialtyRecordSeederTest > seeder creates records across all five concrete models` |
| 5 | 2 | `SQLSTATE[HY000] 1364 Field 'name' doesn't have a default value` | `Feature\Api\AuditLogFiltersTest > by dental chair returns only chair logs` |
| 6 | 2 | `SQLSTATE[23000] 1062 Duplicate entry 'implantologo_test' for key 'users_username_unique'` | `Feature\Modules\SpecialtyRecordsRoundTripTest > post then get` |
| 7 | 2 | `Class "Database\Factories\MedicalRecordFactory" not found` | `Feature\Api\MedicalRecordAttachmentTest > clinician can delete attachment returns 204` |
| 8 | 2 | `Failed asserting that # is identical to #` (design-system source grep) | `Unit\DesignSystem\DashboardAppShellTest > pr5 sidebar group headers added` |
| 9 | 2 | `Too few arguments to function App\Services\AppointmentService::…` | `Unit\Services\AppointmentServiceTest > creates appointment successfully` |
| 10 | 1 | `SQLSTATE[23000] Duplicate entry … dental_pieces_fdi_number_unique` | `Feature\Modules\SpecialtyRecordsRoundTripTest > recepcionista` |
| 11 | 4 | `Failed asserting that '<UiCard` / `'<template>` / `null is not null` (design-system source grep) | `Unit\DesignSystem\{DashboardAppShellTest,PrimitivePressTest}` |
| 12 | 7 | one each: migration-source anchor, `SddCheckMigrationsTest` guard, `AuditLogFiltersTest` size, `AuthTest` rate limit, `CashRegisterEndpointsTest` summary and export, `UserSpecialtySourceOfTruthTest` pivot | see the raw file |

Rows 1–12 sum to 71, which is the failure count of that run: `28 + 15 + 3 + 3 + 2 + 2
+ 2 + 2 + 2 + 1 + 4 + 7`.

Rows 1, 2 and 5 are **one root class with three columns**: a NOT NULL column
without a default that the factories and seeders never fill, rejected because
MySQL/MariaDB run with strict mode on. Together they are **45 of 71 failures
(63%)**. Counting them separately would turn one gap into 45 tickets.

Two more things the table says, both worth acting on before anything else:

- **Rows 6 and 10 are ordering, not integrity**: `Duplicate entry
  'implantologo_test'` means a seeder ran twice against a database that still had
  its rows. `RefreshDatabase` should prevent that; a suite that reuses a database
  between tests will keep producing these regardless of the code under test.
- **Row 4** (`info() on null`) and **row 9** (argument count) are real code
  defects that the SQLite runner also hides, not engine artefacts.

## What this does not say

- **71 local failures are not the 143 of issue #18.** Different engine (MariaDB
  10.4 versus MySQL 8.0), different revision, and the CI run that produced 143
  predates the `APP_KEY` fix. The distribution above must not be quoted as CI's.
- The strict-mode class is *expected* to reproduce on MySQL 8.0, because strict
  mode is the default there, but that is a prediction until it is measured.

## What is missing, and why

The container engine cannot start on this machine (`wsl -l -v` reports no
installed distribution; Docker Desktop fails with
`\\wsl$\docker-desktop-data\isocache`). Until that changes, the local MySQL
family run is MariaDB and must be labelled as such. Fixing the axis properly
needs either a working container engine or a native MySQL 8.0 on another port.

## Next steps for this axis

1. Commit the classifier as `scripts/audit/cluster-failures.mjs`, so the next
   distribution comes from one command and the numbers stop being
   hand-assembled.
2. Measure on MySQL 8.0 and produce the CI-comparable distribution, then compare
   it against the 143 of #18.
3. One issue per root class — not per test: the strict-mode class is one issue
   covering three columns, the seeder-ordering class is another, and the two
   genuine code defects get their own.
4. Hand the design-system source-grep failures (rows 8 and 11, 6 failures) to
   axis A11, which asks whether those guards can fail at all: they assert against
   the file's text, so they break on formatting and cannot see a runtime error.
