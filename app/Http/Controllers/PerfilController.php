<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PerfilPasswordRequest;
use App\Http\Requests\PerfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Mi perfil": dueño y empleado editan sus propios datos y contraseña.
 * `users` es CENTRAL (User usa CentralConnection), por eso funciona aunque
 * la tenancy ya esté inicializada. Solo `name`/`email`/`password` se
 * escriben: rol y comercio_id nunca salen de acá.
 */
class PerfilController extends Controller
{
    public function edit(): View
    {
        return view('perfil.edit', [
            'usuario' => auth()->user(),
        ]);
    }

    public function update(PerfilRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email']));

        return redirect()->route('perfil.edit')->with('status', 'Tus datos se actualizaron correctamente.');
    }

    /**
     * Rota el remember_token y cierra las OTRAS sesiones del usuario (solo
     * con SESSION_DRIVER=database, mismo mecanismo que `admin:crear
     * --reset-password`) para que una sesión robada no sobreviva al cambio;
     * la actual se regenera para no desloguear a quien lo hizo.
     */
    public function updatePassword(PerfilPasswordRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        $usuario->password = $request->validated('password');
        // Asignación directa: debe_cambiar_password no está en $fillable.
        $usuario->debe_cambiar_password = false;
        $usuario->save();

        $usuario->invalidarSesiones($request->session()->getId());

        $request->session()->regenerate(true);

        return redirect()->route('perfil.edit')->with('status', 'Tu contraseña se cambió correctamente.');
    }
}
