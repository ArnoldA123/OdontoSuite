<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function getEvents(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $query = Appointment::with([
            'patient:id,first_name,last_name,document_number,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ])
        ->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('scheduled_at', [$startDate, $endDate])
              ->orWhereBetween('ends_at', [$startDate, $endDate])
              ->orWhere(function($subQ) use ($startDate, $endDate) {
                  $subQ->where('scheduled_at', '<=', $startDate)
                       ->where('ends_at', '>=', $endDate);
              });
        });

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $appointments = $query->orderBy('scheduled_at')->get();

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
        ]);
    }

    public function getAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ]);

        $date = Carbon::parse($request->date);
        $userId = $request->user_id;
        $duration = $request->get('duration_minutes', 60);

        $dayOfWeek = $date->dayOfWeek;

        $workSchedules = WorkSchedule::where('user_id', $userId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($workSchedules->isEmpty()) {
            return response()->json([
                'data' => [
                    'available' => true,
                    'slots' => [],
                    'message' => 'Sin horario de trabajo configurado para este día',
                ],
            ]);
        }

        $existingAppointments = Appointment::where('user_id', $userId)
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();

        $slots = [];

        foreach ($workSchedules as $schedule) {
            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->start_time);
            $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->end_time);

            $currentTime = $startTime->copy();

            while ($currentTime->copy()->addMinutes($duration)->lte($endTime)) {
                $slotEnd = $currentTime->copy()->addMinutes($duration);

                $hasConflict = $existingAppointments->some(function ($appointment) use ($currentTime, $slotEnd) {
                    return $currentTime->lt($appointment->ends_at) && $slotEnd->gt($appointment->scheduled_at);
                });

                if (!$hasConflict) {
                    $slots[] = [
                        'start' => $currentTime->toIso8601String(),
                        'end' => $slotEnd->toIso8601String(),
                        'formatted' => $currentTime->format('H:i') . ' - ' . $slotEnd->format('H:i'),
                    ];
                }

                $currentTime->addMinutes(15);
            }
        }

        return response()->json([
            'data' => [
                'available' => true,
                'slots' => $slots,
                'date' => $date->toDateString(),
                'user_id' => $userId,
            ],
        ]);
    }
}
