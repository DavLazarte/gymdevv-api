<?php

use App\Http\Controllers\Api\AlumnoController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaseGrupoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EntrenamientoController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MembresiaController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\SalidaCajaController;
use App\Http\Controllers\Api\SuperAdminTenantController;
use App\Http\Controllers\Api\TenantConfigController;
use App\Http\Controllers\Api\TipoMembresiaController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

// Rutas SuperAdmin para gestión multi-tenant de Boxes / Gimnasios
Route::prefix('superadmin')->group(function () {
    Route::get('/tenants', [SuperAdminTenantController::class, 'index']);
    Route::post('/tenants', [SuperAdminTenantController::class, 'store']);
    Route::put('/tenants/{id}', [SuperAdminTenantController::class, 'update']);
    Route::patch('/tenants/{id}/toggle', [SuperAdminTenantController::class, 'toggleActive']);
});

// Rutas Públicas para Landing Page de cada Box y Registro de Prospectos / Preventa
Route::prefix('public')->group(function () {
    Route::get('/box/{slug}', [\App\Http\Controllers\Api\PublicTenantController::class, 'show']);
    Route::post('/box/{slug}/registro', [\App\Http\Controllers\Api\PublicTenantController::class, 'registerLead']);
});

Route::middleware(['tenant'])->group(function () {
    // Configuración pública del gimnasio (tema, logo, módulos activos)
    Route::get('/tenant/config', [TenantConfigController::class, 'show']);

    // Autenticación pública (con alias para compatibilidad directa con devgym)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/login', [AuthController::class, 'login']);

    // Rutas protegidas por Sanctum + Verificación de pertenencia al Tenant
    Route::middleware(['auth:sanctum', 'tenant.match'])->group(function () {
        // Sesión y Perfil
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/user', [AuthController::class, 'me']);
        Route::get('/perfil', [AlumnoController::class, 'perfil']);
        Route::post('/perfil', [AlumnoController::class, 'updatePerfilSocio']);
        Route::put('/perfil', [AuthController::class, 'updatePerfil']);

        // Configuración del Gimnasio (solo Admin)
        Route::put('/tenant/config', [TenantConfigController::class, 'update']);

        // Dashboard Stats
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Módulo 2: Alumnos / Socios e Instructores
        Route::post('/socios/{id}/create-user', [AlumnoController::class, 'createUser']);
        Route::post('/alumnos/{id}/acceso', [AlumnoController::class, 'createUser']);
        Route::apiResource('socios', AlumnoController::class)->parameters(['socios' => 'id']);
        Route::apiResource('alumnos', AlumnoController::class)->parameters(['alumnos' => 'id']);
        Route::apiResource('usuarios', UsuarioController::class);

        // Pre-venta, Leads y Lista de Espera (Turnos Fijos)
        Route::post('/leads/{id}/convertir', [LeadController::class, 'convertir']);
        Route::apiResource('leads', LeadController::class)->parameters(['leads' => 'id']);

        // Módulo 3: Tipos de Membresía (Planes comerciales), Membresías, Pagos y Salidas de Caja
        Route::apiResource('servicios', TipoMembresiaController::class)->parameters(['servicios' => 'id']);
        Route::apiResource('tipos-membresia', TipoMembresiaController::class)->parameters(['tipos-membresia' => 'id']);
        Route::apiResource('membresias', MembresiaController::class)->parameters(['membresias' => 'id']);
        Route::apiResource('pagos-gym', PagoController::class)->parameters(['pagos-gym' => 'id']);
        Route::apiResource('pagos', PagoController::class)->parameters(['pagos' => 'id']);
        Route::apiResource('salidas-gym', SalidaCajaController::class)->parameters(['salidas-gym' => 'id']);
        Route::apiResource('caja/salidas', SalidaCajaController::class)->parameters(['salidas' => 'id']);

        // Módulo 4: Clases / Grupos, Turnos, Inscripciones Fijas, Reservas y Asistencias
        Route::get('/clases/instructor-stats', [ClaseGrupoController::class, 'instructorStats']);
        Route::get('/clases-disponibles', [ClaseGrupoController::class, 'available']);
        Route::get('/turnos/disponibles', [ClaseGrupoController::class, 'available']);
        Route::post('/clases/{id}/inscripciones', [ClaseGrupoController::class, 'inscribirFijo']);
        Route::delete('/clases/{id}/inscripciones/{alumnoId}', [ClaseGrupoController::class, 'desinscribirFijo']);
        Route::post('/grupos/{id}/inscripciones', [ClaseGrupoController::class, 'inscribirFijo']);
        Route::delete('/grupos/{id}/inscripciones/{alumnoId}', [ClaseGrupoController::class, 'desinscribirFijo']);
        Route::apiResource('clases', ClaseGrupoController::class)->parameters(['clases' => 'id']);
        Route::apiResource('grupos', ClaseGrupoController::class)->parameters(['grupos' => 'id']);

        Route::apiResource('reservas', ReservaController::class)->parameters(['reservas' => 'id']);
        Route::apiResource('asistencias', AsistenciaController::class)->parameters(['asistencias' => 'id']);

        // Módulo 5: Entrenamiento, Planificación (Kanban), Ejercicios, RMs, Progreso y Comentarios
        Route::prefix('entrenamiento')->group(function () {
            Route::get('/tablero', [EntrenamientoController::class, 'tablero']);
            Route::get('/mi-plan', [EntrenamientoController::class, 'miPlan']);

            // Programas / Planes de entrenamiento
            Route::get('/planes', [EntrenamientoController::class, 'indexPlanes']);
            Route::post('/planes', [EntrenamientoController::class, 'storePlan']);
            Route::put('/planes/{id}', [EntrenamientoController::class, 'updatePlan']);
            Route::delete('/planes/{id}', [EntrenamientoController::class, 'destroyPlan']);
            Route::post('/planes/{id}/alumnos', [EntrenamientoController::class, 'asignarAlumnoPlan']);
            Route::delete('/planes/{id}/alumnos/{alumnoId}', [EntrenamientoController::class, 'desasignarAlumnoPlan']);

            // Semanas y Días (Tablero Kanban)
            Route::post('/semanas', [EntrenamientoController::class, 'storeSemana']);
            Route::put('/semanas/{id}', [EntrenamientoController::class, 'updateSemana']);
            Route::post('/semanas/{id}/duplicar', [EntrenamientoController::class, 'duplicarSemana']);
            Route::delete('/semanas/{id}', [EntrenamientoController::class, 'destroySemana']);
            Route::put('/dias/{id}', [EntrenamientoController::class, 'updateDia']);
            Route::post('/dias/{id}/links', [EntrenamientoController::class, 'storeLink']);
            Route::delete('/links/{id}', [EntrenamientoController::class, 'destroyLink']);

            // Progreso del día y Comentarios
            Route::post('/dias/{id}/progreso', [EntrenamientoController::class, 'toggleProgresoDia']);
            Route::post('/dias/{id}/comentarios', [EntrenamientoController::class, 'storeComentario']);

            // Catálogo de Ejercicios y Marcas Personales (RMs)
            Route::get('/ejercicios', [EntrenamientoController::class, 'indexEjercicios']);
            Route::post('/ejercicios', [EntrenamientoController::class, 'storeEjercicio']);
            Route::delete('/ejercicios/{id}', [EntrenamientoController::class, 'destroyEjercicio']);
            Route::get('/rms', [EntrenamientoController::class, 'indexRms']);
            Route::post('/rms', [EntrenamientoController::class, 'storeRm']);
            Route::put('/rms/{id}', [EntrenamientoController::class, 'updateRm']);
            Route::delete('/rms/{id}', [EntrenamientoController::class, 'destroyRm']);
        });
    });
});
