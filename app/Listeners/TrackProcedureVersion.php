<?php

namespace App\Listeners;

use App\Events\ProcedureCatalogUpdated;
use App\Models\ProcedureCatalogVersion;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 3 fix (IM-7): persiste un snapshot en procedure_catalog_versions
 * cada vez que se crea, actualiza o desactiva un procedimiento. El snapshot
 * incluye los campos sensibles (default_cost, name, is_active) y la lista
 * de campos cambiados.
 */
class TrackProcedureVersion
{
    public function handle(ProcedureCatalogUpdated $event): void
    {
        try {
            ProcedureCatalogVersion::create([
                'procedure_catalog_id' => $event->procedure->id,
                'changed_by' => $event->changedBy?->id,
                'change_type' => $event->changeType,
                'changed_fields' => $event->changedFields,
                'default_cost' => $event->procedure->default_cost,
                'name' => $event->procedure->name,
                'code' => $event->procedure->code,
                'is_active' => $event->procedure->is_active,
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track procedure version', [
                'procedure_id' => $event->procedure->id,
                'change_type' => $event->changeType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
