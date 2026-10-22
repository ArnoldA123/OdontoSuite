<?php

namespace Tests\Unit\DesignSystem;

/**
 * T7 — CitasCalendarDetailsModalTest.
 *
 * Structural contract for the appointment details surface rework: the
 * hand-rolled `fixed inset-0 bg-black bg-opacity-50` backdrop + inline close
 * SVG are gone and the surface renders through `<UiModal>` (the same chrome
 * every other modal in the app consumes), plus the two pre-existing bug
 * fixes discovered in T4/T5:
 *
 *   1) BUG-FIX-1 — `editAppointment()` stores the selection but the form was
 *      never bound to it: `NewAppointmentModal` already implements edit mode
 *      around its `appointment` prop (`isEditMode` → PUT + "Editar Cita"
 *      title), and CalendarPage now passes `:appointment="editingAppointment"`.
 *      Before T7 the "Editar" action opened an EMPTY create form.
 *
 *   2) BUG-FIX-2 — the "Editar" button gated on `can.updateAppointment?.value`,
 *      a permission key that does NOT exist in usePermissions (the real key
 *      is `editAppointment`, see resources/js/composables/usePermissions.js
 *      "Gestión de citas"). `undefined?.value` is falsy, so the button NEVER
 *      rendered for any role. The gate is now `can.editAppointment?.value`.
 *
 * Anchored on the `data-calendar-*` hooks and the real handlers they mark,
 * never on class literals:
 *
 *   - chrome — `<UiModal>` from `components/ui/Modal.vue` owns backdrop,
 *     Escape and focus trap: no hand-built Teleport, no legacy
 *     `bg-black bg-opacity-50` backdrop, no inline `<svg>` in the details
 *     slice (UiModal renders its own close control);
 *   - content — `data-calendar-detail` hook, summary fields from the real
 *     AppointmentResource payload (patient / professional / chair / date +
 *     tnum time range / registry status pill / notes) with optional
 *     relations guarded by `?.`;
 *   - actions — one `data-calendar-action` hook per action:
 *     `edit`, `delete`, `status-confirmed`, `status-cancelled`, `check-in`,
 *     `announce`, `start-attention`, `complete-consultation`, each wired to
 *     its existing handler on the SAME button tag, permission gates verbatim
 *     (custody split, #281: `check-in` marks the arrival, `announce` notifies
 *     the professional, `start-attention` hands the act over to the clinic);
 *   - behaviour pins — `selectAppointment` → selection, `changeStatus` →
 *     PATCH `/status`, `deleteAppointment` → `useConfirm` before `del(...)`,
 *     and the consultation wizard entry points stay untouched.
 *
 * The behaviour itself is exercised end-to-end by the smoke suite
 * (tests/js/smoke/appointments.test.js — "T7 details modal smoke").
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because path
 * patterns contain forward slashes.
 */
