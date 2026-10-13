<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rol = $this->rol_value;

        // Mapeo de compatibilidad con devgym actual
        $legacyRoleName = match ($rol) {
            'admin' => 'gym_owner',
            'coach' => 'gym_staff',
            default => 'gym_socio',
        };

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => $this->nombre_completo,
            'name' => $this->nombre_completo,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'avatar_url' => $this->avatar_url,
            'foto' => $this->avatar_url ?? '',
            'image' => $this->avatar_url ?? '',
            'rol' => $rol,
            'role' => $rol,
            'role_name' => $legacyRoleName,
            'activo' => (bool) $this->activo,
            'tiene_acceso' => ! empty($this->password),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
