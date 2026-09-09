<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\UploadedFile;

/**
 * Alta masiva de productos por CSV (gap encontrado por el usuario, no está
 * en el documento de alcance original — ver ProductoImportController).
 *
 * El acceso dueño-only a productos.importar ya lo cubre RolTest junto con
 * el resto de las secciones detrás de EnsureUserIsDueno — no se duplica acá.
 */
class ProductoImportTest extends TenantTestCase
{
    private function archivoCsv(string $contenido, string $nombre = 'productos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    public function test_csv_valido_crea_los_productos_con_su_stock_inicial(): void
    {
        $csv = <<<'CSV'
        nombre,codigo_barras,precio_costo,precio_venta,stock_minimo,stock_inicial
        Coca Cola 1.5L,7790895000000,800,1200,5,24
        Fideos 500g,,500,800,5,0
        Yerba 1kg,7790895000001,1000,1500,3,10
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 3 producto(s) correctamente.');

        $this->assertDatabaseCount('productos', 3);

        $coca = Producto::where('codigo_barras', '7790895000000')->firstOrFail();
        $this->assertSame(24, $coca->stockActual());
        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $coca->id,
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 24,
        ]);

        $fideos = Producto::where('nombre', 'Fideos 500g')->firstOrFail();
        $this->assertSame(0, $fideos->stockActual());
        $this->assertNull($fideos->codigo_barras);

