<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'pagos';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'membresia_id',
        'monto',
        'fecha_pago',
        'metodo',
        'estado',
        'comprobante',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date:Y-m-d',
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
