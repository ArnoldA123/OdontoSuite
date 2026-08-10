# API Authentication Exception Envelope (NEW — PRODUCTION FIX, PR5 delta)

## Purpose

Fix the production defect surfaced by PR5 (`apply-progress.md` §"PR5 risks" §2). `bootstrap/app.php::withExceptions(...)` registers a generic `\Throwable` render handler (lines 80-94) that returns a 500 envelope (`{"message":"Error interno del servidor.", "error": ...}`) for every unhandled exception. Because Laravel's `Illuminate\Auth\AuthenticationException` is a `Throwable`, the catch-all swallows it and Sanctum routes that should emit 401 silently emit 500 — hiding unauthenticated access from monitoring and breaking every Feature test asserting `assertStatus(401)`. The fix is bounded: register an explicit `AuthenticationException` render handler BEFORE the generic Throwable handler. Existing 422 / 404 / 403 / 500 envelopes MUST be preserved unchanged. This is a PRODUCTION FIX; no new permissions, no middleware changes, no route changes.

## Source evidence

- `bootstrap/app.php` lines 80-94 — generic Throwable render returns 500 for ALL unhandled exceptions including `AuthenticationException`.
- `apply-progress.md` PR5 §"Test results summary" — `assertStatus(401)` Feature tests fail because the catch-all converts Sanctum's `AuthenticationException` into a 500.
- Existing handlers at `bootstrap/app.php` lines 24-77 — `ValidationException` → 422, `ModelNotFoundException` → 404, `NotFoundHttpException` → 404, `UnauthorizedHttpException` → 401, `AccessDeniedHttpException` → 403, generic `HttpException` 403 → 403. None of these MUST change.

## Requirements

### Requirement: AuthenticationException returns a 401 JSON envelope

When a request to an `api/*` route (or any request that `expectsJson()`) hits an `Illuminate\Auth\AuthenticationException`, the system MUST return HTTP 401 with a JSON body matching the shape produced by the existing `UnauthorizedHttpException` handler: `{"message": "No autenticado.", "error": <string|null>}`. The handler MUST be registered BEFORE the generic `\Throwable` render handler in `bootstrap/app.php` so it short-circuits the catch-all.

#### Scenario: Unauthenticated GET to a Sanctum route returns 401

- GIVEN an `api/*` route guarded by `auth:sanctum`
- AND no `Bearer` token is presented
- WHEN the request is made
- THEN the response status MUST be `401`
- AND the body MUST match `{"message": "No autenticado.", "error": <string|null>}`
- AND when `config('app.debug') === false` the `error` key MUST be `null`
- AND the response MUST NOT be `500` with `message: "Error interno del servidor."`.

#### Scenario: AuthenticationException handler is registered before Throwable

- GIVEN `bootstrap/app.php::withExceptions(...)`
- WHEN the file is read top-to-bottom
- THEN an `$exceptions->render(function (\Illuminate\Auth\AuthenticationException ...))` block MUST appear before the existing generic `\Throwable` render block
- AND the generic Throwable handler MUST still exist (unchanged) so 500 envelopes continue to be logged and returned for non-Auth exceptions.

#### Scenario: Existing 401 envelope (UnauthorizedHttpException) shape is preserved

- GIVEN a route throws `Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException` (the path used by `abort(401, ...)`)
- WHEN the request is made
- THEN the response MUST still be `401` with body `{"message": "No autenticado.", "error": ...}`
- AND no duplicate handler MUST cause a regression.

### Requirement: Other error envelopes preserved unchanged

The slice MUST NOT alter the 422 (`ValidationException`), 404 (`ModelNotFoundException` / `NotFoundHttpException`), 403 (`AccessDeniedHttpException` / generic `HttpException` 403), or 500 (`Throwable`) handlers. Only the `AuthenticationException` path changes from 500 → 401.

#### Scenario: Validation envelope still 422

- GIVEN an `api/*` route guarded by a `FormRequest`
- WHEN an invalid payload is posted
- THEN the response MUST be `422` with body `{"message": "Los datos proporcionados no son válidos.", "errors": {...}}`.

#### Scenario: ModelNotFound envelope still 404

- GIVEN an `api/*` route guarded by `findOrFail`
- WHEN the ID does not exist
- THEN the response MUST be `404` with body `{"message": "El recurso solicitado no fue encontrado.", ...}`.

#### Scenario: Role-denied envelope still 403

- GIVEN an `api/*` route guarded by `role:`
- WHEN the authenticated user's role lacks the required role
- THEN the response MUST be `403` with body `{"message": "No tienes permisos para acceder a este recurso.", ...}`.

#### Scenario: Generic 500 envelope still emitted for non-Auth exceptions

- GIVEN an `api/*` route
- WHEN an unexpected exception (e.g., `RuntimeException`) is thrown
- THEN the response MUST be `500` with body `{"message": "Error interno del servidor.", ...}`
- AND the error MUST be logged via `\Illuminate\Support\Facades\Log::error('API Exception', ...)`.

### Requirement: Permissions and middleware aliases unchanged

The slice MUST NOT add, remove, or rename middleware aliases. `auth:sanctum`, `role:`, `throttle.login`, and `cash.session` MUST keep their existing semantics.

#### Scenario: Middleware alias map intact

- GIVEN `bootstrap/app.php::withMiddleware(...)`
- WHEN the file is read
- THEN the alias map MUST remain `['role' => CheckRole::class, 'throttle.login' => ThrottleLoginAttempts::class, 'cash.session' => RequireActiveCashSession::class]`.

#### Scenario: No new role names introduced

- GIVEN the existing role list (`administrador`, `odontologo`, `recepcionista`, `cajero`, etc.)
- WHEN the slice is applied
- THEN no new role MAY be added and no existing role MAY be renamed.

## Permissions

- No new permissions. The slice only adjusts the exception render order in `bootstrap/app.php`.

## Rollback invariants

- Reverting the slice commit alone MUST remove the new `AuthenticationException` handler block. The generic `\Throwable` handler MUST remain exactly as it was.
- The slice MUST NOT modify any controller, route file, middleware class, or policy.
- The slice MUST NOT change any of the existing 422 / 404 / 403 / 500 envelope shapes.
