<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Autentica un usuario dentro del tenant actual y genera su token Sanctum.
     *
     * @return array{token: string, usuario: Usuario}
     */
    public function login(string $email, string $password, string $deviceName = 'web'): array
    {
        $normalizedEmail = strtolower(trim($email));

        // 1. Buscar primero en el tenant actual resuelto por dominio/slug/id
        $usuario = Usuario::query()
            ->where('email', $normalizedEmail)
            ->first();

        // 2. Si entra desde el dominio unificado (ej: fitdev.devsign.ar) y el email pertenece a otro Box, detectarlo automáticamente
        if (! $usuario) {
            $usuario = Usuario::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('email', $normalizedEmail)
                ->first();

            if ($usuario && $usuario->tenant_id) {
                $userTenant = \App\Models\Tenant::query()->find($usuario->tenant_id);
                if (! $userTenant || ! $userTenant->activo) {
                    throw ValidationException::withMessages([
                        'email' => ['El gimnasio asociado a esta cuenta se encuentra inactivo.'],
                    ]);
                }
                $this->tenantContext->setTenant($userTenant);
            }
        }

        if (! $usuario || empty($usuario->password) || ! Hash::check($password, $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales ingresadas son incorrectas.'],
            ]);
        }

        if (! $usuario->activo) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta se encuentra desactivada en este gimnasio.'],
            ]);
        }

        $token = $usuario->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'usuario' => $usuario,
        ];
    }

    public function logout(Usuario $usuario): void
    {
        $currentToken = $usuario->currentAccessToken();

        if ($currentToken && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        } else {
            $usuario->tokens()->delete();
        }
    }

    public function updatePerfil(Usuario $usuario, array $data): Usuario
    {
        if (! empty($data['password'])) {
            if (! empty($data['password_actual']) && ! empty($usuario->password)) {
                if (! Hash::check($data['password_actual'], $usuario->password)) {
                    throw ValidationException::withMessages([
                        'password_actual' => ['La contraseña actual no es correcta.'],
                    ]);
                }
            }
        } else {
            unset($data['password']);
        }

        unset($data['password_actual']);

        if (isset($data['foto']) || isset($data['avatar_url'])) {
            $data['avatar_url'] = app(AlumnoService::class)->resolveAvatarUrl(
                $data['foto'] ?? $data['avatar_url'] ?? null,
                $usuario->avatar_url
            );
            unset($data['foto']);
        }

        $usuario->fill($data);
        $usuario->save();

        return $usuario->refresh();
    }
}
