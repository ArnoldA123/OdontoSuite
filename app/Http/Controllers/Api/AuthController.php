<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user and return token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        $credentials = $request->only('username', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user = Auth::user();

        // Verificar si el usuario está activo
        if (!$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'username' => ['Tu cuenta ha sido desactivada. Contacta al administrador.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
            'meta' => [
                'message' => 'Inicio de sesión exitoso',
            ],
        ]);
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => null,
            'meta' => [
                'message' => 'Sesión cerrada exitosamente',
            ],
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Send password reset link to user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'No encontramos un usuario con ese correo electrónico.',
                ], 404);
            }

            // Generate reset token
            $token = Str::random(64);
            
            // Store token in password_reset_tokens table
            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // Sprint 0 fix (NF-4): envío real de email implementado. Con
            // MAIL_MAILER=log (default dev) el email se escribe a storage/logs/laravel.log.
            // En producción, configurar MAIL_MAILER=smtp + MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD.
            try {
                Mail::to($user->email)->send(new PasswordResetMail($user, $token));
                Log::info('Password reset email sent to: ' . $user->email);
            } catch (\Exception $e) {
                Log::error('Failed to send password reset email to ' . $user->email . ': ' . $e->getMessage());
            }

            $response = [
                'data' => [
                    'message' => 'Si existe una cuenta con ese correo electrónico, hemos enviado un enlace de recuperación.',
                ],
                'meta' => [
                    'message' => 'Enlace de recuperación enviado',
                ],
            ];

            if (config('app.debug')) {
                $response['debug'] = [
                    'token' => $token,
                    'email' => $user->email,
                    'mail_driver' => config('mail.default'),
                    'note' => 'Token visible solo en APP_DEBUG=true. En producción se omite.',
                ];
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in forgotPassword: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar la solicitud. Por favor, intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Find the password reset record
            $resetRecord = \DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return response()->json([
                    'message' => 'Token de recuperación inválido o expirado.',
                ], 400);
            }

            // Check if token is valid (within 60 minutes)
            $createdAt = Carbon::parse($resetRecord->created_at);
            if ($createdAt->diffInMinutes(now()) > 60) {
                \DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'message' => 'El token de recuperación ha expirado. Por favor, solicita uno nuevo.',
                ], 400);
            }

            // Verify token
            if (!Hash::check($request->token, $resetRecord->token)) {
                return response()->json([
                    'message' => 'Token de recuperación inválido.',
                ], 400);
            }

            // Update user password
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the reset token
            \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            Log::info('Password reset successful for user: ' . $user->email);

            return response()->json([
                'data' => [
                    'message' => 'Contraseña actualizada exitosamente.',
                ],
                'meta' => [
                    'message' => 'Contraseña restablecida',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in resetPassword: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al restablecer la contraseña. Por favor, intenta nuevamente.',
            ], 500);
        }
    }
}
