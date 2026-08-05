<?php

namespace Tests\Unit\User;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * bugfix-2026-08 slice 05 — pure unit assertions about the User specialty
 * source-of-truth that do NOT require a database connection. These tests
 * pass locally on SQLite without triggering the documented
 * `transactions.type` dropColumn baseline tech debt (AGENTS.md §6).
 */
class UserSpecialtySourceOfTruthUnitTest extends TestCase
{
    /** @test */
    public function user_fillable_does_not_include_specialties_json(): void
    {
        $fillable = (new User())->getFillable();

        // The JSON `specialties` column is dropped (legacy). It must not be
        // mass-assignable — pivot is the source-of-truth.
        $this->assertNotContains(
            'specialties',
            $fillable,
            'User::$fillable must not include the dropped `specialties` JSON column.'
        );
    }

    /** @test */
    public function user_fillable_keeps_specialty_legacy_string(): void
    {
        $fillable = (new User())->getFillable();

        // ADR-0007: the legacy `specialty` string is RETAINED for frontend
        // compatibility.
        $this->assertContains(
            'specialty',
            $fillable,
            'User::$fillable must keep the legacy `specialty` string (ADR-0007).'
        );
    }

    /** @test */
    public function user_has_specialties_pivot_relation_method(): void
    {
        $user = new User();
        $this->assertTrue(
            method_exists($user, 'specialties'),
            'User must expose a `specialties()` pivot relation.'
        );

        // Use reflection to inspect the relation's pivot table target without
        // touching the DB (raw PHPUnit\Framework\TestCase has no connection resolver).
        $reflection = new \ReflectionMethod($user, 'specialties');
        $this->assertSame('Illuminate\Database\Eloquent\Relations\BelongsToMany', $reflection->getReturnType()?->getName() ?: '');

        // Static source check: the relation body must reference the pivot table
        // and the Specialty model class.
        $source = file_get_contents((new \ReflectionClass($user))->getFileName());
        $this->assertStringContainsString("belongsToMany(Specialty::class, 'user_specialties')", $source);
    }

    /** @test */
    public function user_has_specialty_code_accessor(): void
    {
        $user = new User();
        $this->assertTrue(
            method_exists($user, 'getSpecialtyCodeAttribute'),
            'User must keep the `specialty_code` accessor (Sprint 2 DM-6 sync with pivot).'
        );
    }

    /** @test */
    public function user_casts_do_not_include_specialties_json(): void
    {
        $casts = (new User())->getCasts();

        $this->assertArrayNotHasKey(
            'specialties',
            $casts,
            'User::$casts must not reference the dropped `specialties` JSON column.'
        );
    }
}