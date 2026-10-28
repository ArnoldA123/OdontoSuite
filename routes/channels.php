<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Mirror documentation only.
 *
 * The effective broadcasting authorization authority is
 * App\Http\Controllers\Api\BroadcastingAuthController (the handler bound to
 * POST /api/broadcasting/auth). This project does not call Broadcast::routes(),
 * so the registrations below are never executed at runtime; they document the
 * channel names and the effective rules.
 *
 * Issue #378/#384: the channel => role rule itself lives once, in
 * App\Authorization\BroadcastChannels, as a channel => permission map resolved
 * against App\Authorization\Permissions. The role lists below are the same rule
 * written out as documentation; changing one without the other is drift.
 */

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado del usuario exportador (#249): PatientFileExported avisa a
// quien lanzó la exportación. Solo el propio usuario entra.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado del panel de operaciones: emite citas, pacientes,
// transacciones y estadísticas de todo el dominio, así que la suscripción
// exige el handshake de /api/broadcasting/auth y un rol con acceso al
// dashboard. Un rol nuevo queda fuera hasta que se agregue explícitamente
// (issue #183).
Broadcast::channel('dashboard-updates', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo', 'implantologo',
        'tecnico_dental', 'asistente', 'finanzas',
    ], true);
});

// Canal privado del paciente: la lista, la ficha, el modal de cita y las
// notificaciones globales reciben aquí datos clínicos (medical_history,
// allergies), por eso el payload viaja solo por un canal privado y la
// suscripción exige el handshake de /api/broadcasting/auth. Todos los roles
// autenticados pueden ver pacientes (PatientPolicy::viewAny).
Broadcast::channel('patients', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo', 'implantologo',
        'tecnico_dental', 'asistente', 'finanzas',
    ], true);
});

// Canal privado de presupuestos: el payload de lectura lleva el modelo
// completo del presupuesto (datos del paciente y montos), por eso viaja solo
// por un canal privado y la suscripción exige el handshake de
// /api/broadcasting/auth. Los roles son los mismos que autorizan la lectura
// en routes/api.php.
Broadcast::channel('quotations', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'finanzas', 'odontologo', 'implantologo', 'recepcionista',
    ], true);
});

// Canales de dominio cerrados en #249. Los eventos publicaban payloads de
// citas, historia clínica, caja, planes, transacciones, especialidades,
// catálogo y usuarios en canales públicos; un canal público no verifica
// identidad. Cada canal exige ahora el handshake de
// /api/broadcasting/auth con los mismos roles que autorizan su lectura en
// routes/api.php. Un rol nuevo queda fuera hasta que se agregue a ambas
// listas (App\Http\Controllers\Api\BroadcastingAuthController es la
// autoridad efectiva).
Broadcast::channel('appointments', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo',
        'implantologo', 'tecnico_dental', 'asistente',
    ], true);
});

Broadcast::channel('reminders', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo',
        'implantologo', 'tecnico_dental', 'asistente',
    ], true);
});

Broadcast::channel('medical-records', function ($user) {
    if ($user === null) {
        return false;
    }

    // F5 (#285): the channel publishes the evolution, the addenda and the
    // attachments, so the laboratory desk is out of it.
    return in_array($user->role ?? null, [
        'administrador', 'odontologo', 'implantologo', 'asistente',
    ], true);
});

Broadcast::channel('specialty-records', function ($user) {
    if ($user === null) {
        return false;
    }

    // #313 (decision D1): the channel publishes the whole specialty record,
    // surgical narrative included, so the laboratory desk is out of it and its
    // prosthetic case (`lab-cases`) carries what the workshop needs.
    return in_array($user->role ?? null, [
        'administrador', 'odontologo', 'implantologo',
    ], true);
});

Broadcast::channel('treatment-plans', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'odontologo', 'implantologo', 'tecnico_dental',
    ], true);
});

Broadcast::channel('cash-register', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, ['administrador', 'finanzas', 'recepcionista'], true);
});

Broadcast::channel('transactions', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, ['administrador', 'finanzas', 'recepcionista'], true);
});

Broadcast::channel('procedure-catalog', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo', 'implantologo',
        'tecnico_dental', 'asistente', 'finanzas',
    ], true);
});

Broadcast::channel('users', function ($user) {
    if ($user === null) {
        return false;
    }

    return ($user->role ?? null) === 'administrador';
});

// Canal privado para sesiones de caja específicas
Broadcast::channel('cash-session.{sessionId}', function ($user, $sessionId) {
    // El canal de sesión publica cobros y movimientos del ledger, así que
    // exige los mismos roles que operan la caja (#249). Antes aceptaba a
    // cualquier usuario autenticado.
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, ['administrador', 'finanzas', 'recepcionista'], true);
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

// Canal privado del laboratorio protésico (F5, #285): LabCaseReady publica el
// caso entregado con paciente, profesional y técnico asignado. La suscripción
// exige el handshake de /api/broadcasting/auth y los mismos roles que autorizan
// la lectura del caso en routes/api.php; finanzas queda fuera.
Broadcast::channel('lab-cases', function ($user) {
    if ($user === null) {
        return false;
    }

    return in_array($user->role ?? null, [
        'administrador', 'recepcionista', 'odontologo',
        'implantologo', 'tecnico_dental', 'asistente',
    ], true);
});
