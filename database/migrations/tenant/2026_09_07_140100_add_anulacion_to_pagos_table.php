<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo patrón que la anulación de ventas (ver la migración hermana
     * add_anulacion_to_ventas_table): un pago mal cargado no se borra ni se
     * edita, se marca anulado. Más simple que la venta porque un pago no
     * toca stock — el propio Cliente::saldo() deja de restarlo con el
     * whereNull('anulado_en') agregado ahí.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable();
            // Sin foreign key: users vive en la base CENTRAL, mismo patrón
            // que user_id en esta misma tabla (ver migración original).
            $table->unsignedBigInteger('anulado_por')->nullable();
            $table->string('motivo_anulacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['anulado_en', 'anulado_por', 'motivo_anulacion']);
        });
    }
};
