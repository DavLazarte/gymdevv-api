<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoMembresiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'idservicio' => $this->id,
            'tenant_id' => $this->tenant_id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion ?? '',
            'modalidad' => $this->modalidad,
            'tipo_servicio' => 'plan',
            'precio' => (float) $this->precio,
            'duracion_dias' => $this->duracion_dias,
            'creditos' => $this->creditos,
            'activo' => (bool) $this->activo,
            'estado' => (bool) $this->activo,
        ];
    }
}
