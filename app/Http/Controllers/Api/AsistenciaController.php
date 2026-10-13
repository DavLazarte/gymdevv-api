<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Membresia;
use App\Models\MovimientoCredito;
use App\Models\Turno;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 50);

        $query = Asistencia::query()
            ->with(['alumno.usuario', 'grupo', 'turno'])
            ->orderByDesc('fecha_asistencia')
            ->orderByDesc('id');

        $alumnoId = $request->input('alumno_id') ?? $request->input('id_persona');
        if (! empty($alumnoId)) {
            $query->where('alumno_id', $alumnoId);
        } elseif ($request->user()?->isAlumno() && $request->user()?->alumno) {
            $query->where('alumno_id', $request->user()->alumno->id);
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(fn (Asistencia $a) => $this->formatAsistencia($a));

        return response()->json([
            'asistencias' => $items,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $alumnoId = $request->input('alumno_id') ?? $request->input('id_persona') ?? $request->input('socioId');
        $grupoId = $request->input('grupo_id') ?? $request->input('id_clase_gym') ?? $request->input('claseId');
        $turnoId = $request->input('turno_id');
        $idReserva = $request->input('id_reserva');
        $fecha = $request->input('fecha');

        // Si viene id_reserva como "fijo_{turnoId}_{fecha}_{alumnoId}" o ID numérico, extraer turno y fecha
        $reservaExistente = null;
        if (! empty($idReserva)) {
            if (str_starts_with((string) $idReserva, 'fijo_')) {
                $parts = explode('_', (string) $idReserva, 4);
                $turnoId = $turnoId ?: ($parts[1] ?? null);
                $fecha = $fecha ?: ($parts[2] ?? null);
                $alumnoId = $alumnoId ?: ($parts[3] ?? null);
            } elseif (is_numeric($idReserva)) {
                $reservaExistente = \App\Models\Reserva::query()->find($idReserva);
                if ($reservaExistente) {
                    $turnoId = $turnoId ?: $reservaExistente->turno_id;
                    $grupoId = $grupoId ?: $reservaExistente->grupo_id;
                    $fecha = $fecha ?: $reservaExistente->fecha?->format('Y-m-d');
                    $alumnoId = $alumnoId ?: $reservaExistente->alumno_id;
                }
            }
        }

        $fecha = $fecha ?: now()->toDateString();
        $horaEntrada = $request->input('hora_entrada') ?? $request->input('horaEntrada') ?? now()->format('H:i');

        $request->merge([
            'alumno_id' => $alumnoId,
            'grupo_id' => ! empty($grupoId) ? $grupoId : null,
            'turno_id' => ! empty($turnoId) ? $turnoId : null,
            'fecha' => $fecha,
            'hora_entrada' => $horaEntrada,
        ]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'turno_id' => ['nullable', 'exists:turnos,id'],
            'fecha' => ['required', 'date'],
            'hora_entrada' => ['nullable', 'string'],
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
        $fechaAsistencia = Carbon::parse($validated['fecha'] . ' ' . ($validated['hora_entrada'] ?? now()->format('H:i:s')));

        $asistencia = DB::transaction(function () use ($validated, $turno, $grupoIdFinal, $fechaAsistencia, $reservaExistente) {
            $asistencia = Asistencia::create([
                'grupo_id' => $grupoIdFinal,
                'turno_id' => $turno?->id,
                'alumno_id' => $validated['alumno_id'],
                'fecha' => $validated['fecha'],
                'hora_entrada' => $fechaAsistencia->format('H:i:s'),
                'fecha_asistencia' => $fechaAsistencia,
                'presente' => true,
                'origen' => $grupoIdFinal || $turno ? 'manual' : 'libre',
            ]);

            // Marcar o crear la Reserva correspondiente con estado = 'asistio'
            if (! $reservaExistente && ($turno || $grupoIdFinal)) {
                $reservaExistente = \App\Models\Reserva::withTrashed()
                    ->where('alumno_id', $validated['alumno_id'])
                    ->whereDate('fecha', $validated['fecha'])
                    ->where(function ($q) use ($turno, $grupoIdFinal) {
                        if ($turno) {
                            $q->where('turno_id', $turno->id);
                        } elseif ($grupoIdFinal) {
                            $q->where('grupo_id', $grupoIdFinal);
                        }
                    })
                    ->first();
            }

            if ($reservaExistente) {
                if ($reservaExistente->trashed()) {
                    $reservaExistente->restore();
                }
                $reservaExistente->update([
                    'grupo_id' => $grupoIdFinal,
                    'turno_id' => $turno?->id,
                    'estado' => 'asistio',
                    'cancelada_at' => null,
                ]);
            } elseif ($turno || $grupoIdFinal) {
                \App\Models\Reserva::create([
                    'grupo_id' => $grupoIdFinal,
                    'turno_id' => $turno?->id,
                    'alumno_id' => $validated['alumno_id'],
                    'fecha' => $validated['fecha'],
                    'estado' => 'asistio',
                ]);
            }

            return $asistencia->load(['alumno.usuario', 'grupo', 'turno']);
        });

        $formatted = $this->formatAsistencia($asistencia);

        return response()->json([
            'message' => 'Asistencia registrada correctamente.',
            'asistencia' => $formatted,
            'data' => $formatted,
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $asistencia = Asistencia::query()->findOrFail($id);
        $asistencia->delete(); // Soft delete

        return response()->json([
            'message' => 'Asistencia eliminada correctamente.',
        ]);
    }

    private function formatAsistencia(Asistencia $asistencia): array
    {
        $alumno = $asistencia->alumno;
        $usuario = $alumno?->usuario;
        $grupo = $asistencia->grupo ?? $asistencia->turno?->grupo;

        return [
            'id' => (string) $asistencia->id,
            'id_persona' => (string) $asistencia->alumno_id,
            'alumno_id' => $asistencia->alumno_id,
            'id_clase_gym' => $grupo ? (string) $grupo->id : null,
            'grupo_id' => $grupo?->id,
            'turno_id' => $asistencia->turno_id,
            'fecha' => $asistencia->fecha?->format('Y-m-d'),
            'hora_entrada' => $asistencia->hora_entrada ? substr($asistencia->hora_entrada, 0, 5) : '',
            'fecha_asistencia' => $asistencia->fecha_asistencia?->toISOString(),
            'origen' => $asistencia->origen,
            'tipo' => $grupo ? 'clase' : 'libre',
            'persona' => $alumno ? [
                'id' => (string) $alumno->id,
                'nombre' => $usuario?->nombre_completo ?? '',
                'email' => $usuario?->email ?? '',
            ] : null,
            'clase' => $grupo ? [
                'id' => (string) $grupo->id,
                'nombre' => $grupo->nombre,
            ] : null,
        ];
    }
}
