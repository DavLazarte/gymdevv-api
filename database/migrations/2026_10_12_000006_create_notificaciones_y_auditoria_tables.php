<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('notificaciones');

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 50)->default('sistema'); // vencimiento, pago, reserva, entrenamiento, sistema
            $table->string('titulo', 160);
            $table->text('mensaje')->nullable();
            $table->boolean('leida')->default(false);
            $table->dateTime('leida_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'usuario_id', 'leida'], 'idx_notif_usuario_leida');
        });

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('accion', 80);
            $table->string('entidad', 80)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'entidad', 'entidad_id'], 'idx_auditoria_entidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('notificaciones');
    }
};
