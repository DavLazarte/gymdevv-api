<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asistencia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'asistencias';

    protected $fillable = [
        'tenant_id',
        'grupo_id',
        'turno_id',
        'alumno_id',
        'fecha',
        'hora_entrada',
        'fecha_asistencia',
        'presente',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'fecha_asistencia' => 'datetime',
            'presente' => 'boolean',
        ];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
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
