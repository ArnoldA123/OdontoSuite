# Task: appointment-types audit and fixes (2026-10-19)

Branch `fix/appointment-types-audit`, worktree `.worktrees/appointment-types-audit`, based on
`862f416`. The audit read the module end to end (routes, controller, model, schema, seeder,
list/detail pages, composables, PHP and vitest suites) at `862f416`, recorded every confirmed
defect with `path:line` evidence, and fixes them one work unit at a time, each with a test that
fails before the fix. No other module is in scope.

## Specs

- **S1** Verify how the "tipos de cita" area actually behaves, end to end: front, back and the
  inconsistencies between them. Verbatim intent: "quiero verificar el funcionameinto como tal y
  los bugs que encuentres y problemas, en cuando a los tipos de cita".
- **S2** Report every bug and problem found in that area, front or back: "todo ese apartado sea
  front inconsistencias o backend como tal".
- **S3** Do the work on a dedicated worktree: "has un wortre y ahi trabajalo".
- **S4** "ahi trabajalo" authorizes fixing the confirmed defects in that worktree, not only
  listing them.
- **S5** Decide the open questions as a senior engineer, with the best practices of this project
  and for its future, instead of leaving them as questions: "eso de las desiciones que me quedan tu
  como ingeniero experto respondetelas de la menor manera y mejores racticas para este proyecto y a
  futuro tambien".

## Findings (verified at 862f416)

| ID | Severity | Finding | Evidence |
| --- | --- | --- | --- |
| F1 | critical | `DELETE /api/appointment-types/{id}` silently deletes every appointment of that type: the FK is `onDelete('cascade')` and the model has no soft deletes. The confirm dialog only names the type. | `database/migrations/2025_09_20_082341_create_appointments_table.php:19`, `app/Http/Controllers/Api/AppointmentTypeController.php:200` |
| F2 | high | `requires_confirmation` gates reminder scheduling but is absent from `storeRules()`, so types created through the UI never schedule confirmation reminders while all 8 seeded types set it `true`. | `app/Services/ReminderService.php:23-27`, `app/Http/Controllers/Api/AppointmentTypeController.php:22-31`, `database/seeders/AppointmentTypeSeeder.php` |
| F3 | high | `requires_materials` and `is_consultation_mode` are consumed by the consultation flow and shown in the detail page, but no API or UI path writes them. | `app/Http/Controllers/Api/ConsultationController.php:68-75`, `app/Services/ConsultationService.php:213`, `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue:220-235` |
| F4 | medium | Write contract takes `duration_minutes` while the resource exposes `default_duration_minutes`: a `PUT` carrying the resource field returns 200 and changes nothing. | `AppointmentTypeController.php:74-78,148-152` |
| F5 | medium | `color` is only `max:7`, so `red` or `#GGGGGG` persist and the swatches render empty. | `AppointmentTypeController.php:30` |
| F6 | medium | `findOrFail` under a blanket `catch (\Exception)` turns "unknown id" into 500 on update/destroy; `show` maps every failure to 404. | `AppointmentTypeController.php:133-197` |
| F7 | medium | A null `price` renders `S/ 0.00` in list and detail; the detail's `formatPrice` helper that returns "No especificado" is dead code. | `AppointmentTypesPage.vue:242`, `AppointmentTypeDetailPage.vue:245,300-307` |
| F8 | medium | Created/deleted audit rows render as a diff from/to empty because `old_values`/`new_values` are `[]` (truthy), so the dedicated messages are unreachable. | `AppointmentTypeDetailPage.vue:277-320`, `app/Http/Controllers/Api/AuditLogController.php:256` |
| F9 | low | `default_procedure_catalog_id` is rendered as a link in the detail page but has no write path and no consumer. | `AppointmentTypeDetailPage.vue:253-268` |
| F10 | low | Modal footer buttons sit outside their `<form>`, and `UiInput` forwards `required`/`min`/`max`/`step` to its wrapper `<div>` (no such props), so native validation could never run even after the form association was fixed. | `AppointmentTypesPage.vue:382-390,444-452`, `resources/js/components/ui/Input.vue:75-96` |
| F11 | low | `UiSelect.modelValue` declares `[String, Number, Array]`; the edit modal binds a Boolean and Vue logs a prop-type warning. | `resources/js/components/ui/Select.vue:227-230`, `AppointmentTypesPage.vue:355` |
| F13 | medium (outside scope) | `ReminderService::scheduleReminders` has no caller: neither appointment creation nor rescheduling schedules reminders, so `requires_confirmation` currently gates an unreachable pipeline. The flag still belongs in the contract (the wiring is a reminders-module decision), but it explains why D1 has no immediate side effect. **Resolved later by #154**: creating, rescheduling and cancelling an appointment schedule and cancel the pending reminders, so the pipeline is reachable and the flag gates a real one. | `app/Services/ReminderService.php:21,181-187`; `grep -rn "scheduleReminders" app` matched only that file at the time |
| F12 | low (outside scope) | The shared `ProcedureCatalogFactory` still defines a `category` column the schema no longer has, so every test that uses it errors. Found while writing the T2 procedure-reference test, which builds its fixture from the model instead. Reported here because it was outside the area T2 was writing; **fixed by T8** (`7900ca6`), which keeps only the columns the schema has. | `database/factories/ProcedureCatalogFactory.php:16-24`, `database/migrations/2025_10_24_202849_create_procedure_catalog_table.php:14-30` |

