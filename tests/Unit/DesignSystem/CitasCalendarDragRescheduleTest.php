<?php

namespace Tests\Unit\DesignSystem;

/**
 * T5 — CitasCalendarDragRescheduleTest.
 *
 * Structural contract for drag-and-drop rescheduling on the Day/Week grids
 * (CITAS-DND-001). Anchored on the `data-calendar-*` hooks and the real
 * behaviour they mark, never on dead class literals:
 *
 *   1) drag wiring — chips carry `data-calendar-draggable` +
 *      `data-calendar-drag-state`, cells carry `data-calendar-drop-target` +
 *      `data-calendar-drop-active`, and the drop math is delegated to the
 *      pure `dragDropMath.js` module (no HTML5 DnD API, no pointer logic
 *      inlined in the template);
 *   2) permission gate — the drag source arms only under the same
 *      `can.editAppointment` permission that gates edit/status actions, and
 *      slot buttons (click-to-create) are never drag sources;
 *   3) reschedule flow — optimistic write before the request, duration
 *      preserved, rollback on failure, and the 422-conflict branch renders
 *      its blocker toast from the SERVER payload (`conflict.conflicting_appointment`),
 *      never from a client-side oracle (doubles CITAS-CONF-001 for the new
 *      path);
 *   4) timezone safety — the new pure module stays under CITAS-TZ-001 (no
 *      `.toISOString()`), mirroring the payload rule the modal already
 *      follows;
 *   5) no hardcoded colour maps — drag visuals are Tailwind token classes
 *      gated on `useReducedMotion`, with a static (transition-free) reduced
 *      motion fallback.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules on CalendarPage.vue via `polishedFileProvider()`; the calendar-specific
 * assertions live in the methods below.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because some
 * patterns contain forward slashes (import paths, API URLs).
 */
class CitasCalendarDragRescheduleTest extends ModuleAppShellTestCase
{
    /** Calendar page path constant — single source of truth for the absolute path. */
    private const CALENDAR_PATH = '/resources/js/modules/appointments/CalendarPage.vue';

