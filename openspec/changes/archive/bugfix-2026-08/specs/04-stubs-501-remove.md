# Delta for Stub-501 Removals — Slice 04

Removes orphan stub-501 controllers and routes that have no UI consumer and no active workflow. Includes `WaitingListController` (full removal) plus six other low-priority stubs triaged during apply. Each removal is a route+controller delete (≤30 LOC per file), rollback-safe via `git revert`.

## ADDED Requirements

### Requirement: Removal Triage Documented

The system MUST remove the following stub-501 controllers/routes after a `grep -r` confirms zero references in `resources/js/` AND `tests/Feature/Api/`:
- `WaitingListController` (`/waiting-lists`)
- 6 additional low-priority stubs triaged in apply (each ≤50 LOC, no UI consumer)

A removal manifest MUST be checked in at `openspec/changes/bugfix-2026-08/findings-map.md` under the "stubs-501-removed" subsection.

Evidence: `WaitingListController` returns 501 on every verb; no module under `resources/js/modules/*` calls `/waiting-lists`.

#### Scenario: pre-removal grep clean

- WHEN `grep -r "waiting-lists" resources/js/` runs
- THEN exit code is 1 (no matches)

#### Scenario: route not registered

- WHEN `php artisan route:list --path=waiting-lists` runs
- THEN no rows appear

Test obligation: PHPUnit Feature (no regression) + grep gate in CI script.

---

## MODIFIED Requirements

### Requirement: routes/api.php Has No waiting-lists Registration

The line `Route::apiResource('waiting-lists', WaitingListController::class);` MUST be removed. The controller file `app/Http/Controllers/Api/WaitingListController.php` MUST be deleted.

(Previously: registered but unreachable from the UI; 501 on every call.)

#### Scenario: route file clean

- WHEN `routes/api.php` is read
- THEN no `waiting-lists` line exists

---

### Requirement: Six Low-Priority Stubs Removed

The six additional stubs identified in apply MUST be removed end-to-end (controller file + route declaration + any unused import). Each removal MUST be a separate commit so per-stub rollback stays surgical.

(Previously: each stub returned 501 on every verb; no consumer.)

#### Scenario: per-stub commit

- WHEN `git log --oneline --grep "stub-501-remove"` runs
- THEN six commits appear, each scoped to one controller

---

## REMOVED Requirements

### Requirement: WaitingListController CRUD Endpoints

(Reason: no UI consumer; no active workflow; 501 on every call. Triaged during apply.)
(Migration: if a future feature needs the waiting list, re-add via new change and new spec.)

---

### Requirement: Stub-501 Routes for Removed Low-Priority Controllers

(Reason: each returns 501 on every verb; no UI consumer confirmed via grep.)
(Migration: rollback-safe via `git revert`; consumers can be re-added in a future change.)

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Removal Triage Documented | Feature + grep gate | `tests/Feature/Api/WaitingListRemovedTest.php` |
| routes/api.php Has No waiting-lists | Snapshot | route list golden |
| Six Low-Priority Stubs Removed | Per-stub commit | git history + smoke |
| WaitingListController CRUD | Feature | removal test (assert 404) |
| Stub-501 Routes Removed | Feature | per-stub 404 assertions |
