<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnularPagoRequest;
use App\Http\Requests\ClienteRequest;
use App\Http\Requests\PagoRequest;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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
        // 'anulado': se muestra en el historial igual que cualquier otro
        // movimiento (no se esconde, ver VentaController::anular) aunque
        // una venta anulada ya no cuenta para Cliente::saldo() (ver ese
        // método) — acá es solo auditoría. 'id' solo lo necesitan los
        // pagos: son el único tipo con acción de anular disponible en esta
        // vista (ver clientes/show.blade.php).
        $ventasFiado = $cliente->ventas()
            ->where('medio_pago', Venta::MEDIO_PAGO_FIADO)
            ->get()
            ->map(fn (Venta $venta) => [
                'tipo' => 'venta',
                'monto' => (float) $venta->total,
                'fecha' => $venta->created_at,
                'anulado' => $venta->estaAnulada(),
            ]);

        $pagos = $cliente->pagos()
            ->get()
            ->map(fn (Pago $pago) => [
                'tipo' => 'pago',
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'fecha' => $pago->created_at,
                'anulado' => $pago->estaAnulado(),
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

    /**
     * Anular un pago (documento de alcance — corrección de error humano).
     * Más simple que anular una venta (ver VentaController::anular): un
     * pago no toca stock, no tiene ningún "movimiento" que revertir aparte
     * de sus propias columnas, y no necesita lockForUpdate() — el propio
     * Cliente::saldo() ya deja de restarlo apenas queda anulado_en seteado
     * (ver el whereNull('anulado_en') agregado ahí), así que una carrera
     * entre dos anulaciones del mismo pago en el peor caso pisa las mismas
     * columnas dos veces con el mismo resultado, no duplica nada.
     *
     * Autorización: solo dueño, resuelta por EnsureUserIsDueno en
     * routes/tenant.php (guard duro, no solo el botón oculto en la vista).
     */
    public function anularPago(AnularPagoRequest $request, Pago $pago): RedirectResponse
    {
        if ($pago->estaAnulado()) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya fue anulado.',
            ]);
        }

        $pago->update([
            'anulado_en' => now(),
            'anulado_por' => auth()->id(),
            'motivo_anulacion' => $request->validated('motivo_anulacion'),
        ]);

        return redirect()->route('clientes.show', $pago->cliente_id)->with('status', 'Pago anulado correctamente.');
    }
}
