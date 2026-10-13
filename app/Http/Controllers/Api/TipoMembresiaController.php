<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TipoMembresiaResource;
use App\Models\TipoMembresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoMembresiaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TipoMembresia::query()->orderByDesc('id');

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', $search)
                    ->orWhere('descripcion', 'like', $search);
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $items = TipoMembresiaResource::collection($query->get());

        return response()->json([
            'servicios' => $items,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_dias' => ['nullable', 'integer', 'min:1'],
            'creditos' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', 'string', 'in:mensual,creditos'],
            'estado' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $creditos = ! empty($validated['creditos']) ? (int) $validated['creditos'] : null;
        $modalidad = $validated['modalidad'] ?? ($creditos ? 'creditos' : 'mensual');
        $activo = $validated['activo'] ?? $validated['estado'] ?? true;

        $tipo = TipoMembresia::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'modalidad' => $modalidad,
            'precio' => $validated['precio'],
            'duracion_dias' => $validated['duracion_dias'] ?? 30,
            'creditos' => $modalidad === 'creditos' ? $creditos : null,
            'activo' => (bool) $activo,
        ]);

        $resource = new TipoMembresiaResource($tipo);

        return response()->json([
            'message' => 'Plan creado exitosamente.',
            'servicio' => $resource,
            'data' => $resource,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tipo = TipoMembresia::query()->findOrFail($id);
        $resource = new TipoMembresiaResource($tipo);

        return response()->json([
            'servicio' => $resource,
            'data' => $resource,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tipo = TipoMembresia::query()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0'],
            'duracion_dias' => ['nullable', 'integer', 'min:1'],
            'creditos' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', 'string', 'in:mensual,creditos'],
            'estado' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $creditos = array_key_exists('creditos', $validated)
            ? (! empty($validated['creditos']) ? (int) $validated['creditos'] : null)
            : $tipo->creditos;

        $modalidad = $validated['modalidad'] ?? ($creditos ? 'creditos' : 'mensual');
        $activo = $validated['activo'] ?? $validated['estado'] ?? $tipo->activo;

        $tipo->update([
            'nombre' => $validated['nombre'] ?? $tipo->nombre,
            'descripcion' => array_key_exists('descripcion', $validated) ? $validated['descripcion'] : $tipo->descripcion,
            'modalidad' => $modalidad,
            'precio' => $validated['precio'] ?? $tipo->precio,
            'duracion_dias' => array_key_exists('duracion_dias', $validated) ? $validated['duracion_dias'] : $tipo->duracion_dias,
            'creditos' => $modalidad === 'creditos' ? $creditos : null,
            'activo' => (bool) $activo,
        ]);

        $resource = new TipoMembresiaResource($tipo->refresh());

        return response()->json([
            'message' => 'Plan actualizado correctamente.',
            'servicio' => $resource,
            'data' => $resource,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tipo = TipoMembresia::query()->findOrFail($id);
        $tipo->update(['activo' => false]);
        $tipo->delete(); // Soft delete

        return response()->json([
            'message' => 'Plan eliminado correctamente.',
        ]);
    }
}
