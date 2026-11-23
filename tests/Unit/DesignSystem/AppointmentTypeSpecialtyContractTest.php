<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #518 — the appointment-type specialty is written, listed and read.
 *
 * The API and the seeder already classified the catalog, but the surface
 * offered no way to assign it: a type created from the screen stayed "Sin
 * especialidad" forever. This guard keeps the three pieces together — the
 * selector in both write modals, the column in the list, and the field in the
 * detail — and keeps the catalog source on the API's active list.
 */
class AppointmentTypeSpecialtyContractTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).'/'.$relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[Test]
    public function the_write_modals_offer_the_specialty_and_clear_it_to_null(): void
    {
        $list = $this->source('resources/js/modules/appointment-types/AppointmentTypesPage.vue');

        $this->assertSame(
            2,
            substr_count($list, 'data-field="specialty_id"'),
            'Both the create and the edit modal must offer the specialty selector.'
        );
        $this->assertStringContainsString("label: 'Sin especialidad'", $list);
        $this->assertStringContainsString("specialty_id: ''", $list);
        $this->assertStringContainsString("payload.specialty_id === ''", $list);
        $this->assertStringContainsString("get('/api/specialties/active')", $list);
    }

    #[Test]
    public function the_list_publishes_the_specialty_and_the_detail_declares_it(): void
    {
        $list = $this->source('resources/js/modules/appointment-types/AppointmentTypesPage.vue');
        $detail = $this->source('resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue');

        $this->assertStringContainsString("key: 'specialty'", $list);
        $this->assertStringContainsString('data-type-specialty', $list);
        $this->assertStringContainsString('data-detail-field="specialty"', $detail);
        $this->assertStringContainsString('Sin especialidad', $detail);
    }
}
