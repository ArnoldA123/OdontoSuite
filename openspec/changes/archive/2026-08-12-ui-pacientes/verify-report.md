# Verify Report - pacientes (ui-rollout-all-modules-2026-08)

**Status**: **FAIL**

**Summary**: All 47 pacientes-targeted unit tests across the 5 new AppShell test suites + the 4 contract-preservation tests are GREEN, and the grep audit returns zero forbidden-class matches on the polished surface. However, **CRITICAL: `pnpm build` FAILS with a Vue compile error in `resources/js/modules/patients/PatientDetailPage.vue` line 261 (Invalid end tag)** introduced by `feat(ui): polish PatientDetailPage 5-tab drawer (PR-pacientes-03)`. The visual sweep is BLOCKED by the same build failure (the production bundle is broken) and additionally by the pre-existing `resources/js/components/ui/RadioGroup.vue` syntax error (predates the rollout; out of scope for pacientes, but the dev server cannot render any page until it is fixed). The pacientes PRs must either fix the line 261 extra `</div>` or revert PR-pacientes-03 before this slice can be archived.

## Static checks

All 5 new pacientes AppShell test suites + the 4 cross-cutting contract-preservation tests pass:

- **PatientsListAppShellTest**: 16 passed (47 assertions) - PAC-LIST-001 + DLR-R-002 + DLR-R-004 + DLR-R-021 + useEcho preservation + legacy `<Pagination>` import literal preservation.
- **PatientsModalAppShellTest**: 13 passed (42 assertions) - PAC-MOD-001 + 422 envelope preservation + usePermissions contract preservation + emit contract preservation.
- **PatientDetailAppShellTest**: 9 passed (28 assertions) - PAC-DET-001 (UiTabs + v-model binding + no `border-accent` active indicator) + PAC-DEEP-001 (4 deep-links verbatim) + PAC-RT-001 (5 channels verbatim).
- **PatientDetailEditExportAppShellTest**: 13 passed (39 assertions) - PAC-EDIT-001 + PAC-EXP-001 (binary download pattern preserved byte-for-byte + patient resource endpoint URL preserved).
- **PacientesNegativeSpaceRulesTest**: 7 passed (41 assertions) - PAC-WS-001 cross-cutting variant (PatientSelector not yet tokenized) + PAC-CON-001 Pagination variant (no silent rename) + PAC-DEEP-001 + PAC-RT-001 + PAC-PHI-001 (no dni/blood_type/insurance_provider/insurance_number in frontend) + PAC-EXP-001 PDF template out-of-scope guard + binary download triple preserved.
- **PatientResourceAgeTest**: 8 passed (16 assertions) - PAC-PHI-001 additive `age` integer key preserved.
- **PatientControllerResourceWireUpTest**: 5 passed (15 assertions) - PAC-PHI-001 `PatientResource` references preserved in every public CRUD method.
- **AppLayoutCanvasRoutesTest**: 25 passed (72 assertions) - canvasRoutes array literal includes `/patients`.
- **LegacyAliasForbiddenTest**: 10 passed (48 assertions) - alias regex whole-token match.
- **ApiAndSeedersPolishTest**: 5 passed (14 assertions) - API-035 + API-057 export Content-Type whitelist.
- **ComposablesStandardizationTest**: 3 passed (30 assertions) - composable contract preservation.
- **FormatPENLabelTest**: 21 passed (49 assertions) - formatPENLabel single-location rule.

Total: **135 passed (440 assertions)** across the 12 pacientes-targeted PHP files. Contract preservation tests fully green.

KNOWN: **PatientControllerAgeTest** (8 failures) - pre-existing SQLite migration error (`error in index idx_transactions_patient_type_status after drop column: no such column: type`). Identified in PR-pacientes-01 / 02 / 03 / 04 apply progress as a pre-existing environment issue (the test requires MySQL via `odontosuite_test`; the active phpunit.xml uses SQLite in-memory). NOT introduced by any PR-pacientes-NN. The PR-pacientes-01 apply-progress notes: "verified by stashing this PR's changes and re-running. Not introduced by PR-pacientes-01."

