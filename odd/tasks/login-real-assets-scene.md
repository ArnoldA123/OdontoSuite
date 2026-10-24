# Login Real Assets Scene — CERRADO

## Objetivo ✅ cumplido
Reemplazar el arte SVG de dientes (rechazado por el usuario: "simples y feos") por los PNG reales del usuario en la escena parallax del login: `tooth-1.png`, `tooth-2.png` (con destellos), `toothbrush.png` (512x512 RGBA, `public/images/login/`).

## Contexto
El primer intento de rediseño expiró (worker timeout) sin escribir código: los PNGs quedaron copiados pero sin integrar (por eso el usuario "no veía los cambios").

## Tareas — ambas completadas
| # | Tarea | Evidencia |
|---|---|---|
| T1 | feat(login): escena con PNGs reales + tests TDD | commit `c00dccc` (RED 16 tests → GREEN 42/42); 5 archivos, +356/−447 |
| T2 | Verificación: suites + lint + build + pass visual | JS 26 files/96 tests, PHP 583/2725, lint 0, build ✓ (keyframes scoped OK); capturas 1440x900 / 1280x800 / 390x844 revisadas |

## Diseño aplicado
- 15 marcas `<img>` (8 tooth-1, 6 tooth-2, 1 toothbrush) sobre las 3 capas far/mid/near; se conserva parallax por puntero, drift ambiental, reduced-motion estático y reduced-transparency sólido (nunca display:none).
- Rampa de brillo por capa `--mark-brightness` 0.86/0.78/0.68 + opacidad 0.34/0.52/0.62 (los PNG de dientes son claros y necesitan oscurecimiento explícito).
- Cepillo negro armonizado con opacidad 0.42; destellos de tooth-2 conservan tono cálido (saturate + drop-shadow naranja).

## Revisión RDD nativa
R7 (slice c00dccc vs e3f9697): lineage `review-80caaf62b3ccc240`, 5 paths/803 líneas, tier medium, lente review-reliability. **approved**, authority burned (`gentle-ai.review-acknowledged/v1`, rev `efbb954c…`). Candidato = work-unit commit con baseRef explícito (nunca la rama acumulada).

## Pendientes / hallazgos
1. ~~Móvil 390x844~~ → resuelto en T3.
2. ~~`1>nul` Windows-ism~~ → resuelto en T4.
3. Push + PR #113: ejecutado (commits `0b7e459`, `8333b6d` subidos).

## Ampliación posterior (2026-10-03)
- T3 feat(login): small-screen constellation (`8333b6d`): slots `--mark-sm-*` + bloque `@media (max-width: 640px)`; 10 marcas visibles a 390x844, cepillo reconocible, sin colisión con el formulario. TDD RED 6 → GREEN 53/53; verificación independiente PASS (JS 26/103, PHP 583/2725, lint 0, build ✓, capturas móviles revisadas). Revisión RDD R8 (lineage `review-bbc7ae1657141337`, 3 paths/380 líneas): approved, authority burned.
- T4 fix(tests): redirect portable `2>&1` en TokensModuleTest (`0b7e459`): elimina el archivo `nul` basura en Linux. 32/32 tests.
- **Disposición del usuario extendida**: los targets agregados de la rama (unión de todo vs merge-base, identidad cambia con cada commit) quedan EXPLÍCITAMENTE sin revisar de forma permanente para `feat/login-ui-refresh`; solo se revisan los slices por work-unit con baseRef explícito. Registrado en memoria (obs 165).

## Aprendizajes clave
- ASSESS/inspect vuelven a ofrecer la rama acumulada (base = merge-base con main): pasar `baseRef` explícito al commit previo en START es obligatorio para revisar solo el slice.
- Un consent binding previo expirado bloquea START con `consent-binding-stale`: reintentar START con idempotencyKey nueva devuelve el flujo (lineage_created:false no quema nada).
- Los PNG copiados desde Windows heredan 0755; chmod 644 antes de commitear para no disparar `executable_mode` en ASSESS.
