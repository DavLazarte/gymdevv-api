<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_ejercicios_tenant')
                ->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('categoria', 60)->default('Fuerza')->comment('Fuerza | Weightlifting | Capacidades | Running');
            $table->string('unidad', 20)->default('kg')->comment('kg | reps | segundos');
            $table->text('descripcion')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'categoria'], 'ix_ejercicios_tenant_cat');
        });

        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_planes_tenant')
                ->restrictOnDelete();
            $table->foreignId('coach_id')
                ->nullable()
                ->constrained('usuarios', 'id', 'fk_planes_coach')
                ->nullOnDelete();
            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos', 'id', 'fk_planes_grupo')
                ->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('tipo', 50)->default('personalizado')->comment('clase | personalizado | general');
            $table->text('descripcion')->nullable();
            $table->string('color', 20)->default('#f97316');
            $table->string('icono', 40)->default('dumbbell');
            $table->string('estado', 20)->default('activo')->comment('activo | inactivo');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado'], 'ix_planes_tenant_estado');
        });

        Schema::create('semanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_semanas_tenant')
                ->restrictOnDelete();
            $table->foreignId('plan_id')
                ->constrained('planes', 'id', 'fk_semanas_plan')
                ->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->unsignedSmallInteger('numero')->default(1);
            $table->string('estado', 20)->default('planificacion')->comment('planificacion | en-curso | finalizado | borrador | publicada | archivada');
            $table->date('fecha_inicio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'plan_id', 'estado'], 'ix_semanas_tenant_plan_estado');
        });

        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_dias_tenant')
                ->restrictOnDelete();
            $table->foreignId('semana_id')
                ->constrained('semanas', 'id', 'fk_dias_semana')
                ->cascadeOnDelete();
            $table->string('nombre', 30);
            $table->boolean('descanso')->default(false);
            $table->mediumText('contenido')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['semana_id', 'orden'], 'ix_dias_semana_orden');
            $table->index(['tenant_id'], 'ix_dias_tenant');
        });

        Schema::create('dia_ejercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_dia_ej_tenant')
                ->restrictOnDelete();
            $table->foreignId('dia_id')
                ->constrained('dias', 'id', 'fk_dia_ej_dia')
                ->cascadeOnDelete();
            $table->foreignId('ejercicio_id')
                ->constrained('ejercicios', 'id', 'fk_dia_ej_ejercicio')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedSmallInteger('series')->nullable();
            $table->string('repeticiones', 50)->nullable();
            $table->decimal('peso', 6, 2)->nullable();
            $table->unsignedSmallInteger('descanso')->nullable()->comment('Segundos de descanso');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dia_id', 'orden'], 'ix_dia_ej_dia_orden');
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_links_tenant')
                ->restrictOnDelete();
            $table->foreignId('dia_id')
                ->constrained('dias', 'id', 'fk_links_dia')
                ->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->string('url', 500);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dia_id'], 'ix_links_dia');
        });

        Schema::create('alumno_planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_alumno_planes_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_alumno_planes_alumno')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('planes', 'id', 'fk_alumno_planes_plan')
                ->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('activo')->comment('activo | inactivo | finalizado');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'estado'], 'ix_alumno_planes_alumno_estado');
            $table->index(['plan_id'], 'ix_alumno_planes_plan');
        });

        Schema::create('registros_entrenamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_reg_ent_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_reg_ent_alumno')
                ->cascadeOnDelete();
            $table->foreignId('ejercicio_id')
                ->constrained('ejercicios', 'id', 'fk_reg_ent_ejercicio')
                ->restrictOnDelete();
            $table->foreignId('dia_ejercicio_id')
                ->nullable()
                ->constrained('dia_ejercicios', 'id', 'fk_reg_ent_dia_ej')
                ->nullOnDelete();
            $table->date('fecha');
            $table->decimal('peso', 6, 2)->nullable();
            $table->unsignedSmallInteger('repeticiones')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'ejercicio_id', 'fecha'], 'ix_reg_ent_alumno_ej_fecha');
        });

        Schema::create('rms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_rms_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_rms_alumno')
                ->cascadeOnDelete();
            $table->foreignId('ejercicio_id')
                ->constrained('ejercicios', 'id', 'fk_rms_ejercicio')
                ->restrictOnDelete();
            $table->decimal('kg', 8, 2)->comment('Valor en kg, reps o segundos según ejercicio.unidad');
            $table->date('fecha');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'ejercicio_id', 'fecha'], 'ix_rms_alumno_ej_fecha');
        });

        Schema::create('progreso_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_prog_dias_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_prog_dias_alumno')
                ->cascadeOnDelete();
            $table->foreignId('dia_id')
                ->constrained('dias', 'id', 'fk_prog_dias_dia')
                ->cascadeOnDelete();
            $table->dateTime('completado_en');
            $table->timestamps();

            $table->unique(['alumno_id', 'dia_id'], 'uq_prog_dias_alumno_dia');
        });

        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_comentarios_tenant')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos', 'id', 'fk_comentarios_alumno')
                ->cascadeOnDelete();
            $table->foreignId('dia_id')
                ->constrained('dias', 'id', 'fk_comentarios_dia')
                ->cascadeOnDelete();
            $table->foreignId('autor_usuario_id')
                ->nullable()
                ->constrained('usuarios', 'id', 'fk_comentarios_autor')
                ->nullOnDelete();
            $table->text('texto');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'dia_id', 'created_at'], 'ix_comentarios_hilo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios');
        Schema::dropIfExists('progreso_dias');
        Schema::dropIfExists('rms');
        Schema::dropIfExists('registros_entrenamiento');
        Schema::dropIfExists('alumno_planes');
        Schema::dropIfExists('links');
        Schema::dropIfExists('dia_ejercicios');
        Schema::dropIfExists('dias');
        Schema::dropIfExists('semanas');
        Schema::dropIfExists('planes');
        Schema::dropIfExists('ejercicios');
    }
};
