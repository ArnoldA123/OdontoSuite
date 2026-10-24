# ODD — fix/ci-shallow-guard-pdf-template

Fecha: 2026-09-22
Rama: fix/ci-shallow-guard-pdf-template
Issue: CI rojo en todos los runs — `PacientesNegativeSpaceRulesTest` falla con exit 128 por clon shallow

## Contexto
- Workflow `CI` (3 jobs) marca X en todos los pushes/PRs/merges aunque no haya PRs abiertos.
- Jobs `Quality gates` ✓ y `Frontend build` ✓; `Backend tests (MySQL)` ✗.
- Test failing (1 de ~1108): `Tests\Unit\DesignSystem\PacientesNegativeSpaceRulesTest::test_pdf_export_template_not_modified` (línea 412).
- Causa: `git diff --quiet HEAD~5 HEAD` en clon shallow (`actions/checkout@v4` default `fetch-depth: 1`) → `fatal: bad revision 'HEAD~5'`, exit 128. El test exige `assertSame(0)` aunque su comentario promete tratar `2+` como inconcluso.
- Archivo inocente: `resources/views/exports/patient-file.blade.php` sin cambios desde initial commit.

## Tareas
1. [x] Diagnóstico (solo lectura) + repro shallow en /tmp.
2. [x] Issue #106 en GitHub con evidencia.
3. [x] Fix A: test tolera exit >= 2 con `markTestIncomplete`.
4. [x] Fix B: `fetch-depth: 0` en checkout de `backend-tests`.
5. [x] Validación local (test unitario + `php -l` + diff revisado).
6. [x] Commit 7959507 + push + PR #107 (CI 3/3 verde) + merge c13908b. Issue #106 cerrada.

## Evidencia
- Runs: 35780506565 (main push), 35780127901 — mismo fallo.
- Repro: `git clone --depth 1` → `git diff --quiet HEAD~5 HEAD` → exit 128.
- Commits de evidencia en la rama.
