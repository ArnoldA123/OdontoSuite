# Specialty Record Seeder Contract

## Purpose

Re-align `database/seeders/SpecialtyRecordSeeder.php` to the actual `$fillable` arrays and schema columns of `ImplantologyRecord`, `OrthodonticsRecord`, `EndodonticsRecord`, `RehabilitationRecord`, and `OralSurgeryRecord`. Add a parser-based field-contract test that fails CI when the seeder uses keys outside any of those `$fillable` arrays, eliminating the silent `Column not found` failure that breaks `php artisan db:seed` on every clean run.

## Source-confirmed schema/seeder mismatch

| Model | Seeder key (current) | Required (schema + `$fillable`) |
|-------|----------------------|----------------------------------|
| OrthodonticsRecord | `user_id`, `medical_record_id`, `start_date`, `end_date`, `initial_diagnosis`, `treatment_goals`, `next_adjustment_date`, `attachments`, `status` | `created_by`, `treatment_type`, `treatment_start_date`, `treatment_phase` (enum), `treatment_objectives`. NO `user_id`/`medical_record_id`. |
| EndodonticsRecord | `user_id`, `medical_record_id`, `diagnosis`, `treatment_date`, `anesthesia_type`, `working_length`, `obturation_material`, `obturation_technique`, `irrigants`, `notes`, `radiographic_data`, `status` | `created_by`, `tooth_number`, `canal_count`, `pulp_diagnosis`, `treatment_status` (enum). NO `user_id`/`medical_record_id`. |
| RehabilitationRecord | `user_id`, `medical_record_id`, `rehabilitation_type`, `preparation_date`, `placement_date`, `shade`, `lab_details`, `occlusion_notes`, `status` | `created_by`, `prosthesis_type`, `material_type`, `laboratory_name`, `impression_date`, `delivery_date`, `cementation_date`, `shade_selection`. NO `user_id`/`medical_record_id`. |
| OralSurgeryRecord | `user_id`, `medical_record_id`, `procedure_name`, `surgery_date`, `sutures_removed_date`, `radiographic_data` | `created_by`, `procedure_type`, `surgery_site`, `surgical_technique`, `surgery_start_time`, `surgery_end_time`, `surgery_duration_minutes`. NO `user_id`/`medical_record_id`. |
| ImplantologyRecord | `created_by`, `dental_piece_id`, ... | `created_by`, `dental_piece_id`, ... (already aligned). |

Reference: `database/migrations/2025_10_24_20240{5,21,39,56}_create_*_records_table.php` and `app/Models/{Orthodontics,Endodontics,Rehabilitation,OralSurgery,Implantology}Record.php`.

Existing `tests/Unit/Seeders/SpecialtyRecordSeederSourceTest.php` only asserts class references and the absence of the legacy `SpecialtyRecord` model — it does NOT assert field-vs-schema compatibility, which is why the silent failure went undetected.

## Requirements

### Requirement: Seeder uses only fillable keys

`database/seeders/SpecialtyRecordSeeder.php` MUST use only keys that exist in the corresponding model's `$fillable` array when invoking `Model::create([...])` (or `::updateOrCreate([...])`).

#### Scenario: OrthodonticsRecord insert uses only fillable keys

- GIVEN the seeder reaches the OrthodonticsRecord branch
- WHEN the `OrthodonticsRecord::create([...])` call is executed against a fresh database
- THEN the call MUST NOT raise `Column not found`, `Unknown column`, or `MassAssignmentException`
- AND each key in the array MUST be a member of `OrthodonticsRecord::$fillable`.

#### Scenario: EndodonticsRecord insert uses only fillable keys

- GIVEN the seeder reaches the EndodonticsRecord branch
- WHEN the `EndodonticsRecord::create([...])` call is executed
- THEN the call MUST succeed
- AND each key MUST be a member of `EndodonticsRecord::$fillable`.

#### Scenario: RehabilitationRecord insert uses only fillable keys

- GIVEN the seeder reaches the RehabilitationRecord branch
- WHEN the `RehabilitationRecord::create([...])` call is executed
- THEN the call MUST succeed
- AND each key MUST be a member of `RehabilitationRecord::$fillable`.

#### Scenario: OralSurgeryRecord insert uses only fillable keys

- GIVEN the seeder reaches the OralSurgeryRecord branch
- WHEN the `OralSurgeryRecord::create([...])` call is executed
- THEN the call MUST succeed
- AND each key MUST be a member of `OralSurgeryRecord::$fillable`.

#### Scenario: ImplantologyRecord insert continues to succeed

- GIVEN the existing ImplantologyRecord branch
- WHEN the `ImplantologyRecord::create([...])` call is executed
- THEN the call MUST succeed (no regression vs. the pre-slice state).

