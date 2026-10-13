<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembresiaResource;
use App\Models\Grupo;
use App\Models\InscripcionTurno;
use App\Models\Membresia;
use App\Models\MovimientoCredito;
use App\Models\Pago;
use App\Models\TipoMembresia;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembresiaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = Membresia::query()
            ->with(['alumno.usuario', 'tipoMembresia'])
            ->orderByDesc('id');

        $alumnoId = $request->input('alumno_id') ?? $request->input('id_socio');
        if (! empty($alumnoId)) {
            $query->where('alumno_id', $alumnoId);
        }

        if ($request->filled('estado') && $request->input('estado') !== 'todas') {
            $estado = $request->string('estado')->toString();
            if ($estado === 'por_vencer') {
                $query->where('estado', 'activa')
                    ->whereNotNull('fecha_fin')
                    ->whereBetween('fecha_fin', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            } elseif ($estado === 'vencida') {
                $query->where(function ($q) {
                    $q->where('estado', 'vencida')
                        ->orWhere(function ($sub) {
                            $sub->where('estado', 'activa')
                                ->whereNotNull('fecha_fin')
                                ->where('fecha_fin', '<', now()->toDateString());
                        });
                });
            } elseif ($estado === 'con_deuda') {
                $query->where('estado', 'activa');
            } else {
                $query->where('estado', $estado);
            }
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->whereHas('alumno.usuario', function ($q) use ($search) {
                $q->where('nombre', 'like', $search)
                    ->orWhere('apellido', 'like', $search)
                    ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellido, '')) LIKE ?", [$search])
                    ->orWhere('email', 'like', $search);
            });
        }

        $paginator = $query->paginate($perPage);
        $items = MembresiaResource::collection($paginator->items());

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $alumnoId = $request->input('alumno_id') ?? $request->input('id_socio');
        $tipoId = $request->input('tipo_membresia_id') ?? $request->input('id_plan');
        $idClaseFija = $request->input('id_clase_fija') ?? $request->input('grupo_id');

        $request->merge([
            'alumno_id' => $alumnoId,
            'tipo_membresia_id' => $tipoId,
            'id_clase_fija' => $idClaseFija ?: null,
        ]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'tipo_membresia_id' => ['required', 'exists:tipos_membresia,id'],
            'id_clase_fija' => ['nullable', 'exists:grupos,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'registrar_pago' => ['nullable', 'boolean'],
            'metodo_pago' => ['nullable', 'string', 'max:40'],
            'monto_pago' => ['nullable', 'numeric', 'min:0'],
            'observaciones_pago' => ['nullable', 'string'],
        ]);

        $tipo = TipoMembresia::query()->findOrFail($validated['tipo_membresia_id']);
        $fechaInicio = Carbon::parse($validated['fecha_inicio']);
        $fechaFin = ! empty($validated['fecha_fin'])
            ? Carbon::parse($validated['fecha_fin'])
            : ($tipo->duracion_dias ? $fechaInicio->copy()->addDays($tipo->duracion_dias) : null);

        $membresia = DB::transaction(function () use ($validated, $tipo, $fechaInicio, $fechaFin) {
            $membresia = Membresia::create([
                'alumno_id' => $validated['alumno_id'],
                'tipo_membresia_id' => $tipo->id,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin?->toDateString(),
                'creditos_otorgados' => $tipo->modalidad === 'creditos' ? $tipo->creditos : null,
                'precio_pactado' => $tipo->precio,
                'estado' => 'activa',
                'notas' => $validated['notas'] ?? null,
            ]);

            // Si es por créditos, acreditamos en el libro mayor (movimientos_credito)
            if ($tipo->modalidad === 'creditos' && $tipo->creditos > 0) {
                MovimientoCredito::create([
                    'alumno_id' => $membresia->alumno_id,
                    'membresia_id' => $membresia->id,
                    'cantidad' => (int) $tipo->creditos,
                    'motivo' => 'alta_membresia',
                    'notas' => "Alta de membresía: {$tipo->nombre}",
                    'created_at' => now(),
                ]);
            }

            // Si seleccionó un Turno Fijo / Clase al asignar la membresía, lo inscribimos en los turnos de esa clase
            if (! empty($validated['id_clase_fija'])) {
                $grupo = Grupo::query()->with('turnos')->find($validated['id_clase_fija']);
                if ($grupo) {
                    foreach ($grupo->turnos->where('activo', true) as $turno) {
                        $inscripcion = InscripcionTurno::withTrashed()
                            ->where('turno_id', $turno->id)
                            ->where('alumno_id', $membresia->alumno_id)
                            ->first();

                        if ($inscripcion) {
                            if ($inscripcion->trashed()) {
                                $inscripcion->restore();
                            }
                            $inscripcion->update([
                                'fecha_alta' => $fechaInicio->toDateString(),
                                'fecha_baja' => null,
                                'estado' => 'activa',
                            ]);
                        } else {
                            InscripcionTurno::create([
                                'turno_id' => $turno->id,
                                'alumno_id' => $membresia->alumno_id,
                                'fecha_alta' => $fechaInicio->toDateString(),
                                'fecha_baja' => null,
                                'estado' => 'activa',
                            ]);
                        }
                    }
                }
            }

            // Si se marcó registrar cobro inicial, registramos el Pago
            if (! empty($validated['registrar_pago'])) {
                $montoPago = isset($validated['monto_pago']) && $validated['monto_pago'] !== ''
                    ? (float) $validated['monto_pago']
                    : (float) $tipo->precio;

                if ($montoPago > 0) {
                    Pago::create([
                        'alumno_id' => $membresia->alumno_id,
                        'membresia_id' => $membresia->id,
                        'monto' => $montoPago,
                        'fecha_pago' => now()->toDateString(),
                        'metodo' => $validated['metodo_pago'] ?? 'efectivo',
                        'estado' => 'pagado',
                        'notas' => $validated['observaciones_pago'] ?? "Cobro inicial de membresía ({$tipo->nombre})",
                    ]);
                }
            }

            return $membresia->load(['alumno.usuario', 'tipoMembresia']);
        });

        $resource = new MembresiaResource($membresia);

        return response()->json([
            'message' => 'Membresía asignada correctamente.',
            'membresia' => $resource,
            'data' => $resource,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $membresia = Membresia::query()
            ->with(['alumno.usuario', 'tipoMembresia'])
            ->findOrFail($id);

        $resource = new MembresiaResource($membresia);

        return response()->json([
            'membresia' => $resource,
            'data' => $resource,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $membresia = Membresia::query()->findOrFail($id);

        $validated = $request->validate([
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'estado' => ['sometimes', 'string', 'in:activa,vencida,cancelada,sin_creditos'],
            'notas' => ['nullable', 'string'],
        ]);

        $membresia->update($validated);
        $membresia->load(['alumno.usuario', 'tipoMembresia']);

        $resource = new MembresiaResource($membresia);

        return response()->json([
            'message' => 'Membresía actualizada correctamente.',
            'membresia' => $resource,
            'data' => $resource,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $membresia = Membresia::query()->findOrFail($id);
        $membresia->update(['estado' => 'cancelada']);
        $membresia->delete(); // Soft delete

        return response()->json([
            'message' => 'Membresía eliminada correctamente.',
        ]);
    }
}
