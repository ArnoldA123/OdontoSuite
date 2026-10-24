# Login UI Refresh — Feature Document (CERRADO)

- **Feature**: `login-ui-refresh`
- **Branch**: `feat/login-ui-refresh` (from `main` @ `d99153d`, 2026-10-02/03)
- **Locator**: `odd/tasks/login-ui-refresh.md`
- **Mirror topic**: `odd/login-ui-refresh/tasks`

## Objetivo ✅ cumplido

Eliminar el ruido visual del login ("aspecto sucio"), reemplazar la foto stock por un fondo dental en parallax full-bleed con form flotante (tono clínico claro), y cerrar los defectos de a11y, feedback, flujo de reset, validación y performance.

## Decisiones aceptadas (usuario, 2026-10-02)

- **Composición: full-bleed parallax + form flotante**. Sin foto stock: 3 capas SVG de dientes con parallax por puntero (4/8/12px, spring amortiguado, estático bajo reduced-motion).
- **Tono: Clínico claro**. Base clara hacia `accent-50`, acento verde único, sin glassmorphism.
- **Fix sustractivo**: fuera tarjetas demo, hex/rgb literales y doble acento; movimiento reducido a dos firmas (parallax + morph del botón).

## Tareas — todas completadas

| ID | Work unit | Commit | Verificación |
|----|-----------|--------|--------------|
| T1 | `fix(login)`: toggle accesible, error unificado, roles reales | `774dfe9` | TDD RED 4/4→GREEN 13/13; verificador PASS |
| T2 | `fix(login)`: flujo forgot→reset + deep-link + código de recuperación | `bfeb7bc` | TDD RED 5/6→GREEN 6/6; verificador PASS (2 defects corregidos) |
| T3 | `feat(login)`: simplificación visual (−314/+43) | `e3ef96b` | PHP 38/38, JS 22/38, lint 0; verificador PASS (guard `/api/` restaurado) |
| T4 | `feat(login)`: parallax dental full-bleed + card flotante | `1c797a0` | TDD RED→GREEN 4/4; JS 23/42, PHP 73/180, build ✓; verificador PASS (3 obs. corregidas) |
| T5 | `feat(login)`: min-length + anuncios SR + scroll semantics | `20354eb` | TDD RED 7/8→GREEN 9/9; JS 24/51, PHP 76/222; verificador PASS |
| T6 | `perf(login)`: motion budget + will-change + cleanup naming legacy | `3692045` | JS 26/61, PHP 76/222, build ✓; verificador PASS (1 cosmetic corregido) |

## Revisiones RDD nativas (3/3 aprobadas, autoridad burned)

| Slice | Lineage | Alcance | Resultado |
|-------|---------|---------|-----------|
| R1 (T1+T2) | `review-f23c839ce9898530` | 8 paths / 529 líneas vs `d99153d` | **approved**, 0 hallazgos, burned (`sha256:b1b923…`) |
| R2 (T3+T4) | `review-88d8b7493debf69a` | 5 paths / 1815 líneas vs `bfeb7bc` | **approved**, 3 advisory (R2-001/002/003) — resueltos en T6, burned (`sha256:5a155a…`) |
| R3 (T5+T6) | `review-7a768f8f0330993d` | 10 paths / 945 líneas vs `1c797a0` | **approved**, 0 hallazgos, burned (`sha256:1d0013…`) |
| R4 (slice de PR completo) | `review-e4205fab91e6827a` | 17 paths / 3123 líneas vs `main` (`d99153d`) — target `sha256:072c08b7…` | **approved**, 1 advisory (R1-001 risk WARNING `ResetPasswordModal.vue:423`, informativo), burned (`sha256:99d1b02e…`) |

Cada unidad de trabajo además pasó ASSESS (riesgo `high`, hot-path auth) con plan writer-self-verification + independent verifier ejecutado en las 6 unidades.

## Estado final de verificación

- `pnpm test:js` → 26 files / 61 tests (base: 21/32 — +30 tests nuevos)
- `php artisan test tests/Feature/Ui tests/Unit/DesignSystem/LoginPageRenderTest.php` → 76 / 222 aserciones
- `pnpm lint:check` → 0 errores (1364 warnings prettier preexistentes, deuda #15)
- `pnpm build` → ✓

## Pendientes / deuda conocida

1. **Decisión abierta: `MotionPlugin` + `@vueuse/motion` sin consumidores** (los `v-motion` eran inertes: la directiva solo consume objetos, no funciones — hallazgo medido en T6). Recomendación: retirar plugin + dependencia + retargetear `AppShellTest`. Toca `app.js`/`package.json`; requiere tu visto.
2. ~~Verificación visual en navegador NO ejecutada~~ → **COMPLETADA 2026-10-03** (delegada a verificador independiente con sesión Playwright propia): 8/8 PASS — desktop (elevation-4 token exacto, cero azul, cero glass, cero demo), parallax (transforms escalonados 2.4/4.8/7.2px, will-change solo durante actividad), validación sin llamadas API, error persistente con role=alert, deep-link de reset (email prefilled, token silencioso por diseño), móvil 390×844 (sin overflow, footer alcanzable), reduced-motion estático, happy path `ever`/`password123` → token + `/dashboard`. Capturas en `/tmp/login-verify/`. Observaciones no bloqueantes: (a) tras login fallido los campos muestran checkmarks verdes junto al alert rojo (UX ambigua menor), (b) 404 `/api/dashboard/today` en consola es preexistente y conocido (fuera del login).
3. `public/images/ui/login-hero.jpg` quedó sin referencias (borrado fuera de alcance).
4. Rama supera 400 líneas → entregar como PRs encadenados por slice (PR1=T1+T2, PR2=T3+T4, PR3=T5+T6); excede el presupuesto de review y debe reportarse como excepción o encadenarse.

## Siguiente paso

Verificación visual en navegador → decisión MotionPlugin → push + PRs encadenados (tus decisiones).
