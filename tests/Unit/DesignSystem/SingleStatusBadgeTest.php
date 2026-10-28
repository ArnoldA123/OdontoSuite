<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Auditoría UI U11 (#454) — un solo badge de estado.
 *
 * `StatusBadge` es la primitiva genérica (`variant` + `label`, color -50,
 * `data-variant`). `StatusPill` fue su antecesora de dominio: mapa de estados
 * propio que sus tres consumidores nunca usaron —pasaban su etiqueta por slot—
 * y una segunda escala de color para el mismo concepto.
 *
 * La regla: una sola insignia. Este guard falla si la primitiva retirada vuelve,
 * si alguien la registra de nuevo, o si una de las dos superficies que la usaban
 * deja de montar `UiStatusBadge`.
 */
class SingleStatusBadgeTest extends TestCase
{
    private const SURFACES = [
        'resources/js/modules/ai-analysis/AiAnalysisPage.vue',
        'resources/js/modules/ai-analysis/components/AiAnalysisModal.vue',
        'resources/js/modules/branch-transfers/BranchTransfersPage.vue',
    ];

    public function test_the_deprecated_pill_primitive_is_gone(): void
    {
        $this->assertFileDoesNotExist(
            self::projectRoot().'/resources/js/components/ui/StatusPill.vue',
            'StatusPill.vue was retired in #454: StatusBadge is the only status primitive.'
        );
    }

    public function test_no_source_file_mentions_the_deprecated_pill(): void
    {
        $offenders = [];

        foreach (self::sourceFiles() as $file) {
            $src = (string) file_get_contents($file);

            if (preg_match('/\b(?:Ui)?StatusPill\b/', $src) === 1) {
                $offenders[] = self::relative($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'No file may consume or register StatusPill (#454): StatusBadge is the only status primitive.'
        );
    }

    public function test_the_former_consumers_mount_the_generic_badge(): void
    {
        foreach (self::SURFACES as $path) {
            $src = (string) file_get_contents(self::projectRoot().'/'.$path);

            $this->assertMatchesRegularExpression(
                '/import\s+UiStatusBadge\s+from\s+[\'"][^\'"]*ui\/StatusBadge\.vue[\'"]/',
                $src,
                $path.' must import the generic StatusBadge primitive.'
            );

            $this->assertMatchesRegularExpression(
                '/<UiStatusBadge\b/',
                $src,
                $path.' must render <UiStatusBadge> for its status column.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $root = self::projectRoot().'/resources/js';
        $files = [];
        $queue = [$root];

        while ($queue !== []) {
            $directory = array_pop($queue);

            foreach (scandir($directory) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory.'/'.$entry;

                if (is_dir($path)) {
                    $queue[] = $path;
                } elseif (preg_match('/\.(vue|js)$/', $entry) === 1) {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return $files;
    }

    private static function relative(string $path): string
    {
        return ltrim(str_replace(self::projectRoot(), '', $path), '/');
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
