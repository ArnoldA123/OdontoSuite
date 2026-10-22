<?php

namespace Tests\Feature\Domain;

use App\Exceptions\Clinical\ClinicalRecordImmutableException;
use App\Models\ClinicalAddendum;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F3 (#292) — the clinical history is neither erased nor amended in place.
 *
 * A correction is an addendum: it carries its own author, date and reason, and
 * the row it corrects keeps every field it had. The addendum itself is
 * append-only, so there is no path through the model that can rewrite or erase
 * one.
 */
class ClinicalAddendumDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_addendum_keeps_author_date_reason_and_content(): void
    {
        $author = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $record = MedicalRecord::factory()->create();

        $addendum = ClinicalAddendum::create([
            'medical_record_id' => $record->id,
            'user_id' => $author->id,
            'reason' => 'Error de digitación en el diagnóstico',
            'content' => 'El diagnóstico correcto es pulpitis irreversible.',
        ]);

        $this->assertDatabaseHas('clinical_addenda', [
            'id' => $addendum->id,
            'medical_record_id' => $record->id,
            'clinical_evolution_id' => null,
            'user_id' => $author->id,
            'reason' => 'Error de digitación en el diagnóstico',
            'content' => 'El diagnóstico correcto es pulpitis irreversible.',
        ]);

        $this->assertNotNull($addendum->created_at);
        $this->assertSame($author->id, $addendum->author->id);
        $this->assertTrue($addendum->medicalRecord->is($record));
        $this->assertNull($addendum->clinicalEvolution);

        // Append-only: the schema itself carries no update timestamp.
        $this->assertFalse(Schema::hasColumn('clinical_addenda', 'updated_at'));
    }

    public function test_an_addendum_points_at_a_record_or_at_one_of_its_evolutions(): void
    {
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $recordAddendum = ClinicalAddendum::factory()->create(['medical_record_id' => $record->id]);
        $evolutionAddendum = ClinicalAddendum::factory()->forEvolution($evolution)->create();

        $record->refresh();
        $evolution->refresh();

        $this->assertTrue($record->addenda->contains($recordAddendum));
        $this->assertTrue($record->addenda->contains($evolutionAddendum));
        $this->assertTrue($evolution->addenda->contains($evolutionAddendum));
        $this->assertFalse($evolution->addenda->contains($recordAddendum));
        $this->assertSame($record->id, $evolutionAddendum->medical_record_id);
    }

    public function test_an_addendum_cannot_be_edited(): void
    {
        $addendum = ClinicalAddendum::factory()->create(['reason' => 'Motivo original']);

        try {
            $addendum->update(['reason' => 'Motivo reescrito']);
            $this->fail('An addendum must not be editable.');
        } catch (ClinicalRecordImmutableException $exception) {
            $this->assertSame('clinical_addendum_not_editable', $exception->errorCode);
        }

        $this->assertDatabaseHas('clinical_addenda', [
            'id' => $addendum->id,
            'reason' => 'Motivo original',
        ]);
    }

    public function test_an_addendum_cannot_be_deleted(): void
    {
        $addendum = ClinicalAddendum::factory()->create();

        try {
            $addendum->delete();
            $this->fail('An addendum must not be deletable.');
        } catch (ClinicalRecordImmutableException $exception) {
            $this->assertSame('clinical_addendum_not_deletable', $exception->errorCode);
        }

        $this->assertDatabaseHas('clinical_addenda', ['id' => $addendum->id]);
    }
}
