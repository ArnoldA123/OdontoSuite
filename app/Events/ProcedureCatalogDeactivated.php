<?php

namespace App\Events;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 3 fix (IM-4): se dispara cuando un administrador desactiva un
 * procedimiento del catalogo. Notifica a los clinicos que lo tienen como
 * favorito y transmite por Reverb en el canal 'dashboard-updates' para
 * que cualquier admin/clinico viendo el modulo reciba el toast.
 */
class ProcedureCatalogDeactivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $notifiedUsers
     */
    public function __construct(
        public readonly ProcedureCatalog $procedure,
        public readonly ?User $deactivatedBy,
        public readonly iterable $notifiedUsers,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard-updates'),
            new Channel('procedure-catalog'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'procedure.catalog.deactivated';
    }

    public function broadcastWith(): array
    {
        $notifiedIds = [];
        foreach ($this->notifiedUsers as $u) {
            $notifiedIds[] = $u->id;
        }

        return [
            'procedure_id' => $this->procedure->id,
            'procedure_code' => $this->procedure->code,
            'procedure_name' => $this->procedure->name,
            'deactivated_by' => $this->deactivatedBy?->id,
            'deactivated_at' => now()->toIso8601String(),
            'notified_user_ids' => $notifiedIds,
            'notified_count' => count($notifiedIds),
        ];
    }
}
