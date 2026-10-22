<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * F3 (#292, S3/S5) — the read trail of a clinical history.
 *
 * Every surface that returns clinical content writes a `medical_record_viewed`
 * entry, so "who read it" is answerable from the same `audit_logs` table that
 * answers "who modified it". The auditable model is the history and the metadata
 * carries the surface that produced the read.
 *
 * This service is the single place where the action name and the metadata shape
 * live, and it does not swallow failures: a clinical read that cannot be
 * audited is not served.
 */
class ClinicalRecordAuditService
{
    public const ACTION_VIEWED = 'medical_record_viewed';

    /** History index: one entry per record returned on the page. */
    public const SURFACE_INDEX = 'index';

    /** Record detail: one entry for the record it returns. */
    public const SURFACE_RECORD = 'record';

    /** Evolution timeline: one entry for the history it belongs to. */
    public const SURFACE_EVOLUTIONS = 'evolutions';

    /** Patient-scoped attachment list: one entry per active record. */
    public const SURFACE_ATTACHMENTS = 'attachments';

    /** Patient-scoped statistics: one entry per active record. */
    public const SURFACE_STATS = 'stats';

    /**
     * One read for the record the caller opened.
     */
    public function recordViewed(MedicalRecord $record, string $surface): void
    {
        $this->write($record, $surface);
    }

    /**
     * One read per record returned, so the trail matches what the page showed.
     *
     * @param iterable<int, MedicalRecord> $records
     */
    public function recordsViewed(iterable $records, string $surface): void
    {
        foreach ($records as $record) {
            $this->write($record, $surface);
        }
    }

    /**
     * Patient-scoped surfaces expose the whole history, so every active record
     * of that patient gets its own entry.
     */
    public function patientRecordsViewed(int $patientId, string $surface): void
    {
        $records = MedicalRecord::where('patient_id', $patientId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $this->recordsViewed($records, $surface);
    }

    private function write(MedicalRecord $record, string $surface): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('No se puede auditar una lectura clínica sin un usuario autenticado.');
        }

        AuditLog::log($user, self::ACTION_VIEWED, $record, [], [], ['surface' => $surface]);
    }
}
