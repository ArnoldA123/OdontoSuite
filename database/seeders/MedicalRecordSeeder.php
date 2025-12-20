<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;

class MedicalRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = Patient::limit(10)->get();
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->limit(3)->get();

        if ($patients->isEmpty() || $users->isEmpty()) {
            $this->command->info('No hay suficientes datos para crear registros médicos.');
            return;
        }

        foreach ($patients as $index => $patient) {
            MedicalRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $users->first()->id,
                'record_number' => 'MR-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'first_visit_date' => now()->subDays(rand(1, 365)),
                'chief_complaint' => 'Dolor dental',
                'medical_history' => 'Sin antecedentes médicos relevantes',
                'dental_history' => 'Primera consulta',
                'allergies' => 'Ninguna conocida',
                'medications' => 'Ninguna',
                'systemic_conditions' => 'Ninguna',
                'family_history' => 'Sin antecedentes familiares relevantes',
                'social_history' => 'Paciente activo',
                'vital_signs' => [
                    'blood_pressure' => '120/80',
                    'heart_rate' => 72,
                    'temperature' => 36.5
                ],
                'clinical_examination' => 'Examen clínico normal',
                'diagnosis' => 'Caries dental',
                'treatment_plan' => 'Tratamiento restaurador',
                'notes' => 'Paciente colaborador',
                'is_active' => true
            ]);
        }

        $this->command->info('Registros médicos creados exitosamente.');
    }
}
