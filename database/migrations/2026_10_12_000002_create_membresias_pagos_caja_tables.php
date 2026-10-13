<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipos_membresia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_tipos_membresia_tenant')
                ->restrictOnDelete();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('modalidad', 20)->default('mensual')->comment('mensual | creditos');
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('duracion_dias')->nullable();
            $table->unsignedInteger('creditos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'activo'], 'ix_tipos_membresia_tenant_activo');
        });

        Schema::create('membresias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_membresias_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_membresias_alumno')
                ->restrictOnDelete();
            $table->foreignId('tipo_membresia_id')
                ->constrained('tipos_membresia', 'id', 'fk_membresias_tipo')
                ->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->unsignedInteger('creditos_otorgados')->nullable();
            $table->decimal('precio_pactado', 10, 2);
            $table->string('estado', 20)->default('activa')->comment('activa | vencida | cancelada | sin_creditos');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'alumno_id', 'estado'], 'ix_membresias_tenant_alumno_estado');
            $table->index(['tenant_id', 'fecha_fin'], 'ix_membresias_tenant_vencimiento');
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_pagos_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_pagos_alumno')
                ->restrictOnDelete();
            $table->foreignId('membresia_id')
                ->nullable()
                ->constrained('membresias', 'id', 'fk_pagos_membresia')
                ->nullOnDelete();
            $table->decimal('monto', 10, 2);
            $table->date('fecha_pago');
            $table->string('metodo', 40)->default('efectivo')->comment('efectivo | transferencia | mercadopago | tarjeta');
            $table->string('estado', 20)->default('pagado')->comment('pagado | pendiente | anulado');
            $table->string('comprobante', 500)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'alumno_id'], 'ix_pagos_tenant_alumno');
            $table->index(['tenant_id', 'fecha_pago'], 'ix_pagos_tenant_fecha');
            $table->index(['tenant_id', 'estado'], 'ix_pagos_tenant_estado');
        });

        Schema::create('movimientos_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_mov_cred_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_mov_cred_alumno')
                ->restrictOnDelete();
            $table->foreignId('membresia_id')
                ->nullable()
                ->constrained('membresias', 'id', 'fk_mov_cred_membresia')
                ->restrictOnDelete();
            $table->integer('cantidad')->comment('+N acredita, -N consume');
            $table->string('motivo', 40)->comment('alta_membresia | reserva | asistencia | devolucion_cancelacion | ajuste_manual | vencimiento');
            $table->unsignedBigInteger('reserva_id')->nullable()->index('ix_mov_cred_reserva');
            $table->unsignedBigInteger('asistencia_id')->nullable()->index('ix_mov_cred_asistencia');
            $table->string('notas', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'alumno_id', 'created_at'], 'ix_mov_cred_tenant_alumno');
            $table->index(['membresia_id'], 'ix_mov_cred_membresia');
        });

        Schema::create('salidas_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_salidas_caja_tenant')
                ->restrictOnDelete();
            $table->foreignId('usuario_id')
                ->nullable()
                ->comment('Instructor o empleado beneficiario')
                ->constrained('usuarios', 'id', 'fk_salidas_caja_usuario')
                ->nullOnDelete();
            $table->foreignId('registrado_por_id')
                ->nullable()
                ->constrained('usuarios', 'id', 'fk_salidas_caja_registrador')
                ->nullOnDelete();
            $table->string('tipo_salida', 40)->default('servicio')->comment('servicio | pago_instructor | mantenimiento | insumos | otros');
            $table->string('descripcion', 255);
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 40)->default('efectivo');
            $table->date('fecha');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha'], 'ix_salidas_caja_tenant_fecha');
            $table->index(['tenant_id', 'tipo_salida'], 'ix_salidas_caja_tenant_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas_caja');
        Schema::dropIfExists('movimientos_credito');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('membresias');
        Schema::dropIfExists('tipos_membresia');
    }
};
