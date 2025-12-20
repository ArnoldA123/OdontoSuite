<?php

namespace App\Events;

use App\Models\MedicalRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MedicalRecordCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $medicalRecord;

    public function __construct(MedicalRecord $medicalRecord)
    {
        $this->medicalRecord = $medicalRecord;
    }

    public function broadcastOn()
    {
        return [
            new Channel('medical-records'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'medical-record.created';
    }

    public function broadcastWith()
    {
        return [
            'medical_record' => $this->medicalRecord->load('patient', 'createdBy'),
        ];
    }
}

