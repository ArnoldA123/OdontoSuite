# Currency Format Helper

## Purpose

Single source of truth for rendering Peruvian Sol (PEN) currency across the OdontoSuite V2 frontend. Prevents the duplicate `S/` prefix bug observed on the dashboard and cash register session list, and removes the risk of future components re-introducing the same defect by centralising the rendering helper.

## Source-confirmed defects

| File | Line(s) | Defect |
|------|---------|--------|
| `resources/js/modules/dashboard/DashboardPage.vue` | 400 | `\`Saldo: S/ ${formatCurrency(...)}\`` while `formatCurrency` already prepends `S/` via `Intl.NumberFormat('es-PE', 'currency', 'PEN')`. Renders `S/ S/ 759.00`. |
| `resources/js/modules/cash-register/components/SessionList.vue` | 161, 173, 184 | Same pattern across opening amount, closing amount, and difference rows. |

Reference: `.atl/qa-evidence/screenshots/02-dashboard.png` (clean top card vs. broken cash balance line) and `.atl/qa-evidence/screenshots/11-cash.png` (clean top cards vs. broken session rows).

## Requirements

### Requirement: PEN currency rendering uses one helper

The system MUST render every PEN currency label through a single helper that emits exactly one `S/` prefix. The helper SHALL be the only call site for `Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'})` in the entire frontend codebase.

The helper MUST be exposed as `formatPENLabel(amount)` and accept a numeric or numeric-coercible value, defaulting to `0` when the input is `null`, `undefined`, or non-numeric.

The helper MUST return a string of the form `S/ <amount>` with the amount rendered using `Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN', minimumFractionDigits: 2})` (so the embedded currency glyph is stripped before concatenation).

#### Scenario: Dashboard cash balance renders a single `S/` prefix

- GIVEN an admin user is authenticated with an open cash session
- AND `realTimeTotals.currentBalance` equals `759.00`
- WHEN the dashboard renders the `cashBalanceText` computed value
- THEN the rendered text matches `^Saldo: S\/ 759\.00$`
- AND the literal `S/ S/` MUST NOT appear anywhere in the rendered text.

#### Scenario: Session list opening amount renders a single `S/` prefix

- GIVEN a session row with `opening_amount = 500.00`
- WHEN the `SessionList` row renders the opening-amount cell
- THEN the rendered text matches `^S\/ 500\.00$`
- AND the literal `S/ S/` MUST NOT appear.

#### Scenario: Session list closing amount renders a single `S/` prefix

- GIVEN a closed session row with `closing_amount = 1234.50`
- WHEN the row renders the closing-amount cell
- THEN the rendered text matches `^S\/ 1,234\.50$`.

#### Scenario: Session list difference renders a single `S/` prefix

- GIVEN a session row with `difference_amount = 12.00`
- WHEN the row renders the difference cell
- THEN the rendered text matches `^\+S\/ 12\.00$` (positive sign, one `S/` prefix, no doubled prefix).

#### Scenario: Negative difference renders a single `S/` prefix

- GIVEN a session row with `difference_amount = -5.00`
- WHEN the row renders the difference cell
- THEN the rendered text matches `^-S\/ 5\.00$`
- AND the literal `S/ S/` MUST NOT appear.

#### Scenario: Null or zero amount renders `S/ 0.00`

- GIVEN `amount` is `null`, `undefined`, `0`, or any non-numeric value
- WHEN `formatPENLabel(amount)` is called
- THEN the function MUST return the string `S/ 0.00`
- AND MUST NOT throw, log, or return `NaN`.

#### Scenario: No component concatenates `S/` before the helper

- GIVEN the entire `resources/js/**` source tree
- WHEN a static source scan looks for `S/ ${{` or `S/ {{ ... formatCurrency` patterns
- THEN no matches MUST be found outside the helper's own implementation
- AND the literal `S/ ` MUST appear only inside the helper source file.

### Requirement: Helper is unit-tested

The system MUST ship a unit test that exercises `formatPENLabel` with the documented inputs (positive, zero, negative, null, undefined, string `'123.45'`) and asserts the rendered output.

#### Scenario: Unit test covers positive zero, negative, null, and string inputs

- GIVEN the `formatPENLabel` helper module
- WHEN the helper unit test runs under `php artisan test` (frontend unit runner is not configured; coverage is asserted via a Vitest-style harness or a Node script in `tests/Frontend/` that exits non-zero on failure)
- THEN every documented input shape MUST produce the expected output
- AND the test MUST fail when the helper regresses to produce `S/ S/`.

#### Scenario: Static analysis guard rejects duplicate `S/` literals

- GIVEN a CI hook that runs `tests/Unit/CurrencyFormatHelperSourceTest.php` (or equivalent) scanning `resources/js/**`
- WHEN the source contains the regex `S\/ \$\{` or `S\/ \{\{ formatCurrency`
- THEN the test MUST fail with a message naming the file and line.

### Requirement: Rollback is a single revert

The changes in this spec MUST be revertible by reverting the slice commit alone; no other spec in this change MUST depend on the helper.

#### Scenario: Reverting the slice restores the previous rendering

- GIVEN the slice is applied and all tests pass
- WHEN the slice commit is reverted via `git revert <sha>`
- THEN `php artisan test` MUST still pass
- AND the dashboard, session list, and existing currency components MUST render the pre-fix format (allowing the regression to be reintroduced, then re-fixed in a follow-up).

## Permissions

- No new permissions. The helper is a pure function invoked at render time. Existing role gates on `/dashboard` and `/cash-register` continue to apply.

## Rollback invariants

- Reverting the slice MUST NOT remove `formatCurrency` from any unrelated component.
- The helper file MUST be a single, self-contained module under `resources/js/composables/` or `resources/js/utils/`.
