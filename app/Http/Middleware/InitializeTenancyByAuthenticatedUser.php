<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\Tenant;
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

        $this->aplicarZonaHoraria(tenant());

        return $next($request);
    }

    /**
     * Los comercios pueden estar en Argentina o Paraguay (ver
     * Comercio::ZONAS_HORARIAS) y, a diferencia de Argentina, Paraguay
     * tiene horario de verano — un huso fijo para toda la app no sirve.
     * Mientras el comercio no lo eligió todavía (primer uso — ver
     * EnsureComercioTimezoneIsConfigured), se usa el default de
     * config/app.php.
     *
     * date_default_timezone_set() es estado global de PHP: seguro en el
     * modelo de una request = un proceso/hilo fresco (php artisan serve,
     * PHP-FPM, Apache), que es como corre este proyecto. Si en algún
     * momento se suma Laravel Octane (workers persistentes, reusan el
     * proceso PHP entre requests) esto se filtraría de un comercio a
     * otro y habría que resetearlo a mano al final de cada request.
     */
    private function aplicarZonaHoraria(Tenant $comercio): void
    {
        $timezone = $comercio->timezone ?? config('app.timezone');

        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);
    }
}
