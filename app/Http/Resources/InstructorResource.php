<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $especialidades = $this->especialidades
            ? array_values(array_filter(array_map('trim', explode(',', $this->especialidades))))
            : [];

        return [
            'id' => (string) $this->id,
            'idpersona' => $this->id,
            'userId' => (string) $this->id,
            'nombre' => $this->nombre_completo,
            'nombre_pila' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'telefono' => $this->telefono ?? '',
            'direccion' => $this->direccion ?? '',
            'foto' => $this->avatar_url ?? '',
            'avatar_url' => $this->avatar_url,
            'especialidades' => $especialidades,
            'biografia' => $this->biografia ?? '',
            'estado' => $this->activo ? 'activo' : 'inactivo',
            'activo' => (bool) $this->activo,
            'tieneUsuario' => ! empty($this->password),
            'clasesAsignadas' => 0,
        ];
    }
}
