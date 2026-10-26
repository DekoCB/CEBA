<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo personal del estudiante, puramente informativo -- distinto del
 * correo institucional (estudiantes.email, {dni}@ceba.test) que es la
 * cuenta de acceso al sistema y no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->string('correo_personal')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn('correo_personal');
        });
    }
};
