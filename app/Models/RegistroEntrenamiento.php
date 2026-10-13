<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistroEntrenamiento extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'registros_entrenamiento';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'ejercicio_id',
        'dia_ejercicio_id',
        'fecha',
        'peso',
        'repeticiones',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'peso' => 'float',
            'repeticiones' => 'integer',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }
}
