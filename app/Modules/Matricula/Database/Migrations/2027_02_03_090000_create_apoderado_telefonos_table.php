<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Celulares de respaldo de un apoderado, aparte del principal
 * (apoderados.celular, que no se toca). Mismo patrón que
 * estudiante_telefonos: aditivo, cualquier lugar que ya use
 * apoderados.celular sigue funcionando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoderado_telefonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apoderado_id')->constrained('apoderados')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderado_telefonos');
    }
};
