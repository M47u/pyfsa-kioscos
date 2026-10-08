<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * "Mi perfil" (PerfilController) y cambio de contraseña obligatorio tras un
 * reset de PyFsa (EnsurePasswordCambiada). La contraseña del factory es
 * 'password'.
 */
class PerfilTest extends TenantTestCase
{
    private function empleado(): User
    {
        return User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);
    }

    private function exigirCambioDePassword(User $usuario): void
    {
        // Misma instancia que usa actingAs(): el flag tiene que estar en memoria.
        $usuario->debe_cambiar_password = true;
        $usuario->save();
    }

    public function test_dueno_y_empleado_ven_su_perfil(): void
    {
        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk()->assertSee($this->user->email);
        $this->actingAs($this->empleado())->get(route('perfil.edit'))->assertOk();
    }

    public function test_cambiar_solo_el_nombre_no_pide_password(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'Nombre Nuevo',
            'email' => $this->user->email,
        ]);

        $response->assertRedirect(route('perfil.edit'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('Nombre Nuevo', $this->user->fresh()->name);
    }

    public function test_cambiar_email_sin_password_actual_falla(): void
    {
        $original = $this->user->email;

        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => $this->user->name,
            'email' => 'nuevo@example.com',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame($original, $this->user->fresh()->email);
    }

    public function test_cambiar_email_con_password_incorrecta_falla(): void
    {
        $original = $this->user->email;

        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => $this->user->name,
            'email' => 'nuevo@example.com',
            'current_password' => 'equivocada',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame($original, $this->user->fresh()->email);
    }

    public function test_cambiar_email_con_password_correcta_funciona_y_queda_en_minusculas(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => $this->user->name,
            'email' => '  Nuevo@Example.COM ',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('nuevo@example.com', $this->user->fresh()->email);
    }

    public function test_email_duplicado_falla_incluyendo_otro_comercio_y_dados_de_baja(): void
    {
        $otroComercio = $this->crearComercioConBaseReal();
        $deOtroComercio = User::factory()->create(['comercio_id' => $otroComercio->id, 'rol' => User::ROL_DUENO]);
        $dadoDeBaja = User::factory()->create(['comercio_id' => $this->comercio->id, 'rol' => User::ROL_EMPLEADO]);
        $dadoDeBaja->delete();

        foreach ([$deOtroComercio->email, $dadoDeBaja->email] as $email) {
            $response = $this->actingAs($this->user)->put(route('perfil.update'), [
                'name' => $this->user->name,
                'email' => $email,
                'current_password' => 'password',
            ]);

            $response->assertSessionHasErrors('email');
        }
    }

    public function test_no_se_puede_cambiar_rol_ni_comercio_por_request_manipulado(): void
    {
        $empleado = $this->empleado();
        $otroComercio = $this->crearComercioConBaseReal();

        $this->actingAs($empleado)->put(route('perfil.update'), [
            'name' => 'Otro Nombre',
            'email' => $empleado->email,
            'rol' => User::ROL_DUENO,
            'comercio_id' => $otroComercio->id,
            'is_admin' => 1,
        ])->assertSessionHasNoErrors();

        $fresco = $empleado->fresh();
        $this->assertSame('Otro Nombre', $fresco->name);
        $this->assertSame(User::ROL_EMPLEADO, $fresco->rol);
        $this->assertSame($this->comercio->id, $fresco->comercio_id);
        $this->assertFalse((bool) $fresco->is_admin);
    }

    public function test_cambio_de_password_con_actual_incorrecta_falla(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'equivocada',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_cambio_de_password_debil_o_igual_a_la_actual_falla(): void
    {
        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_cambio_de_password_exitoso_hashea_apaga_el_flag_y_rota_remember_token(): void
    {
        $this->exigirCambioDePassword($this->user);
        $tokenViejo = $this->user->fresh()->remember_token;

        $response = $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ]);

        $response->assertRedirect(route('perfil.edit'));
        $response->assertSessionHasNoErrors();

        $fresco = $this->user->fresh();
        $this->assertTrue(Hash::check('Nueva-clave-segura-2026', $fresco->password));
        $this->assertFalse($fresco->debe_cambiar_password);
        $this->assertNotSame($tokenViejo, $fresco->remember_token);
    }

    public function test_con_flag_activo_las_rutas_del_panel_redirigen_a_perfil(): void
    {
        $this->exigirCambioDePassword($this->user);

        foreach (['panel', 'ventas.create', 'clientes.index', 'productos.index', 'reportes.index'] as $ruta) {
            $this->actingAs($this->user)->get(route($ruta))->assertRedirect(route('perfil.edit'));
        }

        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk();
    }

    public function test_con_flag_activo_actualizar_datos_queda_bloqueado(): void
    {
        $this->exigirCambioDePassword($this->user);
        $nombre = $this->user->name;

        $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'Hackeado',
            'email' => $this->user->email,
        ])->assertRedirect(route('perfil.edit'));

        $this->assertSame($nombre, $this->user->fresh()->name);
    }

    public function test_tras_cambiar_la_password_ya_accede_normal(): void
    {
        $this->exigirCambioDePassword($this->user);

        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ]);

        $this->actingAs($this->user->fresh())->get(route('panel'))->assertOk();
    }

    public function test_sin_loop_con_suscripcion_vencida_ni_sin_zona_horaria(): void
    {
        $this->exigirCambioDePassword($this->user);

        $this->comercio->estado_suscripcion = Comercio::ESTADO_VENCIDA;
        $this->comercio->timezone = null;
        $this->comercio->save();

        $this->actingAs($this->user)->get(route('panel'))->assertRedirect(route('perfil.edit'));
        $this->actingAs($this->user)->get(route('suscripcion-vencida'))->assertRedirect(route('perfil.edit'));
        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk();

        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ])->assertRedirect(route('perfil.edit'));

        // Ya sin flag, el gate de suscripción vuelve a aplicar como siempre.
        $this->actingAs($this->user->fresh())->get(route('panel'))->assertRedirect(route('suscripcion-vencida'));
    }

    /**
     * Mismo patrón que PagoTest: sin la tenancy pre-inicializada, el
     * middleware real (y su orden respecto de EnsurePasswordCambiada) tiene
     * que resolver el flag igual.
     */
    public function test_perfil_funciona_sin_tenancy_pre_inicializada(): void
    {
        $this->exigirCambioDePassword($this->user);

        tenancy()->end();

        $this->actingAs($this->user)->get(route('panel'))->assertRedirect(route('perfil.edit'));
        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk();

        tenancy()->initialize($this->comercio);
    }

    /**
     * Arma el driver de sesión `database` con `session.connection` en null
     * (como en producción) MIENTRAS la tenancy no está inicializada: el
     * handler captura la conexión central, y recién después se inicializa la
     * tenancy, de modo que el request corre con el default apuntando al
     * tenant — el escenario que rompía invalidarSesiones().
     */
    private function usarSesionesEnBaseCentral(): void
    {
        tenancy()->end();
        config(['session.driver' => 'database', 'session.connection' => null]);
        app('session')->driver();
        tenancy()->initialize($this->comercio);
    }

    private function insertarSesionAjena(User $usuario, string $id): void
    {
        DB::connection(config('tenancy.database.central_connection'))->table('sessions')->insert([
            'id' => $id,
            'user_id' => $usuario->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    private function sesionesAjenasDe(User $usuario): int
    {
        return DB::connection(config('tenancy.database.central_connection'))->table('sessions')
            ->where('user_id', $usuario->id)->where('id', 'like', 'ajena-%')->count();
    }

    public function test_cambio_de_password_cierra_las_otras_sesiones_con_tenancy_inicializada(): void
    {
        $this->usarSesionesEnBaseCentral();
        $this->insertarSesionAjena($this->user, 'ajena-1');

        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ])->assertRedirect(route('perfil.edit'));

        $this->assertSame(0, $this->sesionesAjenasDe($this->user));
    }

    public function test_dueno_cambiando_clave_de_empleado_cierra_sus_sesiones_con_tenancy_inicializada(): void
    {
        $empleado = $this->empleado();
        $this->usarSesionesEnBaseCentral();
        $this->insertarSesionAjena($empleado, 'ajena-2');

        $this->actingAs($this->user)->put(route('usuarios.password.update', $empleado), [
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertSame(0, $this->sesionesAjenasDe($empleado));
    }

    private function payloadVenta(): array
    {
        $producto = Producto::create([
            'nombre' => 'Chicle', 'precio_costo' => 1, 'precio_venta' => 2, 'stock_minimo' => 0, 'controla_stock' => false,
        ]);

        return [
            'medio_pago' => 'efectivo',
            'uuid_dispositivo' => '99999999-9999-4999-8999-999999999999',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ];
    }

    public function test_venta_de_la_cola_offline_pasa_con_flag_activo(): void
    {
        $this->exigirCambioDePassword($this->user);
        $payload = $this->payloadVenta();

        $this->actingAs($this->user)
            ->withHeaders(['X-Sincronizacion-Cola' => '1', 'Accept' => 'application/json'])
            ->post(route('ventas.store'), $payload)
            ->assertRedirect(route('ventas.index'));

        $this->assertSame(1, Venta::count());
    }

    public function test_pago_de_la_cola_offline_pasa_con_flag_activo(): void
    {
        $this->exigirCambioDePassword($this->user);
        $cliente = Cliente::create(['nombre' => 'Juan', 'telefono' => null, 'limite_credito' => 5000]);

        $this->actingAs($this->user)
            ->withHeaders(['X-Sincronizacion-Cola' => '1', 'Accept' => 'application/json'])
            ->post(route('clientes.pagos.store', $cliente), ['monto' => 100, 'uuid_dispositivo' => '88888888-8888-4888-8888-888888888888'])
            ->assertRedirect(route('clientes.show', $cliente));

        $this->assertDatabaseHas('pagos', ['cliente_id' => $cliente->id, 'monto' => 100.00]);
    }

    public function test_venta_en_vivo_json_con_flag_activo_responde_423_y_no_crea_nada(): void
    {
        $this->exigirCambioDePassword($this->user);

        $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $this->payloadVenta())
            ->assertStatus(423)
            ->assertJsonStructure(['message', 'errors' => ['password']]);

        $this->assertSame(0, Venta::count());
    }

    public function test_el_header_de_cola_no_abre_otras_rutas_con_flag_activo(): void
    {
        $this->exigirCambioDePassword($this->user);

        $this->actingAs($this->user)
            ->withHeaders(['X-Sincronizacion-Cola' => '1', 'Accept' => 'application/json'])
            ->put(route('perfil.update'), ['name' => 'X', 'email' => $this->user->email])
            ->assertStatus(423);
    }

    public function test_html_con_flag_activo_sigue_redirigiendo(): void
    {
        $this->exigirCambioDePassword($this->user);

        $this->actingAs($this->user)->post(route('ventas.store'), $this->payloadVenta())
            ->assertRedirect(route('perfil.edit'));
    }

    public function test_usuario_con_flag_recibe_el_redirect_y_no_un_404_de_binding(): void
    {
        $this->exigirCambioDePassword($this->user);
        tenancy()->end();

        $this->actingAs($this->user)->get('/productos/999999/edit')->assertRedirect(route('perfil.edit'));

        tenancy()->initialize($this->comercio);
    }

    public function test_cola_offline_de_usuario_con_flag_no_escribe_en_comercio_vencido(): void
    {
        $this->exigirCambioDePassword($this->user);
        $this->comercio->estado_suscripcion = Comercio::ESTADO_VENCIDA;
        $this->comercio->save();

        $this->actingAs($this->user)
            ->withHeaders(['X-Sincronizacion-Cola' => '1', 'Accept' => 'application/json'])
            ->post(route('ventas.store'), $this->payloadVenta())
            ->assertRedirect(route('suscripcion-vencida'));

        $this->assertSame(0, Venta::count());
        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk();
    }

    public function test_cola_offline_de_usuario_con_flag_no_escribe_en_comercio_sin_zona_horaria(): void
    {
        $this->exigirCambioDePassword($this->user);
        $this->comercio->timezone = null;
        $this->comercio->save();

        $this->actingAs($this->user)
            ->withHeaders(['X-Sincronizacion-Cola' => '1', 'Accept' => 'application/json'])
            ->post(route('ventas.store'), $this->payloadVenta())
            ->assertRedirect(route('zona-horaria.edit'));

        $this->assertSame(0, Venta::count());
        $this->actingAs($this->user)->get(route('perfil.edit'))->assertOk();
    }

    public function test_cambio_de_password_destruye_la_sesion_actual_vieja_y_sigue_logueado(): void
    {
        $this->usarSesionesEnBaseCentral();
        $idViejo = str_repeat('a', 40);
        DB::connection(config('tenancy.database.central_connection'))->table('sessions')->insert([
            'id' => $idViejo,
            'user_id' => $this->user->id,
            'payload' => base64_encode(serialize(['_token' => 'x'])),
            'last_activity' => time(),
        ]);

        $this->actingAs($this->user)
            ->withCookie(config('session.cookie'), $idViejo)
            ->put(route('perfil.password'), [
                'current_password' => 'password',
                'password' => 'Nueva-clave-segura-2026',
                'password_confirmation' => 'Nueva-clave-segura-2026',
            ])->assertRedirect(route('perfil.edit'));

        $this->assertSame(0, DB::connection(config('tenancy.database.central_connection'))
            ->table('sessions')->where('id', $idViejo)->count());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_throttle_de_current_password_en_el_cambio_de_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)->put(route('perfil.password'), [
                'current_password' => 'equivocada',
                'password' => 'Nueva-clave-segura-2026',
                'password_confirmation' => 'Nueva-clave-segura-2026',
            ])->assertSessionHasErrors('current_password');
        }

        $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'password',
            'password' => 'Nueva-clave-segura-2026',
            'password_confirmation' => 'Nueva-clave-segura-2026',
        ])->assertStatus(429);

        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_throttle_de_current_password_en_el_cambio_de_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)->put(route('perfil.update'), [
                'name' => 'N', 'email' => 'nuevo@example.com', 'current_password' => 'equivocada',
            ])->assertSessionHasErrors('current_password');
        }

        $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'N', 'email' => 'nuevo@example.com', 'current_password' => 'password',
        ])->assertStatus(429);
    }

    public function test_email_como_array_es_error_de_validacion_y_no_un_500(): void
    {
        $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'N',
            'email' => ['x'],
            'current_password' => ['x'],
        ])->assertSessionHasErrors('email');
    }
}
