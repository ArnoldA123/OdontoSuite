# Proposal: ui-rollout-hotfix-premium-2026-08

**Design authority**: This change treats `apple-design` and `design-taste-frontend` skills as the binding design authorities. Every decision below cites the rule that justifies it. Token compliance (`tokens.js`) is necessary but not sufficient — the previous vertical slice proved that mechanical token swap delivers correct colors without Apple premium feel.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-hotfix-premium-2026-08` |
| Scope | Login + Dashboard only (user-selected) |
| Out of scope | 404 (user-deferred), all other 17 modules, tokens.js, primitives, dark mode |
| Reference | `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/` (vertical slice) |
| Audit evidence | Playwright @ 1440x900, screenshots in `.playwright-cli/login-current.png` and `.playwright-cli/dashboard-real.png` |

## 1. Goal

Close the gap between TOKEN COMPLIANCE and APPLE PREMIUM FEEL on Login + Dashboard so the screens read as the design proving ground the user asked for in the vertical slice.

**Success metric**: After PR-hotfix-login and PR-hotfix-dashboard merge, Playwright screenshots at 1440x900 of /login and /dashboard pass a manual design review against the rules cited below (no AI-Tells from design-taste-frontend §9, every interaction respects apple-design principles).

## 2. Design reads (from skills)

### apple-design (binding for motion, materials, typography, response)

- **Response (§1)**: kill latency. Buttons highlight on pointer-down, not on release. Focus states instant. No debounce on the input path.
- **Direct manipulation (§2)**: irrelevant for these two screens (no drag).
- **Interruptibility (§3)**: every entrance animation must be interruptible. Spring entrance on Login + Dashboard MUST be `useSpring({ damping: 1.0, response: 0.35 })` per apple-design default UI spring table. No CSS transitions for entrance.
- **Behavior over animation (§4)**: springs, not keyframes. `damping: 1.0` (critically damped) for non-momentum entrance. No bounce (`damping: 0.8` is for momentum-driven gestures only).
- **Velocity handoff (§5)**: irrelevant here.
- **Spatial consistency (§7)**: hero column mirrors to the form column with a mirror spring (left form response 0.35s, right hero response 0.45s — slower for the visual element, mirror iOS sheet reveal). Reverse transitions match forward paths.
- **Hint in direction of gesture (§8)**: empty-state CTA animates from text to icon, not the other way.
- **Materials & depth (§12)**: form card lifts via elevation + 1px hairline + soft inset highlight. The hero image uses a gradient overlay that fades content into the canvas, never a hard scrim. Sidebar uses `.surface-glass` (`backdrop-filter: blur(20px) saturate(180%)`) — already present in tokens. Cards on the canvas page use opaque surface (token comment is explicit: glass variant on solid canvas reads flat).
- **Typography (§15)**: SF Pro is the system font. Display headlines tighten tracking to `-0.025em` to `-0.03em` at scale ≥ 30px, never `0`. `font-variant-numeric: tabular-nums` for every number (token exists).
- **Reduced motion + accessibility (§14)**: `prefers-reduced-motion: reduce` collapses springs to instant. `prefers-reduced-transparency: reduce` flattens glass chrome. `prefers-contrast: more` lifts colors. All three media queries already present in LoginPage; add the missing ones in DashboardPage.

### design-taste-frontend (binding for composition, typography hierarchy, AI-Tells)

- **Dial baseline (§1)**: VARIANCE 7, MOTION 6, DENSITY 4 — Apple-premium preset.
- **Brief inference (§0.B)**: "Reading this as: B2B SaaS dashboard for dental clinic operators (recepcionistas + odontólogos), with a premium Apple language, leaning toward iOS 13+ system colors + SF Pro + soft spring motion. Out of scope: agency playfulness."
- **Anti-default discipline (§0.D)**: ban AI-purple gradients (none used), ban three-equal feature cards (Quick Actions 5-col grid needs rhythm), ban generic glassmorphism (only on chrome, not on form card).
- **Hero MUST fit viewport (§4.7)**: Login hero headline ≤ 2 lines desktop, subtext ≤ 20 words, CTA visible without scroll. Current: headline wraps to 2 lines already. Keep.
- **EYEBROW COUNT (§4.7)**: max 1 eyebrow per 3 sections. Sidebar has 2 ("OPERACIONES", "CONFIGURACIÓN") — both banned by §9.F "Section-Numbering Eyebrows". Dashboard 404-style "ERROR 404" pattern: banned.
- **ZIGZAG ALTERNATION CAP (§4.7)**: dashboard has 5 KPI cards in a row — acceptable if they're truly differentiated (variance per §1), not identical.
- **BENTO BACKGROUND DIVERSITY (§4.7)**: KPI cards are bento-shaped; need visual differentiation (color tint, subtle accent dot, or hairline border treatment).
- **CTA BUTTON WRAP (§4.5)**: "Iniciar sesión" + "+ Nuevo Profesional" both fit on one line at desktop.
- **NO DUPLICATE CTA INTENT (§4.5)**: Login has 1 CTA (Iniciar sesión), no Forgot Password CTA collision (it's a link, distinct).
- **ICON LIBRARY (§3.C)**: hand-rolled SVGs in current code — allowed exception for Apple-style 1.75 stroke weight icons (per design-taste §9.E "Hand-rolled decorative SVGs strongly discouraged" — these are functional, not decorative). Document this in code.
- **No fake screenshots (§9.F)**: empty state with line-art SVG icon is fine; div-based fake dashboard preview is not.
- **Premium-consumer palette check (§4.2)**: NOT in this scope (the brand is iOS clinical, not premium consumer). The current blue + systemGray palette is correct.
- **EM-DASH BAN (§9.G)**: zero `—` characters. Audit both screens.
- **PRE-FLIGHT CHECK (§14)**: every checkbox ticked before declaring done.

## 3. PR-hotfix-login (≤ 280 lines)

### 3.1 Brand mark (LoginPage.vue template + scoped CSS)
- **Current**: `<div class="brand-mark">` with `var(--color-terracotta-500)` circle + inverted PNG.
- **Change**: Remove the PNG. Replace with a small SF-style glyph (1.75-stroke "tooth" SVG, 24×24) + the `OdontoSuite` wordmark next to it.
- **Rule**: design-taste §0.D "anti-default discipline" (no favicon-like generic mark); apple-design §15 (wordmark in system font).
- **Test**: pin rule, not example — assert `data-testid="brand-wordmark"` is `OdontoSuite` and the icon SVG has `stroke-width="1.75"`.

### 3.2 Headline + subtitle
- **Current**: `text-3xl sm:text-4xl font-medium leading-[1.1] letter-spacing: -0.022em`.
- **Change**: Bump to `text-[clamp(2.25rem,5vw,3rem)]` with `leading-[1.05]` and `letter-spacing: -0.028em` at the display scale. Subtitle `text-sm` → `text-base` for breathing room.
- **Rule**: apple-design §15 typography (tracking size-specific, never fixed); design-taste §4.7 hero discipline.
- **Test**: assert computed `letter-spacing` of the h1 in the desktop viewport is `-0.028em × font-size`. Pin as rule, not literal — measure the product.

### 3.3 Submit button (LoginPage.vue template)
- **Current**: `<UiButton variant="primary" size="lg">` with no scoped elevation (the existing scoped CSS adds `box-shadow: var(--elevation-3), inset 0 1px 0 rgba(255, 255, 255, 0.3)` on `button[type='submit']`).
- **Change**: Move the elevation + inset highlight + tinted shadow into the `<UiButton>` primitive itself (one place) so all primary buttons everywhere get the premium treatment. Add `:active { transform: translateY(1px); }` and `:disabled` desaturate.
- **Rule**: apple-design §1 (response on pointer-down via `:active` translate), §4 (springs — but for hover/active, the existing `transition` is correct since springs are for entrance, not micro-interaction), §12 (materiality — heavier shadow for bigger CTA).
- **Test**: pin RULE in `tests/Unit/DesignSystem/ButtonConstructionTest.php` — assert that ANY primary button has `box-shadow` containing `var(--elevation-3)` AND `inset 0 1px 0 rgba(255,255,255,X)` where X ≥ 0.25. This catches regressions on every button everywhere.
- **Out of scope for this PR**: rewriting UiButton's other variants. Add the elevation+inset to the primary variant only.

### 3.4 Form card (Card.vue primitive usage)
- **Current**: `<Card variant="glass">` on solid canvas — renders flat, no perceptible translucency.
- **Change**: Switch to `variant="elevated"` (or default if no `elevated` variant yet — add one). Surface = `var(--color-surface-elevated)` (#ffffff), `box-shadow: var(--elevation-2)`, `border: 1px solid var(--color-hairline)`, optional `border-radius: var(--radius-card-lg)` (16px, already on token).
- **Rule**: apple-design §12 (bigger surfaces read thicker — stronger blur AND deeper shadow than chips; but this card is on solid canvas, so glass doesn't apply; use elevation + hairline + soft inset highlight at top edge instead).
- **Test**: assert computed `box-shadow` of the card surface contains a `var(--elevation-2)` reference. Pin rule.

### 3.5 Hero image + caption
- **Current**: `public/images/ui/login-hero.jpg` (stock dental photo) + 180deg gradient overlay + caption.
- **Change**: Replace the stock photo with an editorial SVG composition (generated inline). Options to draft in spec:
  - **A** (recommended): Large typographic "OdontoSuite" wordmark + abstract dental-themed line-art (tooth, chair, calendar) in a 3-tile bento, on a `var(--color-canvas)` (#f2f2f7) background. No stock photo.
  - **B**: Generated image via image-gen tool (if available) with a clinical-but-warm mood.
  - **C**: Abstract gradient SVG (radial gradients of systemBlue/systemGreen) with the wordmark.
- **Rule**: design-taste §4.8 (real images or generated — no stock dental), §9.F ("NO div-based fake product UI in the hero" — bento of small visuals is OK because they're real components, not fake product preview).
- **Test**: assert the hero column does NOT contain `<img>` with `src` ending in `login-hero.jpg`. Pin as rule. Plus visual Playwright check that hero is editorial composition.

### 3.6 Spring entrance (already present)
- **Current**: `useSpring({ response: 0.35, damping: 1.0 })` on form-wrap + `response: 0.2` opacity.
- **Change**: Keep. Add a SECOND spring on the hero column with `response: 0.45, damping: 1.0` — slower mirror.
- **Rule**: apple-design §7 (spatial consistency, mirror easing on reverse transitions; slower right column matches iOS sheet reveal).
- **Test**: assert hero column has `data-spring-attached="true"` (or equivalent) and reduced-motion media query kills it.

### 3.7 Reduced-motion + Reduced-transparency + High-contrast
- **Current**: All three media queries already present.
- **Change**: Keep. Add a Playwright test that toggles each preference and asserts the surface still renders legibly.

### 3.8 EM-DASH audit
- **Current**: None found in LoginPage.vue text content.
- **Change**: No edit needed. Document the audit in apply-progress.md.

## 4. PR-hotfix-dashboard (≤ 380 lines)

### 4.1 Sidebar section labels
- **Current**: "OPERACIONES" and "CONFIGURACIÓN" rendered with `text-xs uppercase tracking-[0.18em]` (banned by §9.F).
- **Change**: Remove the eyebrow text entirely. Replace with a 1px `var(--color-hairline)` horizontal divider between sections. No text label.
- **Rule**: design-taste §9.F "Section-Numbering Eyebrows" (zero uppercase tracking labels), §4.7 "EYEBROW COUNT (mechanical) — max 1 per 3 sections".
- **Test**: assert NO element in AppLayout.vue has `tracking-[0.18em]` (or similar uppercase-tracking class). Pin as rule against the whole sidebar.

### 4.2 KPI cards
- **Current**: 5 cards, each with uppercase label + large number + small caption + icon-in-rounded-gray-box.
- **Change**:
  - Remove the icon-in-rounded-gray-box entirely. Apple would either skip the icon or use a subtle 1px hairline divider at the top + small accent dot (4px circle, `var(--color-system-blue-500)`) top-right.
  - Apply `font-variant-numeric: tabular-nums` (token exists, var `--font-features-tabular-nums`) on the big number.
  - Card surface = `var(--color-surface-elevated)` + `box-shadow: var(--elevation-1)` + 1px `var(--color-hairline)` border + `border-radius: var(--radius-card-lg)`.
  - `:hover { transform: translateY(-1px); box-shadow: var(--elevation-2); transition: transform 200ms var(--motion-easing-ios), box-shadow 200ms var(--motion-easing-ios); }`.
- **Rule**: apple-design §4 (springs for entrance only — hover uses CSS transition), §12 (translucent chrome for nav, opaque for data cards), §15 (tabular nums for numbers); design-taste §9.D (no Material icon-in-box).
- **Test**: assert KPI cards have NO `rounded` `bg-system-gray-*` icon container. Assert computed `font-feature-settings` includes `"tnum" 1`. Assert `:hover` lift is `translateY(-1px)`. Pin rules.

### 4.3 Quick Actions grid
- **Current**: 5 cards in 3-col grid with icon-in-box + title + subtitle + letter-key badge (`P`, `N`, `R`, `E`, `B`).
- **Change**:
  - Remove the letter-key badges (or move to `<kbd>` element in a `title` tooltip — out of scope for visual; just remove).
  - Same surface treatment as KPI cards.
  - Re-arrange to 2-col on tablet, 3-col on desktop — already done via Tailwind.
- **Rule**: design-taste §9.D (no Material icon-in-box); §4.7 (bento rhythm — 5 items in 5 cells, no empty cell).
- **Test**: assert NO Quick Action card has a `<span>` containing a single uppercase letter as a badge. Pin rule.

### 4.4 Greeting block
- **Current**: "Buenas noches, Ever" + "viernes, 21 de agosto de 2026".
- **Change**: Apply `font-variant-numeric: tabular-nums` on the date. Keep greeting typographic (`text-2xl font-medium tracking-tight`).
- **Rule**: apple-design §15 (tabular nums on numbers, dates).
- **Test**: assert the date span computed `font-feature-settings` includes `"tnum" 1`. Pin rule.

### 4.5 Empty state ("Sin citas para hoy")
- **Current**: Phone icon in circle + "Sin citas para hoy" + 1-line caption.
- **Change**:
  - Replace the circle-icon with a clean line-art SVG of a calendar (24×24, `stroke-width: 1.5` per apple-design §16 topbar convention) in `var(--color-label-tertiary-label)`.
  - Add a primary CTA button below: "Crear nueva cita" (`<UiButton variant="primary" size="md">`).
  - Add a subtle radial gradient backdrop (`background: radial-gradient(circle at center, var(--color-system-blue-50) 0%, transparent 70%)`) so the empty state has depth without using real illustration.
- **Rule**: apple-design §12 (translucent chrome — radial gradient as subtle depth), §16 (icon stroke 1.5 for consistency); design-taste §9.F (no div-based fake UI; clean line-art SVG is OK as a placeholder).
- **Test**: assert NO `<div>` fake screenshot divs in the empty state. Assert the CTA button is `<UiButton variant="primary">`. Pin rule.

### 4.6 Per-section staggered springs
- **Current**: Probably none on the dashboard sections (need to verify).
- **Change**: Add a single `useSpring({ response: 0.35, damping: 1.0 })` per visible section (greeting, KPI grid, Quick Actions, Citas de Hoy) with staggered delays (0ms, 60ms, 120ms, 180ms) via `setTimeout`. Reduced-motion media query kills them all.
- **Rule**: apple-design §4 (springs for entrance, critically damped), §8 (intermediate frames telegraph direction).
- **Test**: assert 4 `useSpring` instances are attached to the dashboard sections. Pin rule.

### 4.7 Reduced-motion + High-contrast
- **Current**: Need to verify; add if missing.
- **Change**: Add both media queries at the dashboard level, mirroring LoginPage pattern.

### 4.8 EM-DASH audit
- **Current**: None expected (need to verify in spec phase).
- **Change**: No edit needed if clean; document audit in apply-progress.md.

## 5. Test strategy

### Unit / static (PHP)

- `tests/Unit/DesignSystem/ButtonConstructionTest.php` — pin rule that ANY primary button has `box-shadow` containing `var(--elevation-3)` AND inset highlight.
- `tests/Unit/DesignSystem/CardSurfaceTest.php` (new) — pin rule that `<Card>` opaque variants have `box-shadow: var(--elevation-*)` and 1px hairline.
- `tests/Unit/DesignSystem/SidebarEyebrowAuditTest.php` (new) — pin rule that NO element in `AppLayout.vue` has uppercase-tracking class for section labels.
- `tests/Unit/DesignSystem/IconInBoxAuditTest.php` (new) — pin rule that NO component has `rounded bg-system-gray-*` icon container.

### E2E (Playwright, run manually + captured in verify-report)

- `/login` at 1440x900 + 390x844 — visual diff against design intent.
- `/dashboard` at 1440x900 + 390x844 — same.
- Toggles for `prefers-reduced-motion: reduce`, `prefers-reduced-transparency: reduce`, `prefers-contrast: more` — surface still legible.

## 6. Out of scope (explicit)

- 404 (user-deferred).
- All 17 un-migrated modules + Profesionales + Caja + BI + Calendar (Lote 2+).
- `tokens.js` mutation.
- New primitives beyond `variant="elevated"` on Card (one line).
- Dark mode.
- Sidebar structural changes (icons + labels stay).
- Auth flow, route slugs, form field names.
- Any schema migration.

## 7. Rollback

- `git revert <merge-sha>` per PR. Each PR is independently revertable.
- PR-hotfix-login revert restores the stock photo + flat button. Acceptable.
- PR-hotfix-dashboard revert restores eyebrow labels + icon-in-box. Acceptable.

## 8. Chain strategy

`stacked-to-main` (same as vertical slice + Lote 1). No tracker PR needed. Both PRs under the 400-line budget.

## 9. Success criteria

- Playwright screenshots at 1440x900 of /login and /dashboard pass the manual design review.
- All Unit tests green.
- Zero em-dashes anywhere on the two pages (audit captured).
- No uppercase-tracking eyebrow labels remain.
- No `bg-system-gray-*` icon-in-box containers remain on KPI or Quick Action cards.
- Submit button has visible elevation + inset highlight in the rendered DOM.
- Hero column is NOT the stock dental photo.
- Reduced-motion + reduced-transparency + high-contrast all pass Playwright toggles.

## 10. Open questions — resolved

1. **Hero replacement**: A (typography + bento of small line-art SVGs). B and C are fallbacks if user disagrees in spec review.
2. **Brand mark**: Replace with SF-style tooth glyph (1.75 stroke) + wordmark. Wordmark stays visible.
3. **Sidebar dividers**: 1px `var(--color-hairline)` between sections, no text label.
4. **Empty state CTA**: Inline primary button "Crear nueva cita".
5. **KPI hover**: `translateY(-1px)` + `box-shadow: var(--elevation-2)`.
6. **Staggered springs**: Required for dashboard (4 sections, 60ms stagger). Login gets the existing 2-spring mirror.

## 11. Skills cited

- `apple-design` — §1, §3, §4, §7, §8, §12, §14, §15, §16
- `design-taste-frontend` — §0.D, §4.2, §4.5, §4.7, §4.8, §9.D, §9.E, §9.F, §9.G, §14

## 12. Next steps

- `sdd-spec` writes `spec.md` translating the 12 sections above into Given/When/Then MUST rows with RFC 2119 keywords.
- `sdd-tasks` writes `tasks.md` slicing PR-hotfix-login + PR-hotfix-dashboard into TDD-ordered task lists (RED/GREEN/VERIFY/COMMIT per PR).
- `sdd-apply` writes code via ultracode multi-agent orchestration per the chain.
- `sdd-verify` runs tests + Playwright screenshots + manual design review.

## 13. Files authored this turn

- `openspec/changes/ui-rollout-hotfix-premium-2026-08/explore.md`
- `openspec/changes/ui-rollout-hotfix-premium-2026-08/proposal.md` (this)
