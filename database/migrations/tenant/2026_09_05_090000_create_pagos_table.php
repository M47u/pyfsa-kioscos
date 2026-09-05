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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            // Un pago siempre pertenece a un cliente con cuenta corriente
            // (fiado) — ver Cliente::saldo(), que ahora resta estos pagos.
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            // Sin foreign key: users vive en la base CENTRAL
            // (pyfsa_kioscos_central) y esta tabla vive en la base del tenant,
            // son conexiones/bases distintas y MySQL no soporta FK
            // cross-database (mismo patrón que movimientos_stock/ventas).
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
