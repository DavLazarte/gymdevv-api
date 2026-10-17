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
        if (Schema::hasTable('leads')) {
            return;
        }

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->foreignId('plan_interes_id')->nullable()->constrained('tipos_membresia')->nullOnDelete();
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->string('email', 190);
            $table->string('telefono', 40);
            $table->text('mensaje')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | contactado | convertido | descartado | espera
            $table->text('notas_admin')->nullable();
            $table->date('fecha_registro')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'grupo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
