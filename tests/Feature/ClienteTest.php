<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Venta;

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

    /**
     * Cubre la vista de cuenta corriente: historial mezclado de ventas
     * fiadas y pagos (ver ClienteController::show), y el aviso visual de
     * límite superado.
     */
    public function test_show_de_cliente_muestra_saldo_y_alerta_de_limite_superado(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 1000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 1500]);

        $response = $this->actingAs($this->user)->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('Juan Pérez');
        $response->assertSee('superó su límite de crédito');
    }

    /**
     * Regresión de markup: el botón de "Registrar pago" no debe volver a
     * ser type="submit" directo, y el modal de confirmación (con sus
     * botones de confirmar/cancelar) tiene que estar en la página — es lo
     * único que impide mandar un pago sin confirmar (ver
     * resources/views/components/confirm-dialog.blade.php).
     */
    public function test_show_de_cliente_incluye_modal_de_confirmacion_de_pago(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 1000]);

        $response = $this->actingAs($this->user)->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('id="abrir-confirmar-pago"', false);
        $response->assertSee('id="confirmar-pago-dialog"', false);
        $response->assertSee('id="confirmar-pago-dialog-confirmar"', false);
    }
}
