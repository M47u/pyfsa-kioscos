<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VentaRequest;
use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function index(): View
    {
        $ventas = Venta::query()->with('cliente')->latest()->get();

        return view('ventas.index', [
            'ventas' => $ventas,
        ]);
    }

    public function create(): View
    {
        return view('ventas.create', [
            'productos' => Producto::query()->orderBy('nombre')->get(),
            'clientes' => Cliente::query()->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Crea la venta, sus ítems (con snapshot de precio_venta) y el
     * MovimientoStock negativo de cada ítem, todo en una transacción: si
     * algo falla a mitad de camino no queda una venta a medio crear ni
     * movimientos de stock sueltos. La venta SE BLOQUEA si dejaría el stock
     * de algún producto negativo — esa validación vive en
     * VentaRequest::withValidator, no acá, porque es la convención del
     * proyecto para validación de negocio de ventas.
     */
    public function store(VentaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $productos = Producto::query()
                ->whereIn('id', collect($data['items'])->pluck('producto_id'))
                ->get()
                ->keyBy('id');

            $total = collect($data['items'])->sum(
                fn (array $item) => $productos[$item['producto_id']]->precio_venta * $item['cantidad']
            );

            $venta = Venta::create([
                'cliente_id' => $data['cliente_id'] ?? null,
                'user_id' => auth()->id(),
                'medio_pago' => $data['medio_pago'],
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
                $producto = $productos[$item['producto_id']];

                $venta->items()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $producto->precio_venta,
                ]);

                $producto->movimientos()->create([
                    'tipo' => MovimientoStock::TIPO_VENTA,
                    'cantidad' => -$item['cantidad'],
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return redirect()->route('ventas.index')->with('status', 'Venta registrada correctamente.');
    }
}
