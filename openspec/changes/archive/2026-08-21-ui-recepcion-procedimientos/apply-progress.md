# Apply Progress: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

> Single PR `pr-recepcion-procedimientos-tokenise` applied.

## Metadata
| Key | Value |
|---|---|
| Date | 2026-08-21 |
| PR | pr-recepcion-procedimientos-tokenise |
| Status | PARTIAL |
| Lines changed | 365 (implementation, test, task, and report artifacts) |

## Phase 1 results
- T1: Created `ReceptionProceduresAppShellTest.php`. RED confirmed before production changes: 9 failures (7 REC assertions and inherited module rules).
- Safety net before changes: `vendor/bin/phpunit tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` passed 25 tests, 72 assertions.
- TDD evidence: `RED 9 failures` → `GREEN 12/12 tests, 36 assertions` after implementation → `REFACTOR clean; no production refactor required`.

## Phase 2 results
- T2: Verified `'/reception-procedures'` in `AppLayout.vue` canvasRoutes and `AppLayoutCanvasRoutesTest.php`; no route edit.
- T3: Replaced the raw search input with `UiInput`, preserving the search icon in its `prefix` slot.
- T4: Replaced the raw select with `UiSelect` and its required `options` prop; the template maps the existing specialty objects without changing `<script setup>`.
- T5: Replaced the inline code chip with `UiBadge variant="primary" size="sm" class="font-mono"`.
- T6: Replaced the hand-rolled empty state with `UiEmptyState` and the approved Spanish copy.
- T7: Replaced the price accent class with `text-systemBlue-600 tabular-nums` and the tabular-number inline feature setting.
- T8: Removed the card hover override and changed the divider to `border-hairline`.

## Phase 3 results
- T9: `vendor/bin/phpunit tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` passed 12/12 tests, 36 assertions.
- Focused route regression: `vendor/bin/phpunit tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` passed 25/25 tests, 72 assertions.
- T10: `pnpm build` passed. The full DesignSystem run executed 448 tests and reported 2 unrelated pre-existing failures in `LoginPageRenderTest` and `PrimitivePressTest`; the API run executed 137 tests and reported 95 SQLite migration errors caused by the documented `transactions.type` SQLite limitation. T10 remains PARTIAL.
- T11: Visual capture skipped because the installed `playwright-cli` check failed with a Windows assertion error; no screenshot was produced.

## Phase 4 results
- T12: `feat(ui): tokenise recepcion-procedimientos per DLR + REC-* MUST rows` committed as `654130a`.
- T13: Fast-forward merged to `main`; the implementation merge record is `5538d7f`, and the final main metadata commit is `05bfd5a`.

## Test count delta
+1 = new `ReceptionProceduresAppShellTest`; inherited module rules are unchanged.

## Risks encountered
- T4 uses the existing `UiSelect` `options` prop with an inline specialty mapping. This preserves the primitive contract and the no-`script` constraint, but differs from the task wording suggesting `specialtyOptions` or option children.
- Full regression sweeps are environment-limited; no unrelated failure was modified.

## Work Unit Evidence
| Evidence | Result |
|---|---|
| Focused test | `vendor/bin/phpunit tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` — 12/12 passed, 36 assertions |
| Runtime harness | `pnpm build` — PASS; `vendor/bin/phpunit tests/Feature/Api` — PARTIAL, 95 SQLite migration errors |
| Rollback boundary | Revert `ReceptionProceduresPage.vue` and `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` together |

## TDD Cycle Evidence
| Task | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|
| T1 | 9 test failures | 12/12 focused tests | 7 REC source cases plus 5 inherited cases | N/A, new test |
| T2-T8 | T1 failure set | 12/12 focused tests | 7 independent REC source cases | N/A, no refactor |
| T9 | Focused suite initially red | 12/12 passed | Same structural cases re-run | N/A |
| T10 | Full sweep exposed unrelated failures | Build passed; sweeps partial | 448 DesignSystem and 137 API cases run | No unrelated changes |
| T11 | N/A; Playwright CLI assertion failure | Screenshot intentionally skipped | N/A, visual harness unavailable | N/A |

## Next
sdd-apply again (T10 remains PARTIAL because the full DesignSystem and API sweeps did not pass)
