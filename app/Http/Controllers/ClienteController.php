<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnularPagoRequest;
use App\Http\Requests\ClienteRequest;
use App\Http\Requests\PagoRequest;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
            'cliente' => new Cliente,
        ]);
    }

    /**
     * Accept: application/json (ver ventas/create.blade.php — alta rápida
     * de cliente sin salir de la pantalla de venta, cuando se cobra a
     * cuenta corriente y el cliente todavía no existe): devuelve el
     * Cliente recién creado en vez del redirect pensado para el <form> de
     * clientes/create.blade.php. Mismo criterio que
     * ProductoController::index()/buscar() respondiendo distinto según el
     * header Accept.
     */
    public function store(ClienteRequest $request): RedirectResponse|JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        if (request()->wantsJson()) {
            return response()->json([
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
            ], 201);
        }

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

    /**
     * Offline (documento de alcance — "Cobrar fiado" funciona offline, ver
     * CLAUDE.md, arquitectura offline): mismo criterio de idempotencia que
     * VentaController::store — si uuid_dispositivo ya existe en un Pago, es
     * un reintento de sync (red flaky, doble intento), no un pago nuevo, y
     * se responde como éxito sin crear nada. El chequeo de abajo cubre el
     * reintento secuencial normal; el catch de QueryException cubre la
     * carrera real de dos intentos casi simultáneos contra la constraint
     * UNIQUE de la columna. Un pago no tiene equivalente a "stock
     * insuficiente" — no hay ninguna otra relajación de validación acá.
     */
    public function registrarPago(PagoRequest $request, Cliente $cliente): RedirectResponse
    {
        $data = $request->validated();
        $uuidDispositivo = $data['uuid_dispositivo'] ?? null;

        if ($uuidDispositivo !== null && Pago::where('uuid_dispositivo', $uuidDispositivo)->exists()) {
            return redirect()->route('clientes.show', $cliente)->with('status', 'Pago registrado correctamente.');
        }

        try {
            $cliente->pagos()->create([
                ...$data,
                'user_id' => auth()->id(),
            ]);
        } catch (QueryException $e) {
            if ($uuidDispositivo !== null && str_contains($e->getMessage(), 'uuid_dispositivo')) {
                return redirect()->route('clientes.show', $cliente)->with('status', 'Pago registrado correctamente.');
            }

            throw $e;
        }

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
