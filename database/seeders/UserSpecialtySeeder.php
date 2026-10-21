<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo-data fix for issue #211.
 *
 * `user_specialties` is the source of truth for a professional's specialty
 * (ADR-0007), but the demo seeders only wrote the legacy `users.specialty`
 * string, and the pivot backfill in the migration ran before any user existed.
 * The catalog therefore rendered zero professionals on every row.
 *
 * The mapping is explicit on purpose: the legacy strings do not share the
 * catalog codes (`orthodontics` / `ortoncia` differ, `implantology` /
 * `implantologia` differ), and some legacy values name a job rather than a
 * clinical specialty (`dental_technician`, `assistant`, `finance`), so they
 * deliberately have no target. `users.specialty` stays untouched as the legacy
 * display value ADR-0007 keeps.
 */
class UserSpecialtySeeder extends Seeder
{
    /**
     * Legacy `users.specialty` value => `specialties.code`.
     *
     * @var array<string, string>
     */
    public const LEGACY_TO_SPECIALTY_CODE = [
        'general' => 'general',
        'orthodontics' => 'ortodoncia',
        'implantology' => 'implantologia',
    ];

    /**
     * Roles that may own a clinical specialty.
     *
     * @var array<int, string>
     */
    public const CLINICAL_ROLES = ['odontologo', 'implantologo', 'tecnico_dental'];

    public function run(): void
    {
        $specialties = Specialty::query()
            ->whereIn('code', array_values(self::LEGACY_TO_SPECIALTY_CODE))
            ->pluck('id', 'code');

        if ($specialties->isEmpty()) {
            $this->command?->info(
                'No hay especialidades clínicas para asignar. Ejecute SpecialtySeeder primero.'
            );

            return;
        }

        $users = User::query()
            ->whereIn('role', self::CLINICAL_ROLES)
            ->whereIn('specialty', array_keys(self::LEGACY_TO_SPECIALTY_CODE))
            ->get(['id', 'specialty']);

        $now = now();
        $assigned = 0;

        foreach ($users as $user) {
            $code = self::LEGACY_TO_SPECIALTY_CODE[$user->specialty] ?? null;
            $specialtyId = $code === null ? null : $specialties->get($code);

            if ($specialtyId === null) {
                continue;
            }

            $pivot = DB::table('user_specialties')
                ->where('user_id', $user->id)
                ->where('specialty_id', $specialtyId);

            if ($pivot->exists()) {
                $pivot->update(['is_primary' => true, 'updated_at' => $now]);
            } else {
                DB::table('user_specialties')->insert([
                    'user_id' => $user->id,
                    'specialty_id' => $specialtyId,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $assigned++;
        }

        $this->command?->info(
            "Pivote user_specialties: {$assigned} usuarios clínicos con especialidad asignada."
        );
    }
}
