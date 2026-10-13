<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoMembresia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'tipos_membresia';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'descripcion',
        'modalidad',
        'precio',
        'duracion_dias',
        'creditos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion_dias' => 'integer',
            'creditos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class, 'tipo_membresia_id');
    }
}
