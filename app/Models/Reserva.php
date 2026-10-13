<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reserva extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'reservas';

    protected $fillable = [
        'tenant_id',
        'grupo_id',
        'turno_id',
        'alumno_id',
        'fecha',
        'estado',
        'cancelada_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'cancelada_at' => 'datetime',
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
