<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Historias clínicas interface redesign — T4, "Historia general" surface.
 *
 * Scope: the three files that build the surface
 * (`MedicalRecordCard.vue`, `MedicalRecordDetail.vue`,
 * `MedicalRecordModal.vue`) and nothing else. The page shell is pinned by
 * {@see MedicalRecordsAppShellTest}; this test pins the surface itself
 * against the REAL clinical contract (decision D1) and the shared
 * dashboard/login vocabulary (decision D6).
 *
 * Surface rules asserted here:
 *
 *   - MRC-GEN-001  the three files exist, have no `<style scoped>` block
 *                  (DLR-R-021), no `border-theme` literal (DLR-R-002), no
 *                  legacy focus-ring alias (DLR-R-004), no `<h1>`, no
 *                  gradient and no emoji.
 *   - MRC-GEN-002  permissions ride `usePermissions().can` as an OBJECT of
 *                  computed refs — `can('<permission>')` is banned (D7).
 *   - MRC-GEN-003  confirmation rides `useConfirm()`, never the global
 *                  `confirm()` (D6).
 *   - MRC-GEN-004  no invented contract: `record.title`,
 *                  `record.primary_diagnosis`, `record.secondary_diagnoses`,
 *                  `record.treatment`, `record.description` are gone, and
 *                  the counts never come from `record.evolutions?.length` /
 *                  `record.attachments?.length` (the index does not load
 *                  those relations — it ships `_count`).
 *   - MRC-GEN-005  the card is a `UiCard variant="glass"` row over
 *                  `record_number`/`first_visit_date`/`is_active`/
 *                  `chief_complaint`/`diagnosis` + the two `_count`
 *                  readings, with `StatusBadge` for the state and the three
 *                  `@view`/`@edit`/`@delete` actions gated by permissions.
 *   - MRC-GEN-006  the detail is wrapped in `UiModal` (`size="lg"`,
 *                  `@close` emits `close`), reads the real field set (vital
 *                  signs as chips, antecedents, exam, diagnosis, plan,
 *                  notes), renders only non-empty sections behind a single
 *                  `UiSectionEmpty` hint, and keeps the `edit`/
 *                  `addEvolution`/`uploadAttachment` emits the shell hosts.
 *   - MRC-GEN-007  the modal is a `UiModal` + `form @submit.prevent` over
 *                  `StoreMedicalRecordRequest`: it declares the `record`,
 *                  `patient` and `is-edit` props the page passes, groups the
 *                  fields into the four sections, binds the four
 *                  `vital_signs` sub-fields, sends `patient_id` on create
 *                  only, POSTs/PUTs through `useMedicalRecords`, surfaces
 *                  `response.data.errors` per field and toasts on success.
 *
 * DOM behaviour lives in `tests/js/smoke/medical-records.test.js`; these are
 * the cheap source-anatomy gates.
 */
class MedicalRecordsGeneralSurfaceTest extends TestCase
{
    private const SURFACES = [
        'card' => '/resources/js/modules/medical-records/components/MedicalRecordCard.vue',
        'detail' => '/resources/js/modules/medical-records/components/MedicalRecordDetail.vue',
        'modal' => '/resources/js/modules/medical-records/components/MedicalRecordModal.vue',
    ];

    /** @return array<string, array{0: string}> */
    public static function surfaceProvider(): array
    {
        $root = dirname(__DIR__, 3);
        $cases = [];

        foreach (self::SURFACES as $name => $relative) {
            $cases[$name] = [$root.$relative];
        }

        return $cases;
    }

    private static function surface(string $key): string
    {
        $path = dirname(__DIR__, 3).self::SURFACES[$key];
        $src = is_file($path) ? file_get_contents($path) : false;

        return $src === false ? '' : $src;
    }

    private function cardSource(): string
    {
        $src = self::surface('card');
        $this->assertNotSame('', $src, 'MedicalRecordCard.vue must exist and be readable.');

        return $src;
    }

    private function detailSource(): string
    {
        $src = self::surface('detail');
        $this->assertNotSame('', $src, 'MedicalRecordDetail.vue must exist and be readable.');

        return $src;
    }

    private function modalSource(): string
    {
        $src = self::surface('modal');
        $this->assertNotSame('', $src, 'MedicalRecordModal.vue must exist and be readable.');

        return $src;
    }

