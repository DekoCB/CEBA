<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del estado actual del estudiante (no un historial de cambios):
 * cuándo y por qué se le retiró, o cuándo empezó/termina su pausa
 * temporal y por qué -- antes "estado" era un enum sin nada que lo
 * respalde (ver EstadoEstudianteEnum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->date('fecha_retiro')->nullable()->after('estado');
            $table->string('motivo_retiro', 255)->nullable()->after('fecha_retiro');
            $table->date('pausa_inicio')->nullable()->after('motivo_retiro');
            $table->date('pausa_fin')->nullable()->after('pausa_inicio');
            $table->string('motivo_pausa', 255)->nullable()->after('pausa_fin');
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn(['fecha_retiro', 'motivo_retiro', 'pausa_inicio', 'pausa_fin', 'motivo_pausa']);
        });
    }
};
