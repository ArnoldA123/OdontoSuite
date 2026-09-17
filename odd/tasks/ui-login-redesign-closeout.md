# Task: UI login redesign — closeout pass

Status: COMPLETE — T1-T7 done; verification held, RDD approved and burned
Created: 2026-09-16
Closes: `odd/tasks/ui-login-redesign-arena.md` (the 6 open items it left behind)
Supersedes (deleted): `openspec/changes/ui-redesign-apple-claude-2026-08`,
`openspec/changes/ui-rollout-all-modules-2026-08`

## Goal

Close the pendings that `ui-login-redesign-arena` left open, without opening new
fronts. Four of the six open items are closed here, one was already false, and
one stays open by explicit user decision.

## User decisions (2026-09-16)

| # | Question | Decision |
|---|---|---|
| U1 | Hero rule: HOTFIX-LOGIN-006 (no photo) vs live spec `premium-design-foundation:349` (photo required) | **Retire the obsolete guard.** The photo stays. |
| U2 | The 2 stalled OpenSpec changes | **Delete them.** |
| U3 | Extra scope (guards audit as a class, `lint:check` triage) | **Not now.** Close the plan's pendings only. |

## Measured state before the first write

| Signal | Value |
|---|---|
| `php artisan test tests/Feature/Ui` | **2 failed / 36 passed** — both in `HotfixLoginHeroSourceTest` |
| `php artisan test --filter=DesignSystem` | **5 failed / 540 passed** (5 pre-existing: dashboard + press suites, unrelated to tokens) |
| Candidate-view worktree `2beea149` | registered, `owner.json` pid **2392 = DEAD** → orphan residue, no open transaction |
| `review-transactions/v2/` | only `LOCK` — zero live transactions; `quarantine/` holds 2 abandoned audit records (kept) |
| `admin_test` password | `password123` — same as the other 14 seeded users. The open item was **false**. |

## Evidence for U1 (the decision that unblocks 2 tests)

| Fact | Source |
|---|---|
| HOTFIX-LOGIN-006 was authored 2026-08-22 | `git log -1 0cb1acf --date=short` |
| The rule lives ONLY in an archived change's spec | `openspec/changes/archive/2026-08-22-ui-rollout-hotfix-premium-2026-08/spec.md:48` |
| A later merged SDD change shipped the dental hero on purpose | `7f2f1da` (2026-09-08), archived as `ui-login-refinement-dental-split-2026-09-merged` |
| The LIVE canonical spec REQUIRES the photo | `openspec/specs/premium-design-foundation/spec.md:349` |
| The asset is tracked (safe on a fresh clone) | `git ls-files public/images/ui/login-hero.jpg` → present |
| `HotfixLoginHeroSourceTest` contradicts all of the above | its 2 assertions are the only failures |

`git merge-base --is-ancestor 0cb1acf 7f2f1da` → YES. The guard is older than the
rule it now contradicts.

## Tasks

- [x] T1 — Retire the obsolete hero guard; keep its one still-valid assertion.
      Delete `tests/Feature/Ui/HotfixLoginHeroSourceTest.php` and move the
      structural check (exactly one `<aside class="login-hero-column">`) into
      `LoginPageRenderTest.php`, next to the live-spec hero assertions.
      Gate: `php artisan test tests/Feature/Ui` → 0 failed. **MET (36 passed).**
- [x] T2 — Slice A3, radius rhythm. `tokens.js`: `radius.control` 8 → 12, add
      `radius.panel` 22 and `radius.shell` 32.
      - [x] T2a — `GeneratedTokensCssTest.php:749` pinned `--radius-control: 8px`
            as a literal (the A0 defect class). Rewritten as a token read via a
            new generic `loadTokensSubtree()` loader.
      - [x] T2b — `TokensModuleTest::test_nested_radius_keys_added` now asserts
            the ordering rule `control < panel < shell` instead of literals.
      - [x] T2c — `tokens.generated.css` regenerated (11821 → 11862 bytes).
      - [x] T2d — `LoginPage.vue`'s `--login-radius-panel` / `--login-radius-control`
            now delegate to `var(--radius-panel)` / `var(--radius-control)`; the
            outer card is `rounded-[var(--radius-shell)]`.
      Gate: `--filter=DesignSystem` → same 5 failed / 541 passed. **MET.**
      Both new guards were bite-tested: diverging `tokens.js` from the generated
      CSS fails the ramp guard (`must declare --radius-shell: 34px`), and an
      inverted ladder (`shell 18px < panel 22px`) fails the ordering guard.
- [x] T3 — Orphan candidate-view worktree removed. **MET** (`git worktree list`
      shows `main` only; `candidate-views/` is empty). Note: `git worktree
      remove` deleted the registration but failed on the tree (“Permission
      denied”); the cause was NOT path length (the root is 157 chars) — the
      frozen view marks every entry **ReadOnly**, and Windows refuses to remove
      a read-only directory. Cleared 1221 file + 234 dir attributes, then
      deleted with a `\\?\` extended path (deepest file: 274 chars).
- [x] T4 — The 2 stalled OpenSpec changes deleted. **MET, with a correction to
      the premise.** `openspec/changes/` still holds 5 more changes (Aug 5-10),
      which were out of the user's decision and are reported, not touched.
      The premise itself was partly wrong: `ui-rollout-all-modules-2026-08` was
      not abandoned, it was PAUSED mid-rollout (see Open items §5 in
      `ui-login-redesign-arena.md`).
      Follow-up found by delegated verification: deleting the folders broke 4
      docblock citations into the deleted `categories/` tree (1 absolute, 3
      relative). All 4 were repointed to the archive paths that hold the cited
      documents, and `tests/` now has zero `categories/` references.
- [x] T5 — The 6 open items of `ui-login-redesign-arena.md` are closed with
      measured evidence: 4 resolved, 1 handed to the user, 1 still open.
- [x] T6 — Verification (delegated to `gentle-ai-verify`, read-only). **All 5
      gates HELD**: `pnpm build` exit 0 (630 modules), `tests/Feature/Ui` 36
      passed, `--filter=DesignSystem` 5 failed / 541 passed (the same 5
      pre-existing, by name), Composables 78 passed, Documentation 22 passed.
      The verifier independently confirmed the built CSS really contains
      `.rounded-\[var\(--radius-shell\)\]` (so the outer card cannot silently
      lose its radius), that `tokens.generated.css` matches `tokens.js`
      exactly, and that the blast radius of `radius.control` is ONE additional
      visual site: `ProcedureStatsPage.vue:222` (8px → 12px).
- [x] T7 — RDD preflight and review. Lineage `review-8991f54ee2633ea8`, 50
      paths, 12923 changed lines, 4 lenses, **0 blockers**, 12 advisory. State
      `approved`; authority **burned** (`gentle-ai.review-acknowledged/v1`);
      `delivery: ordinary-repository-policy`. Two 2-lens convergences:
      `CREDENTIALS.md:100` (readability + reliability) and
      `useMagneticHover.js:76-86` (reliability + resilience — the severity
      escalated from SUGGESTION in the previous review).

## Deferred (excluded by explicit user decision, still open)

- **The ~11 false guards as a class** — the systemic finding of this work: a
  guard that asserts the artifact instead of the source of truth. Still open.
- **`pnpm lint:check`** — 4349 problems (1981 errors, 2368 warnings); never a
  CI gate. Still open.
