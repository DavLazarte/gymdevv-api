<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cfg = $this->configuracion_completa;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'dominio' => $this->dominio,
            'logo_url' => ! empty($cfg['logo_url']) ? $cfg['logo_url'] : $this->logo_url,
            'color_primario' => $this->color_primario,
            'modulos' => $this->modulos_completos,
            'configuracion' => $cfg,
            'activo' => (bool) $this->activo,
        ];
    }
}
