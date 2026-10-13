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

        // Clases / Disciplinas activas del Box
        $clases = Grupo::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->select('id', 'nombre', 'descripcion', 'modalidad', 'hora_inicio', 'hora_fin', 'dias_semana', 'cupo_default')
            ->orderBy('nombre')
            ->get();

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
            'objetivo' => ['nullable', 'string', 'max:255'],
            'plan_interes' => ['nullable', 'string', 'max:100'],
            'mensaje' => ['nullable', 'string', 'max:500'],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        // Verificar si ya existe este email en este Box
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
        $alumno = DB::transaction(function () use ($tenant, $validated, $normalizedEmail) {
            $notaProspecto = 'Registro desde Landing Web.';
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
