<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

/**
 * Guard for #265: the clinical attachment lifecycle has exactly one owner.
 *
 * #244 fixed the live deletion path (`MedicalRecordService::deleteAttachment`)
 * while a second, uncalled copy lived in `ClinicalAttachmentService`. A fix
 * applied to the dead copy passed every test and changed nothing, so the
 * duplication is the defect: the next reader looking for "where is an
 * attachment deleted" must find one answer.
 *
 * The guard fails when a second implementation appears — a method that both
 * removes a file from the `public` disk and marks the attachment inactive — or
 * when the unused operations return to the service that does not own them.
 */
class ClinicalAttachmentLifecycleGuardTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return string[] */
    private static function phpFilesUnder(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_only_medical_record_service_owns_the_attachment_deletion(): void
    {
        $owners = [];
        foreach (self::phpFilesUnder(self::root().'/app') as $path) {
            $source = (string) file_get_contents($path);
            $deletesBinary = str_contains($source, "Storage::disk('public')->delete(");
            $archivesRow = str_contains($source, "'is_active' => false");

            if ($deletesBinary && $archivesRow) {
                $owners[] = substr($path, strlen(self::root()) + 1);
            }
        }

        $this->assertSame(
            ['app/Services/MedicalRecordService.php'],
            $owners,
            'Attachment deletion must live in exactly one place: a second copy of the '
                .'delete-a-file-and-mark-it-inactive flow is the defect #265 reports.'
        );
    }

    public function test_clinical_attachment_service_keeps_only_the_creation_path(): void
    {
        $source = (string) file_get_contents(
            self::root().'/app/Services/ClinicalAttachmentService.php'
        );

        foreach (['store', 'getFileType', 'generateMetadata'] as $kept) {
            $this->assertMatchesRegularExpression(
                '/function\s+'.$kept.'\s*\(/',
                $source,
                "ClinicalAttachmentService::{$kept}() is the only operation with a caller; "
                    .'it must stay.'
            );
        }

        foreach (['delete', 'update', 'getAttachmentUrl'] as $removed) {
            $this->assertSame(
                0,
                preg_match('/function\s+'.$removed.'\s*\(/', $source),
                "ClinicalAttachmentService::{$removed}() has no caller and duplicates an "
                    .'operation MedicalRecordService owns; it must not come back.'
            );
        }
    }

    public function test_the_live_endpoint_still_delegates_to_the_single_owner(): void
    {
        $source = (string) file_get_contents(
            self::root().'/app/Http/Controllers/Api/MedicalRecordController.php'
        );

        $this->assertStringContainsString(
            '$this->medicalRecordService->deleteAttachment($attachmentId);',
            $source,
            'The delete endpoint must keep delegating to MedicalRecordService::deleteAttachment().'
        );
    }
}
