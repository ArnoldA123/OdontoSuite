# Explore: Login Refinement — Dental Editorial Split

Change: `ui-login-refinement-dental-split-2026-09`
Phase: explore
Date: 2026-09-08
Artifact store: hybrid (this file + Engram)

---

## 1. Visual reference

User-provided screenshot: `Captura de pantalla 2026-09-08 172448.png` ("Crexfio" signup).

Reference composition:
- Outer card (radius ~32px, soft shadow, hairline) wrapping the entire form+image split.
- 45/55 split: form on the left, image on the right.
- Headline set in a serif (Newsreader-class), center-aligned optical sizing.
- Inputs use generous radius, no visible label above (placeholder + label-on-focus).
- Primary button is solid color (yellow in source, white-palette for us).
- OAuth row (Apple + Google) sits below primary.
- Footer line: "Have any account? Sign in" + "Terms & Conditions".
- Right column is a single photograph with 2 floating UI cards (Task Review, Daily Meeting) and a 7-day calendar strip. Avatars cluster.
- Background outside the card: a neutral light gray.
- Top-right corner: a small "X" close affordance (modal context — does NOT apply to our login page).

## 2. Current state

### 2.1 Login page (`resources/js/modules/auth/LoginPage.vue`, ~1166 lines)

After PR2 of `ui-login-premium-motion-2026-08` the login carries:
- Editorial split grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-5/7`) — split is already wired, but the right column is a translucent glass form, not a hero image.
- Brand wordmark + serif headline + Newsreader display class.
- 6 microinteractions: magnetic submit, live validation, glassmorphism, brand-glyph tooth→check morph, multi-stage loading (Validando/Autenticando/Listo), failure shake.
- Form Card uses `variant="elevated"` + `.decorative-glass` on a single column.
- Footer: only "¿Olvidaste tu contraseña?" link.

### 2.2 Assets (`public/images/pexels/auth/login/`)

Available dental Pexels stills:

| File | Size | Dimensions | Subject |
|---|---|---|---|
| `305567_modern-dental_p1.jpg` | 32 KB | 3888×2592 | Modern dental clinic interior |
| `6812463_modern-dental_p2.jpg` | 56 KB | 5473×3654 | Modern dental clinic interior |
| `33881127_modern-dental_p3.jpg` | 33 KB | 2268×4032 | Modern dental clinic interior (portrait) |

`6812463_modern-dental_p2.jpg` chosen for the hero (landscape, 56KB, sub-100KB comfort zone).

### 2.3 API endpoints for live overlays

- `GET /api/dashboard/stats` — totals (appointments_today, total_patients, etc.)
- `GET /api/dashboard/appointments-today` — today's appointment list (paginated, but head 3 useful)
- `GET /api/users/active` — professionals list
- `GET /api/branches/active` — branch names

These already return 200 with seeded data (`elizabet` / `password123` lands in the dashboard with non-empty responses).

### 2.4 Tokens & design system

- `resources/js/design-system/tokens.js`: iOS 13+ system color ramps. `systemBlue-500: #007aff` is the action accent. `background.systemBackground: #ffffff`, `background.canvas: #f2f2f7`.
- `radius.modal = 14px`, `radius.cardLg = 16px`. We will use `rounded-[32px]` as an ad-hoc utility for the outer card (no new token).
- `shadow.modal` available; we can compose `0 24px 64px rgba(0,0,0,0.08)` ad-hoc for the outer card.
- No changes to tokens. Discipline.

### 2.5 Motion primitives

- `useSpring`, `useSpring2D`, `useMagneticHover`, `useFieldValidation` — all live and tested.
- `@vueuse/motion` v3.0.3 installed and `MotionPlugin` registered in `app.js`.
- `v-motion` directive available for declarative variants.
- `decorative-glass` class available in `resources/css/tokens.generated.css`.

### 2.6 Submit button current shape

Currently the submit is a single `<UiButton variant="primary" size="lg">` with `<span v-if="!loading">Iniciar sesión</span>`. The 3-stage loading text lives OUTSIDE the form Card in a separate `<div class="login-loading-block">` rendered under it. The button itself does not transform — the form Card content swaps.

