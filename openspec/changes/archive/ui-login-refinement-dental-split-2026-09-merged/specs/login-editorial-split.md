# Spec: Login Editorial Split

Change: `ui-login-refinement-dental-split-2026-09`

## S1 — Outer card shell

- The login page MUST render a single outer card element (`<div class="login-page-shell"><div class="login-split-card">`) that wraps the form and hero columns.
- `login-split-card` MUST have `border-radius: 32px`, `box-shadow: 0 24px 64px rgba(0, 0, 0, 0.08)`, `background: #ffffff`, and a 1px hairline border.
- At viewport ≥ 1024px the split MUST be 45% form / 55% image.
- At viewport < 768px the split MUST collapse to a single column with the hero on top.
- The outer card MUST be inside the existing `bg-canvas` (`#f2f2f7`) page background.

## S2 — Right column hero image

- The right column MUST render a single `<img>` with `src="/images/pexels/auth/login/6812463_modern-dental_p2.jpg"`, `alt="Interior de una clínica dental moderna"`, `loading="lazy"`, `decoding="async"`.
- The image MUST fill the right column via `object-fit: cover`.
- The image wrapper MUST reserve `aspect-ratio: 4/3` (desktop) so the layout never collapses while the image loads.
- At viewport < 768px the image MUST crop to a 4:3 square at the top of the single column.

## S3 — Floating overlay cards

- The right column MUST render exactly 3 floating overlay cards, absolutely positioned, on top of the hero image.
- Each card MUST have `background: rgba(255, 255, 255, 0.85)`, `backdrop-filter: blur(20px) saturate(180%)`, `border-radius: 16px`, hairline border, soft shadow.
- Card 1 (top-right): "Pacientes activos" — fetches `/api/dashboard/stats.total_patients` and shows a large number.
- Card 2 (mid-left): "Citas hoy" — fetches `/api/dashboard/appointments-today?per_page=3` and shows a mini list (patient name + hour).
- Card 3 (bottom-right): "Equipo" — fetches `/api/users/active?per_page=3` and shows initials avatars.
- Each card MUST mount with `v-motion` initial `{ opacity: 0, y: 12 }` enter `{ opacity: 1, y: 0, transition: { delay: 100 + index * 80 } }` so the three cards stagger in.
- If a fetch returns 200 with empty data, the card MUST show a graceful empty state ("Sin datos para mostrar" or equivalent) and MUST NOT throw.

## S4 — Footer row

- Below the form Card, inside the outer card, the page MUST render a footer row with two text links: "Términos y Condiciones" (left) and "¿No tienes cuenta? Contacta al administrador" (right).
- Footer links MUST be `text-secondaryLabel` (`#3c3c43`), 13px, with a hover state that shifts to `text-label` (`#000000`).
- The "Términos" link MUST open in a new tab (`target="_blank" rel="noopener"`) and point to `/terminos` (a stub route returning a 200 placeholder page or `/#` is acceptable for v1).
- The "Contacta al administrador" link MUST be a `mailto:admin@odontosuite.local`.

## S5 — Mobile collapse

- At viewport < 768px the outer card MUST be edge-to-edge (`border-radius: 0`, no shadow, no hairline).
- The right column hero MUST occupy 40% of the viewport height.
- The form column MUST fill the remaining height with the existing `padding: 24px`.

## S6 — Asset gate

- The page MUST NOT hard-fail if the hero image is missing. If `naturalWidth === 0` after load, the hero wrapper MUST swap to a static SVG placeholder that preserves the layout aspect ratio.

## Acceptance criteria

- AC1: At 1440x900 the outer card is visible with the split 45/55.
- AC2: At 390x844 the outer card is full-width, hero is on top, form is below.
- AC3: All 3 overlay cards render with real data (or empty state) on a fully seeded environment.
- AC4: The footer row shows both links with the right hover states.
- AC5: Removing the image asset locally keeps the layout intact.
