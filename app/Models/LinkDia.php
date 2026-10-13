<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LinkDia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'links';

    protected $fillable = [
        'tenant_id',
        'dia_id',
        'titulo',
        'url',
    ];

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'dia_id');
    }
}
