<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            // Solo se completa cuando medio_pago = 'fiado' (ver VentaRequest).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            // Sin foreign key: users vive en la base CENTRAL
            // (pyfsa_kioscos_central) y esta tabla vive en la base del tenant,
            // son conexiones/bases distintas y MySQL no soporta FK
            // cross-database (mismo patron que movimientos_stock).
            $table->unsignedBigInteger('user_id');
            // efectivo | transferencia | fiado (ver Venta y VentaRequest).
            $table->string('medio_pago');
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
