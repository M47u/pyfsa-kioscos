<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regresión: el middleware 'guest' (Illuminate\Auth\Middleware\
     * RedirectIfAuthenticated) redirige a un usuario ya logueado que entra
     * a /login — por default busca una ruta llamada 'dashboard' o 'home',
     * ninguna existe en este proyecto (la nuestra se llama 'panel'), y sin
     * AppServiceProvider::boot() configurando redirectUsing() caía al
     * último fallback de Laravel: '/' (la bienvenida default). Reportado
     * en vivo: "vuelvo a /login logueado y me muestra la de bienvenida".
     */
    public function test_usuario_autenticado_que_entra_a_login_es_redirigido_al_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('panel'));
    }

    public function test_usuario_no_autenticado_puede_ver_login(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }
}
