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
use Illuminate\Validation\ValidationException;
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
     * de algún producto negativo.
     *
     * El pre-check de VentaRequest::withValidator (SELECT simple, sin lock)
     * da buen UX -falla rápido, con mensaje claro- pero NO es la garantía
     * real: dos ventas concurrentes del mismo producto (dos cajas, o un
     * doble submit) pueden pasar esa validación las dos antes de que
     * ninguna confirme. La garantía real vive ACÁ: lockForUpdate() sobre
     * los productos involucrados serializa el acceso entre transacciones
     * concurrentes, y el stock se recalcula después de tomar el lock, ya
     * con los datos confirmados por cualquier venta que haya llegado
     * primero.
     */
    public function store(VentaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $productoIds = collect($data['items'])->pluck('producto_id')->unique();

            // lockForUpdate() bloquea las filas de estos productos a nivel
            // de base de datos: si otra venta concurrente del mismo
            // producto ya está adentro de su propia transacción, esta
            // query espera a que esa termine (commit o rollback) antes de
            // poder leer, en vez de leer un stock desactualizado.
            $productos = Producto::query()
                ->whereIn('id', $productoIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Mismo criterio que VentaRequest::withValidator: sumar por
            // producto (puede repetirse en más de una fila del carrito) y
            // comparar el total pedido contra el stock ya recalculado bajo
            // el lock.
            $cantidadesPorProducto = collect($data['items'])
                ->groupBy('producto_id')
                ->map(fn ($filas) => (int) $filas->sum('cantidad'));

            foreach ($cantidadesPorProducto as $productoId => $cantidadPedida) {
                $producto = $productos[$productoId];
                $stockActual = $producto->stockActual();

                if ($cantidadPedida > $stockActual) {
                    // ValidationException, no una excepción custom: así
                    // Laravel la maneja igual que cualquier otro fallo de
                    // validación de la app (redirect back con el error en
                    // la misma clave 'items' que ya usa el pre-check),
                    // sin necesidad de un catch manual acá.
                    throw ValidationException::withMessages([
                        'items' => "No hay stock suficiente de {$producto->nombre}: quedan {$stockActual}, se pidieron {$cantidadPedida}.",
                    ]);
                }
            }

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

        // A diferencia del stock (que bloquea), el límite de crédito de un
        // cliente fiado solo advierte: el kiosquero puede decidir fiarle de
        // más a un cliente de confianza (decisión de negocio confirmada).
        // Se chequea DESPUÉS de la transacción para reflejar el saldo ya
        // actualizado con la venta recién creada.
        if ($data['medio_pago'] === Venta::MEDIO_PAGO_FIADO) {
            $cliente = Cliente::find($data['cliente_id']);

            if ($cliente !== null) {
                $saldo = $cliente->saldo();

                if ($cliente->superaLimite($saldo)) {
                    session()->flash(
                        'advertencia',
                        "{$cliente->nombre} superó su límite de crédito (saldo: {$saldo}, límite: {$cliente->limite_credito})."
                    );
                }
            }
        }

        return redirect()->route('ventas.index')->with('status', 'Venta registrada correctamente.');
    }
}
