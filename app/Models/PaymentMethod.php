<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'gateway_type',
        'gateway_config',
        'requires_authorization',
        'allows_change',
        'commission_percentage',
        'is_active',
        'is_system'
    ];

    protected $casts = [
        'requires_authorization' => 'boolean',
        'allows_change' => 'boolean',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean'
    ];

    // Relaciones
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Accessor: encripta gateway_config antes de persistir.
     * Sprint 2: las credenciales de pasarela (Mercado Pago access_token,
     * etc.) son sensibles y NO deben quedar en texto plano en la BD.
     * Crypt::encryptString usa APP_KEY como llave.
     *
     * Cuando gateway_config es null (metodos manuales como Efectivo),
     * dejamos null sin encriptar.
     */
    public function setGatewayConfigAttribute($value)
    {
        if (is_null($value) || $value === '') {
            $this->attributes['gateway_config'] = null;
            return;
        }

        // Si ya viene encriptado (string largo), no encriptar de nuevo
        if (is_string($value) && str_starts_with($value, 'eyJ')) {
            $this->attributes['gateway_config'] = $value;
            return;
        }

        // Si es array, convertir a JSON y encriptar
        $json = is_string($value) ? $value : json_encode($value);
        $this->attributes['gateway_config'] = Crypt::encryptString($json);
    }

    /**
     * Accessor: desencripta gateway_config al leer.
     * Devuelve array si el valor desencriptado es JSON valido, o el
     * string desencriptado tal cual si no es JSON.
     */
    public function getGatewayConfigAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($value);
            $decoded = json_decode($decrypted, true);
            return is_array($decoded) ? $decoded : $decrypted;
        } catch (\Exception $e) {
            // Si falla la desencriptacion (dato corrupto o APP_KEY rotada),
            // devolver el valor crudo para no romper el flujo.
            return $value;
        }
    }

    /**
     * Helper: indica si este metodo requiere credenciales de pasarela.
     * Usado por la UI admin para mostrar/ocultar campos de credenciales.
     */
    public function hasGateway(): bool
    {
        return !is_null($this->gateway_type) && $this->gateway_type !== 'manual';
    }
}
