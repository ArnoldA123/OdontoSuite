<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'phone',
        'specialty',
        'is_active',
        'branch_id',
        'professional_license',
        'specialties',
        'commission_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'specialties' => 'array',
            'commission_rate' => 'decimal:2',
        ];
    }

    /**
     * Get the appointments for the user (as dentist/professional).
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the work schedules for the user.
     */
    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * Get the appointment blocks for the user.
     */
    public function appointmentBlocks()
    {
        return $this->hasMany(AppointmentBlock::class);
    }

    /**
     * Get the waiting lists where the user is preferred.
     */
    public function preferredWaitingLists()
    {
        return $this->hasMany(WaitingList::class, 'preferred_user_id');
    }

    /**
     * Get the branch for the user.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user is an administrator.
     */
    public function isAdministrador(): bool
    {
        return $this->hasRole('administrador');
    }

    /**
     * Check if the user is reception.
     */
    public function isRecepcionista(): bool
    {
        return $this->hasRole('recepcionista');
    }

    /**
     * Check if the user is clinical staff (dentist, implantologist, technician, assistant).
     */
    public function isClinical(): bool
    {
        return in_array($this->role, ['odontologo', 'implantologo', 'tecnico_dental', 'asistente']);
    }

    /**
     * Check if the user is in finance.
     */
    public function isFinanzas(): bool
    {
        return $this->hasRole('finanzas');
    }

    /**
     * Check if the user can manage patients.
     */
    public function canManagePatients(): bool
    {
        return in_array($this->role, ['administrador', 'recepcionista']);
    }

    /**
     * Check if the user can manage appointments.
     */
    public function canManageAppointments(): bool
    {
        return !in_array($this->role, ['finanzas']);
    }

    /**
     * Check if the user can view reports.
     */
    public function canViewReports(): bool
    {
        return in_array($this->role, ['administrador', 'finanzas']);
    }

    /**
     * Check if the user can manage users.
     */
    public function canManageUsers(): bool
    {
        return $this->hasRole('administrador');
    }

    /**
     * Check if the user can manage system configuration.
     */
    public function canManageConfig(): bool
    {
        return $this->hasRole('administrador');
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function isAdmin(): bool
    {
        return $this->isAdministrador();
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function isReception(): bool
    {
        return $this->isRecepcionista();
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function isDentist(): bool
    {
        return $this->hasRole('odontologo');
    }

    /**
     * Get the audit logs for the user.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