    /**
     * MRC-GEN-001 — the shared surface recipe. Every rewritten file is pure
     * Tailwind + design tokens: no scoped style block, no legacy border
     * alias, no legacy focus ring, no page-level `<h1>`, no gradient and no
     * emoji.
     */
    #[DataProvider('surfaceProvider')]
    public function test_surface_stays_on_tailwind_and_tokens(string $path): void
    {
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression(
            '#<style\b#',
            $src,
            sprintf('%s must not carry a <style> block (DLR-R-021); use Tailwind utilities and tokens.', $path)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w-])border-theme(?![\w-])#',
            $src,
            sprintf('%s must not use the legacy `border-theme` literal (DLR-R-002).', $path)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w-])focus:ring-primary-500(?![\w-])|(?<![\w-])focus:border-accent(?![\w-])#',
            $src,
            sprintf('%s must not use legacy focus-ring aliases (DLR-R-004).', $path)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<h1\b#',
            $src,
            sprintf('%s must not render an <h1>; the app-shell topbar owns it.', $path)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?:bg-gradient-|linear-gradient\()#',
            $src,
            sprintf('%s must not use gradients (decision D6).', $path)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]#u',
            $src,
            sprintf('%s must not use emoji.', $path)
        );
    }

    /**
     * MRC-GEN-002 — `usePermissions().can` is an object of computed refs; a
     * function call throws at render time (decision D7).
     */
    #[DataProvider('surfaceProvider')]
    public function test_permissions_are_read_as_computed_refs(string $path): void
    {
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])can\s*\(#',
            $src,
            sprintf('%s must read `can.<permission>?.value`, never call `can(...)` (decision D7).', $path)
        );
    }

    /**
     * MRC-GEN-003 — confirmation goes through the shared dialog composable.
     */
    #[DataProvider('surfaceProvider')]
    public function test_confirmation_rides_the_shared_dialog(string $path): void
    {
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])confirm\s*\(#',
            $src,
            sprintf('%s must not call the global `confirm()`; use `useConfirm()` (decision D6).', $path)
        );
    }

    /**
     * MRC-GEN-004 — the repaired contract (decision D1). The pre-redesign
     * module read fields the backend never returns and counted relations the
     * index never loads.
     */
    #[DataProvider('surfaceProvider')]
    public function test_no_invented_fields_survive(string $path): void
    {
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $invented = [
            'record.title' => '#record\.title\b#',
            'record.primary_diagnosis' => '#primary_diagnosis#',
            'record.secondary_diagnoses' => '#secondary_diagnoses#',
            'record.treatment' => '#record\.treatment(?!_plan)#',
            'record.description' => '#record\.description\b#',
        ];

        foreach ($invented as $label => $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $src,
                sprintf('%s must not read the invented `%s` field (decision D1).', $path, $label)
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '#\.(?:evolutions|attachments)\?\.length#',
            $src,
            sprintf('%s must read `evolutions_count`/`attachments_count`; the index does not load the relation arrays (decision D1).', $path)
        );
    }

    /**
     * MRC-GEN-005 — the card anatomy: glass row, real fields, state badge,
     * tabular counts and the three permission-gated actions.
     */
    public function test_card_is_a_glass_row_over_the_real_record(): void
    {
        $src = $this->cardSource();

        $this->assertMatchesRegularExpression(
            '#<UiCard\b[\s\S]{0,120}?variant="glass"#',
            $src,
            'MedicalRecordCard.vue must render the shared `UiCard variant="glass"` surface (MRC-GEN-005).'
        );
        $this->assertStringContainsString(
            'var(--color-hairline)',
            $src,
            'The card edge must ride the hairline token (MRC-GEN-005).'
        );
        $this->assertStringContainsString(
            'var(--radius-panel)',
            $src,
            'The card must ride the panel radius (MRC-GEN-005).'
        );
        $this->assertStringContainsString(
            'var(--elevation-2)',
            $src,
            'The card must sit on the elevation-2 rung (MRC-GEN-005).'
        );

        foreach (['record_number', 'first_visit_date', 'is_active', 'chief_complaint', 'diagnosis'] as $field) {
            $this->assertMatchesRegularExpression(
                '#record\.'.preg_quote($field, '#').'\b#',
                $src,
                sprintf('The card must read the real `%s` field (MRC-GEN-005).', $field)
            );
        }

        $this->assertMatchesRegularExpression(
            '#record\.evolutions_count#',
            $src,
            'The evolution counter must read `record.evolutions_count` (MRC-GEN-005).'
        );
        $this->assertMatchesRegularExpression(
            '#record\.attachments_count#',
            $src,
            'The attachment counter must read `record.attachments_count` (MRC-GEN-005).'
        );

        $this->assertMatchesRegularExpression(
            '#<StatusBadge\b#',
            $src,
            'The Activa/Inactiva state must ride the `StatusBadge` primitive (MRC-GEN-005).'
        );
        $this->assertStringContainsString(
            'tabular-nums',
            $src,
            'The record number and counters must stay on tabular-nums (MRC-GEN-005).'
        );
        $this->assertStringContainsString(
            'useConfirm',
            $src,
            'The destructive action must confirm through `useConfirm()` (MRC-GEN-003).'
        );
        $this->assertMatchesRegularExpression(
            "#defineEmits\(\[ *'view', *'edit', *'delete' *\]\)#",
            $src,
            'The card must keep the `view`/`edit`/`delete` emit contract the shell listens to (MRC-GEN-005).'
        );

        foreach (['viewMedicalRecord', 'editMedicalRecord', 'deleteMedicalRecord'] as $permission) {
            $this->assertMatchesRegularExpression(
                '#can\.'.preg_quote($permission, '#').'\?\.value#',
                $src,
                sprintf('The card actions must be gated by `can.%s?.value` (MRC-GEN-002).', $permission)
            );
        }
    }

    /**
     * MRC-GEN-006 — the detail lives in the modal primitive, reads the real
     * clinical fields, renders only non-empty sections and keeps the emits.
     */
    public function test_detail_is_a_modal_over_the_real_clinical_fields(): void
    {
        $src = $this->detailSource();

        $this->assertStringContainsString(
            '<UiModal',
            $src,
            'MedicalRecordDetail.vue must be wrapped in `UiModal` (MRC-GEN-006).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiModal\b[\s\S]{0,200}?\bsize="lg"#',
            $src,
            'The detail modal must keep the `lg` size (MRC-GEN-006).'
        );
        $this->assertMatchesRegularExpression(
            "#@close=\"emit\('close'\)\"#",
            $src,
            'The detail must translate the modal `@close` into its own `close` emit (MRC-GEN-006).'
        );

        foreach ([
            'record.record_number',
            'record.first_visit_date',
            'record.is_active',
            'record.vital_signs',
            'record.chief_complaint',
        ] as $field) {
            $this->assertMatchesRegularExpression(
                '#'.preg_quote($field, '#').'\b#',
                $src,
                sprintf('The detail must read the real `%s` field (MRC-GEN-006).', $field)
            );
        }

        // The antecedent / clinical blocks are table-driven, so their real
        // field names appear as the declared keys rather than as
        // `record.<field>` member expressions.
        foreach ([
            'medical_history',
            'dental_history',
            'allergies',
            'medications',
            'systemic_conditions',
            'family_history',
            'social_history',
            'clinical_examination',
            'diagnosis',
            'treatment_plan',
            'notes',
        ] as $field) {
            $this->assertStringContainsString(
                sprintf("'%s'", $field),
                $src,
                sprintf('The detail must declare the real `%s` field (MRC-GEN-006).', $field)
            );
        }

        foreach (['blood_pressure', 'heart_rate', 'temperature', 'respiratory_rate'] as $sign) {
            $this->assertStringContainsString(
                $sign,
                $src,
                sprintf('The vital-signs chip grid must read `%s` (MRC-GEN-006).', $sign)
            );
        }

        $this->assertSame(
            1,
            substr_count($src, '<UiSectionEmpty'),
            'The detail must render a single empty hint when there is no clinical content (MRC-GEN-006).'
        );
        $this->assertStringContainsString(
            'hasClinicalContent',
            $src,
            'The empty hint must be gated by the computed clinical-content check (MRC-GEN-006).'
        );
        $this->assertMatchesRegularExpression(
            "#defineEmits\(\[ *'close', *'edit', *'addEvolution', *'uploadAttachment' *\]\)#",
            $src,
            'The detail must keep the `close`/`edit`/`addEvolution`/`uploadAttachment` emit contract (MRC-GEN-006).'
        );

        foreach (['editMedicalRecord', 'addEvolution', 'uploadAttachment'] as $permission) {
            $this->assertMatchesRegularExpression(
                '#can\.'.preg_quote($permission, '#').'\?\.value#',
                $src,
                sprintf('The detail actions must be gated by `can.%s?.value` (MRC-GEN-002).', $permission)
            );
        }
    }

    /**
     * MRC-GEN-007 — the create/edit form is built on
     * `StoreMedicalRecordRequest`, never on the retired field set.
     */
    public function test_modal_declares_the_shell_props_and_posts_the_real_contract(): void
    {
        $src = $this->modalSource();

        $this->assertStringContainsString(
            '<UiModal',
            $src,
            'MedicalRecordModal.vue must render through `UiModal` (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            '#<form\b[\s\S]{0,300}?@submit\.prevent="onSubmit"#',
            $src,
            'The modal must own a `<form @submit.prevent="onSubmit">` (MRC-GEN-007).'
        );

        $this->assertMatchesRegularExpression(
            '#record: \{\s*type: Object,\s*default: null#s',
            $src,
            'The modal must declare the nullable `record` prop the shell passes (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            '#patient: \{\s*type: Object,\s*default: null#s',
            $src,
            'The modal must declare the nullable `patient` prop the shell passes (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            '#isEdit: \{\s*type: Boolean,\s*default: false#s',
            $src,
            'The modal must declare the `isEdit` prop bound by `:is-edit` (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            "#defineEmits\(\[ *'close', *'saved' *\]\)#",
            $src,
            'The modal must keep the `close`/`saved` emit contract (MRC-GEN-007).'
        );

        foreach (['patient-date', 'history', 'examination', 'notes'] as $section) {
            $this->assertStringContainsString(
                sprintf('data-form-section="%s"', $section),
                $src,
                sprintf('The form must group its fields into the `%s` section (MRC-GEN-007).', $section)
            );
        }

        $fields = [
            'patient_id',
            'first_visit_date',
            'chief_complaint',
            'medical_history',
            'dental_history',
            'allergies',
            'medications',
            'systemic_conditions',
            'family_history',
            'social_history',
            'vital_signs',
            'clinical_examination',
            'diagnosis',
            'treatment_plan',
            'notes',
        ];

        foreach ($fields as $field) {
            $this->assertStringContainsString(
                $field,
                $src,
                sprintf('The form must post the real `%s` field (MRC-GEN-007).', $field)
            );
        }

        foreach (['blood_pressure', 'heart_rate', 'temperature', 'respiratory_rate'] as $sign) {
            $this->assertStringContainsString(
                $sign,
                $src,
                sprintf('The form must bind the `vital_signs.%s` sub-field (MRC-GEN-007).', $sign)
            );
        }

        $this->assertStringContainsString(
            '1000',
            $src,
            'The form must carry the 1000-character limit of the short clinical fields (MRC-GEN-007).'
        );
        $this->assertStringContainsString(
            '2000',
            $src,
            'The form must carry the 2000-character limit of the long clinical fields (MRC-GEN-007).'
        );

        $this->assertMatchesRegularExpression(
            '#if \(!props\.isEdit\)\s*\{[^}]*payload\.patient_id#s',
            $src,
            '`patient_id` belongs to the create payload only; PUT rejects it (MRC-GEN-007).'
        );

        $this->assertStringContainsString(
            'createRecord',
            $src,
            'The modal must create through `useMedicalRecords().createRecord` (MRC-GEN-007).'
        );
        $this->assertStringContainsString(
            'updateRecord',
            $src,
            'The modal must update through `useMedicalRecords().updateRecord` (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            '#response\?\.data\?\.errors#',
            $src,
            'The modal must surface the server field errors (`response.data.errors`) (MRC-GEN-007).'
        );
        $this->assertMatchesRegularExpression(
            '#toast\.success\(#',
            $src,
            'The modal must confirm a successful save with `useToast()` (MRC-GEN-007).'
        );
        $this->assertStringContainsString(
            '<UiInput',
            $src,
            'The modal must render its fields through `UiInput` (MRC-GEN-007).'
        );
        $this->assertStringContainsString(
            '<UiTextarea',
            $src,
            'The modal must render its long fields through `UiTextarea` (MRC-GEN-007).'
        );
    }
}
