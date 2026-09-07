<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportarProductosRequest;
use App\Http\Requests\ProductoRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Alta masiva de productos por CSV. Gap encontrado por el usuario (no está
 * en el documento de alcance original): un kiosco que ya tiene su catálogo
 * en un cuaderno o Excel tiene que cargar cada producto a mano si no existe
 * esto — fricción real de arranque con un cliente nuevo.
 *
 * Parseo con fgetcsv puro sobre el stream del archivo subido — sin agregar
 * una librería nueva de Composer, no hace falta para un CSV con encabezado
 * simple.
 *
 * A diferencia de una venta (todo o nada, ver VentaController::store), acá
 * el procesamiento es fila por fila e independiente: si una fila falla
 * validación, se saltea y se sigue con las demás — un error en la fila 47
 * de un archivo de 300 productos no debe tirar abajo las otras 299 filas
 * buenas. Por eso NO hay una transacción envolviendo el archivo entero,
 * solo una por fila (para que esa fila puntual quede completa o nada).
 */
class ProductoImportController extends Controller
{
    /**
     * Encabezado esperado del CSV. codigo_barras y stock_inicial son
     * opcionales fila por fila (mismas reglas que ProductoRequest), pero
     * las tres columnas restantes son imprescindibles para poder crear un
     * Producto — sin ellas ni vale la pena empezar a procesar filas.
     *
     * @var list<string>
     */
    private const COLUMNAS_PLANTILLA = ['nombre', 'codigo_barras', 'precio_costo', 'precio_venta', 'stock_minimo', 'stock_inicial'];

    /** @var list<string> */
    private const COLUMNAS_REQUERIDAS = ['nombre', 'precio_costo', 'precio_venta'];

    public function create(): View
    {
        return view('productos.importar');
    }

    public function store(ImportarProductosRequest $request): RedirectResponse
    {
        $handle = fopen($request->file('archivo')->getRealPath(), 'r');

        $encabezado = fgetcsv($handle);

        if ($encabezado === false || $encabezado === [null]) {
            fclose($handle);

            return redirect()->route('productos.importar')
                ->with('importacion_errores', ['El archivo está vacío.']);
        }

        // Excel exporta CSV en UTF-8 con BOM: si queda pegado al primer
        // nombre de columna, "nombre" nunca matchea contra el encabezado
        // esperado.
        $encabezado[0] = preg_replace('/^\x{FEFF}/u', '', (string) $encabezado[0]);

        // Por nombre de columna, no por posición: tolerante a que alguien
        // reordene las columnas en Excel antes de subir el archivo.
        $indices = array_flip(array_map(
            fn (mixed $columna): string => strtolower(trim((string) $columna)),
            $encabezado
        ));

        $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, array_keys($indices));

        if ($faltantes !== []) {
            fclose($handle);

            return redirect()->route('productos.importar')
                ->with('importacion_errores', [
                    'Faltan columnas requeridas en el encabezado: '.implode(', ', $faltantes).'.',
                ]);
        }

        $importados = 0;
        $errores = [];
        $codigosVistos = [];
        $numeroFila = 1; // la fila 1 del archivo es el encabezado.

        while (($fila = fgetcsv($handle)) !== false) {
            $numeroFila++;

            // Línea vacía (típicamente al final del archivo): fgetcsv la
            // devuelve como [null], no es una fila de datos real.
            if ($fila === [null]) {
                continue;
            }

            $leer = fn (string $columna): string => isset($indices[$columna])
                ? trim((string) ($fila[$indices[$columna]] ?? ''))
                : '';

            $codigoBarras = $leer('codigo_barras') !== '' ? $leer('codigo_barras') : null;

            if ($codigoBarras !== null && in_array($codigoBarras, $codigosVistos, true)) {
                $errores[] = "Fila {$numeroFila}: código de barras duplicado en el archivo.";

                continue;
            }

            $datos = [
                'nombre' => $leer('nombre'),
                'codigo_barras' => $codigoBarras,
                'precio_costo' => $leer('precio_costo'),
                'precio_venta' => $leer('precio_venta'),
                'stock_minimo' => $leer('stock_minimo') !== '' ? $leer('stock_minimo') : 0,
                'stock_inicial' => $leer('stock_inicial') !== '' ? $leer('stock_inicial') : 0,
            ];

            // Mismas reglas que el alta manual de un Producto (ver
            // ProductoRequest) — sin $productoAIgnorar porque acá siempre
            // es un alta nueva, nunca una edición.
            $validador = Validator::make($datos, ProductoRequest::reglas());

            if ($validador->fails()) {
                $errores[] = "Fila {$numeroFila}: ".implode(' ', $validador->errors()->all());

                continue;
            }

            $validado = $validador->validated();

            DB::transaction(function () use ($validado): void {
                // Mismo mecanismo que ProductoController::store(): stock_inicial
                // no es columna de `productos`, se traduce en un
                // MovimientoStock de reposición porque el stock nunca se
                // guarda ni edita directo (ver Producto::stockActual()).
                $producto = Producto::create(collect($validado)->except('stock_inicial')->all());

                if ((int) $validado['stock_inicial'] > 0) {
                    $producto->movimientos()->create([
                        'tipo' => MovimientoStock::TIPO_REPOSICION,
                        'cantidad' => $validado['stock_inicial'],
                        'user_id' => auth()->id(),
                    ]);
                }
            });

            if ($codigoBarras !== null) {
                $codigosVistos[] = $codigoBarras;
            }

            $importados++;
        }

        fclose($handle);

        return redirect()->route('productos.importar')
            ->with('status', "Se importaron {$importados} producto(s) correctamente.")
            ->with('importacion_errores', $errores);
    }

    /**
     * CSV de ejemplo descargable desde el propio formulario de importación.
     * Generado al vuelo (más simple de mantener que un archivo estático:
     * si algún día cambia el encabezado esperado, cambia en un solo lugar).
     */
    public function plantilla(): Response
    {
        $filas = [
            self::COLUMNAS_PLANTILLA,
            ['Coca Cola 1.5L', '7790895000000', '800', '1200', '5', '24'],
            ['Fideos 500g', '', '500', '800', '5', '0'],
            ['Yerba 1kg', '7790895000001', '1000', '1500', '3', '10'],
        ];

        $handle = fopen('php://temp', 'r+');

        foreach ($filas as $fila) {
            fputcsv($handle, $fila);
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return response($contenido, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="plantilla-productos.csv"',
        ]);
    }
}
