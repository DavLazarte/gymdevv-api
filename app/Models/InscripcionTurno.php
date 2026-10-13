<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InscripcionTurno extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'inscripciones_turno';

    protected $fillable = [
        'tenant_id',
        'turno_id',
        'alumno_id',
        'fecha_alta',
        'fecha_baja',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date:Y-m-d',
            'fecha_baja' => 'date:Y-m-d',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }
}
