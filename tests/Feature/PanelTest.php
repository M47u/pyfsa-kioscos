<?php

declare(strict_types=1);

namespace Tests\Feature;

class PanelTest extends TenantTestCase
{
    public function test_panel_responde_ok_autenticado(): void
    {
        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertOk();
        $response->assertSee('Bienvenido');
        $response->assertSee($this->comercio->id);
    }

    public function test_panel_redirige_a_login_si_no_esta_autenticado(): void
    {
        tenancy()->end();

        $response = $this->get(route('panel'));

        $response->assertRedirect(route('login'));
    }
}
