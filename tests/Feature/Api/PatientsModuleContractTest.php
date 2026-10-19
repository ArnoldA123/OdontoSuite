<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * patients-module-audit / T1 + T2 — backend contract of the patients area.
 *
 * Every assertion below pins a defect measured by
 * `odd/tasks/patients-module-audit.md` before the fix:
 *
 *   F1  `document_number` MUST be the stored value (or JSON null), never the id.
 *   F2  The DNI shown by the API MUST be searchable (or absent, never fabricated).
 *   F3  `per_page` outside 1..100 MUST degrade to the default, never a 500.
 *   F4  `branch_id` MUST be persisted, returned, and usable as a list filter.
 *   F5  DELETE MUST refuse a patient with clinical or financial history.
 *   F6  LIKE wildcards in `search` MUST match literally.
 *
 * Runs on the MySQL runner (`php artisan test --configuration=phpunit.mysql.xml`)
 * because the patients schema is MySQL-only (AGENTS.md §6).
 */
class PatientsModuleContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    private function patient(array $attributes = []): Patient
    {
        return Patient::create(array_merge([
            'first_name' => 'Paciente',
            'last_name' => 'Prueba',
            'is_active' => true,
        ], $attributes));
    }

    // ---------------------------------------------------------------
    // F1 / F2 — document_number is never fabricated
    // ---------------------------------------------------------------

    public function test_store_returns_null_document_number_when_none_was_sent(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/patients', [
            'first_name' => 'Sin',
            'last_name' => 'Documento',
        ]);

        $response->assertCreated();
        $this->assertNull(
            $response->json('data.document_number'),
            'POST /api/patients MUST return null when no document_number was submitted; the patient id is not a DNI.'
        );

        $stored = Patient::where('first_name', 'Sin')->where('last_name', 'Documento')->firstOrFail();
        $this->assertNull($stored->getAttributes()['document_number'] ?? null);
        $this->assertNull($stored->document_number, 'The model MUST expose the stored value, not $this->id.');
    }

    public function test_show_and_index_return_null_document_number_for_a_patient_without_one(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['first_name' => 'Anonimo', 'last_name' => 'Total']);

        $show = $this->actingAs($admin, 'sanctum')->getJson('/api/patients/' . $patient->id);
        $show->assertOk();
        $this->assertNull($show->json('data.document_number'), 'GET /api/patients/{id} MUST NOT fabricate a document_number.');

        $index = $this->actingAs($admin, 'sanctum')->getJson('/api/patients');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id', $patient->id);
        $this->assertNotNull($row);
        $this->assertNull($row['document_number'], 'GET /api/patients MUST NOT fabricate a document_number.');
    }

    public function test_document_number_is_exposed_verbatim_when_present(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['document_number' => '45678912']);

        $show = $this->actingAs($admin, 'sanctum')->getJson('/api/patients/' . $patient->id);

        $show->assertOk();
        $this->assertSame('45678912', $show->json('data.document_number'));
    }

    public function test_search_does_not_match_patients_by_their_id_through_the_document_number(): void
    {
        $admin = $this->admin();

        // The endpoint requires a 2-character term; push the auto-increment past
        // a single digit so the numeric id is a valid search term.
        for ($i = 0; $i < 10; $i++) {
            $this->patient(['first_name' => 'Relleno', 'last_name' => 'Zeta']);
        }

        $patient = $this->patient(['first_name' => 'Buscable', 'last_name' => 'Zeta']);
        $this->assertGreaterThanOrEqual(10, $patient->id);

        $byId = $this->actingAs($admin, 'sanctum')->getJson('/api/patients/search?q=' . $patient->id);

        $byId->assertOk();
        $this->assertCount(
            0,
            $byId->json('data'),
            'Searching the numeric id MUST NOT be answered through a fabricated document_number.'
        );

        $byName = $this->actingAs($admin, 'sanctum')->getJson('/api/patients/search?q=Buscable');
        $byName->assertOk();
        $this->assertCount(1, $byName->json('data'));
        $this->assertNull($byName->json('data.0.document_number'));
    }

    // ---------------------------------------------------------------
    // F14 — a duplicate phone answers 422, not a 500 from the unique index
    // ---------------------------------------------------------------

    public function test_store_rejects_a_phone_already_taken(): void
    {
        $admin = $this->admin();
        $this->patient(['phone' => '+51 999 111 222']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/patients', [
            'first_name' => 'Duplicado',
            'last_name' => 'Telefono',
            'phone' => '+51 999 111 222',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('phone', $response->json('errors'));
    }

    public function test_update_rejects_another_patients_phone_but_accepts_its_own(): void
    {
        $admin = $this->admin();
        $first = $this->patient(['first_name' => 'Primero', 'phone' => '+51 999 111 222']);
        $second = $this->patient(['first_name' => 'Segundo', 'phone' => '+51 999 333 444']);

        $taken = $this->actingAs($admin, 'sanctum')->putJson('/api/patients/' . $second->id, [
            'first_name' => 'Segundo',
            'last_name' => 'Prueba',
            'phone' => '+51 999 111 222',
        ]);
        $taken->assertStatus(422);
        $this->assertArrayHasKey('phone', $taken->json('errors'));

        $own = $this->actingAs($admin, 'sanctum')->putJson('/api/patients/' . $second->id, [
            'first_name' => 'Segundo',
            'last_name' => 'Prueba',
            'phone' => '+51 999 333 444',
        ]);
        $own->assertOk();
        $this->assertSame('+51 999 333 444', $own->json('data.phone'));
    }

    // ---------------------------------------------------------------
    // Round 2 — the patient events broadcast the resource, not the model
    // ---------------------------------------------------------------

    public function test_patient_events_broadcast_the_resource_payload(): void
    {
        $patient = $this->patient([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'document_number' => '45678912',
            'birth_date' => '1990-04-15',
            'medical_history' => 'Hipertensión',
        ]);

        foreach ([
            new \App\Events\PatientCreated($patient),
            new \App\Events\PatientUpdated($patient, []),
        ] as $event) {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey('patient', $payload);
            $patientPayload = $payload['patient'];

            $this->assertSame('45678912', $patientPayload['document_number']);
            $this->assertSame('Ana Torres', $patientPayload['full_name']);
            $this->assertIsInt($patientPayload['age']);
            $this->assertArrayHasKey('branch_id', $patientPayload);
            $this->assertArrayNotHasKey(
                'appointments',
                $patientPayload,
                'The event MUST NOT ship eagerly loaded relations the consumers never read.'
            );
            $this->assertArrayNotHasKey('deleted_at', $patientPayload);
        }
    }

    // ---------------------------------------------------------------
    // Round 2 — the dormant schema columns stay out of mass assignment
    // ---------------------------------------------------------------

    public function test_dormant_columns_are_not_mass_assignable(): void
    {
        $patient = Patient::create([
            'first_name' => 'Dormido',
            'last_name' => 'Columnas',
            'dni' => '12345678',
            'blood_type' => 'O+',
            'insurance_provider' => 'Rimac',
            'insurance_number' => 'INS-1',
        ]);

        $stored = $patient->fresh()->getAttributes();

        foreach (['dni', 'blood_type', 'insurance_provider', 'insurance_number'] as $column) {
            $this->assertNull(
                $stored[$column] ?? null,
                "patients.{$column} has no owner yet; it MUST NOT be writable through mass assignment."
            );
        }
    }

    // ---------------------------------------------------------------
    // Round 2 — quotation/transaction payloads carry the patient DNI
    // ---------------------------------------------------------------

    public function test_quotation_and_transaction_payloads_expose_the_patient_document_number(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['document_number' => 'DOC-99887766']);

        $treatmentPlan = \App\Models\TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $admin->id,
        ]);

        $quotation = \App\Models\Quotation::create([
            'patient_id' => $patient->id,
            'treatment_plan_id' => $treatmentPlan->id,
            'created_by' => $admin->id,
            'quotation_number' => 'COT-0001',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $method = PaymentMethod::create(['name' => 'Efectivo', 'code' => 'CASH', 'is_active' => true]);
        $transaction = Transaction::create([
            'patient_id' => $patient->id,
            'payment_method_id' => $method->id,
            'created_by' => $admin->id,
            'transaction_number' => 'TX-0002',
            'type' => 'payment',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $quotationPayload = \App\Http\Resources\QuotationResource::make($quotation->load('patient'))->resolve();
        $this->assertSame('DOC-99887766', $quotationPayload['patient']['document_number']);

        $transactionPayload = \App\Http\Resources\TransactionResource::make($transaction->load('patient'))->resolve();
        $this->assertSame('DOC-99887766', $transactionPayload['patient']['document_number']);
    }

    // ---------------------------------------------------------------
    // F3 — per_page never produces a 500
    // ---------------------------------------------------------------

    public function test_out_of_range_per_page_degrades_to_the_default_page_size(): void
    {
        $admin = $this->admin();
        $this->patient();

        foreach (['-5', '0', 'abc'] as $value) {
            $response = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?per_page=' . $value);

            $response->assertOk();
            $this->assertSame(
                15,
                $response->json('meta.per_page'),
                "per_page={$value} MUST fall back to 15 instead of failing or returning a negative/zero page size."
            );
        }

        $clamped = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?per_page=1000');
        $clamped->assertOk();
        $this->assertSame(100, $clamped->json('meta.per_page'));
    }

    // ---------------------------------------------------------------
    // F4 — branch assignment
    // ---------------------------------------------------------------

    public function test_store_persists_and_returns_the_branch_id(): void
    {
        $admin = $this->admin();
        $branch = Branch::create(['name' => 'Sede Centro', 'code' => 'CENTRO', 'address' => 'Av. Centro 1', 'city' => 'Trujillo', 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/patients', [
            'first_name' => 'Con',
            'last_name' => 'Sede',
            'branch_id' => $branch->id,
        ]);

        $response->assertCreated();
        $this->assertSame($branch->id, $response->json('data.branch_id'));
        $this->assertSame($branch->id, Patient::where('first_name', 'Con')->value('branch_id'));
    }

    public function test_store_and_update_accept_a_null_branch(): void
    {
        $admin = $this->admin();
        $patient = $this->patient();

        $update = $this->actingAs($admin, 'sanctum')->putJson('/api/patients/' . $patient->id, [
            'first_name' => 'Sin',
            'last_name' => 'Sede',
            'branch_id' => null,
        ]);

        $update->assertOk();
        $this->assertNull($update->json('data.branch_id'));
    }

    public function test_index_filters_by_branch_id(): void
    {
        $admin = $this->admin();
        $centro = Branch::create(['name' => 'Sede Centro', 'code' => 'CENTRO', 'address' => 'Av. Centro 1', 'city' => 'Trujillo', 'is_active' => true]);
        $norte = Branch::create(['name' => 'Sede Norte', 'code' => 'NORTE', 'address' => 'Av. Norte 2', 'city' => 'Trujillo', 'is_active' => true]);

        $inCentro = $this->patient(['first_name' => 'Centro', 'branch_id' => $centro->id]);
        $inNorte = $this->patient(['first_name' => 'Norte', 'branch_id' => $norte->id]);
        $floating = $this->patient(['first_name' => 'Flotante']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?branch_id=' . $centro->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$inCentro->id], $ids);
        $this->assertNotContains($inNorte->id, $ids);
        $this->assertNotContains($floating->id, $ids);
    }

    public function test_unknown_branch_id_is_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/patients', [
            'first_name' => 'Sede',
            'last_name' => 'Inexistente',
            'branch_id' => 99999,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('branch_id', $response->json('errors'));
    }

    public function test_search_scopes_to_the_requested_branch(): void
    {
        $admin = $this->admin();
        $centro = Branch::create(['name' => 'Sede Centro', 'code' => 'CENTRO', 'address' => 'Av. Centro 1', 'city' => 'Trujillo', 'is_active' => true]);

        $this->patient(['first_name' => 'Buscable', 'last_name' => 'Centro', 'branch_id' => $centro->id]);
        $this->patient(['first_name' => 'Buscable', 'last_name' => 'Otro']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients/search?q=Buscable&branch_id=' . $centro->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Centro', $response->json('data.0.last_name'));
    }

    // ---------------------------------------------------------------
    // F5 — DELETE refuses a patient with history
    // ---------------------------------------------------------------

    public function test_delete_refuses_a_patient_with_a_medical_record(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['first_name' => 'Con', 'last_name' => 'Historia']);

        MedicalRecord::create([
            'patient_id' => $patient->id,
            'created_by' => $admin->id,
            'record_number' => 'HC-0001',
            'first_visit_date' => now()->toDateString(),
            'chief_complaint' => 'Dolor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/patients/' . $patient->id);

        $response->assertStatus(422);
        $this->assertStringContainsString('historia', strtolower($response->json('message')));
        $this->assertNotEmpty($response->json('errors.patient'));
        $this->assertNotNull(Patient::find($patient->id), 'The patient MUST stay alive when the deletion is refused.');
        $this->assertSame(0, Patient::onlyTrashed()->count());
    }

    public function test_delete_refuses_a_patient_with_a_cash_transaction(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['first_name' => 'Con', 'last_name' => 'Caja']);

        $method = PaymentMethod::create(['name' => 'Efectivo', 'code' => 'CASH', 'is_active' => true]);

        Transaction::create([
            'patient_id' => $patient->id,
            'payment_method_id' => $method->id,
            'created_by' => $admin->id,
            'transaction_number' => 'TX-0001',
            'type' => 'payment',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/patients/' . $patient->id);

        $response->assertStatus(422);
        $this->assertSame(0, Patient::onlyTrashed()->count());
    }

    public function test_delete_still_works_for_a_patient_without_history(): void
    {
        $admin = $this->admin();
        $patient = $this->patient(['first_name' => 'Limpio', 'last_name' => 'Sin Historial']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/patients/' . $patient->id);

        $response->assertOk();
        $this->assertSame(1, Patient::onlyTrashed()->count());
    }

    public function test_delete_still_refuses_a_patient_with_appointments(): void
    {
        $admin = $this->admin();
        $patient = $this->patient();

        \App\Models\Appointment::factory()->create([
            'patient_id' => $patient->id,
            'ends_at' => now()->addDay()->addHour(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/patients/' . $patient->id);

        $response->assertStatus(422);
        $this->assertSame(0, Patient::onlyTrashed()->count());
        $this->assertStringContainsString('cita', strtolower($response->json('message')));
    }

    // ---------------------------------------------------------------
    // Status filter — the strings the list actually sends stay valid
    // ---------------------------------------------------------------

    public function test_active_filter_accepts_the_strings_the_list_sends(): void
    {
        $admin = $this->admin();
        $active = $this->patient(['first_name' => 'Activo', 'is_active' => true]);
        $inactive = $this->patient(['first_name' => 'Inactivo', 'is_active' => false]);

        $onlyActive = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?active=true');
        $onlyActive->assertOk();
        $this->assertSame([$active->id], collect($onlyActive->json('data'))->pluck('id')->all());

        $onlyInactive = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?active=false');
        $onlyInactive->assertOk();
        $this->assertSame([$inactive->id], collect($onlyInactive->json('data'))->pluck('id')->all());

        $empty = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?active=');
        $empty->assertOk();
        $this->assertSame(2, $empty->json('meta.total'), 'An empty active= MUST NOT be read as «inactivos».');
    }

    // ---------------------------------------------------------------
    // F6 — LIKE wildcards are literal
    // ---------------------------------------------------------------

    public function test_search_treats_a_percent_sign_literally(): void
    {
        $admin = $this->admin();
        $this->patient(['first_name' => 'Uno', 'last_name' => 'Alfa']);
        $this->patient(['first_name' => 'Dos', 'last_name' => 'Beta']);

        $search = $this->actingAs($admin, 'sanctum')->getJson('/api/patients/search?q=' . urlencode('%%'));
        $search->assertOk();
        $this->assertCount(0, $search->json('data'), 'A literal % MUST NOT behave as a wildcard.');

        $index = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?search=' . urlencode('%%'));
        $index->assertOk();
        $this->assertSame(0, $index->json('meta.total'), 'A literal % MUST NOT behave as a wildcard.');
    }

    public function test_search_still_matches_counters_with_the_escaped_term(): void
    {
        $admin = $this->admin();
        $this->patient(['first_name' => 'Ana', 'last_name' => 'Alfa', 'is_active' => true]);
        $this->patient(['first_name' => 'Ana', 'last_name' => 'Beta', 'is_active' => false]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/patients?search=Ana');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.active_count'));
        $this->assertSame(1, $response->json('meta.inactive_count'));
    }
}
