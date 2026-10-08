<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\VentaRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si PyFsa (o el dueño) le restableció la contraseña al usuario
 * (`users.debe_cambiar_password`), no puede usar nada del sistema hasta
 * cambiarla en "Mi perfil": toda ruta del grupo tenant lo manda a
 * perfil.edit, salvo las dos que necesita para hacerlo (perfil.edit /
 * perfil.password). perfil.update (datos/email) queda bloqueada a
 * propósito: primero la contraseña.
 *
 * Va justo DESPUÉS de InitializeTenancyByAuthenticatedUser y ANTES de
 * EnsureComercioSuscripcionActiva / EnsureComercioTimezoneIsConfigured (y,
 * vía prependToPriorityList en bootstrap/app.php, antes de
 * SubstituteBindings: si no, un binding fallido da 404 en vez del
 * redirect). Esos dos se saltean cuando el flag está activo (ya confinado
 * acá a perfil.*): si no, un comercio vencido o sin zona horaria armaría un
 * loop (perfil.edit -> suscripcion-vencida -> perfil.edit...).
 *
 * Offline: la cola de ventas/pagos del dispositivo (resources/js/offline.js)
 * se reintenta con el header X-Sincronizacion-Cola; esas dos escrituras
 * PASAN aunque el flag esté activo — "una venta que llega de la cola nunca
 * se rechaza" (CLAUDE.md), y un redirect HTML seguido por fetch se vería
 * como éxito y borraría el item de IndexedDB (pérdida de datos). Mismo
 * límite aceptado que VentaRequest::esSincronizacionDeCola: el header lo
 * pone el cliente. Cualquier otro request que espere JSON recibe 423 en vez
 * de un redirect que fetch seguiría a HTML 200.
 */
class EnsurePasswordCambiada
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->debe_cambiar_password || $request->routeIs('perfil.edit', 'perfil.password')) {
            return $next($request);
        }

        if ($this->esEscrituraDeLaCola($request)) {
            return $next($request);
        }

        $mensaje = 'Tu contraseña fue restablecida por PyFsa. Tenés que elegir una nueva antes de seguir usando el sistema.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensaje,
                'errors' => ['password' => [$mensaje]],
            ], 423);
        }

        return redirect()->route('perfil.edit')->with('advertencia', $mensaje);
    }

    private function esEscrituraDeLaCola(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->header(VentaRequest::HEADER_SINCRONIZACION_COLA) === '1'
            && $request->routeIs('ventas.store', 'clientes.pagos.store');
    }
}
