<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['code' => 'general', 'name' => 'Odontología general', 'description' => 'Atención integral, preventiva y diagnóstico general.'],
            ['code' => 'rehabilitacion', 'name' => 'Rehabilitación oral', 'description' => 'Restauraciones, coronas, puentes y prótesis.'],
            ['code' => 'endodoncia', 'name' => 'Endodoncia', 'description' => 'Tratamientos de conducto y retratamientos.'],
            ['code' => 'cirugia_oral', 'name' => 'Cirugía oral', 'description' => 'Exodoncias, apicectomías y drenaje de abscesos.'],
            ['code' => 'implantologia', 'name' => 'Implantología', 'description' => 'Colocación de implantes, injertos y carga inmediata.'],
            ['code' => 'ortodoncia', 'name' => 'Ortodoncia', 'description' => 'Brackets, alineadores y retención.'],
            ['code' => 'estetica', 'name' => 'Estética dental', 'description' => 'Blanqueamiento, carillas y diseño de sonrisa.'],
            ['code' => 'periodoncia', 'name' => 'Periodoncia', 'description' => 'Enfermedad periodontal y cirugía de encías.'],
            ['code' => 'multidisciplinario', 'name' => 'Multidisciplinario', 'description' => 'Procedimientos transversales a varias especialidades.'],
        ];

        foreach ($specialties as $specialty) {
            Specialty::updateOrCreate(
                ['code' => $specialty['code']],
                array_merge($specialty, ['is_active' => true]),
            );
        }
    }
}
