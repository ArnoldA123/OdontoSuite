# Task: tooling del pase con navegador real, eje A7

Status: Tooling mergeado; pase en vivo BLOQUEADO por entorno (faltan libs de
Chromium; el dueño las instala con sudo y avisa para re-correr).

## Tareas

- [x] Deps playwright@^1.63 (lock frozen-compatible), `playwright.config.js`,
      `tests/e2e/smoke.spec.js` (login real + 6 páginas, sin sleeps, captura
      console/pageerror + HTTP≥400 por página), wrapper `browser-pass.mjs`
- [x] Verificar: sintaxis, ESLint limpio en los 3 archivos, build exit 0
- [ ] Re-correr tras `sudo ... install-deps` del dueño → evidencia por página
- [ ] Cerrar #31 (índice final) con A7 verificado

## Espejo Engram

Observación pendiente al mergear.