        $yerba = Producto::where('codigo_barras', '7790895000001')->firstOrFail();
        $this->assertSame(10, $yerba->stockActual());
    }

    /**
     * Procesamiento parcial: una fila inválida en el medio del archivo no
     * tira abajo las filas válidas de alrededor.
     */
    public function test_fila_con_codigo_de_barras_duplicado_se_reporta_sin_afectar_las_demas(): void
    {
        Producto::create([
            'nombre' => 'Ya existente',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $csv = <<<'CSV'
        nombre,codigo_barras,precio_costo,precio_venta,stock_minimo,stock_inicial
        Sprite 1.5L,7790895000001,800,1200,5,0
        Duplicado,7790895000000,800,1200,5,0
        Agua 500ml,7790895000002,300,500,5,0
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 2 producto(s) correctamente.');

        $errores = session('importacion_errores');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('Fila 3', $errores[0]);

        $this->assertDatabaseHas('productos', ['nombre' => 'Sprite 1.5L']);
        $this->assertDatabaseHas('productos', ['nombre' => 'Agua 500ml']);
        $this->assertDatabaseMissing('productos', ['nombre' => 'Duplicado']);

        // 1 preexistente + 2 importados = 3, la fila inválida no creó nada.
        $this->assertDatabaseCount('productos', 3);
    }

    /**
     * El parseo es por nombre de columna, no por posición: reordenar las
     * columnas en Excel antes de subir el archivo no debe romper el import.
     */
    public function test_encabezados_reordenados_funcionan_igual(): void
    {
        $csv = <<<'CSV'
        precio_venta,nombre,stock_inicial,precio_costo,codigo_barras,stock_minimo
        1200,Coca Cola 1.5L,24,800,7790895000000,5
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 1 producto(s) correctamente.');

        $producto = Producto::where('codigo_barras', '7790895000000')->firstOrFail();
        $this->assertSame('Coca Cola 1.5L', $producto->nombre);
        $this->assertSame(1200.0, (float) $producto->precio_venta);
        $this->assertSame(800.0, (float) $producto->precio_costo);
        $this->assertSame(5, $producto->stock_minimo);
        $this->assertSame(24, $producto->stockActual());
    }

    /**
     * Separador ';', no ',': Excel en configuración regional de Argentina/
     * Paraguay usa la coma como separador DECIMAL, así que interpreta un
     * CSV separado por comas como una sola columna al abrirlo con doble
     * click — ver el comentario de ProductoImportController::plantilla().
     */
    public function test_plantilla_de_ejemplo_se_descarga_con_el_encabezado_esperado(): void
    {
        $response = $this->actingAs($this->user)->get(route('productos.importar.plantilla'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('nombre;codigo_barras;precio_costo;precio_venta;stock_minimo;stock_inicial', false);
    }

    /**
     * Regresión del bug real reportado ("el CSV de ejemplo tiene una sola
     * columna"): la plantilla descargable tiene que poder subirse tal
     * cual y funcionar — no alcanza con que el encabezado se vea bien,
     * hace falta probar el archivo real que devuelve la ruta.
     */
    public function test_la_plantilla_descargada_se_puede_importar_tal_cual(): void
    {
        $plantilla = $this->actingAs($this->user)->get(route('productos.importar.plantilla'))->getContent();

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($plantilla),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 3 producto(s) correctamente.');
        $this->assertDatabaseCount('productos', 3);
    }

    /**
     * store() detecta el separador por archivo: un CSV separado por punto
     * y coma (el que realmente exporta/espera Excel en español) tiene que
     * importar igual de bien que uno separado por comas (ya cubierto por
     * test_csv_valido_crea_los_productos_con_su_stock_inicial arriba).
     */
    public function test_csv_separado_por_punto_y_coma_tambien_funciona(): void
    {
        $csv = <<<'CSV'
        nombre;codigo_barras;precio_costo;precio_venta;stock_minimo;stock_inicial
        Coca Cola 1.5L;7790895000000;800;1200;5;24
        Fideos 500g;;500;800;5;0
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 2 producto(s) correctamente.');

        $coca = Producto::where('codigo_barras', '7790895000000')->firstOrFail();
        $this->assertSame(24, $coca->stockActual());
    }

    /**
     * Regresión: precios con coma decimal ("800,50"), formato natural en
     * Argentina/Paraguay — sin normalizar, `numeric` de Laravel los
     * rechaza y la fila entera se pierde.
     */
    public function test_csv_con_coma_decimal_en_los_precios_funciona(): void
    {
        $csv = <<<'CSV'
        nombre;codigo_barras;precio_costo;precio_venta;stock_minimo;stock_inicial
        Coca Cola 1.5L;7790895000000;800,50;1200,75;5;24
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 1 producto(s) correctamente.');

        $producto = Producto::where('codigo_barras', '7790895000000')->firstOrFail();
        $this->assertSame(800.5, (float) $producto->precio_costo);
        $this->assertSame(1200.75, (float) $producto->precio_venta);
    }

    /**
     * Formato completo con punto de miles + coma decimal ("1.234,56"),
     * también natural en la región para precios de 4+ dígitos.
     */
    public function test_csv_con_punto_de_miles_y_coma_decimal_funciona(): void
    {
        $csv = <<<'CSV'
        nombre;codigo_barras;precio_costo;precio_venta;stock_minimo;stock_inicial
        Notebook;7790895000000;1.234,56;2.000,00;1;0
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 1 producto(s) correctamente.');

        $producto = Producto::where('codigo_barras', '7790895000000')->firstOrFail();
        $this->assertSame(1234.56, (float) $producto->precio_costo);
        $this->assertSame(2000.0, (float) $producto->precio_venta);
    }

    /**
     * Un CSV separado por COMAS no puede además usar coma decimal en los
     * precios (ambigüedad irresoluble: ya habría partido la fila en dos
     * columnas antes de llegar a la normalización) — la normalización
     * queda deliberadamente desactivada en ese caso, así que un precio
     * con coma ahí sigue fallando como antes.
     */
    public function test_csv_separado_por_comas_no_normaliza_coma_decimal(): void
    {
        $csv = <<<'CSV'
        nombre,codigo_barras,precio_costo,precio_venta,stock_minimo,stock_inicial
        Coca Cola 1.5L,7790895000000,"800,50",1200,5,24
        CSV;

        $response = $this->actingAs($this->user)->post(route('productos.importar.store'), [
            'archivo' => $this->archivoCsv($csv),
        ]);

        $response->assertRedirect(route('productos.importar'));
        $response->assertSessionHas('status', 'Se importaron 0 producto(s) correctamente.');
        $this->assertCount(1, session('importacion_errores'));
    }
}
