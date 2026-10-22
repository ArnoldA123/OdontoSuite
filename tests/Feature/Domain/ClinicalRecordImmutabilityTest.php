<?php

namespace Tests\Feature\Domain;

use App\Exceptions\Clinical\ClinicalRecordImmutableException;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#292) — un registro clínico no debe poder eliminarse.
 *
 * Removing the delete routes is not enough: the models are evidence of a
 * signed clinical act, so Eloquent itself refuses the two paths that could
 * erase one. `SoftDeletes` is gone from both models — a soft delete is still a
 * delete, and the history is retained, not hidden.
 */
class ClinicalRecordImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_medical_record_cannot_be_deleted(): void
    {
        $record = MedicalRecord::factory()->create();

        try {
            $record->delete();
            $this->fail('A medical record must not be deletable.');
        } catch (ClinicalRecordImmutableException $exception) {
            $this->assertSame('medical_record_not_deletable', $exception->errorCode);
        }

        $this->assertDatabaseHas('medical_records', ['id' => $record->id]);
    }

    public function test_a_clinical_evolution_cannot_be_deleted(): void
    {
        $evolution = ClinicalEvolution::factory()->create();

        try {
            $evolution->delete();
            $this->fail('A clinical evolution must not be deletable.');
        } catch (ClinicalRecordImmutableException $exception) {
            $this->assertSame('clinical_evolution_not_deletable', $exception->errorCode);
        }

        $this->assertDatabaseHas('clinical_evolutions', ['id' => $evolution->id]);
    }

    /**
     * A soft delete is a delayed delete. Both models were the only clinical
     * aggregate that could disappear from the record, so the trait is pinned
     * out by name: reintroducing it reintroduces the hole.
     */
    public function test_the_clinical_models_do_not_use_soft_deletes(): void
    {
        foreach ([MedicalRecord::class, ClinicalEvolution::class] as $model) {
            $this->assertNotContains(
                'Illuminate\\Database\\Eloquent\\SoftDeletes',
                class_uses($model),
                "Modelo {$model} no debe usar SoftDeletes: la historia clínica no se borra (#292).",
            );
        }
    }
}
