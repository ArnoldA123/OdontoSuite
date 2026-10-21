<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Guard for #264: the ADR index is a promise, not a list of intentions.
 *
 * `AGENTS.md` presented ADR 0007 and 0008 as recorded decisions while the index
 * pointed at files that did not exist, and it kept a `0009 (pending — slice 05)`
 * row for a change that had been archived. A reader who followed a link landed
 * nowhere, so the document described a decision the repository did not hold.
 *
 * Three invariants:
 *  - every link in `docs/architecture/decisions/README.md` resolves on disk;
 *  - every ADR body in that directory is reachable from the index (a body
 *    nobody can find is the same defect in the other direction);
 *  - no live section of `AGENTS.md` points at the retired `docs/decisions/`
 *    path. The changelog is history and is deliberately exempt.
 */
class ArchitectureDecisionsIndexTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function decisionsDir(): string
    {
        return self::root().'/docs/architecture/decisions';
    }

    private function index(): string
    {
        $path = self::decisionsDir().'/README.md';
        $this->assertFileExists($path, 'The ADR index must exist.');

        return (string) file_get_contents($path);
    }

    /** @return string[] */
    private function linkedTargets(): array
    {
        preg_match_all('/\]\(\.\/([^)]+)\)/', $this->index(), $matches);

        return $matches[1];
    }

    public function test_every_link_in_the_index_resolves_on_disk(): void
    {
        $targets = $this->linkedTargets();
        $this->assertNotEmpty($targets, 'The index links no ADR, so this guard means nothing.');

        foreach ($targets as $target) {
            $this->assertFileExists(
                self::decisionsDir().'/'.$target,
                "The ADR index links ./{$target}, which does not exist. Write the body or drop the row."
            );
        }
    }

    public function test_every_adr_body_is_reachable_from_the_index(): void
    {
        $bodies = [];
        foreach (glob(self::decisionsDir().'/*.md') ?: [] as $path) {
            $name = basename($path);
            if ($name === 'README.md') {
                continue;
            }
            if (preg_match('/^\d{4}-/', $name) === 1) {
                $bodies[] = $name;
            }
        }

        $this->assertNotEmpty($bodies, 'No ADR body found, so this guard means nothing.');

        $linked = $this->linkedTargets();
        foreach ($bodies as $body) {
            $this->assertContains(
                $body,
                $linked,
                "ADR {$body} exists but the index does not link it, so nobody can find the decision."
            );
        }
    }

    public function test_agents_md_does_not_point_at_the_retired_decision_path(): void
    {
        $docs = (string) file_get_contents(self::root().'/AGENTS.md');
        $changelogAt = strpos($docs, '## 12. Changelog de AGENTS.md');
        $this->assertNotFalse($changelogAt, 'AGENTS.md must keep its §12 changelog heading.');

        $live = substr($docs, 0, (int) $changelogAt);
        $this->assertStringNotContainsString(
            'docs/decisions/',
            $live,
            'AGENTS.md still points at docs/decisions/, which does not exist; '
                .'the ADRs live in docs/architecture/decisions/.'
        );

        foreach (glob(self::decisionsDir().'/*.md') ?: [] as $path) {
            $this->assertStringNotContainsString(
                'docs/decisions/',
                (string) file_get_contents($path),
                basename($path).' points at the retired docs/decisions/ path.'
            );
        }
    }

    public function test_the_recorded_adrs_are_bodies_and_not_stubs(): void
    {
        foreach ([
            '0007-user-specialty-source-of-truth.md',
            '0008-procedure-catalog-legacy-specialty.md',
        ] as $name) {
            $path = self::decisionsDir().'/'.$name;
            $this->assertFileExists($path, "ADR {$name} must exist: AGENTS.md cites it in §9.");

            $body = (string) file_get_contents($path);
            foreach (['## Context', '## Decision', '## Consequences'] as $section) {
                $this->assertStringContainsString(
                    $section,
                    $body,
                    "ADR {$name} is missing its '{$section}' section, so it is a stub, not a decision."
                );
            }
        }
    }
}
