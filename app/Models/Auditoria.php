<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Auditoria extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'auditoria';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'accion',
        'entidad',
        'entidad_id',
        'datos_anteriores',
        'datos_nuevos',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
