# Login Scene Refinements — CERRADO

## Objetivo ✅ cumplido
Correcciones de feedback del usuario sobre la escena del login (post-merge PR #113/#114):

1. Solo 2 recursos (diente simple + cepillo). **Fuera** `tooth-2.png` (estrellas) y el contorno amarillo: nunca se pidieron. ✅
2. Marcas más pequeñas y mezcla variada diente/cepillo. ✅
3. Tarjeta centrada en escritorio (decisión del usuario). ✅
4. Parallax táctil sigue el dedo al arrastrar, sin tembleo al tocar. ✅

## Tareas — todas completadas
| # | Tarea | Evidencia |
|---|---|---|
| T1 | feat(login): escena diente+cepillo, marcas menores/variadas, card centrada | commit `5eb6e63` (TDD RED 14 → GREEN; tests + contrato de assets) |
| T2 | fix(login): parallax táctil (pointermove) sin tembleo | commit `c224c86` (RED 1 → GREEN; tap+jitter dejan la escena quieta) |
| T2b | Corrección RDD R3-001/R3-002 | commit `ddaf357` (16 diff lines, plan acotado) |
| T3 | Verificación + entrega | JS 26/115, PHP 662/2953, lint 0, build ✓, capturas revisadas; PR #115 mergeado (`0db46d9`) |

## Diseño final
- 15 marcas: 9 dientes (1.9-4.4vw) + 6 cepillos (2.2-4.5vw) en 3 capas; ninguna marca supera 4.5vw.
- Card centrada a todos los tamaños; escena repartida alrededor (bandas + bordes), sin solape con controles.
- `usePointerParallax`: solo `pointermove` retargetea (todo pointerType), pointerdown/up registran ancla sin mover el objetivo, slop 4px, reduced-motion inerte.

## Revisión RDD R9 (lineage `review-87bc80541ce686ef`)
Tier high (hot-path auth), 4 lentes (risk/resilience/readability/reliability), 8 paths/986 líneas vs `6e2a629`. Resultado: `correction_required` con 2 BLOCKER deterministas (R3-001: marca a 4.8% > techo 4.5%; R3-002: 4 cepillos < 5 requeridos). Corrección acotada de 16 diff lines (`ddaf357`) + validación dirigida **approved** → authority burned (`rev 6482f1f2…`).

## Aprendizajes clave
- El reviewer puede contar/clasificar mal (vio 4 cepillos donde hay 5 y llamó "diente" a un cepillo): la corrección debe cerrar el hallazgo bajo cualquier lectura (aquí: ninguna marca >4.5vw y 6 cepillos).
- Flujo de corrección RDD: capture del plan (`correctionLines`, diff lines = línea reemplazada cuenta 2) → aplicar el diff exacto → commit → STATUS ofrece `targeted_validation_required` → capture validator (forecast + run) → approved → acknowledge.
- El plan de corrección debe coincidir EXACTO con el diff aplicado (8 reemplazadas = 16 diff lines).

## Estado
PR #115 mergeado en main (`0db46d9`). Ramas limpias. Sin pendientes.
