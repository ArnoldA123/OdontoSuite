<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DentalPiece;

class DentalPieceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear piezas dentales permanentes (adultos)
        $permanentTeeth = [
            // Incisivos centrales superiores
            ['fdi_number' => '11', 'name' => 'Incisivo Central Superior Derecho', 'type' => 'incisor', 'quadrant' => 'superior_derecho', 'position' => 1, 'is_permanent' => true],
            ['fdi_number' => '21', 'name' => 'Incisivo Central Superior Izquierdo', 'type' => 'incisor', 'quadrant' => 'superior_izquierdo', 'position' => 1, 'is_permanent' => true],
            // Incisivos laterales superiores
            ['fdi_number' => '12', 'name' => 'Incisivo Lateral Superior Derecho', 'type' => 'incisor', 'quadrant' => 'superior_derecho', 'position' => 2, 'is_permanent' => true],
            ['fdi_number' => '22', 'name' => 'Incisivo Lateral Superior Izquierdo', 'type' => 'incisor', 'quadrant' => 'superior_izquierdo', 'position' => 2, 'is_permanent' => true],
            // Caninos superiores
            ['fdi_number' => '13', 'name' => 'Canino Superior Derecho', 'type' => 'canine', 'quadrant' => 'superior_derecho', 'position' => 3, 'is_permanent' => true],
            ['fdi_number' => '23', 'name' => 'Canino Superior Izquierdo', 'type' => 'canine', 'quadrant' => 'superior_izquierdo', 'position' => 3, 'is_permanent' => true],
            // Primeros premolares superiores
            ['fdi_number' => '14', 'name' => 'Primer Premolar Superior Derecho', 'type' => 'premolar', 'quadrant' => 'superior_derecho', 'position' => 4, 'is_permanent' => true],
            ['fdi_number' => '24', 'name' => 'Primer Premolar Superior Izquierdo', 'type' => 'premolar', 'quadrant' => 'superior_izquierdo', 'position' => 4, 'is_permanent' => true],
            // Segundos premolares superiores
            ['fdi_number' => '15', 'name' => 'Segundo Premolar Superior Derecho', 'type' => 'premolar', 'quadrant' => 'superior_derecho', 'position' => 5, 'is_permanent' => true],
            ['fdi_number' => '25', 'name' => 'Segundo Premolar Superior Izquierdo', 'type' => 'premolar', 'quadrant' => 'superior_izquierdo', 'position' => 5, 'is_permanent' => true],
            // Primeros molares superiores
            ['fdi_number' => '16', 'name' => 'Primer Molar Superior Derecho', 'type' => 'molar', 'quadrant' => 'superior_derecho', 'position' => 6, 'is_permanent' => true],
            ['fdi_number' => '26', 'name' => 'Primer Molar Superior Izquierdo', 'type' => 'molar', 'quadrant' => 'superior_izquierdo', 'position' => 6, 'is_permanent' => true],
            // Segundos molares superiores
            ['fdi_number' => '17', 'name' => 'Segundo Molar Superior Derecho', 'type' => 'molar', 'quadrant' => 'superior_derecho', 'position' => 7, 'is_permanent' => true],
            ['fdi_number' => '27', 'name' => 'Segundo Molar Superior Izquierdo', 'type' => 'molar', 'quadrant' => 'superior_izquierdo', 'position' => 7, 'is_permanent' => true],
            // Terceros molares superiores
            ['fdi_number' => '18', 'name' => 'Tercer Molar Superior Derecho', 'type' => 'molar', 'quadrant' => 'superior_derecho', 'position' => 8, 'is_permanent' => true],
            ['fdi_number' => '28', 'name' => 'Tercer Molar Superior Izquierdo', 'type' => 'molar', 'quadrant' => 'superior_izquierdo', 'position' => 8, 'is_permanent' => true],

            // Incisivos centrales inferiores
            ['fdi_number' => '31', 'name' => 'Incisivo Central Inferior Derecho', 'type' => 'incisor', 'quadrant' => 'inferior_derecho', 'position' => 1, 'is_permanent' => true],
            ['fdi_number' => '41', 'name' => 'Incisivo Central Inferior Izquierdo', 'type' => 'incisor', 'quadrant' => 'inferior_izquierdo', 'position' => 1, 'is_permanent' => true],
            // Incisivos laterales inferiores
            ['fdi_number' => '32', 'name' => 'Incisivo Lateral Inferior Derecho', 'type' => 'incisor', 'quadrant' => 'inferior_derecho', 'position' => 2, 'is_permanent' => true],
            ['fdi_number' => '42', 'name' => 'Incisivo Lateral Inferior Izquierdo', 'type' => 'incisor', 'quadrant' => 'inferior_izquierdo', 'position' => 2, 'is_permanent' => true],
            // Caninos inferiores
            ['fdi_number' => '33', 'name' => 'Canino Inferior Derecho', 'type' => 'canine', 'quadrant' => 'inferior_derecho', 'position' => 3, 'is_permanent' => true],
            ['fdi_number' => '43', 'name' => 'Canino Inferior Izquierdo', 'type' => 'canine', 'quadrant' => 'inferior_izquierdo', 'position' => 3, 'is_permanent' => true],
            // Primeros premolares inferiores
            ['fdi_number' => '34', 'name' => 'Primer Premolar Inferior Derecho', 'type' => 'premolar', 'quadrant' => 'inferior_derecho', 'position' => 4, 'is_permanent' => true],
            ['fdi_number' => '44', 'name' => 'Primer Premolar Inferior Izquierdo', 'type' => 'premolar', 'quadrant' => 'inferior_izquierdo', 'position' => 4, 'is_permanent' => true],
            // Segundos premolares inferiores
            ['fdi_number' => '35', 'name' => 'Segundo Premolar Inferior Derecho', 'type' => 'premolar', 'quadrant' => 'inferior_derecho', 'position' => 5, 'is_permanent' => true],
            ['fdi_number' => '45', 'name' => 'Segundo Premolar Inferior Izquierdo', 'type' => 'premolar', 'quadrant' => 'inferior_izquierdo', 'position' => 5, 'is_permanent' => true],
            // Primeros molares inferiores
            ['fdi_number' => '36', 'name' => 'Primer Molar Inferior Derecho', 'type' => 'molar', 'quadrant' => 'inferior_derecho', 'position' => 6, 'is_permanent' => true],
            ['fdi_number' => '46', 'name' => 'Primer Molar Inferior Izquierdo', 'type' => 'molar', 'quadrant' => 'inferior_izquierdo', 'position' => 6, 'is_permanent' => true],
            // Segundos molares inferiores
            ['fdi_number' => '37', 'name' => 'Segundo Molar Inferior Derecho', 'type' => 'molar', 'quadrant' => 'inferior_derecho', 'position' => 7, 'is_permanent' => true],
            ['fdi_number' => '47', 'name' => 'Segundo Molar Inferior Izquierdo', 'type' => 'molar', 'quadrant' => 'inferior_izquierdo', 'position' => 7, 'is_permanent' => true],
            // Terceros molares inferiores
            ['fdi_number' => '38', 'name' => 'Tercer Molar Inferior Derecho', 'type' => 'molar', 'quadrant' => 'inferior_derecho', 'position' => 8, 'is_permanent' => true],
            ['fdi_number' => '48', 'name' => 'Tercer Molar Inferior Izquierdo', 'type' => 'molar', 'quadrant' => 'inferior_izquierdo', 'position' => 8, 'is_permanent' => true],
        ];

        foreach ($permanentTeeth as $tooth) {
            DentalPiece::create($tooth);
        }

        $this->command->info('Piezas dentales creadas exitosamente.');
    }
}