### Requirement: Full db:seed completes without abort

`php artisan db:seed` (which calls `SpecialtyRecordSeeder` last via `DatabaseSeeder::run()`) MUST complete without fatal errors.

#### Scenario: Fresh seed run reaches the end of the seeder

- GIVEN a fresh database (`migrate:fresh` followed by `db:seed`)
- WHEN the seed command runs
- THEN the command MUST exit with status `0`
- AND it MUST NOT throw `Column not found: 1054 Unknown column 'user_id'` (or any equivalent SQLSTATE 42S22 error) on the specialty tables.

#### Scenario: SpecialtyRecordSeeder is idempotent on a populated database

- GIVEN the database already contains the rows that the seeder creates
- WHEN the seeder is re-run
- THEN the seeder MUST NOT raise unique-conflict exceptions
- AND the `info('Registros de especialidades creados...')` line MUST still be emitted.

#### Scenario: Empty-database branch returns early without error

- GIVEN no patients, users, medical records, or dental pieces exist
- WHEN the seeder runs
- THEN the seeder MUST early-return after emitting the `info('No hay suficientes datos...')` line
- AND MUST NOT raise any exception.

### Requirement: Field-contract test guards future regressions

`tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php` MUST parse the seeder source, extract every associative-array literal passed to `Model::create([...])` for each of the five models, and assert that every key is a member of the corresponding model's `$fillable`.

#### Scenario: All five model branches pass the field-contract test

- GIVEN the test was added and the seeder was re-aligned
- WHEN `php artisan test --filter=SpecialtyRecordSeederFieldContractTest` runs
- THEN every test method (`test_orthodontics_branch_uses_only_fillable`, `test_endodontics_branch_uses_only_fillable`, `test_rehabilitation_branch_uses_only_fillable`, `test_oral_surgery_branch_uses_only_fillable`, `test_implantology_branch_uses_only_fillable`) MUST pass.

#### Scenario: Test fails when a forbidden key is introduced

- GIVEN a developer adds `user_id` (or any other non-fillable key) back into the OrthodonticsRecord branch
- WHEN the test runs
- THEN the corresponding test method MUST fail with a message that names the offending key and the line number.

#### Scenario: Test ignores docblocks and comments

- GIVEN the seeder source contains the strings `user_id` or `medical_record_id` inside comments or docblocks
- WHEN the test parses the source
- THEN those contextual references MUST NOT trigger the assertion
- AND only the arguments actually passed to `Model::create([...])` MUST be checked.

### Requirement: Foreign-key constraints are respected

Because `OralSurgeryRecord` and `EndodonticsRecord` require a `dental_piece_id` FK and `created_by` FK, the seeder MUST seed `dental_pieces` and `users` BEFORE invoking the specialty branches.

#### Scenario: Dental pieces exist before specialty records are inserted

- GIVEN the seed order
- WHEN the seeder fetches `$dentalPieces = DentalPiece::limit(10)->get();`
- THEN the resulting collection MUST NOT be empty
- AND the collection MUST be populated by an earlier seeder (DentalPieceSeeder) prior to the specialty loop.

#### Scenario: Users exist before specialty records are inserted

- GIVEN the seed order
- WHEN the seeder fetches `$users = User::whereIn('role', [...])->limit(3)->get();`
- THEN the collection MUST NOT be empty.

#### Scenario: DatabaseSeeder orders seeders correctly

- GIVEN `DatabaseSeeder::run()`
- WHEN the seeder is invoked
- THEN `DentalPieceSeeder`, `UserSeeder`, `PatientSeeder`, `MedicalRecordSeeder` MUST run BEFORE `SpecialtyRecordSeeder`.

### Requirement: Rollback is a single revert

The seeder rewrite MUST be revertible by reverting the slice commit alone; the field-contract test MUST also be removed in the same revert.

#### Scenario: Reverting the slice restores the buggy seeder

- GIVEN the slice is applied and `php artisan db:seed` succeeds
- WHEN the slice commit is reverted
- THEN the original `SpecialtyRecordSeeder.php` is restored
- AND the field-contract test file is removed
- AND `php artisan test` MUST still pass (the source test already passes on the original buggy source).

## Permissions

- The seeder runs in a `development` / CLI context only (under `php artisan db:seed`). No HTTP permissions apply.

## Rollback invariants

- The slice MUST NOT alter the existing `SpecialtyRecordSeederSourceTest.php` (which guards model references and the legacy `SpecialtyRecord` absence).
- The slice MUST NOT add new migrations or modify existing migrations for the five specialty tables.
- The slice MUST NOT change the `SpecialtyRecord` model class (it is legacy and pre-removed; nothing in this slice should resurrect it).
