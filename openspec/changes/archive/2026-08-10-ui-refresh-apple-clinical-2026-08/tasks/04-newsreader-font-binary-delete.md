# Task 04: newsreader-font-binary-delete

**Phase**: PR1
**LOC estimate**: 0 LOC (binary asset removal)
**Spec scenario ref**: `Newsreader absence`
**Design decision ref**: Decision 6 (typography: system font, no Newsreader)

## Description

Delete the self-hosted Newsreader font binary `public/fonts/newsreader-latin.woff2`. No replacement ships; the system font has zero FOUT risk.

## Acceptance criteria

- `git ls-files public/fonts/newsreader-latin.woff2` exits non-zero (file is untracked).
- `vendor/bin/phpunit --filter tokens_module_no_newsreader_no_use_fonts_loaded` exits 0.
- `grep -rn "newsreader" resources/css/tokens.generated.css` returns 0 rows.
- `pnpm build` exits 0 (no broken font reference in build pipeline).

## Files touched

- `public/fonts/newsreader-latin.woff2`: delete (binary; ~38 KB).
