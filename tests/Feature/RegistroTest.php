<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta pública de un Comercio nuevo (gap encontrado por el usuario, ver
 * App\Http\Controllers\Auth\RegisteredUserController). No hereda de
 * TenantTestCase a propósito (mismo motivo que Admin\ComercioTest): estas
 * rutas viven en routes/web.php, sin tenancy inicializada de antemano —
 * es justo lo que se está probando, que el flujo la deja bien inicializada
 * después.
 */
class RegistroTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // A diferencia de Admin\ComercioTest (rutas centrales, tenancy
        // nunca se inicializa), acá SÍ se visita /panel dentro del test —
        // eso deja la tenancy inicializada (y `database.default` apuntando
        // al tenant, ver RevertToCentralContext) hasta el final del
        // proceso de PHPUnit, que no reinicia entre tests como sí pasa en
        // producción (una request = un proceso). Sin este `end()` antes de
        // borrar, RefreshDatabase::tearDown() (más abajo, en
        // parent::tearDown()) intenta reconectar contra "la conexión
        // default", que para ese entonces sigue siendo la del tenant, y
        // como ya la borramos en la línea de abajo, explota con
        // "Unknown database" — mismo orden que usa TenantTestCase::tearDown().
        tenancy()->end();

        // Comercio::create() dispara CreateDatabase de verdad (ver
        // TenancyServiceProvider) — hay que borrar cada comercio creado acá
        // para no dejar bases de datos MySQL huérfanas entre corridas.
        Comercio::all()->each->delete();

        parent::tearDown();
    }

    private function datosDeRegistro(array $overrides = []): array
    {
        return array_merge([
            'nombre_comercio' => 'Kiosco Doña Rosa',
            'name' => 'Rosa',
            'email' => 'rosa@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_registro_exitoso_crea_comercio_y_usuario_dueno_y_loguea_automaticamente(): void
    {
        $response = $this->post(route('registro'), $this->datosDeRegistro());

        $response->assertRedirect(route('panel'));

        $user = User::where('email', 'rosa@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->esDueno());
        $this->assertNotNull($user->comercio_id);
        $this->assertAuthenticatedAs($user);

        $comercio = Comercio::find($user->comercio_id);
        $this->assertNotNull($comercio);
        $this->assertSame('Kiosco Doña Rosa', $comercio->nombre);
        // Informativo para el panel admin de PyFsa (Admin\ComercioController)
        // — no bloquea nada, estado_suscripcion sigue en 'prueba' por el
        // default de la migración.
        $this->assertNotNull($comercio->trial_termina_el);
        $this->assertTrue($comercio->trial_termina_el->isSameDay(now()->addDays(14)));

        // El propio gate de zona horaria (EnsureComercioTimezoneIsConfigured,
        // routes/tenant.php) manda a elegirla en la siguiente request, porque
        // un comercio recién creado nunca la tiene configurada — no hace
        // falta armar ese paso a mano en el controller de registro.
        $this->get(route('panel'))->assertRedirect(route('zona-horaria.edit'));
    }

    public function test_email_duplicado_falla_la_validacion(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $response = $this->post(route('registro'), $this->datosDeRegistro([
            'email' => 'existente@example.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
    }

    public function test_passwords_que_no_coinciden_fallan_la_validacion(): void
    {
        $response = $this->post(route('registro'), $this->datosDeRegistro([
            'email' => 'juan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'otraCosa456',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertNull(User::where('email', 'juan@example.com')->first());
    }

    public function test_el_nombre_del_comercio_se_muestra_en_el_panel_despues_de_loguearse(): void
    {
        $this->post(route('registro'), $this->datosDeRegistro([
            'nombre_comercio' => 'Almacén La Esquina',
            'name' => 'Pedro',
            'email' => 'pedro@example.com',
        ]));

        $user = User::where('email', 'pedro@example.com')->first();
        $comercio = Comercio::find($user->comercio_id);
        // La zona horaria se elige aparte (ver test de arriba) — acá solo
        // interesa que el nombre se muestre, así que la seteamos a mano
        // para no quedar redirigido a /zona-horaria antes de ver /panel.
        $comercio->timezone = 'America/Argentina/Buenos_Aires';
        $comercio->save();

        $response = $this->get(route('panel'));

        $response->assertOk();
        $response->assertSee('Almacén La Esquina');
    }
}
