<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            // Fecha que el deudor prometió para pagar esta cuota (vencida o
            // no) -- distinta de fecha_vencimiento, que es la fecha original
            // pactada al armar el cronograma.
            $table->date('fecha_compromiso')->nullable()->after('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn('fecha_compromiso');
        });
    }
};
