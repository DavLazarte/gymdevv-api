<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgresoDia extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'progreso_dias';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'dia_id',
        'completado_en',
    ];

    protected function casts(): array
    {
        return [
            'completado_en' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'dia_id');
    }
}
