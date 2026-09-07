<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Alta de empleados por parte del dueño (documento de alcance, módulo 3.5
 * "Usuarios"). Ver UsuarioController — dueño-only, sin edición ni baja en
 * esta primera versión.
 */
class UsuarioTest extends TenantTestCase
{
    public function test_dueno_da_de_alta_un_empleado(): void
    {
        $response = $this->actingAs($this->user)->post(route('usuarios.store'), [
            'name' => 'Empleado Uno',
            'email' => 'empleado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $empleado = User::where('email', 'empleado@example.com')->firstOrFail();

        $this->assertSame($this->comercio->id, $empleado->comercio_id);
        $this->assertSame(User::ROL_EMPLEADO, $empleado->rol);
        $this->assertTrue($empleado->esEmpleado());
        $this->assertTrue(Hash::check('password123', $empleado->password));
    }

    public function test_empleado_recibe_403_en_las_rutas_de_gestion_de_usuarios(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($empleado)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($empleado)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($empleado)->post(route('usuarios.store'), [
            'name' => 'Otro Empleado',
            'email' => 'otro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }
}
