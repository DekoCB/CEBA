<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona las aulas virtuales duplicadas que ya existan para un mismo
 * curso+grado+ciclo+docente (ej. un admin que envió el formulario "Nuevo
 * horario" varias veces, una por franja, para el mismo curso -- antes cada
 * envío creaba su propia aula virtual separada). Por cada grupo duplicado
 * se queda la fila de id más bajo (la más antigua) como canónica, se
 * reasigna el contenido (materiales, clases grabadas, tareas,
 * publicaciones, foros) de las duplicadas a la canónica, y se borran las
 * filas duplicadas.
 *
 * Solo datos, sin cambio de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grupos = DB::table('aula_virtual_cursos')
            ->select('curso_id', 'grado_id', 'ciclo_id', 'docente_id')
            ->whereNotNull('curso_id')
            ->whereNotNull('grado_id')
            ->whereNotNull('ciclo_id')
            ->whereNotNull('docente_id')
            ->groupBy('curso_id', 'grado_id', 'ciclo_id', 'docente_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            $ids = DB::table('aula_virtual_cursos')
                ->where('curso_id', $grupo->curso_id)
                ->where('grado_id', $grupo->grado_id)
                ->where('ciclo_id', $grupo->ciclo_id)
                ->where('docente_id', $grupo->docente_id)
                ->orderBy('id')
                ->pluck('id');

            $canonico = $ids->first();
            $duplicados = $ids->slice(1)->values();

            foreach (['materiales', 'clases_grabadas', 'tareas', 'publicaciones', 'foros'] as $tabla) {
                DB::table($tabla)->whereIn('curso_virtual_id', $duplicados)->update(['curso_virtual_id' => $canonico]);
            }

            DB::table('aula_virtual_cursos')->whereIn('id', $duplicados)->delete();
        }
    }

    /**
     * No reversible: las filas duplicadas y su asociación original con
     * cada Horario ya no se pueden reconstruir una vez fusionadas y
     * borradas -- degradación aceptada, igual que otras migraciones de
     * este proyecto que fusionan o normalizan datos existentes.
     */
    public function down(): void {}
};
