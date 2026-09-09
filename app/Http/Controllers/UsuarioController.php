<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioPasswordRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Gestión de empleados dentro de un comercio (documento de alcance, módulo
 * 3.5 "Usuarios"). Dueño-only (ver EnsureUserIsDueno en routes/tenant.php).
 * Sin edición de datos ni baja en esta versión — solo alta y restablecer
 * contraseña; el dueño la define directo, sin invitación por email.
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        $usuarios = User::query()
            ->where('comercio_id', tenant('id'))
            ->orderBy('name')
            ->get();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'usuario' => new User,
        ]);
    }

    /**
     * `rol` y `comercio_id` nunca vienen del form (ver UsuarioRequest): se
     * fuerzan acá para que no se pueda dar de alta otro dueño ni un usuario
     * de otro comercio. `password` viaja en texto plano — el cast
     * `'password' => 'hashed'` de User lo hashea solo al guardar.
     */
    public function store(UsuarioRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'comercio_id' => tenant('id'),
            'rol' => User::ROL_EMPLEADO,
        ]);

        return redirect()->route('usuarios.index')->with('status', 'Empleado creado correctamente.');
    }

    public function editPassword(User $usuario): View
    {
        $this->autorizarMismoComercio($usuario);

        return view('usuarios.password', [
            'usuario' => $usuario,
        ]);
    }

    public function updatePassword(UsuarioPasswordRequest $request, User $usuario): RedirectResponse
    {
        $this->autorizarMismoComercio($usuario);

        $usuario->update(['password' => $request->validated('password')]);

        return redirect()->route('usuarios.index')->with('status', "Contraseña de {$usuario->name} actualizada correctamente.");
    }

    /**
     * Eliminación LÓGICA (ver User::class, SoftDeletes): $usuario->delete()
     * solo pone `deleted_at`, nunca borra la fila — sigue siendo el
     * `user_id` de sus ventas/pagos históricos.
     *
     * No se puede eliminar a un dueño: este controller gestiona empleados
     * (ver el docblock de la clase), y solo hay un dueño por comercio —
     * permitirlo abriría la puerta a que el dueño se elimine a sí mismo
     * (esta misma cuenta con la que está logueado ahora) y quede el
     * comercio sin nadie que pueda administrarlo.
     */
    public function destroy(User $usuario): RedirectResponse
    {
        $this->autorizarMismoComercio($usuario);
        abort_if($usuario->esDueno(), 403, 'No se puede eliminar al dueño del comercio.');

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', "Empleado {$usuario->name} eliminado correctamente.");
    }

    /**
     * `{usuario}` hace binding implícito contra `users`, que es CENTRAL
     * (ver CentralConnection en el modelo) — tiene TODOS los usuarios de
     * TODOS los comercios, no solo el actual. Sin este chequeo, un dueño
     * podría cambiarle la contraseña (o eliminar) a un empleado de otro
     * comercio con solo probar IDs en la URL. `index()` ya filtra por
     * comercio_id al LISTAR; acá hace falta el mismo filtro para una
     * acción sobre UN usuario puntual.
     */
    private function autorizarMismoComercio(User $usuario): void
    {
        abort_if($usuario->comercio_id !== tenant('id'), 403);
    }
}
