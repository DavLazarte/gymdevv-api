<?php

namespace App\Http\Requests\Usuario;

use App\Enums\RolUsuario;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $usuarioId = $this->route('usuario')?->id ?? $this->route('usuario');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:190',
                Rule::unique('usuarios', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($usuarioId),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'rol' => ['sometimes', Rule::in(RolUsuario::values())],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
