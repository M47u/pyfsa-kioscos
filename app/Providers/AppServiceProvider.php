<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Intentos de login permitidos por MINUTO desde una misma IP, sin
     * importar contra qué email vayan. COMPLEMENTA (no reemplaza) al
     * límite por email+IP de LoginRequest::INTENTOS_PERMITIDOS: ese cuenta
     * los fallos de UNA cuenta, así que un atacante desde una sola IP podía
     * probar una contraseña contra miles de emails distintos sin encontrar
     * nunca un freno — cada email arrancaba su propio contador en cero.
     * Eso es credential stuffing, el ataque realista contra un login
     * público, y era exactamente el hueco que quedaba abierto.
     *
     * Por qué 20 y no menos: son cuatro veces el límite por email (5), así
     * que cuatro personas distintas detrás del mismo NAT/IP compartida —
     * un comercio con varios empleados, un pueblo con IP compartida del
     * ISP, el caso real de Formosa/Alberdi — pueden agotar cada una su
     * propio margen de tipeo sin pisarse entre sí. Por qué no más: 20
     * emails por minuto es inútil para recorrer una lista filtrada de
     * credenciales, que es de lo que se trata frenar acá.
     *
     * Cuenta TODAS las requests a POST /login, no solo las fallidas (así
     * funciona el middleware `throttle` de Laravel, a diferencia del
     * RateLimiter::hit() manual de LoginRequest, que solo suma en el
     * fallo). Es lo que se quiere: un bombardeo que acierta también tiene
     * que frenarse.
     */
    private const INTENTOS_LOGIN_POR_IP = 20;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El middleware 'guest' (RedirectIfAuthenticated) manda a un
        // usuario ya logueado que entra a /login para otro lado — por
        // default busca una ruta llamada 'dashboard' o 'home', ninguna
        // existe acá (la nuestra se llama 'panel'), así que sin esto cae
        // al último fallback: '/' (la bienvenida de Laravel). Bug real
        // reportado en producción: "vuelvo a /login logueado y me
        // muestra la de bienvenida".
        //
        // rutaDeInicio() en vez de route('panel') pelado por el mismo
        // motivo que en AuthenticatedSessionController: un admin de
        // plataforma sin comercio no tiene nada que hacer en /panel, ahí
        // lo espera un 403. El ?? es defensivo — si el callback corre sin
        // usuario resuelto, el destino de siempre.
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => $request->user()?->rutaDeInicio() ?? route('panel')
        );

        // Limiter con nombre (en vez de un `throttle:20,1` pelado en la
        // ruta) para tener el porqué del número acá, junto a la constante,
        // y para poder ajustarlo/loguearlo más adelante sin tocar
        // routes/web.php. Se aplica en POST /login, ver ese archivo.
        RateLimiter::for('login-por-ip', fn (Request $request) => Limit::perMinute(self::INTENTOS_LOGIN_POR_IP)->by($request->ip()));
    }
}
