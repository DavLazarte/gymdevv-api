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

        // 1. Gráfico de asistencia semanal real (Lunes a Sábado de la semana actual)
        $asistenciaSemanal = [];
        $diasLabels = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
        ];

        $countsPorDia = [];
        if (Schema::hasTable('asistencias')) {
            $lunes = now()->startOfWeek()->toDateString();
            $domingo = now()->endOfWeek()->toDateString();

            $countsPorDia = Asistencia::query()
                ->whereBetween('fecha', [$lunes, $domingo])
                ->selectRaw('DATE(fecha) as dia_fecha, COUNT(*) as total')
                ->groupBy('dia_fecha')
                ->pluck('total', 'dia_fecha')
                ->toArray();
        }

        $maxVisitas = !empty($countsPorDia) ? max(array_values($countsPorDia)) : 0;
        if ($maxVisitas <= 0) {
            $maxVisitas = 1;
        }

        for ($i = 1; $i <= 6; $i++) {
            $fechaDia = now()->startOfWeek()->addDays($i - 1)->toDateString();
            $visitas = (int) ($countsPorDia[$fechaDia] ?? 0);
            $porcentaje = !empty($countsPorDia) && $maxVisitas > 0
                ? (int) round(($visitas / $maxVisitas) * 100)
                : 0;

            $asistenciaSemanal[] = [
                'day' => $diasLabels[$i],
                'visits' => $visitas,
                'percentage' => min(100, $porcentaje),
            ];
        }

        // 2. Clases más populares reales (Top 5 por histórico de reservas)
        $clasesPopulares = [];
        if (Schema::hasTable('reservas') && Schema::hasTable('grupos')) {
            $topGrupos = Reserva::query()
                ->whereNotNull('grupo_id')
                ->where('estado', '!=', 'cancelada')
                ->selectRaw('grupo_id, COUNT(*) as total_reservas')
                ->groupBy('grupo_id')
                ->orderByDesc('total_reservas')
                ->limit(5)
                ->with('grupo:id,nombre')
                ->get();

            $clasesPopulares = $topGrupos->map(function ($row) {
                return [
                    'name' => $row->grupo?->nombre ?? 'Clase #' . $row->grupo_id,
                    'reservations' => (int) $row->total_reservas,
                    'trend' => 'Top',
                ];
            })->values()->toArray();
        }

        // 3. Porcentaje de ocupación hoy (reservas de hoy vs cupos de turnos de hoy)
        $porcentajeOcupacion = 0;
        if (Schema::hasTable('turnos') && Schema::hasTable('reservas')) {
            $turnosHoy = Turno::query()
                ->where('activo', true)
                ->where('dia_semana', now()->dayOfWeek)
                ->get();

            $cupoTotalHoy = $turnosHoy->sum(fn (Turno $t) => $t->cupo_efectivo);

            if ($cupoTotalHoy > 0) {
                $reservasHoy = Reserva::query()
                    ->whereDate('fecha', $hoy)
                    ->where('estado', '!=', 'cancelada')
                    ->count();

                $porcentajeOcupacion = min(100, (int) round(($reservasHoy / $cupoTotalHoy) * 100));
            }
        }

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
                'asistenciaSemanal' => $asistenciaSemanal,
                'clasesPopulares' => $clasesPopulares,
            ],
            'membershipOverview' => [
                'activas' => $activas,
                'por_vencer' => $porVencer,
                'por_vencer_7_dias' => $porVencer,
                'vencidas' => $vencidas,
            ],
            'ocupacion' => [
                'porcentaje' => $porcentajeOcupacion,
            ],
            'instructores' => $instructores,
        ]);
    }
}
