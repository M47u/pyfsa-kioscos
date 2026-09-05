<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use PDO;

class VentaTest extends TenantTestCase
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

    public function test_venta_en_efectivo_no_requiere_cliente_y_resta_stock(): void
    {
        $producto = $this->crearProducto();

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));

        $this->assertDatabaseHas('ventas', [
            'medio_pago' => 'efectivo',
            'cliente_id' => null,
            'total' => 3600.00,
        ]);

        $this->assertSame(17, $producto->fresh()->stockActual());

        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $producto->id,
            'tipo' => MovimientoStock::TIPO_VENTA,
            'cantidad' => -3,
        ]);

        $this->assertDatabaseHas('items_venta', [
            'producto_id' => $producto->id,
            'cantidad' => 3,
            'precio_unitario' => 1200.00,
        ]);
    }

    public function test_venta_fiado_requiere_cliente_y_actualiza_su_saldo(): void
    {
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'fiado',
            'cliente_id' => $cliente->id,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));

        $this->assertDatabaseHas('ventas', [
            'medio_pago' => 'fiado',
            'cliente_id' => $cliente->id,
            'total' => 2400.00,
        ]);

        $this->assertSame(2400.0, $cliente->fresh()->saldo());
        $this->assertSame(18, $producto->fresh()->stockActual());
    }

    public function test_venta_fiado_sin_cliente_falla_validacion(): void
    {
        $producto = $this->crearProducto();

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'fiado',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('cliente_id');
        $this->assertSame(0, Venta::count());
    }

    public function test_venta_con_medio_de_pago_invalido_falla_validacion(): void
    {
        $producto = $this->crearProducto();

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'bitcoin',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('medio_pago');
        $this->assertSame(0, Venta::count());
    }

    /**
     * Corrección confirmada por el usuario: la venta SE BLOQUEA si dejaría
     * el stock de algún producto negativo — no se crea nada (ni Venta, ni
     * ItemVenta, ni MovimientoStock), todo o nada.
     */
    public function test_venta_que_pide_mas_stock_del_disponible_se_rechaza_y_no_crea_nada(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 21],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Venta::count());
        $this->assertDatabaseCount('items_venta', 0);
        $this->assertDatabaseMissing('movimientos_stock', [
            'producto_id' => $producto->id,
            'tipo' => MovimientoStock::TIPO_VENTA,
        ]);
        $this->assertSame(20, $producto->fresh()->stockActual());
    }

    public function test_venta_que_pide_exactamente_el_stock_disponible_se_acepta(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 20],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));
        $this->assertSame(1, Venta::count());
        $this->assertSame(0, $producto->fresh()->stockActual());
    }

    /**
     * Caso real: el mismo producto aparece en más de una fila del carrito
     * (por ejemplo, escaneado dos veces en vez de sumar la cantidad a
     * mano). Hay que sumar las cantidades de todas las filas y validar el
     * total contra el stock, no cada fila por separado.
     */
    public function test_carrito_con_producto_repetido_suma_cantidades_para_validar_stock(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 15],
                ['producto_id' => $producto->id, 'cantidad' => 10],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Venta::count());
        $this->assertDatabaseCount('items_venta', 0);
        $this->assertSame(20, $producto->fresh()->stockActual());
    }

    /**
     * Prueba de concurrencia real (confirmado con el usuario que vale la
     * pena si sale limpio, ver code review): abre una SEGUNDA conexión
     * MySQL real (su propia sesión, no la de Eloquent) a la misma base del
     * tenant, y desde ahí toma un lockForUpdate() sobre el producto SIN
     * confirmar — simulando una segunda caja ya "adentro" de su
     * transacción. Si VentaController::store no tomara su propio
     * lockForUpdate(), la venta pasaría sin esperar nada. Como sí lo toma,
     * la query queda bloqueada por la fila hasta que expira el
     * innodb_lock_wait_timeout (bajado a 1s para el test) y MySQL corta la
     * espera con un error — la prueba de que el lock real está pasando.
     */
    public function test_lockForUpdate_hace_esperar_a_una_venta_mientras_otra_conexion_sostiene_el_lock(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $config = config('database.connections.tenant');

        $segundaConexion = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password']
        );
        $segundaConexion->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $segundaConexion->beginTransaction();
        $segundaConexion->query("SELECT id FROM productos WHERE id = {$producto->id} FOR UPDATE");

        // La conexión de la app también necesita un timeout corto: si no,
        // el test esperaría los 50s por defecto de MySQL antes de fallar.
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->withoutExceptionHandling();

        try {
            $this->expectExceptionMessageMatches('/Lock wait timeout/i');

            $this->actingAs($this->user)->post(route('ventas.store'), [
                'medio_pago' => 'efectivo',
                'items' => [
                    ['producto_id' => $producto->id, 'cantidad' => 1],
                ],
            ]);
        } finally {
            $segundaConexion->rollBack();
        }
    }
}
