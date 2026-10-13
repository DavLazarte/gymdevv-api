<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalidaCaja extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'salidas_caja';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'registrado_por_id',
        'tipo_salida',
        'descripcion',
        'monto',
        'metodo_pago',
        'fecha',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_id');
    }
}
