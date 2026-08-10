<?php

namespace Tests\Unit\Seeders;

use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\RehabilitationRecord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * full-user-browser-audit-2026-08-05 / PR3 / Phase 3a — RED slice.
 *
 * Parser-based field-contract regression guard for
 * `database/seeders/SpecialtyRecordSeeder.php`.
 *
 * Each `Model::create([...])` block in the seeder MUST use only keys that
 * exist in the corresponding model's `$fillable` array. The current seeder
 * still references pre-fix legacy keys (`user_id`, `medical_record_id`,
 * `start_date`, `diagnosis`, `surgical_notes`, ...) that would silently
 * raise SQLSTATE 42S22 ("Column not found") on MySQL during
 * `php artisan db:seed` and break every clean setup of the project.
 *
 * This test exists to FAIL RED against the current broken seeder. The
 * GREEN seeder rewrite ships in Phase 3b (PR4).
 *
 * Source-parsing style: mirrors `AuditLogMigrationTest::migrationUpBody()`
 * (strips line comments and C-style block comments so docblock references
 * to historical anchors do not trip the assertions). Brace-walking for
 * nested arrays mirrors
 * `PatientControllerResourceWireUpTest::extractMethodBody()`.
 *
 * $fillable is read via `ReflectionClass::getDefaultProperties()` so the
 * test does NOT require a database connection — it runs as a pure
 * `PHPUnit\Framework\TestCase` even when the SQLite tech-debt baseline
 * documented in AGENTS.md §6 is in effect.
 *
 * Rollback boundary: delete this single file. No production code, no
 * migration, no model, no seeder, no controller is touched.
 */
class SpecialtyRecordSeederFieldContractTest extends TestCase
{
    /** @var array<string, class-string> */
    private const MODEL_MAP = [
        'ImplantologyRecord' => ImplantologyRecord::class,
        'OrthodonticsRecord' => OrthodonticsRecord::class,
        'EndodonticsRecord' => EndodonticsRecord::class,
        'RehabilitationRecord' => RehabilitationRecord::class,
        'OralSurgeryRecord' => OralSurgeryRecord::class,
    ];

    /**
     * Universal legacy keys that are NEVER in any of the five `$fillable`
     * arrays. Per-model mismatches (e.g. `obturation_material` for
     * endodontics) are caught by the per-model `keys ⊆ $fillable` tests;
     * these two are universally wrong because every model uses `created_by`
     * (User FK) instead of `user_id`, and none of the models has a
     * `medical_record_id` column (the legacy table was removed).
     */
    private const UNIVERSAL_LEGACY_KEYS = [
        'user_id',
        'medical_record_id',
    ];

