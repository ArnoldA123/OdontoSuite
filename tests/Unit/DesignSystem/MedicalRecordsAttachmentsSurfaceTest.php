<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Historias clínicas interface redesign — T6 attachments surface contract.
 *
 * Scope: the two files that own the «Adjuntos» surface.
 *
 *   - resources/js/modules/medical-records/components/AttachmentGallery.vue
 *   - resources/js/modules/medical-records/components/UploadAttachmentModal.vue
 *
 * The module used to read and write fields the backend does not have
 * (`attachment.filename`, `attachment.url`, `addAttachment`, `file_date`,
 * `notes`) and called both `can('...')` and the global `confirm()` as if they
 * were bare functions. Those are runtime `TypeError`s / silent failed uploads,
 * so the contract is pinned here before T8 wires the files into the inherited
 * ModuleAppShellTestCase rules (D1/D5/D7 of the feature document).
 *
 * Real contract: ClinicalAttachment exposes `original_name`, `file_path`,
 * `file_type` (`image|pdf|document|other`), `mime_type`, `file_size_human`,
 * `category` (`radiografia|foto_clinica|documento|otro`, legacy `general`) and
 * `clinical_evolution_id`. The public URL is `/storage/${file_path}`.
 *
 * Rules:
 *
 *   - MRA-001  no `<style scoped>` block on either surface (S1/D6).
 *   - MRA-002  no `can(...)` call — `usePermissions().can` is an object of
 *              computed refs; every guard is `can.<permission>?.value` (D7).
 *   - MRA-003  no native `confirm()`: the gallery consumes `useConfirm()` and
 *              awaits its result before emitting the delete (D6).
 *   - MRA-004  no invented fields: `filename` and `attachment.url` are gone;
 *              the URL is built from `file_path` and the image guard rides on
 *              `mime_type`.
 *   - MRA-005  the gallery filters by `category`, not by `file_type`.
 *   - MRA-006  the modal posts the real multipart contract through
 *              `uploadAttachment` (`patient_id`, `category`, `description`,
 *              `is_private`) and never `addAttachment`/`file_date`/`notes`.
 *   - MRA-007  the modal declares the required `patient` prop and enforces the
 *              10 MB client-side limit with its own message.
 *   - MRA-008  the public wiring the page shell consumes is preserved:
 *              gallery `attachments`/`loading` props + `upload`/`delete`
 *              emits; modal `close`/`uploaded` emits.
 *   - MRA-009  the dashboard vocabulary is consumed: `UiCard variant="glass"`,
 *              `StatusBadge`, `UiSectionEmpty`, `LoadingSpinner`, `UiModal`,
 *              `UiSelect`, with no `<h1>` and no gradients.
 */
class MedicalRecordsAttachmentsSurfaceTest extends TestCase
{
    private const GALLERY_PATH = '/resources/js/modules/medical-records/components/AttachmentGallery.vue';

    private const UPLOAD_MODAL_PATH = '/resources/js/modules/medical-records/components/UploadAttachmentModal.vue';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function source(string $relativePath): string
    {
        $path = self::projectRoot().$relativePath;
        self::assertFileExists($path, sprintf('%s must exist.', $relativePath));

        $src = file_get_contents($path);
        self::assertNotFalse($src, sprintf('%s must be readable.', $relativePath));

        return (string) $src;
    }

    private static function gallery(): string
    {
        return self::source(self::GALLERY_PATH);
    }

    private static function uploadModal(): string
    {
        return self::source(self::UPLOAD_MODAL_PATH);
    }

    public function test_gallery_has_no_scoped_style_block(): void
    {
        $this->assertStringNotContainsString(
            '<style scoped>',
            self::gallery(),
            'AttachmentGallery.vue must express its styling with utilities/tokens (S1/D6): no `<style scoped>` block.'
        );
        $this->assertStringNotContainsString('<style', self::gallery(), 'AttachmentGallery.vue must not ship any style block.');
    }

    public function test_upload_modal_has_no_scoped_style_block(): void
    {
        $this->assertStringNotContainsString(
            '<style scoped>',
            self::uploadModal(),
            'UploadAttachmentModal.vue must express its styling with utilities/tokens (S1/D6): no `<style scoped>` block.'
        );
        $this->assertStringNotContainsString('<style', self::uploadModal(), 'UploadAttachmentModal.vue must not ship any style block.');
    }

