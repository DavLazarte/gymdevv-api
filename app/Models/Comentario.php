<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comentario extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'comentarios';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'dia_id',
        'autor_usuario_id',
        'texto',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'dia_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_usuario_id');
    }
}
