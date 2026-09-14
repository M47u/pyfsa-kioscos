<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL: eliminación LÓGICA de un empleado (documento de alcance, módulo
 * 3.5 "Usuarios") — ver User::class (SoftDeletes) y
 * UsuarioController::destroy(). Nunca se borra la fila de verdad: sigue
 * siendo el `user_id` de sus ventas/pagos históricos (mismo motivo por el
 * que ninguna otra tabla del sistema hace DELETE real, ver
 * `movimientos_stock`/`ventas`/`pagos`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
