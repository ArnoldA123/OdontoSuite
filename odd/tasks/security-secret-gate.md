# Seguridad — credencial local redactada y gate de secretos

Chore sin issue, derivado de la auditoría de datos sensibles del 2026-10-23.
Riesgo alto por el item 5 (CI): el job `security` puede bloquear merges. Rama
`chore/security-secret-gate`, worktree `.worktrees/security-secret-gate`.

## Specs

S1. Arnold: «si hazlo» — sobre la propuesta de «sanear los 46 docs locales y
preparar el gate de `gitleaks` + la nota de excepción en una PR aparte».

S2. Arnold, sobre el saneo: «a que haces referencia con sanear los 46 docs?? y
preparar el gate??». El saneo reemplaza valores sensibles por marcadores sin
tocar el contenido; el gate es un check de CI que falla cuando entra un secreto.

S3. Decisión del 2026-10-23: si no se rota la clave local, la mitigación es
congelar la topología (socket-only, phpMyAdmin local), no reutilizarla,
prevenir la siguiente fuga y registrar la excepción con sus disparadores.

## Tasks

- T1 — S2 — sanear los 46 documentos ODD no versionados: clave real en 2
  (`appointment-types-audit.md`, `quotations-interface-redesign.md`) y rutas
  `<ruta-local>` en 17. Local, sin commit.
- T2 — S2 — sanear los documentos versionados que aún publicaban el valor:
  `odd/tasks/payment-methods-interface-redesign.md:115` (la credencial estaba en
  HEAD, no sólo en la historia) y las rutas de otras cuatro notas. Commit
  `docs(security)`.
- T3 — S2 — `.gitleaks.toml` con las reglas `project-db-password` y
  `project-xampp-root-password` más allowlist de marcadores; job `security` en
  `.github/workflows/ci.yml` con gitleaks 8.30.1 fijado y verificado por
  checksum, sobre el rango de commits que publica el run.
- T4 — S2 — guard estático `SecretScanGateTest` (3): job bloqueante, versión
  fijada, checksum, `--log-opts`, reglas propias, allowlist y `pull_request`.
- T5 — S2 — `SECURITY.md` con la excepción, sus condiciones y los disparadores
  de rotación; AGENTS.md §4, §6, §10 y §12 sincronizados.
- T6 — S2 — evidencia local: fixture que falla (4 hallazgos), `odd/` sano que
  pasa, rango `origin/main..HEAD` limpio.
- T7 — verificación — PHPUnit guard, YAML, Prettier, doc-drift, assess nativo y
  verificación independiente por el item 5 de riesgo, CI de la PR.

## Decisiones

- El gate escanea los commits que publica el run, nunca toda la historia: la
  fuga conocida ya es pública y teñiría cada corrida. Queda documentada en
  `SECURITY.md` con sus disparadores de rotación.
- Se fija la versión del binario y se verifica contra el checksum publicado: un
  gate que cambia solo no es un gate.
- La allowlist es por valor (`secret`, `root`, `password123`, `testing-app-*`,
  `local-key`), no por archivo: un valor real en el mismo sitio sigue fallando.
- El saneo de la nota versionada no despública la historia; sólo saca el valor
  del árbol actual.

## Log

L1 — Arnold: «en que documentos del odd y de otras carpetas que estan en el
repo remoto hay datos sensibles».

L2 — Arnold: «que me recomiendas si no quiero cambias la clave <clave local> de xamp».

L3 — Arnold: «a que haces referencia con sanear los 46 docs?? y preparar el
gate?? a que haces referencia?».

L4 — Arnold: «si hazlo».

## Verificación independiente (gentle-ai-verify)

Veredicto: **NON-BLOCKING**. Evidencia reproducida: checksum del tarball contra la
línea publicada (una sola coincidencia), reglas disparan en env/YAML/citado/export
y en `root/<4+ dígitos>`, marcadores no disparan, rama limpia en
`origin/main..HEAD` (2 commits, 0 hallazgos), historia de `b914f45`/`2fd17a2`
confirmada, guard 3/24 verde.

- **F1 (alta)** — gitleaks 8.30.1 devuelve **exit 0** cuando el rango no resuelve
  (`deadbeef..HEAD`) o está vacío (`HEAD..HEAD`): en un force-push a una rama el
  `before` deja de existir y el job queda verde escaneando 0 commits. Los flujos
  PR / push normal / push de merge sí son fail-closed (probados). Recomendación:
  validar ambos extremos con `git cat-file -e` y fallar si el rango resuelve 0
  commits.
