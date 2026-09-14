<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo criterio y mismo motivo que ventas.uuid_dispositivo (ver
     * 2026_09_08_150000_add_offline_sync_columns_to_ventas_table.php):
     * idempotencia de "Cobrar fiado" offline. Un pago no tiene equivalente a
     * "stock insuficiente" — no hay columna extra acá, ver
     * ClienteController::registrarPago.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('uuid_dispositivo')->nullable()->unique()->after('monto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('uuid_dispositivo');
        });
    }
};
