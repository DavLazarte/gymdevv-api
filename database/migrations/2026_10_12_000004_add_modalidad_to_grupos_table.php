<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            if (! Schema::hasColumn('grupos', 'modalidad')) {
                $table->string('modalidad', 20)
                    ->default('libre')
                    ->after('nombre')
                    ->comment('libre (por reserva abierta) | fijo (alumnos asignados a turno fijo)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            if (Schema::hasColumn('grupos', 'modalidad')) {
                $table->dropColumn('modalidad');
            }
        });
    }
};
