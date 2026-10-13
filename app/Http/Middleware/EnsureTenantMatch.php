<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMatch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = app(TenantContext::class)->id();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($tenantId === null || (int) $user->tenant_id !== (int) $tenantId) {
            return response()->json([
                'message' => 'Acceso denegado: el usuario no pertenece a este gimnasio.',
            ], 403);
        }

        if (! $user->activo) {
            return response()->json([
                'message' => 'Tu cuenta se encuentra desactivada.',
            ], 403);
        }

        return $next($request);
    }
}
