<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\AlumnoPlan;
use App\Models\Comentario;
use App\Models\Dia;
use App\Models\Ejercicio;
use App\Models\InscripcionTurno;
use App\Models\LinkDia;
use App\Models\PlanEntrenamiento;
use App\Models\ProgresoDia;
use App\Models\Reserva;
use App\Models\Rm;
use App\Models\Semana;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntrenamientoController extends Controller
{
    private const DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    private const DEFAULT_EJERCICIOS = [
        // Fuerza (kg)
        ['nombre' => 'Back Squat', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        ['nombre' => 'Front Squat', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        ['nombre' => 'Deadlift', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        ['nombre' => 'Press Estricto', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        ['nombre' => 'Push Press', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        ['nombre' => 'Bench Press', 'categoria' => 'Fuerza', 'unidad' => 'kg'],
        // Weightlifting (kg)
        ['nombre' => 'Snatch', 'categoria' => 'Weightlifting', 'unidad' => 'kg'],
        ['nombre' => 'Clean', 'categoria' => 'Weightlifting', 'unidad' => 'kg'],
        ['nombre' => 'Clean & Jerk', 'categoria' => 'Weightlifting', 'unidad' => 'kg'],
        // Capacidades (reps)
        ['nombre' => 'Pull Ups', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Chest to Bar', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Toes to Bar', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Ring Muscle Up', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Bar Muscle Up', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Handstand Push Up', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Strict Hspu', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Strict Pull Ups', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'Burpees', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        ['nombre' => 'DU', 'categoria' => 'Capacidades', 'unidad' => 'reps'],
        // Running (segundos)
        ['nombre' => '5km', 'categoria' => 'Running', 'unidad' => 'segundos'],
        ['nombre' => '1k', 'categoria' => 'Running', 'unidad' => 'segundos'],
    ];

    /**
     * GET /api/entrenamiento/tablero
     * Devuelve planes, semanas (con días, links y comentarios) y catálogo de ejercicios para el Coach/Admin.
     */
    public function tablero(Request $request): JsonResponse
    {
        $this->ensureDefaultEjercicios();
        $user = $request->user();

        $planesQuery = PlanEntrenamiento::query()
            ->with(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])
            ->withCount('semanas')
            ->orderBy('id');

        if ($user && $user->isCoach()) {
            $misGruposIds = \App\Models\Grupo::query()
                ->where('coach_id', $user->id)
                ->pluck('id')
                ->all();

            $planesQuery->where(function ($q) use ($user, $misGruposIds) {
                $q->where('tipo', 'general')
                    ->orWhere('tipo', 'clase')
                    ->orWhere('coach_id', $user->id);
                if (! empty($misGruposIds)) {
                    $q->orWhereIn('grupo_id', $misGruposIds);
                }
            });
        }

        $planesModels = $planesQuery->get();
        $planesIds = $planesModels->pluck('id')->all();
        $planes = $planesModels->map(fn (PlanEntrenamiento $p) => $this->formatPlan($p));

        $semanasQuery = Semana::query()
            ->with([
                'plan',
                'dias.links',
                'dias.comentarios.autor',
                'dias.comentarios.alumno.usuario',
            ])
            ->orderByDesc('numero')
            ->orderByDesc('id');

        if ($user && $user->isCoach()) {
            $semanasQuery->whereIn('plan_id', $planesIds);
        }

        $semanas = $semanasQuery->get()
            ->map(fn (Semana $s) => $this->formatSemana($s));

        $ejercicios = Ejercicio::query()
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'planes' => $planes,
            'semanas' => $semanas,
            'ejercicios' => $ejercicios,
        ]);
    }

    // ─── PLANES / PROGRAMAS DE ENTRENAMIENTO ───────────────────────────────────

    public function indexPlanes(Request $request): JsonResponse
    {
        $user = $request->user();
        $planesQuery = PlanEntrenamiento::query()
            ->with(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])
            ->withCount('semanas')
            ->orderBy('id');

        if ($user && $user->isCoach()) {
            $misGruposIds = \App\Models\Grupo::query()
                ->where('coach_id', $user->id)
                ->pluck('id')
                ->all();

            $planesQuery->where(function ($q) use ($user, $misGruposIds) {
                $q->where('tipo', 'general')
                    ->orWhere('tipo', 'clase')
                    ->orWhere('coach_id', $user->id);
                if (! empty($misGruposIds)) {
                    $q->orWhereIn('grupo_id', $misGruposIds);
                }
            });
        }

        $planes = $planesQuery->get()
            ->map(fn (PlanEntrenamiento $p) => $this->formatPlan($p));

        return response()->json([
            'planes' => $planes,
            'data' => $planes,
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'in:clase,personalizado,general'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'coach_id' => ['nullable', 'exists:usuarios,id'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'icono' => ['nullable', 'string', 'max:40'],
            'estado' => ['nullable', 'string', 'in:activo,inactivo'],
        ]);

        $plan = PlanEntrenamiento::create([
            'coach_id' => $validated['coach_id'] ?? $request->user()?->id,
            'grupo_id' => ($validated['tipo'] ?? 'personalizado') === 'clase' ? ($validated['grupo_id'] ?? null) : null,
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'] ?? 'personalizado',
            'descripcion' => $validated['descripcion'] ?? null,
            'color' => $validated['color'] ?? '#f97316',
            'icono' => $validated['icono'] ?? 'dumbbell',
            'estado' => $validated['estado'] ?? 'activo',
        ]);

        $plan->load(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])->loadCount('semanas');

        return response()->json([
            'message' => 'Programa de entrenamiento creado correctamente.',
            'plan' => $this->formatPlan($plan),
        ], 201);
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = PlanEntrenamiento::query()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'in:clase,personalizado,general'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'coach_id' => ['nullable', 'exists:usuarios,id'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'icono' => ['nullable', 'string', 'max:40'],
            'estado' => ['nullable', 'string', 'in:activo,inactivo'],
        ]);

        $tipo = $validated['tipo'] ?? $plan->tipo;
        $grupoId = $tipo === 'clase'
            ? (array_key_exists('grupo_id', $validated) ? $validated['grupo_id'] : $plan->grupo_id)
            : null;

        $plan->update([
            'nombre' => $validated['nombre'] ?? $plan->nombre,
            'tipo' => $tipo,
            'grupo_id' => $grupoId,
            'coach_id' => array_key_exists('coach_id', $validated) ? $validated['coach_id'] : $plan->coach_id,
            'descripcion' => array_key_exists('descripcion', $validated) ? $validated['descripcion'] : $plan->descripcion,
            'color' => $validated['color'] ?? $plan->color,
            'icono' => $validated['icono'] ?? $plan->icono,
            'estado' => $validated['estado'] ?? $plan->estado,
        ]);

        $plan->load(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])->loadCount('semanas');

        return response()->json([
            'message' => 'Programa actualizado correctamente.',
            'plan' => $this->formatPlan($plan),
        ]);
    }

    public function destroyPlan(string $id): JsonResponse
    {
        $plan = PlanEntrenamiento::query()->findOrFail($id);
        $plan->update(['estado' => 'inactivo']);
        $plan->delete(); // Soft delete

        return response()->json([
            'message' => 'Programa eliminado correctamente.',
        ]);
    }

    public function asignarAlumnoPlan(Request $request, string $planId): JsonResponse
    {
        $alumnoId = $request->input('alumno_id') ?? $request->input('id_socio');
        $request->merge(['alumno_id' => $alumnoId]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
        ]);

        $plan = PlanEntrenamiento::query()->findOrFail($planId);

        $asignacion = AlumnoPlan::withTrashed()
            ->where('plan_id', $plan->id)
            ->where('alumno_id', $validated['alumno_id'])
            ->first();

        if ($asignacion) {
            if ($asignacion->trashed()) {
                $asignacion->restore();
            }
            $asignacion->update([
                'fecha_inicio' => $validated['fecha_inicio'] ?? now()->toDateString(),
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'estado' => 'activo',
            ]);
        } else {
            AlumnoPlan::create([
                'plan_id' => $plan->id,
                'alumno_id' => $validated['alumno_id'],
                'fecha_inicio' => $validated['fecha_inicio'] ?? now()->toDateString(),
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'estado' => 'activo',
            ]);
        }

        $plan->load(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])->loadCount('semanas');

        return response()->json([
            'message' => 'Alumno asignado al plan correctamente.',
            'plan' => $this->formatPlan($plan),
        ], 201);
    }

    public function desasignarAlumnoPlan(string $planId, string $alumnoId): JsonResponse
    {
        $plan = PlanEntrenamiento::query()->findOrFail($planId);

        $asignaciones = AlumnoPlan::query()
            ->where('plan_id', $plan->id)
            ->where('alumno_id', $alumnoId)
            ->get();

        foreach ($asignaciones as $asig) {
            $asig->update(['estado' => 'inactivo', 'fecha_fin' => now()->toDateString()]);
            $asig->delete();
        }

        $plan->load(['coach', 'grupo', 'alumnoPlanes.alumno.usuario'])->loadCount('semanas');

        return response()->json([
            'message' => 'Alumno quitado del plan.',
            'plan' => $this->formatPlan($plan),
        ]);
    }

    // ─── SEMANAS Y DÍAS (KANBAN) ───────────────────────────────────────────────

    public function storeSemana(Request $request): JsonResponse
    {
        $planId = $request->input('plan_id') ?? $request->input('tipoPlanId');
        $fechaInicio = $request->input('fecha_inicio') ?? $request->input('fechaInicio');
        $request->merge([
            'plan_id' => $planId,
            'fecha_inicio' => $fechaInicio,
        ]);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:planes,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'fecha_inicio' => ['nullable', 'date'],
            'estado' => ['nullable', 'string'],
        ]);

        $semana = DB::transaction(function () use ($validated) {
            $maxNumero = (int) Semana::withTrashed()
                ->where('plan_id', $validated['plan_id'])
                ->max('numero');

            $semana = Semana::create([
                'plan_id' => $validated['plan_id'],
                'titulo' => $validated['titulo'],
                'numero' => $maxNumero + 1,
                'estado' => $validated['estado'] ?? 'planificacion',
                'fecha_inicio' => $validated['fecha_inicio'] ?? now()->toDateString(),
            ]);

            foreach (self::DIAS_SEMANA as $idx => $nombreDia) {
                Dia::create([
                    'semana_id' => $semana->id,
                    'nombre' => $nombreDia,
                    'descanso' => false,
                    'contenido' => '',
                    'orden' => $idx + 1,
                ]);
            }

            return $semana->load(['plan', 'dias.links', 'dias.comentarios.autor', 'dias.comentarios.alumno.usuario']);
        });

        return response()->json([
            'message' => 'Semana creada correctamente.',
            'semana' => $this->formatSemana($semana),
        ], 201);
    }

    public function updateSemana(Request $request, string $id): JsonResponse
    {
        $semana = Semana::query()->findOrFail($id);

        $fechaInicio = $request->input('fecha_inicio') ?? $request->input('fechaInicio');
        if ($fechaInicio !== null) {
            $request->merge(['fecha_inicio' => $fechaInicio]);
        }

        $validated = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:150'],
            'estado' => ['sometimes', 'required', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
        ]);

        $semana->update($validated);
        $semana->load(['plan', 'dias.links', 'dias.comentarios.autor', 'dias.comentarios.alumno.usuario']);

        return response()->json([
            'message' => 'Semana actualizada correctamente.',
            'semana' => $this->formatSemana($semana),
        ]);
    }

    public function duplicarSemana(string $id): JsonResponse
    {
        $original = Semana::query()->with(['dias.links', 'dias.diaEjercicios'])->findOrFail($id);

        $nueva = DB::transaction(function () use ($original) {
            $maxNumero = (int) Semana::withTrashed()
                ->where('plan_id', $original->plan_id)
                ->max('numero');

            $nuevaFecha = $original->fecha_inicio
                ? $original->fecha_inicio->copy()->addDays(7)->toDateString()
                : now()->toDateString();

            $nuevaSemana = Semana::create([
                'plan_id' => $original->plan_id,
                'titulo' => $original->titulo . ' (Copia)',
                'numero' => $maxNumero + 1,
                'estado' => 'planificacion',
                'fecha_inicio' => $nuevaFecha,
            ]);

            foreach ($original->dias as $diaOrig) {
                $nuevoDia = Dia::create([
                    'semana_id' => $nuevaSemana->id,
                    'nombre' => $diaOrig->nombre,
                    'descanso' => $diaOrig->descanso,
                    'contenido' => $diaOrig->contenido,
                    'orden' => $diaOrig->orden,
                ]);

                foreach ($diaOrig->links as $linkOrig) {
                    LinkDia::create([
                        'dia_id' => $nuevoDia->id,
                        'titulo' => $linkOrig->titulo,
                        'url' => $linkOrig->url,
                    ]);
                }
            }

            return $nuevaSemana->load(['plan', 'dias.links', 'dias.comentarios.autor', 'dias.comentarios.alumno.usuario']);
        });

        return response()->json([
            'message' => 'Semana duplicada correctamente.',
            'semana' => $this->formatSemana($nueva),
        ], 201);
    }

    public function destroySemana(string $id): JsonResponse
    {
        $semana = Semana::query()->findOrFail($id);
        $semana->delete(); // Soft delete

        return response()->json([
            'message' => 'Semana eliminada correctamente.',
        ]);
    }

    public function updateDia(Request $request, string $id): JsonResponse
    {
        $dia = Dia::query()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:30'],
            'descanso' => ['sometimes', 'boolean'],
            'contenido' => ['nullable', 'string'],
        ]);

        $dia->update($validated);
        $dia->load(['links', 'comentarios.autor', 'comentarios.alumno.usuario']);

        return response()->json([
            'message' => 'Día actualizado correctamente.',
            'dia' => $this->formatDia($dia),
        ]);
    }

    public function storeLink(Request $request, string $diaId): JsonResponse
    {
        $dia = Dia::query()->findOrFail($diaId);

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'max:500'],
        ]);

        $link = LinkDia::create([
            'dia_id' => $dia->id,
            'titulo' => $validated['titulo'],
            'url' => $validated['url'],
        ]);

        return response()->json([
            'message' => 'Enlace agregado correctamente.',
            'link' => [
                'id' => (string) $link->id,
                'titulo' => $link->titulo,
                'url' => $link->url,
            ],
        ], 201);
    }

    public function destroyLink(string $id): JsonResponse
    {
        $link = LinkDia::query()->findOrFail($id);
        $link->delete();

        return response()->json([
            'message' => 'Enlace eliminado correctamente.',
        ]);
    }

    // ─── EJERCICIOS Y RM (REPETICIÓN MÁXIMA / MARCAS PERSONALES) ───────────────

    public function indexEjercicios(): JsonResponse
    {
        $this->ensureDefaultEjercicios();

        $ejercicios = Ejercicio::query()
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'ejercicios' => $ejercicios,
            'data' => $ejercicios,
        ]);
    }

    public function storeEjercicio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:60'],
            'unidad' => ['required', 'string', 'in:kg,reps,segundos'],
            'descripcion' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
        ]);

        $ejercicio = Ejercicio::create($validated);

        return response()->json([
            'message' => 'Ejercicio guardado en el catálogo.',
            'ejercicio' => $ejercicio,
        ], 201);
    }

    public function destroyEjercicio(string $id): JsonResponse
    {
        $ejercicio = Ejercicio::query()->findOrFail($id);
        $ejercicio->delete();

        return response()->json([
            'message' => 'Ejercicio eliminado correctamente.',
        ]);
    }

    public function indexRms(Request $request): JsonResponse
    {
        $this->ensureDefaultEjercicios();

        $alumnoId = $request->input('alumno_id')
            ?? $request->input('id_socio')
            ?? $request->user()?->alumno?->id;

        $query = Rm::query()
            ->with(['ejercicio', 'alumno.usuario'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($alumnoId) {
            $query->where('alumno_id', $alumnoId);
        }

        $rms = $query->get()->map(fn (Rm $rm) => [
            'id' => (string) $rm->id,
            'alumno_id' => (string) $rm->alumno_id,
            'alumno_nombre' => $rm->alumno?->usuario?->nombre_completo ?? '',
            'ejercicio_id' => (string) $rm->ejercicio_id,
            'ejercicio' => $rm->ejercicio?->nombre ?? '',
            'categoria' => $rm->ejercicio?->categoria ?? 'Fuerza',
            'unidad' => $rm->ejercicio?->unidad ?? 'kg',
            'kg' => (float) $rm->kg,
            'fecha' => $rm->fecha?->format('Y-m-d') ?? '',
        ]);

        return response()->json([
            'rms' => $rms,
            'data' => $rms,
        ]);
    }

    public function storeRm(Request $request): JsonResponse
    {
        $alumnoId = $request->input('alumno_id')
            ?? $request->input('id_socio')
            ?? $request->user()?->alumno?->id;

        $ejercicioNombre = $request->input('ejercicio_nombre') ?? $request->input('ejercicio');
        $request->merge([
            'alumno_id' => $alumnoId,
            'ejercicio_nombre' => $ejercicioNombre,
        ]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'ejercicio_id' => ['nullable', 'exists:ejercicios,id'],
            'ejercicio_nombre' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'unidad' => ['nullable', 'string', 'in:kg,reps,segundos'],
            'kg' => ['required', 'numeric', 'min:0.1'],
            'fecha' => ['nullable', 'date'],
        ]);

        if (! empty($validated['ejercicio_id'])) {
            $ejercicio = Ejercicio::query()->findOrFail($validated['ejercicio_id']);
        } elseif (! empty($validated['ejercicio_nombre'])) {
            $ejercicio = $this->resolveOrCreateEjercicio(
                trim($validated['ejercicio_nombre']),
                $validated['categoria'] ?? null,
                $validated['unidad'] ?? null
            );
        } else {
            return response()->json([
                'message' => 'Debés indicar el ejercicio.',
            ], 422);
        }

        $rm = Rm::create([
            'alumno_id' => $validated['alumno_id'],
            'ejercicio_id' => $ejercicio->id,
            'kg' => $validated['kg'],
            'fecha' => $validated['fecha'] ?? now()->toDateString(),
        ]);

        $rm->load(['ejercicio', 'alumno.usuario']);

        return response()->json([
            'message' => 'Marca personal (RM) registrada correctamente.',
            'rm' => [
                'id' => (string) $rm->id,
                'alumno_id' => (string) $rm->alumno_id,
                'alumno_nombre' => $rm->alumno?->usuario?->nombre_completo ?? '',
                'ejercicio_id' => (string) $rm->ejercicio_id,
                'ejercicio' => $rm->ejercicio?->nombre ?? '',
                'categoria' => $rm->ejercicio?->categoria ?? 'Fuerza',
                'unidad' => $rm->ejercicio?->unidad ?? 'kg',
                'kg' => (float) $rm->kg,
                'fecha' => $rm->fecha?->format('Y-m-d') ?? '',
            ],
        ], 201);
    }

    public function updateRm(Request $request, string $id): JsonResponse
    {
        $rm = Rm::query()->findOrFail($id);

        $ejercicioNombre = $request->input('ejercicio_nombre') ?? $request->input('ejercicio');
        if ($ejercicioNombre) {
            $request->merge(['ejercicio_nombre' => $ejercicioNombre]);
        }

        $validated = $request->validate([
            'ejercicio_id' => ['nullable', 'exists:ejercicios,id'],
            'ejercicio_nombre' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'unidad' => ['nullable', 'string', 'in:kg,reps,segundos'],
            'kg' => ['required', 'numeric', 'min:0.1'],
            'fecha' => ['nullable', 'date'],
        ]);

        if (! empty($validated['ejercicio_id'])) {
            $rm->ejercicio_id = $validated['ejercicio_id'];
        } elseif (! empty($validated['ejercicio_nombre'])) {
            $ejercicio = $this->resolveOrCreateEjercicio(
                trim($validated['ejercicio_nombre']),
                $validated['categoria'] ?? null,
                $validated['unidad'] ?? null
            );
            $rm->ejercicio_id = $ejercicio->id;
        }

        $rm->kg = $validated['kg'];
        if (! empty($validated['fecha'])) {
            $rm->fecha = $validated['fecha'];
        }
        $rm->save();
        $rm->load(['ejercicio', 'alumno.usuario']);

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'rm' => [
                'id' => (string) $rm->id,
                'alumno_id' => (string) $rm->alumno_id,
                'alumno_nombre' => $rm->alumno?->usuario?->nombre_completo ?? '',
                'ejercicio_id' => (string) $rm->ejercicio_id,
                'ejercicio' => $rm->ejercicio?->nombre ?? '',
                'categoria' => $rm->ejercicio?->categoria ?? 'Fuerza',
                'unidad' => $rm->ejercicio?->unidad ?? 'kg',
                'kg' => (float) $rm->kg,
                'fecha' => $rm->fecha?->format('Y-m-d') ?? '',
            ],
        ]);
    }

    public function destroyRm(string $id): JsonResponse
    {
        $rm = Rm::query()->findOrFail($id);
        $rm->delete();

        return response()->json([
            'message' => 'Marca eliminada correctamente.',
        ]);
    }

    // ─── PORTAL DEL ALUMNO: MI PLAN + PROGRESO + COMENTARIOS ───────────────────

    public function miPlan(Request $request): JsonResponse
    {
        $this->ensureDefaultEjercicios();

        $alumnoId = $request->input('alumno_id')
            ?? $request->input('id_socio')
            ?? $request->user()?->alumno?->id;

        if (! $alumnoId) {
            $user = $request->user();
            if ($user && ($user->isCoach() || $user->isAdmin())) {
                $planes = PlanEntrenamiento::query()
                    ->where('estado', 'activo')
                    ->whereIn('tipo', ['general', 'clase'])
                    ->with(['coach', 'grupo'])
                    ->orderBy('id')
                    ->get();
                $planesIds = $planes->pluck('id')->all();
                $semanas = Semana::query()
                    ->whereIn('plan_id', $planesIds)
                    ->whereIn('estado', ['en-curso', 'planificacion', 'finalizado'])
                    ->with(['dias.links', 'dias.comentarios.autor', 'dias.comentarios.alumno.usuario'])
                    ->orderByDesc('numero')
                    ->orderByDesc('id')
                    ->get();

                return response()->json([
                    'alumno' => null,
                    'planes' => $planes->map(fn ($p) => $this->formatPlan($p)),
                    'semanas' => $semanas->map(fn ($s) => $this->formatSemana($s)),
                    'rms' => [],
                    'progreso_dias' => [],
                    'comentarios' => [],
                ]);
            }

            return response()->json([
                'planes' => [],
                'semanas' => [],
                'rms' => [],
                'progreso_dias' => [],
                'comentarios' => [],
            ]);
        }

        $alumno = Alumno::query()->with(['usuario', 'membresiaActiva.tipoMembresia'])->findOrFail($alumnoId);

        // 1. Planes personalizados asignados directamente al alumno
        $planesPersonalizadosIds = AlumnoPlan::query()
            ->where('alumno_id', $alumno->id)
            ->where('estado', 'activo')
            ->pluck('plan_id')
            ->all();

        // 2. Clases/Grupos donde el alumno tiene turno fijo activo o reserva reciente
        $gruposFijosIds = InscripcionTurno::query()
            ->where('alumno_id', $alumno->id)
            ->where('estado', 'activa')
            ->with('turno')
            ->get()
            ->pluck('turno.grupo_id')
            ->filter()
            ->unique()
            ->all();

        $gruposReservaIds = Reserva::query()
            ->where('alumno_id', $alumno->id)
            ->where('estado', 'reservada')
            ->whereDate('fecha', '>=', now()->subDays(7)->toDateString())
            ->pluck('grupo_id')
            ->filter()
            ->unique()
            ->all();

        $gruposAlumnoIds = array_values(array_unique(array_merge($gruposFijosIds, $gruposReservaIds)));

        // Obtener todos los planes activos que corresponden al alumno (personalizados + de su clase + generales)
        $planes = PlanEntrenamiento::query()
            ->where('estado', 'activo')
            ->where(function ($q) use ($planesPersonalizadosIds, $gruposAlumnoIds) {
                $q->where('tipo', 'general');
                if (! empty($planesPersonalizadosIds)) {
                    $q->orWhereIn('id', $planesPersonalizadosIds);
                }
                if (! empty($gruposAlumnoIds)) {
                    $q->orWhere(function ($sub) use ($gruposAlumnoIds) {
                        $sub->where('tipo', 'clase')
                            ->where(function ($g) use ($gruposAlumnoIds) {
                                $g->whereIn('grupo_id', $gruposAlumnoIds)
                                    ->orWhereNull('grupo_id');
                            });
                    });
                } else {
                    // También planes de clase sin grupo específico (WOD general de clases)
                    $q->orWhere(function ($sub) {
                        $sub->where('tipo', 'clase')->whereNull('grupo_id');
                    });
                }
            })
            ->with(['coach', 'grupo'])
            ->get();

        $planIds = $planes->pluck('id')->all();

        // El alumno SOLO ve la semana que está en curso
        $semanas = Semana::query()
            ->whereIn('plan_id', $planIds)
            ->whereIn('estado', ['en-curso', 'publicada'])
            ->with([
                'plan',
                'dias.links',
                'dias.comentarios' => fn ($q) => $q->where('alumno_id', $alumno->id)->with('autor'),
            ])
            ->orderByDesc('numero')
            ->get()
            ->map(fn (Semana $s) => $this->formatSemana($s));

        $rms = Rm::query()
            ->where('alumno_id', $alumno->id)
            ->with('ejercicio')
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (Rm $rm) => [
                'id' => (string) $rm->id,
                'ejercicio_id' => (string) $rm->ejercicio_id,
                'ejercicio' => $rm->ejercicio?->nombre ?? '',
                'categoria' => $rm->ejercicio?->categoria ?? 'Fuerza',
                'unidad' => $rm->ejercicio?->unidad ?? 'kg',
                'kg' => (float) $rm->kg,
                'fecha' => $rm->fecha?->format('Y-m-d') ?? '',
            ]);

        $progresoDiasIds = ProgresoDia::query()
            ->where('alumno_id', $alumno->id)
            ->pluck('dia_id')
            ->map(fn ($id) => (string) $id)
            ->values();

        return response()->json([
            'alumno' => [
                'id' => (string) $alumno->id,
                'nombre' => $alumno->usuario?->nombre_completo ?? '',
                'estado' => $alumno->estado_value,
                'plan_membresia' => $alumno->membresiaActiva?->tipoMembresia?->nombre ?? 'Sin membresía',
                'vencimiento' => $alumno->membresiaActiva?->fecha_fin?->format('Y-m-d'),
            ],
            'planes' => $planes->map(fn (PlanEntrenamiento $p) => $this->formatPlan($p))->values(),
            'semanas' => $semanas,
            'rms' => $rms,
            'progreso_dias' => $progresoDiasIds,
        ]);
    }

    public function toggleProgresoDia(Request $request, string $diaId): JsonResponse
    {
        $dia = Dia::query()->findOrFail($diaId);
        $alumnoId = $request->input('alumno_id') ?? $request->user()?->alumno?->id;

        if (! $alumnoId) {
            return response()->json(['message' => 'No se encontró el perfil de alumno.'], 422);
        }

        $existente = ProgresoDia::query()
            ->where('alumno_id', $alumnoId)
            ->where('dia_id', $dia->id)
            ->first();

        if ($existente) {
            $existente->delete();
            $completado = false;
        } else {
            ProgresoDia::create([
                'alumno_id' => $alumnoId,
                'dia_id' => $dia->id,
                'completado_en' => now(),
            ]);
            $completado = true;
        }

        return response()->json([
            'message' => $completado ? '¡Entrenamiento marcado como completado!' : 'Entrenamiento desmarcado.',
            'completado' => $completado,
            'dia_id' => (string) $dia->id,
        ]);
    }

    public function storeComentario(Request $request, string $diaId): JsonResponse
    {
        $dia = Dia::query()->findOrFail($diaId);
        $usuario = $request->user();
        $alumnoId = $request->input('alumno_id') ?? $usuario?->alumno?->id;

        if (! $alumnoId) {
            $primerAlumno = Alumno::query()->first();
            $alumnoId = $primerAlumno?->id;
        }

        $validated = $request->validate([
            'texto' => ['required', 'string', 'max:2000'],
        ]);

        $comentario = Comentario::create([
            'alumno_id' => $alumnoId,
            'dia_id' => $dia->id,
            'autor_usuario_id' => $usuario?->id,
            'texto' => $validated['texto'],
        ]);

        $comentario->load(['autor', 'alumno.usuario']);

        return response()->json([
            'message' => 'Comentario enviado.',
            'comentario' => [
                'id' => (string) $comentario->id,
                'dia_id' => (string) $comentario->dia_id,
                'alumno_id' => (string) $comentario->alumno_id,
                'alumno_nombre' => $comentario->alumno?->usuario?->nombre_completo ?? 'Alumno',
                'autor_nombre' => $comentario->autor?->nombre_completo ?? 'Usuario',
                'autor_rol' => $comentario->autor?->rol_value ?? 'alumno',
                'texto' => $comentario->texto,
                'fecha' => $comentario->created_at?->format('d/m H:i') ?? '',
            ],
        ], 201);
    }

    // ─── HELPERS PRIVADOS ──────────────────────────────────────────────────────

    private function ensureDefaultEjercicios(): void
    {
        if (Ejercicio::query()->count() > 0) {
            return;
        }

        foreach (self::DEFAULT_EJERCICIOS as $ej) {
            Ejercicio::create($ej);
        }
    }

    private function resolveOrCreateEjercicio(string $nombre, ?string $categoria = null, ?string $unidad = null): Ejercicio
    {
        $existing = Ejercicio::query()->where('nombre', $nombre)->first();
        if ($existing) {
            return $existing;
        }

        // Deducir categoría y unidad automáticamente según el nombre (igual que cross-fit-coach-app)
        $autoCat = $categoria;
        $autoUnit = $unidad;

        foreach (self::DEFAULT_EJERCICIOS as $def) {
            if (strcasecmp($def['nombre'], $nombre) === 0) {
                $autoCat = $autoCat ?? $def['categoria'];
                $autoUnit = $autoUnit ?? $def['unidad'];
                break;
            }
        }

        if (! $autoCat) {
            if (in_array($nombre, ['5km', '1k'], true)) {
                $autoCat = 'Running';
            } elseif (str_ends_with($nombre, ' (Tiempo)') || str_ends_with($nombre, ' (Reps)')) {
                $autoCat = 'Capacidades';
            } else {
                $autoCat = 'Fuerza';
            }
        }

        if (! $autoUnit) {
            if ($autoCat === 'Running' || str_ends_with($nombre, ' (Tiempo)')) {
                $autoUnit = 'segundos';
            } elseif ($autoCat === 'Capacidades' || str_ends_with($nombre, ' (Reps)')) {
                $autoUnit = 'reps';
            } else {
                $autoUnit = 'kg';
            }
        }

        return Ejercicio::create([
            'nombre' => $nombre,
            'categoria' => $autoCat,
            'unidad' => $autoUnit,
        ]);
    }

    private function formatPlan(PlanEntrenamiento $plan): array
    {
        $alumnos = [];
        if ($plan->relationLoaded('alumnoPlanes')) {
            foreach ($plan->alumnoPlanes as $ap) {
                if ($ap->alumno) {
                    $alumnos[] = [
                        'id' => (string) $ap->alumno->id,
                        'nombre' => $ap->alumno->usuario?->nombre_completo ?? 'Socio #' . $ap->alumno->id,
                        'dni' => $ap->alumno->dni ?? '',
                        'fecha_inicio' => $ap->fecha_inicio?->format('Y-m-d') ?? '',
                    ];
                }
            }
        }

        return [
            'id' => (string) $plan->id,
            'nombre' => $plan->nombre,
            'tipo' => $plan->tipo ?? 'personalizado',
            'grupo_id' => $plan->grupo_id ? (string) $plan->grupo_id : null,
            'grupo_nombre' => $plan->grupo?->nombre,
            'coach_id' => $plan->coach_id ? (string) $plan->coach_id : null,
            'coach_nombre' => $plan->coach?->nombre_completo,
            'descripcion' => $plan->descripcion ?? '',
            'color' => $plan->color ?? '#f97316',
            'icono' => $plan->icono ?? 'dumbbell',
            'estado' => $plan->estado ?? 'activo',
            'semanas_count' => $plan->semanas_count ?? 0,
            'alumnos_asignados' => $alumnos,
        ];
    }

    private function formatSemana(Semana $semana): array
    {
        $estadoNorm = match ($semana->estado) {
            'borrador' => 'planificacion',
            'publicada' => 'en-curso',
            'archivada' => 'finalizado',
            default => $semana->estado,
        };

        return [
            'id' => (string) $semana->id,
            'plan_id' => (string) $semana->plan_id,
            'tipoPlanId' => (string) $semana->plan_id,
            'titulo' => $semana->titulo,
            'numero' => (int) $semana->numero,
            'estado' => $estadoNorm,
            'fecha_inicio' => $semana->fecha_inicio?->format('Y-m-d') ?? now()->toDateString(),
            'fechaInicio' => $semana->fecha_inicio?->format('Y-m-d') ?? now()->toDateString(),
            'tipoPlan' => $semana->plan ? [
                'id' => (string) $semana->plan->id,
                'nombre' => $semana->plan->nombre,
                'color' => $semana->plan->color ?? '#f97316',
                'tipo' => $semana->plan->tipo ?? 'personalizado',
            ] : ['id' => '', 'nombre' => 'Plan', 'color' => '#f97316', 'tipo' => 'personalizado'],
            'dias' => $semana->dias->map(fn (Dia $d) => $this->formatDia($d))->values(),
        ];
    }

    private function formatDia(Dia $dia): array
    {
        return [
            'id' => (string) $dia->id,
            'semana_id' => (string) $dia->semana_id,
            'nombre' => $dia->nombre,
            'descanso' => (bool) $dia->descanso,
            'contenido' => $dia->contenido ?? '',
            'orden' => (int) $dia->orden,
            'links' => $dia->relationLoaded('links')
                ? $dia->links->map(fn (LinkDia $l) => [
                    'id' => (string) $l->id,
                    'titulo' => $l->titulo,
                    'url' => $l->url,
                ])->values()
                : [],
            'comentarios' => $dia->relationLoaded('comentarios')
                ? $dia->comentarios->map(fn (Comentario $c) => [
                    'id' => (string) $c->id,
                    'dia_id' => (string) $c->dia_id,
                    'alumno_id' => (string) $c->alumno_id,
                    'alumno_nombre' => $c->alumno?->usuario?->nombre_completo ?? 'Alumno',
                    'autor_nombre' => $c->autor?->nombre_completo ?? 'Usuario',
                    'autor_rol' => $c->autor?->rol_value ?? 'alumno',
                    'texto' => $c->texto,
                    'fecha' => $c->created_at?->format('d/m H:i') ?? '',
                ])->values()
                : [],
        ];
    }
}
