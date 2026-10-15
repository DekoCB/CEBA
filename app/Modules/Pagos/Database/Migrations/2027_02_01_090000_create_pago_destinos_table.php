<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago puede cubrir más de un concepto/cuota/cargo a la vez (ej. cuota 1
 * completa + parte de la cuota 2, en un solo pago): cada fila de
 * pago_destinos es "una cosa que este pago cubre" -- una Cuota, un
 * CargoAdicional, o un concepto suelto del catálogo sin cuota/cargo
 * vinculado (Matrícula, Certificado...) -- y la suma de sus montos debe
 * coincidir con pagos.monto. Distinto de pago_partes, que reparte por MEDIO
 * de pago (efectivo/Yape), no por concepto -- ver PagoParte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos_pago')->restrictOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->foreignId('cargo_adicional_id')->nullable()->constrained('cargos_adicionales')->nullOnDelete();
            $table->string('detalle', 255)->nullable();
            $table->decimal('monto', 8, 2);
            $table->timestamps();

            $table->index('cuota_id');
            $table->index('cargo_adicional_id');
        });

        // Todo pago existente hasta ahora cubría exactamente una cosa (una
        // cuota, un cargo adicional, o un concepto suelto): se migra a un
        // único destino por pago para que ambos modelos queden coherentes
        // desde ya (pagos.concepto_id/cuota_id/cargo_adicional_id/detalle se
        // conservan como el resumen "denormalizado" de ese único destino).
        DB::table('pagos')->orderBy('id')->chunk(200, function ($pagos) {
            foreach ($pagos as $pago) {
                DB::table('pago_destinos')->insert([
                    'pago_id' => $pago->id,
                    'concepto_id' => $pago->concepto_id,
                    'cuota_id' => $pago->cuota_id,
                    'cargo_adicional_id' => $pago->cargo_adicional_id,
                    'detalle' => $pago->detalle,
                    'monto' => $pago->monto,
                    'created_at' => $pago->created_at,
                    'updated_at' => $pago->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_destinos');
    }
};
