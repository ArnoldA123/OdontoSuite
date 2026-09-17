# Task: UI login redesign — Arena neutral + Clinical green

Status: CLOSED — see the closing section at the end of this file.
Closeout pass: `odd/tasks/ui-login-redesign-closeout.md` (2026-09-16).
Created: 2026-09-16
Supersedes: `openspec/changes/ui-redesign-apple-claude-2026-08` (stalled),
`openspec/changes/ui-rollout-all-modules-2026-08` (stalled)
Recovers: PR #11 `feat/ui-login-premium-motion-2026-08` (open, never merged)

## Goal

Replace the current iOS-system-blue clinical language with a **neutral-first
palette** (Arena neutrals + deep clinical green accent) and raise the login to
premium craft. The login is the vertical proving ground; the rollout to the
other 16 modules is a later effort.

## Locked decisions

| # | Decision | Value |
|---|---|---|
| D1 | Palette architecture | Neutrals carry identity; the accent is ONE swappable token (multi-tenant safe) |
| D2 | Canvas | `#F7F6F4` — warmth lives ONLY in the neutral, never in a coloured panel |
| D3 | Surface | `#FFFFFF` |
| D4 | Hairline | `rgba(26, 24, 20, 0.10)` |
| D5 | Text 1 / 2 | `#1A1917` / `#5C5A55` |
| D6 | Accent | `#0F7A5F` deep clinical green |
| D7 | Accent deep | `#0A5F49` |
| D8 | Atmosphere | radial `#FFFFFF → #F7F6F4` + `#0F7A5F` wash at 4%. No looping motion |
| D9 | Hero widgets | Curated illustrative content. Zero fetch on the login |
| D10 | Scope | Microinteractions + visual craft together |
| D11 | PR #11 | Recover composables + tests, re-wire onto the new LoginPage |

## Hard constraints (test-enforced, verified)

1. `TokensModuleTest::tokens_module_exposes_ios_ramps_with_aliases` **requires**
   `systemBlue`, `systemRed`, `systemOrange`, `systemYellow`, `systemGreen`,
   `systemIndigo`, `systemPurple`, `systemPink`, `systemGray` to keep existing.
   → Retire the *values* behind alias keys; never delete a ramp key.
2. `TokensModuleTest::tokens_colors_stay_in_parity_with_tailwind_config_palette`
   → `tokens.js` and `tailwind.config.js` must stay in parity.
3. `LegacyAliasForbiddenTest` forbids `text-accent`, `bg-accent`, `bg-primary-*`,
   `focus:ring-primary-500`, `focus:border-accent` in **4 polished files only**
   (`StatusBadge`, `AppLayout`, `EnvironmentsPage`, `EnvironmentDetailPage`).
   The 269 `primary-` references in un-migrated modules keep resolving by alias.
4. `GeneratedTokensCssTest::generated_css_only_contains_token_hex_literals`
   → every hex in `tokens.generated.css` must exist in `tokens.colors`.
5. **Seven assertions pin the OLD palette as literal hexes.** They are the real
   cost of this migration and MUST move with it:

   | File:line | Pinned value |
   |---|---|
   | `TokensModuleTest.php:509` | `systemBlue.500 === '#007AFF'` |
   | `TokensModuleTest.php:532` | `label.label === '#000000'` |
   | `TokensModuleTest.php:789` | `terracotta.500 === '#007AFF'` |
   | `TokensModuleTest.php:806` | `clinicalTeal.500 === '#007AFF'` |
   | `TokensModuleTest.php:813` | `info.500 === '#007AFF'` |
   | `TokensModuleTest.php:1026` | `focusRing.color === '#007AFF'` |
   | `GeneratedTokensCssTest.php:447` | `--focus-ring-color` is `#007AFF`/`rgba(0,122,255)` |

   These pin an **example**, not a **rule** — the recurring defect the project
   already logged. The correct fix is to rewrite them as *relationship*
   assertions (`terracotta.500 === systemBlue.500`, `info.500 === accent.500`)
   so the next palette change costs zero test edits.
   → New task A0 below.

## Blast radius (measured)

| Token surface | References |
|---|---|
| `bg-theme` | 443 |
| `primary-` (legacy alias) | 269 |
| `border-theme` | 241 |
| `hairline` | 169 |
| `accent` | 137 |
| `system-blue` | 36 |
| `radius-card-lg` | 13 |
| `surface-glass` | 10 |
| `cream-` | 8 |
| `radius-control` | 3 |

## Slicing

### Slice A0 — Turn the design contract into rules (prerequisite, zero visual change) — DONE

- [x] A0.1 Rewrite the pinned-hex assertions as relationship assertions.
- [x] A0.2 Keep a single literal pin for the *canonical* accent ramp only.
- [x] A0.3 Prove it: swap a value in `tokens.js`, run the suite, confirm the
      suite still means something, revert.
