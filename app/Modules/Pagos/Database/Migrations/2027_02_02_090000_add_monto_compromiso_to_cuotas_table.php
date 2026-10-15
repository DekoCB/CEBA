<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monto que el deudor se comprometió a pagar, junto con fecha_compromiso
 * (2027_01_27_090000_add_fecha_compromiso_to_cuotas_table.php) -- opcional:
 * se puede seguir registrando solo la fecha, sin monto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->decimal('monto_compromiso', 8, 2)->nullable()->after('fecha_compromiso');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn('monto_compromiso');
        });
    }
};
