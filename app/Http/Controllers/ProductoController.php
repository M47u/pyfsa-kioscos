<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Http\Requests\ReponerStockRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductoController extends Controller
{
    /**
     * Con Accept: application/json devuelve las coincidencias como JSON en
     * vez de la vista completa — lo usa el buscador de Ventas (ver
     * ventas/create.blade.php), pensado para un lector de código de barras
     * USB/Bluetooth: el lector "tipea" el código y manda Enter, no sirve un
     * <select> con todo el catálogo (impracticable ya con unos pocos
     * cientos de productos, y un lector no interactúa con un dropdown).
     * limit(20) porque es para un buscador en vivo, no para listar el
     * catálogo completo (eso lo sigue haciendo la vista HTML sin límite).
     */
    public function index(): View|JsonResponse
    {
        $buscar = request()->string('buscar')->toString();

        // Filtro usado por el link "Ver productos" del resumen de stock
        // bajo mínimo en Reportes (ver ReporteController). Compatible con
        // ?buscar= al mismo tiempo si algún día hace falta combinarlos,
        // pero el caso de uso real de hoy es bajo_minimo solo.
        $bajoMinimo = request()->boolean('bajo_minimo');

        $productos = Producto::query()
            ->when($buscar !== '', fn ($query) => $query->search($buscar))
            ->orderBy('nombre')
            ->when(request()->wantsJson(), fn ($query) => $query->limit(20))
            ->get();

        if ($bajoMinimo) {
            // Filtro en memoria sobre la colección ya traída: el resultado
            // esperado es chico (solo los productos bajo mínimo), así que
            // llamar bajoMinimo() por fila acá no reintroduce el N+1 que sí
            // importaría en el listado completo sin filtrar.
            $productos = $productos->filter(fn (Producto $producto) => $producto->bajoMinimo())->values();
        }

        if (request()->wantsJson()) {
            return response()->json($productos->map(fn (Producto $producto) => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo_barras' => $producto->codigo_barras,
                'precio_venta' => (float) $producto->precio_venta,
            ]));
        }

        return view('productos.index', [
            'productos' => $productos,
            'buscar' => $buscar,
            'bajoMinimo' => $bajoMinimo,
        ]);
    }

    public function create(): View
    {
        return view('productos.create', [
            'producto' => new Producto(),
        ]);
    }

    /**
     * `stock_inicial` no es columna de `productos`: si viene con valor, se
     * traduce en un MovimientoStock de reposición recién creado el producto
     * (mismo mecanismo que reponerStock), para no romper la regla de que el
     * stock nunca se guarda ni edita directo — ver Producto::stockActual().
     */
    public function store(ProductoRequest $request): RedirectResponse
    {
        $producto = Producto::create($request->safe()->except('stock_inicial'));

        $stockInicial = (int) $request->validated('stock_inicial');

        if ($stockInicial > 0) {
            $producto->movimientos()->create([
                'tipo' => MovimientoStock::TIPO_REPOSICION,
                'cantidad' => $stockInicial,
                'user_id' => auth()->id(),
            ]);
        }

        return redirect()->route('productos.index')->with('status', 'Producto creado correctamente.');
    }

    public function edit(Producto $producto): View
    {
        return view('productos.edit', [
            'producto' => $producto,
        ]);
    }

    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()->route('productos.index')->with('status', 'Producto actualizado correctamente.');
    }

    /**
     * Registra una reposición de stock. La cantidad actual del producto
     * nunca se edita directamente: se suma un movimiento y el stock queda
     * calculado a partir del historial (ver Producto::stockActual()).
     */
    public function reponerStock(ReponerStockRequest $request, Producto $producto): RedirectResponse
    {
        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => $request->validated('cantidad'),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('productos.index')->with('status', 'Stock repuesto correctamente.');
    }
}