Fix decisions: F1 blocks the deletion with `409` and an actionable message (no schema rewrite:
applied migrations are not edited, per #79); F3/F9 become writable through the API, and the three
booleans also get form controls; the procedure picker is deliberately not built because the
field has no consumer yet; F5 is enforced on write only (existing rows are read as-is).

## Tasks

| ID | Spec | Work unit | Commit |
| --- | --- | --- | --- |
| T1 | S1,S2,S4 | F1/F6: refuse deleting a type with appointments (409), 404 on unknown id, delete error surfaced in the list page | `064f53d` (341 lines) |
| T2 | S1,S2,S4 | F2/F3/F4/F5/F9: writable flags, `default_procedure_catalog_id`, duration alias, hex color | `57393c1` (232 lines) |
| T3 | S1,S2,S4 | F3/F10/F11: form controls for the flags, real form submission, `UiSelect` Boolean support | `1483c67` (with T4/T6) |
| T4 | S1,S2,S4 | F7/F8: null price rendered as "No especificado", created/deleted audit rows | `1483c67` (with T3/T6) |
| T5 | S1,S2,S4 | T2 follow-on: the shared audit summary labels the new flags and renders them Sí/No (RED 2 failed / 1 passed, GREEN 3/3) | `95ff463` (81 lines) |
| T6 | S1,S2,S4 | F10 completion: `UiInput` declares and forwards `required`, `min`, `max` and `step` to the real `<input>` (RED 1 failed, GREEN 38/38 on the module files) | `1483c67` (with T3/T4) |
| T7 | S5 | D1: `requires_confirmation` defaults to true in the schema, the create form and the 201 response (RED 1 PHP + 1 vitest, GREEN 76/76 PHP + 38/38 vitest) | `de2e303` (56 lines) |
| T8 | S5 | D3/F12: `ProcedureCatalogFactory` fills only the columns the schema has, guarded by a factory test | `7900ca6` (36 lines) |

## Task detail for T3 and T4

Both touch the same two pages, so they run in order in one work unit. Tests first: each rule gets
its vitest case observed RED before the change.

**T3 (forms and flags)**

1. The New and Edit modals gain three `UiSelect` controls, one per catalog flag
   (`requires_confirmation`, `requires_materials`, `is_consultation_mode`), with `Sí`/`No` options
   whose values are the booleans themselves. Labels: "Requiere confirmación", "Requiere
   materiales", "Modo consulta". The New modal starts all three at `false` (the schema default at that time;
   D1 later moves `requires_confirmation` to `true`) and keeps `is_active: true`.
2. The modal footer buttons become native submitters: `form="form-new-type"` /
   `form="form-edit-type"` and no `@click` handler, so the browser runs the `required` validation
   the handler used to skip. The `creating` / `updating` guards stay.
3. `editType()` normalizes an invalid stored `color` to `DEFAULT_TYPE_COLOR`, so the new backend
   hex rule cannot reject an untouched save of a pre-existing row.
4. `UiSelect.modelValue` accepts `Boolean` (additive: string/number/array keep working), so the
   `is_active` boolean binding stops logging a prop-type warning (F11).

