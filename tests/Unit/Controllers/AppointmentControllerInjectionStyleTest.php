<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — BF-024 controller injection style RED test.
 *
 * AppointmentController declares services as `protected` (mutable) while
 * the rest of the codebase uses `private readonly` (constructor promotion).
 * This test asserts that AppointmentController follows the same pattern.
 */
class AppointmentControllerInjectionStyleTest extends TestCase
{
    private const CONTROLLER_FILE = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/app/Http/Controllers/Api/AppointmentController.php';

    public function test_appointment_controller_uses_private_readonly_services(): void
    {
        $source = file_get_contents(self::CONTROLLER_FILE);
        $this->assertNotFalse($source);

        // The protected assignment must NOT exist in the modern form.
        // Either it uses constructor promotion (private readonly) or it
        // is omitted entirely (no injected service).
        $hasProtectedAssignment = (bool) preg_match(
            '/^protected\s+\\?App\\?Services\\?AppointmentService\s+\$appointmentService\s*;/m',
            $source
        );

        $this->assertFalse(
            $hasProtectedAssignment,
            'BF-024: AppointmentController must drop the protected $appointmentService assignment in favor of constructor promotion (private readonly).'
        );

        // Either: constructor promotion present OR no service injection.
        // After the refactor, the controller should declare
        // `private readonly AppointmentService $appointmentService,` in the
        // constructor signature.
        $hasConstructorPromotion = (bool) preg_match(
            '/public function __construct\(\s*(?:public|private|protected)\s+readonly\s+AppointmentService\s+\$appointmentService\s*,?\s*\)/',
            $source
        );
        $hasNoServiceAtAll = !str_contains($source, 'AppointmentService');

        $this->assertTrue(
            $hasConstructorPromotion || $hasNoServiceAtAll,
            'BF-024: AppointmentController must use constructor-promoted private readonly service OR remove the unused service injection.'
        );
    }
}
