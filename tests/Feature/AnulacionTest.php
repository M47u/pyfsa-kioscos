<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Anular una Venta o un Pago (documento de alcance — corrección de error
 * humano: no hay forma de arreglar una venta o un pago mal cargados más
 * que anularlos). Nunca se borra ni se edita nada — ver
 * VentaController::anular y ClienteController::anularPago.
 */
class AnulacionTest extends TenantTestCase
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
            'cantidad' => 20,
            'user_id' => $this->user->id,
        ]);

        return $producto;
    }

    private function crearEmpleado(): User
    {
        return User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);
    }

    public function test_anular_venta_efectivo_revierte_stock_y_marca_anulada(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ]);

        $venta = Venta::sole();
        $this->assertSame(17, $producto->fresh()->stockActual());

        $response = $this->actingAs($this->user)->post(route('ventas.anular', $venta), [
            'motivo_anulacion' => 'Cargada por error',
        ]);

        $response->assertRedirect(route('ventas.index'));

        $venta->refresh();
        $this->assertNotNull($venta->anulada_en);
        $this->assertSame($this->user->id, $venta->anulada_por);
        $this->assertSame('Cargada por error', $venta->motivo_anulacion);
        $this->assertTrue($venta->estaAnulada());

        // El stock vuelve al valor de antes de la venta (20), no queda en
        // 17: la reversa es exacta, no un movimiento arbitrario.
        $this->assertSame(20, $producto->fresh()->stockActual());

        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $producto->id,
            'tipo' => MovimientoStock::TIPO_ANULACION_VENTA,
            'cantidad' => 3,
        ]);
    }

    public function test_anular_venta_fiado_deja_de_contar_en_saldo_del_cliente(): void
    {
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'fiado',
            'cliente_id' => $cliente->id,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2],
            ],
        ]);

        $venta = Venta::sole();
        $this->assertSame(2400.0, $cliente->fresh()->saldo());

        $this->actingAs($this->user)->post(route('ventas.anular', $venta));

        $this->assertSame(0.0, $cliente->fresh()->saldo());
        $this->assertSame(20, $producto->fresh()->stockActual());
    }

    public function test_no_se_puede_anular_dos_veces_la_misma_venta(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ]);

        $venta = Venta::sole();

        $this->actingAs($this->user)->post(route('ventas.anular', $venta))
            ->assertRedirect(route('ventas.index'));

        $this->assertSame(20, $producto->fresh()->stockActual());

        // Segundo intento: falla (ValidationException -> redirect back con
        // error) y NO duplica el movimiento de reversa.
        $this->actingAs($this->user)->post(route('ventas.anular', $venta))
            ->assertSessionHasErrors('venta');

        $this->assertSame(20, $producto->fresh()->stockActual());
        $this->assertDatabaseCount('movimientos_stock', 3); // reposición inicial + venta + 1 sola reversa
    }

    public function test_empleado_no_puede_anular_venta_ni_pago(): void
    {
        $empleado = $this->crearEmpleado();
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'fiado',
            'cliente_id' => $cliente->id,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1],
            ],
        ]);
        $venta = Venta::sole();

        $pago = $cliente->pagos()->create(['monto' => 100, 'user_id' => $this->user->id]);

        $this->actingAs($empleado)->post(route('ventas.anular', $venta))->assertForbidden();
        $this->actingAs($empleado)->post(route('clientes.pagos.anular', $pago))->assertForbidden();

        $this->assertNull($venta->fresh()->anulada_en);
        $this->assertNull($pago->fresh()->anulado_en);
    }

    public function test_anular_pago_hace_que_saldo_del_cliente_vuelva_a_sumarlo(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 1000]);

        $pago = $cliente->pagos()->create(['monto' => 400, 'user_id' => $this->user->id]);

        $this->assertSame(600.0, $cliente->fresh()->saldo());

        $response = $this->actingAs($this->user)->post(route('clientes.pagos.anular', $pago), [
            'motivo_anulacion' => 'Monto mal tipeado',
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));

        $pago->refresh();
        $this->assertNotNull($pago->anulado_en);
        $this->assertSame($this->user->id, $pago->anulado_por);
        $this->assertSame('Monto mal tipeado', $pago->motivo_anulacion);
        $this->assertTrue($pago->estaAnulado());

        // El pago anulado deja de restar: el saldo vuelve a ser como si el
        // pago nunca hubiera existido.
        $this->assertSame(1000.0, $cliente->fresh()->saldo());
    }

    public function test_reportes_de_ventas_del_dia_y_tramo_no_cuentan_una_venta_anulada(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00')); // día 15, tramo 11-20

        $producto = $this->crearProducto();

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2],
            ],
        ]);
        $venta = Venta::sole();

        $responseAntes = $this->actingAs($this->user)->get(route('reportes.index'));
        $responseAntes->assertViewHas('totalHoy', 2400.0);
        $responseAntes->assertViewHas('tendenciaPorTramo', fn ($t) => $t[2]['total'] === 2400.0);

        $this->actingAs($this->user)->post(route('ventas.anular', $venta));

        $responseDespues = $this->actingAs($this->user)->get(route('reportes.index'));
        $responseDespues->assertViewHas('totalHoy', 0.0);
        $responseDespues->assertViewHas('totalSemana', 0.0);
        $responseDespues->assertViewHas('productoMasVendido', fn ($producto) => $producto === null);
        $responseDespues->assertViewHas('tendenciaPorTramo', fn ($t) => $t[2]['total'] === 0.0 && $t[2]['productos']->isEmpty());

        Carbon::setTestNow();
    }

    public function test_totalporcobrar_y_ranking_deudores_no_cuentan_pago_anulado(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 1000]);
        $pago = $cliente->pagos()->create(['monto' => 400, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->post(route('clientes.pagos.anular', $pago));

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertViewHas('totalPorCobrar', 1000.0);
        $response->assertViewHas('rankingDeudores', function ($ranking) use ($cliente) {
            $fila = $ranking->firstWhere('cliente.id', $cliente->id);

            return $fila !== null && $fila['saldo'] === 1000.0;
        });
    }
}
