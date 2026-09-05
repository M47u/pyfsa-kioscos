<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Http\Requests\PagoRequest;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::query()->orderBy('nombre')->get();

        return view('clientes.index', [
            'clientes' => $clientes,
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'cliente' => new Cliente(),
        ]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        Cliente::create($request->validated());

        return redirect()->route('clientes.index')->with('status', 'Cliente creado correctamente.');
    }

    /**
     * Historial de cuenta corriente: mezcla las ventas fiadas y los pagos
     * del cliente en una sola lista cronológica (más reciente primero),
     * cada uno etiquetado con su tipo para que la vista los distinga.
     * No hay una tabla propia de "movimientos de cuenta corriente": se arma
     * en memoria a partir de las dos fuentes, igual criterio que
     * Producto::stockActual() calculando en vez de guardar un acumulado.
     */
    public function show(Cliente $cliente): View
    {
        $ventasFiado = $cliente->ventas()
            ->where('medio_pago', Venta::MEDIO_PAGO_FIADO)
            ->get()
            ->map(fn (Venta $venta) => [
                'tipo' => 'venta',
                'monto' => (float) $venta->total,
                'fecha' => $venta->created_at,
            ]);

        $pagos = $cliente->pagos()
            ->get()
            ->map(fn (Pago $pago) => [
                'tipo' => 'pago',
                'monto' => (float) $pago->monto,
                'fecha' => $pago->created_at,
            ]);

        $movimientos = $ventasFiado->concat($pagos)->sortByDesc('fecha')->values();

        return view('clientes.show', [
            'cliente' => $cliente,
            'movimientos' => $movimientos,
        ]);
    }

    public function registrarPago(PagoRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->pagos()->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('clientes.show', $cliente)->with('status', 'Pago registrado correctamente.');
    }
}
