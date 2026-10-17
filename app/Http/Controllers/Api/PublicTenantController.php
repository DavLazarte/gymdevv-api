<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoAlumno;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Tenant;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use App\Services\AlumnoService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicTenantController extends Controller
{
    public function __construct(
        private readonly AlumnoService $alumnoService
    ) {}

    /**
     * Devuelve los datos públicos de un Box por su slug (para la Landing Page)
     */
    public function show(string $slug): JsonResponse
    {
        $tenant = Tenant::findBySlug($slug);

        if (! $tenant || ! $tenant->activo) {
            return response()->json([
                'message' => 'Gimnasio no encontrado o inactivo.',
            ], 404);
        }

        $cfg = $tenant->configuracion ?? [];

        // Planes / Membresías activas del Box
        $planes = TipoMembresia::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->select('id', 'nombre', 'descripcion', 'modalidad', 'precio', 'duracion_dias', 'creditos')
            ->orderBy('precio')
            ->get();

        $hasLeadsTable = \Illuminate\Support\Facades\Schema::hasTable('leads');

        // Clases / Disciplinas activas del Box con cálculo de cupos de turnos fijos
        $gruposQuery = Grupo::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->whereNull('deleted_at');

        if (\Illuminate\Support\Facades\Schema::hasTable('inscripciones_turno')) {
            $gruposQuery->with(['turnos.inscripciones' => function ($q) {
                $q->where('estado', 'activa')->whereNull('fecha_baja');
            }]);
        }

        $clases = $gruposQuery->orderBy('hora_inicio')->get()->map(function (Grupo $grupo) use ($tenant, $hasLeadsTable) {
            $inscriptosFijosIds = [];
            if ($grupo->relationLoaded('turnos')) {
                foreach ($grupo->turnos as $turno) {
                    if ($turno->relationLoaded('inscripciones')) {
                        foreach ($turno->inscripciones as $insc) {
                            $inscriptosFijosIds[$insc->alumno_id] = true;
                        }
                    }
                }
            }
            $inscriptosFijos = count($inscriptosFijosIds);

            // Leads pendientes / contactados que descuentan cupo virtual en preventa
            $leadsPendientes = 0;
            if ($hasLeadsTable) {
                $leadsPendientes = \App\Models\Lead::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('grupo_id', $grupo->id)
                    ->whereIn('estado', ['pendiente', 'contactado'])
                    ->count();
            }

            $cupoMaximo = (int) $grupo->cupo_default;
            $cuposDisponibles = max(0, $cupoMaximo - $inscriptosFijos - $leadsPendientes);

            return [
                'id' => (string) $grupo->id,
                'nombre' => $grupo->nombre,
                'descripcion' => $grupo->descripcion,
                'modalidad' => $grupo->modalidad,
                'hora_inicio' => $grupo->hora_inicio,
                'hora_fin' => $grupo->hora_fin,
                'dias_semana' => $grupo->dias_semana,
                'cupo_default' => $cupoMaximo,
                'cupo_maximo' => $cupoMaximo,
                'inscriptos_fijos' => $inscriptosFijos,
                'leads_pendientes' => $leadsPendientes,
                'cupos_disponibles' => $cuposDisponibles,
                'completo' => $cuposDisponibles === 0,
            ];
        });

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'nombre' => $tenant->nombre,
                'slug' => $tenant->slug,
                'dominio' => $tenant->dominio,
                'logo_url' => $cfg['logo_url'] ?? $tenant->logo_url ?? '/logo.jpeg',
                'color_primario' => $tenant->color_primario ?? $cfg['color_primario'] ?? '#f97316',
                'tema_modo' => $cfg['tema_modo'] ?? 'dark',
                'direccion' => $cfg['direccion'] ?? null,
                'telefono' => $cfg['telefono'] ?? null,
                'email' => $cfg['email'] ?? null,
                'descripcion' => $cfg['descripcion'] ?? null,
                'horarios' => $cfg['horarios'] ?? null,
                'alias_pago' => $cfg['alias_pago'] ?? null,
            ],
            'planes' => $planes,
            'clases' => $clases,
        ]);
    }

    /**
     * Registra un prospecto / lead desde el formulario de la Landing
     */
    public function registerLead(Request $request, string $slug): JsonResponse
    {
        $tenant = Tenant::findBySlug($slug);

        if (! $tenant || ! $tenant->activo) {
            return response()->json([
                'message' => 'Gimnasio no encontrado o inactivo.',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'telefono' => ['required', 'string', 'max:40'],
            'grupo_id' => ['nullable', 'string'],
            'clase_id' => ['nullable', 'string'],
            'objetivo' => ['nullable', 'string', 'max:255'],
            'plan_interes' => ['nullable', 'string', 'max:100'],
            'plan_interes_id' => ['nullable', 'numeric'],
            'mensaje' => ['nullable', 'string', 'max:500'],
        ]);

        $grupoId = $validated['grupo_id'] ?? $validated['clase_id'] ?? null;
        if ($grupoId === 'coordinar' || $grupoId === 'ninguno' || $grupoId === '') {
            $grupoId = null;
        }

        $normalizedEmail = strtolower(trim($validated['email']));
        $hasLeadsTable = \Illuminate\Support\Facades\Schema::hasTable('leads');

        if ($hasLeadsTable) {
            $estado = 'pendiente';
            $grupo = null;

            if ($grupoId) {
                $grupo = Grupo::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('id', $grupoId)
                    ->first();

                if ($grupo) {
                    $inscriptosFijos = 0;
                    if (\Illuminate\Support\Facades\Schema::hasTable('inscripciones_turno')) {
                        $inscriptosFijos = \App\Models\InscripcionTurno::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->whereHas('turno', fn ($q) => $q->where('grupo_id', $grupo->id))
                            ->where('estado', 'activa')
                            ->whereNull('fecha_baja')
                            ->distinct('alumno_id')
                            ->count('alumno_id');
                    }
                    $leadsPendientes = \App\Models\Lead::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('grupo_id', $grupo->id)
                        ->whereIn('estado', ['pendiente', 'contactado'])
                        ->count();

                    $disponibles = max(0, (int) $grupo->cupo_default - $inscriptosFijos - $leadsPendientes);
                    if ($disponibles <= 0) {
                        $estado = 'espera';
                    }
                }
            }

            // Buscar si ya existe un lead para este email
            $lead = \App\Models\Lead::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', $normalizedEmail)
                ->whereIn('estado', ['pendiente', 'contactado', 'espera'])
                ->first();

            $leadData = [
                'tenant_id' => $tenant->id,
                'grupo_id' => $grupo ? $grupo->id : null,
                'plan_interes_id' => ! empty($validated['plan_interes_id']) ? $validated['plan_interes_id'] : null,
                'nombre' => trim($validated['nombre']),
                'apellido' => ! empty($validated['apellido']) ? trim($validated['apellido']) : null,
                'email' => $normalizedEmail,
                'telefono' => trim($validated['telefono']),
                'mensaje' => $validated['mensaje'] ?? ($validated['objetivo'] ?? null),
                'estado' => $estado,
                'fecha_registro' => now()->toDateString(),
            ];

            if ($lead) {
                $lead->update($leadData);
            } else {
                $lead = \App\Models\Lead::create($leadData);
            }

            $message = $estado === 'espera'
                ? '¡Recibimos tus datos! Este horario ya se encuentra completo, pero te agregamos a la Lista de Espera prioritaria y te avisaremos ante cualquier vacante.'
                : '¡Inscripción recibida con éxito! Te reservamos tu lugar en el turno y te contactaremos por WhatsApp para coordinar.';

            return response()->json([
                'message' => $message,
                'lead_id' => $lead->id,
                'estado' => $estado,
                'es_lista_espera' => $estado === 'espera',
            ], 201);
        }

        // Fallback histórico en caso de que la tabla leads todavía no esté migrada en la BD
        $existingUser = Usuario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $normalizedEmail)
            ->whereNull('deleted_at')
            ->first();

        if ($existingUser) {
            return response()->json([
                'message' => '¡Hola! Ya tenemos registrado tu correo en este gimnasio. Podés iniciar sesión directamente o escribirnos al WhatsApp.',
                'ya_registrado' => true,
            ], 200);
        }

        // Crear como Socio en estado 'prospecto'
        $alumno = DB::transaction(function () use ($tenant, $validated, $normalizedEmail, $grupoId) {
            $notaProspecto = 'Registro desde Landing Web.';
            if ($grupoId) {
                $notaProspecto .= ' Turno fijo ID: ' . $grupoId . '.';
            }
            if (! empty($validated['objetivo'])) {
                $notaProspecto .= ' Objetivo: ' . $validated['objetivo'] . '.';
            }
            if (! empty($validated['plan_interes'])) {
                $notaProspecto .= ' Plan de interés: ' . $validated['plan_interes'] . '.';
            }
            if (! empty($validated['mensaje'])) {
                $notaProspecto .= ' Mensaje: ' . $validated['mensaje'];
            }

            $usuario = Usuario::create([
                'tenant_id' => $tenant->id,
                'nombre' => trim($validated['nombre']),
                'apellido' => ! empty($validated['apellido']) ? trim($validated['apellido']) : null,
                'email' => $normalizedEmail,
                'telefono' => trim($validated['telefono']),
                'password' => '12345678', // Clave inicial por defecto
                'rol' => RolUsuario::Alumno,
                'activo' => true,
            ]);

            return Alumno::create([
                'tenant_id' => $tenant->id,
                'usuario_id' => $usuario->id,
                'fecha_alta' => now()->toDateString(),
                'estado' => EstadoAlumno::Prospecto->value,
                'notas' => $notaProspecto,
            ]);
        });

        return response()->json([
            'message' => '¡Inscripción recibida con éxito! Te contactaremos a la brevedad.',
            'prospecto_id' => $alumno->id,
        ], 201);
    }
}
