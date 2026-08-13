# Archive Report — PACIENTES Rollout (2026-08-12)

**Status**: ARCHIVED
**Change**: ui-rollout-all-modules-2026-08 (PACIENTES category slice)
**Archived**: 2026-08-12
**Verify**: PASS WITH WARNINGS (verify report)

## Deliverables
- 5 sub-PRs settled (PR-pacientes-01, 02, 03, 04, 05)
- 2 Vue files polished (PatientsPage.vue 1249 lines, PatientDetailPage.vue 1480 lines)
- 3 inlined modals polished (New + Edit in PatientsPage + Edit in PatientDetailPage)
- 5 PHPUnit test files added (~1,800 LOC tests / 58 tests / 197 assertions on pacientes surface)
- 1 hotfix landed (commit 82cfd8c) caught by verify phase: extra </div> at PatientDetailPage.vue:261 introduced by PR-pacientes-03, removed

PAC-LIST-001: list on Apple language
PAC-MOD-001: inlined modals on Apple language
PAC-DET-001: 5-tab drawer + header polished
PAC-EDIT-001: detail Edit modal polished
PAC-EXP-001: Export action surface + binary download pattern preserved
PAC-RT-001: useEcho 5 channels preserved
PAC-PHI-001: API envelope unchanged
PAC-DEEP-001: 4 cross-category deep-links preserved
PAC-REV-001: per-PR budget isolation
PAC-CON-001: existing contract preservation

## Known follow-ups (out-of-scope)
- PatientSelector primitive tokenization (cross-cutting, rides global PR)
- Pagination primitive consolidation (cross-cutting, rides global PR3)
- PDF export template (print artifact, separate slice)
- Dormant fillable cleanup (dni / blood_type / insurance_provider / insurance_number)
- Patient::restore() API route (policy method exists, no REST route)
- Clinical attachment upload UI
- Allergy alert UX (free-text contract)

## Change folder status
`openspec/changes/ui-rollout-all-modules-2026-08/` remains active. Categories still to explore: tratamientos, inventario, business-intelligence, settings (remaining), reception, my-proc, catalog, and others per the proposal's 17-module enumeration. 3 categories closed: PAGOS, CITAS, PACIENTES.
