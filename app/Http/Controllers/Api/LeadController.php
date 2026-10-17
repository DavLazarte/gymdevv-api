<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoAlumno;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\InscripcionTurno;
use App\Models\Lead;
use App\Models\Membresia;
use App\Models\MovimientoCredito;
use App\Models\Pago;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Listado de leads y estadísticas de pre-venta.
     * GET /api/leads
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->id();

        if (! Schema::hasTable('leads')) {
            // Fallback: Si aún no se corrió la migración, leer prospectos de alumnos
            $prospectos = Alumno::query()
                ->where('estado', 'prospecto')
                ->with('usuario')
                ->orderByDesc('id')
                ->get()
                ->map(function ($alumno) {
                    return [
                        'id' => $alumno->id,
                        'tenant_id' => $alumno->tenant_id,
                        'grupo_id' => null,
                        'plan_interes_id' => null,
                        'alumno_id' => $alumno->id,
                        'nombre' => $alumno->usuario?->nombre ?? '',
                        'apellido' => $alumno->usuario?->apellido ?? '',
                        'nombre_completo' => $alumno->usuario?->nombre_completo ?? '',
                        'email' => $alumno->usuario?->email ?? '',
                        'telefono' => $alumno->usuario?->telefono ?? '',
                        'mensaje' => $alumno->notas ?? '',
                        'estado' => 'pendiente',
                        'notas_admin' => null,
                        'fecha_registro' => $alumno->fecha_alta?->toDateString() ?? now()->toDateString(),
                        'created_at' => $alumno->created_at?->toISOString(),
                        'grupo' => null,
                        'plan_interes' => null,
                    ];
                });

            return response()->json([
                'data' => $prospectos,
                'stats' => [
                    'total' => $prospectos->count(),
                    'pendientes' => $prospectos->count(),
                    'contactados' => 0,
                    'espera' => 0,
                    'convertidos' => 0,
                    'descartados' => 0,
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 50,
                    'total' => $prospectos->count(),
                ],
            ]);
        }

        $query = Lead::query()
            ->with(['grupo.coach', 'planInteres', 'alumno.usuario'])
            ->orderByDesc('id');

        $estado = $request->input('estado');
        if ($estado && $estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($request->filled('grupo_id')) {
            $query->where('grupo_id', $request->input('grupo_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', $search)
                    ->orWhere('apellido', 'like', $search)
                    ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellido, '')) LIKE ?", [$search])
                    ->orWhere('email', 'like', $search)
                    ->orWhere('telefono', 'like', $search);
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $query->paginate($perPage);

        // Estadísticas agregadas para los badges y filtros
        $statsRaw = Lead::query()
            ->select('estado', DB::raw('count(*) as count'))
            ->groupBy('estado')
            ->pluck('count', 'estado')
            ->all();

        $stats = [
            'total' => array_sum($statsRaw),
            'pendientes' => $statsRaw['pendiente'] ?? 0,
            'contactados' => $statsRaw['contactado'] ?? 0,
            'espera' => $statsRaw['espera'] ?? 0,
            'convertidos' => $statsRaw['convertido'] ?? 0,
            'descartados' => $statsRaw['descartado'] ?? 0,
        ];

        return response()->json([
            'data' => $paginator->items(),
            'stats' => $stats,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Actualizar estado o notas de un lead.
     * PATCH/PUT /api/leads/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'in:pendiente,contactado,convertido,descartado,espera'],
            'notas_admin' => ['nullable', 'string'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'plan_interes_id' => ['nullable', 'exists:tipos_membresia,id'],
        ]);

        $lead->update(array_filter($validated, fn ($val) => $val !== null));

        return response()->json([
            'message' => 'Lead actualizado correctamente.',
            'lead' => $lead->fresh(['grupo.coach', 'planInteres', 'alumno.usuario']),
        ]);
    }

    /**
     * Convertir Lead en Socio activo:
     * 1. Crea o asocia el Alumno / Usuario
     * 2. (Opcional) Asigna la Membresía seleccionada
     * 3. (Opcional) Inscribe al socio en el Turno Fijo / Grupo seleccionado
     * 4. (Opcional) Registra el Cobro inicial
     * 5. Marca el Lead como 'convertido'
     * POST /api/leads/{id}/convertir
     */
    public function convertir(Request $request, string $id): JsonResponse
    {
        $lead = Lead::with('grupo')->findOrFail($id);

        $validated = $request->validate([
            'tipo_membresia_id' => ['nullable', 'exists:tipos_membresia,id'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'registrar_pago' => ['nullable', 'boolean'],
            'metodo_pago' => ['nullable', 'string', 'max:40'],
            'monto_pago' => ['nullable', 'numeric', 'min:0'],
            'notas_admin' => ['nullable', 'string'],
        ]);

        $tenantId = $this->tenantContext->id();

        $result = DB::transaction(function () use ($lead, $validated, $tenantId) {
            // 1. Obtener o crear Usuario y Alumno
            $alumno = null;
            if ($lead->alumno_id) {
                $alumno = Alumno::with('usuario')->find($lead->alumno_id);
            }

            if (! $alumno) {
                // Verificar si ya existe usuario con este email en el tenant
                $usuario = Usuario::query()->where('email', strtolower(trim($lead->email)))->first();

                if (! $usuario) {
                    $usuario = Usuario::create([
                        'tenant_id' => $tenantId,
                        'nombre' => $lead->nombre,
                        'apellido' => $lead->apellido,
                        'email' => strtolower(trim($lead->email)),
                        'telefono' => $lead->telefono,
                        'password' => '12345678', // Contraseña inicial por defecto
                        'rol' => RolUsuario::Alumno,
                        'activo' => true,
                    ]);
                }

                $alumno = Alumno::firstOrCreate(
                    ['usuario_id' => $usuario->id],
                    [
                        'tenant_id' => $tenantId,
                        'fecha_alta' => now()->toDateString(),
                        'estado' => EstadoAlumno::Activo->value,
                        'notas' => 'Convertido desde Lead Pre-venta.',
                    ]
                );
            }

            // Asegurar que el estado del alumno sea activo
            if ($alumno->estado !== EstadoAlumno::Activo->value) {
                $alumno->update(['estado' => EstadoAlumno::Activo->value]);
            }

            // 2. Si se especificó membresía, asignarla
            $membresia = null;
            if (! empty($validated['tipo_membresia_id'])) {
                $tipo = TipoMembresia::findOrFail($validated['tipo_membresia_id']);
                $fechaInicio = ! empty($validated['fecha_inicio']) ? Carbon::parse($validated['fecha_inicio']) : now();
                $fechaFin = ! empty($validated['fecha_fin'])
                    ? Carbon::parse($validated['fecha_fin'])
                    : ($tipo->duracion_dias ? $fechaInicio->copy()->addDays($tipo->duracion_dias) : null);

                $membresia = Membresia::create([
                    'tenant_id' => $tenantId,
                    'alumno_id' => $alumno->id,
                    'tipo_membresia_id' => $tipo->id,
                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin?->toDateString(),
                    'creditos_otorgados' => $tipo->modalidad === 'creditos' ? $tipo->creditos : null,
                    'precio_pactado' => $tipo->precio,
                    'estado' => 'activa',
                    'notas' => 'Alta por conversión de Lead Pre-venta.',
                ]);

                if ($tipo->modalidad === 'creditos' && $tipo->creditos > 0) {
                    MovimientoCredito::create([
                        'tenant_id' => $tenantId,
                        'alumno_id' => $alumno->id,
                        'membresia_id' => $membresia->id,
                        'cantidad' => (int) $tipo->creditos,
                        'motivo' => 'alta_membresia',
                        'notas' => "Alta membresía pre-venta: {$tipo->nombre}",
                        'created_at' => now(),
                    ]);
                }

                if (! empty($validated['registrar_pago'])) {
                    $montoPago = isset($validated['monto_pago']) && $validated['monto_pago'] !== ''
                        ? (float) $validated['monto_pago']
                        : (float) $tipo->precio;

                    if ($montoPago > 0) {
                        Pago::create([
                            'tenant_id' => $tenantId,
                            'alumno_id' => $alumno->id,
                            'membresia_id' => $membresia->id,
                            'monto' => $montoPago,
                            'fecha_pago' => $fechaInicio->toDateString(),
                            'metodo' => $validated['metodo_pago'] ?? 'efectivo',
                            'estado' => 'pagado',
                            'notas' => 'Pago inicial por conversión de Lead Pre-venta.',
                        ]);
                    }
                }
            }

            // 3. Inscripción al Turno Fijo / Clase
            $grupoId = $validated['grupo_id'] ?? $lead->grupo_id;
            if ($grupoId) {
                $grupo = Grupo::query()->with('turnos')->find($grupoId);
                if ($grupo) {
                    foreach ($grupo->turnos->where('activo', true) as $turno) {
                        $inscripcion = InscripcionTurno::withTrashed()
                            ->where('turno_id', $turno->id)
                            ->where('alumno_id', $alumno->id)
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
                                'tenant_id' => $tenantId,
                                'turno_id' => $turno->id,
                                'alumno_id' => $alumno->id,
                                'fecha_alta' => now()->toDateString(),
                                'fecha_baja' => null,
                                'estado' => 'activa',
                            ]);
                        }
                    }
                }
            }

            // 4. Actualizar Lead
            $lead->update([
                'estado' => 'convertido',
                'alumno_id' => $alumno->id,
                'notas_admin' => $validated['notas_admin'] ?? $lead->notas_admin,
            ]);

            return [
                'lead' => $lead->fresh(['grupo.coach', 'planInteres', 'alumno.usuario']),
                'alumno' => $alumno->load('usuario'),
                'membresia' => $membresia,
            ];
        });

        return response()->json([
            'message' => '¡Lead convertido a Socio con éxito! Ya tiene su turno asignado.',
            'lead' => $result['lead'],
            'alumno' => $result['alumno'],
            'membresia' => $result['membresia'],
        ]);
    }

    /**
     * Descartar o eliminar un lead (libera el cupo inmediatamente).
     * DELETE /api/leads/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'message' => 'Lead eliminado correctamente. El cupo del horario ha sido liberado.',
        ]);
    }
}
