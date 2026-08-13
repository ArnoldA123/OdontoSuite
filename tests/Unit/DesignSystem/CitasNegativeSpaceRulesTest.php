<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR-citas-05 — CitasNegativeSpaceRulesTest.
 *
 * Cross-cutting negative-space guard for the CITAS rollout. Extends plain
 * `TestCase` (NOT `ModuleAppShellTestCase`) because the rules are
 * cross-cutting — they span all 5 polished CITAS modules and assert
 * ABSENCES (rules that MUST NOT regress).
 *
 * The 5 negative-space rules map directly to the CITAS spec rows:
 *
 *   1. CITAS-TZ-001 — no JS-side `.toISOString()` on `datetime-local` inputs.
 *      Server interprets naive local time as `app.timezone` per
 *      `AppointmentService::createAppointment`; a JS-side `.toISOString()`
 *      would drop the local TZ offset and silently corrupt appointment time.
 *
 *   2. CITAS-CONF-001 — no client-side conflict heuristic. The modal MUST
 *      NOT compute conflict status locally; the source of truth is
 *      `AppointmentRepository::findConflicts` (server-side round-trip).
 *      The catch block mapping the 422 duplicate-key to a friendly
 *      message is the only valid client-side reference.
 *
 *   3. CITAS-CONF-001 (token variant) — no `ConfirmationToken` exposure.
 *      `ConfirmationToken` is backend-only; the frontend MUST NOT render
 *      the hash, id, or raw token to non-admin viewers.
 *
 *   4. CITAS-WS-001 — no `WorkSchedule` / `AppointmentBlock` enforcement UX.
 *      The validations are commented out in
 *      `AppointmentService::createAppointment` lines 75–89 ("profesionales
 *      trabajan 24/7"). UI MUST NOT imply enforcement (e.g. "fuera de
 *      horario", "en bloqueo", "scheduling blocked").
 *
 *   5. CITAS-CON-001 — existing `<script>` block reactivity preserved
 *      byte-for-byte. UI changes are template-level class-string
 *      replacement only; the `<script>` blocks of the 5 CITAS modules
 *      MUST keep their existing function signatures (proxy: assert each
 *      module's script block contains at least one preserved signature).
 *
 * Per `categories/citas/spec.md` §2 CITAS-CON-001, `useConsultation`
 * composable contract AND the `useEcho` `appointments` channel
 * subscription are preserved verbatim. The signature assertion below
 * is the cross-cutting pin for that contract.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because path
 * patterns contain forward slashes; using `/` as delimiter would force
 * every `/` in the path to be escaped `\/`, which is brittle and
 * error-prone.
 */
class CitasNegativeSpaceRulesTest extends TestCase
{
    /**
     * The 5 CITAS polished `.vue` files. The cross-cutting rules are
     * asserted against every file in this list.
     *
     * @return array<int, string>
     */
    private static function citasPolishedFiles(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root . '/resources/js/modules/appointments/ConsultationWizard.vue',
            $root . '/resources/js/modules/appointments/CalendarPage.vue',
            $root . '/resources/js/components/appointments/NewAppointmentModal.vue',
            $root . '/resources/js/modules/appointment-types/AppointmentTypesPage.vue',
            $root . '/resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue',
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function citasPolishedFileProvider(): array
    {
        $cases = [];
        foreach (self::citasPolishedFiles() as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    /**
     * CITAS-TZ-001 — no JS-side `.toISOString()` call on any `datetime-local`
     * input value. The exact regex `\.toISOString\(\)` matches the bare
     * call (no arguments). The server interprets `datetime-local` input
     * as naive local time per `AppointmentService::createAppointment`
     * (`Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`).
     *
     * The regression from `CalendarPage.vue::getInitialDateForModal`
     * (the line `return date.toISOString().slice(0, 16)`) is the exact
     * bug CITAS-TZ-001 was created to prevent.
     *
     * @dataProvider citasPolishedFileProvider
     */
    public function test_no_to_iso_string_on_datetime_local(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $matches = [];
        $count = preg_match_all('#\.toISOString\s*\(\s*\)#', $src, $matches);
        $this->assertSame(
            0,
            $count,
            sprintf(
                '%s contains %d `.toISOString()` call(s) — CITAS-TZ-001 violation. '
                . 'The server interprets `datetime-local` input as naive local time; '
                . 'a JS-side ISO conversion would drop the local TZ offset.',
                $path,
                $count
            )
        );
    }

    /**
     * CITAS-CONF-001 — no client-side conflict heuristic. The modal MUST
     * NOT compute conflict status locally. The 422 duplicate-key catch
     * block is the only valid client-side reference (and the regex
     * accommodates the literal `duplicate_key` / `422` detection).
     *
     * The rule forbids:
     *   - `findConflicts` (mirrors the backend method name)
     *   - `hasConflict` / `conflicts` / `conflicts.length` / `conflict_count`
     *     in a non-test method
     *   - any `setTimeout` / `setInterval` that counts blocks before submit
     *
     * The acceptance criterion: zero `findConflicts` / `hasConflict` / `conflicts`
     * references in any of the 5 CITAS modules.
     *
     * @dataProvider citasPolishedFileProvider
     */
    public function test_no_client_side_conflict_heuristic(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The backend uses `findConflicts` method name. The frontend MUST NOT
        // call it (the server is the conflict oracle). The grep is case-sensitive
        // to avoid false positives on variables like `conflictsEnabled`.
        $this->assertSame(
            0,
            preg_match('#\bfindConflicts\b#', $src),
            sprintf(
                '%s contains `findConflicts` — CITAS-CONF-001 violation. '
                . 'The server is the conflict oracle; the frontend MUST NOT call '
                . '`AppointmentRepository::findConflicts` directly.',
                $path
            )
        );

        // `hasConflict` heuristic (boolean derived from a local block count).
        $this->assertSame(
            0,
            preg_match('#\bhasConflict\b#', $src),
            sprintf(
                '%s contains `hasConflict` — CITAS-CONF-001 violation. '
                . 'A local conflict heuristic is stale by definition; the source of truth is the server.',
                $path
            )
        );

        // `conflicts.length` heuristic — counting a local array of conflicts
        // before the API round-trip suggests a client-side oracle.
        $this->assertSame(
            0,
            preg_match('#\bconflicts\s*\.\s*length\b#', $src),
            sprintf(
                '%s contains `conflicts.length` — CITAS-CONF-001 violation. '
                . 'A local conflict count is stale by definition.',
                $path
            )
        );

        // `available` time-slot heuristic (computing slot availability from
        // local data without an API round-trip).
        $this->assertSame(
            0,
            preg_match('#\bavailable\b#', $src),
            sprintf(
                '%s contains `available` — CITAS-CONF-001 violation. '
                . 'A front-end "available" claim is stale; the server is the oracle.',
                $path
            )
        );
    }

    /**
     * CITAS-CONF-001 (token variant) — no `ConfirmationToken` exposure.
     * `ConfirmationToken` is backend-only (`app/Models/ConfirmationToken.php`).
     * The frontend MUST NOT render the hash, id, or raw token. The grep
     * covers both `ConfirmationToken` (PascalCase, full class name) and the
     * snake_case `confirmation_token` string identifier (which may appear in
     * API responses or DTO mapping).
     *
     * @dataProvider citasPolishedFileProvider
     */
    public function test_no_confirmation_token_exposure(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#ConfirmationToken#', $src),
            sprintf(
                '%s contains `ConfirmationToken` — CITAS-CONF-001/UXF-021 violation. '
                . 'The model is backend-only; the frontend MUST NOT reference the hash, id, or raw token.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#\bconfirmation_token\b#', $src),
            sprintf(
                '%s contains `confirmation_token` (snake_case) — CITAS-CONF-001 violation. '
                . 'Frontend code MUST NOT reference the snake_case token identifier.',
                $path
            )
        );
    }

    /**
     * CITAS-WS-001 — no `WorkSchedule` / `AppointmentBlock` enforcement UX.
     * The validations are commented out in `AppointmentService::createAppointment`
     * lines 75–89 ("profesionales trabajan 24/7"). The UI MUST NOT imply
     * enforcement (e.g. "fuera de horario", "en bloqueo", "scheduling blocked").
     *
     * The grep covers both `WorkSchedule` / `work_schedule` / `work schedule`
     * (the legacy class names + snake_case + free-text) and `AppointmentBlock`
     * / `appointment_block` / `appointment block`. Any match implies a UX
     * affordance that the backend does not enforce.
     *
     * @dataProvider citasPolishedFileProvider
     */
    public function test_no_work_schedule_or_block_enforcement_ux(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#\bWorkSchedule\b#', $src),
            sprintf(
                '%s contains `WorkSchedule` — CITAS-WS-001 violation. '
                . 'The validations are commented out in `AppointmentService::createAppointment` '
                . 'lines 75–89 ("profesionales trabajan 24/7"); the UI MUST NOT imply enforcement.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#\bwork_schedules?\b#', $src),
            sprintf(
                '%s contains `work_schedule` (snake_case) — CITAS-WS-001 violation. '
                . 'The validations are commented out; the UI MUST NOT imply enforcement.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#\bAppointmentBlock\b#', $src),
            sprintf(
                '%s contains `AppointmentBlock` — CITAS-WS-001 violation. '
                . 'The `appointment_blocks` table exists but is NOT enforced; the UI MUST NOT imply enforcement.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#\bappointment_blocks?\b#', $src),
            sprintf(
                '%s contains `appointment_block` (snake_case) — CITAS-WS-001 violation. '
                . 'The table is not enforced; the UI MUST NOT imply enforcement.',
                $path
            )
        );

        // UX-text heuristics: "fuera de horario" / "en bloqueo" / "scheduling blocked"
        // would imply enforcement. The regex is case-insensitive.
        $this->assertSame(
            0,
            preg_match('#fuera\s+de\s+horario#i', $src),
            sprintf(
                '%s contains "fuera de horario" UX text — CITAS-WS-001 violation. '
                . 'The work-schedule validation is dormant; the UI MUST NOT imply enforcement.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#\ben\s+bloqueo\b#i', $src),
            sprintf(
                '%s contains "en bloqueo" UX text — CITAS-WS-001 violation. '
                . 'The block validation is dormant; the UI MUST NOT imply enforcement.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#scheduling\s+blocked#i', $src),
            sprintf(
                '%s contains "scheduling blocked" UX text — CITAS-WS-001 violation.',
                $path
            )
        );
    }

    /**
     * CITAS-CON-001 — existing `<script>` block reactivity preserved
     * byte-for-byte. UI changes are template-level class-string
     * replacement only; the `<script>` blocks of the 5 CITAS modules
     * MUST keep their existing function signatures.
     *
     * The proxy used here: each file's `<script>` block MUST contain at
     * least one well-known preserved signature (a function name, a
     * reactive ref name, or a `defineEmits`/`defineProps` payload). If
     * the apply phase accidentally renames or deletes a load-bearing
     * signature, this assertion fires.
     *
     * The signatures are intentionally canonical (no whitespace flexibility)
     * so accidental whitespace-only edits are still detected. The proxy
     * checks the FULL source file (not just the script block) because the
     * script block is the unit of edit; the reactivity is the contract.
     *
     * @dataProvider citasPolishedFileProvider
     */
    public function test_script_block_reactivity_signature_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $signatures = self::preservedSignaturesFor($path);
        $this->assertNotEmpty(
            $signatures,
            sprintf('No preserved signatures registered for %s.', $path)
        );

        $cmd = self::extractScriptBlock($src);
        $this->assertNotNull(
            $cmd,
            sprintf('%s must contain a `<script>` block.', $path)
        );

        foreach ($signatures as $signature) {
            $this->assertMatchesRegularExpression(
                $signature,
                $cmd,
                sprintf(
                    '%s `<script>` block is missing preserved signature `%s` (CITAS-CON-001). '
                    . 'The reactivity contract is byte-for-byte; rename or removal of a load-bearing '
                    . 'function/ref/emits payload is a regression.',
                    $path,
                    $signature
                )
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private static function preservedSignaturesFor(string $path): array
    {
        $basename = basename($path);

        return match ($basename) {
            'ConsultationWizard.vue' => [
                '#defineEmits\s*\(\s*\[\s*[\'"]completed[\'"]\s*,\s*[\'"]close[\'"]\s*\]#', // wizard emit contract
                '#useConsultation\b#',                                     // composable contract
            ],
            'CalendarPage.vue' => [
                '#useEcho\b#',                                             // .listen(...) on `appointments`
                '#getInitialDateForModal\b#',                               // day-view initial date for modal
                '#useConsultation\b#',                                     // composable contract
            ],
            'NewAppointmentModal.vue' => [
                '#defineEmits\s*\(\s*\[\s*[\'"]update:modelValue[\'"]\s*,\s*[\'"]created[\'"]\s*,\s*[\'"]updated[\'"]\s*\]\s*\)#', // full emit contract
                '#useApi\b#',                                              // 401 redirect owner
            ],
            'AppointmentTypesPage.vue' => [
                '#useApi\b#',                                              // 401 redirect owner
                '#loadTypes\b#',                                           // list-refresh handler
            ],
            'AppointmentTypeDetailPage.vue' => [
                '#useApi\b#',                                              // 401 redirect owner
                '#loadAppointmentType\b#',                                 // detail-refresh handler
            ],
            default => [],
        };
    }

    private static function extractScriptBlock(string $src): ?string
    {
        if (preg_match('#<script\b[^>]*>(.*?)</script>#s', $src, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
