<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ejercicio extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'ejercicios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'categoria',
        'unidad',
        'descripcion',
        'video_url',
    ];
}
