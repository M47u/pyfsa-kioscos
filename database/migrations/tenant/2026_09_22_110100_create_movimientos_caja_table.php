<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingresos/egresos de efectivo dentro de un turno de caja (ver
     * database/migrations/tenant/2026_09_22_110000_create_cajas_table.php)
     * — ej. un retiro para cambio, un pago a un proveedor en efectivo. Las
     * VENTAS no generan una fila acá: su aporte al efectivo esperado se
     * calcula agregando la tabla `ventas` directamente por rango de fecha
     * (ver Caja::efectivoEsperadoActual()), no se duplican como
     * "movimientos de caja" — evita mantener dos historiales del mismo
     * dinero.
     */
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            // ingreso | egreso (ver MovimientoCaja::TIPO_*).
            $table->string('tipo');
            $table->decimal('monto', 10, 2);
            $table->string('concepto');
            // Sin foreign key: users es CENTRAL, esta tabla vive en la base
            // del tenant — mismo patrón que el resto de las tablas de
            // movimientos.
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
