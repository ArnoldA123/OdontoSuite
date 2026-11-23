<?php

namespace Tests\Unit\Models;

use App\Models\AppointmentType;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Contract of the `specialty_id` reference on the appointment-type catalog.
 *
 * The column is nullable on purpose: a type without a specialty is a valid,
 * visible state, so the relation must not assume one.
 */
class AppointmentTypeSpecialtyRelationTest extends TestCase
{
    public function test_specialty_id_is_fillable(): void
    {
        $type = new AppointmentType(['specialty_id' => 7]);

        $this->assertSame(7, $type->specialty_id);
    }

    public function test_specialty_relation_is_a_belongs_to_to_the_specialties_catalog(): void
    {
        $relation = (new AppointmentType)->specialty();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Specialty::class, $relation->getRelated());
        $this->assertSame('specialty_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
    }
}
