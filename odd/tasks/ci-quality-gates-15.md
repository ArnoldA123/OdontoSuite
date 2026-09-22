# Task: CI quality gates honestos (issue #15)

Status: DONE, merged a main (PR #80, merge b6f6789).

## Alcance

Solo la #15, a propósito. Superficie: `.github/workflows/ci.yml` (job `quality`)
+ `.prettierrc`. #14/#19/#20 se verificaron ya verdes y se excluyeron para no
pisar trabajo ajeno; #17 depende del estado post-fix; #18/#79 son otro dominio.

## Tareas

- [x] Medir el estado real (Pint 365 archivos en rojo sin reportar, Prettier
      crasheando con `Couldn't resolve parser "php"`, sintaxis PHP con `|| true`)
- [x] Quitar el override `*.php` de `.prettierrc` (el plugin nunca instalado;
      PHP es trabajo de Pint)
- [x] PHP syntax check bloqueante (`FAIL`/`exit 1` + anotación `::error`)
- [x] Pint y Prettier explícitamente no-bloqueantes (`continue-on-error` +
      `::warning::` con ref a #15), sin `|| echo` silenciosos
- [x] Verificar: YAML válido, step PHP en ambas direcciones, format:check sin
      crash, lint:check exit 0, AgentsDocsSyncTest 5 passed
- [x] Commit e4dc6e8, PR #80, merge b6f6789
- [ ] Cerrar #15 con evidencia (pendiente al crear este doc)

## Evidencia

- `vendor/bin/pint --test | grep -c "^  ⨯"` → 365
- Step PHP simulado: OK en limpio, `::error` + exit 1 con fixture roto
- `pnpm format:check` → exit 2 con deuda real, sin crash de parser
- `pnpm lint:check` → exit 0 (0 errores)

## Follow-ups (fuera de alcance)

- Limpieza Pint → volver el step bloqueante
- `pnpm format` sobre el árbol → volver Prettier bloqueante

## Espejo Engram

Observaciones `ci-quality-gates-15` (decisión: selección y merge PR #80).