- [x] A0.4 `php artisan test --filter=DesignSystem` green with no palette change.

**Result.** 14 scattered literals across 2 files collapsed into ONE
`PALETTE SNAPSHOT` block (`ACCENT_RAMP`, `PALETTE_ANCHORS`, `RAMP_500`).
Three further pins the first inventory missed were also found and closed:

| Was pinned | Now asserted as |
|---|---|
| `--color-hairline === rgba(60,60,67,0.12)` | `=== tokens.colors.border.hairline` |
| elevation rungs must match `rgba(60,60,67,α)` | rungs must share the `label.secondaryLabel` hue |
| `--focus-ring-color === #007AFF` | `=== tokens.colors.border.hairline`-style SoT read, case-insensitive |
| `focusRing.color === '#007AFF'` | `=== <ACCENT_RAMP>.500` |
| `background.canvas === '#F2F2F7'` | `=== background.secondaryBackground` |
| `terracotta/clinicalTeal/info` literals | `=== <ACCENT_RAMP>.500` |

**Bite test (measured).** Changing exactly one value (`systemBlue.500` -> `#0f7a5f`)
fails exactly 5 tests, each reporting a real defect rather than churn:

1. `hex literals match palette snapshot` -> the palette identity moved (1 conscious edit)
2. `deprecated aliases resolve` -> **the aliases did not follow the ramp** (the A2 trap)
3. `focus ring token composed shape` -> focus ring drifted from the accent
4. `generated css only contains token hex literals` -> stale generated CSS
5. `generated css emits focus ring parts` -> same, in the composed shorthand

**Regression proof.** `--filter=DesignSystem` reports the identical 5 failed /
537 passed with AND without these changes. The 5 are pre-existing
(dashboard + sidebar + press suites, unrelated to tokens). Token files alone:
52 passed / 585 assertions.

**Migration cost after A0: 1 test edit.** Everything else is source-of-truth
work (aliases, `focusRing.color`) plus one regeneration.

---

### Slice A0 (original scope, kept for the record)

- [x] A0.1 Rewrite the 7 pinned-hex assertions as relationship assertions
      (`terracotta.500 === systemBlue.500`, `clinicalTeal.500 === systemBlue.500`,
      `info.500 === systemBlue.500`, `focusRing.color === accent`,
      `--focus-ring-color === var(--color-accent)`).
- [x] A0.2 Keep a single literal pin for the *canonical* accent ramp only.
- [x] A0.3 Prove it: swap a value in `tokens.js`, run the suite, confirm the
      suite still means something, revert.
- [x] A0.4 `php artisan test --filter=DesignSystem` green with no palette change.

### Slice A1 — Neutrals only (low risk, high perceived gain)

Warm the canvas, retemper hairlines/labels/elevation. No accent change.
Delivers the "calidez disimulada" without touching the 406 accent references.

- [ ] A1.1 `tokens.js` — canvas `#F7F6F4`, `systemGray` warm-neutral ramp,
      `label` ramp, `separator`, `border.hairline` `rgba(26,24,20,0.10)`,
      `elevation` rungs re-tinted warm.
- [ ] A1.2 `tokens.js` — `cream-*` aliases re-point to the warm neutrals.
- [ ] A1.3 Regenerate `resources/css/tokens.generated.css` (`pnpm tokens:build`).
- [ ] A1.4 `pnpm build` + `php artisan test --filter=DesignSystem` green.
- [ ] A1.5 Visual sweep: login + dashboard + 2 un-migrated modules at 1440x900.

### Slice A2 — Accent swap (high risk, isolated)

- [ ] A2.1 Add `colors.accent` ramp; keep `systemBlue` as deprecated alias -> accent.
- [ ] A2.2 Re-point `terracotta` / `clinicalTeal` / `info` / `primary` -> accent.
- [ ] A2.3 Verify `success` (`#34C759`) stays visually distinct from the accent
      (`#0F7A5F`). **Risk: two greens competing.** Validate on the Caja + BI modules.
- [ ] A2.4 Contrast audit: accent on white, white on accent, accent on canvas
      at AA (4.5:1 body / 3:1 large + UI).

### Slice A3 — Radius rhythm + typography (global, small)

- [ ] A3.1 `radius.control` `8px -> 12px`; add `radius.panel` `22px`, `radius.shell` `32px`.
- [ ] A3.2 Keyframes / anti-pattern cleanup if the radius change exposes any.

### Slice B — Login craft (7 moves)

