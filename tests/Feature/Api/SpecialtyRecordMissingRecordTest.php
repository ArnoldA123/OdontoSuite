<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\ImplantologyRecord;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #428 (S8) — the two write doors of a specialty record must answer 404 for an
 * id that does not exist, exactly like `show()` does.
 *
 * The service resolves the row with `findOrFail`, so the
 * `ModelNotFoundException` used to fall into the controller's
 * `catch (\Exception)`: the caller got a generic 500 and the attempt was
 * recorded in the log as a server error. A missing row is a client outcome,
 * not a server failure.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class SpecialtyRecordMissingRecordTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_ID = 999999999;

    private const NOT_FOUND_MESSAGE = 'Registro de especialidad no encontrado';

    private User $admin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
        $this->patient->forceFill(['is_active' => true])->save();
    }

    private function piece(): DentalPiece
    {
        $this->seed(DentalPieceSeeder::class);

        return DentalPiece::query()->firstOrFail();
    }

    private function implantology(): ImplantologyRecord
    {
        return ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece()->id,
            'created_by' => $this->admin->id,
            'implant_brand' => 'Nobel Biocare',
            'implant_model' => 'Replace Select',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'NB-1000',
            'placement_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    #[Test]
    public function update_of_a_missing_record_answers_404_without_logging_a_server_error(): void
    {
        Log::spy();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.self::MISSING_ID, [
                'specialty' => 'implantologia',
            ])
            ->assertStatus(404)
            ->assertJsonPath('message', self::NOT_FOUND_MESSAGE);

        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function delete_of_a_missing_record_answers_404_without_logging_a_server_error(): void
    {
        Log::spy();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/specialty-records/'.self::MISSING_ID.'?specialty=implantologia')
            ->assertStatus(404)
            ->assertJsonPath('message', self::NOT_FOUND_MESSAGE);

        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function update_of_an_existing_record_keeps_answering_200(): void
    {
        $record = $this->implantology();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$record->id}", [
                'specialty' => 'implantologia',
                'status' => 'loaded',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $record->id);

        $this->assertSame('loaded', $record->refresh()->status);
    }

    #[Test]
    public function delete_of_an_existing_record_keeps_answering_200(): void
    {
        $record = $this->implantology();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/specialty-records/{$record->id}?specialty=implantologia")
            ->assertOk();

        $this->assertNull(ImplantologyRecord::find($record->id));
    }
}
