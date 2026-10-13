<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notificacion extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'notificaciones';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'tipo',
        'titulo',
        'mensaje',
        'leida',
        'leida_en',
    ];

    protected function casts(): array
    {
        return [
            'leida' => 'boolean',
            'leida_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
