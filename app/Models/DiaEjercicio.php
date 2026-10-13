<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiaEjercicio extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'dia_ejercicios';

    protected $fillable = [
        'tenant_id',
        'dia_id',
        'ejercicio_id',
        'orden',
        'series',
        'repeticiones',
        'peso',
        'descanso',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'series' => 'integer',
            'peso' => 'float',
            'descanso' => 'integer',
        ];
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'dia_id');
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }
}
