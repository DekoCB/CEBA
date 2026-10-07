<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horario_dias', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->change();
            $table->time('hora_fin')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('horario_dias')->whereNull('hora_inicio')->orWhereNull('hora_fin')->update([
            'hora_inicio' => '00:00:00',
            'hora_fin' => '00:00:00',
        ]);

        Schema::table('horario_dias', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable(false)->change();
            $table->time('hora_fin')->nullable(false)->change();
        });
    }
};