**T4 (display)**

5. A null `price` renders "No especificado" in the list cell (including its `aria-label`) and in
   the detail price field; a non-null price still renders through `formatCurrency`. The detail
   page uses its existing `formatPrice` helper, which is dead code today (F7).
6. The audit tab renders "Tipo de cita creado en el sistema." for `appointment_type_created` and
   "Tipo de cita eliminado del sistema." for `appointment_type_deleted` even when
   `old_values`/`new_values` arrive as `[]` (the real API shape), and keeps the field diff with
   `text-systemRed-600` / `text-systemGreen-600` for `appointment_type_updated` (F8).

**Constraints carried by the module's existing guards:** no bare `duration_minutes` reference in
`AppointmentTypesPage.vue` outside `newType.duration_minutes`, `editingType.duration_minutes` and
`duration_minutes:`; no U+2014 em dash in either page; no raw text/number `<input>` and at least
seven `<UiInput v-model>` in the list page; the four KPI slots, hero recipe and audit ramps stay
as pinned by `AppointmentTypesRedesignContractTest`, `AppointmentTypesListCleanupTest` and
`AppointmentTypesAppShellTest`.

**Slice boundaries.** T1 341, T2 234, T5 81 authored lines. T3+T4+T6 landed together at 442
lines, slightly over the ~400 target: the two files interleave the form and the display changes in
the same hunks (`newType` state, the setup return block), so a hunk split would have mixed the
concerns inside one commit. It is the last slice of this branch; further module work starts a new
one.

## Decisions (2026-10-19, delegated by the owner)

**S5** (verbatim): "eso de las desiciones que me quedan tu como ingeniero experto respondetelas de la
menor manera y mejores racticas para este proyecto y a futuro tambien".

- **D1 — `requires_confirmation` defaults to `true`.** Evidence: no application path could ever set
  the flag (`storeRules()` never accepted it and the page never exposed it until `1483c67`), so every
  type created through the UI/API was `false` by omission while the seeder sets `true` for the whole
  catalog. A schema default is the single source of truth for callers that do not speak the form, so
  it now matches the product intent and the form inherits it. Failure modes drove the value: a
  missed confirmation costs a no-show (revenue, unrecoverable), an unwanted one costs a patient
  email (annoying, recoverable and switchable per type). No backfill: stored values are the
  operator's data, and the flag is now editable per type. The follow-up SQL, if the owner wants the
  pre-fix rows to confirm, is `UPDATE appointment_types SET requires_confirmation = 1;` and is
  deliberately not run by a migration.
- **D2 — `default_procedure_catalog_id` stays an optional integration point.** Evidence: the field
  was introduced by the 2026 catalog slice (`ee73ddd`, `e815bbe`) and never had a consumer. It is
  now validated and writable through the API, the detail page links it when set, and the picker is
  built together with its consumer (appointment prefill of `procedure_id`, duration and cost) as one
  unit. Exposing a selector before the behavior exists would create a field the operator can set but
  nothing reads.
- **D3 — `ProcedureCatalogFactory` is fixed, not worked around.** A shared factory that inserts a
  dropped column fails every future test that touches it; the fixture is code and stays aligned with
  the schema, with a one-test guard.

**Practice for the future.** A default belongs to the schema, never to one form; when the form and
the schema disagree, callers outside the form get a silent, invisible value. A professionally
readable field is either writable and consumed, or it is not exposed. And no migration rewrites
stored values to compensate for a default that was wrong: the fix restores the ability to choose,
and the owner decides about legacy rows with a visible, reviewable statement.


## Log

- **L1** (user, verbatim): "ahora continuando este proyecto quiero verificar el funcionameinto
  como tal y los bugs que encuentres y problemas, en cuando a los tipos de cita com tal todo ese
  apartado sea front inconsistencias o backend como tal, has un wortre y ahi trabajalo".
