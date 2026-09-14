<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (no tenant): rastro de las acciones que PyFsa ejecuta sobre los
 * comercios desde /admin — crear uno, cambiarle el estado de suscripción.
 * Va acá y no en database/migrations/tenant/ porque esas acciones son
 * transversales a TODOS los comercios y las ejecuta un admin de
 * plataforma, que no pertenece a ningún tenant.
 *
 * Sin foreign keys en `user_id`/`comercio_id` a propósito, mismo criterio
 * ya establecido en el proyecto para las columnas "quién/sobre qué" de un
 * historial (ver `anulada_por`/`anulado_por`/`user_id` en las tablas de
 * tenant): un log tiene que sobrevivir a que borren al usuario o al
 * comercio que menciona — si un FK con cascade lo borrara, o un
 * nullOnDelete lo vaciara, dejaría de ser auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('accion')->index();
            // string, no foreignId: tenants.id es UUID (string), mismo
            // motivo que users.comercio_id.
            $table->string('comercio_id')->nullable()->index();
            $table->json('detalles')->nullable();
            // Sin updated_at: un registro de auditoría nunca se edita (ver
            // RegistroAuditoria::UPDATED_AT).
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_auditoria');
    }
};
