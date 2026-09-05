<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Http\Requests\ReponerStockRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(): View
    {
        $buscar = request()->string('buscar')->toString();

        $productos = Producto::query()
            ->when($buscar !== '', fn ($query) => $query->search($buscar))
            ->orderBy('nombre')
            ->get();

        return view('productos.index', [
            'productos' => $productos,
            'buscar' => $buscar,
        ]);
    }

    public function create(): View
    {
        return view('productos.create', [
            'producto' => new Producto(),
        ]);
    }

    public function store(ProductoRequest $request): RedirectResponse
    {
        Producto::create($request->validated());

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
