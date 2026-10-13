<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoAlumno;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\SalidaCaja;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $sociosActivos = Schema::hasTable('alumnos')
            ? Alumno::query()->where('estado', EstadoAlumno::Activo->value)->count()
            : Usuario::query()->where('rol', RolUsuario::Alumno->value)->where('activo', true)->count();

        $hoy = now()->toDateString();

        $ingresosHoy = Schema::hasTable('pagos')
            ? (float) Pago::query()->where('estado', 'pagado')->whereDate('fecha_pago', $hoy)->sum('monto')
            : 0.0;

        $salidasHoy = Schema::hasTable('salidas_caja')
            ? (float) SalidaCaja::query()->whereDate('fecha', $hoy)->sum('monto')
            : 0.0;

        $balanceHoy = round($ingresosHoy - $salidasHoy, 2);

        $activas = 0;
        $porVencer = 0;
        $vencidas = 0;

        if (Schema::hasTable('membresias')) {
            $activas = Membresia::query()
                ->where('estado', 'activa')
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $hoy);
                })
                ->count();

            $porVencer = Membresia::query()
                ->where('estado', 'activa')
                ->whereNotNull('fecha_fin')
                ->whereBetween('fecha_fin', [$hoy, now()->addDays(7)->toDateString()])
                ->count();

            $vencidas = Membresia::query()
                ->where(function ($q) use ($hoy) {
                    $q->where('estado', 'vencida')
                        ->orWhere(function ($sub) use ($hoy) {
                            $sub->where('estado', 'activa')
                                ->whereNotNull('fecha_fin')
                                ->whereDate('fecha_fin', '<', $hoy);
                        });
                })
                ->count();
        }

        $clasesHoy = Schema::hasTable('turnos')
            ? Turno::query()->where('activo', true)->where('dia_semana', now()->dayOfWeek)->count()
            : 0;

        $reservasSemana = Schema::hasTable('reservas')
            ? Reserva::query()
                ->where('estado', 'reservada')
                ->whereBetween('fecha', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
                ->count()
            : 0;

        $hasGrupos = Schema::hasTable('grupos');
        $instructores = Usuario::query()
            ->where('rol', RolUsuario::Coach->value)
            ->where('activo', true)
            ->get()
            ->map(fn (Usuario $coach) => [
                'name' => $coach->nombre_completo,
                'classes' => $hasGrupos
                    ? Grupo::query()->where('coach_id', $coach->id)->where('activo', true)->count()
                    : 0,
            ])
            ->values();

        return response()->json([
            'metrics' => [
                'sociosActivos' => $sociosActivos,
                'clasesHoy' => $clasesHoy,
                'reservasSemana' => $reservasSemana,
                'ingresosHoy' => round($ingresosHoy, 2),
                'salidasHoy' => round($salidasHoy, 2),
                'balanceHoy' => $balanceHoy,
            ],
            'charts' => [
                'asistenciaSemanal' => [
                    ['day' => 'Lun', 'visits' => 0, 'percentage' => 0],
                    ['day' => 'Mar', 'visits' => 0, 'percentage' => 0],
                    ['day' => 'Mié', 'visits' => 0, 'percentage' => 0],
                    ['day' => 'Jue', 'visits' => 0, 'percentage' => 0],
                    ['day' => 'Vie', 'visits' => 0, 'percentage' => 0],
                    ['day' => 'Sáb', 'visits' => 0, 'percentage' => 0],
                ],
                'clasesPopulares' => [],
            ],
            'membershipOverview' => [
                'activas' => $activas,
                'por_vencer' => $porVencer,
                'por_vencer_7_dias' => $porVencer,
                'vencidas' => $vencidas,
            ],
            'ocupacion' => [
                'porcentaje' => 0,
            ],
            'instructores' => $instructores,
        ]);
    }
}
