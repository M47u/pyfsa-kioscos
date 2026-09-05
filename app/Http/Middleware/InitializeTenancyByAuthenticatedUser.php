<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reemplaza a InitializeTenancyByDomain: acá no identificamos el comercio
 * por subdominio, sino por el usuario que ya inició sesión (ver decisión
 * de arquitectura en el documento de alcance — evita tener que configurar
 * DNS/hosting cada vez que se suma un comercio nuevo).
 */
class InitializeTenancyByAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401);
        abort_if(blank($user->comercio_id), 403, 'Este usuario no tiene un comercio asignado.');

        tenancy()->initialize($user->comercio_id);

        return $next($request);
    }
}
