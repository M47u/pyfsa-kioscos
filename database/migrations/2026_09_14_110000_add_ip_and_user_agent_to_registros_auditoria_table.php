<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (ver la migración que creó `registros_auditoria`).
 *
 * Para investigar una cuenta admin comprometida, DE DÓNDE vino la acción
 * importa tanto como quién la hizo: dos cambios de estado desde la misma
 * sesión pero desde dos países distintos es exactamente la señal que un
 * log sin estos campos no puede dar.
 *
 * Las dos columnas son nullable y sin default: las filas ya escritas se
 * quedan en NULL (no hay forma de reconstruir el dato hacia atrás), y las
 * acciones de consola — `admin:crear`, ver CrearAdminCommand — también van
 * a quedar en NULL siempre, porque no hay request HTTP de la cual sacarlo.
 * Es esperado, no un bug: el "de dónde" de una acción de consola es el
 * acceso al servidor, que se audita fuera de esta app.
 *
 * `user_agent` con 512 en vez del 255 default de string(): los
 * user-agents reales de navegadores móviles pasan cómodos los 255 y un
 * valor truncado a la mitad es peor que ninguno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_auditoria', function (Blueprint $table) {
            // 45 = largo máximo de una IPv6 en texto (incluido el formato
            // IPv4-mapeado ::ffff:192.168.1.1), el mismo que usa Laravel
            // en sus propias tablas con columnas de IP.
            $table->string('ip', 45)->nullable()->after('detalles');
            $table->string('user_agent', 512)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->dropColumn(['ip', 'user_agent']);
        });
    }
};
