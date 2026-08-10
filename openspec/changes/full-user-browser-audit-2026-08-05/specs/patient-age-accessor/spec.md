# Patient Age Accessor

## Purpose

Expose a derived `age` field on patient resources so every frontend surface (patient list, patient selector, clinical-history views, specialty records) can render a correct integer age. Eliminates the `N/A años` defect confirmed in the 2026-08-05 browser walkthrough and restores the `Edad` column expected on the patient table.

## Source-confirmed defects

| File | Line(s) | Defect |
|------|---------|--------|
| `app/Http/Resources/PatientResource.php` | 17–86 | No `age` field in the `toArray()` output. Only `birth_date` is exposed. |
| `resources/js/components/ui/PatientSelector.vue` | 51 | `{{ patient.age || 'N/A' }} años` falls through to `N/A años` because `patient.age` is always `undefined`. |
| `resources/js/modules/patients/PatientsPage.vue` | 144–158 | Table headers list `Paciente`, `Contacto`, `Fecha de Nacimiento`, `Estado`, `Acciones` — no `Edad` column. |

Reference: `.atl/qa-evidence/screenshots/07-records.png` and `.atl/qa-evidence/screenshots/10-specialty.png` (every patient rendered as `N/A años`).

## Requirements

### Requirement: PatientResource exposes a derived integer age

The `PatientResource` MUST include an `age` key in its JSON output. The value MUST be either:

- A non-negative integer when `birth_date` is present and parseable, OR
- `null` when `birth_date` is `null` or unparseable.

Computation MUST use the patient's `birth_date` and the current date (server-time UTC), rounding DOWN to the number of completed years. Computation MUST NOT use any client-side clock.

#### Scenario: Adult patient with known birth date returns an integer

- GIVEN a patient with `birth_date = 1990-04-15`
- WHEN the patient is returned via `GET /api/patients/{id}` (or any patient-index endpoint)
- THEN the response payload MUST contain `"age": 36` (or whatever the integer years are at the current test-fixed date)
- AND `age` MUST be a JSON number, not a string.

#### Scenario: Infant returns zero on the day of birth

- GIVEN a patient with `birth_date` equal to today
- WHEN the resource is resolved
- THEN `age` MUST equal `0`.

#### Scenario: Patient with null birth date returns null

- GIVEN a patient whose `birth_date` column is `null`
- WHEN the resource is resolved
- THEN `age` MUST be `null` (JSON null), NOT `0`, NOT `undefined`, NOT the string `"N/A"`.

#### Scenario: Boundary day-of-year does not falsely increment

- GIVEN today's date is `2026-08-05` and a patient with `birth_date = 2026-08-05`
- WHEN the resource is resolved
- THEN `age` MUST equal `0` (the day-of-birth case MUST NOT increment).

- GIVEN a patient with `birth_date = 2025-08-06` and today is `2026-08-05`
- WHEN the resource is resolved
- THEN `age` MUST equal `0` (the day-before-first-birthday case MUST NOT increment).

#### Scenario: Time-zone is server UTC

- GIVEN server timezone is `UTC`
- WHEN two requests land on the same UTC day but different local timezones
- THEN the `age` value MUST be identical for the same patient.

### Requirement: PatientSelector renders the backend age

`resources/js/components/ui/PatientSelector.vue` MUST render the backend-provided `age` value. The fallback label `N/A años` MUST only appear when `patient.age === null` (e.g., the patient has no birth date on file).

#### Scenario: Selector shows computed age for a patient with birth date

- GIVEN a patient with `birth_date = 1990-04-15` is rendered by `PatientSelector`
- WHEN the selector renders the patient row
- THEN the rendered text MUST match `/\b36 años\b/` (or the integer-matching equivalent based on the current date)
- AND the literal `N/A años` MUST NOT appear.

#### Scenario: Selector shows `N/A años` only when age is null

- GIVEN a patient whose resource returns `age: null`
- WHEN the selector renders the row
- THEN the rendered text MUST be `N/A años`.

### Requirement: PatientsPage table exposes an Edad column

`resources/js/modules/patients/PatientsPage.vue` MUST include an `Edad` column in the desktop table view, positioned between `Fecha de Nacimiento` and `Estado`. The mobile card view MUST also display the age.

#### Scenario: Desktop table renders the Edad column

- GIVEN the user is on `/patients` with a desktop viewport (≥ `lg` breakpoint)
- AND the patient list contains at least one patient with `age = 36`
- WHEN the table renders
- THEN the HTML MUST contain a `<th>` whose text is `Edad`
- AND at least one `<td>` in the body MUST contain the integer `36`.

#### Scenario: Mobile card view renders the age

- GIVEN the user is on `/patients` with a mobile viewport (< `lg` breakpoint)
- AND the patient list contains at least one patient
- WHEN the card view renders
- THEN each card MUST display the age label `Edad` followed by the integer value.

#### Scenario: Null age renders as a placeholder

- GIVEN a patient whose `age` is `null`
- WHEN the table or card renders
- THEN the cell MUST show `—` (or `N/D`) and MUST NOT show the literal string `"null"`.

### Requirement: PatientResource age is unit-tested

A `tests/Feature/Api/PatientResourceAgeTest.php` MUST exist and assert: happy adult, day-of-birth, day-before-first-birthday, null birth date, and non-leap-year edge cases.

#### Scenario: Age test covers all boundary cases

- GIVEN the `PatientResourceAgeTest` exists
- WHEN `php artisan test --filter=PatientResourceAgeTest` is run
- THEN every documented boundary case MUST pass.
- AND the test MUST fail if `PatientResource` ever drops the `age` key.

### Requirement: Permission gating

The `Edad` column MUST be visible to any role that currently has read access to the patient list (e.g., `administrador`, `odontologo`, `recepcionista`). No new permissions are introduced.

#### Scenario: Receptionist sees the Edad column

- GIVEN a user with role `recepcionista` is authenticated
- WHEN they navigate to `/patients`
- THEN the `Edad` column MUST be visible.

#### Scenario: Role without patient read access is not affected

- GIVEN a user whose role lacks `patients.read`
- WHEN they attempt `GET /api/patients`
- THEN the API MUST return the standard 403 forbidden response
- AND the test MUST NOT rely on the `age` key being absent for unauthorised callers — the test only asserts behaviour for authorised callers.

## Rollback invariants

- Reverting the slice MUST remove the `age` access from `PatientResource`, the `Edad` column from `PatientsPage`, and the `patient.age` reference from `PatientSelector` — all in one revert.
- The `age` key is OPTIONAL in the JSON contract; downstream consumers that ignore unknown keys MUST continue to work after the revert.
- Existing patient indexes (`/api/patients`, `/api/patients/{id}`) MUST continue to return the same shape minus the `age` key.
