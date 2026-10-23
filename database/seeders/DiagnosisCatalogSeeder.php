<?php

namespace Database\Seeders;

use App\Models\DiagnosisCatalog;
use Illuminate\Database\Seeder;

/**
 * F6 (#286) — the CIE-10 codes an odontology practice actually codes.
 *
 * The catalogue is the reference the close reads; it is seeded, not written from
 * the chair. The selection is the odontological chapter of CIE-10 (K00-K14) plus
 * the two codes the stomatology chapter contributes, each with the description
 * the printed record shows. The seeder is idempotent: re-running updates the
 * canonical text and never duplicates a code.
 */
class DiagnosisCatalogSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const DIAGNOSES = [
        [
            'cie_code' => 'K00.0',
            'name' => 'Anodoncia',
            'category' => 'Trastornos del desarrollo y de la erupción dentaria',
            'specialty' => 'general',
            'description' => 'Ausencia congénita de uno o más dientes.',
        ],
        [
            'cie_code' => 'K00.6',
            'name' => 'Trastornos de la erupción dentaria',
            'category' => 'Trastornos del desarrollo y de la erupción dentaria',
            'specialty' => 'general',
            'description' => 'Erupción retardada, prematura o retenida de una pieza.',
        ],
        [
            'cie_code' => 'K01.1',
            'name' => 'Diente impactado',
            'category' => 'Dientes incluidos e impactados',
            'specialty' => 'cirugia_oral',
            'description' => 'Pieza que no erupciona y queda retenida en el maxilar.',
        ],
        [
            'cie_code' => 'K02.0',
            'name' => 'Caries limitada al esmalte',
            'category' => 'Caries dental',
            'specialty' => 'general',
            'description' => 'Lesión cariosa que compromete únicamente el esmalte.',
        ],
        [
            'cie_code' => 'K02.1',
            'name' => 'Caries de la dentina',
            'category' => 'Caries dental',
            'specialty' => 'general',
            'description' => 'Lesión cariosa que alcanza la dentina.',
        ],
        [
            'cie_code' => 'K02.9',
            'name' => 'Caries dental, no especificada',
            'category' => 'Caries dental',
            'specialty' => 'general',
            'description' => 'Caries sin precisión del tejido comprometido.',
        ],
        [
            'cie_code' => 'K03.6',
            'name' => 'Depósitos en los dientes',
            'category' => 'Otras enfermedades de los tejidos duros',
            'specialty' => 'general',
            'description' => 'Cálculo, sarro y tinción dentaria.',
        ],
        [
            'cie_code' => 'K04.0',
            'name' => 'Pulpitis',
            'category' => 'Enfermedades de la pulpa y de los tejidos periapicales',
            'specialty' => 'endodoncia',
            'description' => 'Inflamación de la pulpa dental.',
        ],
        [
            'cie_code' => 'K04.5',
            'name' => 'Periodontitis apical crónica',
            'category' => 'Enfermedades de la pulpa y de los tejidos periapicales',
            'specialty' => 'endodoncia',
            'description' => 'Lesión periapical crónica de origen pulpar.',
        ],
        [
            'cie_code' => 'K04.7',
            'name' => 'Absceso periapical sin fístula',
            'category' => 'Enfermedades de la pulpa y de los tejidos periapicales',
            'specialty' => 'endodoncia',
            'description' => 'Colección purulenta periapical sin trayecto fistuloso.',
        ],
        [
            'cie_code' => 'K05.1',
            'name' => 'Gingivitis crónica',
            'category' => 'Enfermedades gingivales y periodontales',
            'specialty' => 'periodoncia',
            'description' => 'Inflamación gingival de curso crónico.',
        ],
        [
            'cie_code' => 'K05.3',
            'name' => 'Periodontitis crónica',
            'category' => 'Enfermedades gingivales y periodontales',
            'specialty' => 'periodoncia',
            'description' => 'Pérdida de inserción periodontal de curso crónico.',
        ],
        [
            'cie_code' => 'K06.0',
            'name' => 'Retracción gingival',
            'category' => 'Otros trastornos de la encía y de la mucosa',
            'specialty' => 'periodoncia',
            'description' => 'Migración apical del margen gingival con exposición radicular.',
        ],
        [
            'cie_code' => 'K07.3',
            'name' => 'Anomalías de la posición de los dientes',
            'category' => 'Anomalías dentofaciales',
            'specialty' => 'ortodoncia',
            'description' => 'Malposición, rotación o apiñamiento dentario.',
        ],
        [
            'cie_code' => 'K07.4',
            'name' => 'Maloclusión, no especificada',
            'category' => 'Anomalías dentofaciales',
            'specialty' => 'ortodoncia',
            'description' => 'Alteración de la relación oclusal.',
        ],
        [
            'cie_code' => 'K08.1',
            'name' => 'Pérdida de dientes por accidente, extracción o enfermedad',
            'category' => 'Otros trastornos de los dientes y estructuras de sostén',
            'specialty' => 'rehabilitacion',
            'description' => 'Ausencia de pieza adquirida.',
        ],
        [
            'cie_code' => 'K08.3',
            'name' => 'Raíz dental retenida',
            'category' => 'Otros trastornos de los dientes y estructuras de sostén',
            'specialty' => 'cirugia_oral',
            'description' => 'Resto radicular que permanece en el alvéolo.',
        ],
        [
            'cie_code' => 'K10.2',
            'name' => 'Enfermedades inflamatorias de los maxilares',
            'category' => 'Otras enfermedades de los maxilares',
            'specialty' => 'cirugia_oral',
            'description' => 'Osteítis y osteomielitis de los maxilares.',
        ],
        [
            'cie_code' => 'K12.0',
            'name' => 'Estomatitis aftosa recurrente',
            'category' => 'Estomatitis y lesiones afines',
            'specialty' => 'general',
            'description' => 'Aftas recurrentes de la mucosa oral.',
        ],
        [
            'cie_code' => 'K13.7',
            'name' => 'Otras lesiones y las no especificadas de la mucosa oral',
            'category' => 'Otras enfermedades de los labios y de la mucosa oral',
            'specialty' => 'general',
            'description' => 'Lesión de la mucosa oral sin otra clasificación.',
        ],
    ];

    public function run(): void
    {
        foreach (self::DIAGNOSES as $diagnosis) {
            DiagnosisCatalog::updateOrCreate(
                ['cie_code' => $diagnosis['cie_code']],
                [
                    'name' => $diagnosis['name'],
                    'description' => $diagnosis['description'] ?? null,
                    'category' => $diagnosis['category'] ?? null,
                    'specialty' => $diagnosis['specialty'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
