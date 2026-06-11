<?php

namespace Tests\Unit\Services;

use App\Services\WaitingListService;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Sprint 0 fix (NF-6): WaitingListService::addToWaitingList() ya no
 * hardcodea created_by = 1. Ahora recibe $createdBy como parámetro y
 * cae a Auth::id() como default.
 */
class WaitingListServiceTest extends TestCase
{
    /** @test */
    public function add_to_waiting_list_accepts_created_by_parameter(): void
    {
        $reflection = new \ReflectionClass(WaitingListService::class);
        $method = $reflection->getMethod('addToWaitingList');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('data', $params[0]->getName());
        $this->assertEquals('createdBy', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional(), 'createdBy should be optional (null default)');
        $this->assertTrue($params[1]->allowsNull(), 'createdBy should allow null');
    }

    /** @test */
    public function convert_to_appointment_accepts_created_by_parameter(): void
    {
        $reflection = new \ReflectionClass(WaitingListService::class);
        $method = $reflection->getMethod('convertToAppointment');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('createdBy', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }

    /** @test */
    public function add_to_waiting_list_falls_back_to_auth_id(): void
    {
        $source = file_get_contents(app_path('Services/WaitingListService.php'));
        $this->assertStringContainsString("\$createdBy ?? \\Illuminate\\Support\\Facades\\Auth::id()", $source);
    }
}
