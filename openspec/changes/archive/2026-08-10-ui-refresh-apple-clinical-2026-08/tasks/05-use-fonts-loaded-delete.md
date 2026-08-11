# Task 05: use-fonts-loaded-delete

**Phase**: PR1
**LOC estimate**: 0 LOC (file deletion; previously ~40 LOC)
**Spec scenario ref**: `Newsreader absence`
**Design decision ref**: Decision 6 (typography: no FOUT mitigation needed)

## Description

Delete `resources/js/composables/useFontsLoaded.js`. The composable was Newsreader FOUT mitigation; with the system font there is no FOUT risk, so no replacement ships. Grep audit confirms zero consumers.

## Acceptance criteria

- `git ls-files resources/js/composables/useFontsLoaded.js` exits non-zero (file is untracked).
- `grep -rn "useFontsLoaded" resources/` returns 0 rows.
- `vendor/bin/phpunit --filter tokens_module_no_newsreader_no_use_fonts_loaded` exits 0.
- `pnpm build` exits 0 (no broken import).

## Files touched

- `resources/js/composables/useFontsLoaded.js`: delete (~-40 LOC).
