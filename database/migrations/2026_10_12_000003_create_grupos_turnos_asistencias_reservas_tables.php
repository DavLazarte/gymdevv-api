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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_grupos_tenant')
                ->restrictOnDelete();
            $table->foreignId('coach_id')
                ->nullable()
                ->constrained('usuarios', 'id', 'fk_grupos_coach')
                ->nullOnDelete();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('dias_semana', 150)->nullable()->comment('Ej: Lunes,Miércoles,Viernes');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->unsignedInteger('duracion_minutos')->default(60);
            $table->unsignedInteger('cupo_default')->default(20);
            $table->unsignedInteger('minutos_limite_reserva')->nullable();
            $table->unsignedInteger('minutos_limite_cancelacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'activo'], 'ix_grupos_tenant_activo');
        });

        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_turnos_tenant')
                ->restrictOnDelete();
            $table->foreignId('grupo_id')
                ->constrained('grupos', 'id', 'fk_turnos_grupo')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana')->comment('0=Dom, 1=Lun .. 6=Sab');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedInteger('cupo')->nullable()->comment('NULL = usa grupos.cupo_default');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'dia_semana', 'activo'], 'ix_turnos_tenant_dia');
        });

        Schema::create('inscripciones_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_insc_turno_tenant')
                ->restrictOnDelete();
            $table->foreignId('turno_id')
                ->constrained('turnos', 'id', 'fk_insc_turno_turno')
                ->cascadeOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_insc_turno_alumno')
                ->cascadeOnDelete();
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->string('estado', 20)->default('activa')->comment('activa | pausada | baja');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['turno_id', 'alumno_id'], 'uq_insc_turno_alumno');
            $table->index(['tenant_id', 'alumno_id', 'estado'], 'ix_insc_turno_tenant_alumno');
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_reservas_tenant')
                ->restrictOnDelete();
            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos', 'id', 'fk_reservas_grupo')
                ->nullOnDelete();
            $table->foreignId('turno_id')
                ->nullable()
                ->constrained('turnos', 'id', 'fk_reservas_turno')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_reservas_alumno')
                ->cascadeOnDelete();
            $table->date('fecha');
            $table->string('estado', 20)->default('reservada')->comment('reservada | cancelada | baja_fijo');
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha', 'estado'], 'ix_reservas_tenant_fecha');
            $table->index(['tenant_id', 'alumno_id', 'fecha'], 'ix_reservas_tenant_alumno');
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_asistencias_tenant')
                ->restrictOnDelete();
            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos', 'id', 'fk_asistencias_grupo')
                ->nullOnDelete();
            $table->foreignId('turno_id')
                ->nullable()
                ->constrained('turnos', 'id', 'fk_asistencias_turno')
                ->nullOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_asistencias_alumno')
                ->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->dateTime('fecha_asistencia');
            $table->boolean('presente')->default(true);
            $table->string('origen', 20)->default('manual')->comment('fijo | reserva | manual | libre');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha'], 'ix_asistencias_tenant_fecha');
            $table->index(['tenant_id', 'alumno_id', 'fecha'], 'ix_asistencias_tenant_alumno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('inscripciones_turno');
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('grupos');
    }
};
