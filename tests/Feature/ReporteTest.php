<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * La semana de referencia para todos estos tests está CONGELADA con
 * Carbon::setTestNow() en vez de usar "hoy" real: el reporte agrupa por
 * semana (lunes a domingo, ver ReporteController::ventasDeLaSemana()) y
 * necesitamos fechas de lunes/martes/etc. estables para poder armar el
 * escenario del "día pico" sin que el test sea sensible al día real en que
 * se corre la suite.
 *
 * 2026-06-08 es lunes, 2026-06-10 es miércoles (mitad de la semana y mitad
 * del día, lejos de cualquier borde de medianoche/huso horario).
 */
class ReporteTest extends TenantTestCase
{
    private const LUNES = '2026-06-08 10:00:00';

    private const MARTES = '2026-06-09 10:00:00';

    private const MIERCOLES = '2026-06-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::MIERCOLES));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearProducto(string $nombre, int $precioVenta, int $stockMinimo = 5): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre,
            'codigo_barras' => null,
            'precio_costo' => (int) ($precioVenta * 0.7),
            'precio_venta' => $precioVenta,
            'stock_minimo' => $stockMinimo,
        ]);

        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 1000,
            'user_id' => $this->user->id,
        ]);

        return $producto;
    }

    /**
     * Crea una venta fechada a mano (Venta::create() usaría "ahora" para
     * created_at; acá lo pisamos con una query directa después de crearla,
     * sin pasar por el evento de timestamps de Eloquent).
     */
    private function crearVentaEnFecha(string $fecha, int $total, ?Cliente $cliente = null, string $medioPago = Venta::MEDIO_PAGO_EFECTIVO): Venta
    {
        $venta = Venta::create([
            'cliente_id' => $cliente?->id,
            'user_id' => $this->user->id,
            'medio_pago' => $medioPago,
            'total' => $total,
        ]);

        Venta::where('id', $venta->id)->update(['created_at' => $fecha]);

        return $venta->fresh();
    }

    public function test_totales_de_hoy_y_de_la_semana_con_dia_pico(): void
    {
        $this->crearVentaEnFecha(self::LUNES, 500);
        $this->crearVentaEnFecha(self::MARTES, 5000); // día pico
        $this->crearVentaEnFecha(self::MIERCOLES, 700); // "hoy"

        // Fuera de la semana actual (semana anterior): no debe contarse.
        $this->crearVentaEnFecha('2026-06-01 10:00:00', 99999);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('totalHoy', 700.0);
        $response->assertViewHas('totalSemana', 6200.0);
        $response->assertViewHas('diaPico', 'Martes');
    }

    public function test_producto_mas_vendido_de_la_semana_es_por_cantidad_no_por_facturacion(): void
    {
        // A: 2 unidades a 5000 c/u = 10000 de facturación, pero solo 2 de cantidad.
        $productoA = $this->crearProducto('Notebook', 5000);
        // B: 50 unidades a 100 c/u = 5000 de facturación, pero 50 de cantidad (gana).
        $productoB = $this->crearProducto('Chicle', 100);

        $ventaA = $this->crearVentaEnFecha(self::MARTES, 10000);
        $ventaA->items()->create(['producto_id' => $productoA->id, 'cantidad' => 2, 'precio_unitario' => 5000]);

        $ventaB = $this->crearVentaEnFecha(self::MIERCOLES, 5000);
        $ventaB->items()->create(['producto_id' => $productoB->id, 'cantidad' => 50, 'precio_unitario' => 100]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('productoMasVendido', fn (Producto $producto) => $producto->id === $productoB->id);
        $response->assertViewHas('cantidadMasVendida', 50);
    }

    public function test_cuentas_por_cobrar_total_y_ranking_de_deudores(): void
    {
        // A: saldo 3000, límite 2000 -> supera.
        $clienteA = Cliente::create(['nombre' => 'Cliente A', 'telefono' => null, 'limite_credito' => 2000]);
        $this->crearVentaEnFecha(self::LUNES, 5000, $clienteA, Venta::MEDIO_PAGO_FIADO);
        $clienteA->pagos()->create(['monto' => 2000, 'user_id' => $this->user->id]);

        // B: saldo 800, límite 5000 -> no supera.
        $clienteB = Cliente::create(['nombre' => 'Cliente B', 'telefono' => null, 'limite_credito' => 5000]);
        $this->crearVentaEnFecha(self::MARTES, 1000, $clienteB, Venta::MEDIO_PAGO_FIADO);
        $clienteB->pagos()->create(['monto' => 200, 'user_id' => $this->user->id]);

        // C: saldo 500, límite 100 -> supera, pero saldo menor que A y B.
        $clienteC = Cliente::create(['nombre' => 'Cliente C', 'telefono' => null, 'limite_credito' => 100]);
        $this->crearVentaEnFecha(self::MIERCOLES, 10000, $clienteC, Venta::MEDIO_PAGO_FIADO);
        $clienteC->pagos()->create(['monto' => 9500, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('totalPorCobrar', 4300.0);

        $response->assertViewHas('rankingDeudores', function ($ranking) use ($clienteA, $clienteB, $clienteC) {
            $ids = $ranking->pluck('cliente.id')->values()->all();

            return $ids === [$clienteA->id, $clienteB->id, $clienteC->id]
                && $ranking[0]['saldo'] === 3000.0 && $ranking[0]['supera_limite'] === true
                && $ranking[1]['saldo'] === 800.0 && $ranking[1]['supera_limite'] === false
                && $ranking[2]['saldo'] === 500.0 && $ranking[2]['supera_limite'] === true;
        });
    }

    public function test_resumen_de_stock_bajo_minimo_cuenta_solo_los_productos_por_debajo(): void
    {
        $sobreMinimo = $this->crearProducto('Con stock', 100, stockMinimo: 5);
        // El helper crearProducto ya repone 1000 de stock, por eso "sobre mínimo".

        $bajoMinimo1 = Producto::create([
            'nombre' => 'Bajo minimo 1', 'codigo_barras' => null,
            'precio_costo' => 50, 'precio_venta' => 100, 'stock_minimo' => 5,
        ]);
        $bajoMinimo1->movimientos()->create(['tipo' => MovimientoStock::TIPO_REPOSICION, 'cantidad' => 2, 'user_id' => $this->user->id]);

        $bajoMinimo2 = Producto::create([
            'nombre' => 'Bajo minimo 2', 'codigo_barras' => null,
            'precio_costo' => 50, 'precio_venta' => 100, 'stock_minimo' => 3,
        ]);
        // Sin movimientos: stock actual 0, bajo el mínimo de 3.

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('productosBajoMinimo', 2);
    }
}
