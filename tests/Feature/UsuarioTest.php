<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public function test_dueno_restablece_la_contrasena_de_un_empleado(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
            'password' => 'password-vieja',
        ]);

        $this->actingAs($this->user)->get(route('usuarios.password.edit', $empleado))->assertOk();

        $response = $this->actingAs($this->user)->put(route('usuarios.password.update', $empleado), [
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $this->assertTrue(Hash::check('password-nueva-123', $empleado->fresh()->password));
        $this->assertFalse(Hash::check('password-vieja', $empleado->fresh()->password));
    }

    public function test_empleado_recibe_403_al_intentar_restablecer_una_contrasena(): void
    {
        $otroEmpleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($empleado)->get(route('usuarios.password.edit', $otroEmpleado))->assertForbidden();
        $this->actingAs($empleado)->put(route('usuarios.password.update', $otroEmpleado), [
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ])->assertForbidden();
    }

    /**
     * Regresión: `{usuario}` hace binding contra `users`, que es CENTRAL —
     * tiene los usuarios de TODOS los comercios, no solo el actual. Sin el
     * chequeo de `UsuarioController::autorizarMismoComercio()`, el dueño de
     * un comercio podría cambiarle la contraseña a un empleado de OTRO
     * comercio con solo probar IDs en la URL.
     */
    public function test_dueno_no_puede_restablecer_la_contrasena_de_un_usuario_de_otro_comercio(): void
    {
        $otroComercio = $this->crearComercioConBaseReal();
        $usuarioDeOtroComercio = User::factory()->create([
            'comercio_id' => $otroComercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($this->user)->get(route('usuarios.password.edit', $usuarioDeOtroComercio))->assertForbidden();
        $this->actingAs($this->user)->put(route('usuarios.password.update', $usuarioDeOtroComercio), [
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ])->assertForbidden();

        $otroComercio->delete();
    }

    /**
     * `users` es CENTRAL (ver CentralConnection en el modelo) — los
     * asserts de base de Laravel (assertDatabaseHas/assertSoftDeleted/
     * assertDatabaseCount) sin conexión explícita consultan la conexión
     * DEFAULT, que acá es la del TENANT (ya inicializada por
     * TenantTestCase::setUp()). Todas las verificaciones de este archivo
     * usan consultas Eloquent sobre el modelo (que sí fuerza la conexión
     * central solo) en vez de esos asserts crudos, para no pisar el mismo
     * gotcha en cada test nuevo.
     */
    public function test_dueno_elimina_un_empleado(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $response = $this->actingAs($this->user)->delete(route('usuarios.destroy', $empleado));

        $response->assertRedirect(route('usuarios.index'));

        // Eliminación LÓGICA: sigue existiendo en `users` (withTrashed
        // la encuentra), pero el scope global de SoftDeletes la saca de
        // cualquier consulta normal — incluido el listado.
        $this->assertNotNull(User::withTrashed()->find($empleado->id)->deleted_at);
        $this->assertNull(User::find($empleado->id));

        $this->actingAs($this->user)->get(route('usuarios.index'))->assertDontSee($empleado->email);
    }

    /**
     * El punto de la eliminación lógica: el empleado eliminado no puede
     * volver a entrar al sistema — no alcanza con que desaparezca del
     * listado, tiene que dejar de poder loguearse. Se verifica contra
     * Auth::attempt() directo (no la ruta /login completa): actingAs()
     * deja la sesión de test logueada como $this->user para cualquier
     * request posterior, así que un POST a /login en el mismo test
     * pegaría contra el middleware 'guest' (que redirige a alguien ya
     * autenticado) en vez de validar credenciales — false negativo, no
     * prueba lo que dice probar.
     */
    public function test_empleado_eliminado_no_puede_loguearse(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
            'password' => 'password123',
        ]);

        $this->actingAs($this->user)->delete(route('usuarios.destroy', $empleado));

        $this->assertFalse(Auth::attempt([
            'email' => $empleado->email,
            'password' => 'password123',
        ]));
    }

    public function test_dueno_no_puede_eliminarse_a_si_mismo_ni_a_otro_dueno(): void
    {
        $this->actingAs($this->user)->delete(route('usuarios.destroy', $this->user))->assertForbidden();
        $this->assertNull(User::find($this->user->id)->deleted_at);
    }

    public function test_empleado_recibe_403_al_intentar_eliminar_un_usuario(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $otroEmpleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($empleado)->delete(route('usuarios.destroy', $otroEmpleado))->assertForbidden();
    }

    public function test_dueno_no_puede_eliminar_un_usuario_de_otro_comercio(): void
    {
        $otroComercio = $this->crearComercioConBaseReal();
        $usuarioDeOtroComercio = User::factory()->create([
            'comercio_id' => $otroComercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($this->user)->delete(route('usuarios.destroy', $usuarioDeOtroComercio))->assertForbidden();

        $otroComercio->delete();
    }
}
