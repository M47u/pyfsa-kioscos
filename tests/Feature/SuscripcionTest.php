<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;

/**
 * Gate del lado del comercio (módulo 3.6 "Suscripciones" del documento de
 * alcance, ver App\Http\Middleware\EnsureComercioSuscripcionActiva).
 * Mismo patrón que ZonaHorariaTest para el otro gate del grupo de
 * routes/tenant.php: TenantTestCase::setUp() deja el comercio en 'prueba'
 * por default, acá se lo pisamos a propósito en cada test.
 */
class SuscripcionTest extends TenantTestCase
{
    public function test_comercio_vencida_redirige_a_suscripcion_vencida(): void
    {
        $this->comercio->estado_suscripcion = Comercio::ESTADO_VENCIDA;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertRedirect(route('suscripcion-vencida'));
    }

    public function test_comercio_cancelada_redirige_a_suscripcion_vencida(): void
    {
        $this->comercio->estado_suscripcion = Comercio::ESTADO_CANCELADA;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertRedirect(route('suscripcion-vencida'));
    }

    public function test_comercio_en_prueba_no_redirige(): void
    {
        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertOk();
    }

    public function test_comercio_activa_no_redirige(): void
    {
        $this->comercio->estado_suscripcion = Comercio::ESTADO_ACTIVA;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertOk();
    }

    /**
     * Sin el chequeo de routeIs('suscripcion-vencida') en el middleware,
     * un comercio vencido nunca podría ver la pantalla que le explica por
     * qué está bloqueado: el redirect a esa misma ruta volvería a disparar
     * el gate en el siguiente request, en loop.
     */
    public function test_sin_loop_de_redirect_si_ya_esta_en_suscripcion_vencida(): void
    {
        $this->comercio->estado_suscripcion = Comercio::ESTADO_VENCIDA;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->get(route('suscripcion-vencida'));

        $response->assertOk();
    }
}
