<?php

namespace App\Services;

use App\Models\ImplantologyRecord;
use App\Models\OrthodonticsRecord;
use App\Models\EndodonticsRecord;
use App\Models\RehabilitationRecord;
use App\Models\OralSurgeryRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SpecialtyRecordService
{
    /**
     * Crear registro de implantología
     */
    public function createImplantologyRecord(array $data): ImplantologyRecord
    {
        $data['created_by'] = Auth::id();
        $data['placement_date'] = $data['placement_date'] ?? now()->toDateString();

        return ImplantologyRecord::create($data);
    }

    /**
     * Crear registro de ortodoncia
     */
    public function createOrthodonticsRecord(array $data): OrthodonticsRecord
    {
        $data['created_by'] = Auth::id();
        $data['treatment_start_date'] = $data['treatment_start_date'] ?? now()->toDateString();

        return OrthodonticsRecord::create($data);
    }

    /**
     * Crear registro de endodoncia
     */
    public function createEndodonticsRecord(array $data): EndodonticsRecord
    {
        $data['created_by'] = Auth::id();

        return EndodonticsRecord::create($data);
    }

    /**
     * Crear registro de rehabilitación
     */
    public function createRehabilitationRecord(array $data): RehabilitationRecord
    {
        $data['created_by'] = Auth::id();

        return RehabilitationRecord::create($data);
    }

    /**
     * Crear registro de cirugía oral
     */
    public function createOralSurgeryRecord(array $data): OralSurgeryRecord
    {
        $data['created_by'] = Auth::id();
        $data['surgery_date'] = $data['surgery_date'] ?? now()->toDateString();

        return OralSurgeryRecord::create($data);
    }

    /**
     * Obtener registros por especialidad
     */
    public function getSpecialtyRecords(int $patientId, string $specialty)
    {
        return match ($specialty) {
            'implantologia' => $this->getImplantologyRecords($patientId),
            'ortodoncia' => $this->getOrthodonticsRecords($patientId),
            'endodoncia' => $this->getEndodonticsRecords($patientId),
            'rehabilitacion' => $this->getRehabilitationRecords($patientId),
            'cirugia_oral' => $this->getOralSurgeryRecords($patientId),
            default => collect()
        };
    }

    /**
     * Obtener registros de implantología
     */
    private function getImplantologyRecords(int $patientId)
    {
        return ImplantologyRecord::with(['patient', 'appointment', 'dentalPiece', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('placement_date', 'desc')
            ->get();
    }

    /**
     * Obtener registros de ortodoncia
     */
    private function getOrthodonticsRecords(int $patientId)
    {
        return OrthodonticsRecord::with(['patient', 'appointment', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('treatment_start_date', 'desc')
            ->get();
    }

    /**
     * Obtener registros de endodoncia
     */
    private function getEndodonticsRecords(int $patientId)
    {
        return EndodonticsRecord::with(['patient', 'appointment', 'dentalPiece', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener registros de rehabilitación
     */
    private function getRehabilitationRecords(int $patientId)
    {
        return RehabilitationRecord::with(['patient', 'appointment', 'dentalPiece', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener registros de cirugía oral
     */
    private function getOralSurgeryRecords(int $patientId)
    {
        return OralSurgeryRecord::with(['patient', 'appointment', 'dentalPiece', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('surgery_date', 'desc')
            ->get();
    }

    /**
     * Actualizar registro de especialidad
     */
    public function updateRecord(string $specialty, int $id, array $data)
    {
        return match ($specialty) {
            'implantologia' => $this->updateImplantologyRecord($id, $data),
            'ortodoncia' => $this->updateOrthodonticsRecord($id, $data),
            'endodoncia' => $this->updateEndodonticsRecord($id, $data),
            'rehabilitacion' => $this->updateRehabilitationRecord($id, $data),
            'cirugia_oral' => $this->updateOralSurgeryRecord($id, $data),
            default => throw new \InvalidArgumentException("Especialidad no válida: {$specialty}")
        };
    }

    /**
     * Actualizar registro de implantología
     */
    private function updateImplantologyRecord(int $id, array $data): ImplantologyRecord
    {
        $record = ImplantologyRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['patient', 'appointment', 'dentalPiece', 'createdBy']);
    }

    /**
     * Actualizar registro de ortodoncia
     */
    private function updateOrthodonticsRecord(int $id, array $data): OrthodonticsRecord
    {
        $record = OrthodonticsRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['patient', 'appointment', 'createdBy']);
    }

    /**
     * Actualizar registro de endodoncia
     */
    private function updateEndodonticsRecord(int $id, array $data): EndodonticsRecord
    {
        $record = EndodonticsRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['patient', 'appointment', 'dentalPiece', 'createdBy']);
    }

    /**
     * Actualizar registro de rehabilitación
     */
    private function updateRehabilitationRecord(int $id, array $data): RehabilitationRecord
    {
        $record = RehabilitationRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['patient', 'appointment', 'dentalPiece', 'createdBy']);
    }

    /**
     * Actualizar registro de cirugía oral
     */
    private function updateOralSurgeryRecord(int $id, array $data): OralSurgeryRecord
    {
        $record = OralSurgeryRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['patient', 'appointment', 'dentalPiece', 'createdBy']);
    }

    /**
     * Eliminar registro de especialidad
     */
    public function deleteRecord(string $specialty, int $id): bool
    {
        return match ($specialty) {
            'implantologia' => ImplantologyRecord::findOrFail($id)->delete(),
            'ortodoncia' => OrthodonticsRecord::findOrFail($id)->delete(),
            'endodoncia' => EndodonticsRecord::findOrFail($id)->delete(),
            'rehabilitacion' => RehabilitationRecord::findOrFail($id)->delete(),
            'cirugia_oral' => OralSurgeryRecord::findOrFail($id)->delete(),
            default => throw new \InvalidArgumentException("Especialidad no válida: {$specialty}")
        };
    }

    /**
     * Obtener estadísticas por especialidad
     */
    public function getSpecialtyStats(int $patientId, string $specialty): array
    {
        $records = $this->getSpecialtyRecords($patientId, $specialty);

        return [
            'total_records' => $records->count(),
            'active_treatments' => $records->where('status', 'in_progress')->count(),
            'completed_treatments' => $records->where('status', 'completed')->count(),
            'last_record' => $records->first(),
            'treatment_types' => $records->pluck('treatment_type')->unique()->values()->toArray()
        ];
    }

    /**
     * Obtener todos los registros de especialidades de un paciente
     */
    public function getAllPatientSpecialtyRecords(int $patientId): array
    {
        return [
            'implantologia' => $this->getImplantologyRecords($patientId),
            'ortodoncia' => $this->getOrthodonticsRecords($patientId),
            'endodoncia' => $this->getEndodonticsRecords($patientId),
            'rehabilitacion' => $this->getRehabilitationRecords($patientId),
            'cirugia_oral' => $this->getOralSurgeryRecords($patientId)
        ];
    }
}
