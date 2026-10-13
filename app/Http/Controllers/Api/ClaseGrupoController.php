<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\InscripcionTurno;
use App\Models\Reserva;
use App\Models\Turno;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClaseGrupoController extends Controller
{
    private const DIAS_MAP = [
        'domingo' => 0,
        'lunes' => 1,
        'martes' => 2,
        'miercoles' => 3,
        'miércoles' => 3,
        'jueves' => 4,
        'viernes' => 5,
        'sabado' => 6,
        'sábado' => 6,
    ];

    public function index(Request $request): JsonResponse
    {
        $hoy = now()->toDateString();
        $user = $request->user();

        $query = Grupo::query()
            ->with(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva'])
            ->orderBy('hora_inicio');

        if ($user && $user->isCoach()) {
            $query->where('coach_id', $user->id);
        }

        $grupos = $query->get()
            ->map(fn (Grupo $g) => $this->formatGrupo($g, $hoy));

        return response()->json([
            'clases' => $grupos,
            'data' => $grupos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'modalidad' => ['nullable', 'string', 'in:libre,fijo,mixto'],
            'descripcion' => ['nullable', 'string'],
            'id_coach' => ['nullable', 'exists:usuarios,id'],
            'coach_id' => ['nullable', 'exists:usuarios,id'],
            'dias_semana' => ['required', 'string'],
            'hora_inicio' => ['required', 'string'],
            'hora_fin' => ['required', 'string'],
            'duracion_minutos' => ['nullable', 'integer', 'min:1'],
            'cupo_maximo' => ['nullable', 'integer', 'min:1'],
            'cupo_default' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string'],
            'minutos_limite_reserva' => ['nullable', 'integer', 'min:0'],
            'minutos_limite_cancelacion' => ['nullable', 'integer', 'min:0'],
        ]);

        $grupo = DB::transaction(function () use ($validated) {
            $cupo = (int) ($validated['cupo_maximo'] ?? $validated['cupo_default'] ?? 20);
            $activo = ($validated['estado'] ?? 'activa') === 'activa';

            $payload = [
                'coach_id' => $validated['id_coach'] ?? $validated['coach_id'] ?? null,
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'dias_semana' => $validated['dias_semana'],
                'hora_inicio' => $validated['hora_inicio'],
                'hora_fin' => $validated['hora_fin'],
                'duracion_minutos' => (int) ($validated['duracion_minutos'] ?? 60),
                'cupo_default' => $cupo,
                'minutos_limite_reserva' => $validated['minutos_limite_reserva'] ?? null,
                'minutos_limite_cancelacion' => $validated['minutos_limite_cancelacion'] ?? null,
                'activo' => $activo,
            ];

            if (Schema::hasColumn('grupos', 'modalidad')) {
                $payload['modalidad'] = $validated['modalidad'] ?? 'libre';
            }

            $grupo = Grupo::create($payload);

            $this->syncTurnos($grupo);

            return $grupo->load(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva']);
        });

        $formatted = $this->formatGrupo($grupo, now()->toDateString());

        return response()->json([
            'message' => 'Clase creada exitosamente.',
            'clase' => $formatted,
            'data' => $formatted,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $grupo = Grupo::query()
            ->with(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva'])
            ->findOrFail($id);
        $formatted = $this->formatGrupo($grupo, now()->toDateString());

        return response()->json([
            'clase' => $formatted,
            'data' => $formatted,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $grupo = Grupo::query()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'modalidad' => ['nullable', 'string', 'in:libre,fijo,mixto'],
            'descripcion' => ['nullable', 'string'],
            'id_coach' => ['nullable', 'exists:usuarios,id'],
            'coach_id' => ['nullable', 'exists:usuarios,id'],
            'dias_semana' => ['sometimes', 'required', 'string'],
            'hora_inicio' => ['sometimes', 'required', 'string'],
            'hora_fin' => ['sometimes', 'required', 'string'],
            'duracion_minutos' => ['nullable', 'integer', 'min:1'],
            'cupo_maximo' => ['nullable', 'integer', 'min:1'],
            'cupo_default' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string'],
            'minutos_limite_reserva' => ['nullable', 'integer', 'min:0'],
            'minutos_limite_cancelacion' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($grupo, $validated) {
            $cupo = (int) ($validated['cupo_maximo'] ?? $validated['cupo_default'] ?? $grupo->cupo_default);
            $activo = isset($validated['estado']) ? ($validated['estado'] === 'activa') : $grupo->activo;

            $payload = [
                'coach_id' => array_key_exists('id_coach', $validated) || array_key_exists('coach_id', $validated)
                    ? ($validated['id_coach'] ?? $validated['coach_id'])
                    : $grupo->coach_id,
                'nombre' => $validated['nombre'] ?? $grupo->nombre,
                'descripcion' => array_key_exists('descripcion', $validated) ? $validated['descripcion'] : $grupo->descripcion,
                'dias_semana' => $validated['dias_semana'] ?? $grupo->dias_semana,
                'hora_inicio' => $validated['hora_inicio'] ?? $grupo->hora_inicio,
                'hora_fin' => $validated['hora_fin'] ?? $grupo->hora_fin,
                'duracion_minutos' => (int) ($validated['duracion_minutos'] ?? $grupo->duracion_minutos),
                'cupo_default' => $cupo,
                'minutos_limite_reserva' => array_key_exists('minutos_limite_reserva', $validated)
                    ? $validated['minutos_limite_reserva']
                    : $grupo->minutos_limite_reserva,
                'minutos_limite_cancelacion' => array_key_exists('minutos_limite_cancelacion', $validated)
                    ? $validated['minutos_limite_cancelacion']
                    : $grupo->minutos_limite_cancelacion,
                'activo' => $activo,
            ];

            if (Schema::hasColumn('grupos', 'modalidad') && isset($validated['modalidad'])) {
                $payload['modalidad'] = $validated['modalidad'];
            }

            $grupo->update($payload);

            $this->syncTurnos($grupo);
        });

        $grupo->load(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva']);
        $formatted = $this->formatGrupo($grupo, now()->toDateString());

        return response()->json([
            'message' => 'Clase actualizada correctamente.',
            'clase' => $formatted,
            'data' => $formatted,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $grupo = Grupo::query()->findOrFail($id);
        $grupo->update(['activo' => false]);
        $grupo->turnos()->update(['activo' => false]);
        $grupo->delete(); // Soft delete

        return response()->json([
            'message' => 'Clase eliminada correctamente.',
        ]);
    }

    /**
     * GET /api/clases-disponibles?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function available(Request $request): JsonResponse
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : $start->copy()->addDays(6)->endOfDay();

        $alumnoId = $request->user()?->alumno?->id;

        $misTurnosFijosIds = $alumnoId
            ? InscripcionTurno::query()
                ->where('alumno_id', $alumnoId)
                ->where('estado', 'activa')
                ->pluck('turno_id')
                ->all()
            : [];

        $tieneTurnoFijoGeneral = ! empty($misTurnosFijosIds);

        $gruposQuery = Grupo::query()
            ->where('activo', true)
            ->with(['coach', 'turnos' => fn ($q) => $q->where('activo', true)]);

        if ($request->user()?->isCoach()) {
            $gruposQuery->where('coach_id', $request->user()->id);
        }

        $grupos = $gruposQuery->get();

        $instancias = [];
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $diaSemanaNum = $date->dayOfWeek; // 0 (Dom) .. 6 (Sab)
            $fechaStr = $date->toDateString();

            foreach ($grupos as $grupo) {
                $turno = $grupo->turnos->firstWhere('dia_semana', $diaSemanaNum);
                if (! $turno) {
                    continue;
                }

                $esFijoMio = in_array($turno->id, $misTurnosFijosIds, true);

                // Si el socio ya está vinculado a una clase fija, solo le mostramos su(s) clase(s) asignada(s)
                if ($tieneTurnoFijoGeneral && ! $esFijoMio) {
                    continue;
                }

                $cupoMaximo = (int) ($turno->cupo ?? $grupo->cupo_default ?? 20);
                $modalidad = $grupo->modalidad ?? 'libre';

                $inscripcionesDelTurno = InscripcionTurno::query()
                    ->where('turno_id', $turno->id)
                    ->where('estado', 'activa')
                    ->with('alumno.usuario')
                    ->get();

                $fijosAlumnoIds = $inscripcionesDelTurno->pluck('alumno_id')->filter()->all();
                $fijosCount = count($fijosAlumnoIds);

                $reservasDelDia = Reserva::query()
                    ->where(function ($q) use ($turno, $grupo) {
                        $q->where('turno_id', $turno->id)->orWhere('grupo_id', $grupo->id);
                    })
                    ->whereDate('fecha', $fechaStr)
                    ->where('estado', '!=', 'cancelada')
                    ->with('alumno.usuario')
                    ->get();

                $bajasHoyIds = $reservasDelDia
                    ->where('estado', 'baja_fijo')
                    ->pluck('alumno_id')
                    ->filter()
                    ->all();

                $bajasHoyCount = count(array_intersect($fijosAlumnoIds, $bajasHoyIds));

                // Contar reservas puntuales de alumnos que NO son fijos de este turno (para no duplicar cupo cuando un fijo confirma asistencia)
                $reservasExtraCount = $reservasDelDia
                    ->whereIn('estado', ['reservada', 'asistio'])
                    ->filter(fn (Reserva $r) => ! in_array($r->alumno_id, $fijosAlumnoIds, true))
                    ->count();

                $ocupados = max(0, $fijosCount - $bajasHoyCount + $reservasExtraCount);

                $miReserva = $alumnoId
                    ? $reservasDelDia
                        ->where('alumno_id', $alumnoId)
                        ->whereIn('estado', ['reservada', 'asistio'])
                        ->first()
                    : null;

                $bajaFijoHoyMia = ($esFijoMio && $alumnoId)
                    ? in_array($alumnoId, $bajasHoyIds, true)
                    : false;

                $horaInicioStr = substr((string) $turno->hora_inicio, 0, 5);
                $fechaHoraTurno = Carbon::parse("{$fechaStr} {$horaInicioStr}");
                $clasePasada = $fechaHoraTurno->isPast();
                $minutosFaltantes = now()->diffInMinutes($fechaHoraTurno, false);
                $limiteReserva = (int) ($grupo->minutos_limite_reserva ?? 0);
                $puedeReservar = ! $clasePasada && ($limiteReserva <= 0 || $minutosFaltantes >= $limiteReserva);

                $confirmadoHoy = $miReserva !== null;
                $reservada = $confirmadoHoy || ($esFijoMio && ! $bajaFijoHoyMia);
                $reservaIdOut = $miReserva
                    ? (string) $miReserva->id
                    : ($esFijoMio ? "fijo_{$turno->id}_{$fechaStr}_{$alumnoId}" : null);

                // Construir listado de alumnos presentes/anotados en la clase para esa fecha
                $alumnosEnClase = [];
                $alumnosAgregadosIds = [];

                foreach ($inscripcionesDelTurno as $ins) {
                    $al = $ins->alumno;
                    if (! $al || in_array($al->id, $bajasHoyIds, true)) {
                        continue;
                    }
                    $resSocio = $reservasDelDia->firstWhere('alumno_id', $al->id);
                    $alumnosEnClase[] = [
                        'id' => $resSocio ? (string) $resSocio->id : "fijo_{$turno->id}_{$fechaStr}_{$al->id}",
                        'id_persona' => (string) $al->id,
                        'nombre' => $al->usuario?->nombre_completo ?? ('Socio #' . $al->id),
                        'foto' => $al->usuario?->avatar_url ?? '',
                        'dni' => $al->dni ?? '',
                        'tipo_persona' => 'cliente',
                        'estado_asistencia' => $resSocio?->estado ?? 'anotado_fijo',
                    ];
                    $alumnosAgregadosIds[] = $al->id;
                }

                foreach ($reservasDelDia->whereIn('estado', ['reservada', 'asistio']) as $resExtra) {
                    $al = $resExtra->alumno;
                    if (! $al || in_array($al->id, $alumnosAgregadosIds, true)) {
                        continue;
                    }
                    $alumnosEnClase[] = [
                        'id' => (string) $resExtra->id,
                        'id_persona' => (string) $al->id,
                        'nombre' => $al->usuario?->nombre_completo ?? ('Socio #' . $al->id),
                        'foto' => $al->usuario?->avatar_url ?? '',
                        'dni' => $al->dni ?? '',
                        'tipo_persona' => 'cliente',
                        'estado_asistencia' => $resExtra->estado,
                    ];
                    $alumnosAgregadosIds[] = $al->id;
                }

                $instancias[] = [
                    'id' => "{$grupo->id}_{$fechaStr}",
                    'clase_id' => (string) $grupo->id,
                    'id_clase_gym' => (string) $grupo->id,
                    'grupo_id' => $grupo->id,
                    'turno_id' => $turno->id,
                    'nombre' => $grupo->nombre,
                    'modalidad' => $modalidad,
                    'instructor' => $grupo->coach?->nombre_completo ?? 'Sin asignar',
                    'coach' => $grupo->coach?->nombre_completo ?? 'Sin asignar',
                    'fecha' => $fechaStr,
                    'hora' => $horaInicioStr,
                    'hora_inicio' => $horaInicioStr,
                    'hora_fin' => substr((string) $turno->hora_fin, 0, 5),
                    'duracion' => (int) $grupo->duracion_minutos,
                    'duracion_minutos' => (int) $grupo->duracion_minutos,
                    'capacidad' => $cupoMaximo,
                    'cupo_maximo' => $cupoMaximo,
                    'inscritos' => $ocupados,
                    'disponibles' => max(0, $cupoMaximo - $ocupados),
                    'reservada' => $reservada,
                    'reservado' => $reservada,
                    'es_turno_fijo' => $esFijoMio,
                    'confirmado_hoy' => $confirmadoHoy,
                    'baja_fijo_hoy' => $bajaFijoHoyMia,
                    'estado_inscripcion' => $bajaFijoHoyMia
                        ? 'no_asiste'
                        : ($confirmadoHoy ? 'confirmada' : ($esFijoMio ? 'anotado_fijo' : 'libre')),
                    'reserva_id' => $reservaIdOut,
                    'estado_clase' => $esFijoMio ? 'Tu Clase Asignada' : ($modalidad === 'fijo' ? 'Turno Fijo' : 'Clase Abierta'),
                    'puede_reservar' => $puedeReservar,
                    'clase_pasada' => $clasePasada,
                    'alumnos' => $alumnosEnClase,
                ];
            }
        }

        return response()->json([
            'instancias' => $instancias,
            'tiene_turno_fijo' => $tieneTurnoFijoGeneral,
            'data' => $instancias,
        ]);
    }

    /**
     * GET /api/clases/instructor-stats?instructor_id=&start_date=&end_date=
     */
    public function instructorStats(Request $request): JsonResponse
    {
        $instructorId = $request->input('instructor_id');
        $start = Carbon::parse($request->input('start_date', now()->startOfMonth()->toDateString()));
        $end = Carbon::parse($request->input('end_date', now()->toDateString()));

        $grupos = Grupo::query()
            ->where('coach_id', $instructorId)
            ->where('activo', true)
            ->with('turnos')
            ->get();

        $details = [];
        $period = CarbonPeriod::create($start, $end);
        $nombresDias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        foreach ($period as $date) {
            $diaNum = $date->dayOfWeek;
            $fechaStr = $date->toDateString();

            foreach ($grupos as $grupo) {
                $turno = $grupo->turnos->firstWhere('dia_semana', $diaNum);
                if ($turno) {
                    $details[] = [
                        'date' => $fechaStr,
                        'day' => $nombresDias[$diaNum] ?? '',
                        'time' => substr((string) $turno->hora_inicio, 0, 5) . ' - ' . $grupo->nombre,
                        'class_name' => $grupo->nombre,
                    ];
                }
            }
        }

        return response()->json([
            'total_classes' => count($details),
            'details' => $details,
        ]);
    }

    /**
     * POST /api/clases/{id}/inscripciones
     * Inscribir un alumno en horario fijo (inscripciones_turno)
     */
    public function inscribirFijo(Request $request, string $grupoId): JsonResponse
    {
        $alumnoId = $request->input('alumno_id') ?? $request->input('id_socio') ?? $request->input('id_persona');
        $request->merge(['alumno_id' => $alumnoId]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'turno_id' => ['nullable', 'exists:turnos,id'],
        ]);

        $grupo = Grupo::query()->with('turnos')->findOrFail($grupoId);

        // Asegurar que la clase tenga sus turnos creados
        if ($grupo->turnos->isEmpty()) {
            $this->syncTurnos($grupo);
            $grupo->load('turnos');
        }

        $turnosActivos = ! empty($validated['turno_id'])
            ? $grupo->turnos->where('id', $validated['turno_id'])
            : $grupo->turnos->where('activo', true);

        // Verificar cupo máximo de alumnos fijos en la clase
        $primerTurno = $turnosActivos->first();
        if ($primerTurno) {
            $yaInscripto = InscripcionTurno::query()
                ->where('turno_id', $primerTurno->id)
                ->where('alumno_id', $validated['alumno_id'])
                ->where('estado', 'activa')
                ->exists();

            if (! $yaInscripto) {
                $actualesFijos = InscripcionTurno::query()
                    ->where('turno_id', $primerTurno->id)
                    ->where('estado', 'activa')
                    ->count();

                $cupoMaximo = (int) ($grupo->cupo_default ?? 20);
                if ($actualesFijos >= $cupoMaximo) {
                    return response()->json([
                        'message' => "La clase ya alcanzó su cupo máximo ({$cupoMaximo} alumnos).",
                    ], 422);
                }
            }
        }

        foreach ($turnosActivos as $turno) {
            $inscripcion = InscripcionTurno::withTrashed()
                ->where('turno_id', $turno->id)
                ->where('alumno_id', $validated['alumno_id'])
                ->first();

            if ($inscripcion) {
                if ($inscripcion->trashed()) {
                    $inscripcion->restore();
                }
                $inscripcion->update([
                    'fecha_alta' => now()->toDateString(),
                    'fecha_baja' => null,
                    'estado' => 'activa',
                ]);
            } else {
                InscripcionTurno::create([
                    'turno_id' => $turno->id,
                    'alumno_id' => $validated['alumno_id'],
                    'fecha_alta' => now()->toDateString(),
                    'fecha_baja' => null,
                    'estado' => 'activa',
                ]);
            }
        }

        $grupo->load(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva']);
        $formatted = $this->formatGrupo($grupo, now()->toDateString());

        return response()->json([
            'message' => 'Alumno asignado al turno fijo correctamente.',
            'clase' => $formatted,
            'data' => $formatted,
        ], 201);
    }

    /**
     * DELETE /api/clases/{id}/inscripciones/{alumnoId}
     * Dar de baja a un alumno de un horario fijo
     */
    public function desinscribirFijo(Request $request, string $grupoId, string $alumnoId): JsonResponse
    {
        $grupo = Grupo::query()->with('turnos')->findOrFail($grupoId);
        $turnoIds = $grupo->turnos->pluck('id');

        $inscripciones = InscripcionTurno::query()
            ->whereIn('turno_id', $turnoIds)
            ->where('alumno_id', $alumnoId)
            ->get();

        foreach ($inscripciones as $insc) {
            $insc->update([
                'estado' => 'baja',
                'fecha_baja' => now()->toDateString(),
            ]);
            $insc->delete(); // Soft delete
        }

        $grupo->load(['coach', 'turnos.inscripciones.alumno.usuario', 'turnos.inscripciones.alumno.membresiaActiva']);
        $formatted = $this->formatGrupo($grupo, now()->toDateString());

        return response()->json([
            'message' => 'Alumno dado de baja del turno fijo.',
            'clase' => $formatted,
            'data' => $formatted,
        ]);
    }

    public function syncTurnos(Grupo $grupo): void
    {
        $diasNombres = array_filter(array_map('trim', explode(',', (string) $grupo->dias_semana)));
        $diasNumeros = [];

        foreach ($diasNombres as $nombreDia) {
            $key = mb_strtolower($nombreDia, 'UTF-8');
            if (isset(self::DIAS_MAP[$key])) {
                $diasNumeros[] = self::DIAS_MAP[$key];
            }
        }

        foreach ($diasNumeros as $diaNum) {
            Turno::query()->updateOrCreate(
                [
                    'tenant_id' => $grupo->tenant_id,
                    'grupo_id' => $grupo->id,
                    'dia_semana' => $diaNum,
                ],
                [
                    'hora_inicio' => $grupo->hora_inicio ?? '08:00:00',
                    'hora_fin' => $grupo->hora_fin ?? '09:00:00',
                    'cupo' => $grupo->cupo_default,
                    'activo' => $grupo->activo,
                ]
            );
        }

        if (! empty($diasNumeros)) {
            Turno::query()
                ->where('grupo_id', $grupo->id)
                ->whereNotIn('dia_semana', $diasNumeros)
                ->update(['activo' => false]);
        }
    }

    private function formatGrupo(Grupo $grupo, string $hoy): array
    {
        // Obtener alumnos fijos activos únicos en los turnos de este grupo
        $alumnosMap = [];
        foreach ($grupo->turnos->where('activo', true) as $turno) {
            $inscripciones = $turno->relationLoaded('inscripciones')
                ? $turno->inscripciones->where('estado', 'activa')
                : $turno->inscripciones()->where('estado', 'activa')->with(['alumno.usuario', 'alumno.membresiaActiva'])->get();

            foreach ($inscripciones as $insc) {
                $alumno = $insc->alumno;
                if (! $alumno || isset($alumnosMap[$alumno->id])) {
                    continue;
                }
                $alumnosMap[$alumno->id] = [
                    'id' => (string) $alumno->id,
                    'alumno_id' => $alumno->id,
                    'inscripcion_id' => $insc->id,
                    'nombre' => $alumno->usuario?->nombre_completo ?? 'Socio #' . $alumno->id,
                    'email' => $alumno->usuario?->email ?? '',
                    'telefono' => $alumno->usuario?->telefono ?? '',
                    'dni' => $alumno->dni ?? '',
                    'fecha_alta' => $insc->fecha_alta?->format('Y-m-d') ?? '',
                    'tiene_membresia_activa' => $alumno->membresiaActiva !== null,
                ];
            }
        }

        $alumnosFijos = array_values($alumnosMap);
        $inscritosFijosCount = count($alumnosFijos);

        $turnoIds = $grupo->turnos->pluck('id');
        $bajasFijosHoy = $turnoIds->isNotEmpty()
            ? Reserva::query()
                ->whereIn('turno_id', $turnoIds)
                ->whereDate('fecha', $hoy)
                ->where('estado', 'baja_fijo')
                ->count()
            : 0;

        $reservasYAsistenciasHoy = Asistencia::query()
            ->where('grupo_id', $grupo->id)
            ->whereDate('fecha', $hoy)
            ->count()
            + Reserva::query()
                ->where('grupo_id', $grupo->id)
                ->whereDate('fecha', $hoy)
                ->where('estado', 'reservada')
                ->count();

        $modalidad = $grupo->modalidad ?? ($inscritosFijosCount > 0 ? 'fijo' : 'libre');
        $inscritosHoy = max(0, $inscritosFijosCount - $bajasFijosHoy) + $reservasYAsistenciasHoy;

        return [
            'id' => (string) $grupo->id,
            'nombre' => $grupo->nombre,
            'modalidad' => $modalidad,
            'descripcion' => $grupo->descripcion ?? '',
            'id_coach' => $grupo->coach_id ? (string) $grupo->coach_id : '',
            'coach_id' => $grupo->coach_id,
            'coach' => $grupo->coach ? [
                'id' => (string) $grupo->coach->id,
                'nombre' => $grupo->coach->nombre_completo,
            ] : ['id' => '', 'nombre' => 'Sin instructor'],
            'dias_semana' => $grupo->dias_semana ?? 'Lunes',
            'hora_inicio' => substr((string) $grupo->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $grupo->hora_fin, 0, 5),
            'duracion_minutos' => (int) $grupo->duracion_minutos,
            'cupo_maximo' => (int) $grupo->cupo_default,
            'inscritos_fijos' => $inscritosFijosCount,
            'inscritos_hoy' => $inscritosHoy,
            'alumnos_fijos' => $alumnosFijos,
            'estado' => $grupo->activo ? 'activa' : 'cancelada',
            'minutos_limite_reserva' => $grupo->minutos_limite_reserva,
            'minutos_limite_cancelacion' => $grupo->minutos_limite_cancelacion,
            'turnos' => $grupo->turnos->map(fn (Turno $t) => [
                'id' => $t->id,
                'dia_semana' => $t->dia_semana,
                'hora_inicio' => substr((string) $t->hora_inicio, 0, 5),
                'hora_fin' => substr((string) $t->hora_fin, 0, 5),
                'cupo' => $t->cupo_efectivo,
                'activo' => $t->activo,
            ])->values(),
        ];
    }
}
