<?php

namespace Tests\Unit\DesignSystem;

/**
 * Registros de Especialidades (#212) — module shell guard.
 *
 * Scope: the redesigned page and its three child surfaces. The shared
 * vocabulary (`specialtyRecordMeta.js`) carries no markup, so the inherited
 * rules (canvas surface, hairlines, focus-ring token, no `<style scoped>`)
 * only apply to the four `.vue` files.
 */
class SpecialtyRecordsAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE = '/resources/js/modules/specialty-records/SpecialtyRecordsPage.vue';

    private const CARD = '/resources/js/modules/specialty-records/components/SpecialtyRecordCard.vue';

    private const DETAIL = '/resources/js/modules/specialty-records/components/SpecialtyRecordDetail.vue';

    private const MODAL = '/resources/js/modules/specialty-records/components/SpecialtyRecordModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root.self::PAGE,
            $root.self::CARD,
            $root.self::DETAIL,
            $root.self::MODAL,
        ];
    }
}
