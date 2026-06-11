<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReminderTemplateController;
use App\Http\Controllers\Api\WaitingListController;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sprint 0 fix (NF-1): los controllers Reminder/ReminderTemplate/WaitingList
 * eran stubs vacíos (cuerpo `//`) y devolvían 500. Ahora devuelven 501 con
 * mensaje claro. WaitingListController::store() sí está implementado.
 */
class StubsNotImplementedTest extends TestCase
{
    /** @test */
    public function reminder_controller_returns_501_for_all_resource_methods(): void
    {
        $controller = app(ReminderController::class);
        $request = Request::create('/api/reminders', 'GET');

        $methods = [
            'index' => [],
            'store' => [$request],
            'show' => [$request, '999'],
            'update' => [$request, '999'],
            'destroy' => [$request, '999'],
        ];

        foreach ($methods as $method => $args) {
            $response = $controller->{$method}(...$args);
            $this->assertEquals(501, $response->status(), "Reminder::{$method} should return 501");
            $body = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('message', $body);
            $this->assertArrayHasKey('todo', $body);
        }
    }

    /** @test */
    public function reminder_template_controller_returns_501_for_all_resource_methods(): void
    {
        $controller = app(ReminderTemplateController::class);
        $request = Request::create('/api/reminder-templates', 'GET');

        $methods = [
            'index' => [],
            'store' => [$request],
            'show' => [$request, '999'],
            'update' => [$request, '999'],
            'destroy' => [$request, '999'],
        ];

        foreach ($methods as $method => $args) {
            $response = $controller->{$method}(...$args);
            $this->assertEquals(501, $response->status(), "ReminderTemplate::{$method} should return 501");
        }
    }

    /** @test */
    public function waiting_list_store_validates_required_fields(): void
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