## Build & lint

- **pnpm build**: **FAILS** with the following error:

  ```
  [vite:vue] [plugin vite:vue] resources/js/modules/patients/PatientDetailPage.vue (261:7): Invalid end tag.
  SyntaxError: [plugin vite:vue] resources/js/modules/patients/PatientDetailPage.vue (261:7): Invalid end tag.
  ```

  Root cause: the `treatment-plans` template slot (lines 147-262) carries an unbalanced extra `</div>` at line 261. The depth analysis shows the closing tags at lines 256-261 over-close by TWO `</div>` elements. The structure is:

  ```
  255  </div>           (closes v-for div at line 220 - depth 3 to 2)
  256  </div>           (closes v-else div at line 219 - depth 2 to 1)
  257  </div>           (closes UiCard at line 171 - depth 1 to 0)
  258  </UiCard>        (already closed at 257 - EXTRA CLOSE)
  259  </div>           (closes space-y-6 at line 150 - depth 0 to -1)
  260  [empty]
  261  </div>           (EXTRA: no matching opening - depth -1 to -2)
  262  </template>      (closes treatment-plans slot - depth -2 to -3)
  ```

  The error is **CRITICAL**: the production build cannot produce a bundle. PR-pacientes-03 added this extra `</div>` (visible in the git show HEAD output). The error is reproducible from a clean checkout. **NONE of the PR-pacientes-01..05 apply phases ran `pnpm build` to validate the production bundle** - they only ran `php artisan test --filter=...` and `git grep`-based regex checks. The ModuleAppShellTestCase-based tests use regex pattern matching on the source file and do NOT run the Vue compiler, so the syntax error was not caught by the test suite.

- **pnpm lint:check**: 3154 problems (1729 errors, 1425 warnings) at HEAD. The patient-page templates touch < 100 lines of legacy class-string swaps per PR; the lint debt is pre-existing project-wide formatting noise (the original PR0 baseline was 3434 errors / 7117 warnings per the PAGOS verify report). The pacientes PRs contributed no new lint errors above the project baseline.

## Grep audit

