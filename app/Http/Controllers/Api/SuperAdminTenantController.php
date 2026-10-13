<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminTenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    private function authorizeSuperAdmin(Request $request): void
    {
        $expectedKey = (string) env('SUPERADMIN_KEY', 'devgym-master-2026');
        $providedKey = (string) (
            $request->header('X-Superadmin-Key')
            ?? $request->input('superadmin_key')
            ?? ''
        );

        if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            abort(403, 'Clave de SuperAdmin inválida o no proporcionada.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $tenants = Tenant::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tenant $t) => $this->formatTenantForSuperAdmin($t));

        return response()->json([
            'tenants' => $tenants,
            'data' => $tenants,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'dominio' => ['required', 'string', 'max:190', 'unique:tenants,dominio'],
            'color_primario' => ['nullable', 'string', 'max:20'],
            'tema_modo' => ['nullable', 'string', Rule::in(['dark', 'light'])],
            'logo_url' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'admin_nombre' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
            'admin_password' => ['nullable', 'string', 'min:6'],
            'modulos' => ['nullable', 'array'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $tenant = DB::transaction(function () use ($validated) {
            $color = $validated['color_primario'] ?? '#f97316';
            $temaModo = $validated['tema_modo'] ?? 'dark';
            $dominio = strtolower(trim($validated['dominio']));

            $config = array_merge(Tenant::DEFAULT_CONFIGURACION, [
                'tema_modo' => $temaModo,
                'color_primario' => $color,
                'telefono' => $validated['telefono'] ?? '',
                'direccion' => $validated['direccion'] ?? '',
                'email' => strtolower(trim($validated['admin_email'])),
            ]);

            $modulos = array_merge(Tenant::DEFAULT_MODULOS, [
                'creditos' => true,
                'reservas' => true,
            ], $validated['modulos'] ?? []);

            $tenant = Tenant::create([
                'nombre' => trim($validated['nombre']),
                'dominio' => $dominio,
                'color_primario' => $color,
                'modulos' => $modulos,
                'configuracion' => $config,
                'activo' => $validated['activo'] ?? true,
            ]);

            if (! empty($validated['logo_url'])) {
                $resolvedLogo = $this->tenantService->resolveLogoUrl($validated['logo_url'], $tenant->id);
                $config['logo_url'] = $resolvedLogo;
                $tenant->update([
                    'logo_url' => $resolvedLogo,
                    'configuracion' => $config,
                ]);
            }

            $parts = preg_split('/\s+/', trim($validated['admin_nombre']), 2);
            $nombreAdmin = $parts[0] ?? trim($validated['admin_nombre']);
            $apellidoAdmin = $parts[1] ?? null;
            $passwordAdmin = ! empty($validated['admin_password']) ? $validated['admin_password'] : '12345678';

            Usuario::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'nombre' => $nombreAdmin,
                'apellido' => $apellidoAdmin,
                'email' => strtolower(trim($validated['admin_email'])),
                'password' => $passwordAdmin,
                'telefono' => $validated['telefono'] ?? null,
                'rol' => RolUsuario::Admin,
                'activo' => true,
            ]);

            return $tenant->refresh();
        });

        $formatted = $this->formatTenantForSuperAdmin($tenant);

        return response()->json([
            'message' => 'Nuevo Box / Gimnasio creado exitosamente con su usuario Administrador.',
            'tenant' => $formatted,
            'data' => $formatted,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $tenant = Tenant::query()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'dominio' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('tenants', 'dominio')->ignore($tenant->id),
            ],
            'color_primario' => ['nullable', 'string', 'max:20'],
            'tema_modo' => ['nullable', 'string', Rule::in(['dark', 'light'])],
            'logo_url' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'admin_nombre' => ['nullable', 'string', 'max:120'],
            'admin_email' => ['nullable', 'email', 'max:190'],
            'admin_password' => ['nullable', 'string', 'min:6'],
            'modulos' => ['nullable', 'array'],
            'activo' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($tenant, $validated) {
            if (isset($validated['nombre'])) {
                $tenant->nombre = trim($validated['nombre']);
            }
            if (isset($validated['dominio'])) {
                $tenant->dominio = strtolower(trim($validated['dominio']));
            }
            if (! empty($validated['color_primario'])) {
                $tenant->color_primario = $validated['color_primario'];
            }
            if (isset($validated['activo'])) {
                $tenant->activo = (bool) $validated['activo'];
            }
            if (isset($validated['modulos']) && is_array($validated['modulos'])) {
                $tenant->modulos = array_merge($tenant->modulos_completos, $validated['modulos']);
            }

            $cfg = $tenant->configuracion_completa;
            if (! empty($validated['tema_modo'])) {
                $cfg['tema_modo'] = $validated['tema_modo'];
            }
            if (! empty($validated['color_primario'])) {
                $cfg['color_primario'] = $validated['color_primario'];
            }
            if (array_key_exists('telefono', $validated)) {
                $cfg['telefono'] = $validated['telefono'] ?? '';
            }
            if (array_key_exists('direccion', $validated)) {
                $cfg['direccion'] = $validated['direccion'] ?? '';
            }
            if (array_key_exists('logo_url', $validated)) {
                $resolvedLogo = $this->tenantService->resolveLogoUrl((string) ($validated['logo_url'] ?? ''), $tenant->id);
                $tenant->logo_url = $resolvedLogo;
                $cfg['logo_url'] = $resolvedLogo ?? '';
            }

            $tenant->configuracion = $cfg;
            $tenant->save();

            // Actualizar o crear el usuario Admin del tenant si se enviaron datos
            if (! empty($validated['admin_email']) || ! empty($validated['admin_nombre']) || ! empty($validated['admin_password'])) {
                $adminUser = Usuario::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('rol', RolUsuario::Admin->value)
                    ->orderBy('id')
                    ->first();

                if ($adminUser) {
                    if (! empty($validated['admin_nombre'])) {
                        $parts = preg_split('/\s+/', trim($validated['admin_nombre']), 2);
                        $adminUser->nombre = $parts[0] ?? trim($validated['admin_nombre']);
                        $adminUser->apellido = $parts[1] ?? null;
                    }
                    if (! empty($validated['admin_email'])) {
                        $adminUser->email = strtolower(trim($validated['admin_email']));
                    }
                    if (! empty($validated['admin_password'])) {
                        $adminUser->password = $validated['admin_password'];
                    }
                    $adminUser->save();
                } elseif (! empty($validated['admin_email'])) {
                    $parts = preg_split('/\s+/', trim($validated['admin_nombre'] ?? 'Admin'), 2);
                    Usuario::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id,
                        'nombre' => $parts[0] ?? 'Admin',
                        'apellido' => $parts[1] ?? null,
                        'email' => strtolower(trim($validated['admin_email'])),
                        'password' => ! empty($validated['admin_password']) ? $validated['admin_password'] : '12345678',
                        'rol' => RolUsuario::Admin,
                        'activo' => true,
                    ]);
                }
            }
        });

        $formatted = $this->formatTenantForSuperAdmin($tenant->refresh());

        return response()->json([
            'message' => 'Gimnasio actualizado correctamente.',
            'tenant' => $formatted,
            'data' => $formatted,
        ]);
    }

    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->activo = ! $tenant->activo;
        $tenant->save();

        $formatted = $this->formatTenantForSuperAdmin($tenant);

        return response()->json([
            'message' => $tenant->activo
                ? 'Gimnasio activado correctamente.'
                : 'Gimnasio pausado/suspendido correctamente.',
            'tenant' => $formatted,
            'data' => $formatted,
        ]);
    }

    private function formatTenantForSuperAdmin(Tenant $tenant): array
    {
        $cfg = $tenant->configuracion_completa;

        $sociosCount = Alumno::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        $coachesCount = Usuario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('rol', RolUsuario::Coach->value)
            ->whereNull('deleted_at')
            ->count();

        $adminUser = Usuario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('rol', RolUsuario::Admin->value)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        return [
            'id' => $tenant->id,
            'nombre' => $tenant->nombre,
            'slug' => $tenant->slug,
            'dominio' => $tenant->dominio,
            'logo_url' => ! empty($cfg['logo_url']) ? $cfg['logo_url'] : $tenant->logo_url,
            'color_primario' => $tenant->color_primario ?: ($cfg['color_primario'] ?? '#f97316'),
            'tema_modo' => $cfg['tema_modo'] ?? 'dark',
            'telefono' => $cfg['telefono'] ?? '',
            'direccion' => $cfg['direccion'] ?? '',
            'modulos' => $tenant->modulos_completos,
            'configuracion' => $cfg,
            'activo' => (bool) $tenant->activo,
            'socios_count' => $sociosCount,
            'coaches_count' => $coachesCount,
            'admin' => $adminUser ? [
                'id' => $adminUser->id,
                'nombre' => $adminUser->nombre_completo,
                'email' => $adminUser->email,
            ] : null,
            'created_at' => $tenant->created_at?->toISOString(),
        ];
    }
}
