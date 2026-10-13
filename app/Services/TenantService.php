<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function getCurrentTenant(): Tenant
    {
        return $this->tenantContext->getTenant();
    }

    public function updateConfig(array $data): Tenant
    {
        $tenant = $this->getCurrentTenant();

        if (isset($data['nombre'])) {
            $tenant->nombre = $data['nombre'];
        }

        $rawLogo = $data['logo_url'] ?? $data['configuracion']['logo_url'] ?? null;
        if ($rawLogo !== null) {
            $resolvedLogo = $this->resolveLogoUrl($rawLogo, $tenant->id);
            $tenant->logo_url = $resolvedLogo;
            if (isset($data['configuracion']) && is_array($data['configuracion'])) {
                $data['configuracion']['logo_url'] = $resolvedLogo;
            }
        }

        $colorPrimario = $data['color_primario'] ?? $data['configuracion']['color_primario'] ?? null;
        if (! empty($colorPrimario)) {
            $tenant->color_primario = $colorPrimario;
        }

        if (isset($data['modulos']) && is_array($data['modulos'])) {
            $tenant->modulos = array_merge($tenant->modulos_completos, $data['modulos']);
        }

        if (isset($data['configuracion']) && is_array($data['configuracion'])) {
            $tenant->configuracion = array_merge($tenant->configuracion_completa, $data['configuracion']);
        }

        $tenant->save();

        return $tenant->refresh();
    }

    public function resolveLogoUrl(string $rawLogo, int|string $tenantId = 0): ?string
    {
        $trimmed = trim($rawLogo);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $trimmed, $matches)) {
            $ext = strtolower($matches[1] ?? 'png');
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $binary = base64_decode($matches[2], true);
            if ($binary !== false) {
                $dir = public_path('uploads/logos');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = 'logo_' . $tenantId . '_' . uniqid() . '.' . $ext;
                @file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $binary);

                return url('uploads/logos/' . $filename);
            }
        }

        return $trimmed;
    }
}