- **L2** Worktree `.worktrees/appointment-types-audit` created on `fix/appointment-types-audit`
  from `862f416`. `.git/info/exclude` excludes `odd/` ("ODD task documents are local agent
  workflow memory, not repo code") and `.worktrees/`, so this document stays local and the
  commits carry only code and tests.
- **L3** MySQL is reachable only through the XAMPP socket, and the shared
  `odontosuite_test` database is used by another session's runs, so this branch tested against
  the isolated `odontosuite_at_test`:
  `DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_DATABASE=odontosuite_at_test DB_USERNAME=root
  DB_PASSWORD=<from .env> vendor/bin/phpunit --configuration=phpunit.mysql.xml`.
- **L4** F1 was reproduced before the fix: the RED test creates an appointment for a type and
  asserts the delete is refused with `409` and the appointment survives; at `862f416` the delete
  returns 200 and the appointment row is gone.
- **L5** The worktree needs a real `vendor/` copy: the shared `vendor` symlink resolves the
  Composer autoloader against the main checkout, so a symlinked worktree silently tests the main
  tree (the RED run proved it by loading the unpatched controller). `node_modules` can stay a
  symlink because Vite resolves sources from the config root.
- **L6** (superseded by L10/D1) The form default for `requires_confirmation` stays `false` (the
  schema default) rather than copying the seeder's `true`: exposing the control is the fix, and
  flipping the default is a product decision left to the owner.
- **L7** T3/T4 delivered by the delegated writer and reviewed hunk by hunk. The writer flagged that
  the native-submit rule was only half achieved: `UiInput` had no `required` prop, so the attribute
  landed on the wrapper `<div>` and the browser never validated the field. `Input.vue` now declares
  and forwards `required`/`min`/`max`/`step`; the module contract asserts the real input carries
  them. The writer's `type="button"` addition to every `UiSelect` internal button is kept: without
  it, an option click inside a native form activates submit.
- **L8** Verification before closeout. Module vitest (5 files) 38/38. PHP `--filter='AppointmentType|Documentation'`
  102/102. `node scripts/audit/doc-drift.mjs` 12/12. The full vitest suite in this worktree could not
  be read as green while other sessions saturated the machine: load average 27-40 on 12 cores, and
  the only failures were 4-10 timeouts in dashboard/environments files that do not import the
  changed primitives. Those dashboard files pass 18/18 when run alone, and `main` at `862f416`
  passes 421/421 on an idle machine. No assertion failure was observed in the branch's own files.
- **L9** (user, verbatim): "eso de las desiciones que me quedan tu como ingeniero experto respondetelas
  de la menor manera y mejores racticas para este proyecto y a futuro tambien".
- **L10** D1, D2 and D3 decided and recorded in "Decisions": the default moves to `true` in schema
  and form (`de2e303`), the procedure reference stays API-only until its consumer exists, and the
  shared factory is fixed (`7900ca6`). The create response is refreshed so it carries the effective
  default instead of omitting it.
- **L11** Verification after the decisions: PHP `--filter='AppointmentType|ProcedureCatalogFactory'`
  76/76; the five module vitest files 38/38. F13 (reminder scheduling entry point unreachable) is
  recorded as a reminders-module follow-up, not fixed here.
- **L12** The new migration moved the documented count to 112; `71d641b` syncs AGENTS.md §4 and its
  changelog and keeps `doc-drift` (12/12) and `AgentsDocsSyncTest` (5/5) green. Branch state: seven
  commits, each a reviewable slice of at most 442 authored lines.
- **L13** The merge to `main` (`55fc410`) went red on `SddCheckMigrationsTest`: the default-change
  migration used `->change()` bare, and the repository requires MySQL-only DDL to carry a driver
  guard. `186ad9d` on `fix/appointment-types-migration-guard` (PR #152) wraps `up()` and `down()`
  in `DB::getDriverName() === 'mysql'`; MySQL behaviour is unchanged. Local: guard test 7/7,
  appointment-types suite 76/76.
- **L14** Browser verification of the last open item (Chromium via playwright-cli against `artisan
  serve`, fresh `pnpm build`): the create modal renders the three flag selects with the D1 default
  (`requires_confirmation` = Sí, materials and consultation mode = No); the real name input carries
  `required` and the duration input carries `min=15`, `max=480`, `step=15`, `required`; clicking
  Crear with an empty name keeps the modal open, focuses the name field, surfaces the browser
  validation message and sends **zero** POSTs to `/api/appointment-types`. The earlier attempt hit
  a stale `public/build` (pre-`1483c67`), which is why the rebuild was required before the check.
