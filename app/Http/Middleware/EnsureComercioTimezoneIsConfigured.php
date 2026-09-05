<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada comercio elige su zona horaria una sola vez, al primer uso del
 * sistema (Argentina o Paraguay — ver Comercio::ZONAS_HORARIAS y
 * ZonaHorariaController). Mientras no la eligió, cualquier ruta del panel
 * redirige acá en vez de dejarlo pasar con el default de config/app.php,
 * que puede no corresponder a dónde está su comercio.
 *
 * Va DESPUÉS de InitializeTenancyByAuthenticatedUser en el grupo de
 * routes/tenant.php: necesita la tenancy ya inicializada para poder leer
 * tenant('timezone').
 */
class EnsureComercioTimezoneIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(tenant('timezone')) && ! $request->routeIs('zona-horaria.*')) {
            return redirect()->route('zona-horaria.edit');
        }

        return $next($request);
    }
}
