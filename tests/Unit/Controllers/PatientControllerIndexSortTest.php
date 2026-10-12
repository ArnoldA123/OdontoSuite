<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\PatientController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Patients list sort contract for PatientController::index.
 *
 * No-DB source/contract test in the structural style of
 * PatientControllerResourceWireUpTest: `index` validates the `sort` /
 * `direction` whitelists inline, and the resolved ordering falls back to
 * the legacy `last_name,first_name` behavior when the params are absent
 * or not whitelisted. Nothing here touches the schema.
 */
class PatientControllerIndexSortTest extends TestCase
{
    private static function controllerFile(): string
    {
        return dirname(__DIR__, 3) . '/app/Http/Controllers/Api/PatientController.php';
    }

    private function controllerSource(): string
    {
        $source = file_get_contents(self::controllerFile());
        $this->assertNotFalse($source, 'PatientController source MUST be readable.');
        return $source;
    }

    private function extractMethodBody(string $source, string $methodName): ?string
    {
        if (!preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:[^{]*\{/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openPos = $matches[0][1] + strlen($matches[0][0]) - 1;
        $depth = 0;
        $len = strlen($source);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $source[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $openPos + 1, $i - $openPos - 1);
                }
            }
        }
        return null;
    }

    private function invokeHelper(string $method, ?string $argument)
    {
        $this->assertTrue(
            method_exists(PatientController::class, $method),
            sprintf('PatientController MUST expose the `%s` sort helper.', $method)
        );

        $reflection = new ReflectionMethod(PatientController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $argument);
    }

    public function test_index_validates_sort_and_direction_whitelists(): void
    {
        $body = $this->extractMethodBody($this->controllerSource(), 'index');
        $this->assertNotNull($body, 'Could not locate the `index` method body in PatientController.');

        $this->assertMatchesRegularExpression(
            "/'sort'\s*=>\s*\[[^\]]*?Rule::in\(\s*\[\s*'last_name'\s*,\s*'first_name'\s*,\s*'created_at'\s*,\s*'email'\s*\]/s",
            $body,
            'PatientController::index() MUST validate `sort` against Rule::in([last_name, first_name, created_at, email]).'
        );

        $this->assertMatchesRegularExpression(
            "/'direction'\s*=>\s*\[[^\]]*?Rule::in\(\s*\[\s*'asc'\s*,\s*'desc'\s*\]/s",
            $body,
            'PatientController::index() MUST validate `direction` against Rule::in([asc, desc]).'
        );
    }

    public function test_index_wires_the_resolved_sort_into_the_query(): void
    {
        $body = $this->extractMethodBody($this->controllerSource(), 'index');
        $this->assertNotNull($body, 'Could not locate the `index` method body in PatientController.');

        foreach (['self::sortColumns(', 'self::sortDirection(', 'orderBy('] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $body,
                sprintf('PatientController::index() MUST apply the resolved ordering via `%s`.', $needle)
            );
        }
    }

    public function test_sort_columns_fall_back_to_last_name_first_name(): void
    {
        $this->assertSame(['last_name', 'first_name'], $this->invokeHelper('sortColumns', null));
        $this->assertSame(['last_name', 'first_name'], $this->invokeHelper('sortColumns', ''));
        $this->assertSame(['last_name', 'first_name'], $this->invokeHelper('sortColumns', 'unknown_column'));
    }

    public function test_sort_columns_map_the_whitelisted_sort_values(): void
    {
        $this->assertSame(['last_name', 'first_name'], $this->invokeHelper('sortColumns', 'last_name'));
        $this->assertSame(['first_name', 'last_name'], $this->invokeHelper('sortColumns', 'first_name'));
        $this->assertSame(['created_at'], $this->invokeHelper('sortColumns', 'created_at'));
        $this->assertSame(['email'], $this->invokeHelper('sortColumns', 'email'));
    }

    public function test_sort_direction_falls_back_to_asc(): void
    {
        $this->assertSame('asc', $this->invokeHelper('sortDirection', null));
        $this->assertSame('asc', $this->invokeHelper('sortDirection', ''));
        $this->assertSame('asc', $this->invokeHelper('sortDirection', 'sideways'));
        $this->assertSame('asc', $this->invokeHelper('sortDirection', 'asc'));
        $this->assertSame('desc', $this->invokeHelper('sortDirection', 'desc'));
    }
}
