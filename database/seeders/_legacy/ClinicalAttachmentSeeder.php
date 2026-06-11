<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ClinicalAttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = Patient::limit(10)->get();
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->get();

        if ($patients->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No hay suficientes datos para crear adjuntos clínicos.');
            return;
        }

        $attachments = [];

        foreach ($patients as $patient) {
            // Crear 2-3 adjuntos por paciente
            $count = fake()->numberBetween(2, 3);

            for ($i = 0; $i < $count; $i++) {
                $category = fake()->randomElement(['radiografia', 'foto_clinica', 'documento']);
                $fileType = $category === 'radiografia' ? 'image' : fake()->randomElement(['image', 'document']);

                $attachments[] = [
                    'patient_id' => $patient->id,
                    'appointment_id' => null,
                    'clinical_evolution_id' => null,
                    'created_by' => $users->random()->id,
                    'file_name' => fake()->uuid() . '.jpg',
                    'original_name' => fake()->words(2, true) . '.jpg',
                    'file_path' => 'clinical-attachments/' . fake()->uuid() . '.jpg',
                    'file_type' => $fileType,
                    'mime_type' => 'image/jpeg',
                    'file_size' => fake()->numberBetween(500000, 5000000), // 500KB - 5MB
                    'category' => $category,
                    'description' => fake()->optional(0.8)->sentence(),
                    'metadata' => json_encode([
                        'width' => fake()->numberBetween(800, 2000),
                        'height' => fake()->numberBetween(600, 1500),
                        'camera' => fake()->optional(0.6)->company() . ' ' . fake()->word()
                    ]),
                    'is_private' => fake()->boolean(20), // 20% privados
                    'is_active' => true,
                    'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
                    'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ];
            }
        }

        // Insertar adjuntos
        ClinicalAttachment::insert($attachments);

        $this->command->info('Adjuntos clínicos creados exitosamente: ' . count($attachments));
    }
}
