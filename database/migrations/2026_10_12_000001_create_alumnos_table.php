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
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('direccion', 255)->nullable()->after('telefono');
            $table->string('especialidades', 500)->nullable()->after('direccion');
            $table->text('biografia')->nullable()->after('especialidades');
        });

        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_alumnos_tenant')
                ->restrictOnDelete();
            $table->foreignId('usuario_id')
                ->unique('uq_alumnos_usuario')
                ->constrained('usuarios', 'id', 'fk_alumnos_usuario')
                ->restrictOnDelete();
            $table->string('dni', 40)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_alta');
            $table->string('estado', 20)->default('activo')->comment('activo | inactivo | suspendido');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado'], 'ix_alumnos_tenant_estado');
            $table->index(['tenant_id', 'dni'], 'ix_alumnos_tenant_dni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'especialidades', 'biografia']);
        });
    }
};
