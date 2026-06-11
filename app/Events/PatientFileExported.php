<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientFileExported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Patient $patient,
        public readonly ?User $user,
        public readonly string $format,
        public readonly string $filePath,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('dashboard-updates')];
        if ($this->user) {
            $channels[] = new Channel('user.' . $this->user->id);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'patient.file.exported';
    }

    public function broadcastWith(): array
    {
        return [
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->full_name ?? "{$this->patient->first_name} {$this->patient->last_name}",
            'format' => $this->format,
            'file_path' => $this->filePath,
            'user_id' => $this->user?->id,
        ];
    }
}
