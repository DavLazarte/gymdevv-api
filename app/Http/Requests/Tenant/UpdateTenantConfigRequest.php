<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->isAdmin() || $this->user()->isCoach());
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'color_primario' => ['sometimes', 'required', 'string', 'max:20'],
            'modulos' => ['sometimes', 'array'],
            'modulos.membresias' => ['sometimes', 'boolean'],
            'modulos.pagos' => ['sometimes', 'boolean'],
            'modulos.caja' => ['sometimes', 'boolean'],
            'modulos.grupos_turnos' => ['sometimes', 'boolean'],
            'modulos.asistencias' => ['sometimes', 'boolean'],
            'modulos.entrenamiento' => ['sometimes', 'boolean'],
            'modulos.rms' => ['sometimes', 'boolean'],
            'modulos.comentarios' => ['sometimes', 'boolean'],
            'modulos.creditos' => ['sometimes', 'boolean'],
            'modulos.reservas' => ['sometimes', 'boolean'],
            'configuracion' => ['sometimes', 'array'],
            'configuracion.agenda' => ['sometimes', 'string', Rule::in(['fijo', 'reserva', 'mixto'])],
            'configuracion.consumo_credito' => ['sometimes', 'string', Rule::in(['reservar', 'asistir'])],
            'configuracion.cancelacion_horas' => ['sometimes', 'integer', 'min:0', 'max:168'],
            'configuracion.bloquear_plan_si_vencido' => ['sometimes', 'boolean'],
            'configuracion.alias_pago' => ['nullable', 'string', 'max:190'],
            'configuracion.telefono' => ['nullable', 'string', 'max:60'],
            'configuracion.email' => ['nullable', 'string', 'max:190'],
            'configuracion.direccion' => ['nullable', 'string', 'max:255'],
            'configuracion.descripcion' => ['nullable', 'string', 'max:1000'],
            'configuracion.horarios_texto' => ['nullable', 'string', 'max:500'],
            'configuracion.horarios' => ['nullable', 'array'],
            'configuracion.notificaciones' => ['nullable', 'array'],
            'configuracion.logo_url' => ['nullable', 'string'],
            'configuracion.tema_modo' => ['nullable', 'string', Rule::in(['dark', 'light'])],
            'configuracion.color_primario' => ['nullable', 'string', 'max:20'],
        ];
    }
}
