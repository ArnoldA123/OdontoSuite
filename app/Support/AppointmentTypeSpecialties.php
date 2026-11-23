<?php

namespace App\Support;

/**
 * #523 — the one place that says which specialty belongs to each seeded
 * appointment type.
 *
 * The seeder and the backfill command read the same map, so a base that was
 * populated before `appointment_types.specialty_id` existed can be completed
 * without inventing a second classification. The codes are the catalog's own
 * (`specialties.code`), not ids: the catalog is seeded independently.
 */
final class AppointmentTypeSpecialties
{
    /**
     * @var array<string, string>
     */
    public const NAME_TO_CODE = [
        'Consulta General' => 'general',
        'Limpieza Dental' => 'general',
        'Extracción' => 'cirugia_oral',
        'Empaste' => 'rehabilitacion',
        'Endodoncia' => 'endodoncia',
        'Corona' => 'rehabilitacion',
        'Ortodoncia' => 'ortodoncia',
        'Cirugía Oral' => 'cirugia_oral',
    ];

    public static function codeFor(string $name): ?string
    {
        return self::NAME_TO_CODE[$name] ?? null;
    }
}
