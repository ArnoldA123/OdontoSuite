# Task: Treatment plans interface redesign — dashboard language

Worktree: `~/proyectos/odontosuite-treatment-plans-ui` · Branch:
`feat/treatment-plans-ui-redesign` (from `main` @ b3c168a) · Created: 2026-10-20
Scope decided by the user: full module (4 surfaces) + real summary endpoint.

## Specs

S1 — Reference language. The treatment plans interface adopts the dashboard
surface recipe shipped in `main`: hero band on canvas + hairline + panel radius
+ elevation-2, KPI strip of four cards with tabular numerals, filters card,
list/kanban data surfaces, and the three explicit states (loading skeletons
with `aria-busy`, retryable `role="alert"` error, empty state through the
shared primitive). User string: `conforme dejamos el dashboar`.

S2 — Surface coverage. All four surfaces are redesigned, per the user's answer
`Módulo completo (4 superficies)` — `Página (lista + kanban), modal
crear/editar, detalle del plan y tarjetas/columnas kanban.` Files: page,
`TreatmentPlanModal`, `TreatmentPlanDetail`, `TreatmentPlanCard`,
`KanbanColumn`, `PlanStatusBadge`.

S3 — Real KPIs. Per the user's answer `Endpoint summary real` — `Agregar
GET /api/treatment-plans/summary con conteos reales y costo pendiente`: counts
by status, active, overdue, and pending cost computed over the whole table, not
over the visible page. Additive route declared before the `apiResource` so
`summary` is never bound as a plan id. Covered by a MySQL feature test plus a
401 case.

S4 — Preserved behavior. The redesign keeps every current capability: filters
(patient name/document, status, date range), quick filters, list/kanban toggle,
kanban drag-and-drop through `change-status`, keyboard `N` and `Escape`,
realtime channel `treatment-plans` (`created`/`updated`/`deleted`), pagination,
create/edit modal with phases, items and totals plus inline patient creation,
detail actions, and the role gate
(`administrador,odontologo,implantologo,tecnico_dental`).

S5 — Design guards (repo convention). Every touched `.vue` drops its
`<style scoped>` block (DLR-R-021), references the canvas token (DLR-R-001),
uses no legacy `border-theme` literal (DLR-R-002), consumes
`var(--focus-ring-default)` for focus, uses token ramps instead of raw
purple/amber/emerald classes, and reuses shared `components/ui` primitives. A
new `TreatmentPlansAppShellTest` pins the contract the same way
`ProcedureStatsAppShellTest` does.

S6 — Docs stay true (repo convention). `AGENTS.md` §4 route counts and §12
changelog move with the new route, and `scripts/audit/doc-drift.mjs` stays
green.

## Tasks

| ID  | Spec   | Route     | Task                                                                  | Commit |
| --- | ------ | --------- | --------------------------------------------------------------------- | ------ |
| T1  | S3     | inline    | Add `treatment-plans/summary` (route, service, controller) + MySQL feature test | 0429d29 |
| T1b | S4     | inline    | Fix quick-filter pills: additive `statuses` + `overdue` index filters, `created_by` mapping, MySQL test | 0429d29 |
| T2  | S5     | inline    | Add `TreatmentPlansAppShellTest` contract for the module              | page slice |
| T3  | S1, S2 | writer    | Rebuild page shell: hero band, KPI strip, filters, states, pagination | page slice |
| T4  | S1, S2 | worker    | Redesign card, kanban column and status badge                         | 1b20745 |
| T5  | S1, S2 | worker    | Redesign plan detail surface                                          | ed40932 |
| T6  | S1, S2 | worker    | Redesign create/edit modal (phases, items, totals, inline patient)    | 5beb26f |
| T7  | S6     | inline    | Sync AGENTS.md §4/§12 and confirm doc-drift green                     | page slice |
| T8  | S1–S6  | inline    | Full verification pass (pint, eslint, prettier, vitest, build, MySQL suite) | page slice |

### Delivery slices and review workload

Commit per surface, because every file is a full rewrite and splitting a
rewritten file mid-way would produce incoherent commits:

| Slice | Content | Authored changed lines |
| --- | --- | --- |
| 1 | backend (`0429d29`) | ~357 |
| 2 | card + kanban + badge + smoke (`1b20745`) | ~1272 |
| 3 | detail + smoke (`ed40932`) | ~930 |
| 4 | modal + inline patient + smoke (`5beb26f`) | ~1466 |
| 5 | page + composable + page smoke + contract + AGENTS | ~1520 |

The whole feature is well past the ~400-line slice target. The honest options
for delivery are a chained-PR split by surface (backend | card+kanban | detail |
modal | page) or reviewing one surface per commit on the single branch. Push and
PR creation stay a user decision.

## Log

L1 (user request, verbatim): `continuando con este proyecto conforme dejamos el
dashboar quiero hacer ahora la interfaz de Planes de tratamiento, para hacer
ello en un worktree nuevo`

L2 (user decision — surfaces): `Módulo completo (4 superficies)` —
`Página (lista + kanban), modal crear/editar, detalle del plan y
tarjetas/columnas kanban.`

L3 (user decision — KPIs): `Endpoint summary real` — `Agregar
GET /api/treatment-plans/summary con conteos reales y costo pendiente.`

L4 (evidence): no other module imports `treatment-plans/components/*` (grep over
`resources/js`), so the component surface is not a contract others consume.
Role gate lives at `routes/api.php:231`. New route must precede
`Route::apiResource('treatment-plans', ...)` to avoid `summary` binding as a
plan id.

L6 (incident): the MySQL test database `odontosuite_test` was left with a
registered migration set that no longer matched its schema (`treatment_plans`
lost `deleted_at`; `appointment_types` had columns applied without a migration
row), so the module suite started returning 500. Mine: the earlier green run in
this same session proves the drift appeared mid-session, while the workers were
running checks. Root cause not attributable from evidence. The `odontosuite`
user cannot create databases, so the options were repair-in-place or rebuild;
with the user's authorization the database was rebuilt with
`migrate:fresh --force` (113 migrations). All 27 module tests pass afterwards.

L7 (observation, not fixed): `text-label` and `bg-canvas` are dead utilities in
this theme — `theme.colors` has no top-level `canvas` and `label` has no
`DEFAULT` key, so Tailwind generates nothing and the declaration falls back to
inherited colour. The dashboard recipe already used them, so the module keeps
parity; `var(--color-canvas)` inline is what actually paints the hero band.
Fixing the token map would change every page that already uses the recipe, so
it stays a follow-up outside this feature.

L5 (evidence + decision): the quick-filter pills are non-functional today.
`applyQuickFilter` assigns the pill value to `filters.status`, so `Activos`
requests `status=active`, `Míos` requests `status=mine` and `Vencidos` requests
`status=overdue`; `TreatmentPlanService::getPlans` compares `status` exactly, so
those three presets always return an empty list. The redesigned surface cannot
ship with dead pills. Fix: additive index filters (`statuses` list,
`overdue=1`) plus frontend mapping (`Míos` sends `created_by`). The existing
exact `status` filter and every other caller stay untouched.
