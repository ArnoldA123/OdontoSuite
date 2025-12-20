<?php

namespace App\Services;

use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Get active professionals with cache
     */
    public static function getActiveProfessionals(): array
    {
        return Cache::remember('active_professionals', 600, function () {
            return User::where('role', 'odontologo')
                ->where('is_active', true)
                ->select('id', 'name', 'specialty', 'email')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get active dental chairs with cache
     */
    public static function getActiveDentalChairs(): array
    {
        return Cache::remember('active_dental_chairs', 600, function () {
            return DentalChair::where('is_active', true)
                ->select('id', 'name', 'code', 'description', 'equipment', 'status')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get active appointment types with cache
     */
    public static function getActiveAppointmentTypes(): array
    {
        return Cache::remember('active_appointment_types', 600, function () {
            return AppointmentType::where('is_active', true)
                ->select('id', 'name', 'description', 'default_duration_minutes', 'price', 'color', 'requires_confirmation')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Clear all reference data cache
     */
    public static function clearReferenceDataCache(): void
    {
        Cache::forget('active_professionals');
        Cache::forget('active_dental_chairs');
        Cache::forget('active_appointment_types');
    }

    /**
     * Clear dashboard cache
     */
    public static function clearDashboardCache(): void
    {
        $today = now()->toDateString();
        Cache::forget("dashboard_stats_{$today}");

        // Clear dashboard data cache (this will clear all variations)
        $keys = Cache::getRedis()->keys('*dashboard_data_*');
        foreach ($keys as $key) {
            Cache::forget(str_replace(config('cache.prefix'), '', $key));
        }
    }

    /**
     * Clear all cache
     */
    public static function clearAllCache(): void
    {
        Cache::flush();
    }
}
