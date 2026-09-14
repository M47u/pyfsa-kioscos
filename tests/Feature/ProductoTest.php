<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MovimientoStock;
use App\Models\Producto;

class ProductoTest extends TenantTestCase
{
    public function test_alta_de_producto(): void
    {
        $response = $this->actingAs($this->user)->post(route('productos.store'), [
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response->assertRedirect(route('productos.index'));

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
        ]);
    }

    public function test_alta_de_producto_con_stock_inicial_crea_movimiento_de_reposicion(): void
    {
        $response = $this->actingAs($this->user)->post(route('productos.store'), [
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
            'stock_inicial' => 24,
        ]);

        $response->assertRedirect(route('productos.index'));

        $producto = Producto::where('codigo_barras', '7790895000000')->firstOrFail();

        $this->assertSame(24, $producto->stockActual());
        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $producto->id,
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 24,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Sin stock_inicial (o en cero) no debe crear un movimiento de
     * reposición "vacío" — un producto recién dado de alta arranca en 0
     * sin necesidad de un registro en movimientos_stock.
     */
    public function test_alta_de_producto_sin_stock_inicial_no_crea_movimiento(): void
    {
        $response = $this->actingAs($this->user)->post(route('productos.store'), [
            'nombre' => 'Fideos 500g',
            'codigo_barras' => null,
            'precio_costo' => 500,
            'precio_venta' => 800,
            'stock_minimo' => 5,
        ]);

        $response->assertRedirect(route('productos.index'));

        $producto = Producto::where('nombre', 'Fideos 500g')->firstOrFail();

        $this->assertSame(0, $producto->stockActual());
        $this->assertDatabaseCount('movimientos_stock', 0);
    }

    public function test_reponer_stock_sube_stock_actual(): void
    {
        $producto = Producto::create([
            'nombre' => 'Yerba 1kg',
            'codigo_barras' => null,
            'precio_costo' => 1000,
            'precio_venta' => 1500,
            'stock_minimo' => 3,
        ]);

        $this->assertSame(0, $producto->stockActual());

        $response = $this->actingAs($this->user)->post(
            route('productos.reponer', $producto),
            ['cantidad' => 10]
        );

        $response->assertRedirect(route('productos.index'));

        $this->assertSame(10, $producto->fresh()->stockActual());
        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $producto->id,
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 10,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * inputmode="numeric" en el input de "Reponer" del listado: en mobile
     * abre directo el teclado numérico en vez del QWERTY completo —
     * type="number" solo no alcanza, en iOS a veces muestra el teclado con
     * signo/decimal en vez del numérico puro.
     */
    public function test_input_de_reponer_stock_tiene_teclado_numerico(): void
    {
        Producto::create([
            'nombre' => 'Yerba 1kg',
            'codigo_barras' => null,
            'precio_costo' => 1000,
            'precio_venta' => 1500,
            'stock_minimo' => 3,
        ]);

        $this->actingAs($this->user)->get(route('productos.index'))
            ->assertSee('inputmode="numeric"', false);
    }

    public function test_bajo_minimo_segun_stock_actual(): void
    {
        $producto = Producto::create([
            'nombre' => 'Fideos 500g',
            'codigo_barras' => null,
            'precio_costo' => 500,
            'precio_venta' => 800,
            'stock_minimo' => 5,
        ]);

        $this->assertTrue($producto->bajoMinimo(), 'Sin stock, debe estar bajo minimo.');

        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 10,
            'user_id' => $this->user->id,
        ]);

        $this->assertFalse($producto->fresh()->bajoMinimo(), 'Con stock por encima del minimo, no debe estar bajo minimo.');
    }

