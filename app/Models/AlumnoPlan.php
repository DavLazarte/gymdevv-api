<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlumnoPlan extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'alumno_planes';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'plan_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEntrenamiento::class, 'plan_id');
    }
}
