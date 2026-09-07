<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL: rol del usuario dentro de su comercio (documento de alcance,
 * módulo 3.5 "Usuarios"). Dueño y empleados comparten la misma tabla
 * `users` — no hay permisos granulares, solo dos roles fijos (ver
 * User::ROL_DUENO / User::ROL_EMPLEADO y el gate en
 * EnsureUserIsDueno).
 *
 * Default 'dueño' para usuarios YA EXISTENTES: hasta esta migración todo
 * usuario cargado era, de hecho, el dueño del comercio (no existía alta
 * de empleados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('dueño')->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
