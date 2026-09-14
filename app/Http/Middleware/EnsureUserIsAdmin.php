<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate del panel admin de PyFsa (/admin, ver routes/web.php): es un
 * concepto totalmente distinto de "tenant sin acceso" — acá se trata de
 * quién puede administrar la suscripción de TODOS los comercios, no de un
 * comercio en particular. No hay tenancy inicializada en estas rutas.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        return $next($request);
    }
}
