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
}
