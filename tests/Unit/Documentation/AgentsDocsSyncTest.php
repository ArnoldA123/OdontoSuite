<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — AGENTS.md documentation drift RED tests.
 *
 * Covers BF-017 (PaymentMethodSeeder missing from seeders list),
 * BF-020 (listener count mismatch — 7 listener classes, 11 cableos),
 * BF-027 (SQLite bug note without fix), and BF-028 (composer dev pnpm
 * verification).
 *
 * Each test parses AGENTS.md and asserts the documentation matches the
 * actual code reality (DatabaseSeeder.php and AppServiceProvider.php).
 */
class AgentsDocsSyncTest extends TestCase
{
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    private function agentsMd(): string
    {
        $path = self::PROJECT_ROOT . '/AGENTS.md';
        $this->assertFileExists($path, 'AGENTS.md must exist at project root');

        return file_get_contents($path);
    }

    private function databaseSeeder(): string
    {
        return file_get_contents(self::PROJECT_ROOT . '/database/seeders/DatabaseSeeder.php');
    }

    private function appServiceProvider(): string
    {
        return file_get_contents(self::PROJECT_ROOT . '/app/Providers/AppServiceProvider.php');
    }

    /** @test BF-017 */
    public function agentsMd_documents_payment_method_seeder(): void
    {
        // DatabaseSeeder.php uses PaymentMethodSeeder (line 22). AGENTS.md
        // §4 must list it among the 11 active seeders, otherwise
        // onboarding docs are out of sync with code reality.
        $seeder = $this->databaseSeeder();
        $this->assertStringContainsString('PaymentMethodSeeder', $seeder);

        $docs = $this->agentsMd();
        $this->assertStringContainsString(
            'PaymentMethodSeeder',
            $docs,
            'AGENTS.md §4 must list PaymentMethodSeeder (BF-017 docs drift)'
        );
    }

    /** @test BF-020 */
    public function agentsMd_listener_count_matches_app_service_provider(): void
    {
        // AppServiceProvider.php currently wires N Event::listen calls.
        // AGENTS.md §4 must reflect the real count of "cableos" (wires),
        // not just the count of distinct listener classes.
        $provider = $this->appServiceProvider();

        // Count Event::listen( invocations.
        $listenCount = preg_match_all('/Event::listen\(/', $provider);
        $this->assertGreaterThanOrEqual(
            11,
            $listenCount,
            'AppServiceProvider must wire at least 11 Event::listen calls'
        );

        $docs = $this->agentsMd();
        $this->assertStringContainsString(
            '11',
            $docs,
            'AGENTS.md §4 must mention the actual cableos count (11)'
        );
        $this->assertStringContainsString(
            'cableos',
            $docs,
            'AGENTS.md §4 must distinguish listener classes from cableos (BF-020 docs drift)'
        );
    }

    /** @test BF-027 */
    public function agentsMd_offers_workaround_for_sqlite_test_failures(): void
    {
        // The 28 SQLite pre-existing failures are documented as tech debt
        // without a concrete workaround. AGENTS.md §6 must offer at least
        // one actionable mitigation (docker-compose mysql service OR the
        // --group=mysql phpunit annotation).
        $docs = $this->agentsMd();
        $hasDockerFix = str_contains($docs, 'docker-compose') || str_contains($docs, 'docker compose');
        $hasGroupAnnotation = str_contains($docs, '@group mysql') || str_contains($docs, '--group=mysql');

        $this->assertTrue(
            $hasDockerFix || $hasGroupAnnotation,
            'AGENTS.md §6 must document a SQLite workaround (docker-compose or @group mysql) — BF-027'
        );
    }

    /** @test BF-028 */
    public function composer_dev_script_uses_pnpm_not_npm(): void
    {
        // composer.json scripts.dev must invoke `pnpm dev`, not `npm run dev`.
        $composer = file_get_contents(self::PROJECT_ROOT . '/composer.json');
        $this->assertNotFalse($composer);

        $this->assertStringContainsString(
            'pnpm dev',
            $composer,
            'composer.json scripts.dev must call `pnpm dev` (BF-028)'
        );
        $this->assertStringNotContainsString(
            'npm run dev',
            $composer,
            'composer.json scripts.dev must NOT call `npm run dev` (BF-028)'
        );
    }

    /** @test BF-017 */
    public function agentsMd_seeder_count_matches_database_seeder_call_count(): void
    {
        // Count how many ::class references appear in DatabaseSeeder.php
        // $this->call([...]); the count must match the seeder count
        // asserted in AGENTS.md §4 (11 active).
        $seeder = $this->databaseSeeder();
        preg_match_all('/::class\s*,/', $seeder, $matches);
        $callCount = count($matches[0]);

        $this->assertSame(
            13,
            $callCount,
            'DatabaseSeeder.php must call exactly 13 seeders in $this->call([...])'
        );

        // AGENTS.md §4 must declare 13 activos (was stale at 11).
        $docs = $this->agentsMd();
        $this->assertStringContainsString(
            '13 activos',
            $docs,
            'AGENTS.md §4 must declare 13 active seeders (BF-017 docs drift)'
        );
    }
}
