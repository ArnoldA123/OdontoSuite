# Task 08: app-layout-token-swap

**Phase**: PR1
**LOC estimate**: ~30
**Spec scenario ref**: `Reduced transparency solidifies chrome`
**Design decision ref**: Decision 5 (Liquid-Glass chrome)

## Description

Edit `resources/js/components/layout/AppLayout.vue`: page background `bg-cream-50` -> `bg-systemBackground`; nav text `text-ink-700` -> `text-label`; WS indicator chips `bg-success-100 text-success-700` -> `bg-systemGray-100 text-systemGray-600`. The `.surface-glass` class consumption stays (CSS handles the rgba swap from task 03). Edit `PageHeader.vue` and `FloatingActionButton.vue` for token swap only.

## Acceptance criteria

- `pnpm build` exits 0.
- `grep -n "bg-cream-50\|bg-cream-100\|text-ink-700" resources/js/components/layout/AppLayout.vue` returns 0 rows.
- `grep -n "bg-success-100\|text-success-700" resources/js/components/layout/AppLayout.vue` returns 0 rows on the WS indicator.
- Visual: manual render of `/dashboard` (after `adm1n`/`password123` login) shows white sidebar + topbar instead of cream.

## Files touched

- `resources/js/components/layout/AppLayout.vue`: modify (~+8 / -6).
- `resources/js/components/layout/PageHeader.vue`: modify (~+2 / -2).
- `resources/js/components/layout/FloatingActionButton.vue`: modify (~+1 / -1).
