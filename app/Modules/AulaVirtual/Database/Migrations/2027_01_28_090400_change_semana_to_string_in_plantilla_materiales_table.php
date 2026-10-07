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
        Schema::table('plantilla_materiales', function (Blueprint $table) {
            $table->string('semana', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('plantilla_materiales')->where('semana', 'NOT REGEXP', '^[0-9]+$')->update(['semana' => null]);

        Schema::table('plantilla_materiales', function (Blueprint $table) {
            $table->unsignedInteger('semana')->nullable()->change();
        });
    }
};
