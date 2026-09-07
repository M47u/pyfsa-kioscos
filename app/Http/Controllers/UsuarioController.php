<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Gestión de empleados dentro de un comercio (documento de alcance, módulo
 * 3.5 "Usuarios"). Dueño-only (ver EnsureUserIsDueno en routes/tenant.php).
 * Solo alta: sin edición ni baja en esta primera versión — el dueño define
 * la contraseña directo, sin invitación por email.
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
}
