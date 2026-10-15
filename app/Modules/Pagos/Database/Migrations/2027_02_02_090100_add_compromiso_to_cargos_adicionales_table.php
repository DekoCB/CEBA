<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo mecanismo de compromiso de pago que Cuota (ver
 * 2027_01_27_090000_add_fecha_compromiso_to_cuotas_table.php y
 * 2027_02_02_090000_add_monto_compromiso_to_cuotas_table.php), que
 * cargos_adicionales no tenía hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos_adicionales', function (Blueprint $table) {
            $table->date('fecha_compromiso')->nullable()->after('estado');
            $table->decimal('monto_compromiso', 8, 2)->nullable()->after('fecha_compromiso');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_adicionales', function (Blueprint $table) {
            $table->dropColumn(['fecha_compromiso', 'monto_compromiso']);
        });
    }
};