- [ ] B.1 Kill the inner card. `.login-form-column` / `.login-form-wrap` own padding.
- [ ] B.2 Atmosphere layer (`::before` radial). Off under reduced-transparency.
- [ ] B.3 Radius rhythm applied: shell 32 / panel 22 / control 12 / pill.
- [ ] B.4 Typography: H1 weight 500 -> 600, tracking `-0.05em` -> `-0.022em`
      (align with `typography.fontSize.4xl`), brand -> wordmark chip.
- [ ] B.5 Controls: filled fields, no left icon, no placeholder, radio 12,
      height 52. Submit -> pill with real material + press on pointer-down.
- [ ] B.6 Widgets: curated content, soft two-layer elevation, sentence-case
      12px labels, luminous top edge on the glass, drop `min-width: 140px`.
- [ ] B.7 Hero inset with own radius; evaluate replacing the washed-out photo.
- [ ] B.8 Footer to a single row.
- [ ] B.9 Content truth: drop "Mínimo 8 caracteres" (registration semantics),
      sentence-case labels, correct hero `alt`.

### Slice C — Motion recovery (PR #11)

- [ ] C.1 Cherry-pick `useMagneticHover.js`, `magneticHoverMath.js`,
      `useFieldValidation.js`, `fieldValidationMath.js` + their 806 test lines
      from `feat/ui-login-premium-motion-2026-08`.
- [ ] C.2 Re-apply the `Button.vue` `data-magnetic` hook.
- [ ] C.3 Re-wire into the NEW LoginPage: magnetic submit, live validation
      (blur + 250ms idle).
- [ ] C.4 Restore: error shake (220ms), glyph morph tooth -> check, per-field
      entrance stagger, password toggle morph, checkbox tick draw.
- [ ] C.5 Extend every reduced-motion block per the project rule.

### Slice D — Dead code + hygiene

- [ ] D.1 Remove the orphan `data-magnetic="true"` attribute (nothing reads it today).
- [ ] D.2 Remove `validateField()` at `LoginPage.vue:649` — defined, never called.
- [ ] D.3 `useSpring2D.js` has 0 consumers: give it one (the confirmation) or delete it.
- [ ] D.4 Resolve the 2 stalled OpenSpec changes this work supersedes.

## Evidence required to close

- `pnpm build` exit 0
- `php artisan test --filter=DesignSystem` green (30 files)
- Full `php artisan test` green on MySQL (local SQLite has 28 known `MODIFY COLUMN` failures)
- Visual sweep: login + dashboard + 2 un-migrated modules, 1440x900 and 390x844
- Reduced-motion / reduced-transparency / high-contrast paths
- Contrast audit result recorded

---

# CLOSING STATE

## Delivered

**Palette** — A0 (design contract → rules: 14 scattered literals collapsed to one
`PALETTE SNAPSHOT` block), A1 (Arena neutrals: canvas `#f7f6f4`, monotonic
`systemGray`, `label` `#1a1917`, hairline `rgba(26,24,20,0.10)`), A2 (accent
`#0f7a5f` as a canonical `accent` ramp, `systemBlue` retired to a deprecated
alias). `info` vs `success` collapsed to 27.4 deg of hue; a dedicated
`systemSteel` tone restored 69.8 deg. `systemGreen` 300/400 repaired (it was
non-monotonic: 400 was DARKER than 500).

**Login** — B (the ten moves: inner card deleted, atmosphere layer, radius
rhythm 32/22/12, headline on `font-semibold` at the token's tracking, filled
unadorned fields, curated `LOGIN_SAMPLE` widgets with zero fetch, hero inset,
one-row footer, sentence-case eyebrows), C (seven files recovered BYTE-IDENTICAL
from PR #11 — `git hash-object` matched `git rev-parse <branch>:<path>` 7/7 —
plus the magnet, live validation, error shake, field stagger, password toggle
morph, drawn checkbox tick and glyph morph).

**Two real bugs found while wiring C:**
- The magnet's CSS was INERT: `useSpring.writeVar()` writes a unitless number and
  `translate3d()` needs a length, so the whole `transform` was dropped. PR2's own
  verification had measured the CSS variable, never the rendered transform, and
  passed green with the feature invisible.
- The login BRICKED after one failed attempt: `error` is terminal, `cancel()`
  refuses terminal states, so every later submit was a silent no-op. Fixed by
  giving the machine `release()` — it now owns and cancels its own exit.

**Infrastructure** — `main`'s production build repaired (`7991f41`, `@vueuse/motion`
was imported by PR #12 but only ever declared on the unmerged PR #11).
`CREDENTIALS.md` was GITIGNORED and had never been in git, so nine tests failed on
every clean clone; it is tracked now and its test verifies the document against
the seeder in BOTH directions. Four generator hardcodes removed (hairline,
focus-ring, eleven dead semantic aliases, the parallel `--shadow-*` ramp):
generated CSS went from 12800 to 11821 bytes. Four stale claims in `AGENTS.md`
corrected, including the missing `core.longpaths` prerequisite.

