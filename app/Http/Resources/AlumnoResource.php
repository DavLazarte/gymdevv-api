<?php

namespace App\Http\Resources;

use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class AlumnoResource extends JsonResource
{
    private static ?bool $hasMembresias = null;
    private static ?bool $hasAsistencias = null;
    private static ?bool $hasInscripciones = null;

    public function toArray(Request $request): array
    {
        if (self::$hasMembresias === null) {
            self::$hasMembresias = Schema::hasTable('membresias');
            self::$hasAsistencias = Schema::hasTable('asistencias');
            self::$hasInscripciones = Schema::hasTable('inscripciones_turno');
        }

        $usuario = $this->usuario;
        $nombreCompleto = $usuario ? $usuario->nombre_completo : '';

        $membresiaActiva = $this->relationLoaded('membresiaActiva')
            ? $this->membresiaActiva
            : (self::$hasMembresias ? $this->membresiaActiva()->with('tipoMembresia')->first() : null);
        $tipoMembresia = $membresiaActiva?->tipoMembresia;

        $membresiaData = null;
        try {
            if ($membresiaActiva) {
                $esCreditos = $membresiaActiva->esPorCreditos();
                $modalidad = $esCreditos ? 'creditos' : 'tiempo';
                $creditosTotales = $esCreditos
                    ? (int) (($membresiaActiva->creditos_otorgados !== null && $membresiaActiva->creditos_otorgados > 0)
                        ? $membresiaActiva->creditos_otorgados
                        : ($tipoMembresia?->creditos ?? 0))
                    : null;
                $creditosRestantes = $esCreditos
                    ? (int) ($membresiaActiva->creditos_restantes ?? $creditosTotales ?? 0)
                    : null;

                $membresiaData = [
                    'id' => (string) $membresiaActiva->id,
                    'plan' => $tipoMembresia?->nombre ?? 'Plan Activo',
                    'tipo' => $modalidad,
                    'modalidad' => $esCreditos ? 'Por Créditos' : 'Ilimitado (Tiempo)',
                    'creditos' => $creditosRestantes,
                    'creditos_restantes' => $creditosRestantes,
                    'creditos_totales' => $creditosTotales,
                    'vencimiento' => $membresiaActiva->fecha_fin?->format('Y-m-d'),
                    'fecha_inicio' => $membresiaActiva->fecha_inicio?->format('Y-m-d'),
                    'estado' => $membresiaActiva->estado_calculado,
                ];
            }
        } catch (\Throwable) {
            $membresiaData = null;
        }

        // Racha / asistencias del mes
        $rachaActual = 0;
        try {
            if (self::$hasAsistencias) {
                $rachaActual = Asistencia::query()
                    ->where('alumno_id', $this->id)
                    ->whereMonth('fecha', now()->month)
                    ->whereYear('fecha', now()->year)
                    ->distinct('fecha')
                    ->count('fecha');
            }
        } catch (\Throwable) {
            $rachaActual = 0;
        }

        // Turnos fijos activos del socio
        $tieneTurnoFijo = false;
        $clasesFijas = [];
        try {
            if (self::$hasInscripciones) {
                $inscripciones = \App\Models\InscripcionTurno::query()
                    ->where('alumno_id', $this->id)
                    ->where('estado', 'activa')
                    ->with('turno.grupo')
                    ->get();

                $tieneTurnoFijo = $inscripciones->isNotEmpty();
                $clasesFijas = $inscripciones
                    ->map(fn ($i) => $i->turno?->grupo)
                    ->filter()
                    ->unique('id')
                    ->map(fn ($g) => [
                        'id' => (string) $g->id,
                        'nombre' => $g->nombre,
                        'hora_inicio' => substr((string) $g->hora_inicio, 0, 5),
                    ])
                    ->values()
                    ->all();
            }
        } catch (\Throwable) {
            $tieneTurnoFijo = false;
            $clasesFijas = [];
        }

        return [
            'id' => (string) $this->id,
            'idpersona' => $this->id,
            'alumno_id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'userId' => $this->usuario_id ? (string) $this->usuario_id : null,
            'tenant_id' => $this->tenant_id,
            'tipo_persona' => 'socio',
            'nombre' => $nombreCompleto,
            'nombre_pila' => $usuario?->nombre,
            'apellido' => $usuario?->apellido,
            'email' => $usuario?->email ?? '',
            'telefono' => $usuario?->telefono ?? '',
            'dni' => $this->dni ?? '',
            'direccion' => $this->direccion ?? $usuario?->direccion ?? '',
            'fechaNacimiento' => $this->fecha_nacimiento?->format('Y-m-d') ?? '',
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'fechaAlta' => $this->fecha_alta?->format('Y-m-d') ?? '',
            'fecha_alta' => $this->fecha_alta?->format('Y-m-d'),
            'foto' => $usuario?->avatar_url ?? '',
            'avatar_url' => $usuario?->avatar_url,
            'estado' => $membresiaData ? $membresiaData['estado'] : $this->estado_value,
            'notas' => $this->notas ?? '',
            'tieneUsuario' => ! empty($usuario?->password),
            'tiene_acceso' => ! empty($usuario?->password),
            'planNombre' => $membresiaData ? $membresiaData['plan'] : 'Sin plan',
            'fechaVencimiento' => $membresiaData ? $membresiaData['vencimiento'] : null,
            'membresia' => $membresiaData,
            'tiene_turno_fijo' => $tieneTurnoFijo,
            'clases_fijas' => $clasesFijas,
            'racha_actual' => $rachaActual,
        ];
    }
}