    private const SEEDER_FILE = __DIR__
        . '/../../../database/seeders/SpecialtyRecordSeeder.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertFileExists(
            self::SEEDER_FILE,
            'SpecialtyRecordSeeder.php must exist for the field-contract test to be meaningful.'
        );
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Strip line comments and block comments so docblock references to
     * historical anchors (e.g. "was previously anchored on `description`")
     * do not trip the source assertions. Mirrors
     * `AuditLogMigrationTest::migrationUpBody()`.
     */
    private function seederSourceWithoutComments(): string
    {
        $source = file_get_contents(self::SEEDER_FILE);
        $this->assertIsString($source, 'SpecialtyRecordSeeder.php must be readable.');

        return preg_replace([
            '/\/\/.*$/m',
            '/\/\*.*?\*\//s',
        ], '', $source) ?? $source;
    }

    /**
     * Read the live `$fillable` array of a model class via reflection so
     * this test does NOT need to instantiate Eloquent (no DB connection).
     *
     * @return array<int, string>
     */
    private function fillableFor(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $defaults = $reflection->getDefaultProperties();

        $this->assertArrayHasKey(
            'fillable',
            $defaults,
            "{$modelClass} must declare a \$fillable property for the field-contract test."
        );

        return $defaults['fillable'] ?? [];
    }

    /**
     * Locate every `ModelName::create(...)` call in the (comment-stripped)
     * seeder source. Returns one descriptor per `create()` call in source
     * order so the failure message can name the offending line.
     *
     * @return array<int, array{model: string, line: int, body: string}>
     */
    private function extractCreateBlocks(string $source): array
    {
        $blocks = [];

        foreach (self::MODEL_MAP as $modelName => $_) {
            $needle = $modelName . '::create(';
            $offset = 0;

            while (($pos = strpos($source, $needle, $offset)) !== false) {
                $line = substr_count(substr($source, 0, $pos), "\n") + 1;
                $bodyStart = $pos + strlen($needle);

                // Brace-walk to find the matching ')'. The body may contain
                // nested arrays (radiographic_data, attachments, ...), so
                // we walk parens depth.
                $depth = 1;
                $len = strlen($source);
                $i = $bodyStart;
                while ($i < $len && $depth > 0) {
                    $ch = $source[$i];
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                    $i++;
                }

                $this->assertGreaterThan(
                    $bodyStart,
                    $i,
                    "Could not find matching ')' for {$modelName}::create( starting at line {$line}."
                );

                $blocks[] = [
                    'model' => $modelName,
                    'line' => $line,
                    'body' => substr($source, $bodyStart, $i - $bodyStart),
                ];

                $offset = $i + 1;
            }
        }

        return $blocks;
    }

    /**
     * Walk the body of `Model::create(...)` and collect TOP-LEVEL string
     * keys of the outer associative array. Nested arrays (e.g.
     * `radiographic_data`, `attachments`, `measurements`, `lab_details`)
     * are skipped via depth tracking so a nested key like `'bone_height'`
     * is not falsely flagged as a `$fillable` violation.
     *
     * Key pattern is restricted to snake_case (`[a-z_][a-z0-9_]*`) so the
     * walk does not pick up string values that happen to contain `=>` or
     * model class references like `MedicalRecord`.
     *
     * @return array<int, string>
     */
    private function topLevelKeys(string $body): array
    {
        $start = strpos($body, '[');
        $this->assertNotFalse(
            $start,
            'Model::create body must start with ['
        );

        // Walk to matching ']' so nested arrays inside the outer one do
        // not derail key extraction (the body may end with `->get()` or
        // `->resolve()`, but we slice only the leading `[ ... ]`).
        $depth = 0;
        $len = strlen($body);
        $end = -1;
        for ($i = $start; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $this->assertGreaterThan(
            $start,
            $end,
            'Could not find matching ] in Model::create body.'
        );

        $arrayBody = substr($body, $start + 1, $end - $start - 1);
        $keys = [];

        $depth = 0;
        $i = 0;
        $len = strlen($arrayBody);
        while ($i < $len) {
            $ch = $arrayBody[$i];

            if ($ch === '[' || $ch === '{') {
                $depth++;
                $i++;
                continue;
            }
            if ($ch === ']' || $ch === '}') {
                $depth--;
                $i++;
                continue;
            }

            // Only consider top-level keys (depth === 0). A key in PHP is
            // a single-quoted snake_case identifier followed by optional
            // whitespace and the `=>` operator.
            if ($depth === 0 && $ch === "'") {
                $slice = substr($arrayBody, $i);
                if (preg_match("/^'([a-z_][a-z0-9_]*)'\s*=>/", $slice, $m) === 1) {
                    $keys[] = $m[1];
                    $i += strlen($m[0]);
                    continue;
                }
                // Not a key — step past the opening quote so we do not
                // misread a string value as a key candidate.
                $i++;
                continue;
            }

            $i++;
        }

        return $keys;
    }

    /**
     * Return all `{line, keys}` descriptors for the given model's
     * `::create()` blocks. Fails loudly if the seeder no longer contains
     * the branch (which itself is a contract violation).
     *
     * @return array<int, array{line: int, keys: array<int, string>}>
     */
    private function blocksFor(string $modelName): array
    {
        $blocks = array_values(array_filter(
            $this->extractCreateBlocks($this->seederSourceWithoutComments()),
            fn(array $b): bool => $b['model'] === $modelName
        ));

        $this->assertNotEmpty(
            $blocks,
            "Seeder MUST contain at least one {$modelName}::create() block; "
            . 'the field-contract test cannot prove the contract if the branch is missing.'
        );

        return array_map(
            function (array $b): array {
                return [
                    'line' => $b['line'],
                    'keys' => $this->topLevelKeys($b['body']),
                ];
            },
            $blocks
        );
    }

    /**
     * Shared assertion: every top-level key in every `Model::create`
     * block MUST be a member of `Model::$fillable`. The failure message
     * names the offending key(s) and the source line per the spec
     * scenario "Test fails when a forbidden key is introduced".
     */
    private function assertModelKeysAreSubsetOfFillable(string $modelName, string $modelClass): void
    {
        $fillable = $this->fillableFor($modelClass);

        foreach ($this->blocksFor($modelName) as $block) {
            $offending = array_values(array_diff($block['keys'], $fillable));
            $this->assertSame(
                [],
                $offending,
                sprintf(
                    "%s::create at line %d uses non-fillable keys: [%s]. Allowed \$fillable: [%s].",
                    $modelName,
                    $block['line'],
                    implode(', ', $offending),
                    implode(', ', $fillable)
                )
            );
        }
    }

    // -------------------------------------------------------------------
    // Per-model field-contract tests (each MUST fail RED on current seeder
    // except ImplantologyRecord, which is already aligned)
    // -------------------------------------------------------------------

    /** @test */
    public function test_implantology_record_create_keys_are_subset_of_fillable(): void
    {
        $this->assertModelKeysAreSubsetOfFillable(
            'ImplantologyRecord',
            ImplantologyRecord::class
        );
    }

    /** @test */
    public function test_orthodontics_record_create_keys_are_subset_of_fillable(): void
    {
        $this->assertModelKeysAreSubsetOfFillable(
            'OrthodonticsRecord',
            OrthodonticsRecord::class
        );
    }

    /** @test */
    public function test_endodontics_record_create_keys_are_subset_of_fillable(): void
    {
        $this->assertModelKeysAreSubsetOfFillable(
            'EndodonticsRecord',
            EndodonticsRecord::class
        );
    }

    /** @test */
    public function test_rehabilitation_record_create_keys_are_subset_of_fillable(): void
    {
        $this->assertModelKeysAreSubsetOfFillable(
            'RehabilitationRecord',
            RehabilitationRecord::class
        );
    }

    /** @test */
    public function test_oral_surgery_record_create_keys_are_subset_of_fillable(): void
    {
        $this->assertModelKeysAreSubsetOfFillable(
            'OralSurgeryRecord',
            OralSurgeryRecord::class
        );
    }

    // -------------------------------------------------------------------
    // Universal legacy-key deny list — regression guard for the two
    // keys that NEVER belong in any of the five `$fillable` arrays
    // -------------------------------------------------------------------

    /** @test */
    public function test_seeder_does_not_write_universal_legacy_keys(): void
    {
        $source = $this->seederSourceWithoutComments();

        foreach (self::UNIVERSAL_LEGACY_KEYS as $legacy) {
            $this->assertStringNotContainsString(
                "'{$legacy}' =>",
                $source,
                "SpecialtyRecordSeeder MUST NOT write the universal legacy key `{$legacy}`. "
                . 'Use `created_by` (User FK) instead of `user_id`, and the per-model FK '
                . "on patients/appointments instead of `medical_record_id`."
            );
        }
    }
}
