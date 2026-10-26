<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membresia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'membresias';

    protected $fillable = [
        'tenant_id',
        'alumno_id',
        'tipo_membresia_id',
        'fecha_inicio',
        'fecha_fin',
        'creditos_otorgados',
        'precio_pactado',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
            'creditos_otorgados' => 'integer',
            'precio_pactado' => 'decimal:2',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function tipoMembresia(): BelongsTo
    {
        return $this->belongsTo(TipoMembresia::class, 'tipo_membresia_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'membresia_id');
    }

    public function movimientosCredito(): HasMany
    {
        return $this->hasMany(MovimientoCredito::class, 'membresia_id');
    }

    public function esPorCreditos(): bool
    {
        $tipo = $this->tipoMembresia;

        return ($tipo?->modalidad === 'creditos')
            || ($this->creditos_otorgados !== null && $this->creditos_otorgados > 0)
            || ($tipo?->creditos !== null && $tipo?->creditos > 0);
    }

    public function getCreditosRestantesAttribute(): ?int
    {
        if (! $this->esPorCreditos()) {
            return null;
        }

        $tipo = $this->tipoMembresia;
        $creditosTotales = (int) (($this->creditos_otorgados !== null && $this->creditos_otorgados > 0)
            ? $this->creditos_otorgados
            : ($tipo?->creditos ?? 0));
        if ($creditosTotales <= 0) {
            return 0;
        }

        $fechaInicio = $this->fecha_inicio instanceof \Carbon\CarbonInterface ? $this->fecha_inicio->format('Y-m-d') : (string) ($this->fecha_inicio ?: '');
        $fechaFin = $this->fecha_fin instanceof \Carbon\CarbonInterface ? $this->fecha_fin->format('Y-m-d') : (string) ($this->fecha_fin ?: '');
        $createdAt = $this->created_at instanceof \Carbon\CarbonInterface ? $this->created_at->copy()->subMinute() : null;

        // 1. Contar reservas activas o asistidas dentro del período de esta membresía
        $reservasUsadas = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('reservas')) {
                $qRes = Reserva::query()
                    ->where('alumno_id', $this->alumno_id)
                    ->whereIn('estado', ['reservada', 'asistio']);

                if ($fechaInicio) {
                    $qRes->whereDate('fecha', '>=', $fechaInicio);
                }
                if ($fechaFin) {
                    $qRes->whereDate('fecha', '<=', $fechaFin);
                }
                if ($createdAt) {
                    $qRes->where('created_at', '>=', $createdAt);
                }

                $reservasUsadas = $qRes->get()->map(function (Reserva $r) {
                    $f = $r->fecha instanceof \Carbon\CarbonInterface ? $r->fecha->format('Y-m-d') : (string) ($r->fecha ?: '');
                    return $f . ':' . ($r->grupo_id ?? '0');
                });
            }
        } catch (\Throwable) {
            $reservasUsadas = collect();
        }

        // 2. Contar asistencias registradas dentro del período de esta membresía
        $asistenciasUsadas = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('asistencias')) {
                $qAsis = Asistencia::query()
                    ->where('alumno_id', $this->alumno_id)
                    ->where('presente', true);

                if ($fechaInicio) {
                    $qAsis->whereDate('fecha', '>=', $fechaInicio);
                }
                if ($fechaFin) {
                    $qAsis->whereDate('fecha', '<=', $fechaFin);
                }
                if ($createdAt) {
                    $qAsis->where('created_at', '>=', $createdAt);
                }

                $asistenciasUsadas = $qAsis->get()->map(function (Asistencia $a) {
                    $f = $a->fecha instanceof \Carbon\CarbonInterface ? $a->fecha->format('Y-m-d') : (string) ($a->fecha ?: '');
                    return $f . ':' . ($a->grupo_id ?? '0');
                });
            }
        } catch (\Throwable) {
            $asistenciasUsadas = collect();
        }

        $totalUsados = $reservasUsadas->merge($asistenciasUsadas);

        // 3. Contar turnos fijos pasados o del día de hoy donde el alumno NO avisó que faltaba (baja_fijo)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('inscripciones_turno')) {
                $inscripciones = InscripcionTurno::query()
                    ->where('alumno_id', $this->alumno_id)
                    ->where('estado', 'activa')
                    ->with('turno')
                    ->get();

                if ($inscripciones->isNotEmpty()) {
                    $bajasFijos = collect();
                    if (\Illuminate\Support\Facades\Schema::hasTable('reservas')) {
                        $bajasFijos = Reserva::query()
                            ->where('alumno_id', $this->alumno_id)
                            ->where('estado', 'baja_fijo')
                            ->get()
                            ->map(fn (Reserva $r) => ($r->fecha?->format('Y-m-d') ?? '') . ':' . ($r->turno_id ?? '0'));
                    }

                    $start = $this->fecha_inicio ? \Carbon\Carbon::parse($this->fecha_inicio)->startOfDay() : now()->subMonth();
                    $limitDate = now()->endOfDay();
                    if ($this->fecha_fin) {
                        $fFin = \Carbon\Carbon::parse($this->fecha_fin)->endOfDay();
                        if ($fFin->lt($limitDate)) {
                            $limitDate = $fFin;
                        }
                    }

                    foreach ($inscripciones as $inscripcion) {
                        $turno = $inscripcion->turno;
                        if (! $turno || $turno->dia_semana === null) {
                            continue;
                        }

                        $fechaAltaTurno = $inscripcion->fecha_alta ? \Carbon\Carbon::parse($inscripcion->fecha_alta)->startOfDay() : $start;
                        $fechaDesde = $start->gt($fechaAltaTurno) ? $start->copy() : $fechaAltaTurno->copy();

                        $current = $fechaDesde->copy();
                        while ($current->dayOfWeek !== (int) $turno->dia_semana && $current->lte($limitDate)) {
                            $current->addDay();
                        }

                        while ($current->lte($limitDate)) {
                            $fechaKey = $current->format('Y-m-d');
                            $bajaKey = $fechaKey . ':' . $turno->id;

                            if (! $bajasFijos->contains($bajaKey)) {
                                $totalUsados->push($fechaKey . ':' . ($turno->grupo_id ?? '0'));
                            }

                            $current->addWeek();
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Si hay algún problema calculando turnos fijos, no bloquear la membresía
        }

        $totalConsumido = $totalUsados->unique()->count();

        return max(0, $creditosTotales - $totalConsumido);
    }

    public function getEstadoCalculadoAttribute(): string
    {
        $computedStatus = $this->estado ?: 'activa';
        try {
            if ($computedStatus === 'activa' && $this->fecha_fin) {
                $fechaFin = $this->fecha_fin instanceof \Carbon\CarbonInterface
                    ? $this->fecha_fin
                    : \Carbon\Carbon::parse($this->fecha_fin);
                $diasRestantes = now()->startOfDay()->diffInDays($fechaFin->startOfDay(), false);
                if ($diasRestantes < 0) {
                    $computedStatus = 'vencida';
                } elseif ($diasRestantes <= 7) {
                    $computedStatus = 'por_vencer';
                }
            }
        } catch (\Throwable) {
            $computedStatus = $this->estado ?: 'activa';
        }

        return $computedStatus;
    }
}

