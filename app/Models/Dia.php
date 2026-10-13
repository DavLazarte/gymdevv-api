<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'dias';

    protected $fillable = [
        'tenant_id',
        'semana_id',
        'nombre',
        'descanso',
        'contenido',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'descanso' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function semana(): BelongsTo
    {
        return $this->belongsTo(Semana::class, 'semana_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(LinkDia::class, 'dia_id');
    }

    public function diaEjercicios(): HasMany
    {
        return $this->hasMany(DiaEjercicio::class, 'dia_id')->orderBy('orden');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class, 'dia_id')->orderBy('created_at');
    }
}
