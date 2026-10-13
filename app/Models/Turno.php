<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turno extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'turnos';

    protected $fillable = [
        'tenant_id',
        'grupo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'cupo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'cupo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionTurno::class, 'turno_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'turno_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'turno_id');
    }

    public function getCupoEfectivoAttribute(): int
    {
        return (int) ($this->cupo ?? $this->grupo?->cupo_default ?? 20);
    }
}
