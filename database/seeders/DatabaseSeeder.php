<?php

namespace Database\Seeders;

use App\Enums\EstadoAlumno;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Gimnasio Principal (resuelve automáticamente en localhost / 127.0.0.1 / gymdevv-api.test)
        $tenant1 = Tenant::query()->updateOrCreate(
            ['dominio' => 'localhost'],
            [
                'nombre' => 'DevGym & CrossFit Central',
                'logo_url' => null,
                'color_primario' => '#f97316',
                'modulos' => Tenant::DEFAULT_MODULOS,
                'configuracion' => array_merge(Tenant::DEFAULT_CONFIGURACION, [
                    'alias_pago' => 'devgym.central.mp',
                    'telefono' => '+54 11 4444-5555',
                    'direccion' => 'Av. Principal 1234',
                ]),
                'activo' => true,
            ]
        );

        app(TenantContext::class)->setTenant($tenant1);

        Usuario::query()->updateOrCreate(
            ['tenant_id' => $tenant1->id, 'email' => 'admin@devgym.com'],
            [
                'nombre' => 'Roberto',
                'apellido' => 'Díaz',
                'password' => 'password123',
                'telefono' => '+54 11 1111-1111',
                'rol' => RolUsuario::Admin,
                'activo' => true,
            ]
        );

        Usuario::query()->updateOrCreate(
            ['tenant_id' => $tenant1->id, 'email' => 'coach@devgym.com'],
            [
                'nombre' => 'Martín',
                'apellido' => 'Coach',
                'password' => 'password123',
                'telefono' => '+54 11 2222-2222',
                'direccion' => 'Av. Corrientes 850',
                'especialidades' => 'CrossFit, Weightlifting, Funcional',
                'biografia' => 'Head Coach Nivel 2',
                'rol' => RolUsuario::Coach,
                'activo' => true,
            ]
        );

        $usuarioAlumno1 = Usuario::query()->updateOrCreate(
            ['tenant_id' => $tenant1->id, 'email' => 'alumno@devgym.com'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'password' => 'password123',
                'telefono' => '+54 11 3333-3333',
                'direccion' => 'Belgrano 450',
                'rol' => RolUsuario::Alumno,
                'activo' => true,
            ]
        );

        if (Schema::hasTable('alumnos')) {
            Alumno::query()->updateOrCreate(
                ['tenant_id' => $tenant1->id, 'usuario_id' => $usuarioAlumno1->id],
                [
                    'dni' => '35123456',
                    'direccion' => 'Belgrano 450',
                    'fecha_nacimiento' => '1994-05-15',
                    'fecha_alta' => now()->subMonths(2)->toDateString(),
                    'estado' => EstadoAlumno::Activo,
                    'notas' => 'Alumno inicial de prueba',
                ]
            );
        }

        // 2. Segundo Gimnasio (para probar aislamiento multi-tenant)
        $tenant2 = Tenant::query()->updateOrCreate(
            ['dominio' => 'boxnorte.local'],
            [
                'nombre' => 'Box Norte Fitness',
                'logo_url' => null,
                'color_primario' => '#10b981',
                'modulos' => array_merge(Tenant::DEFAULT_MODULOS, [
                    'creditos' => true,
                    'reservas' => true,
                ]),
                'configuracion' => array_merge(Tenant::DEFAULT_CONFIGURACION, [
                    'agenda' => 'mixto',
                    'alias_pago' => 'box.norte.mp',
                ]),
                'activo' => true,
            ]
        );

        app(TenantContext::class)->setTenant($tenant2);

        Usuario::query()->updateOrCreate(
            ['tenant_id' => $tenant2->id, 'email' => 'admin@boxnorte.com'],
            [
                'nombre' => 'Laura',
                'apellido' => 'Gómez',
                'password' => 'password123',
                'telefono' => '+54 11 9999-8888',
                'rol' => RolUsuario::Admin,
                'activo' => true,
            ]
        );
    }
}
