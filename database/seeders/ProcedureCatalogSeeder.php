<?php

namespace Database\Seeders;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class ProcedureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = Specialty::pluck('id', 'code');

        $procedures = [
            ['code' => 'PREV-CONSULT', 'name' => 'Consulta y evaluación general', 'specialty' => 'general', 'default_cost' => 50.00, 'default_duration_minutes' => 30, 'materials_needed' => 'espejo, explorador, pinza', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'PREV-CLEAN', 'name' => 'Profilaxis y limpieza dental', 'specialty' => 'general', 'default_cost' => 80.00, 'default_duration_minutes' => 45, 'materials_needed' => 'pasta_profilactica, fluoruro_topico, cepillo_robin, punta_ultrasonido', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'PREV-FLUOR', 'name' => 'Fluorización tópico', 'specialty' => 'general', 'default_cost' => 40.00, 'default_duration_minutes' => 20, 'materials_needed' => 'fluoruro_topico, rollos_algodon', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'PREV-SEAL', 'name' => 'Sellantes de fosas y fisuras', 'specialty' => 'general', 'default_cost' => 35.00, 'default_duration_minutes' => 20, 'materials_needed' => 'resina_fluida, acido_grabador, adhesivo, microbrush', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'REST-COMP-1S', 'name' => 'Restauración con resina 1 superficie', 'specialty' => 'rehabilitacion', 'default_cost' => 90.00, 'default_duration_minutes' => 45, 'materials_needed' => 'resina_compuesta, acido_grabador, adhesivo, matriz_metalica, cuña_madera', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REST-COMP-2S', 'name' => 'Restauración con resina 2 superficies', 'specialty' => 'rehabilitacion', 'default_cost' => 130.00, 'default_duration_minutes' => 60, 'materials_needed' => 'resina_compuesta, acido_grabador, adhesivo, matriz_metalica, cuña_madera', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REST-COMP-3S', 'name' => 'Restauración con resina 3 superficies', 'specialty' => 'rehabilitacion', 'default_cost' => 170.00, 'default_duration_minutes' => 75, 'materials_needed' => 'resina_compuesta, acido_grabador, adhesivo, matriz_metalica, cuña_madera, tira_mylar', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REST-AMALG-1S', 'name' => 'Restauración con amalgama 1 superficie', 'specialty' => 'rehabilitacion', 'default_cost' => 70.00, 'default_duration_minutes' => 40, 'materials_needed' => 'amalgama_capsula, matriz_metalica, amalgama_mortero', 'requires_anesthesia' => true, 'requires_radiographs' => true, 'contraindications' => 'Embarazo, alergia a metales.'],
            ['code' => 'REST-IVOMER', 'name' => 'Restauración con ionómero de vidrio', 'specialty' => 'rehabilitacion', 'default_cost' => 75.00, 'default_duration_minutes' => 35, 'materials_needed' => 'ionomero_vidrio, vaselina', 'requires_anesthesia' => false, 'requires_radiographs' => true],
            ['code' => 'ENDO-UNI', 'name' => 'Endodoncia unirradicular', 'specialty' => 'endodoncia', 'default_cost' => 300.00, 'default_duration_minutes' => 90, 'materials_needed' => 'lima_K, gutta_percha, cemento_endodontico, anestesia, hipoclorito, EDTA', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'ENDO-BI', 'name' => 'Endodoncia birradicular', 'specialty' => 'endodoncia', 'default_cost' => 380.00, 'default_duration_minutes' => 120, 'materials_needed' => 'lima_K, gutta_percha, cemento_endodontico, anestesia, hipoclorito, EDTA', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'ENDO-TRI', 'name' => 'Endodoncia multirradicular', 'specialty' => 'endodoncia', 'default_cost' => 480.00, 'default_duration_minutes' => 150, 'materials_needed' => 'lima_K, gutta_percha, cemento_endodontico, anestesia, hipoclorito, EDTA', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'ENDO-RETX', 'name' => 'Retratamiento endodóntico', 'specialty' => 'endodoncia', 'default_cost' => 550.00, 'default_duration_minutes' => 180, 'materials_needed' => 'lima_K, gutta_percha, cemento_endodontico, anestesia, solvente_gutta, hipoclorito', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'CIRUG-EXO-S', 'name' => 'Exodoncia simple', 'specialty' => 'cirugia_oral', 'default_cost' => 80.00, 'default_duration_minutes' => 30, 'materials_needed' => 'anestesia, forceps, elevador, gasa_esteril', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'CIRUG-EXO-C', 'name' => 'Exodoncia compleja', 'specialty' => 'cirugia_oral', 'default_cost' => 250.00, 'default_duration_minutes' => 60, 'materials_needed' => 'anestesia, fresa_quirurgica, sutura, gasa_esteril, elevador, forceps', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'CIRUG-CORDAL', 'name' => 'Exodoncia de tercer molar incluido', 'specialty' => 'cirugia_oral', 'default_cost' => 350.00, 'default_duration_minutes' => 75, 'materials_needed' => 'anestesia, fresa_quirurgica, sutura, gasa_esteril, elevador, forceps', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'CIRUG-APICE', 'name' => 'Apicectomía', 'specialty' => 'cirugia_oral', 'default_cost' => 400.00, 'default_duration_minutes' => 90, 'materials_needed' => 'anestesia, fresa_quirurgica, MTA, sutura, microscopio', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REHAB-CORONA-MET', 'name' => 'Corona metal-porcelana', 'specialty' => 'rehabilitacion', 'default_cost' => 500.00, 'default_duration_minutes' => 90, 'materials_needed' => 'cemento_provisional, silicon_a, silicon_b, hilo_retractor', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REHAB-CORONA-ZR', 'name' => 'Corona zirconio', 'specialty' => 'rehabilitacion', 'default_cost' => 800.00, 'default_duration_minutes' => 90, 'materials_needed' => 'cemento_provisional, hilo_retractor', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REHAB-PUENTE-3', 'name' => 'Puente fijo 3 unidades', 'specialty' => 'rehabilitacion', 'default_cost' => 1500.00, 'default_duration_minutes' => 120, 'materials_needed' => 'cemento_provisional, silicon_a, silicon_b, hilo_retractor', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'REHAB-PARCIAL-REM', 'name' => 'Prótesis parcial removible', 'specialty' => 'rehabilitacion', 'default_cost' => 900.00, 'default_duration_minutes' => 120, 'materials_needed' => 'alginato, godiva, silicon_a, silicon_b, cera_roja', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'REHAB-TOTAL', 'name' => 'Prótesis total (dentadura completa)', 'specialty' => 'rehabilitacion', 'default_cost' => 1200.00, 'default_duration_minutes' => 120, 'materials_needed' => 'alginato, godiva, silicon_a, silicon_b, cera_roja, rodillos_articulacion', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'REHAB-INCERAM', 'name' => 'Incrustación cerámica (inlay/onlay)', 'specialty' => 'rehabilitacion', 'default_cost' => 450.00, 'default_duration_minutes' => 75, 'materials_needed' => 'cemento_resinoso, silicon_a, silicon_b, hilo_retractor', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'IMPL-1-UNIT', 'name' => 'Implante unitario + corona', 'specialty' => 'implantologia', 'default_cost' => 2200.00, 'default_duration_minutes' => 120, 'materials_needed' => 'implante_titanio, pilares, anestesia, sutura, fresas_implante', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'IMPL-INJERTO', 'name' => 'Injerto óseo', 'specialty' => 'implantologia', 'default_cost' => 950.00, 'default_duration_minutes' => 90, 'materials_needed' => 'membrana_colageno, hueso_liofilizado, anestesia, sutura, chinchetas_membrana', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'IMPL-SINUS', 'name' => 'Elevación de seno maxilar', 'specialty' => 'implantologia', 'default_cost' => 1400.00, 'default_duration_minutes' => 120, 'materials_needed' => 'membrana_colageno, hueso_liofilizado, anestesia, sutura, osteotomos', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'IMPL-ALL-4', 'name' => 'Carga inmediata All-on-4', 'specialty' => 'implantologia', 'default_cost' => 8500.00, 'default_duration_minutes' => 240, 'materials_needed' => 'implante_titanio, pilares_multi, anestesia, sutura, provisional_acrílico', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'ORTO-DIAG', 'name' => 'Estudio ortodóncico + plan', 'specialty' => 'ortodoncia', 'default_cost' => 150.00, 'default_duration_minutes' => 60, 'materials_needed' => 'alginato, cera_mordida, separadores', 'requires_anesthesia' => false, 'requires_radiographs' => true],
            ['code' => 'ORTO-BRACKETS', 'name' => 'Colocación de brackets metálicos', 'specialty' => 'ortodoncia', 'default_cost' => 1200.00, 'default_duration_minutes' => 120, 'materials_needed' => 'brackets_metalicos, arcos_niti, ligas, cemento_ortodoncia', 'requires_anesthesia' => false, 'requires_radiographs' => true],
            ['code' => 'ORTO-CONTROL', 'name' => 'Control mensual de ortodoncia', 'specialty' => 'ortodoncia', 'default_cost' => 80.00, 'default_duration_minutes' => 30, 'materials_needed' => 'ligas, arcos_niti', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'ORTO-ALIGN', 'name' => 'Alineadores invisibles (set)', 'specialty' => 'ortodoncia', 'default_cost' => 3500.00, 'default_duration_minutes' => 90, 'materials_needed' => 'alginato, scanner_intraoral', 'requires_anesthesia' => false, 'requires_radiographs' => true],
            ['code' => 'ORTO-RETEN', 'name' => 'Retenedor fijo o removible', 'specialty' => 'ortodoncia', 'default_cost' => 180.00, 'default_duration_minutes' => 45, 'materials_needed' => 'alambre_retencion, acrilico_autopolimerizable', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'EST-BLANQ', 'name' => 'Blanqueamiento dental en consultorio', 'specialty' => 'estetica', 'default_cost' => 600.00, 'default_duration_minutes' => 90, 'materials_needed' => 'peroxido_hidrogeno_35, barrera_gengival, gel_desensibilizante, protector_faríngeo', 'requires_anesthesia' => false, 'requires_radiographs' => false, 'contraindications' => 'Embarazo, defectos severos del esmalte, sensibilidad extrema.'],
            ['code' => 'EST-CARILLA', 'name' => 'Carilla de porcelana', 'specialty' => 'estetica', 'default_cost' => 1100.00, 'default_duration_minutes' => 90, 'materials_needed' => 'cemento_resinoso, silicon_a, silicon_b, acido_grabador', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'EST-GINGIV', 'name' => 'Gingivoplastía / diseño de sonrisa', 'specialty' => 'estetica', 'default_cost' => 450.00, 'default_duration_minutes' => 60, 'materials_needed' => 'anestesia, sutura, gel_clorhexidina', 'requires_anesthesia' => true, 'requires_radiographs' => false],
            ['code' => 'PERIO-DETARTRAJE', 'name' => 'Detartraje supragingival', 'specialty' => 'periodoncia', 'default_cost' => 90.00, 'default_duration_minutes' => 45, 'materials_needed' => 'pasta_profilactica, punta_ultrasonido', 'requires_anesthesia' => false, 'requires_radiographs' => false],
            ['code' => 'PERIO-RASPAJE', 'name' => 'Raspaje y alisado radicular (por cuadrante)', 'specialty' => 'periodoncia', 'default_cost' => 220.00, 'default_duration_minutes' => 60, 'materials_needed' => 'curetas_gracey, anestesia, gel_clorhexidina', 'requires_anesthesia' => true, 'requires_radiographs' => false],
            ['code' => 'PERIO-CIRUG', 'name' => 'Cirugía periodontal (colgajo)', 'specialty' => 'periodoncia', 'default_cost' => 480.00, 'default_duration_minutes' => 90, 'materials_needed' => 'anestesia, sutura, gel_clorhexidina, membrana_colageno', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'URG-CONSULT', 'name' => 'Consulta de urgencia', 'specialty' => 'general', 'default_cost' => 70.00, 'default_duration_minutes' => 30, 'materials_needed' => 'anestesia, espejo, explorador', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'URG-PULP', 'name' => 'Pulpotomía / pulpectomía de urgencia', 'specialty' => 'endodoncia', 'default_cost' => 180.00, 'default_duration_minutes' => 60, 'materials_needed' => 'anestesia, gutta_percha, cemento_temporal, hipoclorito', 'requires_anesthesia' => true, 'requires_radiographs' => true],
            ['code' => 'URG-DRENAJE', 'name' => 'Drenaje de absceso', 'specialty' => 'cirugia_oral', 'default_cost' => 160.00, 'default_duration_minutes' => 45, 'materials_needed' => 'anestesia, dren_penrose, suero_fisiologico, seda_sutura', 'requires_anesthesia' => true, 'requires_radiographs' => true],
        ];

        $descriptions = [
            'PREV-CONSULT' => 'Examen clínico completo, revisión de historia clínica y plan diagnóstico inicial.',
            'PREV-CLEAN' => 'Limpieza profesional con ultrasonido, pulido y aplicación de flúor.',
            'PREV-FLUOR' => 'Aplicación de flúor en gel o barniz para prevención de caries.',
            'PREV-SEAL' => 'Sellado preventivo de surcos en molares y premolares permanentes.',
            'REST-COMP-1S' => 'Obturación de una superficie con composite fotopolimerizable.',
            'REST-COMP-2S' => 'Obturación de dos superficies con composite fotopolimerizable.',
            'REST-COMP-3S' => 'Obturación de tres superficies con composite fotopolimerizable.',
            'REST-AMALG-1S' => 'Obturación con amalgama de plata en una superficie.',
            'REST-IVOMER' => 'Indicada en lesiones cervicales o pacientes pediátricos.',
            'ENDO-UNI' => 'Tratamiento de conducto en diente con una raíz (incisivos, caninos).',
            'ENDO-BI' => 'Tratamiento de conducto en premolares con dos conductos.',
            'ENDO-TRI' => 'Tratamiento de conducto en molares con tres o más conductos.',
            'ENDO-RETX' => 'Remoción de material de conducto previo y nuevo tratamiento.',
            'CIRUG-EXO-S' => 'Extracción de diente con fórceps sin necesidad de colgajo.',
            'CIRUG-EXO-C' => 'Extracción con colgajo y/o osteotomía.',
            'CIRUG-CORDAL' => 'Extracción quirúrgica de cordal incluido o semi-incluido.',
            'CIRUG-APICE' => 'Resección quirúrgica del ápice radicular y retro-obturación.',
            'REHAB-CORONA-MET' => 'Corona fija con base metálica y recubrimiento de porcelana.',
            'REHAB-CORONA-ZR' => 'Corona libre de metal en zirconio monolítico o estratificado.',
            'REHAB-PUENTE-3' => 'Prótesis fija de tres unidades (dos pilares + póntico).',
            'REHAB-PARCIAL-REM' => 'Aparato removible con estructura metálica y dientes acrílicos.',
            'REHAB-TOTAL' => 'Dentadura completa superior y/o inferior en acrílico.',
            'REHAB-INCERAM' => 'Restauración indirecta cementada, fresada en laboratorio.',
            'IMPL-1-UNIT' => 'Colocación de un implante endoóseo con su corona definitiva.',
            'IMPL-INJERTO' => 'Regeneración ósea guiada con membrana y biomaterial.',
            'IMPL-SINUS' => 'Procedimiento de aumento del piso del seno maxilar.',
            'IMPL-ALL-4' => 'Rehabilitación completa sobre 4 implantes con provisional inmediato.',
            'ORTO-DIAG' => 'Análisis cefalométrico, modelos, fotos y plan de tratamiento.',
            'ORTO-BRACKETS' => 'Aparatología fija con brackets metálicos por arcada.',
            'ORTO-CONTROL' => 'Ajuste y revisión de aparatología fija o removible.',
            'ORTO-ALIGN' => 'Plan completo de alineadores termoplásticos transparentes.',
            'ORTO-RETEN' => 'Aparato de contención post-tratamiento.',
            'EST-BLANQ' => 'Blanqueamiento con peróxido de hidrógeno al 35% con activación LED.',
            'EST-CARILLA' => 'Carilla de cerámica delgada cementada en cara vestibular.',
            'EST-GINGIV' => 'Remodelado del contorno gingival con láser o bisturí eléctrico.',
            'PERIO-DETARTRAJE' => 'Remoción de cálculo y placa supragingival con ultrasonido.',
            'PERIO-RASPAJE' => 'Terapia periodontal básica, eliminación de cálculo subgingival.',
            'PERIO-CIRUG' => 'Acceso quirúrgico para descontaminación radicular profunda.',
            'URG-CONSULT' => 'Atención prioritaria del dolor, diagnóstico y alivio sintomático.',
            'URG-PULP' => 'Manejo de urgencia endodóntica con remoción parcial de pulpa.',
            'URG-DRENAJE' => 'Incisión y drenaje de colección purulenta con antibioticoterapia.',
        ];

        foreach ($procedures as $procedure) {
            $code = $procedure['code'];
            $specialtyId = $specialties[$procedure['specialty']] ?? null;

            ProcedureCatalog::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $procedure['name'],
                    'description' => $descriptions[$code] ?? null,
                    'legacy_specialty' => $procedure['specialty'],
                    'specialty_id' => $specialtyId,
                    'default_cost' => $procedure['default_cost'] ?? 0,
                    'default_duration_minutes' => $procedure['default_duration_minutes'] ?? 30,
                    'materials_needed' => $procedure['materials_needed'] ?? null,
                    'requires_anesthesia' => $procedure['requires_anesthesia'] ?? false,
                    'requires_radiographs' => $procedure['requires_radiographs'] ?? false,
                    'contraindications' => $procedure['contraindications'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
