<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate del lado del rol (documento de alcance, módulo 3.5 "Usuarios"): sin
 * permisos granulares, un empleado solo puede vender y cobrar fiado — todo
 * lo demás (Productos, Reportes, Zona horaria, gestión de Usuarios) es
 * dueño-only. Ver User::esDueno() y el sub-grupo dueño-only en
 * routes/tenant.php.
 *
 * Va DESPUÉS de InitializeTenancyByAuthenticatedUser en el grupo de
 * routes/tenant.php: necesita auth()->user() ya resuelto.
 */
class EnsureUserIsDueno
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->user()->esDueno(), 403);

        return $next($request);
    }
}
