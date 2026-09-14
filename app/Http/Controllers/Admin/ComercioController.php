<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComercioEstadoRequest;
use App\Models\Comercio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Panel de administración de PyFsa (módulo 3.6 "Suscripciones" del
 * documento de alcance) — NO es una feature para el kiosquero, es cómo
 * PyFsa controla qué comercios tienen acceso pagado. Vive fuera de
 * routes/tenant.php, sin tenancy inicializada (ver routes/web.php y
 * App\Http\Middleware\EnsureUserIsAdmin).
 */
class ComercioController extends Controller
{
    public function index(): View
    {
        $comercios = Comercio::all()->map(function (Comercio $comercio) {
            $comercio->setAttribute(
                'cantidad_usuarios',
                User::where('comercio_id', $comercio->id)->count(),
            );

            return $comercio;
        });

        return view('admin.comercios.index', [
            'comercios' => $comercios,
            'estados' => [
                Comercio::ESTADO_PRUEBA,
                Comercio::ESTADO_ACTIVA,
                Comercio::ESTADO_VENCIDA,
                Comercio::ESTADO_CANCELADA,
            ],
        ]);
    }

    public function update(ComercioEstadoRequest $request, Comercio $comercio): RedirectResponse
    {
        $comercio->update($request->validated());

        return redirect()->route('admin.comercios.index')->with('status', "Comercio {$comercio->id} actualizado.");
    }
}
