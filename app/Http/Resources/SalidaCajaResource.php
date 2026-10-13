<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalidaCajaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $beneficiario = $this->beneficiario;

        return [
            'id' => (string) $this->id,
            'id_persona' => $this->usuario_id ? (string) $this->usuario_id : '',
            'usuario_id' => $this->usuario_id,
            'tipo_salida' => $this->tipo_salida,
            'descripcion' => $this->descripcion,
            'monto' => (float) $this->monto,
            'metodo_pago' => $this->metodo_pago,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'notas' => $this->notas,
            'persona' => $beneficiario ? [
                'id' => (string) $beneficiario->id,
                'nombre' => $beneficiario->nombre_completo,
                'email' => $beneficiario->email,
            ] : null,
        ];
    }
}
