# Task 01: tokens-palette-rename

**Phase**: PR1
**LOC estimate**: ~70
**Spec scenario ref**: `systemBlue hex`, `background + label hex`, `Alias regression guard`, `No cream/terracotta/clinicalTeal literals`
**Design decision ref**: Decision 3 (iOS clinical palette)

## Description

Rewrite the color ramp in `resources/js/design-system/tokens.js`. Replace the previous `terracotta`/`cream`/`ink`/`clinicalTeal`/`info` ramps with iOS 13+ system colors. Add the iOS background, label, separator, and fill ramps. Preserve the deprecated alias keys (`cream`, `terracotta`, `clinicalTeal`, `info`) so the 17 un-migrated modules' Tailwind classes keep resolving without churn.

## Acceptance criteria

- `vendor/bin/phpunit --filter tokens_module_exposes_ios_system_color_ramps` exits 0 (asserts each ramp shape).
- `vendor/bin/phpunit --filter tokens_module_hex_literals_match_ios_palette` exits 0 (asserts exact hex values).
- `vendor/bin/phpunit --filter tokens_module_deprecated_aliases_resolve` exits 0 (alias regression guard).
- `vendor/bin/phpunit --filter tokens_module_no_cream_terracotta_clinical_teal_literals` exits 0 (forbidden hex absence outside SoT).
- `pnpm tokens:build` exits 0 and regenerates `tokens.generated.css` without error.
- `grep -rEn "#FAF9F7|#F2EFE9|#E8E3D8|#C96442|#B05432|#2C7A7B" resources/ | grep -v "tokens.js\|tokens.generated.css"` returns 0 rows.

## Files touched

- `resources/js/design-system/tokens.js`: modify (palette rewrite; ~+220 / -160 LOC).
