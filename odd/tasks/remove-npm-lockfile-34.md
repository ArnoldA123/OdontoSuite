# Task: lock de npm sobrante (issue #34)

Status: PR #81 abierto (rama `fix/remove-npm-lockfile-34`, commit 08e76c9).
Merge pendiente.

## Alcance

Solo #34. `package.json` intacto a propósito (eso es N14 / #32, con decisión
del dueño pendiente y plan doc que dice "No se borra").

## Tareas

- [x] Medir: `package-lock.json` solo referenciado en troubleshooting genérico
      de INSTALACION.md (consejo que sigue válido); CI usa pnpm exclusivo
- [x] `git rm package-lock.json` + `.gitignore` (package-lock.json, yarn.lock)
- [x] Verificar criterio de aceptación: `git ls-files` vacío,
      `pnpm install --frozen-lockfile` exit 0
- [x] Commit 08e76c9, PR #81
- [ ] Merge PR #81 (cierra #34 automáticamente)

## Espejo Engram

Observación `remove-npm-lockfile-34` (bugfix).
