# Delta for Events / Listeners Cleanup — Slice 10

Resolves events-orphans findings: 26 `@deprecated` event classes with no listener must be deleted. New event wiring (if any is introduced) must be registered in `AppServiceProvider` and the listener file must exist.

## ADDED Requirements

### Requirement: Deprecated Events Deleted

The system MUST delete the 26 event classes marked `@deprecated` in `app/Events/` that have zero listeners in `AppServiceProvider` and no direct dispatch sites outside the deletion target.

Evidence: 26 `@deprecated` event classes have no listeners; deletion reduces cognitive load.

#### Scenario: deletion list published

- WHEN `openspec/changes/bugfix-2026-08/findings-map.md` is read
- THEN the `events-orphans` subsection enumerates every event deleted

#### Scenario: post-deletion grep

- WHEN `grep -r "EventClassName" app/ resources/` runs after deletion
- THEN no references remain

Test obligation: Static grep gate + manual review.

---

### Requirement: New Events Have Listeners

Any new event class added in this change MUST have at least one listener class registered in `AppServiceProvider::boot()` or `EventServiceProvider`. The system MUST NOT ship events with zero listeners unless explicitly marked `@deprecated`.

#### Scenario: registered listener

- WHEN an event is dispatched
- THEN the corresponding listener runs

Test obligation: Feature (dispatch + assert listener side effect).

---

### Requirement: Listener Errors Isolated

Listeners MUST catch their own exceptions and MUST NOT propagate failures into the request lifecycle (use `try/catch` or queue isolation). A failing listener MUST NOT cause a 500 to the user.

#### Scenario: listener failure doesn't break response

- WHEN a listener throws
- THEN the originating request still returns its success response
- AND the failure is logged

Test obligation: Feature + log assertion.

---

### Requirement: Event Class Coverage Gate

The system MUST run `php artisan sdd:check-events` as a CI step. The gate MUST fail if any non-`@deprecated` event class has no listener and no direct dispatch in 30 days.

#### Scenario: orphan event caught

- WHEN an event has been orphaned for 30 days
- THEN CI gate fails

Test obligation: CI step.

---

## MODIFIED Requirements

### Requirement: AppServiceProvider Lists All Listeners

The `AppServiceProvider::boot()` MUST include every active `Event::listen(...)` call. Hidden listeners MUST NOT exist.

(Previously: listeners split across multiple providers, some unregistered.)

#### Scenario: provider lists all

- WHEN `AppServiceProvider::boot()` is read
- THEN every active listener has a corresponding `Event::listen` line

Test obligation: Static review + grep.

---

## REMOVED Requirements

### Requirement: 26 Deprecated Event Classes

(Reason: zero listeners; delete per cleanup policy.)
(Migration: any future consumer must re-create the event under a new name in a future change.)

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Deprecated Events Deleted | Grep gate | CI |
| New Events Have Listeners | Feature | per new event |
| Listener Errors Isolated | Feature | per listener |
| Event Class Coverage Gate | CI step | `php artisan sdd:check-events` |
| AppServiceProvider Lists All | Static review | per PR |
