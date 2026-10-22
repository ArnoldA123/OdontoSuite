<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sprint 4 (M-1): tests que verifican que los modelos criticos
 * tienen SoftDeletes aplicado.
 *
 * `MedicalRecord` y `ClinicalEvolution` salieron de la lista en F3 (#292): la
 * historia clinica no se borra, ni siquiera con soft delete. La guarda de esa
 * decision vive en `ClinicalRecordImmutabilityTest`.
 *
 * Si en el futuro alguien remueve el trait por error, este test falla.
 * No usa BD, solo inspecciona las clases.
 */
class SoftDeletesTest extends TestCase
{
    public static function modelProvider(): array
    {
        return [
            'Sprint 3 (4 criticos)' => [
                ['Patient', 'Appointment', 'Transaction'],
            ],
            'Sprint 4 (11 restantes)' => [
                [
                    'ClinicalAttachment', 'Quotation', 'PaymentMethod',
                    'PaymentPlan', 'Installment', 'Odontogram', 'Interconsultation',
                    'CashRegisterSession', 'CashMovement', 'TreatmentPlan',
                ],
            ],
        ];
    }

    #[DataProvider('modelProvider')]
    public function test_models_use_soft_deletes(array $models): void
    {
        foreach ($models as $model) {
            $class = "App\\Models\\{$model}";
            $this->assertTrue(class_exists($class), "Modelo $class no existe");

            $uses = in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses($class), true);
            $this->assertTrue(
                $uses,
                "Modelo $class debe usar SoftDeletes"
            );
        }
    }
}
