<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anular una venta (documento de alcance — corrección de error humano):
     * nunca se borra ni se edita una venta, se marca anulada y se revierte
     * su efecto con un movimiento nuevo (ver VentaController::anular y
     * MovimientoStock::TIPO_ANULACION_VENTA). anulada_en nullable = venta
     * vigente; no-nullo = anulada, y deja de contar en Cliente::saldo() y en
     * ReporteController (ver los whereNull('anulada_en') agregados ahí).
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('anulada_en')->nullable();
            // Sin foreign key: users vive en la base CENTRAL, mismo patrón
            // que user_id en esta misma tabla (ver migración original).
            $table->unsignedBigInteger('anulada_por')->nullable();
            // Opcional a propósito: no queremos friccionar corregir un
            // error con un campo obligatorio.
            $table->string('motivo_anulacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['anulada_en', 'anulada_por', 'motivo_anulacion']);
        });
    }
};
