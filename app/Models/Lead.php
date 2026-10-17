<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'leads';

    protected $fillable = [
        'tenant_id',
        'grupo_id',
        'plan_interes_id',
        'alumno_id',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'mensaje',
        'estado',
        'notas_admin',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'date:Y-m-d',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function planInteres(): BelongsTo
    {
        return $this->belongsTo(TipoMembresia::class, 'plan_interes_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }
}
