<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalidaCajaResource;
use App\Models\SalidaCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalidaCajaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = SalidaCaja::query()
            ->with(['beneficiario'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $tipo = $request->input('tipo') ?? $request->input('tipo_salida');
        if (! empty($tipo) && $tipo !== 'todos') {
            $query->where('tipo_salida', $tipo);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->input('fecha_inicio'));
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->input('fecha_fin'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', $search)
                    ->orWhere('tipo_salida', 'like', $search)
                    ->orWhereHas('beneficiario', function ($uq) use ($search) {
                        $uq->where('nombre', 'like', $search)
                            ->orWhere('apellido', 'like', $search)
                            ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellido, '')) LIKE ?", [$search]);
                    });
            });
        }

        $paginator = $query->paginate($perPage);
        $items = SalidaCajaResource::collection($paginator->items());

        $totalHistorico = (float) SalidaCaja::query()->sum('monto');
        $totalMes = (float) SalidaCaja::query()
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->sum('monto');

        return response()->json([
            'salidas' => $items,
            'data' => $items,
            'stats' => [
                'total_historico' => round($totalHistorico, 2),
                'total_mes' => round($totalMes, 2),
            ],
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
        $usuarioId = $request->input('usuario_id') ?? $request->input('id_persona');
        $request->merge([
            'usuario_id' => ! empty($usuarioId) ? $usuarioId : null,
        ]);

        $validated = $request->validate([
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
            'tipo_salida' => ['required', 'string', 'max:40'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['nullable', 'string', 'max:40'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string'],
        ]);

        $salida = SalidaCaja::create([
            'usuario_id' => $validated['usuario_id'] ?? null,
            'registrado_por_id' => $request->user()?->id,
            'tipo_salida' => $validated['tipo_salida'],
            'descripcion' => $validated['descripcion'],
            'monto' => $validated['monto'],
            'metodo_pago' => $validated['metodo_pago'] ?? 'efectivo',
            'fecha' => $validated['fecha'],
            'notas' => $validated['notas'] ?? null,
        ]);

        $salida->load('beneficiario');
        $resource = new SalidaCajaResource($salida);

        return response()->json([
            'message' => 'Gasto registrado correctamente.',
            'salida' => $resource,
            'data' => $resource,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $salida = SalidaCaja::query()->findOrFail($id);

        $usuarioId = $request->input('usuario_id') ?? $request->input('id_persona');
        if ($request->has('id_persona') || $request->has('usuario_id')) {
            $request->merge([
                'usuario_id' => ! empty($usuarioId) ? $usuarioId : null,
            ]);
        }

        $validated = $request->validate([
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
            'tipo_salida' => ['sometimes', 'required', 'string', 'max:40'],
            'descripcion' => ['sometimes', 'required', 'string', 'max:255'],
            'monto' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['nullable', 'string', 'max:40'],
            'fecha' => ['sometimes', 'required', 'date'],
            'notas' => ['nullable', 'string'],
        ]);

        $salida->update($validated);
        $salida->load('beneficiario');
        $resource = new SalidaCajaResource($salida);

        return response()->json([
            'message' => 'Gasto actualizado correctamente.',
            'salida' => $resource,
            'data' => $resource,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $salida = SalidaCaja::query()->findOrFail($id);
        $salida->delete(); // Soft delete

        return response()->json([
            'message' => 'Gasto eliminado correctamente.',
        ]);
    }
}