- **border-theme in `resources/js/modules/patients/`**: 4 violations in `PatientDetailPage.vue` at lines 223, 336, 453, 571 - all in `class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"` on the per-tab list item wrappers (treatment-plans / quotations / medical-records / specialties panels). These are EXPLICITLY OUT OF SCOPE per `PatientDetailAppShellTest::test_no_legacy_border_theme_literal` (the override scopes the assertion to the polished sections only - header + 5-tab drawer nav + audit tab content via `extractPolishedSection()`). The apply-progress PR-pacientes-03 risk #5 documents these as "intentional" until a future polish slice migrates them to `<UiCard>`. PASS for the polished sections; SUGGESTION for the 4 unpolished panels.
- **border-theme in `resources/js/components/ui/PatientSelector.vue`**: 4 violations at lines 13, 32, 36, 77. EXPLICITLY OUT OF SCOPE (cross-module primitive consumed by 6+ modules; rides its own PR per global OQ#7). Pinned by `PacientesNegativeSpaceRulesTest::test_patient_selector_not_yet_tokenized` (the test asserts the CURRENT state + documents the upgrade contract for the future PR).
- **bg-success-badge / bg-danger-badge in `resources/js/modules/patients/`**: **0 violations**. PASS.
- **hover-lift in `resources/js/modules/patients/`**: **0 violations**. PASS.
- **`<Teleport to="body">` in `resources/js/modules/patients/`**: **0 violations**. PASS.
- **bg-black bg-opacity-50 in `resources/js/modules/patients/`**: **0 violations**. PASS.
- **focus:ring-primary-500 / focus:border-accent in `resources/js/modules/patients/`**: **0 violations**. PASS.
- **`<style scoped>` in `resources/js/modules/patients/PatientsPage.vue` + `PatientDetailPage.vue`**: **0 violations**. PASS.

## PAC-PHI-001 audit

`git grep -nE "dni|blood_type|insurance_provider|insurance_number"` on `resources/js/modules/patients/` returns **0 matches**. PASS. The dormant `Patient::$fillable` entries are NOT surfaced in the frontend; the active field is `document_number`. Pinned by `PacientesNegativeSpaceRulesTest::test_no_phi_envelope_widening_in_frontend`.

## Cross-category deep-links (PAC-DEEP-001)

`git grep -c "router.push.*\?patient_id" resources/js/modules/patients/PatientDetailPage.vue` returns **4 hits** (treatment-plans + quotations + medical-records + specialty-records). PASS. Pinned by `PatientDetailAppShellTest::test_detail_cross_category_deep_links_preserved` + `PacientesNegativeSpaceRulesTest::test_cross_category_deep_links_preserved`.

## Echo channels (PAC-RT-001)

`git grep -nE "channel\s*\(" resources/js/modules/patients/` returns **6 hits** (1 in `PatientsPage.vue` for `patients` channel + 5 in `PatientDetailPage.vue` for `patients`, `treatment-plans`, `quotations`, `medical-records`, `specialty-records`). PASS. Pinned by `PatientDetailAppShellTest::test_detail_echo_channels_preserved` + `PacientesNegativeSpaceRulesTest::test_use_echo_channels_preserved`.

## Visual sweep (playwright-cli)

**BLOCKED.** Two independent blockers:

1. **Production build failure** (CRITICAL, in-scope): `pnpm build` fails because of the `PatientDetailPage.vue` line 261 syntax error. The Vue compiler cannot produce a production bundle, so the visual sweep must be re-run after the build is fixed.
2. **Pre-existing RadioGroup.vue syntax error** (WARNING, out-of-scope): the dev server at `http://localhost:8000/patients` returns HTTP 200 for the SPA shell, but the Vite HMR overlay blocks all route rendering. The console error is: `[plugin:vite:vue] [vue/compiler-sfc] Missing semicolon. (19:52) E:/.../resources/js/components/ui/RadioGroup.vue 43 | return [base, selected].join(' ')` - the file's last commit is `c7010bb` (Sprint 3 paleta refactor), which predates every PR-pacientes-NN. The Citas verify report already documented this as a pre-existing defect. NOT introduced by any PR-pacientes-NN.

Worth noting: neither blocker is unique to pacientes - the build failure blocks archiving for ALL modules that depend on the pacientes bundle path, and the RadioGroup error blocks visual verification for ANY category. The orchestrator should land a separate hotfix for both before the pacientes slice can be archived.

**Skip rationale**: 0 screenshots captured. The visual sweep is documented as BLOCKED, not skipped-by-tooling. The 4 planned screenshots (patients list 1440x900 + 390x844, patient detail 1440x900 + 390x844) are not captureable in the current state.

## PAC MUSTs coverage table

| MUST | Spec | Status | Evidence |
| --- | --- | --- | --- |
| PAC-LIST-001 | `PatientsPage` list MUST use Ui primitives + tabular-nums on DNI/age | PASS | `PatientsListAppShellTest` 16/16 (47 assertions) covers Ui primitives + tabular-nums + no legacy aliases + no `hover-lift` + no `<style scoped>` + useEcho `patients` channel preservation |
| PAC-MOD-001 | Three inlined modals MUST use `<UiModal>` + `<UiInput>` + `<UiSelect>` + `<UiTextarea>` | PASS | `PatientsModalAppShellTest` 13/13 (42 assertions) covers 3 modals + 422 envelope + usePermissions + emit contract |
| PAC-DET-001 | `PatientDetailPage` 5-tab drawer MUST use `<UiTabs>` + cross-category deep-links preserved | PASS (static) / FAIL (build) | `PatientDetailAppShellTest` 9/9 (28 assertions) green at the regex level. The build fails with the line 261 syntax error introduced by PR-pacientes-03. |
| PAC-EDIT-001 | `PatientDetailPage` Edit Patient modal MUST use `<UiModal>` + `<UiSelect>` | PASS | `PatientDetailEditExportAppShellTest` 13/13 (39 assertions) covers Edit modal + no `bg-black bg-opacity-50` + no raw `<select>` + 422 envelope + useApi PUT |
| PAC-EXP-001 | Export action MUST use `<UiButton>` + preserve Bearer-token binary download pattern | PASS | `PatientDetailEditExportAppShellTest::test_detail_export_button_uses_ui_button` + `test_detail_export_binary_download_pattern_preserved` + `test_detail_export_calls_patient_resource_endpoint` + `ApiAndSeedersPolishTest` API-035 + API-057 |
| PAC-RT-001 | `useEcho` channel subscriptions MUST stay subscribed byte-for-byte | PASS | `PatientDetailAppShellTest::test_detail_echo_channels_preserved` 9/9 + `PacientesNegativeSpaceRulesTest::test_use_echo_channels_preserved` 7/7 (5 channels on PatientDetailPage + 1 channel on PatientsPage = 6 total) |
| PAC-PHI-001 | `PatientResource` API envelope MUST NOT widen or narrow | PASS | `PatientResourceAgeTest` 8/8 (16 assertions) - additive `age` integer key preserved; `PatientControllerResourceWireUpTest` 5/5 (15 assertions) - controller references `PatientResource` in every public CRUD method; `PacientesNegativeSpaceRulesTest::test_no_phi_envelope_widening_in_frontend` - no dormant `dni / blood_type / insurance_provider / insurance_number` fields in frontend |
| PAC-DEEP-001 | Cross-category deep-links MUST stay byte-for-byte | PASS | `PatientDetailAppShellTest::test_detail_cross_category_deep_links_preserved` 9/9 + `PacientesNegativeSpaceRulesTest::test_cross_category_deep_links_preserved` 7/7 (4 deep-links hit) |
| PAC-REV-001 | Each `pr-pacientes-NN` MUST stay under the 400-line review budget | PASS WITH WARNING | PR-pacientes-01: ~120 lines (concrete + tests, under budget). PR-pacientes-02: ~430 lines test file + template edits (slightly over for test-only file; production code under budget). PR-pacientes-03: 421 inserts + 218 deletes = 639 lines total (includes pre-existing prettier reformat of ~563 lines; PR's net authored contribution ~76 lines, under budget). PR-pacientes-04: 31 inserts + 51 deletes = 82 lines (well under budget). PR-pacientes-05: 0 production code changes (test-only). All within the 1000-line runtime attempt budget. |
| PAC-CON-001 | Existing paciente contracts MUST be preserved | PASS | `ComposablesStandardizationTest` 3/3 (30 assertions) + `PatientDetailEditExportAppShellTest::test_detail_edit_use_api_put_preserved` - `useApi` PUT signature preserved; `PacientesNegativeSpaceRulesTest::test_pagination_import_not_silently_renamed` - legacy `<Pagination>` import preserved verbatim |

## PR budget reconciliation

| PR | Budget | Actual (from apply-progress) | Settled | Verify note |
| --- | --- | --- | --- | --- |
| PR-pacientes-01 (`PatientsPage` list) | 400 | ~120 lines template + tests | yes | Static green; list compiles individually |
| PR-pacientes-02 (`PatientsPage` modals) | 400 | ~430 lines test + template | yes | Static green; modal tests pass |
| PR-pacientes-03 (`PatientDetailPage` 5-tab drawer) | 400 | 421 inserts + 218 deletes = 639 lines total (includes pre-existing prettier reformat) | yes (over at file-level, but authored contribution ~76 lines) | **CRITICAL: introduced the line 261 `</div>` syntax error that breaks the build** |
| PR-pacientes-04 (`PatientDetailPage` Edit modal + Export) | 400 | 31 inserts + 51 deletes = 82 lines | yes | Static green; did NOT fix the build error |
| PR-pacientes-05 (cross-cutting tests) | 400 | 1 new test file (~470 lines) + 1 doc append (~100 lines) | yes | Static green; no production code changes |

**Budget summary**: the 5 sub-PRs each fit within the 400-line authored budget on production code. The 1 critical regression is the syntax error introduced by PR-pacientes-03 - the PR added the extra `</div>` at line 261 (visible in the git show HEAD output) and the structural over-close is unrecoverable by the existing test suite. The apply-progress PR-pacientes-03 section notes "Modal chrome + tab strip visually untested" but does NOT flag the build failure.

## Deviations & warnings

1. **CRITICAL - Build failure on `PatientDetailPage.vue` line 261**. The treatment-plans template slot closes TWO `</div>` tags too many after the `</UiCard>` at line 258. The error is reproducible from a clean checkout via `pnpm build`. The PR-pacientes-03 changes introduced this error; the PR-pacientes-04 changes did not fix it (the Edit modal + Export action surface are entirely in the lines 700+ range, which is well after the line 261 regression). **Action**: fix the malformed template (remove the extra `</div>` at line 261 OR add a matching opening `<div>` if one was accidentally removed) before archiving. The orchestrator should land a separate hotfix PR OR have `sdd-apply` re-run on the pacientes branch with the fix.

2. **WARNING - Pre-existing `RadioGroup.vue` syntax error**. The dev server at `http://localhost:8000/patients` returns the Vite HMR error overlay (radioIndicator function at line 19:52 missing semicolon). The file's last commit is `c7010bb` (Sprint 3 paleta refactor), well before any PR-pacientes-NN. The Citas verify report already documented this. **Not introduced by the pacientes PRs** - pre-existing defect that blocks visual verification for ALL categories. **Action**: separate hotfix PR.

3. **WARNING - `border-theme` violations in `PatientDetailPage.vue` tab panels (4 hits at lines 223, 336, 453, 571)**. The per-tab list item wrappers (`border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors`) on the treatment-plans / quotations / medical-records / specialties panels are explicitly OUT OF SCOPE per `PatientDetailAppShellTest::test_no_legacy_border_theme_literal` override. The apply-progress PR-pacientes-03 risk #5 documents these as deferred to a future polish slice. SUGGESTION (not a blocker for archive). **Action**: future polish slice migrates these to `<UiCard>`.

4. **WARNING - `border-theme` violations in `PatientSelector.vue` (4 hits at lines 13, 32, 36, 77)**. Cross-module primitive consumed by 6+ modules. Pinned by `PacientesNegativeSpaceRulesTest::test_patient_selector_not_yet_tokenized` (the test asserts the CURRENT state + documents the upgrade contract). SUGGESTION. **Action**: future global PR per global OQ#7.

5. **WARNING - `PatientControllerAgeTest` 8 failures (pre-existing SQLite migration error)**. The test requires MySQL via `odontosuite_test`; the active phpunit.xml uses SQLite in-memory. The 8 failures are identical to the same pre-existing failures documented in PR-pacientes-01 / 02 / 03 / 04 apply progress. **Not introduced by the pacientes PRs**. The acceptance criteria for `PatientControllerAgeTest` stay green at the pacientes boundary (the unit + feature contract is preserved by the resource + wireup tests; the controller age test is an SQLite-specific infrastructure issue). **Action**: orchestrator should run the test suite against MySQL before archiving.

## Final status

**FAIL - CRITICAL: `pnpm build` fails with a Vue compile error in `resources/js/modules/patients/PatientDetailPage.vue` line 261 (Invalid end tag).** Introduced by `feat(ui): polish PatientDetailPage 5-tab drawer (PR-pacientes-03)`. The 47 unit tests + 4 contract-preservation tests pass at the static level, and the grep audit returns zero forbidden-class matches on the polished surface. The visual sweep is doubly blocked (production build + pre-existing RadioGroup.vue syntax error). The slice cannot be archived until the line 261 extra `</div>` is fixed and the production build is green. The pre-existing `PatientControllerAgeTest` SQLite migration failures + the `RadioGroup.vue` syntax error are NOT in scope and should be addressed in separate hotfix PRs.

---

End of verify report.
