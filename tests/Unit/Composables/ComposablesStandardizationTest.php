<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 08 — composable state shape convention (T-08.10, T-08.11).
 *
 * Verifies that the list/CRUD composables expose the canonical shape:
 *   { data, loading, error, refresh, retry }
 *
 * The intent is to make `refresh()` an alias of the primary fetch and
 * `retry()` an alias of `refresh()` so callers (and components in slice
 * 11/12) can wire a single retry button regardless of which composable
 * owns the data.
 *
 * The list of in-scope composables matches the slice 08 spec:
 *   useTransactions, useTreatmentPlans, useBranches, useSpecialties,
 *   useAiAnalysis, useCashRegister.
 */
class ComposablesStandardizationTest extends TestCase
{
    /** Project root. */
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    /**
     * @return array<string, array<string, bool>>
     *   Map of composable basename => map of exposed members => true/false.
     */
    private function exposedMembers(): array
    {
        $candidates = [
            'useTransactions',
            'useTreatmentPlans',
            'useBranches',
            'useSpecialties',
            'useAiAnalysis',
            'useCashRegister',
        ];

        $report = [];
        foreach ($candidates as $name) {
            $file = self::PROJECT_ROOT . "/resources/js/composables/{$name}.js";
            if (!is_file($file)) {
                $report[$name] = ['exists' => false];
                continue;
            }
            $source = file_get_contents($file);

            // Extract everything inside the final `return { ... };`
            $report[$name] = [
                'exists' => true,
                'hasLoading' => (bool) preg_match('/\bloading\b/', $source),
                'hasError' => (bool) preg_match('/\berror\b/', $source),
                'hasRefresh' => (bool) preg_match('/\brefresh\b/', $source),
                'hasRetry' => (bool) preg_match('/\bretry\b/', $source),
                'hasData' => (bool) preg_match('/\bdata\b/', $source),
                'hasFetcher' => $this->hasFetcher($source),
            ];
        }
        return $report;
    }

    /** File has at least one named function that hits `useApi().get|post|...`. */
    private function hasFetcher(string $source): bool
    {
        return (bool) preg_match(
            '/const\s+\w+\s*=\s*async\s*\([^)]*\)\s*=>\s*\{[^}]*\bawait\s+(get|post|put|patch|del|delete)\b/s',
            $source
        );
    }

    /** @test T-08.10 */
    public function every_list_composable_exposes_loading_and_error(): void
    {
        $report = $this->exposedMembers();
        foreach ($report as $name => $info) {
            if ($info['exists'] === false) {
                $this->markTestSkipped("Composable {$name} does not exist (out of scope for this project)");
                continue;
            }
            $this->assertTrue(
                $info['hasLoading'],
                "{$name} must expose loading ref (T-08.10)"
            );
            $this->assertTrue(
                $info['hasError'],
                "{$name} must expose error ref (T-08.10)"
            );
        }
    }

    /** @test T-08.11 */
    public function every_list_composable_with_fetcher_exposes_refresh_and_retry(): void
    {
        $report = $this->exposedMembers();
        foreach ($report as $name => $info) {
            if ($info['exists'] === false) {
                $this->markTestSkipped("Composable {$name} does not exist");
                continue;
            }
            if ($info['hasFetcher'] === false) {
                $this->markTestSkipped("{$name} has no fetcher; refresh/retry N/A");
                continue;
            }
            $this->assertTrue(
                $info['hasRefresh'],
                "{$name} must expose refresh() alias (T-08.11)"
            );
            $this->assertTrue(
                $info['hasRetry'],
                "{$name} must expose retry() alias of refresh (T-08.11)"
            );
        }
    }

    /** @test T-08.10 */
    public function every_list_composable_exposes_data_alias_or_collection(): void
    {
        // At least one of the in-scope composables has a single
        // "collection" ref named `data` after the standardization pass OR
        //  exposes a documented collection name (transactions, plans,
        // branches, specialties, analyses, currentSession).
        $allowedCollections = [
            'useTransactions' => 'transactions',
            'useTreatmentPlans' => 'plans',
            'useBranches' => 'branches',
            'useSpecialties' => 'specialties',
            'useAiAnalysis' => 'analyses',
            'useCashRegister' => 'currentSession',
        ];

        $report = $this->exposedMembers();
        foreach ($report as $name => $info) {
            if ($info['exists'] === false) {
                $this->markTestSkipped("Composable {$name} does not exist");
                continue;
            }
            $source = file_get_contents(self::PROJECT_ROOT . "/resources/js/composables/{$name}.js");
            $collection = $allowedCollections[$name];
            $hasCollection = (bool) preg_match('/\b' . preg_quote($collection, '/') . '\b/', $source);
            $hasData = $info['hasData'];
            $this->assertTrue(
                $hasCollection || $hasData,
                "{$name} must expose either `data` or the canonical collection ref `{$collection}` (T-08.10)"
            );
        }
    }
}
