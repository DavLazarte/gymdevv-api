<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    public const DEFAULT_MODULOS = [
        'membresias' => true,
        'pagos' => true,
        'caja' => true,
        'grupos_turnos' => true,
        'asistencias' => true,
        'entrenamiento' => true,
        'rms' => true,
        'comentarios' => true,
        'creditos' => false,
        'reservas' => false,
    ];

    public const DEFAULT_CONFIGURACION = [
        'agenda' => 'fijo', // fijo | reserva | mixto
        'consumo_credito' => 'asistir', // reservar | asistir
        'cancelacion_horas' => 2,
        'bloquear_plan_si_vencido' => true,
        'tema_modo' => 'dark',
        'alias_pago' => '',
        'telefono' => '',
        'email' => '',
        'direccion' => '',
        'descripcion' => '',
        'horarios_texto' => 'Lunes a Viernes 07:00 a 22:00',
        'horarios' => [
            'lunes' => ['apertura' => '06:00', 'cierre' => '23:00', 'cerrado' => false],
            'martes' => ['apertura' => '06:00', 'cierre' => '23:00', 'cerrado' => false],
            'miercoles' => ['apertura' => '06:00', 'cierre' => '23:00', 'cerrado' => false],
            'jueves' => ['apertura' => '06:00', 'cierre' => '23:00', 'cerrado' => false],
            'viernes' => ['apertura' => '06:00', 'cierre' => '23:00', 'cerrado' => false],
            'sabado' => ['apertura' => '08:00', 'cierre' => '20:00', 'cerrado' => false],
            'domingo' => ['apertura' => '09:00', 'cierre' => '15:00', 'cerrado' => false],
        ],
        'notificaciones' => [
            'recordatorioClases' => true,
            'alertaVencimiento' => true,
            'confirmacionPago' => true,
            'newsletterSemanal' => false,
        ],
    ];

    protected $fillable = [
        'nombre',
        'dominio',
        'logo_url',
        'color_primario',
        'modulos',
        'configuracion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'modulos' => 'array',
            'configuracion' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function getModulosCompletosAttribute(): array
    {
        return array_merge(self::DEFAULT_MODULOS, $this->modulos ?? []);
    }

    public function getConfiguracionCompletaAttribute(): array
    {
        return array_merge(self::DEFAULT_CONFIGURACION, $this->configuracion ?? []);
    }

    public function hasModulo(string $modulo): bool
    {
        return (bool) ($this->modulos_completos[$modulo] ?? false);
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->configuracion_completa[$key] ?? $default;
    }

    public function getSlugAttribute(): string
    {
        if (isset($this->attributes['slug']) && ! empty($this->attributes['slug'])) {
            return (string) $this->attributes['slug'];
        }

        $cfg = $this->configuracion ?? [];
        if (! empty($cfg['slug'])) {
            return (string) $cfg['slug'];
        }

        if (! empty($this->dominio) && ! in_array($this->dominio, ['localhost', '127.0.0.1'])) {
            $parts = explode('.', $this->dominio);
            if (! empty($parts[0])) {
                return \Illuminate\Support\Str::slug($parts[0]);
            }
        }

        return \Illuminate\Support\Str::slug($this->nombre ?? 'box');
    }

    public static function findBySlug(string $slug): ?self
    {
        $normalized = strtolower(trim($slug));

        // 1. Si la columna física 'slug' existe en MySQL
        if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'slug')) {
            $byCol = static::query()->where('slug', $normalized)->first();
            if ($byCol) {
                return $byCol;
            }
        }

        // 2. Por dominio exacto
        $byDominio = static::query()->where('dominio', $normalized)->first();
        if ($byDominio) {
            return $byDominio;
        }

        // 3. Por ID numérico
        if (is_numeric($normalized)) {
            $byId = static::query()->find($normalized);
            if ($byId) {
                return $byId;
            }
        }

        // 4. Buscar entre los tenants comparando el slug derivado o en configuracion
        return static::query()->get()->first(function (self $t) use ($normalized) {
            return $t->slug === $normalized
                || \Illuminate\Support\Str::slug($t->nombre) === $normalized;
        });
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'tenant_id');
    }
}
