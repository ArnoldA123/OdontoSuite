<?php

namespace Tests\Feature\Api;

use App\Http\Requests\StoreSpecialtyRecordRequest;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * RED test (slice 02, T-02.4, T-02.10) — BF-010.
 *
 * Acceptance:
 *  - StoreSpecialtyRecordRequest::authorize() returns bool (does NOT throw)
 *    when input `specialty` is null. After slice 02 it must not crash.
 *  - rules() includes procedure_id (nullable).
 */
class SpecialtyRecordValidationTest extends TestCase
{
    public function test_authorize_does_not_throw_when_specialty_null(): void
    {
        // Create a fake logged-in user (we don't need DB — just Auth context)
        $request = new StoreSpecialtyRecordRequest();
        // setUser via container; we want the case where 'specialty' is missing
        $this->app->instance('auth.driver', function () {
            $mock = new class {
                public function user() { return null; }
                public function id() { return null; }
                public function check() { return false; }
                public function guest() { return true; }
            };
            return $mock;
        });
        // Override UserResolver to return null in case Auth::user() returns a real user
        $request->setUserResolver(fn () => null);

        // Populate request with NO 'specialty' input.
        $request->initialize([], [], [], [], [], [], json_encode([]));

        $authorized = $request->authorize();
        $this->assertIsBool($authorized, 'authorize() must return bool');
        $this->assertFalse($authorized, 'authorize() must deny when specialty is null');
    }

    public function test_rules_include_procedure_id(): void
    {
        $rules = (new StoreSpecialtyRecordRequest())->rules();

        $this->assertArrayHasKey('procedure_id', $rules);
        $this->assertStringContainsString('nullable', $rules['procedure_id']);
        $this->assertStringContainsString('procedure_catalog', $rules['procedure_id']);
    }
}
