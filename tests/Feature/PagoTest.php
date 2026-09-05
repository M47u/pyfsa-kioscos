<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;

class PagoTest extends TenantTestCase
{
    private function crearProducto(int $precioVenta = 1200): Producto
    {
        $producto = Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => $precioVenta,
            'stock_minimo' => 5,
        ]);

        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 100,
            'user_id' => $this->user->id,
        ]);

        return $producto;
    }

    public function test_registrar_pago_reduce_el_saldo(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 1000]);

        $response = $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), [
            'monto' => 400,
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));

        $this->assertDatabaseHas('pagos', [
            'cliente_id' => $cliente->id,
            'monto' => 400.00,
            'user_id' => $this->user->id,
        ]);

        $this->assertSame(600.0, $cliente->fresh()->saldo());
    }

    /**
     * Un pago parcial no salda toda la deuda: el saldo queda pendiente por
     * la diferencia.
     */
    public function test_pago_parcial_deja_saldo_pendiente(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 2000]);

        $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), ['monto' => 500]);
        $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), ['monto' => 300]);

        $this->assertSame(1200.0, $cliente->fresh()->saldo());
    }

    public function test_pago_total_deja_saldo_en_cero(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 1500]);

        $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), ['monto' => 1500]);

        $this->assertSame(0.0, $cliente->fresh()->saldo());
    }

    public function test_pago_de_monto_cero_falla_validacion(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $response = $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), ['monto' => 0]);

        $response->assertSessionHasErrors('monto');
        $this->assertDatabaseCount('pagos', 0);
    }

    /**
     * pagos.monto es decimal(10,2), tope 99999999.99. Sin un max acá, un
     * monto de 9 dígitos pasaba la validación y explotaba como un error
     * crudo de MySQL al insertar en vez de un mensaje de validación claro.
     */
    public function test_pago_que_desborda_la_columna_monto_falla_validacion(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $response = $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), [
            'monto' => 100000000,
        ]);

        $response->assertSessionHasErrors('monto');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_supera_limite_segun_saldo_y_limite_credito(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 1000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 800]);

        $this->assertFalse($cliente->fresh()->superaLimite(), 'Saldo por debajo del limite: no deberia superarlo.');

        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 500]);

        $this->assertTrue($cliente->fresh()->superaLimite(), 'Saldo por encima del limite: deberia superarlo.');
    }

    /**
     * Decisión confirmada por el usuario: a diferencia del stock, el límite
     * de crédito de fiado NO bloquea la venta — solo advierte. La venta se
     * registra igual y queda una advertencia flash para el kiosquero.
     */
    public function test_venta_fiado_que_supera_el_limite_se_registra_igual_y_deja_advertencia(): void
    {
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 1000]);

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'fiado',
            'cliente_id' => $cliente->id,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2], // 2400, supera el limite de 1000
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));
        $response->assertSessionHas('advertencia');

        $this->assertSame(1, Venta::count());
        $this->assertTrue($cliente->fresh()->superaLimite());
    }

    /**
     * Regresión del gotcha de middleware documentado en CLAUDE.md: el
     * binding implícito de {cliente} en esta ruta nueva debe resolver
     * contra la base del TENANT, no la central, aun sin la tenancy
     * pre-inicializada por TenantTestCase::setUp() (ver el mismo patrón en
     * ProductoTest::test_update_producto_resuelve_binding_de_tenant_sin_tenancy_pre_inicializada).
     */
    public function test_registrar_pago_resuelve_binding_de_tenant_sin_tenancy_pre_inicializada(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        tenancy()->end();

        $response = $this->actingAs($this->user)->post(route('clientes.pagos.store', $cliente), [
            'monto' => 100,
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));

        tenancy()->initialize($this->comercio);

        $this->assertDatabaseHas('pagos', [
            'cliente_id' => $cliente->id,
            'monto' => 100.00,
        ]);
    }
}
