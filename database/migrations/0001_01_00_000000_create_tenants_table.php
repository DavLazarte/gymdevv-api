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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('dominio', 190)->unique('uq_tenants_dominio');
            $table->string('logo_url', 500)->nullable();
            $table->string('color_primario', 20)->default('#f97316');
            $table->json('modulos')->nullable();
            $table->json('configuracion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
