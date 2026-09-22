<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Control de stock opcional (gap encontrado por el usuario, documento
     * de alcance nuevo): un kiosco puede querer vender un artículo sin
     * llevarle la cuenta de stock (ej. bolsas sueltas, artículos de reventa
     * variable). Decisión de diseño: el toggle es POR PRODUCTO, no un
     * interruptor único a nivel comercio — más flexible (un mismo kiosco
     * puede controlar stock de bebidas y no de golosinas sueltas) y sigue
     * cubriendo el caso "el comercio no quiere controlar stock" (se
     * desmarca en todos los productos). Default true: preserva el
     * comportamiento actual para todo producto ya cargado.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('controla_stock')->default(true)->after('stock_minimo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('controla_stock');
        });
    }
};
