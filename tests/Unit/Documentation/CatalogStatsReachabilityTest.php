<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Issue #59 — AGENTS.md section 5 listed 17 rows while nav+router exposed
 * /settings/branches and /settings/payment-methods, and /procedure-stats
 * had route+API but no nav entry. What is not listed is not audited.
 *
 * Static verification of the catalog-to-stats link and the section 5 rows:
 * removing the nav entry, the catalog button or a row fails this suite.
 * (Patterns stay ASCII-only on purpose: accented row names broke PCRE
 * byte classes without the u-modifier.)
 */
class CatalogStatsReachabilityTest extends TestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function read(string $relative): string
    {
        $path = self::projectRoot() . $relative;
        self::assertFileExists($path, "{$relative} must exist");
        $src = file_get_contents($path);
        self::assertIsString($src, "{$relative} must be readable");

        return $src;
    }

    public function test_agents_section5_lists_settings_branches(): void
    {
        $docs = self::read('/AGENTS.md');
        $this->assertMatchesRegularExpression(
            '/^\|[^|\n]*\|\s*`\/settings\/branches`\s*\|/m',
            $docs,
            'AGENTS.md section 5 must list /settings/branches (#59)'
        );
    }

    public function test_agents_section5_lists_settings_payment_methods(): void
    {
        $docs = self::read('/AGENTS.md');
        $this->assertMatchesRegularExpression(
            '/^\|[^|\n]*\|\s*`\/settings\/payment-methods`\s*\|/m',
            $docs,
            'AGENTS.md section 5 must list /settings/payment-methods (#59)'
        );
    }

    public function test_procedure_stats_has_nav_entry_for_admin_and_finanzas(): void
    {
        $src = self::read('/resources/js/components/layout/AppLayout.vue');
        $this->assertMatchesRegularExpression(
            "/to:\\s*'\\/procedure-stats'/",
            $src,
            'AppLayout navigation must link to /procedure-stats (#59)'
        );
        $this->assertMatchesRegularExpression(
            "/to:\\s*'\\/procedure-stats'[\\s\\S]{0,200}?roles:\\s*\\[[^\\]]*'administrador'[^\\]]*'finanzas'[^\\]]*\\]/",
            $src,
            '/procedure-stats nav entry must be visible to administrador and finanzas (#59)'
        );
    }

    public function test_procedure_catalog_page_links_to_stats(): void
    {
        $src = self::read('/resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue');
        $this->assertStringContainsString(
            '/procedure-stats',
            $src,
            'ProcedureCatalogPage must link to /procedure-stats (#59)'
        );
        $this->assertStringContainsString(
            'canViewStats',
            $src,
            'ProcedureCatalogPage stats link must be role-gated (admin/finanzas, #59)'
        );
    }
}
