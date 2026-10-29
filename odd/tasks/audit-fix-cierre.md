# Cierre del plan de corrección de la auditoría UI 2026-10 (ola 7)

Rama `chore/cierre-auditoria-2026-10` sobre `main` (`a3a7db29`), worktree
`../odontosuite-cierre`. Cierra la ola 7 del plan #13: cruce de las 71 issues,
duplicados, verificación global y acta. No corrige producto.

## Specs

S1 — Cruce completo. Las 71 issues del programa (67 hallazgos de sesión, #384,
#386, #458 y #459) quedan clasificadas una a una como cerrada con evidencia,
duplicada de otra o pendiente. Una issue solo cuenta como cerrada con evidencia
si está `CLOSED`, su commit está en `main` y el commit toca un archivo de
prueba (o declara su comprobación equivalente).

```console
$ gh issue list --state all --label auditoria-2026-10 --limit 200 --json number,state
# 88 issues, todas CLOSED/COMPLETED
$ gh issue list --state open --label auditoria-2026-10 --limit 200 --json number
[]
```

S2 — Duplicados. #384→#378, #385→#379, #436→#453, #439→#420, #440 y #437→#415
y #381→#405 quedan documentados en su issue con el issue clase y el commit de
la corrección compartida. No se reabre ninguna; #375 (U12) no se toca.

```console
$ gh api repos/ArnoldA123/OdontoSuite/issues/384/comments
# cuerpo con "#378" y "d32681c"
```

S3 — Base aislada. La suite corre contra `odontosuite_cierre_test` en el MySQL
8.0 de `docker-compose.yml` (127.0.0.1:3307). `odontosuite` (desarrollo) y
`odontosuite_test` no se usan ni se recrean.

```console
$ DB_DATABASE=odontosuite_cierre_test vendor/bin/phpunit -c phpunit.mysql.xml
OK (2915 tests, 17853 assertions)
```

S4 — Verificación estática y de cliente. Vitest, ESLint, Prettier, build de
Vite, Pint y `doc-drift` salen en verde sobre el mismo árbol.

```console
$ ./node_modules/.bin/vitest run
Test Files 173 passed (173) / Tests 1564 passed (1564)
$ ./node_modules/.bin/eslint . --ext .mjs,.vue,.js,.jsx,.ts,.tsx
0 errors, 36 warnings
$ ./node_modules/.bin/prettier --check .
All matched files use Prettier code style!
$ ./node_modules/.bin/vite build
✓ built
$ vendor/bin/pint --test
PASS 928 files
$ node scripts/audit/doc-drift.mjs
All 14 doc-drift checks hold.
```

S5 — Instrumento. `ui-pass --all` se ejecuta contra una app servida desde el
worktree con la base `odontosuite_cierre_audit` sembrada; si la base no está
disponible se declara el motivo. El manifiesto publicado tiene 36 superficies.

```console
$ node scripts/audit/ui-pass.mjs --all --base=http://127.0.0.1:8010
Gate OK: 36/36 superficies, 0 page errors, 18 diálogos y 0 fallos de contrato. La corrida deja 361 passed / 10 failed / 60 skipped; los fallos son del arnés (#501).
```

S6 — Acta y conteos. `docs/mejoras/13-informe-correccion-2026-10.md` publica la
tabla issue → commit → veredicto, los comandos y sus resultados y los
follow-ups; `AGENTS.md` §6/§12 y esta nota registran los conteos re-medidos sin
inventar cifras.

## Tasks

| ID | Spec | Entrega | Commit |
| --- | --- | --- | --- |
| T1 | S1 | Cruzar las 71 issues contra GitHub, `main` y sus pruebas | este commit |
| T2 | S2 | Comentar los siete duplicados con clase y commit | este commit |
| T3 | S3-S5 | Correr la verificación global en la base aislada | este commit |
| T4 | S6 | Publicar el informe de corrección | este commit |
| T5 | S6 | Actualizar AGENTS.md §6 y §12 | este commit |
| T6 | S6 | Publicar esta nota ODD | este commit |

## Log

- **L1** — Pedido del dueño: cerrar el plan de corrección de la auditoría UI
  2026-10 (ola 7), sin corregir producto, sin reabrir #375 y sin tocar la base
  de desarrollo ni los datos sembrados.
- **L2** — Estado medido: `main` = `origin/main` = `a3a7db29`; 88 issues con la
  etiqueta, todas cerradas; 0 abiertas. Las olas 1-6 están mergeadas.
- **L3** — Cruce: 64 issues cerradas con commit en `main` y prueba; 7
  duplicadas con su clase; 0 pendientes. La única evidencia no testimonial es
  #386, cuya comprobación es el lint de `.mjs`.
- **L4** — Duplicados: siete comentarios publicados con el issue clase y los
  commits (enlaces en el informe §4).
- **L5** — Verificación: suite MySQL OK (2915/17853); Vitest 173/1564; ESLint
  0 errores/36 avisos; Prettier limpio; build OK; Pint 928 archivos; doc-drift
  14/14; ui-pass gate OK, 36/36 superficies (361 passed / 10 failed del arnés, #501).
- **L6** — Sin follow-ups nuevos. El acta vive en
  `docs/mejoras/13-informe-correccion-2026-10.md`.
