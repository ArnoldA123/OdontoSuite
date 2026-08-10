# Slice 10 — Events Orphans

> Findings: 26 @deprecated events with no listener
> Cluster: events-orphans
> LOC est: ~280 · Budget risk: Medium · Depends on: S07
> Spec: [../specs/10-events-orphans.md](../specs/10-events-orphans.md)

## Per-slice forecast

Decision needed before apply: Yes (list of 26 events to delete vs wire)
Chained PRs recommended: Yes
Chain strategy: stacked-to-main (per-event commits)
400-line budget risk: Medium

## Acceptance Criteria

- Grep each of the 26 `@deprecated` events for real `Event::dispatch(` or `event(` sites.
- Events with no real dispatch → delete the class.
- Events with real dispatch → wire a listener OR remove the dispatch (and the class if reusability unneeded).
- `AppServiceProvider::boot()` lists every active listener (single source).
- New `php artisan sdd:check-events` CI step fails on any non-@deprecated event with no listener and no recent dispatch.
- Listeners MUST catch their own exceptions (no 500 propagation).

## Tasks

- [x] **T-10.1** Grep each of the 22 NF-2-marked events in `app/Events/` for real dispatch sites across `app/` + `resources/js/`. Description: Triage gate. Files: CLI only. AC: produce triage table (delete vs wire). Estimated LOC: 0. Depends on: —. Parallelizable: yes.
  **Result**: 22 NF-2 events; 0 truly orphan (all dispatched); 21 have WS consumers (Reverb); 1 (AppointmentCheckedIn) had no listener + no consumer → fixed in T-10.4. PaymentReceived had listener-less broadcast on public channel → fixed in T-10.3. Matrix saved in engram.
- [x] **T-10.2** Delete each event with no real dispatch (per-event commit). Description: Cleanup. Files: `app/Events/*.php`. AC: per-event commit; grep returns 0 references. Estimated LOC: -300 (varies). Depends on: T-10.1. Parallelizable: yes (per-event).
  **Result**: NONE to delete — all 22 NF-2 events have active dispatch sites (the premise of 26 orphans was empirically false, per feedback corrective in this slice's launch prompt).
- [x] **T-10.3** Wire a useful listener for each orphan-broadcast event. Files: `app/Listeners/LogPaymentReceived.php`, `app/Listeners/LogAppointmentCheckedIn.php`, `app/Providers/AppServiceProvider.php`. AC: per-event commit; sdd:check-events green. Estimated LOC: ~100. Depends on: T-10.2. Parallelizable: yes (per-event).
  **Result**: Added `LogPaymentReceived` (audit trail for MercadoPago/webhook payments) + `LogAppointmentCheckedIn` (audit trail for reception check-in). Both wired in AppServiceProvider.
- [x] **T-10.4** `AppointmentCheckedIn` `ShouldBroadcast` → private channel `private-appointment.{id}`. Description: BF-019 + related findings. Files: `app/Events/AppointmentCheckedIn.php`, `app/Events/PaymentReceived.php`, `routes/channels.php`. AC: feature test confirms broadcast OR listener removed; Reverb smoke green. Estimated LOC: ~30. Depends on: T-10.3. Parallelizable: no.
  **Result**: AppointmentCheckedIn broadcasts on `private-appointment.{id}`; PaymentReceived broadcasts on `private-cash-register.{branchId}`; both channels authorized in `routes/channels.php` with role allowlist.
- [x] **T-10.5** Centralize all `Event::listen(...)` in `AppServiceProvider::boot()` (no hidden listeners in other providers). Description: Audit. Files: `app/Providers/AppServiceProvider.php`, `EventServiceProvider.php` if exists. AC: grep `Event::listen` returns 1 provider. Estimated LOC: ~30. Depends on: T-10.3. Parallelizable: yes.
  **Result**: Verified — single provider (AppServiceProvider::boot) wires all 12 listeners (added 2: LogPaymentReceived + LogAppointmentCheckedIn). No EventServiceProvider exists.
- [x] **T-10.6** Add `php artisan sdd:check-events` command: scans events, flags non-@deprecated events with no listener and no WebSocket consumer. Description: CI gate. Files: `app/Console/Commands/SddCheckEvents.php` (new). AC: command exits 0/1 per state. Estimated LOC: ~130. Depends on: T-10.5. Parallelizable: no.
  **Result**: Command implemented. Reports "33 event classes scanned, 0 orphan" after slice 10 wiring. Supports `--events-path`, `--frontend-path`, `--provider-path` options.
- [x] **T-10.7** Wrap every active listener in `try { ... } catch (\Throwable $e) { report($e); }` per AGENTS.md §7. Description: Resilience. Files: `app/Listeners/*.php`. AC: smoke failing listener logs error, request returns 2xx. Estimated LOC: ~60. Depends on: T-10.5. Parallelizable: yes (per-listener).
  **Result**: Wrapped `CreateTransactionOnAppointmentCompleted` and `NotifyProcedureDeactivation` (the 2 listeners that were missing outer try/catch). All 10 listeners now isolated per AGENTS.md §7.
- [x] **T-10.8** Write RED tests for each retained event + listener pair. Description: Strict TDD. Files: `tests/Unit/Events/*.php`, `tests/Unit/Listeners/*.php`, `tests/Unit/Console/*.php`. AC: dispatch + listener side effect asserted. Estimated LOC: ~120. Depends on: T-10.3..T-10.7. Parallelizable: yes (per-event).
  **Result**: 27 RED tests written, all GREEN. New test files: AppointmentCheckedInPrivateChannelTest, PaymentReceivedChannelTest, LogPaymentReceivedTest, ListenerIsolationTest, SddCheckEventsCommandTest, EventChannelAuthorizationTest; updated OrphanEventsDeprecatedTest.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Event deletion breaks a hidden dispatch site | Grep gate in T-10.1; abort if found |
| Listener crash propagates 500 | try/catch in T-10.7 |
| `ShouldBroadcast` removal breaks Reverb consumer | Verify `resources/js/composables/useEcho.js` consumer list before removing |
| Per-event commits make history noisy | Squash on merge |
