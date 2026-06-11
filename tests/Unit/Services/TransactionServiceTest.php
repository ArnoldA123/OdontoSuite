<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Sprint 4 (M-6): tests estructurales para TransactionService.
 *
 * Verifica que el servicio expone los metodos esperados y que
 * el middleware cash.session (I-4) esta correctamente aliasado.
 */
class TransactionServiceTest extends TestCase
{
    public function test_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(\App\Services\TransactionService::class, 'createTransaction'));
        $this->assertTrue(method_exists(\App\Services\TransactionService::class, 'voidTransaction'));
        $this->assertTrue(method_exists(\App\Services\TransactionService::class, 'getTransactions'));
        $this->assertTrue(method_exists(\App\Services\TransactionService::class, 'generateReceipt'));
    }

    /**
     * I-4: cash.session debe estar aliasado en bootstrap/app.php.
     * Si no lo esta, las rutas de transactions no se protegen.
     */
    public function test_cash_session_middleware_is_aliased(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString(
            "'cash.session' => \\App\\Http\\Middleware\\RequireActiveCashSession::class",
            $bootstrap,
            'cash.session middleware debe estar aliasado en bootstrap/app.php (I-4 fix)'
        );
    }
}
