<?php

use Illuminate\Support\Facades\Broadcast;

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
// Acceso limitado a roles clínicos/administrativos autenticados; el
// recepcionista también necesita ver el check-in para el flujo diario.
Broadcast::channel('private-appointment.{appointmentId}', function ($user, $appointmentId) {
    if ($user === null) {
        return false;
    }
    $allowed = ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'recepcionista'];
    return in_array($user->role ?? null, $allowed, true);
});

// Slice 10 (T-10.3 + T-10.4): private channel para PaymentReceived por
// sucursal. Restringido a roles con permiso financiero.
Broadcast::channel('private-cash-register.{branchId}', function ($user, $branchId) {
    if ($user === null) {
        return false;
    }
    return in_array($user->role ?? null, ['administrador', 'finanzas', 'recepcionista'], true);
});
