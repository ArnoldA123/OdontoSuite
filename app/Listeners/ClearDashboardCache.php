<?php

namespace App\Listeners;

use App\Services\Reports\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClearDashboardCache
{
    /**
     * Invalida solo las claves de caché del dashboard (sin vaciar toda la caché de la app).
     */
    public static function handle(): void
    {
        try {
            $userId = Auth::id();
            if ($userId) {
                $today = Carbon::today()->toDateString();
                $week = Carbon::now()->format('Y-W');

                Cache::forget("dashboard_stats_{$userId}_all");
                Cache::forget("dashboard_today_{$userId}_{$today}_all");
                Cache::forget("dashboard_upcoming_{$userId}_{$week}_all");
            }

            try {
                app(DashboardService::class)->clearCache();
            } catch (\Exception $e) {
                // El servicio puede no estar registrado en algunos entornos
            }

            Log::debug('Dashboard cache cleared due to data change');
        } catch (\Exception $e) {
            Log::error('Error clearing dashboard cache: ' . $e->getMessage());
        }
    }
}
