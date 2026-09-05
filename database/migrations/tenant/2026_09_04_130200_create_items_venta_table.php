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
        Schema::create('items_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->integer('cantidad');
            // Snapshot del precio de venta AL MOMENTO de vender: nunca se lee
            // producto->precio_venta despues de este punto, porque el precio
            // de lista puede cambiar y eso rompería el historial de ventas
            // pasadas (ver ItemVenta y VentaController::store).
            $table->decimal('precio_unitario', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_venta');
    }
};
