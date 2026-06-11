<?php

namespace App\Listeners;

use App\Events\ProcedureCatalogDeactivated;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 3 fix (IM-4): registra en log la cantidad de clinicos notificados
 * cuando un procedimiento se desactiva. El frontend escucha el broadcast
 * Reverb en el canal 'procedure-catalog' y muestra un toast.
 */
class NotifyProcedureDeactivation
{
    public function handle(ProcedureCatalogDeactivated $event): void
    {
        $count = 0;
        foreach ($event->notifiedUsers as $u) {
            $count++;
        }

        Log::info('Procedure catalog item deactivated', [
            'procedure_id' => $event->procedure->id,
            'procedure_code' => $event->procedure->code,
            'procedure_name' => $event->procedure->name,
            'deactivated_by' => $event->deactivatedBy?->id,
            'notified_count' => $count,
        ]);
    }
}
