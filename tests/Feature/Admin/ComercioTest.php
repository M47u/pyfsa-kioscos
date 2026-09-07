<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel admin de PyFsa (/admin/comercios, ver routes/web.php) — rutas
 * CENTRALES, no tenant-scoped. No hereda de TenantTestCase a propósito
 * (mismo motivo que WelcomeTest): no hace falta tenancy()->initialize()
 * de un comercio en particular para pegarle a estas rutas.
 *
 * No aplica acá la regresión de binding de tenant que sí tienen
 * ProductoTest/PagoTest (SubstituteBindings corriendo antes que
 * InitializeTenancyByAuthenticatedUser): Comercio usa CentralConnection
 * (ver vendor/stancl/tenancy), que fuerza SIEMPRE la conexión central sin
 * importar el orden de middlewares — y estas rutas ni siquiera pasan por
 * InitializeTenancyByAuthenticatedUser.
 */
class ComercioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Comercio::create() dispara CreateDatabase de verdad (ver
        // TenancyServiceProvider) — hay que borrar cada comercio creado acá
        // para no dejar bases de datos MySQL huérfanas entre corridas.
        Comercio::all()->each->delete();

        parent::tearDown();
    }

    public function test_usuario_sin_is_admin_no_puede_entrar_al_panel_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.comercios.index'));

        $response->assertForbidden();
    }

    public function test_usuario_admin_puede_ver_el_listado_de_comercios(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = Comercio::create();

        $response = $this->actingAs($admin)->get(route('admin.comercios.index'));

        $response->assertOk();
        $response->assertSee($comercio->id);
    }

    public function test_usuario_admin_puede_cambiar_el_estado_de_un_comercio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = Comercio::create();

        $response = $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            'estado_suscripcion' => Comercio::ESTADO_ACTIVA,
            'trial_termina_el' => null,
        ]);

        $response->assertRedirect(route('admin.comercios.index'));
        $this->assertSame(Comercio::ESTADO_ACTIVA, $comercio->fresh()->estado_suscripcion);
    }

    /**
     * Regresión del listado: la cantidad de usuarios se cuenta con una
     * query directa (User::where('comercio_id', ...)), no con una relación
     * Eloquent nueva.
     */
    public function test_el_listado_muestra_la_cantidad_de_usuarios_por_comercio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = Comercio::create();
        User::factory()->count(2)->create(['comercio_id' => $comercio->id]);

        $response = $this->actingAs($admin)->get(route('admin.comercios.index'));

        $response->assertOk();
        $response->assertSee('2');
    }
}
