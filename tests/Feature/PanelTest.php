<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;

class PanelTest extends TenantTestCase
{
    public function test_panel_responde_ok_autenticado(): void
    {
        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertOk();
        $response->assertSee('Bienvenido');
        $response->assertSee($this->comercio->id);
    }

    /**
     * Mismo criterio dueño-only que layouts/nav.blade.php: la tarjeta de
     * Usuarios en el panel no le sirve a un empleado (no gestiona
     * empleados), así que ni se muestra.
     */
    public function test_panel_muestra_tarjeta_de_usuarios_solo_al_dueno(): void
    {
        $this->actingAs($this->user)->get(route('panel'))->assertSee('Usuarios');

        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($empleado)->get(route('panel'))->assertDontSee('Usuarios');
    }

    public function test_panel_redirige_a_login_si_no_esta_autenticado(): void
    {
        tenancy()->end();

        $response = $this->get(route('panel'));

        $response->assertRedirect(route('login'));
    }
}
