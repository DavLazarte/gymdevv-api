<?php

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, BelongsToTenant;

    protected $table = 'usuarios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'apellido',
        'email',
        'password',
        'telefono',
        'direccion',
        'especialidades',
        'biografia',
        'avatar_url',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'rol' => RolUsuario::class,
        ];
    }

    public function alumno(): HasOne
    {
        return $this->hasOne(Alumno::class, 'usuario_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    public function getRolValueAttribute(): string
    {
        return $this->rol instanceof RolUsuario ? $this->rol->value : (string) $this->rol;
    }

    public function isAdmin(): bool
    {
        return $this->rol_value === RolUsuario::Admin->value;
    }

    public function isCoach(): bool
    {
        return $this->rol_value === RolUsuario::Coach->value;
    }

    public function isAlumno(): bool
    {
        return $this->rol_value === RolUsuario::Alumno->value;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isCoach();
    }
}