## 3. Reusable primitives

| Need | Existing primitive | Status |
|---|---|---|
| Card outer shell with split grid | `<Card variant="elevated">` + scoped CSS grid | Need wrapper div for the larger outer card |
| Hero image | `<img loading="lazy" decoding="async">` | Available, no changes needed |
| Overlay cards on hero | `<Card variant="glass" compact>` x N | Need new small component or scoped CSS |
| Stage crossfade text | `useSpring` + `v-motion` | Available |
| Submit polymorphic states | None — must build | NEW `useShapeMorph` composable |
| Shape state machine | None | NEW `shapeMorphMath.js` |
| Real-data fetch on login page | `useApi.get(...)` | Available |

## 4. Scope

### IN SCOPE

| # | Feature | Where |
|---|---|---|
| 1 | Outer card (radius 32px) wrapping the existing split | `LoginPage.vue` template + scoped CSS |
| 2 | Right-column hero image (dental Pexels still) | `LoginPage.vue` template + scoped CSS |
| 3 | 3 floating overlay cards on hero with real seeder data | New `HeroOverlay.vue` component (or scoped template) + 3 fetches |
| 4 | Footer row: "Términos y Condiciones" + "¿No tienes cuenta?" link | `LoginPage.vue` template + scoped CSS |
| 5 | Submit button polymorphic states (idle / validating / authenticating / success / error) | New `useShapeMorph` composable + LoginPage submit |
| 6 | Form Card polymorphism (form ↔ mini-summary on success) | `useShapeMorph` reused, new `<MiniSummary>` slot |
| 7 | Reduced-motion + reduced-transparency collapse for every new motion path | Scoped CSS in `LoginPage.vue` |
| 8 | Bundle-neutral: zero new dependencies | — |

### OUT OF SCOPE

- New tokens (palette / radius / shadow).
- New primitives in `components/ui/`.
- Modifying `Button.vue`, `Card.vue`, `Sheet.vue`, `Modal.vue`.
- OAuth (OdontoSuite does not have OAuth providers).
- Sound, haptics, parallax, confetti.
- Dark mode (project is light-only by decision).
- Server-side changes (zero new endpoints; the existing `/api/dashboard/*` and `/api/users/active` and `/api/branches/active` already exist).

## 5. Affected files

### Novel
- `resources/js/composables/useShapeMorph.js` — Vue wrapper. State machine driver for polymorphic component transitions.
- `resources/js/composables/shapeMorphMath.js` — pure functions. State validation, intensity clamp, terminal-state guard.

### Modified
- `resources/js/modules/auth/LoginPage.vue` — outer card, hero image, overlays, footer, polymorphic submit, polymorphic form card. ~+350 lines net.
- `resources/css/tokens.generated.css` — REGENERATED (no semantic changes; only regeneration to confirm parity).

### Test files (additive)
- `tests/Unit/Composables/ShapeMorphTest.php` — new. 6 pure-math cases.
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — extended. 6 new source-inspection assertions.

### No changes
- `resources/js/design-system/tokens.js` — untouched.
- `tailwind.config.js` — untouched.
- `package.json` — no new deps.
- Backend — untouched.

## 6. Test gaps

### Backend
None — no API changes.

### Frontend (PHPUnit source-inspection)
1. `login_page_uses_shape_morph_composable` — LoginPage imports `useShapeMorph`.
2. `login_page_submit_button_has_four_polymorphic_states` — submit template declares the 4 stage labels (`Iniciar sesión`, `Validando`, `Autenticando`, `Listo`).
3. `login_page_form_card_has_form_and_summary_states` — LoginPage carries both a `<form>` and a `<MiniSummary>` (or equivalent) branch.
4. `login_page_no_hand_written_hex_literals` — re-run existing grep guard.
5. `login_page_prefers_reduced_motion_collapses_polymorphism` — at least one `@media (prefers-reduced-motion: reduce)` block covering the polymorphic state machine.
6. `login_page_image_uses_committed_dental_pexels_asset` — the hero `<img src="...">` references one of the committed `6812463_modern-dental_p2.jpg` files (not the Pexels directory directly).

