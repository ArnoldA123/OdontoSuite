# Task 09: tokens-module-test-extensions

**Phase**: PR1
**LOC estimate**: ~50 (PHP test additions)
**Spec scenario ref**: All PR1 scenarios
**Design decision ref**: All PR1 decisions (testing strategy)

## Description

Extend `tests/Unit/DesignSystem/TokensModuleTest.php` with 11 new test methods (1.1.1 - 1.1.11). Each method asserts one specific contract from the design (iOS ramps, hex literals, radius, font family, letter spacing, Newsreader absence, useFontsLoaded absence, cream/terracotta/clinicalTeal literal absence, dark block absence, deprecated aliases, font-face/font-serif absence in generated CSS, surface-glass rgba + pure-black shadow).

## Acceptance criteria

- `vendor/bin/phpunit tests/Unit/DesignSystem/TokensModuleTest.php` exits 0.
- All 11 new test methods present and individually runnable via `--filter`.
- No test method depends on filesystem state beyond `git ls-files` shell-out and `file_get_contents` of `tokens.generated.css`.

## Files touched

- `tests/Unit/DesignSystem/TokensModuleTest.php`: modify (~+90 / -20 LOC).
