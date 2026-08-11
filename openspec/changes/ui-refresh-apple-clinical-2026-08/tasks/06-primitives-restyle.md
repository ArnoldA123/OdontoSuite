# Task 06: primitives-restyle

**Phase**: PR1
**LOC estimate**: ~80 (split across 16 components)
**Spec scenario ref**: `Alias regression guard`, `systemBlue hex`, `Radius literals`
**Design decision ref**: Decision 3 (token architecture), Decision 7 (status chip pattern)

## Description

Revalue class names on all 16 UI primitives to use the new iOS system token names. Preserve every prop surface (`variant`, `size`, `loading`, `disabled`, `as`, `to`); only the rendered CSS classes change. Apply radius `rounded-ios` (10 px) to cards, buttons, inputs, status chips; `rounded-modal` (14 px) to Modal and Sheet. Focus rings revalue to `systemBlue`. Badge `variant="info"` re-keyed to `systemBlue` (filled iOS pattern). StatusPill uses `bg-system{Color}-100 text-system{Color}-600` filled pattern.

## Acceptance criteria

- `pnpm build` exits 0.
- `grep -rn "bg-terracotta\|text-terracotta\|border-terracotta\|ring-terracotta" resources/js/components/ui/` returns 0 rows.
- `grep -rn "rounded-lg\|rounded-2xl\|rounded-3xl" resources/js/components/ui/` returns 0 rows (primitives use `rounded-ios` / `rounded-modal`).
- `grep -rn "bg-cream-100\|bg-cream-200\|bg-ink-200\|bg-ink-700" resources/js/components/ui/` returns 0 rows.
- All 16 primitive components pass the prop-surface contract unchanged (no test regressions in any consumer).

## Files touched

- `resources/js/components/ui/Button.vue`: modify (~+3 / -3).
- `resources/js/components/ui/Card.vue`: modify (~+6 / -4).
- `resources/js/components/ui/Modal.vue`: modify (~+3 / -2).
- `resources/js/components/ui/Sheet.vue`: modify (~+3 / -2).
- `resources/js/components/ui/Input.vue`: modify (~+4 / -3).
- `resources/js/components/ui/Badge.vue`: modify (~+4 / -3).
- `resources/js/components/ui/StatusPill.vue`: modify (~+3 / -2).
- `resources/js/components/ui/Toast.vue`: modify (~+3 / -2).
- `resources/js/components/ui/Skeleton.vue`: modify (~+1 / -1).
- `resources/js/components/ui/LoadingSpinner.vue`: modify (~+1 / -1).
- `resources/js/components/ui/EmptyState.vue`: modify (~+1 / -1).
- `resources/js/components/ui/Avatar.vue`: modify (~+1 / -1).
- `resources/js/components/ui/Breadcrumbs.vue`: modify (~+1 / -1).
- `resources/js/components/ui/Tabs.vue`: modify (~+2 / -2).
- `resources/js/components/ui/ConfirmDialog.vue`: modify (~+1 / -1).
- `resources/js/components/ui/NotificationToast.vue`: modify (~+1 / -1).
