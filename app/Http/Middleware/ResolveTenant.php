<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->findTenant($request);

        if (! $tenant || ! $tenant->activo) {
            return response()->json([
                'message' => 'Gimnasio no encontrado o inactivo para el dominio indicado.',
            ], 404);
        }

        app(TenantContext::class)->setTenant($tenant);

        return $next($request);
    }

    private function findTenant(Request $request): ?Tenant
    {
        // 1. Por ID explícito (Header X-Tenant-Id o query ?tenant_id)
        $tenantId = $request->header('X-Tenant-Id') ?? $request->query('tenant_id');
        if ($tenantId) {
            $byId = Tenant::query()->find($tenantId);
            if ($byId) {
                return $byId;
            }
        }

        // 2. Por Slug explícito (Header X-Tenant-Slug o query ?box= / ?tenant_slug=)
        $tenantSlug = $request->header('X-Tenant-Slug')
            ?? $request->query('box')
            ?? $request->query('tenant_slug');
        if ($tenantSlug) {
            $bySlug = Tenant::findBySlug((string) $tenantSlug);
            if ($bySlug) {
                return $bySlug;
            }
        }

        // 3. Por Header X-Tenant-Host o Host de la petición (sin puerto)
        $rawHost = $request->header('X-Tenant-Host') ?: $request->getHost();
        $host = strtolower(explode(':', trim((string) $rawHost))[0]);

        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1', 'fitdev.devsign.ar', 'fitdev.devsing.ar', 'gymdev-api.devsign.ar', 'gymdev-api.devsing.ar'], true)) {
            // 3a. Coincidencia exacta por columna dominio (ej: kratos.devsign.ar o mibox.com)
            $tenant = Tenant::query()->where('dominio', $host)->first();
            if ($tenant) {
                return $tenant;
            }

            // 3b. Coincidencia automática por primer segmento de subdominio == slug (ej: kratos.fitdev.devsign.ar -> slug "kratos")
            $firstSegment = explode('.', $host)[0] ?? '';
            if ($firstSegment !== '' && ! in_array($firstSegment, ['www', 'api', 'fitdev', 'gymdev-api'], true)) {
                $bySubdomainSlug = Tenant::findBySlug($firstSegment);
                if ($bySubdomainSlug) {
                    return $bySubdomainSlug;
                }
            }
        }

        // 4. Si hay un usuario autenticado por Bearer Token (Sanctum), usar su propio tenant_id
        $authUser = $request->user('sanctum');
        if ($authUser && $authUser->tenant_id) {
            $byUserTenant = Tenant::query()->find($authUser->tenant_id);
            if ($byUserTenant) {
                return $byUserTenant;
            }
        }

        // 5. Fallback cuando se entra al dominio general (fitdev.devsign.ar o localhost) sin sesión aún
        return Tenant::query()->where('activo', true)->orderBy('id')->first();
    }
}
