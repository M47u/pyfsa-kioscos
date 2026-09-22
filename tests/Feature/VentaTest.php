<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use PDO;

class VentaTest extends TenantTestCase
{
    private function crearProducto(int $precioVenta = 1200, bool $controlaStock = true): Producto
    {
        $producto = Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => $precioVenta,
            'stock_minimo' => 5,
            'controla_stock' => $controlaStock,
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

    /**
     * Offline (ver CLAUDE.md, arquitectura offline): una venta que llega con
     * uuid_dispositivo se crea normal, igual que una venta online sin uuid.
     */
    public function test_venta_con_uuid_dispositivo_nuevo_se_crea_normal(): void
    {
        $producto = $this->crearProducto();

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'uuid_dispositivo' => '11111111-1111-4111-8111-111111111111',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));
        $this->assertDatabaseHas('ventas', [
            'uuid_dispositivo' => '11111111-1111-4111-8111-111111111111',
            'sincronizada_con_stock_insuficiente' => false,
        ]);
        $this->assertSame(1, Venta::count());
    }

    /**
     * Idempotencia (ver CLAUDE.md, arquitectura offline): reintentar la
     * MISMA venta (mismo uuid_dispositivo) — red flaky, doble intento de
     * sync — no crea una segunda Venta. Responde como éxito igual, no como
     * error, porque no es un fallo: es un sync repetido.
     */
    public function test_venta_con_mismo_uuid_dispositivo_no_duplica_y_responde_exito(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20
        $uuid = '22222222-2222-4222-8222-222222222222';

        $payload = [
            'medio_pago' => 'efectivo',
            'uuid_dispositivo' => $uuid,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ];

        $primeraRespuesta = $this->actingAs($this->user)->post(route('ventas.store'), $payload);
        $segundaRespuesta = $this->actingAs($this->user)->post(route('ventas.store'), $payload);

        $primeraRespuesta->assertRedirect(route('ventas.index'));
        $segundaRespuesta->assertRedirect(route('ventas.index'));
        $segundaRespuesta->assertSessionHasNoErrors();

        $this->assertSame(1, Venta::where('uuid_dispositivo', $uuid)->count());
        $this->assertSame(17, $producto->fresh()->stockActual());
    }

    /**
     * Decisión de negocio confirmada (ver CLAUDE.md, arquitectura offline):
     * una venta offline no pudo validar el stock contra el servidor en el
     * momento real (el cliente ya se fue con el producto en mano) — se
     * crea igual aunque deje stock negativo, marcada
     * sincronizada_con_stock_insuficiente, a diferencia de una venta online
     * equivalente (sin uuid_dispositivo, ver el test de regresión de abajo).
     */
    public function test_venta_offline_que_deja_stock_negativo_se_crea_marcada(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'uuid_dispositivo' => '33333333-3333-4333-8333-333333333333',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 25],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, Venta::count());
        $this->assertDatabaseHas('ventas', [
            'uuid_dispositivo' => '33333333-3333-4333-8333-333333333333',
            'sincronizada_con_stock_insuficiente' => true,
        ]);
        $this->assertSame(-5, $producto->fresh()->stockActual());
    }

    /**
     * Regresión: una venta SIN uuid_dispositivo (venta online normal) sigue
     * bloqueada si deja stock negativo — el comportamiento existente
     * (test_venta_que_pide_mas_stock_del_disponible_se_rechaza_y_no_crea_nada)
     * no cambia en nada por la existencia del flujo offline.
     */
    public function test_venta_sin_uuid_dispositivo_que_deja_stock_negativo_sigue_bloqueada(): void
    {
        $producto = $this->crearProducto(); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 25],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Venta::count());
        $this->assertSame(20, $producto->fresh()->stockActual());
    }

    /**
     * Bug real encontrado (POS/UX): ventas/create.blade.php buscaba
     * productos contra productos.index (dueño-only) — un empleado real
     * recibía 403 al intentar buscar un producto para vender, aunque
     * seguía pudiendo vender vía ventas.store directo (por eso ningún test
     * viejo lo detectaba). productos.buscar es la ruta compartida nueva;
     * productos.index sigue dueño-only sin cambios.
     */
    public function test_empleado_puede_buscar_productos_para_vender_pero_no_administrar_el_catalogo(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);
        $this->crearProducto();

        $this->actingAs($empleado)->getJson(route('productos.buscar', ['buscar' => 'Coca']))
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Coca Cola 1.5L']);

        $this->actingAs($empleado)->getJson(route('productos.catalogo'))
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Coca Cola 1.5L']);

        $this->actingAs($empleado)->get(route('productos.index'))->assertForbidden();
    }

    /**
     * Búsqueda tolerante a texto parcial y orden de palabras (ver
     * Producto::scopeSearch): "coc cola" debe encontrar "Coca Cola" aunque
     * no sea substring literal de la frase completa.
     */
    public function test_busqueda_tolerante_encuentra_por_palabras_parciales(): void
    {
        Producto::create([
            'nombre' => 'Alfajor Milka Chocolate 55g',
            'codigo_barras' => null,
            'precio_costo' => 300,
            'precio_venta' => 500,
            'stock_minimo' => 5,
        ]);
        $this->crearProducto(); // "Coca Cola 1.5L"

        $this->actingAs($this->user)->getJson(route('productos.buscar', ['buscar' => 'coc cola']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['nombre' => 'Coca Cola 1.5L']);

        $this->actingAs($this->user)->getJson(route('productos.buscar', ['buscar' => 'alfajor milka']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['nombre' => 'Alfajor Milka Chocolate 55g']);
    }

    /**
     * Nuevos medios de pago (POS/UX): débito y QR se preservan junto a
     * efectivo/transferencia/fiado (ver Venta::MEDIO_PAGO_*).
     */
    public function test_venta_acepta_debito_y_qr_como_medio_de_pago(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => Venta::MEDIO_PAGO_DEBITO,
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertRedirect(route('ventas.index'));

        $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => Venta::MEDIO_PAGO_QR,
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertRedirect(route('ventas.index'));

        $this->assertSame(1, Venta::where('medio_pago', Venta::MEDIO_PAGO_DEBITO)->count());
        $this->assertSame(1, Venta::where('medio_pago', Venta::MEDIO_PAGO_QR)->count());
    }

    /**
     * Control de stock opcional (gap encontrado por el usuario): un
     * producto con controla_stock=false nunca bloquea la venta ni se marca
     * sincronizada_con_stock_insuficiente, ni siquiera SIN uuid_dispositivo
     * (venta online normal) — a diferencia de la relajación offline, que
     * SÍ depende de uuid_dispositivo (ver los tests de arriba).
     */
    public function test_producto_sin_control_de_stock_no_bloquea_venta_online_con_stock_insuficiente(): void
    {
        $producto = $this->crearProducto(controlaStock: false); // stock inicial: 20

        $response = $this->actingAs($this->user)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 25],
            ],
        ]);

        $response->assertRedirect(route('ventas.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, Venta::count());
        $this->assertDatabaseHas('ventas', [
            'sincronizada_con_stock_insuficiente' => false,
        ]);
        $this->assertSame(-5, $producto->fresh()->stockActual());
    }
}