    /** Pure drop-math module path constant (T5 extraction). */
    private const DRAG_MATH_PATH = '/resources/js/modules/appointments/dragDropMath.js';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::CALENDAR_PATH,
        ];
    }

    private static function calendarSource(): string
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = is_file($path) ? file_get_contents($path) : false;
        self::assertNotFalse($src, sprintf('%s must be readable.', $path));

        return $src;
    }

    private static function dragMathSource(): string
    {
        $path = dirname(__DIR__, 3) . self::DRAG_MATH_PATH;
        $src = is_file($path) ? file_get_contents($path) : false;
        self::assertNotFalse($src, sprintf('%s must be readable.', $path));

        return $src;
    }

    /**
     * T5 drag wiring contract — BOTH grids (Day + Week) bind the chip and
     * cell hooks: chips carry `:data-calendar-draggable="appointment.id"`
     * plus the `data-calendar-drag-state` active marker, and every hour cell
     * exposes `:data-calendar-drop-target="`${date}-${hour}`"` with the
     * `data-calendar-drop-active` hover marker driven by `dropHoverSlot`.
     *
     * The hooks are the test/audit surface for the whole feature (the Vitest
     * smoke suite drives the real pointer lifecycle through them), so their
     * count and binding expressions are pinned, not just their presence.
     */
    public function test_drag_hooks_bound_on_both_grids(): void
    {
        $src = self::calendarSource();

        $this->assertSame(
            2,
            preg_match_all('#:data-calendar-draggable\s*=\s*"appointment\.id"#', $src),
            sprintf(
                '%s Day and Week chips MUST carry `:data-calendar-draggable="appointment.id"` (T5 drag wiring).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            2,
            preg_match_all(
                '#:data-calendar-drag-state="\s*draggingAppointmentId === appointment\.id \? \'active\' : null\s*"#',
                $src
            ),
            sprintf(
                '%s chips MUST bind the active drag marker to `draggingAppointmentId` (T5 drag wiring).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            2,
            preg_match_all(
                '#:data-calendar-drop-target\s*=\s*"`\$\{[\w.]+\}-\$\{hour\}`"#',
                $src
            ),
            sprintf(
                '%s Day and Week hour cells MUST expose `:data-calendar-drop-target="`${date}-${hour}`"` '
                . '(T5 drop targets; the key format is the pure-module slot key).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            2,
            preg_match_all(
                '#:data-calendar-drop-active="\s*dropHoverSlot === `\$\{[\w.]+\}-\$\{hour\}` \? \'\' : null\s*"#',
                $src
            ),
            sprintf(
                '%s drop targets MUST bind the hover marker to `dropHoverSlot` (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            2,
            preg_match_all('#@pointerdown\s*=\s*"onChipPointerDown\(\$event, appointment\)"#', $src),
            sprintf(
                '%s Day and Week chips MUST arm the drag via `@pointerdown="onChipPointerDown($event, appointment)"` (T5).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T5 click-vs-drag discrimination — the click-to-create slot buttons are
     * NEVER drag sources: no `@pointerdown` inside any `<button>` block that
     * carries the `data-calendar-slot` hook, and the `openSlotCreate` wiring
     * stays intact. The details-modal path (`selectAppointment` on chip
     * click) is preserved by the smoke suite; here we pin that the slot
     * buttons did not inherit drag arming.
     */
    public function test_slot_buttons_are_not_drag_sources(): void
    {
        $src = self::calendarSource();

        $this->assertSame(
            0,
            preg_match(
                '#<button\b(?:(?!</button>).)*data-calendar-slot(?:(?!</button>).)*@pointerdown#s',
                $src
            ),
            sprintf(
                '%s click-to-create slot buttons MUST NOT carry `@pointerdown` drag arming (T5; '
                . 'drags start only from appointment chips).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            2,
            preg_match_all('#@click\s*=\s*"openSlotCreate\(#', $src),
            sprintf(
                '%s slot buttons MUST keep the `openSlotCreate` click-to-create wiring in both grids (T4 contract preserved under T5).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T5 permission gate — dragging is gated by the SAME permission that
     * gates edit/status actions (`can.editAppointment`, the edit/update key;
     * not `createAppointment` and not ownership). The gate sits at the top
     * of `onChipPointerDown` (the drag never arms) and in `dragChipClasses`
     * (no grab affordance renders for gated roles; month view excluded —
     * month chips are click-only). Non-primary button presses are refused.
     */
    public function test_drag_source_gated_by_edit_appointment_permission(): void
    {
        $src = self::calendarSource();

        $this->assertSame(
            1,
            preg_match(
                '#const onChipPointerDown = \(event, appointment\) => \{\s*if \(!can\.editAppointment\?\.value\) return#',
                $src
            ),
            sprintf(
                '%s `onChipPointerDown` MUST early-return when `can.editAppointment?.value` is false (T5 permission gate: '
                . 'dragging follows the edit/update permission, role-based, not ownership).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                "#if \(!can\.editAppointment\?\.value\) return\n\s*if \(typeof event\.button === 'number' && event\.button !== 0\) return#",
                $src
            ),
            sprintf(
                '%s `onChipPointerDown` MUST refuse non-primary button presses (T5: only the primary button drags).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                "#if \(currentView\.value === 'month' \|\| !can\.editAppointment\?\.value\) return ''#",
                $src
            ),
            sprintf(
                '%s `dragChipClasses` MUST return no affordance for month view or gated roles (T5: month view untouched, '
                . 'no grab cursor without the edit permission).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            0,
            preg_match('#can\.createAppointment\?\.value\)\s*return\b[^;]*drag#i', $src),
            sprintf(
                '%s drag arming MUST NOT be keyed on `createAppointment` (T5: create and edit can differ; dragging is an edit).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T5 lifecycle contract — window-scoped pointer listeners are attached
     * and detached as a set (pointermove/pointerup/pointercancel + a
     * capture-phase keydown for Escape), the drag arms only past
     * `DRAG_THRESHOLD_PX`, the trailing click after a real drag is
     * suppressed so click-vs-drag discrimination holds, and `onUnmounted`
     * tears an in-flight session down (no orphan window listeners).
     */
    public function test_drag_lifecycle_listeners_threshold_and_unmount_cleanup(): void
    {
        $src = self::calendarSource();

        foreach (['pointermove', 'pointerup', 'pointercancel'] as $evt) {
            $this->assertSame(
                1,
                preg_match(
                    "#window\.addEventListener\('" . $evt . "'#",
                    $src
                ),
                sprintf(
                    '%s MUST listen to window `%s` during a drag session (T5 lifecycle).',
                    self::CALENDAR_PATH,
                    $evt
                )
            );
            $this->assertSame(
                1,
                preg_match(
                    "#window\.removeEventListener\('" . $evt . "'#",
                    $src
                ),
                sprintf(
                    '%s MUST remove the window `%s` listener when the session ends (T5 lifecycle).',
                    self::CALENDAR_PATH,
                    $evt
                )
            );
        }

        $this->assertSame(
            1,
            preg_match("#window\.addEventListener\('keydown', onDragKeyDown, true\)#", $src),
            sprintf(
                '%s MUST capture keydown during a drag to honor Escape cancellation (T5 lifecycle).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match("#if \(event\.key !== 'Escape'\) return#", $src),
            sprintf(
                '%s `onDragKeyDown` MUST cancel only on Escape (T5 lifecycle).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match('#if \(moved < DRAG_THRESHOLD_PX\) return#', $src),
            sprintf(
                '%s MUST gate drag start on the `DRAG_THRESHOLD_PX` travel threshold (T5: below it the press stays a click).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match('#Math\.hypot\(event\.clientX - session\.startX, event\.clientY - session\.startY\)#', $src),
            sprintf(
                '%s MUST measure drag travel with `Math.hypot` from the press origin (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#const slotKey = resolveDropSlot\(session\.dropTargets, event\.clientX, event\.clientY\)\s*endDrag\(\{ suppressClick: true \}\)#',
                $src
            ),
            sprintf(
                '%s MUST resolve the drop slot from the pointer position and suppress the trailing click (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#if \(dragSession\) \{\s*endDrag\(\)\s*\} else \{\s*detachDragListeners\(\)\s*\}#',
                $src
            ),
            sprintf(
                '%s `onUnmounted` MUST tear down an in-flight drag session / window listeners (T5 lifecycle).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T5 pure-module delegation + no-request cancellations — CalendarPage
     * imports the drop rules from `./dragDropMath` and consults
     * `canDropOn` BEFORE scheduling the request, so a drop on the
     * appointment's own slot or on a non-future slot rolls back without a
     * network call (the >= 1-minute-future rule is mirrored client-side,
     * the same way the server validates it). The pure module itself stays
     * Vue-free and `.toISOString()`-free (CITAS-TZ-001 extended to T5).
     */
    public function test_drop_math_delegated_to_pure_module(): void
    {
        $src = self::calendarSource();
        $math = self::dragMathSource();

        $this->assertSame(
            1,
            preg_match(
                "#import \{\s*DRAG_THRESHOLD_PX,\s*resolveDropSlot,\s*slotKeyToScheduledAt,\s*addMinutesToLocal,\s*canDropOn\s*\} from '\./dragDropMath'#",
                $src
            ),
            sprintf(
                '%s MUST import the drop rules from the pure `./dragDropMath` module (T5: testable without a DOM).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#if \(!canDropOn\(slotKey, session\.appointment\)\) \{[\s\S]*?return\s*\}\s*rescheduleAppointment\(session\.appointment, slotKey\)#',
                $src
            ),
            sprintf(
                '%s MUST consult `canDropOn` BEFORE calling `rescheduleAppointment` — an invalid drop (own slot / past slot) '
                . 'must not fire a request (T5 no-request cancellations; T8b: the past-slot rejection now also '
                . 'announces itself with an info toast, still client-side only).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            0,
            preg_match("#from 'vue'|from \"vue\"#", $math),
            sprintf(
                '%s MUST stay a pure module (no Vue import) so the drop rules unit-test without a DOM (T5).',
                self::DRAG_MATH_PATH
            )
        );

        $this->assertSame(
            0,
            preg_match('#\.toISOString\s*\(\s*\)#', $math),
            sprintf(
                '%s MUST NOT call `.toISOString()` — payloads are naive-local `YYYY-MM-DDTHH:mm` (CITAS-TZ-001 applied to the T5 module).',
                self::DRAG_MATH_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match('#const MIN_FUTURE_MS = 60 \* 1000#', $math),
            sprintf(
                '%s MUST mirror the endpoint >= 1-minute-future rule (`MIN_FUTURE_MS = 60 * 1000`) so the UI rolls back '
                . 'before calling the API (T5 drop rule).',
                self::DRAG_MATH_PATH
            )
        );
    }

    /**
     * T5 optimistic/rollback matrix (source-level) — `rescheduleAppointment`
     * writes the new `scheduled_at`/`ends_at` optimistically BEFORE the
     * request, preserves the duration (`duration_minutes` sent in the body,
     * `ends_at` derived via `addMinutesToLocal`), and on ANY failure restores
     * the original pair. A pointerup outside a valid target returns without
     * a request.
     */
    public function test_optimistic_update_and_rollback_contract(): void
    {
        $src = self::calendarSource();

        $optimisticWrite = strpos($src, 'appointment.scheduled_at = scheduledAt');
        $request = strpos($src, 'await patch(`');
        $this->assertNotFalse($optimisticWrite, sprintf('%s MUST write the optimistic `scheduled_at`.', self::CALENDAR_PATH));
        $this->assertNotFalse($request, sprintf('%s MUST issue the reschedule request.', self::CALENDAR_PATH));
        $this->assertLessThan(
            $request,
            $optimisticWrite,
            sprintf(
                '%s MUST set the optimistic `scheduled_at` BEFORE awaiting the request (T5 optimistic update).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                "#await patch\(\`/api/appointments/\\\$\{appointment\.id\}/reschedule\`, \{\s*scheduled_at: scheduledAt,\s*duration_minutes: durationMinutes\s*\}\)#",
                $src
            ),
            sprintf(
                '%s MUST PATCH `/api/appointments/{id}/reschedule` with `{scheduled_at, duration_minutes}` (T5 payload contract).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#appointment\.ends_at = addMinutesToLocal\(scheduledAt, durationMinutes\)#',
                $src
            ),
            sprintf(
                '%s MUST preserve the appointment duration on drop: `ends_at = start + duration_minutes` (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#catch \(error\) \{\s*appointment\.scheduled_at = original\.scheduled_at\s*appointment\.ends_at = original\.ends_at#',
                $src
            ),
            sprintf(
                '%s MUST roll back BOTH `scheduled_at` and `ends_at` to the captured originals on failure (T5 rollback).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T5 conflict feedback contract — the 422-conflict branch renders its
     * toast from the SERVER payload (`error.response.data.conflict`), names
     * the blocker by conflict type (chair vs professional) and cites the
     * conflicting appointment's patient + time summary
     * (`conflict.conflicting_appointment`). Every other failure falls back
     * to the honest server message via `normalizeError`. The client keeps NO
     * conflict oracle of its own (doubles CITAS-CONF-001 for the new path:
     * no `findConflicts` / `hasConflict` heuristic anywhere in the module).
     */
    public function test_conflict_feedback_from_server_payload_only(): void
    {
        $src = self::calendarSource();

        $this->assertSame(
            1,
            preg_match(
                '#const conflict = error\?\.response\?\.data\?\.conflict#',
                $src
            ),
            sprintf(
                '%s MUST read the conflict branch from the server 422 payload (`error.response.data.conflict`) — '
                . 'the server is the conflict oracle (T5 / CITAS-CONF-001).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#const other = conflict\.conflicting_appointment#',
                $src
            ),
            sprintf(
                '%s `conflictToastMessage` MUST source the blocker summary from `conflict.conflicting_appointment` (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                "#conflict\.type === 'chair_conflict'#",
                $src
            ),
            sprintf(
                '%s MUST branch the blocker wording on `conflict.type` (chair vs professional) (T5 conflict toast).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#other\?\.patient\?\.full_name#',
                $src
            ),
            sprintf(
                '%s conflict toast MUST name the conflicting patient (`conflicting_appointment.patient.full_name`) (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#toast\.error\(conflictToastMessage\(conflict\)\)#',
                $src
            ),
            sprintf(
                '%s MUST surface the conflict via `toast.error(conflictToastMessage(conflict))` after rollback (T5).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                "#toast\.error\(normalizeError\(error, 'No se pudo reprogramar la cita'\)\)#",
                $src
            ),
            sprintf(
                '%s MUST fall back to the honest server message via `normalizeError` for validation/network failures (T5).',
                self::CALENDAR_PATH
            )
        );

        // CITAS-CONF-001 doubled for the new path: no client-side conflict oracle.
        foreach (['findConflicts', 'hasConflict'] as $heuristic) {
            $this->assertSame(
                0,
                preg_match('#\b' . $heuristic . '\b#', $src),
                sprintf(
                    '%s MUST NOT compute conflicts client-side (`%s`) — the 422 payload is the only conflict source (CITAS-CONF-001).',
                    self::CALENDAR_PATH,
                    $heuristic
                )
            );
        }
    }

    /**
     * T5 visual contract — drag visuals are Tailwind token classes only:
     * the drag class constants and the drop-target highlight carry no raw
     * colour literals (hex/rgb) and no hardcoded colour map; the reduced
     * motion path is a STATIC fallback (opacity + inset ring, no
     * transition/transform) selected through the shared `useReducedMotion`
     * composable, while the motion path uses the transition/transform
     * utilities. `dragDropMath.js` carries no presentation at all.
     */
    public function test_drag_visuals_token_driven_with_static_reduced_motion_fallback(): void
    {
        $src = self::calendarSource();
        $math = self::dragMathSource();

        $this->assertSame(
            1,
            preg_match('#import \{ useReducedMotion \} from#', $src),
            sprintf(
                '%s MUST consume the shared `useReducedMotion` composable for the drag visual (T5 reduced-motion rule).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match(
                '#return reducedMotion\.value \? DRAG_CHIP_REDUCED_MOTION_CLASSES : DRAG_CHIP_MOTION_CLASSES#',
                $src
            ),
            sprintf(
                '%s MUST select the reduced-motion drag visual through `reducedMotion.value` (T5).',
                self::CALENDAR_PATH
            )
        );

        // The reduced-motion classes are STATIC: opacity + ring only, no
        // transition/transform/animate.
        $reducedClasses = self::constantStringValue($src, 'DRAG_CHIP_REDUCED_MOTION_CLASSES');
        $this->assertNotFalse(
            $reducedClasses,
            sprintf('%s MUST define the `DRAG_CHIP_REDUCED_MOTION_CLASSES` constant (T5).', self::CALENDAR_PATH)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#transition|animate|-translate#',
            $reducedClasses,
            sprintf(
                '%s reduced-motion drag classes MUST be static (no transition/animate/translate) — opacity/outline feedback only (T5 reduced-motion rule).',
                self::CALENDAR_PATH
            )
        );
        $this->assertMatchesRegularExpression(
            '#ring-2 ring-inset ring-accent-500#',
            $reducedClasses,
            sprintf(
                '%s reduced-motion drag classes MUST use the token ring for the static outline (T5).',
                self::CALENDAR_PATH
            )
        );

        // The motion path uses the transition utilities, not keyframe flourishes.
        $motionClasses = self::constantStringValue($src, 'DRAG_CHIP_MOTION_CLASSES');
        $this->assertNotFalse(
            $motionClasses,
            sprintf('%s MUST define the `DRAG_CHIP_MOTION_CLASSES` constant (T5).', self::CALENDAR_PATH)
        );
        $this->assertMatchesRegularExpression(
            '#transition-\[transform,opacity\]#',
            $motionClasses,
            sprintf(
                '%s motion drag classes MUST transition only transform+opacity (lightweight drag visual, T5).',
                self::CALENDAR_PATH
            )
        );
        $this->assertDoesNotMatchRegularExpression(
            '#spring|bounce|animate-#',
            $motionClasses,
            sprintf(
                '%s motion drag classes MUST NOT carry spring/bounce/keyframe flourishes (T5 visual budget).',
                self::CALENDAR_PATH
            )
        );

        // No raw colour literals in any drag-related class constant.
        foreach ([
            'DRAG_CHIP_MOTION_CLASSES',
            'DRAG_CHIP_REDUCED_MOTION_CLASSES',
            'DRAG_CHIP_IDLE_CLASSES',
            'DROP_TARGET_HOVER_CLASSES',
        ] as $constant) {
            $value = self::constantStringValue($src, $constant);
            $this->assertNotFalse(
                $value,
                sprintf('%s MUST define `%s` (T5).', self::CALENDAR_PATH, $constant)
            );
            $this->assertDoesNotMatchRegularExpression(
                '#\#\\s*([0-9a-fA-F]{3,8})\\b|rgb\\(|rgba\\(|hsl\\(#',
                $value,
                sprintf(
                    '%s `%s` MUST use design-token utility classes only — no raw colour literals, no hardcoded colour map (T5).',
                    self::CALENDAR_PATH,
                    $constant
                )
            );
        }

        // The pure math module carries no presentation.
        $this->assertDoesNotMatchRegularExpression(
            '#bg-|text-|ring-|border-|\\#[0-9a-fA-F]{3,8}\\b#',
            $math,
            sprintf(
                '%s MUST stay presentation-free (pure drop math, T5).',
                self::DRAG_MATH_PATH
            )
        );
    }

    /**
     * Extract the single-quoted string literal assigned to a `const NAME =`
     * declaration (the drag visual contract's class constants). Returns the
     * literal contents (plain Tailwind class strings) or false when the
     * declaration is absent.
     */
    private static function constantStringValue(string $src, string $name): string|false
    {
        $matched = preg_match(
            "#const " . preg_quote($name, '#') . "\\s*=\\s*\\n?\\s*'([^']*)'#",
            $src,
            $m
        );

        if ($matched !== 1) {
            return false;
        }

        return $m[1];
    }
}
