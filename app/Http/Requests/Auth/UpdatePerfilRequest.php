<?php

namespace App\Http\Requests\Auth;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $userId = $this->user()?->id;

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
                    ->ignore($userId),
            ],
            'telefono' => ['nullable', 'string', 'max:40'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'password_actual' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }
}
