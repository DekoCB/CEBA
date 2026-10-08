<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega curso_id/grado_id/ciclo_id/docente_id a aula_virtual_cursos --
 * hasta ahora un CursoVirtual solo se identificaba por su horario_id (1 a
 * 1), así que cada vez que un admin creaba un Horario para el mismo
 * curso+grado+docente (ej. una franja distinta del mismo curso) se creaba
 * un aula virtual nueva y separada en vez de reutilizar la existente. Estas
 * 4 columnas son la clave real de deduplicación (ver
 * CursoVirtualService::activarParaHorario()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->after('horario_id')->constrained('cursos')->nullOnDelete();
            $table->foreignId('grado_id')->nullable()->after('curso_id')->constrained('grados')->nullOnDelete();
            $table->foreignId('ciclo_id')->nullable()->after('grado_id')->constrained('ciclos')->nullOnDelete();
            $table->foreignId('docente_id')->nullable()->after('ciclo_id')->constrained('users')->nullOnDelete();
        });

        // Backfill portable (funciona igual en MySQL y SQLite): un
        // UPDATE...JOIN no se compila de forma confiable igual en ambos
        // motores, así que se hace fila por fila con valores planos. El
        // volumen de aula_virtual_cursos es chico (un colegio), no hay
        // problema de rendimiento.
        DB::table('aula_virtual_cursos')
            ->whereNotNull('horario_id')
            ->orderBy('id')
            ->get(['id', 'horario_id'])
            ->each(function (object $fila): void {
                $horario = DB::table('horarios')->where('id', $fila->horario_id)->first(['curso_id', 'grado_id', 'ciclo_id', 'docente_id']);

                if (! $horario) {
                    return;
                }

                DB::table('aula_virtual_cursos')->where('id', $fila->id)->update([
                    'curso_id' => $horario->curso_id,
                    'grado_id' => $horario->grado_id,
                    'ciclo_id' => $horario->ciclo_id,
                    'docente_id' => $horario->docente_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_id');
            $table->dropConstrainedForeignId('grado_id');
            $table->dropConstrainedForeignId('ciclo_id');
            $table->dropConstrainedForeignId('docente_id');
        });
    }
};
