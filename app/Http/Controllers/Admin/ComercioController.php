<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComercioCreateRequest;
use App\Http\Requests\Admin\ComercioEstadoRequest;
use App\Models\Comercio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Panel de administración de PyFsa (módulo 3.6 "Suscripciones" del
 * documento de alcance) — NO es una feature para el kiosquero, es cómo
 * PyFsa controla qué comercios tienen acceso pagado. Vive fuera de
 * routes/tenant.php, sin tenancy inicializada (ver routes/web.php y
 * App\Http\Middleware\EnsureUserIsAdmin).
 */
class ComercioController extends Controller
{
    public function index(): View
    {
        $comercios = Comercio::all()->map(function (Comercio $comercio) {
            $comercio->setAttribute(
                'cantidad_usuarios',
                User::where('comercio_id', $comercio->id)->count(),
            );

            return $comercio;
        });

        return view('admin.comercios.index', [
            'comercios' => $comercios,
            'estados' => [
                Comercio::ESTADO_PRUEBA,
                Comercio::ESTADO_ACTIVA,
                Comercio::ESTADO_VENCIDA,
                Comercio::ESTADO_CANCELADA,
            ],
        ]);
    }

    public function update(ComercioEstadoRequest $request, Comercio $comercio): RedirectResponse
    {
        // Leído ANTES del update: después ya no hay forma de saber de dónde
        // venía, y "de vencida a activa" es justamente el dato que hace
        // útil al registro (ver RegistroAuditoria).
        $estadoAnterior = $comercio->estado_suscripcion;

        $comercio->update($request->validated());

        RegistroAuditoria::registrar(
            RegistroAuditoria::ACCION_COMERCIO_ESTADO_ACTUALIZADO,
            $comercio->id,
            [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $comercio->estado_suscripcion,
            ],
        );

        return redirect()->route('admin.comercios.index')->with('status', "Comercio {$comercio->id} actualizado.");
    }

    public function create(): View
    {
        return view('admin.comercios.create');
    }

    /**
     * Reemplaza al registro público (ver CLAUDE.md, decisión "sin CREATE
     * DATABASE en la app"): la base ya existe (ComercioCreateRequest la
     * verificó ANTES de llegar acá), esto solo la asigna y da de alta el
     * comercio + su primer dueño.
     *
     * Orden importa, y el rollback NO es automático: crear las tablas en
     * la base del tenant (disparado por $comercio->save() vía
     * TenantCreated -> Jobs\MigrateDatabase) es una conexión/base DISTINTA
     * de la transacción de Eloquent de la línea de abajo — si
     * User::create() fallara después de guardar el Comercio, no hay forma
     * de "deshacer" las migraciones ya corridas con un rollback de DB.
     * Estado resultante ante esa falla: un Comercio sin dueño asociado,
     * pero RECUPERABLE sin tocar nada a mano en la base — como
     * Comercio::delete() ya no dispara ningún DROP DATABASE (ver
     * TenancyServiceProvider), se puede: (a) reintentar el alta del User
     * a mano (tinker) apuntando al mismo comercio_id, o (b) borrar la fila
     * del Comercio y volver a enviar este mismo formulario — la base ya
     * migrada hace que Jobs\MigrateDatabase sea un no-op la segunda vez
     * (las migraciones que ya corrieron se saltean solas).
     */
    public function store(ComercioCreateRequest $request): RedirectResponse
    {
        $comercio = new Comercio(['nombre' => $request->validated('nombre_comercio')]);
        $comercio->asignarBaseDeDatos($request->validated('nombre_base'));
        $comercio->save();

        $dueno = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_DUENO,
        ]);

        // Después del User::create() a propósito: si esa línea falla, el
        // alta quedó a medias (ver el docblock de arriba) y no corresponde
        // registrarla como un comercio creado. `detalles` guarda el email
        // del dueño y el nombre de la base porque son los dos datos que no
        // se pueden reconstruir después si el comercio termina borrado.
        RegistroAuditoria::registrar(
            RegistroAuditoria::ACCION_COMERCIO_CREADO,
            $comercio->id,
            [
                'nombre_comercio' => $comercio->nombre,
                'nombre_base' => $request->validated('nombre_base'),
                'email_dueno' => $dueno->email,
            ],
        );

        return redirect()->route('admin.comercios.index')
            ->with('status', "Comercio \"{$comercio->nombre}\" creado correctamente.");
    }
}
