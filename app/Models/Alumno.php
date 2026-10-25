<?php

namespace App\Models;

use App\Enums\EstadoAlumno;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alumno extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'alumnos';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'dni',
        'direccion',
        'fecha_nacimiento',
        'fecha_alta',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
            'fecha_alta' => 'date:Y-m-d',
        ];
    }

    public function getEstadoAttribute($value): string
    {
        if ($value instanceof EstadoAlumno) {
            return $value->value;
        }

        return (string) ($value ?: 'activo');
    }

    public function getEstadoValueAttribute(): string
    {
        return $this->estado;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class, 'alumno_id');
    }

    public function membresiaActiva(): HasOne
    {
        return $this->hasOne(Membresia::class, 'alumno_id')
            ->where('estado', 'activa')
            ->latest('id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'alumno_id');
    }

    public function movimientosCredito(): HasMany
    {
        return $this->hasMany(MovimientoCredito::class, 'alumno_id');
    }

    public function inscripcionesTurno(): HasMany
    {
        return $this->hasMany(InscripcionTurno::class, 'alumno_id')
            ->where('estado', 'activa');
    }
}
