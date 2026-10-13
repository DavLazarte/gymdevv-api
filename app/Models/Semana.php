<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semana extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'semanas';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'titulo',
        'numero',
        'estado',
        'fecha_inicio',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_inicio' => 'date:Y-m-d',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEntrenamiento::class, 'plan_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(Dia::class, 'semana_id')->orderBy('orden');
    }
}
