<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #2563eb;">{{ config('app.name') }}</h1>
    <p>Hola <strong>{{ $userName }}</strong>,</p>
    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
    <p>Para continuar, hacé click en el siguiente enlace (válido por {{ $expiresInMinutes }} minutos):</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="background-color: #2563eb; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
            Restablecer mi contraseña
        </a>
    </p>
    <p>Si no solicitaste este cambio, podés ignorar este mensaje. Tu contraseña actual seguirá siendo válida.</p>
    <hr style="margin-top: 32px; border: none; border-top: 1px solid #e5e7eb;">
    <p style="color: #6b7280; font-size: 12px;">Este es un email automático, por favor no respondas.</p>
</body>
</html>
