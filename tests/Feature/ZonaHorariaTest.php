<?php

declare(strict_types=1);

namespace Tests\Feature;

class ZonaHorariaTest extends TenantTestCase
{
    /**
     * Regresión del gate: un comercio SIN zona horaria configurada no
     * puede entrar a ninguna pantalla del panel, va derecho a elegirla.
     * TenantTestCase::setUp() le pone una por default a todos los demás
     * tests — acá se la sacamos a propósito para probar justo este caso.
     */
    public function test_sin_zona_horaria_configurada_redirige_a_elegirla(): void
    {
        $this->comercio->timezone = null;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertRedirect(route('zona-horaria.edit'));
    }

    public function test_con_zona_horaria_configurada_no_redirige(): void
    {
        $response = $this->actingAs($this->user)->get(route('panel'));

        $response->assertOk();
    }

    public function test_configurar_zona_horaria_la_guarda_y_redirige_al_panel(): void
    {
        $this->comercio->timezone = null;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->post(route('zona-horaria.update'), [
            'timezone' => 'America/Asuncion',
        ]);

        $response->assertRedirect(route('panel'));
        $this->assertSame('America/Asuncion', $this->comercio->fresh()->timezone);

        // Regresión: panel.blade.php no tenía <x-status-banner />, así que
        // este mensaje se perdía en el aire (mismo bug que ya se había
        // evitado en productos/clientes al usar el componente desde el
        // principio). El flash de sesión sigue disponible en el próximo
        // request, como cualquier redirect()->with('status', ...) normal.
        $this->get(route('panel'))->assertSee('Zona horaria configurada correctamente');
    }

    /**
     * Rule::in(array_keys(Comercio::ZONAS_HORARIAS)) — no se acepta
     * cualquier string de zona horaria, solo Argentina o Paraguay.
     */
    public function test_configurar_zona_horaria_rechaza_un_valor_fuera_de_las_opciones(): void
    {
        $this->comercio->timezone = null;
        $this->comercio->save();

        $response = $this->actingAs($this->user)->post(route('zona-horaria.update'), [
            'timezone' => 'Europe/Madrid',
        ]);

        $response->assertSessionHasErrors('timezone');
        $this->assertNull($this->comercio->fresh()->timezone);
    }

    /**
     * Regresión de la aplicación real del huso horario: no alcanza con
     * que quede guardado en el comercio, PHP tiene que empezar a
     * responder en esa zona horaria en el siguiente request (ver
     * InitializeTenancyByAuthenticatedUser::aplicarZonaHoraria()).
     */
    public function test_configurar_asuncion_hace_que_now_use_ese_huso_en_el_siguiente_request(): void
    {
        $this->actingAs($this->user)->post(route('zona-horaria.update'), [
            'timezone' => 'America/Asuncion',
        ]);

        $this->actingAs($this->user)->get(route('panel'));

        $this->assertSame('America/Asuncion', date_default_timezone_get());
    }
}
