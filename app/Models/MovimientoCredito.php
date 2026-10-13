<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCredito extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'movimientos_credito';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'membresia_id',
        'cantidad',
        'motivo',
        'reserva_id',
        'asistencia_id',
        'notas',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function membresia(): BelongsTo
    {
        return $this->belongsTo(Membresia::class, 'membresia_id');
    }
}
