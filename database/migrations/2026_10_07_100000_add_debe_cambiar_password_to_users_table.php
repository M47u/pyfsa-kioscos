<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL: marca a los usuarios a los que PyFsa les restableció la
 * contraseña desde el panel admin — tienen que cambiarla en "Mi perfil"
 * antes de usar el sistema (ver EnsurePasswordCambiada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
