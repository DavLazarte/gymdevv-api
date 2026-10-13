<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rm extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'rms';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'ejercicio_id',
        'kg',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'kg' => 'float',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class, 'ejercicio_id');
    }
}
