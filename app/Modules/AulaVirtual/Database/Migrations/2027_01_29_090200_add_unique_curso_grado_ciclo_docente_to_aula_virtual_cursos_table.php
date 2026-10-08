<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Único compuesto sobre (curso_id, grado_id, ciclo_id, docente_id) --
 * defensa adicional a nivel de base de datos para que ya no puedan
 * existir dos aulas virtuales duplicadas para el mismo curso+grado+ciclo+
 * docente (la migración anterior ya fusionó las que existían).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->unique(['curso_id', 'grado_id', 'ciclo_id', 'docente_id']);
        });
    }

    public function down(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->dropUnique(['curso_id', 'grado_id', 'ciclo_id', 'docente_id']);
        });
    }
};