- **F2 (baja)** — el `useDefault` de gitleaks trae stopwords que suprimen
  credenciales formadas sólo por palabras comunes (`pass`, `${PASS}`); no está
  documentado.
- **F3 (baja)** — `tests/Unit/Tooling/AuditBaselineCheckTest.php` tiene valores
  `DB_PASSWORD=<valor literal>` / `foo bar` que dispararían la regla si un commit
  futuro toca esas líneas; hoy está fuera del rango.
- **F4-F6 (info)** — instalación sólida, guard honesto en su alcance, docs y
  claims consistentes con el código y la historia.

Decisión: F1 se corrige como unidad de seguimiento (no se edita el candidato
congelado de la revisión nativa en curso).

## Revisión nativa (3 lentes, tier high)

- Refutador: hallazgo `R4-branch-deletion-red` (CRITICAL, inferencial, causalidad
  `introduced`) — mismo defecto que F1 del verificador: borrado de rama y rango
  irresoluble. Corrección acotada aprobada con **56 líneas de diff** (presupuesto
  148): salida verde en borrado, fallback a `origin/main..HEAD` si el `before`
  no existe, resolución de extremos y fallo si el rango tiene 0 commits.
- Estado final: **approved**; autoridad quemada (`gentle-ai.review-acknowledged/v1`).
- 12 hallazgos informativos (allowlist por valor, retry del instalador, timeout
  del job, observabilidad del rango) quedan como follow-up no bloqueante.
- Orden aprendido: el plan de corrección se registra **antes** de tocar el
  candidato (el binding apunta al target congelado); si se corrige primero, el
  binding queda stale. La rama de entrega se armó limpia desde `22c4df0` +
  cherry-pick de la corrección, con el mismo árbol revisado (`43ed7f99`).

## Entrega

Rama `chore/security-secret-gate-clean` (árbol revisado), PR pendiente de CI.

## CI y cierre

- PR #331 (`chore/security-secret-gate-clean`, 3 commits, árbol revisado).
- Primer run real del job `Secret scan`: **success**; `frontend-build` success;
  `quality` y `backend-tests` en curso al escribir esta línea.
- Push protection y secret scanning del repositorio ya estaban **enabled**; no
  hubo que cambiar la configuración de GitHub.
- Pendiente de decisión del usuario: merge de la PR, rotación de la clave y
  versión (o no) de los 46 documentos ODD locales.

## Reconciliación con main

- `main` avanzó con `0a2d339` («Redact sensitive info in payment methods log
  entry»): la misma nota quedó redactada allí como `XAMPP********`. El merge
  `7e1e734` resolvió el conflicto tomando la versión de `main` (mi cambio en ese
  archivo era redundante); el resto del saneo viaja intacto.
- Checks re-corridos tras el merge: guard 3/28, `origin/main..HEAD` 0 hallazgos,
  Prettier y doc-drift 14/14.
- CI del merge: runs `39994858514` (push) y `39994867420` (PR) en curso.

## CI del merge

- Run del PR `39994867420`: **success** en `Secret scan`, `Quality gates`,
  `Backend tests (MySQL)` y `Frontend build`.
- La protección de `main` exige hoy `Quality gates`, `Backend tests (MySQL)` y
  `Frontend build`; **`Secret scan` no está en la lista de required checks**, así
  que un hallazgo teñiría el run pero no bloquearía el merge por sí solo. Cambiar
  la protección es una decisión del usuario.
- `BLOCKED` transitorio mientras el run de push duplicaba los contextos.

## Estado final

- PR #331 **MERGEABLE/CLEAN**; runs `39994867420` (PR) y `39994858514` (push):
  `Secret scan`, `Quality gates`, `Backend tests (MySQL)` y `Frontend build` en
  verde en ambos.
- Queda pendiente de decisión del usuario: fusionar #331, agregar `Secret scan`
  a los required checks de `main`, y limpiar worktree/ramas. El doc local sigue
  sin versionar.

## Cierre

- PR #331 fusionada en `17db5c7` (merge commit); `main` local y remoto en ese
  commit.
- `Secret scan` agregado a los required checks de `main` (junto a `Quality
  gates`, `Backend tests (MySQL)` y `Frontend build`; `strict: true`), así que
  un hallazgo ahora bloquea el merge por sí solo.
- Limpieza: worktree `.worktrees/security-secret-gate` retirado, ramas locales
  `chore/security-secret-gate-clean` y `chore/security-secret-gate` borradas,
  rama remota auto-borrada al fusionar, scratch `/tmp` eliminado. Los worktrees
  de otras sesiones no se tocaron.
