<?php

declare(strict_types=1);

namespace Tests\Feature;

class ClienteTest extends TenantTestCase
{
    public function test_alta_de_cliente(): void
    {
        $response = $this->actingAs($this->user)->post(route('clientes.store'), [
            'nombre' => 'Juan Pérez',
            'telefono' => '3705000000',
            'limite_credito' => 5000,
        ]);

        $response->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Juan Pérez',
        ]);
    }

    /**
     * Regresión: `limite_credito` es NOT NULL en la DB (default 0), pero la
     * regla de validación es 'nullable'. Si el campo llega vacío,
     * ConvertEmptyStringsToNull lo vuelve null, y sin prepareForValidation()
     * normalizándolo a 0 el insert explota con un error de DB en vez de
     * guardar el default de negocio.
     */
    public function test_alta_de_cliente_con_limite_credito_vacio_usa_default_cero(): void
    {
        $response = $this->actingAs($this->user)->post(route('clientes.store'), [
            'nombre' => 'María Gómez',
            'telefono' => null,
            'limite_credito' => '',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'María Gómez',
            'limite_credito' => 0,
        ]);
    }
}
