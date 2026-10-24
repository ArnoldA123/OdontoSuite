# Retire maatwebsite/excel (#185)

Feature: drop the unused `maatwebsite/excel` dependency and its abandoned PHPExcel tree.
Branch: `chore/remove-maatwebsite-excel` · Issue: #185 · Risk: item 5 (dependencies) and item 3 (shared install surface).

## Specs

S1. "La dependencia y su lock se retiran del proyecto."

S2. "`composer install` y la suite siguen en verde."

S3. "Si en el futuro hace falta XLSX real, se evalúa `phpoffice/phpspreadsheet` mantenida, no este paquete."

## Tasks

| ID  | Spec | Route   | Commit   |
| --- | ---- | ------- | -------- |
| T1  | S1   | inline  | 0a04722 |
| T2  | S2   | inline  | 0a04722 |

T1 — `composer remove maatwebsite/excel`; confirm the lock no longer names `maatwebsite/excel` or `phpoffice/phpexcel`.
T2 — `composer validate`, `composer install --dry-run`, package discovery, full MySQL suite, Pint.
T3 (out of code) — leave the historical rationale comments in `CashReportExport`/`CashReportController` intact: they explain why the export is hand-rolled, and S3 keeps `phpoffice/phpspreadsheet` as the future option.

## Log

L1. User request (verbatim): "quiero hacer issues #183 #184 #185 #186 #206, en un worktree nuevo".
L2. Grouping decision (user): five branches, five PRs, one per issue.
L3. Confirmed no runtime use: the only hits for `Maatwebsite`/`Excel::`/`PHPExcel` are explanatory comments in `app/Exports/CashReportExport.php` and `app/Http/Controllers/Api/CashReportController.php`.
