<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * Get appointments for a specific day.
     */
    public function getDayAppointments(Carbon $date, ?int $userId = null): Collection
    {
        $query = Appointment::with([
            'patient:id,first_name,last_name,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ])
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get appointments for a specific week.
     */
    public function getWeekAppointments(Carbon $date, ?int $userId = null): Collection
    {
        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();

        $query = Appointment::with([
            'patient:id,first_name,last_name,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ])
            ->whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get appointments for a specific month.
     */
    public function getMonthAppointments(Carbon $date, ?int $userId = null): Collection
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $query = Appointment::with([
            'patient:id,first_name,last_name,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ])
            ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get calendar data formatted for FullCalendar.
     */
    public function getCalendarData(Carbon $startDate, Carbon $endDate, ?int $userId = null): array
    {
        $appointments = $this->getAppointmentsInRange($startDate, $endDate, $userId);

        return $appointments->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'title' => $appointment->patient->full_name . ' - ' . $appointment->appointmentType->name,
                'start' => $appointment->scheduled_at->toISOString(),
                'end' => $appointment->ends_at->toISOString(),
                'backgroundColor' => $appointment->appointmentType->color,
                'borderColor' => $appointment->appointmentType->color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'patient' => [
                        'id' => $appointment->patient->id,
                        'name' => $appointment->patient->full_name,
                        'phone' => $appointment->patient->phone,
                        'email' => $appointment->patient->email,
                    ],
                    'user' => [
                        'id' => $appointment->user->id,
                        'name' => $appointment->user->name,
                    ],
                    'dental_chair' => [
                        'id' => $appointment->dentalChair->id,
                        'name' => $appointment->dentalChair->name,
                    ],
                    'appointment_type' => [
                        'id' => $appointment->appointmentType->id,
                        'name' => $appointment->appointmentType->name,
                        'duration' => $appointment->appointmentType->default_duration_minutes,
                        'price' => $appointment->appointmentType->price,
                    ],
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'duration_minutes' => $appointment->duration_minutes,
                ],
            ];
        })->toArray();
    }

    /**
     * Get appointments in a date range.
     */
    public function getAppointmentsInRange(Carbon $startDate, Carbon $endDate, ?int $userId = null): Collection
    {
        $query = Appointment::with([
            'patient:id,first_name,last_name,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ])
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get calendar statistics for a date range.
     */
    public function getCalendarStats(Carbon $startDate, Carbon $endDate, ?int $userId = null): array
    {
        $query = Appointment::whereBetween('scheduled_at', [$startDate, $endDate]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $totalAppointments = $query->count();
        $completedAppointments = $query->where('status', 'completed')->count();
        $cancelledAppointments = $query->where('status', 'cancelled')->count();
        $noShowAppointments = $query->where('status', 'no_show')->count();

        return [
            'total' => $totalAppointments,
            'completed' => $completedAppointments,
            'cancelled' => $cancelledAppointments,
            'no_show' => $noShowAppointments,
            'completion_rate' => $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0,
        ];
    }

    /**
     * Get busy times for a specific date and user.
     */
    public function getBusyTimes(Carbon $date, int $userId): array
    {
        $appointments = Appointment::where('user_id', $userId)
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();

        return $appointments->map(function ($appointment) {
            return [
                'start' => $appointment->scheduled_at->format('H:i'),
                'end' => $appointment->ends_at->format('H:i'),
                'title' => $appointment->patient->full_name,
            ];
        })->toArray();
    }

    /**
     * Get available time slots for a specific date and user.
     */
    public function getAvailableSlots(Carbon $date, int $userId, int $duration = 60): array
    {
        $appointmentService = new AppointmentService();
        return $appointmentService->getAvailableTimeSlots($userId, $date, $duration);
    }

    /**
     * Get calendar view data with filters.
     */
    public function getCalendarViewData(string $view, Carbon $date, array $filters = []): array
    {
        $userId = $filters['user_id'] ?? null;
        $appointmentTypeId = $filters['appointment_type_id'] ?? null;
        $status = $filters['status'] ?? null;

        switch ($view) {
            case 'day':
                $appointments = $this->getDayAppointments($date, $userId);
                break;
            case 'week':
                $appointments = $this->getWeekAppointments($date, $userId);
                break;
            case 'month':
                $appointments = $this->getMonthAppointments($date, $userId);
                break;
            default:
                $appointments = collect();
        }

        // Apply additional filters
        if ($appointmentTypeId) {
            $appointments = $appointments->where('appointment_type_id', $appointmentTypeId);
        }

        if ($status) {
            $appointments = $appointments->where('status', $status);
        }

        return [
            'appointments' => $appointments,
            'calendar_data' => $this->formatForCalendar($appointments),
            'stats' => $this->getCalendarStats($date->copy()->startOfDay(), $date->copy()->endOfDay(), $userId),
        ];
    }

    /**
     * Format appointments for calendar display.
     */
    private function formatForCalendar(Collection $appointments): array
    {
        return $appointments->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'title' => $appointment->patient->full_name,
                'start' => $appointment->scheduled_at->toISOString(),
                'end' => $appointment->ends_at->toISOString(),
                'backgroundColor' => $appointment->appointmentType->color,
                'borderColor' => $appointment->appointmentType->color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'patient_name' => $appointment->patient->full_name,
                    'patient_phone' => $appointment->patient->phone,
                    'appointment_type' => $appointment->appointmentType->name,
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                ],
            ];
        })->toArray();
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(?int $userId = null): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $query = Appointment::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $todayAppointments = $query->whereDate('scheduled_at', $today)->count();
        $tomorrowAppointments = $query->whereDate('scheduled_at', $tomorrow)->count();
        $weekAppointments = $query->whereBetween('scheduled_at', [$weekStart, $weekEnd])->count();

        return [
            'today' => $todayAppointments,
            'tomorrow' => $tomorrowAppointments,
            'this_week' => $weekAppointments,
            'pending_confirmation' => $query->where('status', 'scheduled')->count(),
            'completed_today' => $query->whereDate('scheduled_at', $today)->where('status', 'completed')->count(),
        ];
    }
}