    public function test_gallery_never_calls_permissions_as_a_function(): void
    {
        $src = self::gallery();

        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])can\s*\(#',
            $src,
            '`usePermissions().can` is an object of computed refs; calling it throws (D7).'
        );
        $this->assertStringContainsString('can.uploadAttachment?.value', $src, 'The upload affordance must read `can.uploadAttachment?.value` (D7).');
        $this->assertStringContainsString('can.deleteAttachment?.value', $src, 'The delete action must read `can.deleteAttachment?.value` (D7).');
    }

    public function test_gallery_routes_deletion_through_use_confirm(): void
    {
        $src = self::gallery();

        $this->assertStringNotContainsString('window.confirm', $src, 'The gallery must not call the native confirm (D6).');
        $this->assertStringContainsString('useConfirm', $src, 'The gallery must import the promise-based confirm composable (D6).');

        preg_match_all('/(?<![\w.$])confirm\s*\(/', $src, $matches, PREG_OFFSET_CAPTURE);

        $this->assertNotEmpty(
            $matches[0],
            'The gallery must expose the delete confirmation; otherwise this rule passes vacuously.'
        );

        foreach ($matches[0] as [$call, $offset]) {
            $before = substr($src, 0, $offset);
            $tail = substr($before, max(0, strlen($before) - 120));

            $this->assertMatchesRegularExpression(
                '/\b(\w+)\s*=\s*await\s*$/',
                $tail,
                'The confirm() call site must be an awaited consumption (`const ok = await confirm({...})`); an unawaited promise is always truthy.'
            );

            preg_match('/\b(\w+)\s*=\s*await\s*$/', $tail, $assigned);
            $resultVar = $assigned[1];

            $this->assertMatchesRegularExpression(
                '/\bif\s*\(\s*'.$resultVar.'\s*\)/',
                substr($src, $offset),
                'The awaited confirm result must gate the delete emission.'
            );
        }

        $this->assertStringContainsString(
            "emit('delete', attachment)",
            $src,
            'The gallery confirms and emits `delete`; the page shell owns the write and its toast.'
        );
    }

    public function test_gallery_does_not_read_invented_attachment_fields(): void
    {
        $src = self::gallery();

        $this->assertStringNotContainsString(
            'filename',
            $src,
            'ClinicalAttachment has no `filename`; the display name is `original_name` (D5).'
        );
        $this->assertStringNotContainsString(
            'attachment.url',
            $src,
            'ClinicalAttachment has no `url`; the public URL is /storage/${file_path} (D5).'
        );
        $this->assertStringContainsString('attachment.original_name', $src, 'The stored display name is `original_name` (D5).');
        $this->assertStringContainsString('attachment.file_size_human', $src, 'The formatted size is the `file_size_human` accessor (D5).');
    }

    public function test_gallery_builds_the_public_storage_url_from_file_path(): void
    {
        $src = self::gallery();

        $this->assertStringContainsString(
            '/storage/${attachment.file_path}',
            $src,
            'The download/preview URL must be built as `/storage/${file_path}`, like the AI analysis module does (D5).'
        );
        $this->assertStringContainsString('attachment.file_path', $src, 'The URL source is `file_path` (D5).');
        $this->assertStringContainsString('const isImage = attachment =>', $src, 'The image guard must be a named helper (D5).');
        $this->assertStringContainsString('mime_type', $src, 'The image guard must read `mime_type`, not `filename` (D5).');
        $this->assertStringContainsString(
            "startsWith('image/')",
            $src,
            'The image guard is `mime_type.startsWith(\'image/\')` (D5).'
        );
    }

    public function test_gallery_filters_the_loaded_list_by_category(): void
    {
        $src = self::gallery();

        $this->assertStringContainsString('categoryFilter', $src, 'The filter state must be a category filter (D5).');
        $this->assertStringContainsString(
            'attachment.category === categoryFilter.value',
            $src,
            'The filter must compare `category`, never `file_type` (D5).'
        );
        $this->assertStringNotContainsString('selectedCategory', $src, 'The old `file_type` filter state must be gone.');
        $this->assertStringNotContainsString('file_type ===', $src, '`file_type` is a rendering concept, not a filter (D5).');

        foreach (['radiografia', 'foto_clinica', 'documento', 'otro', 'general'] as $category) {
            $this->assertStringContainsString(
                $category,
                $src,
                sprintf('The category map must cover `%s` (D5).', $category)
            );
        }

        $this->assertStringContainsString("label: 'Todas'", $src, 'The filter must offer «Todas» plus the four real categories.');
        $this->assertStringContainsString('categoryLabel', $src, 'Categories render with a human label (D5).');
        $this->assertStringContainsString('categoryVariant', $src, 'Categories render through the StatusBadge variant map (D6).');

        foreach (['Radiografía', 'Foto clínica', 'Documento', 'Otro', 'General'] as $label) {
            $this->assertStringContainsString($label, $src, sprintf('The human category label `%s` must exist (D5).', $label));
        }
    }

    public function test_upload_modal_posts_the_real_attachment_contract(): void
    {
        $src = self::uploadModal();

        $this->assertStringContainsString('uploadAttachment', $src, 'The modal must upload through the data layer (`uploadAttachment`), never `addAttachment` (D1/D5).');
        $this->assertStringNotContainsString('addAttachment(', $src, '`addAttachment` is not exported by the composable (D1).');
        $this->assertStringContainsString('new FormData()', $src, 'The upload is multipart (D5).');
        $this->assertStringContainsString("payload.append('patient_id', props.patient.id)", $src, 'The payload posts the required `patient_id` (D5).');
        $this->assertStringContainsString("payload.append('category', form.value.category)", $src, 'The payload posts the required `category` (D5).');
        $this->assertStringContainsString("payload.append('description', form.value.description)", $src, 'The payload posts `description` (D5).');
        $this->assertStringContainsString("payload.append('is_private'", $src, 'The private checkbox is wired to `is_private` (D5).');
        $this->assertStringNotContainsString('file_date', $src, '`file_date` is not part of the upload contract (D5).');
        $this->assertStringNotContainsString('notes', $src, '`notes` is not part of the upload contract (D5).');
    }

    public function test_upload_modal_declares_the_patient_prop(): void
    {
        $src = self::uploadModal();

        $this->assertMatchesRegularExpression(
            '/patient:\s*\{/',
            $src,
            'The modal declares the `patient` prop the page shell passes.'
        );
        $this->assertStringContainsString('required: true', $src, 'The `patient` prop is required (page shell contract).');
        $this->assertStringContainsString('props.patient.id', $src, 'The upload targets the declared patient id.');
    }

    public function test_upload_modal_enforces_the_ten_megabyte_limit(): void
    {
        $src = self::uploadModal();

        $this->assertStringContainsString('10 * 1024 * 1024', $src, 'The client-side guard is the API limit: 10 MB / 10240 KB (D5).');
        $this->assertStringContainsString(
            'El archivo supera el límite de 10 MB',
            $src,
            'The over-limit message must be specific about the 10 MB ceiling.'
        );
        $this->assertStringContainsString('accept="image/*,.pdf,.doc,.docx"', $src, 'The picker accepts images, PDF and Word documents.');
        $this->assertStringContainsString('@submit.prevent', $src, 'The form submits through its own handler (no page reload).');
    }

    public function test_upload_modal_reports_success_and_field_errors(): void
    {
        $src = self::uploadModal();

        $this->assertStringContainsString('useToast', $src, 'The success path uses the toast composable (D6).');
        $this->assertStringContainsString('toast.success', $src, 'A successful upload reports through `toast.success`.');
        $this->assertStringContainsString("emit('uploaded')", $src, 'The success path emits the page shell contract (`uploaded`).');
        $this->assertStringContainsString('data-upload-error="file"', $src, 'The file field renders its API/client error.');
        $this->assertStringContainsString('data-upload-error="category"', $src, 'The category field renders its API/client error.');
        $this->assertStringContainsString('data-upload-error="description"', $src, 'The description field renders its API error.');
    }

    public function test_surfaces_keep_the_page_shell_wiring(): void
    {
        $gallery = self::gallery();
        $modal = self::uploadModal();

        $this->assertStringContainsString('attachments: {', $gallery, 'The gallery keeps its `attachments` prop.');
        $this->assertStringContainsString('loading: {', $gallery, 'The gallery keeps its `loading` prop.');
        $this->assertStringContainsString("defineEmits(['upload', 'delete'])", $gallery, 'The gallery keeps the `upload`/`delete` emits the page shell binds.');
        $this->assertStringContainsString("defineEmits(['close', 'uploaded'])", $modal, 'The modal keeps the `close`/`uploaded` emits the page shell binds.');

        $this->assertStringContainsString("emit('upload')", $gallery, 'The upload CTA emits `upload`.');
        $this->assertStringContainsString("emit('close')", $modal, 'Cancel/backdrop emit `close`.');
    }

    public function test_gallery_renders_the_dashboard_primitives(): void
    {
        $src = self::gallery();

        foreach (['UiCard', 'StatusBadge', 'UiSectionEmpty', 'LoadingSpinner', 'UiModal', 'UiSelect'] as $primitive) {
            $this->assertStringContainsString(
                $primitive,
                $src,
                sprintf('The gallery must consume the shared `%s` primitive (D6).', $primitive)
            );
        }

        $this->assertStringContainsString('variant="glass"', $src, 'The attachment tiles are `UiCard variant="glass"` (D6).');
        $this->assertStringContainsString('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', $src, 'The tiles sit in a responsive grid (D6).');
        $this->assertStringContainsString('data-state="empty-attachments"', $src, 'The empty state uses `UiSectionEmpty` with an upload CTA (D6).');
        $this->assertStringContainsString('data-attachment-preview', $src, 'Image previews open through `UiModal` (D5/D6).');
        $this->assertStringContainsString('data-download-attachment', $src, 'Every tile exposes a download link (D5).');
        $this->assertStringContainsString('data-delete-attachment', $src, 'The delete action keeps its hook (D6).');
    }

    public function test_surfaces_avoid_headings_and_gradients(): void
    {
        foreach ([self::GALLERY_PATH => self::gallery(), self::UPLOAD_MODAL_PATH => self::uploadModal()] as $path => $src) {
            $this->assertStringNotContainsString('<h1', $src, sprintf('%s must not declare an <h1>: the app-shell topbar owns it (D6).', basename($path)));
            $this->assertStringNotContainsString('bg-gradient', $src, sprintf('%s must not use gradients (D6).', basename($path)));
            $this->assertStringNotContainsString('linear-gradient', $src, sprintf('%s must not use gradients (D6).', basename($path)));
        }
    }
}
