<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\InscripcionTurno;
use App\Models\Membresia;
use App\Models\MovimientoCredito;
use App\Models\Reserva;
use App\Models\Turno;
use App\Services\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Reserva::query()
            ->with([
                'grupo.coach',
                'turno.grupo.coach',
                'alumno.usuario',
                'alumno.membresiaActiva.tipoMembresia',
            ])
            ->where('estado', '!=', 'cancelada')
            ->orderBy('fecha')
            ->orderBy('id');

        $alumnoId = $request->input('alumno_id') ?? $request->input('id_persona');
        if (! empty($alumnoId)) {
            $query->where('alumno_id', $alumnoId);
        } elseif ($request->user()?->isAlumno() && $request->user()?->alumno) {
            $alumnoId = $request->user()->alumno->id;
            $query->where('alumno_id', $alumnoId);
        }

        $coachGrupoIds = null;
        if ($request->user()?->isCoach()) {
            $coachGrupoIds = \App\Models\Grupo::query()
                ->where('coach_id', $request->user()->id)
                ->pluck('id')
                ->all();

            $query->where(function ($q) use ($coachGrupoIds) {
                $q->whereIn('grupo_id', $coachGrupoIds)
                    ->orWhereHas('turno', fn ($tq) => $tq->whereIn('grupo_id', $coachGrupoIds));
            });
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->input('fecha'));
        }

        $reservasRaw = $query->get();

        // Consultar inscripciones de turno fijo activas (filtradas por alumno si corresponde, o todas las del gimnasio si es Admin viendo la fecha)
        $inscripcionesQuery = InscripcionTurno::query()
            ->where('estado', 'activa')
            ->with([
                'turno.grupo.coach',
                'alumno.usuario',
                'alumno.membresiaActiva.tipoMembresia',
            ]);

        if (! empty($alumnoId)) {
            $inscripcionesQuery->where('alumno_id', $alumnoId);
        }
        if (is_array($coachGrupoIds)) {
            $inscripcionesQuery->whereHas('turno', fn ($tq) => $tq->whereIn('grupo_id', $coachGrupoIds));
        }

        $inscripcionesFijas = $inscripcionesQuery->get();

        // Mapa rápido de (alumno_id . ':' . turno_id) para saber si una reserva puntual pertenece a un turno fijo del socio
        $fijosLookup = [];
        foreach ($inscripcionesFijas as $ins) {
            if ($ins->alumno_id && $ins->turno_id) {
                $fijosLookup[$ins->alumno_id . ':' . $ins->turno_id] = true;
            }
        }

        $items = $reservasRaw
            ->map(function (Reserva $r) use ($fijosLookup) {
                $esFijo = ($r->alumno_id && $r->turno_id && isset($fijosLookup[$r->alumno_id . ':' . $r->turno_id]))
                    || $r->estado === 'baja_fijo';

                return $this->formatReserva($r, $esFijo);
            })
            ->values()
            ->all();

        // Generar las instancias de turnos fijos para la fecha solicitada (Admin o Socio) o próximos 7 días (Socio)
        if ($inscripcionesFijas->isNotEmpty() && ($request->filled('fecha') || ! empty($alumnoId))) {
            $start = $request->filled('fecha')
                ? Carbon::parse($request->input('fecha'))->startOfDay()
                : now()->startOfDay();
            $end = $request->filled('fecha')
                ? Carbon::parse($request->input('fecha'))->endOfDay()
                : now()->addDays(6)->endOfDay();

            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $diaSemana = $date->dayOfWeek;
                $fechaStr = $date->toDateString();

                foreach ($inscripcionesFijas as $inscripcion) {
                    $turno = $inscripcion->turno;
                    $grupo = $turno?->grupo;
                    $alumno = $inscripcion->alumno;

                    if (! $turno || ! $turno->activo || ! $grupo || ! $grupo->activo || ! $alumno) {
                        continue;
                    }
                    if ((int) $turno->dia_semana !== (int) $diaSemana) {
                        continue;
                    }

                    // Verificar si este alumno ya tiene un registro en `reservas` para ese turno/grupo y fecha
                    $yaExiste = $reservasRaw->first(function (Reserva $r) use ($alumno, $turno, $grupo, $fechaStr) {
                        if ((int) $r->alumno_id !== (int) $alumno->id) {
                            return false;
                        }
                        $mismaFecha = $r->fecha?->format('Y-m-d') === $fechaStr;
                        $mismoTurnoOGrupo = ($r->turno_id && (int) $r->turno_id === (int) $turno->id)
                            || ($r->grupo_id && (int) $r->grupo_id === (int) $grupo->id);

                        return $mismaFecha && $mismoTurnoOGrupo;
                    });

                    if ($yaExiste) {
                        continue;
                    }

                    $horaInicio = substr((string) ($turno->hora_inicio ?? $grupo->hora_inicio), 0, 5);
                    $usuario = $alumno->usuario;
                    $planNombre = $alumno->membresiaActiva?->tipoMembresia?->nombre ?? 'Sin membresía';

                    $items[] = [
                        'id' => "fijo_{$turno->id}_{$fechaStr}_{$alumno->id}",
                        'id_persona' => (string) $alumno->id,
                        'alumno_id' => (int) $alumno->id,
                        'id_clase_gym' => (string) $grupo->id,
                        'grupo_id' => $grupo->id,
                        'turno_id' => $turno->id,
                        'fecha_reserva' => $fechaStr,
                        'fecha' => $fechaStr,
                        'estado' => 'anotado_fijo',
                        'es_turno_fijo' => true,
                        'confirmado' => false,
                        'no_asiste' => false,
                        'persona' => [
                            'id' => (string) $alumno->id,
                            'nombre' => $usuario?->nombre_completo ?? 'Socio #' . $alumno->id,
                            'email' => $usuario?->email ?? '',
                            'dni' => $alumno->dni ?? '',
                            'foto' => $usuario?->avatar_url ?? '',
                            'plan_nombre' => $planNombre,
                        ],
                        'clase' => [
                            'id' => (string) $grupo->id,
                            'nombre' => $grupo->nombre,
                            'hora_inicio' => $horaInicio,
                            'coach' => $grupo->coach?->nombre_completo ?? '',
                        ],
                    ];
                }
            }
        }

        // Ordenar por fecha ascendente, hora ascendente y nombre del socio
        usort($items, function (array $a, array $b) {
            $cmpFecha = strcmp((string) ($a['fecha_reserva'] ?? ''), (string) ($b['fecha_reserva'] ?? ''));
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }
            $cmpHora = strcmp((string) ($a['clase']['hora_inicio'] ?? ''), (string) ($b['clase']['hora_inicio'] ?? ''));
            if ($cmpHora !== 0) {
                return $cmpHora;
            }

            return strcmp((string) ($a['persona']['nombre'] ?? ''), (string) ($b['persona']['nombre'] ?? ''));
        });

        return response()->json([
            'reservas' => $items,
            'tiene_turno_fijo' => $inscripcionesFijas->isNotEmpty(),
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $alumnoId = $request->input('alumno_id')
            ?? $request->input('id_persona')
            ?? $request->user()?->alumno?->id;

        $grupoId = $request->input('grupo_id') ?? $request->input('id_clase_gym');
        $fecha = $request->input('fecha') ?? $request->input('fecha_reserva') ?? now()->toDateString();
        $esBajaTurnoFijo = $request->boolean('baja_fijo') || $request->input('accion') === 'no_asisto';

        $request->merge([
            'alumno_id' => $alumnoId,
            'grupo_id' => $grupoId,
            'fecha' => $fecha,
        ]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'turno_id' => ['nullable', 'exists:turnos,id'],
            'fecha' => ['required', 'date'],
        ]);

        $fechaCarbon = Carbon::parse($validated['fecha']);
        $turno = null;
        if (! empty($validated['turno_id'])) {
            $turno = Turno::query()->find($validated['turno_id']);
        } elseif (! empty($validated['grupo_id'])) {
            $turno = Turno::query()
                ->where('grupo_id', $validated['grupo_id'])
                ->where('dia_semana', $fechaCarbon->dayOfWeek)
                ->first();
        }

        $grupoIdFinal = $validated['grupo_id'] ?? $turno?->grupo_id;

        $existente = Reserva::withTrashed()
            ->where('alumno_id', $validated['alumno_id'])
            ->whereDate('fecha', $fechaCarbon->toDateString())
            ->where(function ($q) use ($turno, $grupoIdFinal) {
                if ($turno) {
                    $q->where('turno_id', $turno->id);
                } elseif ($grupoIdFinal) {
                    $q->where('grupo_id', $grupoIdFinal);
                }
            })
            ->first();

        $yaEstabaReservada = $existente && ! $existente->trashed() && in_array($existente->estado, ['reservada', 'asistio'], true);

        if (! $esBajaTurnoFijo && ! $yaEstabaReservada) {
            $membresiaActiva = Membresia::query()
                ->where('alumno_id', $validated['alumno_id'])
                ->where('estado', 'activa')
                ->with('tipoMembresia')
                ->latest('id')
                ->first();

            if ($membresiaActiva && $membresiaActiva->esPorCreditos() && ($membresiaActiva->creditos_restantes ?? 0) <= 0) {
                return response()->json([
                    'message' => 'No tenés créditos disponibles en tu membresía actual.',
                ], 422);
            }
        }

        $esTurnoFijoSocio = $turno
            ? InscripcionTurno::query()
                ->where('turno_id', $turno->id)
                ->where('alumno_id', $validated['alumno_id'])
                ->where('estado', 'activa')
                ->exists()
            : false;

        $reserva = DB::transaction(function () use ($validated, $turno, $grupoIdFinal, $esBajaTurnoFijo, $existente, $yaEstabaReservada) {
            $nuevoEstado = $esBajaTurnoFijo ? 'baja_fijo' : 'reservada';

            if ($existente) {
                if ($existente->trashed()) {
                    $existente->restore();
                }
                $existente->update([
                    'grupo_id' => $grupoIdFinal,
                    'turno_id' => $turno?->id,
                    'estado' => $nuevoEstado,
                    'cancelada_at' => $esBajaTurnoFijo ? now() : null,
                ]);
                $reserva = $existente;
            } else {
                $reserva = Reserva::create([
                    'grupo_id' => $grupoIdFinal,
                    'turno_id' => $turno?->id,
                    'alumno_id' => $validated['alumno_id'],
                    'fecha' => $validated['fecha'],
                    'estado' => $nuevoEstado,
                    'cancelada_at' => $esBajaTurnoFijo ? now() : null,
                ]);
            }

            if (! $esBajaTurnoFijo && ! $yaEstabaReservada) {
                $this->consumirCreditoSiCorresponde((int) $validated['alumno_id'], $reserva->id, null);
            } elseif ($esBajaTurnoFijo && $yaEstabaReservada) {
                $this->devolverCreditoSiCorresponde($reserva);
            }

            return $reserva->load([
                'grupo.coach',
                'turno.grupo.coach',
                'alumno.usuario',
                'alumno.membresiaActiva.tipoMembresia',
            ]);
        });

        $formatted = $this->formatReserva($reserva, $esTurnoFijoSocio);

        return response()->json([
            'message' => $esBajaTurnoFijo
                ? 'Avisaste que no asistís a esta clase. Tu lugar quedó liberado por el día.'
                : ($esTurnoFijoSocio ? '¡Asistencia confirmada para tu clase!' : 'Reserva creada exitosamente.'),
            'reserva' => $formatted,
            'data' => $formatted,
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (str_starts_with($id, 'fijo_')) {
            $parts = explode('_', $id, 4);
            $turnoId = $parts[1] ?? null;
            $fecha = $parts[2] ?? now()->toDateString();
            $alumnoIdFromId = $parts[3] ?? null;
            $alumnoId = $alumnoIdFromId ?: $request->user()?->alumno?->id;

            if ($turnoId && $alumnoId) {
                $turno = Turno::query()->find($turnoId);
                $existente = Reserva::withTrashed()
                    ->where('turno_id', $turnoId)
                    ->where('alumno_id', $alumnoId)
                    ->whereDate('fecha', $fecha)
                    ->first();

                if ($existente) {
                    if ($existente->trashed()) {
                        $existente->restore();
                    }
                    $this->devolverCreditoSiCorresponde($existente);
                    $existente->update([
                        'grupo_id' => $turno?->grupo_id,
                        'estado' => 'baja_fijo',
                        'cancelada_at' => now(),
                    ]);
                } else {
                    Reserva::create([
                        'turno_id' => $turnoId,
                        'grupo_id' => $turno?->grupo_id,
                        'alumno_id' => $alumnoId,
                        'fecha' => $fecha,
                        'estado' => 'baja_fijo',
                        'cancelada_at' => now(),
                    ]);
                }

                return response()->json([
                    'message' => 'Se registró que el socio no asiste en esa fecha y se liberó el cupo del día.',
                ]);
            }
        }

        $reserva = Reserva::query()->findOrFail($id);

        $esTurnoFijo = $reserva->turno_id
            ? InscripcionTurno::query()
                ->where('turno_id', $reserva->turno_id)
                ->where('alumno_id', $reserva->alumno_id)
                ->where('estado', 'activa')
                ->exists()
            : false;

        DB::transaction(function () use ($reserva, $esTurnoFijo) {
            $this->devolverCreditoSiCorresponde($reserva);

            $reserva->update([
                'estado' => $esTurnoFijo ? 'baja_fijo' : 'cancelada',
                'cancelada_at' => now(),
            ]);
        });

        return response()->json([
            'message' => $esTurnoFijo
                ? 'Se registró que el socio no asiste en esa fecha y se liberó el cupo del día.'
                : 'Reserva cancelada correctamente.',
        ]);
    }

    private function consumirCreditoSiCorresponde(int $alumnoId, ?int $reservaId, ?int $asistenciaId): void
    {
        $membresia = Membresia::query()
            ->where('alumno_id', $alumnoId)
            ->where('estado', 'activa')
            ->with('tipoMembresia')
            ->latest('id')
            ->first();

        if ($membresia && $membresia->esPorCreditos()) {
            MovimientoCredito::create([
                'alumno_id' => $alumnoId,
                'membresia_id' => $membresia->id,
                'cantidad' => -1,
                'motivo' => $reservaId ? 'reserva' : 'asistencia',
                'reserva_id' => $reservaId,
                'asistencia_id' => $asistenciaId,
                'notas' => $reservaId ? 'Consumo por reserva/confirmación de turno' : 'Consumo por asistencia',
                'created_at' => now(),
            ]);
        }
    }

    private function devolverCreditoSiCorresponde(Reserva $reserva): void
    {
        $movimiento = MovimientoCredito::query()
            ->where('reserva_id', $reserva->id)
            ->where('cantidad', '<', 0)
            ->first();

        if ($movimiento) {
            MovimientoCredito::create([
                'alumno_id' => $reserva->alumno_id,
                'membresia_id' => $movimiento->membresia_id,
                'cantidad' => abs($movimiento->cantidad),
                'motivo' => 'devolucion_cancelacion',
                'reserva_id' => $reserva->id,
                'notas' => 'Devolución por aviso de inasistencia / cancelación',
                'created_at' => now(),
            ]);
        }
    }

    private function formatReserva(Reserva $reserva, bool $esTurnoFijo = false): array
    {
        $grupo = $reserva->grupo ?? $reserva->turno?->grupo;
        $alumno = $reserva->alumno;
        $usuario = $alumno?->usuario;
        $planNombre = $alumno?->membresiaActiva?->tipoMembresia?->nombre ?? 'Sin membresía';

        return [
            'id' => (string) $reserva->id,
            'id_persona' => (string) $reserva->alumno_id,
            'alumno_id' => $reserva->alumno_id,
            'id_clase_gym' => $grupo ? (string) $grupo->id : '',
            'grupo_id' => $grupo?->id,
            'turno_id' => $reserva->turno_id,
            'fecha_reserva' => $reserva->fecha?->format('Y-m-d'),
            'fecha' => $reserva->fecha?->format('Y-m-d'),
            'estado' => $reserva->estado,
            'es_turno_fijo' => $esTurnoFijo || $reserva->estado === 'baja_fijo',
            'confirmado' => in_array($reserva->estado, ['reservada', 'asistio'], true),
            'no_asiste' => $reserva->estado === 'baja_fijo',
            'persona' => $alumno ? [
                'id' => (string) $alumno->id,
                'nombre' => $usuario?->nombre_completo ?? ('Socio #' . $alumno->id),
                'email' => $usuario?->email ?? '',
                'dni' => $alumno->dni ?? '',
                'foto' => $usuario?->avatar_url ?? '',
                'plan_nombre' => $planNombre,
            ] : null,
            'clase' => $grupo ? [
                'id' => (string) $grupo->id,
                'nombre' => $grupo->nombre,
                'hora_inicio' => substr((string) ($reserva->turno?->hora_inicio ?? $grupo->hora_inicio), 0, 5),
                'coach' => $grupo->coach?->nombre_completo ?? '',
            ] : null,
        ];
    }
}
