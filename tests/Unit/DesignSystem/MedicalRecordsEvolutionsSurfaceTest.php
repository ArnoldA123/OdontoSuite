<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Historias clínicas interface redesign — T5 «Evoluciones» surface contract.
 *
 * Scope: the two files that own the evolutions surface, and nothing else.
 *
 *   - `EvolutionTimeline.vue`  reads the real `clinical_evolutions` payload
 *                              (SOAP + procedures + materials + prescriptions
 *                              + recommendations + vital signs + follow-up)
 *                              and keeps the glass/rail language of the shell.
 *   - `EvolutionModal.vue`     writes only the columns the backend validates
 *                              (`StoreEvolutionRequest`), in create and edit
 *                              mode, with the follow-up date gated by the
 *                              `requires_follow_up` switch.
 *
 * The page shell (T3) is the consumer: `evolutions` / `loading` props and the
 * `add` / `edit` / `delete` emits on the timeline, `record` / `evolution` props
 * and the `close` / `saved` emits on the modal. Those are pinned here because a
 * silent rename would drop the surface out of the page with no render error.
 *
 * Rules asserted:
 *
 *   - EVO-R-001  no `<style scoped>` block on either surface (DLR-R-021).
 *   - EVO-R-002  permissions are read as computed refs (`can.editEvolution?.value`),
 *                never called as `can('...')` (decision D7).
 *   - EVO-R-003  delete is confirmed through `useConfirm()`, never a bare
 *                global `confirm()`.
 *   - EVO-R-004  no invented payload key reaches the API (decision D4): the
 *                modal writes the real SOAP columns and nothing else. The
 *                specialty moved from "inferred, never exposed" to a catalog
 *                selector in issue #225 (see EVO-R-013).
 *   - EVO-R-005  the timeline renders the four SOAP blocks from the real
 *                columns, plus the real clinical rows and vital signs.
 *   - EVO-R-006  loading uses `LoadingSpinner`, empty uses `UiSectionEmpty`
 *                with its motif and an `add` CTA (never a bare icon).
 *   - EVO-R-007  the modal declares `evolution` (nullable) and routes the
 *                write to `updateEvolution` in edit mode, `addEvolution` in
 *                create mode.
 *   - EVO-R-008  `follow_up_date` is disabled and cleared while
 *                `requires_follow_up` is off.
 *   - EVO-R-009  the form is built from `UiModal` + `@submit.prevent` +
 *                `UiInput` (date/number) + `UiTextarea`, with the 422 errors
 *                read from `err.response.data.errors` and a `useToast`
 *                confirmation on success.
 *   - EVO-R-010  neither surface owns the page `<h1>` and neither paints a
 *                gradient (the shell keeps the single page title).
 *   - EVO-R-011  the modal captures the `clinical_measurements` contract
 *                (issue #222): five unit-bearing keys with their own hooks.
 *   - EVO-R-012  the timeline renders every recorded measurement, units
 *                included, and keeps a `false` bleeding finding.
 *   - EVO-R-013  the specialty is editable from the `specialties` catalog
 *                (issue #225), never as a free string, and the timeline labels
 *                the stored code with the catalog name.
 *
 * DOM behavior lives in `tests/js/smoke/medical-records.test.js`; this file
 * pins the contract statically so a regression fails without a browser.
 */
class MedicalRecordsEvolutionsSurfaceTest extends TestCase
{
    private const TIMELINE_PATH = '/resources/js/modules/medical-records/components/EvolutionTimeline.vue';

    private const MODAL_PATH = '/resources/js/modules/medical-records/components/EvolutionModal.vue';

    /**
     * Keys the old form sent and the backend never validated (D4). None of
     * them may come back: a rebuilt form on the real contract cannot contain
     * them even as a label or a comment.
     */
    private const INVENTED_KEYS = [
        'evolution_type',
        'professional_name',
        'consultation_reason',
        'observations',
        'treatment_performed',
        'prescribed_medications',
    ];

    /** SOAP blocks the timeline must render, one hook each. */
    private const SOAP_BLOCKS = ['subjective', 'objective', 'assessment', 'plan'];

    /** Free-text rows the backend stores and the timeline must expose. */
    private const CLINICAL_ROWS = [
        'procedures_performed',
        'materials_used',
        'prescriptions',
        'recommendations',
        'next_appointment_notes',
    ];

    /** `clinical_measurements` contract from issue #222 (units in the name). */
    private const CLINICAL_MEASUREMENTS = [
        'pocket_depth_mm',
        'gingival_recession_mm',
        'plaque_index_percent',
        'bleeding_on_probing',
        'tooth_mobility',
    ];

    /** @return array<string, array{0: string}> */
    public static function surfaceProvider(): array
    {
        return [
            'timeline' => [self::TIMELINE_PATH],
            'modal' => [self::MODAL_PATH],
        ];
    }

    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).$relativePath;
        $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));

        $source = file_get_contents($path);
        $this->assertIsString($source, sprintf('%s must be readable.', $relativePath));

        return $source;
    }

    private function timeline(): string
    {
        return $this->source(self::TIMELINE_PATH);
    }

    private function modal(): string
    {
        return $this->source(self::MODAL_PATH);
    }

    /**
     * The `<template>` region: everything before the `<script>` block. Scoping
     * a ban to it keeps a script constant from satisfying (or tripping) a
     * markup rule by accident.
     */
    private function templateOf(string $source, string $label): string
    {
        $scriptStart = strpos($source, '<script');
        $this->assertNotFalse($scriptStart, sprintf('%s must declare a <script> block.', $label));

        return substr($source, 0, (int) $scriptStart);
    }

    private function assertAbsentPattern(string $pattern, string $source, string $message): void
    {
        $this->assertDoesNotMatchRegularExpression($pattern, $source, $message);
    }

    /**
     * EVO-R-001 — the redesign retires the bespoke stylesheet: the surface
     * must ride the shared tokens and primitives instead.
     */
    #[DataProvider('surfaceProvider')]
    public function test_surfaces_carry_no_scoped_style_block(string $path): void
    {
        $source = $this->source($path);

        $this->assertStringNotContainsString(
            '<style scoped>',
            $source,
            sprintf('%s must not declare a `<style scoped>` block (EVO-R-001 / DLR-R-021).', $path)
        );
        $this->assertStringNotContainsString(
            '<style',
            $source,
            sprintf('%s must not declare a <style> block; tokens and primitives own the look.', $path)
        );
    }

    /**
     * EVO-R-002 — D7: `usePermissions().can` is an object of computed refs.
     * Calling it as a function threw on render.
     */
    public function test_timeline_reads_permissions_as_computed_refs(): void
    {
        $source = $this->timeline();

        $this->assertAbsentPattern(
            '/(?<![\w.])can\s*\(/',
            $source,
            'EvolutionTimeline.vue must read `can.*?.value`, never call `can(...)` (EVO-R-002 / D7).'
        );
        $this->assertStringContainsString('const { can } = usePermissions()', $source);
        $this->assertStringContainsString('can.editEvolution?.value', $source);
        $this->assertStringContainsString('can.deleteEvolution?.value', $source);
    }

    /**
     * EVO-R-003 — the destructive action goes through the shared dialog, the
     * same one `PatientsPage` uses.
     */
    public function test_timeline_confirms_deletion_through_the_shared_dialog(): void
    {
        $source = $this->timeline();

        $this->assertStringContainsString("import { useConfirm } from '@/composables/useConfirm'", $source);
        $this->assertStringContainsString('const { confirm } = useConfirm()', $source);
        $this->assertStringContainsString('await confirm({', $source);
        $this->assertStringNotContainsString('window.confirm(', $source);

        // A bare global `confirm(` (no import binding) must not come back: the
        // only legitimate spellings left are `useConfirm()` and `await confirm(`.
        $withoutBindings = str_replace(
            ['useConfirm()', 'await confirm('],
            ['', ''],
            $source
        );
        $this->assertAbsentPattern(
            '/(?<![\w.])confirm\s*\(/',
            $withoutBindings,
            'EvolutionTimeline.vue must not call a bare `confirm(...)` (EVO-R-003).'
        );
    }

    /**
     * EVO-R-004 — D4: only validated columns travel to the API. The excluded
     * keys were discarded by the backend and the form showed invented data.
     */
    #[DataProvider('surfaceProvider')]
    public function test_surfaces_do_not_resurrect_invented_payload_keys(string $path): void
    {
        $source = $this->source($path);

        foreach (self::INVENTED_KEYS as $key) {
            $this->assertAbsentPattern(
                '/(?<![\w-])'.preg_quote($key, '/').'(?![\w-])/',
                $source,
                sprintf('%s must not reference the invented key `%s` (EVO-R-004).', $path, $key)
            );
        }
    }

    /**
     * EVO-R-013 — issue #225: the specialty is editable, but only from the
     * catalog the page loads (`GET /api/specialties/active`). The form omits
     * the key when it has no value so an unrelated edit preserves the stored
     * code, and the timeline labels that code with the catalog name.
     */
    public function test_modal_exposes_the_specialty_catalog_selector(): void
    {
        $source = $this->modal();
        $template = $this->templateOf($source, 'EvolutionModal.vue');

        $this->assertStringContainsString('data-evolution-field="specialty"', $template);
        $this->assertStringContainsString('form.specialty', $template);
        $this->assertStringContainsString(':options="specialtyOptions"', $template);
        $this->assertMatchesRegularExpression(
            '/specialties:\s*\{[^}]*type:\s*Array[^}]*\}/s',
            $source,
            'The modal declares the `specialties` prop the page passes (EVO-R-013).'
        );
        $this->assertStringContainsString('specialtyOptions', $source);
        $this->assertStringContainsString('payload.specialty = specialty', $source);
        $this->assertStringContainsString('isCatalogValue', $source);

        $page = $this->source('/resources/js/modules/medical-records/MedicalRecordsPage.vue');
        $this->assertStringContainsString('getSpecialties()', $page);
        $this->assertStringContainsString(':specialties="specialties"', $page);
    }

    /**
     * EVO-R-013 — the timeline shows the catalog name and keeps the raw code
     * for historical values the catalog no longer has.
     */
    public function test_timeline_labels_the_specialty_from_the_catalog(): void
    {
        $source = $this->timeline();

        $this->assertStringContainsString('specialtyLabel(evolution)', $source);
        $this->assertStringContainsString('props.specialties.find', $source);
        $this->assertStringContainsString("'Sin especialidad'", $source);
        $this->assertStringContainsString('data-evolution-specialty', $source);
    }

    /**
     * EVO-R-005 — the four SOAP blocks, each rendered from its real column and
     * each with its own hook so "only non-empty" stays observable.
     */
    public function test_timeline_renders_the_four_soap_blocks(): void
    {
        $source = $this->timeline();

        $this->assertStringContainsString('data-evolution-card', $source);
        $this->assertStringContainsString('data-evolution-date', $source);
        $this->assertStringContainsString('data-evolution-author', $source);

        foreach (self::SOAP_BLOCKS as $block) {
            $this->assertStringContainsString(
                sprintf('v-if="evolution.%s"', $block),
                $source,
                sprintf('The timeline must render `%s` only when it has content (EVO-R-005).', $block)
            );
            $this->assertStringContainsString(
                sprintf('data-soap-block="%s"', $block),
                $source,
                sprintf('The `%s` SOAP block needs its own hook (EVO-R-005).', $block)
            );
            $this->assertStringContainsString(
                sprintf('{{ evolution.%s }}', $block),
                $source,
                sprintf('The timeline must print the `%s` column, not a derived label (EVO-R-005).', $block)
            );
        }
    }

    /**
     * EVO-R-005 — the clinical rows and the vital signs chip grid read the
     * real columns, and the next-appointment notes keep their muted block.
     */
    public function test_timeline_renders_the_real_clinical_rows(): void
    {
        $source = $this->timeline();

        foreach (self::CLINICAL_ROWS as $row) {
            $this->assertStringContainsString(
                sprintf('evolution.%s', $row),
                $source,
                sprintf('The timeline must read `%s` (EVO-R-005).', $row)
            );
        }

        $this->assertStringContainsString('evolution.vital_signs', $source);
        $this->assertStringContainsString('data-evolution-vitals', $source);
        $this->assertStringContainsString('data-evolution-notes', $source);
        $this->assertStringContainsString('requires_follow_up', $source);

        foreach (['blood_pressure', 'heart_rate', 'temperature', 'respiratory_rate'] as $sign) {
            $this->assertStringContainsString(
                $sign,
                $source,
                sprintf('The vital-signs grid must cover `%s` (EVO-R-005).', $sign)
            );
        }
    }

    /**
     * EVO-R-006 — the three states: spinner while loading, shared empty state
     * with motif + CTA when there is nothing, one glass card per evolution.
     */
    public function test_timeline_states_use_the_shared_primitives(): void
    {
        $source = $this->timeline();

        $this->assertStringContainsString('<LoadingSpinner', $source);
        $this->assertStringContainsString('<UiSectionEmpty', $source);
        $this->assertStringContainsString('data-state="empty-evolutions"', $source);
        $this->assertStringContainsString('motif', $source);
        $this->assertStringContainsString('<UiCard variant="glass"', $source);
        $this->assertStringContainsString('var(--color-hairline)', $source);
        $this->assertStringContainsString('<StatusBadge', $source);

        $emptyStart = strpos($source, '<UiSectionEmpty');
        $emptyEnd = strpos($source, '</UiSectionEmpty>', (int) $emptyStart);
        $this->assertNotFalse($emptyStart, 'EvolutionTimeline.vue must declare the empty state.');
        $this->assertNotFalse($emptyEnd, 'The empty state must be closed.');

        $emptyBlock = substr($source, (int) $emptyStart, (int) $emptyEnd - (int) $emptyStart);
        $this->assertStringContainsString(
            "emit('add')",
            $emptyBlock,
            'The empty state must offer the add CTA instead of a dead icon (EVO-R-006).'
        );
    }

    /**
     * EVO-R-007 — the page shell contract and the edit-mode wiring.
     */
    public function test_modal_declares_the_create_and_edit_contract(): void
    {
        $source = $this->modal();

        $this->assertMatchesRegularExpression(
            '/evolutions:\s*\{[^}]*type:\s*Array[^}]*default:\s*\(\)\s*=>\s*\[\][^}]*\}/s',
            $this->timeline(),
            'The timeline keeps the `evolutions` prop the page passes (EVO-R-007).'
        );
        $this->assertMatchesRegularExpression(
            '/loading:\s*\{[^}]*type:\s*Boolean[^}]*\}/s',
            $this->timeline(),
            'The timeline keeps the `loading` prop the page passes (EVO-R-007).'
        );
        $this->assertStringContainsString(
            "defineEmits(['add', 'edit', 'delete'])",
            $this->timeline(),
            'The timeline keeps the add/edit/delete emits the page listens to (EVO-R-007).'
        );

        $this->assertMatchesRegularExpression(
            '/record:\s*\{[^}]*type:\s*Object[^}]*required:\s*true[^}]*\}/s',
            $source,
            'The modal keeps the required `record` prop (EVO-R-007).'
        );
        $this->assertMatchesRegularExpression(
            '/evolution:\s*\{[^}]*type:\s*Object[^}]*default:\s*null[^}]*\}/s',
            $source,
            'The modal declares the nullable `evolution` prop that selects edit mode (EVO-R-007).'
        );
        $this->assertStringContainsString(
            "defineEmits(['close', 'saved'])",
            $source,
            'The modal keeps the close/saved emits the page listens to (EVO-R-007).'
        );

        $this->assertStringContainsString('updateEvolution(props.evolution.id', $source);
        $this->assertStringContainsString('addEvolution(props.record.id', $source);
    }

    /**
     * EVO-R-008 — the follow-up date exists only alongside the switch, and it
     * is cleared when the switch goes off.
     */
    public function test_modal_gates_the_follow_up_date_behind_requires_follow_up(): void
    {
        $source = $this->modal();

        $this->assertStringContainsString(
            ':disabled="!form.requires_follow_up"',
            $source,
            'The follow-up date must stay disabled while `requires_follow_up` is off (EVO-R-008).'
        );
        $this->assertStringContainsString('data-evolution-follow-up-toggle', $source);
        $this->assertStringContainsString('type="checkbox"', $source);
        $this->assertStringContainsString(
            "form.value.follow_up_date = ''",
            $source,
            'Turning the switch off must clear the date instead of sending a stale one (EVO-R-008).'
        );
        $this->assertStringContainsString('follow_up_date', $source);
    }

    /**
     * EVO-R-009 — shared modal chrome and shared form primitives.
     */
    public function test_modal_builds_on_shared_form_primitives(): void
    {
        $source = $this->modal();
        $template = $this->templateOf($source, 'EvolutionModal.vue');

        $this->assertStringContainsString('<UiModal', $template);
        $this->assertStringContainsString('data-evolution-form', $template);
        $this->assertStringContainsString('@submit.prevent="submitEvolution"', $template);
        $this->assertStringContainsString('<UiInput', $template);
        $this->assertStringContainsString('<UiTextarea', $template);
        $this->assertStringContainsString('type="date"', $template);
        $this->assertStringContainsString('type="number"', $template);
        $this->assertStringContainsString(':max="today"', $template);
        $this->assertStringContainsString('data-evolution-section="vital-signs"', $template);

        // Native inputs the redesign retires: the date/time pickers and hand
        // built textareas are the primitive's job now.
        $this->assertAbsentPattern('/<textarea\b/', $template, 'Raw <textarea> is out; use UiTextarea.');
        $this->assertAbsentPattern('/<select\b/', $template, 'Raw <select> is out; use a primitive.');
        $this->assertStringNotContainsString('type="datetime-local"', $template);

        // Vital signs mirror the column types: blood pressure is a string
        // (`120/80`), the other three are numeric measurements.
        $this->assertStringContainsString('data-evolution-field="blood_pressure"', $template);
        $vitalSignsStart = strpos($template, 'data-evolution-section="vital-signs"');
        $this->assertNotFalse($vitalSignsStart, 'The vital-signs fieldset must exist (EVO-R-009).');
        $vitalSignsBlock = substr(
            $template,
            (int) $vitalSignsStart,
            (int) strpos($template, '</fieldset>', (int) $vitalSignsStart) - (int) $vitalSignsStart
        );
        $this->assertSame(
            3,
            substr_count($vitalSignsBlock, 'type="number"'),
            'Heart rate, temperature and respiratory rate are numeric inputs (EVO-R-009).'
        );
    }

    /**
     * EVO-R-011 — the modal captures the `clinical_measurements` contract
     * (issue #222): five unit-bearing keys, each with its own field hook, and
     * the tri-state bleeding selector instead of an ambiguous checkbox.
     */
    public function test_modal_captures_the_clinical_measurements_contract(): void
    {
        $source = $this->modal();
        $template = $this->templateOf($source, 'EvolutionModal.vue');

        $this->assertStringContainsString('data-evolution-section="clinical-measurements"', $template);

        foreach (self::CLINICAL_MEASUREMENTS as $key) {
            $this->assertStringContainsString(
                sprintf('data-evolution-field="%s"', $key),
                $template,
                sprintf('The modal must capture `clinical_measurements.%s` (EVO-R-011).', $key)
            );
            $this->assertStringContainsString(
                sprintf('form.clinical_measurements.%s', $key),
                $template,
                sprintf('The `%s` field must be bound to the form model (EVO-R-011).', $key)
            );
        }

        $this->assertStringContainsString('clinical_measurements: buildClinicalMeasurements()', $source);
        $this->assertStringContainsString('bleedingOptions', $source);
        $this->assertStringContainsString('<UiSelect', $template);
    }

    /**
     * EVO-R-012 — the timeline renders every recorded measurement with units,
     * including the `false` bleeding finding (not recorded is a different
     * result from "no bleeding").
     */
    public function test_timeline_renders_the_clinical_measurements(): void
    {
        $source = $this->timeline();
        $template = $this->templateOf($source, 'EvolutionTimeline.vue');

        $this->assertStringContainsString('data-evolution-measurements', $template);
        $this->assertStringContainsString('clinicalMeasurements(evolution)', $template);

        foreach (self::CLINICAL_MEASUREMENTS as $key) {
            $this->assertStringContainsString(
                sprintf("'%s'", $key),
                $source,
                sprintf('The timeline must map `%s` to a labelled chip (EVO-R-012).', $key)
            );
        }

        $this->assertStringContainsString("label: 'Sondaje'", $source);
        $this->assertStringContainsString("label: 'Recesión'", $source);
        $this->assertStringContainsString("label: 'Índice de placa'", $source);
        $this->assertStringContainsString("suffix: ' mm'", $source);
        $this->assertStringContainsString("suffix: ' %'", $source);
        $this->assertStringContainsString("value ? 'Sí' : 'No'", $source);
    }

    /**
     * EVO-R-009 — 422 field errors and the success toast.
     */
    public function test_modal_surfaces_field_errors_and_the_success_toast(): void
    {
        $source = $this->modal();

        $this->assertStringContainsString("import { useToast } from '@/composables/useToast'", $source);
        $this->assertStringContainsString('err?.response?.data?.errors', $source);
        $this->assertStringContainsString('fieldError(', $source);
        $this->assertStringContainsString('toast.success(', $source);
        $this->assertStringContainsString('toast.error(', $source);
    }

    /**
     * EVO-R-010 — the page shell owns the `<h1>`, and the surface paints no
     * gradient (flat tokens only).
     */
    #[DataProvider('surfaceProvider')]
    public function test_surfaces_avoid_page_headings_and_gradients(string $path): void
    {
        $source = $this->source($path);

        $this->assertAbsentPattern('/<h1[\s>]/', $source, sprintf('%s must not own the page <h1> (EVO-R-010).', $path));
        $this->assertAbsentPattern('/gradient/', $source, sprintf('%s must not paint a gradient (EVO-R-010).', $path));
    }
}