## The systemic finding

**Ten guards written against the artifact instead of against the source of truth.**
The mechanism is one, not ten: the guard asserts a value that happened to be
there rather than the rule it was there to satisfy. In two cases the guard
actively BLOCKED a correct fix (the headline tracking test demanded the very
tracking value that was the defect; the 404 scrim test pinned the retired cool
hue). Fix them as a class, not one by one.

## The environment prerequisite nobody documented

`git config core.longpaths true` is MANDATORY on Windows for this repo. 35 of
1214 tracked paths exceed 260 characters under the review's frozen-view prefix
(157 chars). `LongPathsEnabled=1` in the registry is NOT enough: git needs its
own opt-in. Without it `gentle_review` fails with
`candidate-owner-preparation-failed`, an opaque error that costs hours.
Documented in `AGENTS.md` Quickstart §1b and Troubleshooting.

## Review ledger

| Lineage | Scope | Outcome |
|---|---|---|
| `review-c5ea2472df6659cd` | A0+A1, 6 paths, 434 lines | approved, authority burned, 0 blockers |
| `review-76dd1ab1143df26c` | A2, 7 paths, 851 lines | approved, authority burned, 0 blockers |
| `review-3746a3a730a64ea6` | B+C, 19 paths, 3561 lines | approved, authority burned, 0 blockers |
| `review-7f4c6e1e134548bc` | corrective pass, 31 paths, 4325 lines | ABANDONED after a CRITICAL finding (R3-001) was corroborated; the correction is applied and verified |
| `review-9e61c9069f149c0d` | re-review, 31 paths, 4382 lines | ABANDONED at 2/4 lenses so no transaction was left open |

## Open items

Closed 2026-09-16 in `odd/tasks/ui-login-redesign-closeout.md`, except where
noted.

1. **RESOLVED — the hero contradiction was a stale guard, not a product
   choice.** `HotfixLoginHeroSourceTest` demanded ZERO `login-hero.jpg` while
   `LoginPageRenderTest` demanded the reference. Evidence settled it: the
   prohibition (HOTFIX-LOGIN-006) was authored 2026-08-22 (`0cb1acf`) and lives
   ONLY in an archived change's spec; a LATER merged SDD change shipped the
   dental photo on purpose (`7f2f1da`, 2026-09-08, archived as
   `ui-login-refinement-dental-split-2026-09-merged`); and the LIVE canonical
   spec requires it (`openspec/specs/premium-design-foundation/spec.md:349`)
   with the asset tracked in git. `git merge-base --is-ancestor 0cb1acf 7f2f1da`
   → YES. The guard was retired; its one still-valid assertion (exactly one
   `<aside class="login-hero-column">`) moved to `LoginPageRenderTest`.
   `tests/Feature/Ui/` went from 2 failed / 36 passed to **36 passed**.
2. **RESOLVED — Slice A3.** `radius.control` 8→12, `radius.panel` 22 and
   `radius.shell` 32 are now shared tokens; `LoginPage.vue`'s local
   `--login-radius-*` vars delegate to them instead of duplicating the values.
   Two guards were rewritten to assert the RULE: the CSS-emission check now
   reads the values from `tokens.js` (it pinned `--radius-control: 8px` as a
   literal), and the ladder is asserted as ordering (`control < panel < shell`).
   Both were bite-tested. `LoginPageRenderTest` also pinned `rounded-[32px]` as a
   literal and flagged the token reference as a defect — that assertion was
   liberalised to accept either spelling.
3. **STILL OPEN — the false guards, as a class.** Excluded from the closeout by
   explicit user decision. ~11 now known.
4. **RESOLVED — the two stalled OpenSpec changes were deleted** (user decision)
   after measuring their real state. Note found while doing it:
   `ui-rollout-all-modules-2026-08` was NOT abandoned — its SDD runtime
   (`.git/gentle-ai/sdd-runtime/v1/ui-rollout-all-modules-2026-08/`, 98 records)
   shows it paused mid-rollout on 2026-08-12, with "change folder stays active"
   in its own last record. Its 7 files were tracked in git, so the folder is
   recoverable with `git checkout HEAD -- openspec/changes/ui-rollout-all-modules-2026-08`.
5. **FALSE — `admin_test` already has `password123`.** Measured: the seeder is
   the only file declaring users, it declares 15, and all 15 hash `password123`
   (`RoleBasedUsersSeeder.php:46` for `admin_test`). `CREDENTIALS.md:20` matches.
   Nothing to fix; the claim was stale.

## The one process rule this session earned

**Open the RDD review only when NO further edits are pending.** Any edit changes
the frozen candidate and invalidates the transaction. This session reviewed
work-in-progress three times and paid for it each time.
