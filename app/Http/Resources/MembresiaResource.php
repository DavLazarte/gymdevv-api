<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembresiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $alumno = $this->alumno;
        $usuario = $alumno?->usuario;
        $tipo = $this->tipoMembresia;

        $montoTotal = (float) ($this->precio_pactado ?? $tipo?->precio ?? 0);
        $montoPagado = (float) $this->pagos()
            ->where('estado', 'pagado')
            ->sum('monto');
        $saldoPendiente = max(0, round($montoTotal - $montoPagado, 2));

        $esCreditos = $this->esPorCreditos();
        $creditosTotales = ($this->creditos_otorgados !== null && $this->creditos_otorgados > 0)
            ? $this->creditos_otorgados
            : $tipo?->creditos;
        $creditosRestantes = $esCreditos ? $this->creditos_restantes : null;
        $computedStatus = $this->estado_calculado;

        return [
            'id' => (string) $this->id,
            'idpersona' => $this->alumno_id,
            'alumno_id' => $this->alumno_id,
            'idservicio' => $this->tipo_membresia_id,
            'tipo_membresia_id' => $this->tipo_membresia_id,
            'tipo' => $esCreditos ? 'creditos' : 'fecha',
            'modalidad' => $tipo?->modalidad ?? 'mensual',
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'creditos_totales' => $creditosTotales,
            'creditos_otorgados' => $this->creditos_otorgados,
            'creditos_restantes' => $creditosRestantes,
            'precio_pactado' => $montoTotal,
            'monto_total' => $montoTotal,
            'monto_pagado' => $montoPagado,
            'saldo_pendiente' => $saldoPendiente,
            'estado' => $this->estado,
            'computed_status' => $computedStatus,
            'notas' => $this->notas,
            'socio' => $alumno ? [
                'id' => (string) $alumno->id,
                'idpersona' => $alumno->id,
                'nombre' => $usuario?->nombre_completo ?? '',
                'email' => $usuario?->email ?? '',
                'telefono' => $usuario?->telefono ?? '',
            ] : null,
            'plan' => $tipo ? [
                'id' => (string) $tipo->id,
                'idservicio' => $tipo->id,
                'nombre' => $tipo->nombre,
                'precio' => (float) $tipo->precio,
                'duracion_dias' => $tipo->duracion_dias,
                'creditos' => $tipo->creditos,
                'modalidad' => $tipo->modalidad,
            ] : null,
        ];
    }
}
