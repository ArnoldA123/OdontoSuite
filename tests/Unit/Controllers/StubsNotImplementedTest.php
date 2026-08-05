<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\WaitingListController;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sprint 0 fix (NF-1): los controllers Reminder/ReminderTemplate/WaitingList
 * eran stubs vacíos (cuerpo `//`) y devolvían 500.
 *
 * Slice 03 update: ReminderController y ReminderTemplateController ya NO
 * devuelven 501 — el CRUD real está implementado (BF-001, BF-002). Este
 * test ahora valida solo WaitingListController (slice 04 cubrirá el resto):
 *   - ::store valida campos requeridos
 *   - ::update y ::destroy siguen devolviendo 501 (out-of-scope para slice 03)
 */
class StubsNotImplementedTest extends TestCase
{
    public function test_waiting_list_store_validates_required_fields(): void
    {
        $controller = app(WaitingListController::class);
        $request = Request::create('/api/waiting-lists', 'POST', []);

        $threw = false;
        try {
            $controller->store($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $threw = true;
            $errors = $e->errors();
            $this->assertArrayHasKey('patient_id', $errors);
            $this->assertArrayHasKey('appointment_type_id', $errors);
        }

        $this->assertTrue($threw, 'WaitingListController::store should throw ValidationException for empty body');
    }

    /** @test */
    public function waiting_list_update_and_destroy_return_501(): void
    {
        $controller = app(WaitingListController::class);
        $request = Request::create('/api/waiting-lists/999', 'PUT');

        $update = $controller->update($request, '999');
        $this->assertEquals(501, $update->status());

        $destroy = $controller->destroy('999');
        $this->assertEquals(501, $destroy->status());
    }
}
