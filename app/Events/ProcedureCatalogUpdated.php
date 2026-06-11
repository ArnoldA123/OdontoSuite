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
 * Sprint 3 fix (IM-7): se dispara cuando un procedimiento es actualizado
 * (en create o update). El listener TrackProcedureVersion crea una fila
 * en procedure_catalog_versions con los campos relevantes.
 */
class ProcedureCatalogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $changedFields
     */
    public function __construct(
        public readonly ProcedureCatalog $procedure,
        public readonly string $changeType, // 'created' | 'updated' | 'deactivated'
        public readonly array $changedFields,
        public readonly ?User $changedBy,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel('procedure-catalog')];
    }

    public function broadcastAs(): string
    {
        return 'procedure.catalog.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'procedure_id' => $this->procedure->id,
            'change_type' => $this->changeType,
            'changed_fields' => array_keys($this->changedFields),
            'changed_by' => $this->changedBy?->id,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
