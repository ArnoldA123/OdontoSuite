# Sync Report: Login Premium Motion (`ui-login-premium-motion-2026-08`)

Change: `ui-login-premium-motion-2026-08`
Phase: sync
Artifact store: hybrid (this file + Engram `sdd/ui-login-premium-motion-2026-08/sync-report`)
Date: 2026-08-23

---

## What this sync captures

This is the **mid-slice sync** for `ui-login-premium-motion-2026-08`. PR1 foundation is COMPLETE on the working tree (not yet committed). PR2 composition is pending user round-trip. A second sync will run after PR2 completes; archive happens at end of full slice.

---

## Artifacts in this change (`openspec/changes/ui-login-premium-motion-2026-08/`)

| File | Status | Purpose |
|---|---|---|
| `explore.md` | ✅ complete | Inventory + scope + open questions |
| `proposal.md` | ✅ complete | 6 microinteractions IN-scope + 5 deferred (documented) |
| `spec.md` | ✅ complete | Spec index with 7 spec files |
| `specs/login-magnetic-hover.md` | ✅ complete | M1-M8 + AC1-AC3 |
| `specs/login-live-validation.md` | ✅ complete | V1-V7 + AC1-AC4 |
| `specs/login-glassmorphism.md` | ✅ complete | G1-G4 + AC1-AC4 |
| `specs/login-brand-glyph-morph.md` | ✅ complete | P1-P6 + AC1-AC4 |
| `specs/login-multi-stage-loading.md` | ✅ complete | L1-L5 + AC1-AC4 |
| `specs/login-premium-motion-reduced-motion.md` | ✅ complete | R1-R5 + AC1-AC3 (cross-cutting) |
| `specs/login-premium-motion-bundle-budget.md` | ✅ complete | B1-B4 + AC1-AC3 (cross-cutting) |
| `design.md` | ✅ complete | 10 architecture decisions (D17-D26) + composable APIs + Button.vue additive + PR1/PR2 file changes |
| `tasks.md` | ✅ Phase 1 + Phase 2 written | Phase 1 checkboxes marked -[x]; Phase 2 pending |
| `apply-progress.md` | ✅ PR1 complete + deviations D1-D6 documented | Includes D5 F-03 fix |
| `verify-report.md` | ✅ PASS for PR1 + F-03 fix appended | 0 CRITICAL, 3 WARNING (F-01/F-02 + F-03 FIXED) |
| `sync-report.md` | ✅ this file | Mid-slice sync |
| `archive-report.md` | ⏳ pending | Will be written after PR2 archive |

## Engram observations

| Topic key | ID | Title |
|---|---|---|
| `sdd/ui-login-premium-motion-2026-08/explore` | 608 | Explore — ui-login-premium-motion-2026-08 |
| `sdd/ui-login-premium-motion-2026-08/proposal` | 609 | Proposal — ui-login-premium-motion-2026-08 (7 specs) |
| `sdd/ui-login-premium-motion-2026-08/spec` | 610 | Spec — ui-login-premium-motion-2026-08 (7 specs) |
| `sdd/ui-login-premium-motion-2026-08/design` | 611 | Design — ui-login-premium-motion-2026-08 (2 PRs, ~600 lines) |
| `sdd/ui-login-premium-motion-2026-08/tasks` | 612 | Tasks — ui-login-premium-motion-2026-08 (2 PRs strict TDD) |
| `sdd/ui-login-premium-motion-2026-08/apply-progress` | 613 | Apply progress — ui-login-premium-motion-2026-08 (PR1 complete) |
| `sdd/ui-login-premium-motion-2026-08/verify-report` | 614 | Verify report — PASS for PR1 + 3 WARNING findings |
| `sdd/ui-login-premium-motion-2026-08/verify` | 615 | Verify complete + F-03 fix |

## Code state (working tree, not committed)

### Modified
- `package.json` — added `@vueuse/motion: 3.0.3` (exact pin).
- `pnpm-lock.yaml` — regenerated.
- `resources/js/app.js` — added `import { MotionPlugin } from '@vueuse/motion'` (line 4) and `app.use(MotionPlugin)` (line 264).
- `resources/js/components/ui/Button.vue` — added F-03 fix: `:hover` rule scoped to exclude `[data-magnetic='true']`; additive rule bumped specificity to 0,2,1.

