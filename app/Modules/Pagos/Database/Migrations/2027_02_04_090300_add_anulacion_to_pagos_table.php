<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago aprobado (con recibo ya emitido) puede anularse para corregir un
 * error -- el pago y su recibo quedan en la base de datos, marcados, en vez
 * de borrarse, para que la numeración correlativa del recibo nunca quede
 * con huecos sin explicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('anulado_por')->nullable()->after('motivo_rechazo')->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_anulacion')->nullable()->after('anulado_por');
            $table->string('motivo_anulacion')->nullable()->after('fecha_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['fecha_anulacion', 'motivo_anulacion']);
        });
    }
};
