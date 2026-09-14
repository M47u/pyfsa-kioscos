<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Concerns\ProvisionaTenantConBaseReal;
use Tests\TestCase;

class AuthenticatedSessionTest extends TestCase
{
    use ProvisionaTenantConBaseReal;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Comercio::all()->each->delete();
        $this->borrarComerciosConBaseReal();

        parent::tearDown();
    }

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

    /**
     * El otro camino que también decidía "el home" a mano: el middleware
     * 'guest' rebotando a alguien ya logueado que entra a /login. Sin esto
     * mandaba a un admin sin comercio directo al 403 de /panel.
     */
    public function test_admin_sin_comercio_que_entra_a_login_es_redirigido_al_panel_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'comercio_id' => null]);

        $this->actingAs($admin)->get(route('login'))
            ->assertRedirect(route('admin.comercios.index'));
    }

    /**
     * Bug real: /panel vive en routes/tenant.php, detrás de
     * InitializeTenancyByAuthenticatedUser, que hace abort 403 si el
     * usuario no tiene comercio_id. Un admin de plataforma provisionado con
     * `admin:crear` NO tiene comercio (es el caso normal), así que se
     * logueaba bien y se estrellaba contra un 403 sin llegar a ningún lado
     * usable. Ahora aterriza en el panel admin, que es lo único que ese
     * usuario puede usar.
     */
    public function test_admin_sin_comercio_aterriza_en_el_panel_admin_al_loguearse(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-sin-comercio@pyfsa.test',
            'is_admin' => true,
            'comercio_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'admin-sin-comercio@pyfsa.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.comercios.index'));

        $this->assertAuthenticatedAs($admin);

        // Y el destino es de verdad usable, no otro 403 encadenado.
        $this->get(route('admin.comercios.index'))->assertOk();
    }

    /**
     * Regresión del caso legítimo de al lado: un admin de PyFsa que ADEMÁS
     * es dueño de su propio comercio (explícitamente soportado por
     * admin:crear) no cambia de comportamiento — sigue entrando a /panel
     * como cualquier usuario, y al panel admin llega por el nav.
     *
     * Acá no hace falta un comercio real: el redirect del login se decide
     * solo con `comercio_id`/`is_admin`, sin tocar la base del tenant. Se
     * verifica el destino, no que /panel cargue (eso ya lo cubre
     * PanelTest, con un tenant real).
     */
    public function test_admin_con_comercio_sigue_yendo_al_panel_del_comercio(): void
    {
        $comercio = $this->crearComercioConBaseReal();

        User::factory()->create([
            'email' => 'admin-con-comercio@pyfsa.test',
            'is_admin' => true,
            'comercio_id' => $comercio->id,
        ]);

        $this->post('/login', [
            'email' => 'admin-con-comercio@pyfsa.test',
            'password' => 'password',
        ])->assertRedirect(route('panel'));
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
     * El hueco que el límite por email+IP NO cubría: credential stuffing.
     * Un atacante desde UNA sola IP probando una contraseña contra miles de
     * emails distintos nunca chocaba con el límite de LoginRequest, porque
     * cada email arranca su propio contador en cero. El limiter con nombre
     * 'login-por-ip' (ver AppServiceProvider) cuenta por IP sola.
     *
     * Los emails de este test son todos DISTINTOS a propósito: si
     * colisionaran con el throttle de email+IP, el 429 podría venir del
     * límite viejo y el test no probaría nada nuevo.
     */
    public function test_veintiun_intentos_desde_la_misma_ip_con_emails_distintos_devuelven_429(): void
    {
        for ($intento = 1; $intento <= 20; $intento++) {
            $this->post('/login', [
                'email' => "objetivo-{$intento}@example.com",
                'password' => 'password',
            ])->assertStatus(302);
        }

        $this->post('/login', [
            'email' => 'objetivo-21@example.com',
            'password' => 'password',
        ])->assertStatus(429);
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
