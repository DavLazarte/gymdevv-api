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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants', 'id', 'fk_usuarios_tenant')
                ->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->string('email', 190);
            $table->string('password', 255)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('rol', 20)->comment('admin | coach | alumno');
            $table->boolean('activo')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email'], 'uq_usuarios_tenant_email');
            $table->index(['tenant_id', 'rol'], 'ix_usuarios_tenant_rol');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
