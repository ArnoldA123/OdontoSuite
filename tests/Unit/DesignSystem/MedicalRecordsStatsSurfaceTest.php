<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Design + contract test for the "Estadísticas" surface of /medical-records
 * (T7 of medical-records-interface-redesign, S1).
 *
 * The surface used to be built on five keys the backend never returned
 * (`last_updated`, `days_since_creation`, `evolutions_by_month`,
 * `attachments_by_type`, `recent_activity`), so every block rendered empty.
 * This contract pins the replacement: the real last-evolution / specialty /
 * attachment-category payloads, the shared loading and empty primitives, and
 * the specialty label map that turns backend codes into human copy. The four
 * totals live once, in the page hero strip; this surface owns the detail and
 * must not repeat them.
 *
 * Every assertion is static source grep: the behavior lives in
 * tests/js/smoke/medical-records.test.js. Removing a hook, resurrecting an
 * invented key, or dropping the label map fails here even while the page
 * still renders.
 */
class MedicalRecordsStatsSurfaceTest extends TestCase
{
    private const COMPONENT_PATH = '/resources/js/modules/medical-records/components/MedicalRecordStats.vue';

    /**
     * Specialty codes the backend can emit for an evolution
     * (`clinical_evolutions.specialty`) and the label the surface must show.
     *
     * @var array<string, string>
     */
    private const SPECIALTY_LABELS = [
        'odontologia_general' => 'Odontología general',
        'implantologia' => 'Implantología',
        'ortodoncia' => 'Ortodoncia',
        'endodoncia' => 'Endodoncia',
        'periodoncia' => 'Periodoncia',
        'cirugia_oral' => 'Cirugía oral',
        'rehabilitacion' => 'Rehabilitación',
        'odontopediatria' => 'Odontopediatría',
    ];

    /** Backend keys the surface must read instead of the invented ones. */
    private const REAL_CONTRACT_KEYS = [
        'total_records',
        'total_evolutions',
        'total_attachments',
        'last_evolution',
        'specialties_involved',
        'attachment_categories',
        'pending_follow_ups',
    ];

    /** Keys that never existed in GET /api/medical-records/patient/{patient}/stats. */
    private const INVENTED_KEYS = [
        'last_updated',
        'days_since_creation',
        'evolutions_by_month',
        'attachments_by_type',
        'recent_activity',
    ];

    private function componentPath(): string
    {
        return dirname(__DIR__, 3).self::COMPONENT_PATH;
    }

    private function componentSource(): string
    {
        $path = self::componentPath();
        $this->assertFileExists($path, self::COMPONENT_PATH.' must exist.');

        $source = file_get_contents($path);
        $this->assertIsString($source, self::COMPONENT_PATH.' must be readable.');

        return $source;
    }

