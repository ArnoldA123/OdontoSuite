<?php

namespace App\Listeners;

use App\Services\Reports\DashboardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClearDashboardCache
{
    /**
     * Handle the event and clear dashboard cache.
     * This method can be called from event handlers or directly from controllers.
     */
    public static function handle(): void
    {
        try {
            // Limpiar caché del servicio de dashboard si existe
            try {
                $dashboardService = app(DashboardService::class);
                $dashboardService->clearCache();
            } catch (\Exception $e) {
                // El servicio puede no existir, continuar
            }
            
            // Limpiar todas las claves de caché del dashboard
            // Usar tags si están disponibles, sino limpiar por patrón
            Cache::flush(); // En producción, usar tags o patrón específico
            
            Log::debug('Dashboard cache cleared due to data change');
        } catch (\Exception $e) {
            Log::error('Error clearing dashboard cache: ' . $e->getMessage());
        }
    }
}

