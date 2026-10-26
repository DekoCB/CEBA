<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distintos celulares de respaldo del apoderado pueden ser de personas
 * distintas (ej. la mamá, un tío) -- se anota de quién es cada uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apoderado_telefonos', function (Blueprint $table) {
            $table->string('parentesco', 50)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('apoderado_telefonos', function (Blueprint $table) {
            $table->dropColumn('parentesco');
        });
    }
};
