<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $alumno = $this->alumno;
        $usuario = $alumno?->usuario;
        $membresia = $this->membresia;
        $tipo = $membresia?->tipoMembresia;

        return [
            'id' => (string) $this->id,
            'id_persona' => $this->alumno_id,
            'alumno_id' => $this->alumno_id,
            'id_membresia' => $this->membresia_id,
            'membresia_id' => $this->membresia_id,
            'monto' => (float) $this->monto,
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'metodo_pago' => $this->metodo,
            'metodo' => $this->metodo,
            'estado' => $this->estado,
            'comprobante' => $this->comprobante,
            'observaciones' => $this->notas ?? '',
            'notas' => $this->notas ?? '',
            'persona' => $alumno ? [
                'id' => (string) $alumno->id,
                'idpersona' => $alumno->id,
                'nombre' => $usuario?->nombre_completo ?? '',
                'email' => $usuario?->email ?? '',
            ] : null,
            'membresia' => $membresia ? [
                'id' => (string) $membresia->id,
                'estado' => $membresia->estado,
                'plan' => $tipo ? [
                    'id' => (string) $tipo->id,
                    'nombre' => $tipo->nombre,
                ] : null,
            ] : null,
        ];
    }
}