    /**
     * S1 - the surface owns no scoped CSS and no gradient: it is built out of
     * Tailwind utilities over the token ramps, like the dashboard.
     */
    public function test_surface_uses_no_scoped_block_and_no_gradient(): void
    {
        $source = $this->componentSource();

        $this->assertDoesNotMatchRegularExpression(
            '~<style\b[^>]*\bscoped\b~i',
            $source,
            'MedicalRecordStats.vue must not carry a `<style scoped>` block: the redesign uses utilities over tokens (S1).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '~<style\b~i',
            $source,
            'MedicalRecordStats.vue must not declare any `<style>` block (S1).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '~linear-gradient\s*\(~i',
            $source,
            'MedicalRecordStats.vue must not paint gradients (S1 KPI anatomy: flat tokens only).'
        );
    }

    /**
     * D1/T7 - the surface reads the real stats contract. The invented keys
     * made every panel render empty and must never come back.
     */
    public function test_surface_reads_the_real_stats_contract_only(): void
    {
        $source = $this->componentSource();

        foreach (self::REAL_CONTRACT_KEYS as $key) {
            $this->assertStringContainsString(
                $key,
                $source,
                sprintf('MedicalRecordStats.vue must read the real stats key `%s`.', $key)
            );
        }

        foreach (self::INVENTED_KEYS as $key) {
            $this->assertStringNotContainsString(
                $key,
                $source,
                sprintf('MedicalRecordStats.vue must not reference `%s`: the backend never returned it.', $key)
            );
        }
    }

    /**
     * S1 - one surface per datum. The page hero already renders the four
     * counters; repeating them inside the Estadísticas tab is the duplication
     * the dashboard hierarchy redesign eliminated (D8/R1). This surface owns
     * the detail blocks only.
     */
    public function test_surface_keeps_the_totals_in_the_page_strip(): void
    {
        $source = $this->componentSource();

        $this->assertSame(
            0,
            preg_match_all('~<UiCard\b~', $source),
            'MedicalRecordStats.vue must not re-render the page KPI cards (S1: one surface per datum).'
        );

        $this->assertSame(
            0,
            preg_match_all('~data-stat="~', $source),
            'MedicalRecordStats.vue must not duplicate the hero `data-stat` hooks (S1).'
        );

        $this->assertSame(
            0,
            preg_match_all('~data-kpi-caption="~', $source),
            'MedicalRecordStats.vue must not duplicate the hero KPI captions (S1).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '~<h1\b~i',
            $source,
            'MedicalRecordStats.vue must not render an <h1>: the page hero owns the only h1 on screen.'
        );
    }

    /**
     * T7 - the page shell passes `stats` and `loading`; both must be declared
     * props. `loading` used to fall through as a raw HTML attribute.
     */
    public function test_component_declares_stats_and_loading_props(): void
    {
        $source = $this->componentSource();

        $this->assertMatchesRegularExpression(
            '~const\s+props\s*=\s*defineProps\(\s*\{[\s\S]*?\}\s*\)~',
            $source,
            'MedicalRecordStats.vue must declare its props with defineProps().'
        );

        $this->assertMatchesRegularExpression(
            '~stats\s*:\s*\{[^}]*type\s*:\s*Object~s',
            $source,
            'MedicalRecordStats.vue must declare the `stats` object prop the page passes.'
        );

        $this->assertMatchesRegularExpression(
            '~loading\s*:\s*\{[^}]*type\s*:\s*Boolean~s',
            $source,
            'MedicalRecordStats.vue must declare the `loading` boolean prop instead of letting it fall through as an attribute.'
        );

        $this->assertMatchesRegularExpression(
            '~v-if="loading"~',
            $source,
            'The surface must branch on its own `loading` prop (shared loading state).'
        );
    }

    /**
     * S1 - the three data blocks below the strip: last evolution, specialty
     * chips and attachment distribution, each backed by its real payload.
     */
    public function test_blocks_cover_last_evolution_specialties_and_attachment_categories(): void
    {
        $source = $this->componentSource();

        foreach (['last-evolution', 'specialties', 'attachment-categories'] as $block) {
            $this->assertMatchesRegularExpression(
                '~data-stats-block="'.preg_quote($block, '~').'"~',
                $source,
                sprintf('MedicalRecordStats.vue must expose the `data-stats-block="%s"` block (T7).', $block)
            );
        }

        $this->assertStringContainsString(
            'Sin evoluciones registradas',
            $source,
            'The last-evolution block must state a clear "sin evoluciones" line instead of rendering blank.'
        );

        $this->assertStringContainsString(
            'bg-accent-500',
            $source,
            'The attachment distribution bars must fill with the accent-500 token (S1).'
        );

        $this->assertStringContainsString(
            'bg-theme-surface',
            $source,
            'The attachment distribution track must ride the theme surface token (S1).'
        );
    }

    /**
     * S1 - the specialty chips read backend codes through a human label map;
     * the eight clinical codes the API can emit are covered.
     */
    public function test_specialty_label_map_covers_the_eight_codes(): void
    {
        $source = $this->componentSource();

        $this->assertMatchesRegularExpression(
            '~const\s+SPECIALTY_LABELS\s*=\s*\{[\s\S]*?\n\}~',
            $source,
            'MedicalRecordStats.vue must declare a SPECIALTY_LABELS map for the specialty codes.'
        );

        foreach (self::SPECIALTY_LABELS as $code => $label) {
            $this->assertMatchesRegularExpression(
                '~'.preg_quote($code, '~').'\s*:\s*[\'"]'.preg_quote($label, '~').'[\'"]~u',
                $source,
                sprintf('SPECIALTY_LABELS must map `%s` to `%s`.', $code, $label)
            );
        }

        $this->assertMatchesRegularExpression(
            '~SPECIALTY_LABELS\[[^\]]+\]\s*(?:\|\||\?\?)\s*[A-Za-z_$][\w$]*~',
            $source,
            'Unknown specialty codes must fall back to the raw value from the payload.'
        );

        $this->assertMatchesRegularExpression(
            '~data-specialty-chip~',
            $source,
            'Specialty chips must expose a stable data-specialty-chip hook for the reader contract.'
        );
    }

    /**
     * T7 - the two states the shell delegates to this surface: the shared
     * spinner while loading and the shared empty state (with motif) when the
     * payload is null or every counter is zero.
     */
    public function test_loading_and_empty_states_use_the_shared_primitives(): void
    {
        $source = $this->componentSource();

        $this->assertMatchesRegularExpression(
            '~<LoadingSpinner\b~',
            $source,
            'MedicalRecordStats.vue must render <LoadingSpinner> while loading (shared primitive).'
        );

        $emptyBlock = preg_match(
            '~<UiSectionEmpty\b[^>]*\bdata-state="empty-stats"[^>]*>~s',
            $source,
            $matches
        );

        $this->assertSame(
            1,
            $emptyBlock,
            'MedicalRecordStats.vue must render <UiSectionEmpty data-state="empty-stats"> when there is no data.'
        );

        $this->assertMatchesRegularExpression(
            '~\bmotif\b~',
            $matches[0],
            'The empty state must carry the dental motif (S1 empty-state anatomy).'
        );

        $this->assertMatchesRegularExpression(
            '~v-else-if="!(?:props\.)?(?:hasAnyData|hasData)"~',
            $source,
            'The empty state must be driven by a single "any counter > 0" guard, not by per-field ternaries.'
        );
    }
}
