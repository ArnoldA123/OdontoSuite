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
