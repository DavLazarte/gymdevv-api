<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantConfigRequest;
use App\Http\Resources\TenantConfigResource;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantConfigController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    public function show(): JsonResponse
    {
        $tenant = $this->tenantService->getCurrentTenant();
        $resource = new TenantConfigResource($tenant);

        return response()->json([
            'tenant' => $resource,
            'data' => $resource,
        ]);
    }

    public function update(UpdateTenantConfigRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->updateConfig($request->validated());
        $resource = new TenantConfigResource($tenant);

        return response()->json([
            'message' => 'Configuración del gimnasio actualizada correctamente.',
            'tenant' => $resource,
            'data' => $resource,
        ]);
    }
}
