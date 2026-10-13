<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isStaff()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $query = Usuario::query()->orderBy('nombre');

        if ($request->filled('rol')) {
            $query->where('rol', $request->string('rol'));
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', $search)
                    ->orWhere('apellido', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('telefono', 'like', $search);
            });
        }

        $usuarios = $query->get();

        return response()->json([
            'data' => UsuarioResource::collection($usuarios),
        ]);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = Usuario::create($request->validated());

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => new UsuarioResource($usuario),
        ], 201);
    }

    public function show(Request $request, Usuario $usuario): JsonResponse
    {
        if (! $request->user()?->isStaff()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json([
            'data' => new UsuarioResource($usuario),
        ]);
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('password', $data) && empty($data['password'])) {
            unset($data['password']);
        }

        $usuario->update($data);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => new UsuarioResource($usuario->refresh()),
        ]);
    }

    public function destroy(Request $request, Usuario $usuario): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['message' => 'Solo un administrador puede eliminar usuarios.'], 403);
        }

        if ((int) $request->user()->id === (int) $usuario->id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
        }

        $usuario->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }
}
