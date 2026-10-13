<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanEntrenamiento extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'planes';

    protected $fillable = [
        'tenant_id',
        'coach_id',
        'grupo_id',
        'nombre',
        'tipo',
        'descripcion',
        'color',
        'icono',
        'estado',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'coach_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function semanas(): HasMany
    {
        return $this->hasMany(Semana::class, 'plan_id')->orderBy('numero');
    }

    public function alumnoPlanes(): HasMany
    {
        return $this->hasMany(AlumnoPlan::class, 'plan_id')->where('estado', 'activo');
    }
}
