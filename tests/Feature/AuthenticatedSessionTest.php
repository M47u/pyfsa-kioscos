<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    /**
     * /login es compartido por kiosqueros y por los admins de plataforma
     * (is_admin, ver EnsureUserIsAdmin) — una cuenta admin da acceso al
     * panel de TODOS los comercios, así que la fuerza bruta contra este
     * formulario no es un riesgo hipotético. LoginRequest::authenticate()
     * cuenta los intentos fallidos por email+IP y corta en 5 por minuto.
     *
     * El caso importante es el que se prueba acá: el sexto intento queda
     * bloqueado AUNQUE las credenciales sean correctas. Si solo se
     * bloquearan los intentos con contraseña equivocada, el límite no
     * frenaría nada — el atacante que acierta pasa igual.
     */
    public function test_el_sexto_intento_queda_bloqueado_aunque_la_password_sea_correcta(): void
    {
        User::factory()->create(['email' => 'victima@example.com']);

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->post('/login', [
                'email' => 'victima@example.com',
                'password' => "adivinanza-{$intento}",
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'victima@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * El contador se limpia al entrar bien (RateLimiter::clear()): sin
     * esto, alguien que se equivoca cuatro veces y después entra quedaría
     * arrastrando esos intentos y se bloquearía a sí mismo más tarde.
     *
     * 4 + 4 = 8 intentos fallidos en el mismo minuto: si el login exitoso
     * del medio no limpiara el contador, el segundo bloque habría
     * disparado el bloqueo antes de llegar al final.
     */
    public function test_un_login_exitoso_limpia_el_contador_de_intentos(): void
    {
        User::factory()->create(['email' => 'distraido@example.com']);

        foreach ([1, 2] as $bloque) {
            for ($intento = 1; $intento <= 4; $intento++) {
                $this->post('/login', [
                    'email' => 'distraido@example.com',
                    'password' => "mal-{$bloque}-{$intento}",
                ])->assertSessionHasErrors('email');
            }

            $this->post('/login', [
                'email' => 'distraido@example.com',
                'password' => 'password',
            ])->assertRedirect(route('panel'));

            $this->assertAuthenticated();

            $this->post(route('logout'));
        }
    }

    /**
     * El límite es por email + IP, no global: que alguien esté bombardeando
     * una cuenta no puede dejar afuera al resto de los usuarios (sería un
     * DoS trivial contra cualquier cuenta conocida).
     */
    public function test_el_limite_no_afecta_a_otro_email_desde_la_misma_ip(): void
    {
        User::factory()->create(['email' => 'bombardeado@example.com']);
        User::factory()->create(['email' => 'inocente@example.com']);

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post('/login', [
                'email' => 'bombardeado@example.com',
                'password' => "mal-{$intento}",
            ]);
        }

        $this->post('/login', [
            'email' => 'inocente@example.com',
            'password' => 'password',
        ])->assertRedirect(route('panel'));

        $this->assertAuthenticated();
    }

    /**
     * El bloqueo dispara Lockout — es el hook que deja la puerta abierta a
     * notificar/alertar más adelante sin tocar LoginRequest.
     */
    public function test_el_bloqueo_dispara_el_evento_lockout(): void
    {
        Event::fake([Lockout::class]);

        User::factory()->create(['email' => 'lockout@example.com']);

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post('/login', [
                'email' => 'lockout@example.com',
                'password' => "mal-{$intento}",
            ]);
        }

        Event::assertDispatched(Lockout::class);
    }
}
