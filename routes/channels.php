<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Mirror documentation only.
 *
 * The effective broadcasting authorization authority is
 * App\Http\Controllers\Api\BroadcastingAuthController (the handler bound to
 * POST /api/broadcasting/auth). This project does not call Broadcast::routes(),
 * so the registrations below are never executed at runtime; they document the
 * channel names and role rules that the controller enforces.
 */

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para sesiones de caja específicas
Broadcast::channel('cash-session.{sessionId}', function ($user, $sessionId) {
    // Verificar que el usuario tenga acceso a esta sesión
    // Por ahora permitimos acceso si el usuario está autenticado
    return $user !== null;
});

// Slice 10 (T-10.4): private channel para AppointmentCheckedIn.
// Base name registrada: appointment.{appointmentId}. Laravel antepone el
// prefijo del canal, por lo que el nombre en el cable es el canal privado
// private-appointment.{appointmentId} que consume el frontend.
// Acceso limitado a roles clínicos/administrativos autenticados; el
// recepcionista también necesita ver el check-in para el flujo diario.
Broadcast::channel('appointment.{appointmentId}', function ($user, $appointmentId) {
    if ($user === null) {
        return false;
    }
    $allowed = ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'recepcionista'];
    return in_array($user->role ?? null, $allowed, true);
});

// Slice 10 (T-10.3 + T-10.4): private channel para PaymentReceived por
// sucursal. Base name registrada: cash-register.{branchId}; el nombre en el
// cable es el canal privado private-cash-register.{branchId}. Restringido a
// roles con permiso financiero.
Broadcast::channel('cash-register.{branchId}', function ($user, $branchId) {
    if ($user === null) {
        return false;
    }
    return in_array($user->role ?? null, ['administrador', 'finanzas', 'recepcionista'], true);
});
