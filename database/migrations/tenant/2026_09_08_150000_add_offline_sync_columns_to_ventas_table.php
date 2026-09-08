<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offline (documento de alcance — "Solo Ventas y Cobrar fiado funcionan
     * offline"): ver CLAUDE.md, sección de arquitectura offline, y el
     * módulo resources/js/offline.js.
     *
     * uuid_dispositivo: generado SIEMPRE en el frontend (crypto.randomUUID())
     * para toda venta, se mande online al toque o se encole offline — un
     * solo código server-side (VentaController::store) maneja los dos
     * casos. Nullable porque una venta vieja o cualquier venta que nunca
     * pasó por este flujo sigue sin él. UNIQUE es la garantía real de
     * idempotencia: si el mismo item de la cola offline se reintenta
     * sincronizar dos veces (red flaky, doble intento), el segundo INSERT
     * con el mismo uuid choca contra esta constraint en vez de duplicar la
     * venta — VentaController::store también chequea esto ANTES de insertar
     * como camino feliz, la constraint es el resguardo real contra la
     * carrera de dos intentos casi simultáneos.
     *
     * sincronizada_con_stock_insuficiente: una venta ONLINE sigue
     * bloqueándose si deja stock negativo (sin tocar, ver
     * VentaController::store). Pero una venta que llega por la cola OFFLINE
     * no pudo validar el stock contra el servidor en el momento real de la
     * venta (el cliente ya se fue con el producto) — se registra igual
     * aunque deje stock negativo, y esta columna la marca para que el dueño
     * la revise después (ver ventas/index.blade.php y reportes/index.blade.php).
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('uuid_dispositivo')->nullable()->unique()->after('total');
            $table->boolean('sincronizada_con_stock_insuficiente')->default(false)->after('uuid_dispositivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['uuid_dispositivo', 'sincronizada_con_stock_insuficiente']);
        });
    }
};
