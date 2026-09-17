# Spec: login-multi-stage-loading

## Summary
On submit, the form content is replaced with a 3-stage progress indicator (`Validando` → `Autenticando` → `Listo`) with skeleton placeholders matching the final form's headline shape. On failure, the form reappears with a shake. On success, the form does not reappear — the morph (`login-brand-glyph-morph`) takes over.

## Source-of-truth references
- The existing `.login-form` skeleton structure (headline + 2 fields + submit)
- `useSpring` (existing primitive) for the per-stage crossfade

## Scenarios

### L1 — Three-stage indicator renders on submit
**Given** the user has submitted the form,
**When** the `loading` state is `true`,
**Then** the form content MUST be replaced by:
- A skeleton placeholder for the form title (h-7 width-3/4).
- A 3-stage indicator with three pill labels: `Validando`, `Autenticando`, `Listo`.
**And** the currently active stage MUST have a brighter background + check icon; the other two MUST be dimmed.

### L2 — Stages crossfade in ~150ms each
**Given** the indicator is showing stage 1,
**When** 150ms have elapsed,
**Then** the active stage MUST advance to stage 2 with a transform + opacity crossfade (duration `var(--motion-duration-fast)` = 120ms, easing `var(--motion-easing-ios)`).

### L3 — No layout shift between form and skeleton
**Given** the form is replaced by the skeleton + indicator,
**Then** the total vertical height of the loading block MUST match the form's vertical height to within ±4px (no layout shift).

### L4 — Failure shake + revert
**Given** the API call throws (auth failure),
**When** the error is caught,
**Then** the form MUST reappear with a 220ms shake animation:
  - `translateX(0 → -6px → 6px → -4px → 4px → 0)` over 220ms (3 oscillations).
**And** under `prefers-reduced-motion: reduce`, the shake MUST collapse to a 200ms opacity flash (`opacity 1 → 0.6 → 1`).

### L5 — Success path does not re-render the form
**Given** the API call resolves successfully,
**Then** the form MUST NOT re-render — the brand-glyph morph (`login-brand-glyph-morph`) takes over and then `router.push('/dashboard')` fires.

## Acceptance criteria
- AC1: The template's loading branch renders all three stage labels (`Validando`, `Autenticando`, `Listo`).
- AC2: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_loading_has_multi_stage_indicator` (source-grep for the three labels).
- AC3: The shake keyframe is declared with the 6-stop keyframe pattern (`0% → -6px → 6px → -4px → 4px → 0`); the reduced-motion block replaces it with the opacity flash.
- AC4: Source contains NO hand-written hex literals added.
