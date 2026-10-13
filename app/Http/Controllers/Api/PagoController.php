<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PagoResource;
use App\Models\Membresia;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = Pago::query()
            ->with(['alumno.usuario', 'membresia.tipoMembresia'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id');

        $alumnoId = $request->input('alumno_id') ?? $request->input('id_persona');
        if (! empty($alumnoId)) {
            $query->where('alumno_id', $alumnoId);
        }

        if ($request->filled('membresia_id')) {
            $query->where('membresia_id', $request->input('membresia_id'));
        }

        if ($request->filled('estado') && $request->input('estado') !== 'todos') {
            $query->where('estado', $request->input('estado'));
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
        $items = PagoResource::collection($paginator->items());

        // Calcular deuda total de membresías activas
        $deudaTotal = 0;
        $membresiasActivas = Membresia::query()
            ->where('estado', 'activa')
            ->withSum(['pagos as total_pagado' => fn ($q) => $q->where('estado', 'pagado')], 'monto')
            ->get();

        foreach ($membresiasActivas as $m) {
            $pactado = (float) $m->precio_pactado;
            $pagado = (float) ($m->total_pagado ?? 0);
            if ($pactado > $pagado) {
                $deudaTotal += ($pactado - $pagado);
            }
        }

        return response()->json([
            'pagos' => $items,
            'data' => $items,
            'deuda_total' => round($deudaTotal, 2),
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
        $alumnoId = $request->input('alumno_id') ?? $request->input('id_persona');
        $membresiaId = $request->input('membresia_id') ?? $request->input('id_membresia');
        $metodo = $request->input('metodo') ?? $request->input('metodo_pago') ?? 'efectivo';
        $notas = $request->input('notas') ?? $request->input('observaciones');

        $request->merge([
            'alumno_id' => $alumnoId,
            'membresia_id' => $membresiaId ?: null,
            'metodo' => $metodo,
            'notas' => $notas,
        ]);

        $validated = $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'membresia_id' => ['nullable', 'exists:membresias,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'metodo' => ['required', 'string', 'max:40'],
            'estado' => ['nullable', 'string', 'in:pagado,pendiente,anulado'],
            'comprobante' => ['nullable', 'string', 'max:500'],
            'notas' => ['nullable', 'string'],
        ]);

        $pago = Pago::create([
            'alumno_id' => $validated['alumno_id'],
            'membresia_id' => $validated['membresia_id'] ?? null,
            'monto' => $validated['monto'],
            'fecha_pago' => $validated['fecha_pago'],
            'metodo' => $validated['metodo'],
            'estado' => $validated['estado'] ?? 'pagado',
            'comprobante' => $validated['comprobante'] ?? null,
            'notas' => $validated['notas'] ?? null,
        ]);

        $pago->load(['alumno.usuario', 'membresia.tipoMembresia']);
        $resource = new PagoResource($pago);

        return response()->json([
            'message' => 'Pago registrado exitosamente.',
            'pago' => $resource,
            'data' => $resource,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pago = Pago::query()->findOrFail($id);

        $validated = $request->validate([
            'monto' => ['sometimes', 'numeric', 'min:0.01'],
            'fecha_pago' => ['sometimes', 'date'],
            'metodo' => ['sometimes', 'string', 'max:40'],
            'estado' => ['sometimes', 'string', 'in:pagado,pendiente,anulado'],
            'comprobante' => ['nullable', 'string', 'max:500'],
            'notas' => ['nullable', 'string'],
        ]);

        $pago->update($validated);
        $pago->load(['alumno.usuario', 'membresia.tipoMembresia']);
        $resource = new PagoResource($pago);

        return response()->json([
            'message' => 'Pago actualizado correctamente.',
            'pago' => $resource,
            'data' => $resource,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $pago = Pago::query()->findOrFail($id);
        $pago->update(['estado' => 'anulado']);
        $pago->delete(); // Soft delete

        return response()->json([
            'message' => 'Pago anulado correctamente.',
        ]);
    }
}
