# Archive Report: ui-login-premium-motion-2026-08 (recovered)

**Status**: ARCHIVED — recovered from an unmerged branch
**Change**: ui-login-premium-motion-2026-08
**Archived**: 2026-09-17
**Verify**: never verified to completion as a change — see "What this archive does not claim"

## Why this archive is unusual

This change folder never reached `main`. Its branch
(`feat/ui-login-premium-motion-2026-08`, PR #11) stayed open from 2026-09-08 and
was closed without merging, so `main` carried the code but none of the record.
Archiving it here is what stops the record from disappearing: the branch was its
only home, and the branch is deleted.

## Disposition of the code

The payload is already in `main`, recovered **byte-identically**. Verified with
`git rev-parse <branch>:<path>` against `git rev-parse origin/main:<path>` — blob
hashes, not eyeballing. Committed to `main` in `016bcf4`.

| Artifact | Relation to `main` |
|---|---|
| `resources/js/composables/useMagneticHover.js` | byte-identical |
| `resources/js/composables/magneticHoverMath.js` | byte-identical |
| `resources/js/composables/useFieldValidation.js` | byte-identical |
| `resources/js/composables/fieldValidationMath.js` | byte-identical |
| `tests/Unit/Composables/AppShellTest.php` | byte-identical |
| `tests/Unit/Composables/FieldValidationTest.php` | byte-identical |
| `tests/Unit/Composables/MagneticHoverTest.php` | byte-identical |
| `package.json` | byte-identical (the `@vueuse/motion` declaration, commit `7991f41`) |
| `resources/js/components/ui/Button.vue` | `main` is newer — the `data-magnetic` hook was re-applied on top |
| `resources/js/modules/auth/LoginPage.vue` | `main` is newer — the microinteractions were re-wired onto the redesign |
| `resources/js/app.js` | `main` is newer |
| `pnpm-lock.yaml` | `main` is newer |

## Why it was not merged

A merge would have carried `LoginPage.vue` and `Button.vue` **from before the
redesign**. The Arena neutrals, the clinical-green accent, the editorial split
and the premium login craft all landed afterwards, and each was independently
reviewed. Merging would have required hand-resolving conflicts on the most
sensitive surface in the app — the authentication page — to avoid regressing a
surface that was verified twice. Closing the PR and preserving the record was the
cheaper honest path.

## What this archive does not claim

- **The 7 delta specs under `specs/` were never synced** into `openspec/specs/`.
  Whether they still describe the intended contract after the redesign is an open
  question, deliberately left unanswered here. The specs describe a login that no
  longer exists in that shape (glassmorphism, the pre-redesign layout).
- **`verify-report.md` and `sync-report.md` describe a codebase that has since
  changed substantially.** They are historical records, not current state.
- **The review cycles that covered this code reviewed it in its recovered form on
  `main`** — not as this change, and not on this branch.
- This archive is a record-keeping action, not evidence that the change passed
  its own verify gate.

## Artifact layout

| Artifact | Detail |
|---|---|
| Source | `feat/ui-login-premium-motion-2026-08` → `openspec/changes/ui-login-premium-motion-2026-08/` |
| Files | 15 (157,089 bytes) |
| Imported via | `git checkout <branch> -- <path>`, then `git mv` into this folder |
| Source folder | removed; it no longer exists as an active change |
| Branch fate | PR #11 closed, branch deleted (local and remote) |

## Next

Nothing here is scheduled. If the 7 delta specs are ever wanted as canonical
contracts, that is a separate decision and its own change.