    public function test_busqueda_por_nombre(): void
    {
        Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        Producto::create([
            'nombre' => 'Sprite 1.5L',
            'codigo_barras' => '7790895000001',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('productos.index', ['buscar' => 'Coca']));

        $response->assertOk();
        $response->assertSee('Coca Cola 1.5L');
        $response->assertDontSee('Sprite 1.5L');
    }

    /**
     * Regresión del bug de orden de middleware: sin la prioridad explícita
     * en bootstrap/app.php, SubstituteBindings corre ANTES que
     * InitializeTenancyByAuthenticatedUser, así que el binding implícito de
     * {producto} se resuelve contra la base CENTRAL (donde "productos" no
     * existe) en vez de la base del tenant.
     *
     * A propósito NO reusamos la inicialización de tenancy que hace
     * TenantTestCase::setUp(): esa inicialización "de más" es justamente lo
     * que hacía que los tests anteriores no detectaran el bug (la conexión
     * ya estaba en la base del tenant antes de que el pipeline HTTP
     * arrancara, sin importar el orden real de los middlewares). Acá
     * terminamos la tenancy a mano para simular una request real "en frío"
     * y dejamos que sea el middleware, corriendo dentro del pipeline HTTP
     * real disparado por actingAs()->put(), el que la inicialice.
     */
    public function test_update_producto_resuelve_binding_de_tenant_sin_tenancy_pre_inicializada(): void
    {
        $producto = Producto::create([
            'nombre' => 'Yerba 1kg',
            'codigo_barras' => null,
            'precio_costo' => 1000,
            'precio_venta' => 1500,
            'stock_minimo' => 3,
        ]);

        tenancy()->end();

        $response = $this->actingAs($this->user)->put(route('productos.update', $producto), [
            'nombre' => 'Yerba 1kg (actualizada)',
            'codigo_barras' => null,
            'precio_costo' => 1100,
            'precio_venta' => 1600,
            'stock_minimo' => 3,
        ]);

        $response->assertRedirect(route('productos.index'));

        tenancy()->initialize($this->comercio);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre' => 'Yerba 1kg (actualizada)',
        ]);
    }

    /**
     * Regresión: `stock_minimo` es NOT NULL en la DB (default 0), pero la
     * regla de validación es 'nullable'. Si el campo llega vacío,
     * ConvertEmptyStringsToNull lo vuelve null, y sin prepareForValidation()
     * normalizándolo a 0 el insert explota con un error de DB en vez de
     * guardar el default de negocio.
     */
    public function test_alta_de_producto_con_stock_minimo_vacio_usa_default_cero(): void
    {
        $response = $this->actingAs($this->user)->post(route('productos.store'), [
            'nombre' => 'Fideos 500g',
            'codigo_barras' => null,
            'precio_costo' => 500,
            'precio_venta' => 800,
            'stock_minimo' => '',
        ]);

        $response->assertRedirect(route('productos.index'));

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Fideos 500g',
            'stock_minimo' => 0,
        ]);
    }

    /**
     * Regresión: scopeSearch interpolaba el término crudo en el LIKE, sin
     * escapar los metacaracteres % y _. Un nombre que contenga un '%'
     * literal terminaba matcheando de más (el '%' del término se
     * interpretaba como wildcard en vez de caracter literal).
     */
    public function test_busqueda_escapa_comodines_de_like(): void
    {
        Producto::create([
            'nombre' => 'Combo 50% off',
            'codigo_barras' => '1111111111111',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        Producto::create([
            'nombre' => 'Combo 50X off',
            'codigo_barras' => '2222222222222',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('productos.index', ['buscar' => '50%']));

        $response->assertOk();
        $response->assertSee('Combo 50% off');
        $response->assertDontSee('Combo 50X off');
    }

    public function test_busqueda_por_codigo_de_barras(): void
    {
        Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        Producto::create([
            'nombre' => 'Sprite 1.5L',
            'codigo_barras' => '7790895000001',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('productos.index', ['buscar' => '7790895000001']));

        $response->assertOk();
        $response->assertSee('Sprite 1.5L');
        $response->assertDontSee('Coca Cola 1.5L');
    }

    /**
     * ?bajo_minimo=1 (usado por el link "Ver productos" del resumen de
     * stock bajo mínimo en Reportes, ver ReporteController) devuelve solo
     * los productos por debajo de su mínimo, sin tocar el comportamiento
     * de ?buscar= (ver test_busqueda_por_nombre arriba, que sigue pasando
     * sin este filtro).
     */
    public function test_filtro_bajo_minimo_devuelve_solo_productos_bajo_minimo(): void
    {
        $conStock = Producto::create([
            'nombre' => 'Con stock',
            'codigo_barras' => null,
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);
        $conStock->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 10,
            'user_id' => $this->user->id,
        ]);

        Producto::create([
            'nombre' => 'Sin stock',
            'codigo_barras' => null,
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('productos.index', ['bajo_minimo' => 1]));

        $response->assertOk();
        $response->assertSee('Sin stock');
        $response->assertDontSee('Con stock');
    }

    /**
     * El buscador de productos en el carrito de Ventas (ver
     * ventas/create.blade.php) le pega a esta misma ruta con
     * Accept: application/json en vez de un <select> con el catálogo
     * entero — no es usable pasados unos pocos cientos de productos, y no
     * es compatible con un lector de código de barras.
     */
    public function test_buscar_productos_con_accept_json_devuelve_json(): void
    {
        Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        Producto::create([
            'nombre' => 'Sprite 1.5L',
            'codigo_barras' => '7790895000001',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('productos.index', ['buscar' => '7790895000001']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'nombre' => 'Sprite 1.5L',
            'codigo_barras' => '7790895000001',
            'precio_venta' => 1200.0,
        ]);
    }
}
