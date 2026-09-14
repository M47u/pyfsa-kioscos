<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate del lado del comercio (documento de alcance, módulo 3.6
 * "Suscripciones"): si PyFsa marcó al comercio como vencida/cancelada
 * desde /admin/comercios (ver Admin\ComercioController), el kiosquero deja
 * de poder usar el sistema — se lo manda a una pantalla explicando que
 * tiene que contactar a PyFsa.
 *
 * Va DESPUÉS de InitializeTenancyByAuthenticatedUser en el grupo de
 * routes/tenant.php: necesita la tenancy ya inicializada para poder leer
 * tenant()->tieneAccesoActivo(). El orden respecto a
 * EnsureComercioTimezoneIsConfigured no importa, ninguno depende del otro.
 */
class EnsureComercioSuscripcionActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant()->tieneAccesoActivo() && ! $request->routeIs('suscripcion-vencida')) {
            return redirect()->route('suscripcion-vencida');
        }

        return $next($request);
    }
}
