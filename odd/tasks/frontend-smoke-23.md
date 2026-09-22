# Task: verificación ejecutable del frontend (issue #23)

Status: DONE, PR abierto. Pendiente: merge (cierra #23).

## Alcance (elegido por el dueño)

Runner + 1 smoke por módulo (19/19) + step CI. Cero cambios en `resources/js`.

## Tareas

- [x] Deps: vitest@^2 (+test-utils@^2, jsdom@^25 por Node 22 del CI)
- [x] `vitest.config.js` (jsdom, setup, alias `@`, VITE_APP_URL) + `tests/js/setup.js`
      (rAF, matchMedia, observers, canvas proxy, animate)
- [x] 19 smokes: montan página real + AppLayout real; mockean useApi/Echo/WS
- [x] `package.json` script `test:js` (sin tocar `test` ni `test:unit`: colisión
      documentada) + step CI bloqueante tras ESLint
- [x] Mutación NotFoundPage → suite falla; restaurado → verde (criterio #23)
- [x] `pnpm lint:check` exit 0, `pnpm build` exit 0, lock `--frozen-lockfile` OK
- [ ] Merge (cierra #23)

## Hallazgos laterales (warnings, no fallos)

- `ReceptionProceduresPage` referencia `<UiEmptyState>` no registrado.
- `v-motion` sin resolver en LoginPage bajo tests.
- Insumo para issues aparte, no bloquean este PR.

## Espejo Engram

Observación pendiente al mergear.
