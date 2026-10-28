<?php

namespace App\Services;

use App\Events\LabCaseReady;
use App\Models\LabCase;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * F5 (#285, S1/S2/S4) — the prosthetic laboratory case.
 *
 * The case is the laboratory's work order and this service is the only writer
 * of its phase. The machine only moves forward (S2), so a case advances to any
 * later phase — a simple job skips the try-in — and never goes back, because a
 * workshop does not un-deliver work it already returned.
 *
 * Entering `delivered` means the case is ready: it stamps the delivery and
 * notifies the professional and reception (S4). `placed` is a later clinical
 * act and stays silent.
 */
class LabCaseService
{
    /**
     * Roles that prescribe prosthetic work: the professional the case belongs
     * to. Reading the record is not required to fill this field.
     *
     * @var list<string>
     */
    public const PROFESSIONAL_ROLES = ['odontologo', 'implantologo'];

    /**
     * Roles that own the laboratory desk and receive the assigned case.
     *
     * @var list<string>
     */
    public const TECHNICIAN_ROLES = ['tecnico_dental'];

    /**
     * Fields a client may write. `status` is deliberately absent: the phase
     * only moves through `transition()`.
     *
     * @var list<string>
     */
    private const WRITABLE = [
        'patient_id',
        'branch_id',
        'professional_id',
        'technician_id',
        'dental_piece_id',
        'work',
        'material',
        'sent_at',
        'promised_at',
        'notes',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): LabCase
    {
        $professionalId = isset($data['professional_id']) ? (int) $data['professional_id'] : $actor->id;

        $this->assertProfessional($professionalId);
        $this->assertTechnician($data['technician_id'] ?? null);

        $attributes = Arr::only($data, self::WRITABLE);
        $attributes['professional_id'] = $professionalId;
        $attributes['created_by'] = $actor->id;

        return LabCase::create($attributes)->refresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(LabCase $case, array $data): LabCase
    {
        // #449: placed work is finished work. The phase is not editable through
        // this door anyway, so a placed case has nothing left to correct.
        if ($case->status === LabCase::PHASE_PLACED) {
            throw ValidationException::withMessages([
                'status' => ['No se puede editar un caso de laboratorio ya colocado: el trabajo concluyó.'],
            ]);
        }

        $attributes = Arr::only($data, self::WRITABLE);

        if (array_key_exists('professional_id', $attributes)) {
            if ($attributes['professional_id'] === null) {
                unset($attributes['professional_id']);
            } else {
                $this->assertProfessional((int) $attributes['professional_id']);
            }
        }

        if (array_key_exists('technician_id', $attributes)) {
            $this->assertTechnician($attributes['technician_id']);
        }

        $case->fill($attributes)->save();

        return $case->refresh();
    }

    /**
     * Move the case forward. Same or earlier phase, and an unknown phase, are
     * rejected with 422 and leave the case untouched.
     */
    public function transition(LabCase $case, string $phase): LabCase
    {
        $target = LabCase::phaseIndex($phase);

        if ($target === null) {
            throw ValidationException::withMessages([
                'phase' => [sprintf(
                    'La fase «%s» no existe. Las fases válidas son: %s.',
                    $phase,
                    implode(', ', LabCase::PHASES),
                )],
            ]);
        }

        $updated = DB::transaction(function () use ($case, $phase, $target) {
            $current = LabCase::query()
                ->whereKey($case->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentIndex = LabCase::phaseIndex($current->status);

            if ($currentIndex === null) {
                throw ValidationException::withMessages([
                    'phase' => ["El caso de laboratorio está en una fase desconocida ({$current->status})."],
                ]);
            }

            if ($target === $currentIndex) {
                throw ValidationException::withMessages([
                    'phase' => ["El caso de laboratorio ya está en la fase «{$current->status}»."],
                ]);
            }

            if ($target < $currentIndex) {
                throw ValidationException::withMessages([
                    'phase' => [
                        "No se puede retroceder la fase del caso de laboratorio: está en «{$current->status}» y se pidió «{$phase}».",
                    ],
                ]);
            }

            $attributes = ['status' => $phase];

            if ($phase === LabCase::PHASE_DELIVERED && $current->delivered_at === null) {
                $attributes['delivered_at'] = now();
            }

            if ($phase === LabCase::PHASE_PLACED && $current->placed_at === null) {
                $attributes['placed_at'] = now();
            }

            $current->forceFill($attributes)->save();

            return $current->refresh();
        });

        if ($phase === LabCase::PHASE_DELIVERED) {
            $this->dispatchReady($updated);
        }

        return $updated;
    }

    private function assertProfessional(?int $professionalId): void
    {
        $valid = $professionalId !== null && User::query()
            ->whereKey($professionalId)
            ->where('is_active', true)
            ->whereIn('role', self::PROFESSIONAL_ROLES)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'professional_id' => ['El profesional asignado debe ser un odontólogo o implantólogo activo.'],
            ]);
        }
    }

    /**
     * A case may be born without an assigned technician: not every workshop
     * hands the job out when the work arrives.
     */
    private function assertTechnician(mixed $technicianId): void
    {
        if ($technicianId === null) {
            return;
        }

        $valid = User::query()
            ->whereKey((int) $technicianId)
            ->where('is_active', true)
            ->whereIn('role', self::TECHNICIAN_ROLES)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'technician_id' => ['El técnico asignado debe ser un técnico dental activo.'],
            ]);
        }
    }

    /**
     * AGENTS.md §7: a failing broadcast must never break the phase change.
     */
    private function dispatchReady(LabCase $case): void
    {
        try {
            event(new LabCaseReady(
                $case->loadMissing(['patient', 'professional', 'technician', 'dentalPiece'])
            ));
        } catch (Throwable $e) {
            Log::warning('No se pudo emitir el aviso de caso de laboratorio listo', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
