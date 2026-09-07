<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL, no tenant: vive en la tabla `tenants` de la base central
 * (pyfsa_kioscos_central), no en la base de cada comercio — ver módulo
 * 3.6 "Suscripciones" del documento de alcance. Es PyFsa quien administra
 * esto, no el kiosquero.
 *
 * Columnas reales, no el patrón de `timezone` (JSON `data` de
 * stancl/tenancy, ver Comercio::ZONAS_HORARIAS): el panel admin necesita
 * LISTAR y FILTRAR comercios por estado, mucho más simple con columnas
 * reales. Para que Eloquent las trate como columnas reales (y no las
 * empuje al JSON `data`) hace falta además declararlas en
 * Comercio::getCustomColumns() — ver ese modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('estado_suscripcion')->default('prueba')->after('id');

            // Informativo: el documento de alcance dice "sin cobro
            // automático en v1", así que esta fecha NO dispara nada sola,
            // es solo un dato que el admin ve para decidir a mano si pasar
            // el comercio a "vencida".
            $table->date('trial_termina_el')->nullable()->after('estado_suscripcion');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['estado_suscripcion', 'trial_termina_el']);
        });
    }
};
