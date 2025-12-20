<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $stats;

    public function __construct(array $stats = null)
    {
        $this->stats = $stats;
    }

    public function broadcastOn()
    {
        return [
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'dashboard.stats-updated';
    }

    public function broadcastWith()
    {
        return [
            'stats' => $this->stats,
        ];
    }
}

