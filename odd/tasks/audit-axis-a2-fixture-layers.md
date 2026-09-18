# Task: axis A2 — the remaining fixture layers behind the MySQL failures

Status: **measured and complete**. Continuation of
`odd/tasks/audit-axis-a2-mysql-failures.md`, which measured the distribution and
fixed its first layer in PR #42. This branch carries the layers that PR left
measured and unfixed, tracked as issue #43.

Base: `fix/fixtures-layer` (`4f1196f`), **not** `main`. PR #42 is unmerged, and its
fixture fills are the substrate this work builds on; branching from `main` would
duplicate those five files and conflict with them.

## Method

```
DB_URL= DB_DATABASE=odontosuite_test php artisan test --configuration=phpunit.mysql.xml
```

Engine: **MariaDB 10.4.32** (XAMPP, `127.0.0.1:3306`). Measured equivalent to
MySQL 8.0.46 in the previous task's second pass: same signatures, same counts,
same order.

Raw output: `.atl/qa-evidence/audit/a2-layers/{before,after}.txt` (untracked;
`.atl/` is gitignored).

## Measured result

| | Failures | Passed | Signatures |
| --- | --- | --- | --- |
| Before | 57 | 963 | 21 |
| After | **37** | **983** | 26 |

Six signatures disappeared — 31 failures:

| Signature | Gone |
| --- | --- |
| `Field 'patient_id' doesn't have a default value` | 12 |
| `Field 'ends_at' doesn't have a default value` | 8 |
| `Field 'dental_chair_id' doesn't have a default value` | 5 |
| `Field 'user_id' doesn't have a default value` | 2 |
| `Class "Database\Factories\MedicalRecordFactory" not found` | 2 |
| `Class "Database\Factories\TransactionFactory" not found` | 2 |

**Eleven failures appeared underneath, and they are a different root class.**
That is the chain of this axis behaving as documented: 31 fixed minus 11 revealed
is the net −20. The revealed class is not fixtures — see "What the fills
revealed" below — and it is now tracked as issues #46 and #47.

## Work units

| # | Work unit | Predicted | Measured |
| --- | --- | --- | --- |
| 1 | `AppointmentFactory`: fill the eight required columns, derive `ends_at` from the final `scheduled_at` + `duration_minutes`, derive `created_by` from `user_id` | −26 | −15 net (26 signatures cleared, 11 revealed) |
| 2 | New `TransactionFactory` | −2 | −2 |
| 3 | New `MedicalRecordFactory` | −2 | −2 |
| 4 | `AppointmentTest::it_can_create_an_appointment`: pass the `ends_at` and `created_by` it omits | −1 | −1 |
| 5 | New `ClinicalAttachmentFactory` — a second missing factory hidden behind work unit 3 | not predicted | −2 |
| 6 | `MedicalRecordAttachmentTest`: drop the phantom `medical_record_id` | not predicted | (same 2) |

Work units 5 and 6 were not in issue #43's measurement: `MedicalRecordAttachmentTest`
died on the first missing factory, so the second one and the phantom column were
invisible behind it. A scan for every `<Model>::factory()` call in `tests/`
against `database/factories/` now returns nothing, which closes the missing-factory
class rather than fixing it one test at a time.

## Why `ends_at` is derived, not filled

`appointments.ends_at` is NOT NULL and is a function of the other two columns:
production computes it as `scheduled_at + duration_minutes`
(`app/Http/Services/AppointmentService.php:99`) and `StoreAppointmentRequest`
documents it as "computed from scheduled_at + duration".

Every failing consumer passes its own `scheduled_at` and `duration_minutes`
(`CalendarServiceTest` most visibly), so a factory that hardcoded `ends_at` beside
a fixed default `scheduled_at` would produce a valid insert and an inconsistent
record: the range queries those tests exercise filter on `ends_at`. The factory
therefore derives `ends_at` from the **final** pair, after overrides, inside
`configure()/afterMaking()`.

The derivation is validated by a test that now reaches its assertions:
`CalendarServiceTest > it can get calendar data for fullcalendar` compares
`$event['end']` against `scheduled_at + 60min` and **passes**; it fails on the
event title, which is a different contract (#46).

## What the fills revealed

**Issues #46 and #47**, both measured, not predicted:

- **#46** — a RED-test slice whose implementation never landed. `AppointmentTest`
  exercises five scopes (`scheduled`, `completed`, `cancelled`, `forDateRange`,
  `forPatient`) and three accessors (`end_time`, `formatted_duration`,
  `formatted_scheduled_time`) that never existed in the model
  (`git log -S` finds no history), and `CalendarService` disagrees with its test
  on the event `title` and on the stats keys. 10 failures.
- **#47** — `reminder_schedules.status` is `enum('pending','sent','failed','cancelled')`
  while `ReminderSchedule::STATUS_TRANSITIONS` and `UpdateReminderRequest` both
  treat `queued` as a real state, and no migration declares it. Asking for it
  truncates the column and the endpoint answers 500. 1 failure, and a real defect
  rather than test debt.

## Lessons worth keeping

- **Factories run inside `Model::unguarded()`**
  (`Factory::makeInstance()`), so mass-assignment protection does not apply: any
  key in a factory or in a per-call override reaches the INSERT even when it is
  not `$fillable`. A fixture must be checked against the **schema**, not against
  `$fillable`. That is how `medical_record_id` — a column `clinical_attachments`
  never had — surfaced.
- **An empty factory is a chain, not a bug.** `AppointmentFactory` returning `[]`
  wore four different column names across three test files, and fixing it took
  the suite from 57 to 37 while revealing 11 failures of another class.
- **A missing factory hides whatever comes next.** Both new factories this branch
  added were standing in front of a second defect.

## What this does not fix

Clearing these leaves 37 failures, of which 11 are the revealed class (#46, #47)
and 26 are the pre-existing ones with their own issues: the seeder-ordering
duplicates (#37), `info() on null` (#38, which gained one instance), the
`AppointmentService` constructor split (#39), the design-system source greps, the
audit-log 405 assertions, and the cash-register 500s.

**CI stays red after this branch.** It cannot be turned green by fixtures alone,
and this task must not be read as promising that.

## Evidence recorded

- Before: 57 failures, 21 signatures (`.atl/qa-evidence/audit/a2-layers/before.txt`).
- After: 37 failures, 26 signatures (`.atl/qa-evidence/audit/a2-layers/after.txt`).
- `vendor/bin/pint --test` → **PASS** on the nine factory files this branch and
  PR #42 touch. The style issues Pint reports in `MedicalRecordAttachmentTest.php`
  and `AppointmentTest.php` are pre-existing at `4f1196f` (verified by running
  Pint over the base revisions) and were deliberately left alone.
