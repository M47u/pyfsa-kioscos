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
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            // 'reposicion' (suma) | 'venta' (resta) — ver MovimientoStock.
            $table->string('tipo');
            // Puede ser negativo: reposición siempre positiva, venta siempre
            // negativa. El stock actual de un producto = SUM(cantidad).
            $table->integer('cantidad');
            // Sin foreign key: users vive en la base CENTRAL
            // (pyfsa_kioscos_central) y esta tabla vive en la base del tenant,
            // son conexiones/bases distintas y MySQL no soporta FK
            // cross-database. Queda como referencia simple al id del usuario.
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