### Created
- `resources/js/composables/magneticHoverMath.js` (pure `computeOffset`).
- `resources/js/composables/useMagneticHover.js` (Vue wrapper).
- `resources/js/composables/fieldValidationMath.js` (pure `attachValidator`).
- `resources/js/composables/useFieldValidation.js` (Vue wrapper).
- `tests/Unit/Composables/AppShellTest.php` (1 source-grep test).
- `tests/Unit/Composables/MagneticHoverTest.php` (5 tests: 4 clamp math + 1 Button.vue source-grep).
- `tests/Unit/Composables/FieldValidationTest.php` (6 tests: 5 state machine + 1 timing).

### Untracked
- `openspec/changes/ui-login-premium-motion-2026-08/` (this change's artifacts).

## Test results (mid-sync snapshot)

```
php artisan test tests/Unit/Composables/  → 60 passed (182 assertions)
AppShellTest + MagneticHoverTest + FieldValidationTest + UseSpringMathTest → 24 passed (70 assertions)
pnpm build  → success in 8.05s
app-y3ZiBNJX.js = 479.82 kB raw / 152.93 kB gzip
delta vs main = +12.95 kB gzip (8% over aspirational +12 kB budget; deviation D3)
```

## Deviations snapshot

- **D1** — Baseline 48 pre-existing Unit failures on `main` (SQLite MODIFY COLUMN + 9 stale source-inspection). Out of scope.
- **D2** — Design adjustment: pure-math extracted to `*Math.js` modules (positive; parity with `useSpringMath.js`).
- **D3** — Bundle budget: +12.95 kB gzip vs +12 kB target (~8% over; monitored + accepted).
- **D4** — PR1 line count ~530 vs ~330 forecast (per-file OK; cumulative above 400-line guideline).
- **D5** — F-03 CSS cascade ambiguity: FIXED on working tree (specificity bump).
- **D6** — PR1 line count documented (D4 + D6 merged conceptually).

## Open work for PR2 (after PR1 merge + user OK)

PR2 composition tasks in `tasks.md` § Phase 2:
- 2.1 Brand glyph SVG morph (~600ms SMIL `<animate>`).
- 2.2 Magnetic hover wiring (LoginPage template + submit ref).
- 2.3 Live validation wiring (replace existing `errors` ref with composable).
- 2.4 Glassmorphism (attach `.decorative-glass`).
- 2.5 Multi-stage loading (Validando → Autenticando → Listo) + 220ms shake.
- 2.6-2.7 6 new `LoginPageRenderTest` source-inspection assertions + manual Playwright sweep (1440x900 + iPhone 12).
- 2.8 PR2 merge gate.

PR2 adds 0 kB to bundle (composition only). Manual sweep is the only human-in-the-loop step.

---

## Result

```yaml
status: success
executive_summary: |
  PR1 foundation COMPLETE on working tree (4 modified + 7 untracked).
  Verify report PASS for PR1 with 0 CRITICAL findings; F-03 CSS cascade
  ambiguity FIXED post-verify. Bundle delta +12.95 kB gzip (8% over
  aspirational +12 kB). All artifacts persisted to Engram + filesystem.
  PR2 composition pending user round-trip after PR1 merges to main.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/sync-report.md    (this file)
  - engram://sdd/ui-login-premium-motion-2026-08/sync-report          (persisted)
next_recommended: |
  User round-trip to gate PR1 → commit + open PR → after PR1 merges →
  launch PR2 → final sync + archive at end of full slice.
risks:
  - Bundle budget is 8% over the +12 kB aspirational target. Mitigated by 0 kB PR2 delta + future v-motion consumption.
  - PR1 line count ~530 vs ~330 forecast. Each file is small but cumulative exceeds the 400-line guideline.
  - 48 pre-existing Unit failures on `main` persist. PR2 must not regress but cannot be expected to fix.
skill_resolution: paths-injected
```
