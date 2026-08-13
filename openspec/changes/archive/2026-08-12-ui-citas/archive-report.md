# Archive Report — CITAS Rollout (2026-08-12)

**Status**: ARCHIVED
**Change**: ui-rollout-all-modules-2026-08 (CITAS category slice)
**Archived**: 2026-08-12
**Verify**: PASS WITH WARNINGS (see verify-report.md)

## Deliverables
- 6 sub-PRs settled (PR-citas-01a, 01b, 02, 03, 04, 05)
- 5 Vue files polished (ConsultationWizard, CalendarPage, NewAppointmentModal, AppointmentTypesPage, AppointmentTypeDetailPage)
- 5 PHPUnit test files added (~1,800 LOC tests / 88 tests / 309 assertions on citas surface alone)
- CITAS-CAL-001 status legend expanded from 5 to 7 enum values (added no_show + rescheduled)
- CITAS-TZ-001 timezone contract preserved (caught and fixed a real toISOString() bug in CalendarPage.vue:563)
- CITAS-CONF-001 duplicate-key 422 race-condition handled with friendly error
- CITAS-CON-001 useConsultation contract preserved byte-for-byte
- CITAS-RT-001 Echo appointments channel preserved byte-for-byte
- 1 bug fixed in commit 4585bbf (CalendarPage.vue getInitialDateForModal — local timezone contract)
- a11y follow-up documented at a11y-followup.md

## Spec promotion
11 CITAS-* MUST rows appended to openspec/specs/design-language-rollout/spec.md as the new CITAS Rollout section.

## Archived artifact layout (deviation from the archive brief)
The brief assumed the CITAS spec and tasks lived under `categories/citas/`.
They did not. Actual source locations at archive time:

| Artifact | Source path (in change) | Archived to |
|---|---|---|
| explore.md, proposal.md, design.md, verify-report.md, a11y-followup.md, screenshots/ | `categories/citas/` | `./` (folder root) |
| CITAS delta spec | `specs/citas/spec.md` (change root) | `./specs/citas/spec.md` |
| CITAS task files 07–11 | `tasks/0[7-9]-pr-citas-*.md` and `tasks/1[0-1]-pr-citas-*.md` (change root) | `./tasks/` |

Left in the parent change (not CITAS-only): `apply-progress.md`,
`explore.md`, `proposal.md`, `design.md`, `specs/design-language-rollout/`,
`specs/foundation-primitives/`, `tasks/01-pr0-foundation.md`, and the
remaining categories (pacientes, tratamientos, inventario,
business-intelligence, settings, reception, my-proc, catalog).

`git mv` was used only for the tracked files (`verify-report.md`,
`a11y-followup.md`, and `screenshots/calendar-1440x900-error-overlay.png`).
The remaining artifacts were untracked in git, so a plain `mv`
was used — no rename can be recorded for content git never tracked.
The now-empty `categories/` directory was removed.

## Known follow-ups (out-of-scope)
- Pre-existing RadioGroup.vue syntax bug (predates CITAS rollout; blocks playwright-cli visual sweep)
- CalendarPage grid role=grid + per-cell aria-label (CITAS-A11Y-001 deferred to a11y-followup.md)
- CalendarService textColor: '#ffffff' luminance resolver (deferred to a11y-followup.md)

## Change folder status
`openspec/changes/ui-rollout-all-modules-2026-08/` remains active. Categories still to explore: pacientes, tratamientos, inventario, business-intelligence, settings (remaining), reception, my-proc, catalog, and others per the proposal's 17-module enumeration.

## Mechanical archive verification

The archive was built from a recursive snapshot of `categories/citas/` and
`specs/citas/` taken immediately before any move. After moving files,
two `diff -r` reads returned empty:

- `diff -r /tmp/sdd-archive-citas-snapshot/citas openspec/changes/archive/2026-08-12-ui-citas --exclude=specs --exclude=tasks` → exit 0, no output
- `diff -r /tmp/sdd-archive-citas-snapshot/citas-spec openspec/changes/archive/2026-08-12-ui-citas/specs/citas` → exit 0, no output

The five task files (`tasks/07-…-11-pr-citas-*.md`) were moved with plain
`mv` from an untracked source directory; OS-level `mv` is byte-preserving
by definition and the snapshot/compare pattern was not applicable (source
dir was the entire `tasks/` folder, which still contains the unrelated
`01-pr0-foundation.md`). The snapshot has been retained for review and
removed after verification.