### Frontend (math / unit)
1. `ShapeMorphTest::intensity_clamp_returns_zero_when_zero_intent`
2. `ShapeMorphTest::intensity_clamp_caps_at_one`
3. `ShapeMorphTest::state_transition_validates_terminal`
4. `ShapeMorphTest::cancelled_transition_returns_to_idle`
5. `ShapeMorphTest::parallel_states_resolve_to_terminal`
6. `ShapeMorphTest::timing_within_50ms_tolerance`

### Frontend (Playwright / manual)
1. Outer card visible at 1440x900 with split 45/55, image right.
2. At 390x844 (iPhone 12) the split collapses to single column with hero on top (cropped square).
3. 3 overlay cards on hero with real data: stats card, "today's appointments" card, "professionals" card.
4. Submit cycle: idle → validating (200ms) → authenticating (on API resolve) → success (with check + "Listo") → router.push to /dashboard.
5. Failure path: shake + revert to idle with `error` state.
6. Form Card on success: crossfade to mini-summary (avatar + name + role + "Ir al dashboard" button).
7. `prefers-reduced-motion: reduce`: every polymorphic transition collapses to opacity-only.
8. `prefers-reduced-transparency: reduce`: outer card flattens to opaque surface.

## 7. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Image at /images/pexels/auth/login/6812463_modern-dental_p2.jpg is not committed (Pexels INDEX says it is, but git ls-files may differ) | High | `git ls-files` gate in the test. Fallback to `<svg>` placeholder with copy. |
| Overlay API calls fail (no seeder, broken endpoint) → overlays render empty | Medium | Graceful empty-state on each card; never break the login layout. |
| Submit polymorphic states balloon the button width | Low | Fixed `min-width: 220px` for the button; stages use absolute-positioned crossfade. |
| Mini-summary needs the user object; `useAuth().getCurrentUser()` may not be populated on success | Medium | `useAuth` populates after `login()`; mini-summary reads from the login response. |
| 480 lines exceeds the 400-line budget per RDD | High | User explicit exception granted; document as deviation in apply-progress. |
| Right-column image height collapses to 0 on slow image load | Low | `aspect-ratio: 4/3` reserved via `min-height`; image uses `loading="lazy"`. |
| Outer card radius 32px broken on iOS Safari < 16 | Low | `rounded-[32px]` is plain CSS border-radius; no iOS issue. |

## 8. Open questions (resolved)

| Q | Decision | Source |
|---|---|---|
| Direction visual | A — Editorial split con imagen dental | user |
| Imagen hero | Pexels dental moderna | user |
| Overlays | Con datos seeders reales | user |
| Polimorfismo incluido en plan | Sí, según necesidad | user |
| Entrega | Un solo PR grande | user |
| RDD | Habilitado | user (this session) |

## 9. Result

```yaml
status: success
executive_summary: |
  Inventory complete. The current login carries a 2-column grid but the right
  column is a translucent form, not a hero image. Outer card with radius 32px
  and 3 floating UI overlays are missing. Pexels dental stills already
  committed. Existing motion primitives (useSpring, useMagneticHover,
  useFieldValidation, v-motion) cover every new need. The submit button must
  become polymorphic (4 states, 1 piece). The form Card must become
  polymorphic (form ↔ mini-summary). New composable useShapeMorph + pure math
  module + 6 PHPUnit tests + 6 LoginPageRenderTest extensions + Playwright
  manual sweep at 1440x900 + iPhone 12 cover the test gap. Zero new
  dependencies. Zero new tokens. Estimated 480 lines net (exceeds RDD 400-line
  budget; user-approved exception documented).
artifacts:
  - openspec/changes/ui-login-refinement-dental-split-2026-09/explore.md (this file)
  - engram://sdd/ui-login-refinement-dental-split-2026-09/explore (persisted)
next_recommended: sdd-proposal
risks:
  - 480 lines exceeds RDD 400-line budget; user-approved exception.
  - Image asset must be committed; Pexels INDEX is not authoritative.
  - Overlays depend on seeders; if seeders not run, overlays fall back to empty state.
skill_resolution: paths-injected
```
