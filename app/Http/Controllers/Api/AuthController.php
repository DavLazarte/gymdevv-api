<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePerfilRequest;
use App\Http\Resources\TenantConfigResource;
use App\Http\Resources\UsuarioResource;
use App\Services\AuthService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TenantContext $tenantContext
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name') ?? 'web'
        );

        $usuarioResource = new UsuarioResource($result['usuario']);
        $tenantResource = new TenantConfigResource($this->tenantContext->getTenant());

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'usuario' => $usuarioResource,
                'tenant' => $tenantResource,
            ],
            // Campos en raíz para compatibilidad directa con devgym (lib/api.ts)
            'access_token' => $result['token'],
            'token' => $result['token'],
            'user' => $usuarioResource,
            'tenant' => $tenantResource,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $usuarioResource = new UsuarioResource($usuario);
        $tenantResource = new TenantConfigResource($this->tenantContext->getTenant());

        return response()->json([
            'data' => [
                'usuario' => $usuarioResource,
                'tenant' => $tenantResource,
            ],
            // Campos en raíz para compatibilidad con devgym (useRequireRole / AuthContext)
            'user' => $usuarioResource,
            'roles' => [$usuario->rol_value],
            'tenant' => $tenantResource,
        ]);
    }

    public function updatePerfil(UpdatePerfilRequest $request): JsonResponse
    {
        $usuario = $this->authService->updatePerfil(
            $request->user(),
            $request->validated()
        );

        $usuarioResource = new UsuarioResource($usuario);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'data' => $usuarioResource,
            'user' => $usuarioResource,
        ]);
    }
}
