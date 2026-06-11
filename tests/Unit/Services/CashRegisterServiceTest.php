<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Sprint 4 (M-6): tests estructurales para CashRegisterService.
 *
 * Verifica que el servicio expone los metodos esperados (que el plan
 * de catalogo de procedimientos y los demas modulos esperan).
 */
class CashRegisterServiceTest extends TestCase
{
    public function test_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(\App\Services\CashRegisterService::class, 'openSession'));
        $this->assertTrue(method_exists(\App\Services\CashRegisterService::class, 'closeSession'));
        $this->assertTrue(method_exists(\App\Services\CashRegisterService::class, 'getCurrentSession'));
        $this->assertTrue(method_exists(\App\Services\CashRegisterService::class, 'getSessionSummary'));
        $this->assertTrue(method_exists(\App\Services\CashRegisterService::class, 'getSessions'));
    }

    /**
     * Verifica que el servicio se puede instanciar sin argumentos
     * (todos sus metodos son public, no requiere DI en constructor).
     */
    public function test_service_can_be_instantiated(): void
    {
        $service = new \App\Services\CashRegisterService();
        $this->assertInstanceOf(\App\Services\CashRegisterService::class, $service);
    }
}
