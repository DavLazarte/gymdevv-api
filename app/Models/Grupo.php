<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'grupos';

    protected $fillable = [
        'tenant_id',
        'coach_id',
        'nombre',
        'modalidad',
        'descripcion',
        'dias_semana',
        'hora_inicio',
        'hora_fin',
        'duracion_minutos',
        'cupo_default',
        'minutos_limite_reserva',
        'minutos_limite_cancelacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'duracion_minutos' => 'integer',
            'cupo_default' => 'integer',
            'minutos_limite_reserva' => 'integer',
            'minutos_limite_cancelacion' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'coach_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'grupo_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'grupo_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'grupo_id');
    }
}