class CitasCalendarDetailsModalTest extends ModuleAppShellTestCase
{
    /** Calendar page path constant — single source of truth for the absolute path. */
    private const CALENDAR_PATH = '/resources/js/modules/appointments/CalendarPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::CALENDAR_PATH,
        ];
    }

    private static function calendarSource(): string
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = is_file($path) ? file_get_contents($path) : false;
        self::assertNotFalse($src, sprintf('%s must be readable.', self::CALENDAR_PATH));

        return $src;
    }

    /**
     * Extract the details-modal template region (from its marker comment to
     * the NewAppointmentModal comment that follows it). Scoping keeps the
     * details rules details rules: a hook in the Day/Week/Month grids must
     * not mask a details regression, and vice versa.
     */
    private static function detailsSlice(string $src): string
    {
        $start = strpos($src, '<!-- Appointment Details Modal -->');
        $end = strpos($src, '<!-- New Appointment Modal -->');
        self::assertNotFalse($start, sprintf('%s MUST keep the `<!-- Appointment Details Modal -->` template marker.', self::CALENDAR_PATH));
        self::assertNotFalse($end, sprintf('%s MUST keep the `<!-- New Appointment Modal -->` template marker.', self::CALENDAR_PATH));
        self::assertGreaterThan($start, $end, 'Details slice must precede the NewAppointmentModal usage.');

        return substr($src, $start, $end - $start);
    }

    /**
     * Return the full opening-tag attributes of the single button carrying
     * the given `data-calendar-action` hook, so gate + handler wiring can be
     * asserted ON THE SAME TAG (a hook on one button and a handler on
     * another must not satisfy the rule).
     */
    private static function actionTagAttributes(string $src, string $hook): string
    {
        $pattern = '#<UiButton\b(?=[^>]*data-calendar-action="'.preg_quote($hook, '#').'")([^>]*)>#s';
        $count = preg_match($pattern, $src, $matches);
        self::assertSame(
            1,
            $count,
            sprintf(
                '%s MUST declare exactly one `<UiButton data-calendar-action="%s">` (T7 action-hook contract).',
                self::CALENDAR_PATH,
                $hook
            )
        );

        return $matches[1];
    }

    /**
     * T7 chrome contract — the details surface renders through `<UiModal>`
     * (title-bound, model-value-driven like NewAppointmentModal) and the
     * legacy hand-rolled backdrop chrome is gone file-wide. The details
     * slice carries no inline `<svg>`: the close control belongs to UiModal
     * and page icons are heroicon components.
     */
    public function test_details_surface_renders_through_ui_modal(): void
    {
        $src = self::calendarSource();
        $slice = self::detailsSlice($src);

        // 1) UiModal primitive is consumed with the selection as model value.
        $this->assertSame(
            1,
            preg_match('#<UiModal\b#', $slice),
            sprintf('%s details surface MUST render through `<UiModal>` (T7 / CITAS-MOD-001 grammar).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:model-value="!!selectedAppointment"#', $slice),
            sprintf('%s details `<UiModal>` MUST bind `:model-value="!!selectedAppointment"` (T7: UiModal owns open/close).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#title="Detalles de la Cita"#', $slice),
            sprintf('%s details `<UiModal>` MUST keep the "Detalles de la Cita" title (behaviour contract, asserted by the smoke suite).', self::CALENDAR_PATH)
        );
        $this->assertTrue(
            (bool) preg_match('#import\s+UiModal\s+from\s+[\'"][^\'"]*components/ui/Modal\.vue[\'"]#', $src),
            sprintf('%s MUST import UiModal from the shared `components/ui/Modal.vue` (T7).', self::CALENDAR_PATH)
        );

        // 2) hand-rolled backdrop chrome is gone file-wide.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])#', $src),
            sprintf('%s MUST NOT keep the legacy `bg-black bg-opacity-50` backdrop literal (T7: UiModal owns the backdrop).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            0,
            preg_match('#<Teleport\s+to\s*=\s*["\']body["\']#', $src),
            sprintf('%s MUST NOT hand-roll a `<Teleport to="body">` modal (T7).', self::CALENDAR_PATH)
        );

        // 3) no inline SVG inside the details surface (heroicon components only).
        $this->assertSame(
            0,
            preg_match('#<svg#', $slice),
            sprintf('%s details slice MUST NOT carry inline `<svg>` markup (T7: UiModal owns the close control; page icons are heroicons).', self::CALENDAR_PATH)
        );
    }

    /**
     * T7 content contract — the details body is exposed behind the
     * `data-calendar-detail` hook and renders the summary from REAL
     * AppointmentResource fields only: patient / professional / guarded
     * optional relations (type, chair) / date + tnum time range / registry
     * status pill / guarded notes. The status pill consumes the
     * appointmentStatus registry helpers, never a hardcoded label.
     */
    public function test_details_content_fields_and_registry_pill(): void
    {
        $src = self::calendarSource();
        $slice = self::detailsSlice($src);

        $this->assertSame(
            1,
            preg_match('#data-calendar-detail#', $slice),
            sprintf('%s details surface MUST expose the `data-calendar-detail` hook (T7; consumed by the smoke suite).', self::CALENDAR_PATH)
        );

        // Real AppointmentResource payload fields.
        foreach ([
            'selectedAppointment.patient?.first_name' => 'patient',
            'selectedAppointment.user?.name' => 'professional',
            'selectedAppointment.dental_chair?.name' => 'chair/room',
            'appointmentTimeRange(selectedAppointment)' => 'time range',
            'selectedAppointment.duration_minutes' => 'duration',
            'selectedAppointment.notes' => 'notes',
            'selectedAppointment.treatment_notes' => 'treatment notes',
        ] as $needle => $label) {
            $this->assertStringContainsString(
                $needle,
                $slice,
                sprintf('%s details slice MUST render the %s from the real AppointmentResource payload (`%s`) (T7).', self::CALENDAR_PATH, $label, $needle)
            );
        }

        // Time formatting stays tabular (tnum) like the grid chips.
        $this->assertStringContainsString(
            "font-feature-settings: 'tnum'",
            $slice,
            sprintf('%s details date/time MUST render with `font-feature-settings: \'tnum\'` (T7, same grammar as the chips).', self::CALENDAR_PATH)
        );

        // Status pill comes from the appointmentStatus registry.
        $this->assertStringContainsString(
            'getStatusBadgeProps(selectedAppointment.status)',
            $slice,
            sprintf('%s details status pill MUST derive its variant/dot from `getStatusBadgeProps(...)` (D2 registry, never a literal).', self::CALENDAR_PATH)
        );
        $this->assertStringContainsString(
            'getStatusText(selectedAppointment.status)',
            $slice,
            sprintf('%s details status pill MUST take its label from `getStatusText(...)` (D2 registry).', self::CALENDAR_PATH)
        );
        $this->assertStringContainsString(
            ':data-status="selectedAppointment.status"',
            $slice,
            sprintf('%s details status pill MUST bind `:data-status` for the data hooks (T7).', self::CALENDAR_PATH)
        );
    }

    /**
     * T7 action contract — every action carries its `data-calendar-action`
     * hook AND keeps its permission gate / handler on the SAME button tag.
     *
     * BUG-FIX-2 pinned here: the edit gate MUST be `can.editAppointment`
     * (the real usePermissions key) and the retired `can.updateAppointment`
     * key MUST be absent file-wide — that key never existed, which is why
     * "Editar" never rendered for any role before T7.
     */
    public function test_details_action_hooks_gates_and_handlers(): void
    {
        $src = self::calendarSource();
        $slice = self::detailsSlice($src);

        // BUG-FIX-2 anti-rule: the non-existent permission key is gone.
        $this->assertSame(
            0,
            preg_match('#can\.updateAppointment#', $src),
            sprintf(
                '%s MUST NOT reference `can.updateAppointment` — the key does not exist in usePermissions '
                .'(real key: `editAppointment`); the optional chain made "Editar" silently unreachable (T7 bugfix).',
                self::CALENDAR_PATH
            )
        );

        // Edit: gate + handler + hook on the same tag.
        $edit = self::actionTagAttributes($slice, 'edit');
        $this->assertStringContainsString('v-if="can.editAppointment?.value"', $edit, sprintf('%s "Editar" MUST gate on `can.editAppointment?.value` (T7 bugfix 2).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="editAppointment(selectedAppointment)"', $edit, sprintf('%s "Editar" MUST stay wired to `editAppointment(selectedAppointment)` (T7).', self::CALENDAR_PATH));

        // Delete: gate + handler + hook on the same tag.
        $delete = self::actionTagAttributes($slice, 'delete');
        $this->assertStringContainsString('v-if="can.deleteAppointment?.value"', $delete, sprintf('%s "Eliminar" MUST keep the `can.deleteAppointment` gate (T7).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="deleteAppointment(selectedAppointment)"', $delete, sprintf('%s "Eliminar" MUST stay wired to `deleteAppointment(selectedAppointment)` (T7).', self::CALENDAR_PATH));

        // Status actions: hooks + existing changeStatus wiring untouched.
        $confirmed = self::actionTagAttributes($slice, 'status-confirmed');
        $this->assertStringContainsString('@click="changeStatus(selectedAppointment, \'confirmed\')"', $confirmed, sprintf('%s "Confirmar" MUST call `changeStatus(..., \'confirmed\')` (behaviour contract preserved).', self::CALENDAR_PATH));

        $cancelled = self::actionTagAttributes($slice, 'status-cancelled');
        $this->assertStringContainsString('@click="changeStatus(selectedAppointment, \'cancelled\')"', $cancelled, sprintf('%s "Cancelar Cita" MUST call `changeStatus(..., \'cancelled\')` (behaviour contract preserved).', self::CALENDAR_PATH));

        // Custody actions (#281): arrival, announcement and attention start
        // are three separate doors, each with its own permission gate.
        $checkIn = self::actionTagAttributes($slice, 'check-in');
        $this->assertStringContainsString('can.checkInAppointment?.value', $checkIn, sprintf('%s "Marcar llegada" MUST gate on `can.checkInAppointment?.value` (S1).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="markArrival(selectedAppointment)"', $checkIn, sprintf('%s "Marcar llegada" MUST call `markArrival(selectedAppointment)` — POST /appointments/{id}/check-in (S1).', self::CALENDAR_PATH));

        $announce = self::actionTagAttributes($slice, 'announce');
        $this->assertStringContainsString('can.announcePatient?.value', $announce, sprintf('%s "Anunciar paciente" MUST gate on `can.announcePatient?.value` (S2).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="announcePatient(selectedAppointment)"', $announce, sprintf('%s "Anunciar paciente" MUST call `announcePatient(selectedAppointment)` — POST /appointments/{id}/announce (S2).', self::CALENDAR_PATH));

        $startAttention = self::actionTagAttributes($slice, 'start-attention');
        $this->assertStringContainsString('can.startConsultation?.value', $startAttention, sprintf('%s "Iniciar atención" MUST gate on `can.startConsultation?.value` (clinical roles only, S5).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="startAttention(selectedAppointment)"', $startAttention, sprintf('%s "Iniciar atención" MUST call `startAttention(selectedAppointment)` — POST /appointments/{id}/start-attention (S5).', self::CALENDAR_PATH));

        $complete = self::actionTagAttributes($slice, 'complete-consultation');
        $this->assertStringContainsString('@click="openConsultationWizard(selectedAppointment)"', $complete, sprintf('%s "Completar Consulta" MUST stay wired to `openConsultationWizard(selectedAppointment)` (T7: wizard entry untouched).', self::CALENDAR_PATH));
    }

    /**
     * BUG-FIX-1 — the selected appointment is bound into
     * NewAppointmentModal through the `appointment` prop the modal already
     * implements edit mode around (`isEditMode` → PUT `/appointments/{id}`,
     * title "Editar Cita"). Without this binding "Editar" opened an empty
     * create form. The `initialDate` prefill wiring (T4) is preserved.
     */
    public function test_edit_action_binds_appointment_into_new_appointment_modal(): void
    {
        $src = self::calendarSource();

        $this->assertSame(
            1,
            preg_match('#:appointment\s*=\s*"editingAppointment"#', $src),
            sprintf(
                '%s `<NewAppointmentModal>` MUST receive `:appointment="editingAppointment"` '
                .'— the modal implements edit mode around that prop (T7 bugfix 1: "Editar" used to open an empty form).',
                self::CALENDAR_PATH
            )
        );

        $this->assertSame(
            1,
            preg_match('#:initial-date\s*=\s*"newAppointmentPrefill"#', $src),
            sprintf('%s `<NewAppointmentModal>` MUST keep the T4 `:initial-date` prefill wiring.', self::CALENDAR_PATH)
        );

        // editAppointment() still stores the selection before opening the form.
        $this->assertSame(
            1,
            preg_match('#const editAppointment = appointment => \{\s*editingAppointment\.value = appointment#', $src),
            sprintf('%s `editAppointment` MUST store the selection in `editingAppointment` (T7: this is what the modal is now bound to).', self::CALENDAR_PATH)
        );
    }

    /**
     * T7 behaviour contract — opening via `selectAppointment`, status changes
     * through the dedicated PATCH `/status` route, delete through
     * `useConfirm` BEFORE the API call + reload. These are the pre-existing
     * behaviours the rework must not change (exercised end-to-end by the
     * smoke suite; pinned structurally here).
     */
    public function test_details_behaviour_contract_preserved(): void
    {
        $src = self::calendarSource();

        // selectAppointment → selection (chips wire it in all three grids,
        // count pinned by CitasCalendarAppShellTest).
        $this->assertSame(
            1,
            preg_match('#const selectAppointment = appointment => \{[\s\S]*?selectedAppointment\.value = appointment#', $src),
            sprintf('%s `selectAppointment` MUST keep storing the clicked appointment in `selectedAppointment` (T7 behaviour contract).', self::CALENDAR_PATH)
        );

        // changeStatus → dedicated PATCH /status route (slice 01 / API-018 fix).
        $this->assertSame(
            1,
            preg_match('#patch\(`/api/appointments/\$\{appointment\.id\}/status`#', $src),
            sprintf('%s `changeStatus` MUST keep calling the dedicated PATCH `/status` route (API-018 behaviour preserved under T7).', self::CALENDAR_PATH)
        );

        // deleteAppointment → useConfirm first, then del + reload.
        $this->assertSame(
            1,
            preg_match('#const deleteAppointment = async appointment => \{\s*const ok = await confirm\(\{#', $src),
            sprintf('%s `deleteAppointment` MUST await `useConfirm` BEFORE any API call (T2 wiring preserved under T7).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#await del\(`/api/appointments/\$\{appointment\.id\}`\)#', $src),
            sprintf('%s `deleteAppointment` MUST delete through `del(/api/appointments/{id})` after the confirm (T7).', self::CALENDAR_PATH)
        );

        // The details close funnel exists (backdrop/Escape/close button all
        // route through clearing the selection).
        $this->assertSame(
            1,
            preg_match('#const closeDetails = \(\) => \{\s*selectedAppointment\.value = null\s*\}#', $src),
            sprintf('%s MUST expose `closeDetails` clearing the selection (T7: single close funnel for the UiModal paths).', self::CALENDAR_PATH)
        );
    }
}
