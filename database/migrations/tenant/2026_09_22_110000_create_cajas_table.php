<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de Caja (gap encontrado por el usuario, no está en el
     * documento de alcance original): apertura/cierre de turno simple, no
     * un sistema contable. Una fila = un turno de caja completo (desde que
     * se abre hasta que se cierra), no una tabla de saldo corriente.
     *
     * `cerrada_en` nulo = caja actualmente abierta. Solo puede haber UNA
     * caja abierta a la vez por comercio (chequeado en CajaController,
     * NO con una constraint de base — MySQL/InnoDB trata cada NULL como
     * distinto en un índice UNIQUE, así que un UNIQUE sobre cerrada_en no
     * serviría para impedir dos filas con cerrada_en NULL a la vez; queda
     * como una validación de aplicación, con el mismo riesgo de carrera
     * asumido que el resto del sistema en el caso de dos requests casi
     * simultáneas — de bajo impacto real: un solo kiosco, una sola caja
     * física).
     *
     * efectivo_esperado/efectivo_contado/diferencia quedan NULL mientras la
     * caja está abierta: se calculan y se congelan recién al cerrar (ver
     * Caja::efectivoEsperadoActual() para el cálculo EN VIVO que se usa
     * mientras está abierta, sin persistir nada todavía).
     *
     * user_id_apertura/user_id_cierre sin foreign key: `users` es CENTRAL
     * y esta tabla vive en la base del tenant — mismo patrón que
     * movimientos_stock/ventas/pagos (MySQL no soporta FK cross-database).
     */
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('abierta_en');
            $table->decimal('monto_apertura', 10, 2);
            $table->unsignedBigInteger('user_id_apertura');
            $table->timestamp('cerrada_en')->nullable();
            $table->decimal('efectivo_esperado', 10, 2)->nullable();
            $table->decimal('efectivo_contado', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->unsignedBigInteger('user_id_cierre')->nullable();
            $table->string('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
