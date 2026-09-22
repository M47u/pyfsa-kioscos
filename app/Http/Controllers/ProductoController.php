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
            ->when($bajoMinimo, fn ($query) => $query->withSum('movimientos', 'cantidad'))
            ->orderBy('nombre')
            ->when(request()->wantsJson(), fn ($query) => $query->limit(20))
            ->get();

        if ($bajoMinimo) {
            // Filtro en memoria sobre la colección ya traída, pero usando el
            // stock ya agregado por SQL vía withSum() de arriba: llamar
            // Producto::bajoMinimo()/stockActual() acá dispararía una query
            // por producto del catálogo completo (se evalúa antes de
            // filtrar), reintroduciendo el N+1 que el resto del módulo evita.
            // controla_stock=false nunca cuenta como bajo mínimo (ver
            // Producto::bajoMinimo(), mismo criterio acá sin llamarlo por
            // fila).
            $productos = $productos
                ->filter(fn (Producto $producto) => $producto->controla_stock && ($producto->movimientos_sum_cantidad ?? 0) < $producto->stock_minimo)
                ->values();
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

    /**
     * Búsqueda en vivo para VENDER (ver ventas/create.blade.php) — a
     * diferencia de index()/JSON de arriba (dueño-only, pensado para
     * administrar el catálogo), esta ruta vive en el grupo COMPARTIDO de
     * routes/tenant.php: buscar un producto para cobrarlo es parte de
     * vender, no de administrar el catálogo.
     *
     * Bug real encontrado (documento de alcance nuevo — POS/UX): antes de
     * esto, ventas/create.blade.php le pegaba directo a productos.index con
     * Accept: application/json, ruta que vive DENTRO del sub-grupo
     * EnsureUserIsDueno — un empleado real recibía 403 al intentar buscar
     * un producto para vender, y ningún test lo detectaba porque RolTest
     * nunca ejercita el fetch JS del lado del cliente (solo pega contra
     * ventas.store directo). Ver tests/Feature/VentaTest.php para la
     * regresión.
     */
    public function buscar(): JsonResponse
    {
        $buscar = request()->string('buscar')->toString();

        $productos = Producto::query()
            ->when($buscar !== '', fn ($query) => $query->search($buscar))
            ->orderBy('nombre')
            ->limit(20)
            ->get();

        return response()->json($productos->map(fn (Producto $producto) => $this->comoJson($producto)));
    }

    /**
     * Catálogo completo (sin filtro), para que el frontend lo cachee en
     * IndexedDB apenas carga /ventas/create con conexión (ver
     * resources/js/offline.js) y pueda seguir buscando productos si la
     * señal se corta a mitad de una venta — la búsqueda en vivo de arriba
     * depende de la red, este catálogo es el fallback local. Mismo grupo de
     * rutas compartido que buscar().
     *
     * limit(5000): un tope de sanidad, no un paginado real — ningún kiosco
     * de este mercado tiene un catálogo de ese tamaño, pero evita que un
     * comercio con un catálogo anormalmente grande mande un payload
     * gigante al dispositivo del cajero.
     */
    public function catalogo(): JsonResponse
    {
        $productos = Producto::query()->orderBy('nombre')->limit(5000)->get();

        return response()->json($productos->map(fn (Producto $producto) => $this->comoJson($producto)));
    }

    /**
     * @return array{id: int, nombre: string, codigo_barras: ?string, precio_venta: float, controla_stock: bool}
     */
    private function comoJson(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'codigo_barras' => $producto->codigo_barras,
            'precio_venta' => (float) $producto->precio_venta,
            'controla_stock' => $producto->controla_stock,
        ];
    }

    /**
     * ?codigo_barras=...: prefill de conveniencia cuando se llega acá desde
     * "Crear artículo nuevo" en ventas/create.blade.php (código escaneado
     * o tipeado que no matcheó ningún producto existente) — evita que el
     * dueño tenga que volver a tipear/escanear el código a mano.
     */
    public function create(): View
    {
        $producto = new Producto;

        if (request()->filled('codigo_barras')) {
            $producto->codigo_barras = request()->string('codigo_barras')->toString();
        }

        return view('productos.create', [
            'producto' => $producto,
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

        return redirect()->route('productos.index')->with('status', 'Artículo creado correctamente.');
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

        return redirect()->route('productos.index')->with('status', 'Artículo actualizado correctamente.');
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
