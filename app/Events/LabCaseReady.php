<?php

namespace App\Events;

use App\Models\LabCase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * F5 (#285, S4) — `caso_laboratorio_listo`.
 *
 * The work came back from the workshop: the case entered `delivered` and can
 * be placed. Reception and the prescribing professional learn it here. The
 * case carries no clinical content (S3), so the payload is the work order and
 * the people around it — never the record.
 *
 * It travels on the laboratory channel (the desk that moves the phases) and on
 * the professional's own private channel, which only that user can subscribe
 * to.
 */
class LabCaseReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LabCase $case) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('lab-cases'),
            new PrivateChannel('user.'.$this->case->professional_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'caso_laboratorio_listo';
    }

    public function broadcastWith(): array
    {
        $case = $this->case->loadMissing(['patient', 'professional', 'technician', 'dentalPiece']);
        $patient = $case->patient;

        return [
            'case' => [
                'id' => $case->id,
                'work' => $case->work,
                'material' => $case->material,
                'status' => $case->status,
                'sent_at' => $case->sent_at?->toDateString(),
                'promised_at' => $case->promised_at?->toDateString(),
                'delivered_at' => $case->delivered_at?->toIso8601String(),
            ],
            'patient' => [
                'id' => $case->patient_id,
                'full_name' => trim(($patient?->first_name ?? '').' '.($patient?->last_name ?? '')),
            ],
            'professional' => [
                'id' => $case->professional_id,
                'name' => $case->professional?->name,
            ],
            'technician' => $case->technician === null ? null : [
                'id' => $case->technician->id,
                'name' => $case->technician->name,
            ],
            'piece' => $case->dentalPiece === null ? null : [
                'id' => $case->dentalPiece->id,
                'fdi_number' => $case->dentalPiece->fdi_number,
            ],
        ];
    }
}
