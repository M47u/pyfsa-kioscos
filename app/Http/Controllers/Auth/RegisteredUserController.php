<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroRequest;
use App\Models\Comercio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Alta pública de un Comercio nuevo (gap encontrado por el usuario, no
 * está en el documento de alcance original: hasta ahora la única forma de
 * dar de alta un Comercio era a mano por tinker — ver CLAUDE.md).
 *
 * Crea el Comercio (dispara la base de datos MySQL real del tenant, ver
 * Comercio::create() ya verificado en CLAUDE.md "Verified working"), su
 * primer User como dueño, y lo loguea automáticamente. `estado_suscripcion`
 * queda en el default 'prueba' de la migración (no hace falta setearlo a
 * mano acá); `trial_termina_el` es solo informativo para que el panel
 * admin de PyFsa (Admin\ComercioController) sepa cuándo revisar el trial.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.registro');
    }

    public function store(RegistroRequest $request): RedirectResponse
    {
        $comercio = Comercio::create([
            'nombre' => $request->validated('nombre_comercio'),
            'trial_termina_el' => now()->addDays(14),
        ]);

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_DUENO,
        ]);

        Auth::login($user);

        // No hace falta armar el paso de zona horaria acá: el propio gate
        // EnsureComercioTimezoneIsConfigured (routes/tenant.php) redirige
        // solo a /zona-horaria en la siguiente request, porque un comercio
        // recién creado nunca la tiene configurada.
        return redirect()->route('panel');
    }
}
