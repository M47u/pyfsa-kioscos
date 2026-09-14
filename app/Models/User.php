<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class User extends Authenticatable
{
    /**
     * `users` es CENTRAL (a diferencia de Producto/Cliente/Venta), pero
     * UsuarioController vive dentro de routes/tenant.php: para cuando su
     * store()/index() corren, InitializeTenancyByAuthenticatedUser YA
     * inicializó la tenancy, y esa inicialización cambia la conexión
     * DEFAULT de Eloquent a la del tenant (ver Tenancy::initialize()).
     * Sin este trait, `User::create(...)` ahí intentaría insertar en la
     * base del TENANT (que no tiene tabla `users` — solo tiene productos/
     * clientes/ventas, ver database/migrations/tenant/) y explotaría con
     * "table not found". Mismo mecanismo que ya usa Comercio para forzar
     * SIEMPRE la conexión central sin importar el estado de la tenancy
     * (ver vendor/stancl/tenancy, Comercio extiende BaseTenant que ya lo
     * trae) — acá hay que pedirlo explícito porque User no extiende nada
     * de stancl/tenancy.
     */
    use CentralConnection;

    /**
     * Eliminación lógica (documento de alcance, módulo 3.5 "Usuarios" —
     * ver UsuarioController::destroy()): un empleado "eliminado" nunca se
     * borra de verdad, sigue siendo el `user_id` de sus ventas/pagos
     * históricos. `deleted_at` también saca al usuario del guard de auth
     * sin nada extra que hacer: Auth::attempt() resuelve por Eloquent, que
     * respeta este scope global — un usuario soft-deleted no puede volver
     * a loguearse solo por tener este trait, no hace falta chequearlo a
     * mano en LoginRequest.
     */
    use SoftDeletes;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Roles dentro de un comercio (documento de alcance, módulo 3.5
     * "Usuarios"): "Dueño y empleados, cada uno con su propio acceso. Sin
     * permisos granulares — el dueño ve todo, el empleado vende y cobra
     * fiado." Los nombres de constante van sin ñ (identificadores PHP),
     * los valores sí llevan ñ porque son el string real de negocio.
     */
    public const ROL_DUENO = 'dueño';

    public const ROL_EMPLEADO = 'empleado';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'comercio_id',
        'rol',
    ];

    /**
     * El comercio (tenant) al que pertenece este usuario. Es lo que usa
     * InitializeTenancyByAuthenticatedUser para inicializar la tenancy.
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    /**
     * El dueño ve todo (Productos, Reportes, Zona horaria, Usuarios) además
     * de Ventas y Clientes. Usado por el gate EnsureUserIsDueno y por el nav
     * para ocultar las secciones dueño-only a un empleado.
     */
    public function esDueno(): bool
    {
        return $this->rol === self::ROL_DUENO;
    }

    /**
     * El empleado solo vende y cobra fiado (Ventas + Clientes) — sin
     * permisos granulares, ver documento de alcance módulo 3.5.
     */
    public function esEmpleado(): bool
    {
        return $this->rol === self::ROL_EMPLEADO;
    }

    /**
     * A dónde mandar a este usuario cuando la app tiene que llevarlo "a su
     * home": después de un login exitoso (AuthenticatedSessionController)
     * y cuando el middleware 'guest' rebota a alguien ya logueado que entra
     * a /login (RedirectIfAuthenticated, ver AppServiceProvider::boot()).
     *
     * Bug real que resuelve: /panel vive en routes/tenant.php, detrás de
     * InitializeTenancyByAuthenticatedUser, que hace abort 403 si el
     * usuario no tiene `comercio_id`. Un admin de plataforma provisionado
     * con `admin:crear` NO tiene comercio (es el caso normal, ver ese
     * comando), así que lograba loguearse y caía en un 403 en vez de
     * llegar a algún lado usable.
     *
     * La condición es "admin Y sin comercio", no "admin": un admin de
     * PyFsa que ADEMÁS es dueño de su propio comercio (caso legítimo y
     * explícitamente soportado por admin:crear) sigue entrando a /panel
     * como cualquier usuario, y llega al panel admin por el nav.
     */
    public function rutaDeInicio(): string
    {
        return $this->is_admin && blank($this->comercio_id)
            ? route('admin.comercios.index')
            : route('panel');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
