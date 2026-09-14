<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Provisiona un administrador de PLATAFORMA (PyFsa), no de un comercio —
 * es el flag `is_admin` que gatea /admin/comercios vía EnsureUserIsAdmin,
 * distinto del rol dueño/empleado de User::ROL_* (que es a nivel comercio).
 *
 * Reemplaza al snippet de tinker que estaba documentado en CLAUDE.md y que
 * NUNCA funcionó: `User::where(...)->update(['is_admin' => true])` respeta
 * $fillable, y `is_admin` no está ahí — era un no-op silencioso (ni error
 * ni cambio). La decisión de NO sumarlo a $fillable se mantiene a
 * propósito: es un flag de plataforma, con acceso a TODOS los comercios;
 * dejarlo fuera de mass-assignment evita que un `User::create($request->
 * all())` futuro lo abra sin querer. Este comando lo setea por asignación
 * directa de propiedad, que es el único camino que se salta $fillable sin
 * desactivarlo para nadie más.
 *
 * A propósito NO hay pantalla web para esto: crear un admin es una acción
 * de provisioning con acceso a la base, no una feature del producto.
 */
class CrearAdminCommand extends Command
{
    protected $signature = 'admin:crear
        {email : Email del administrador (si ya existe como usuario, lo promueve)}
        {--nombre= : Nombre a mostrar — solo se usa si hay que crear el usuario}
        {--password= : Contraseña — solo se usa si hay que crear el usuario}';

    protected $description = 'Crea o promueve a un usuario como administrador de plataforma (is_admin).';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! $this->esValido(['email' => $email], ['email' => ['required', 'email']])) {
            return self::FAILURE;
        }

        // withTrashed() a propósito: `users` tiene SoftDeletes (ver el
        // modelo), así que un email "libre" a los ojos de una query normal
        // puede seguir ocupando la fila con el UNIQUE de la columna. Sin
        // esto, promover a alguien dado de baja terminaría en un
        // SQLSTATE[23000] ilegible en vez de un mensaje útil.
        $usuario = User::withTrashed()->where('email', $email)->first();

        return $usuario === null
            ? $this->crearAdmin($email)
            : $this->promoverAdmin($usuario);
    }

    /**
     * Camino "el usuario ya existe": no se le tocan ni el nombre ni la
     * contraseña (para eso están el panel del dueño y el propio usuario) —
     * si vinieron esas opciones, se avisa que se ignoran en vez de
     * aplicarlas en silencio.
     */
    private function promoverAdmin(User $usuario): int
    {
        foreach (['nombre', 'password'] as $opcion) {
            if ($this->option($opcion) !== null) {
                $this->warn("--{$opcion} se ignora: el usuario {$usuario->email} ya existe y este comando no le cambia los datos, solo lo promueve a admin.");
            }
        }

        $estabaDadoDeBaja = $usuario->trashed();

        if ($estabaDadoDeBaja) {
            $usuario->restore();
            $this->warn("El usuario {$usuario->email} estaba dado de baja (eliminación lógica) y se restauró para poder promoverlo.");
        }

        // Un admin de PyFsa PUEDE además ser dueño de su propio comercio de
        // prueba — es un caso legítimo (probar el producto con datos reales
        // sin una segunda cuenta), así que se avisa y se sigue, no se
        // bloquea. Vale la pena avisarlo igual: esa cuenta pasa a tener a
        // la vez acceso a su comercio y al panel de TODOS los comercios.
        if ($usuario->comercio_id !== null) {
            $this->warn("Atención: {$usuario->email} pertenece al comercio {$usuario->comercio_id} — va a tener acceso a su comercio Y al panel admin de todos los comercios.");
        }

        if ($usuario->is_admin) {
            // Un no-op de verdad no se audita (ensuciaría el log con filas
            // que no son un cambio). Pero si la fila venía dada de baja,
            // restaurarla SÍ fue un cambio real — y devolverle el acceso a
            // todos los comercios a una cuenta que estaba desactivada es
            // exactamente lo que un log de seguridad tiene que mostrar.
            if ($estabaDadoDeBaja) {
                $this->auditarPromocion($usuario, restaurado: true, yaEraAdmin: true);
            }

            $this->info("{$usuario->email} ya era administrador. No hay nada que hacer.");

            return self::SUCCESS;
        }

        $this->marcarComoAdmin($usuario);
        $this->auditarPromocion($usuario, restaurado: $estabaDadoDeBaja, yaEraAdmin: false);

        $this->info("{$usuario->email} ahora es administrador de plataforma.");

        return self::SUCCESS;
    }

    /**
     * Camino "el usuario no existe": hacen falta nombre y contraseña. El
     * nombre se pide interactivo si no vino por opción; para la contraseña
     * ver el docblock de resolverPassword().
     */
    private function crearAdmin(string $email): int
    {
        $nombre = $this->resolverNombre();

        if ($nombre === null) {
            $this->error('Falta el nombre del administrador: pasalo con --nombre= (es obligatorio cuando el usuario todavía no existe).');

            return self::FAILURE;
        }

        $password = $this->resolverPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        // `password` viaja en texto plano: el cast 'hashed' de User lo pasa
        // por Hash::make al guardar (mismo mecanismo que UsuarioController
        // y Admin\ComercioController — nunca se guarda texto plano).
        // `comercio_id` queda null: un admin de plataforma no pertenece a
        // ningún comercio salvo que después se lo asigne a mano.
        $usuario = User::create([
            'name' => $nombre,
            'email' => $email,
            'password' => $password,
        ]);

        $this->marcarComoAdmin($usuario);

        // Después de marcarComoAdmin() a propósito, mismo criterio que
        // Admin\ComercioController::store(): si el save() fallara, no
        // corresponde registrar un admin que no quedó creado.
        RegistroAuditoria::registrar(
            RegistroAuditoria::ACCION_ADMIN_CREADO,
            detalles: [
                'email' => $usuario->email,
                'nombre' => $usuario->name,
                'usuario_id' => $usuario->id,
            ],
        );

        $this->info("Administrador {$email} creado correctamente.");

        return self::SUCCESS;
    }

    private function resolverNombre(): ?string
    {
        $nombre = trim((string) $this->option('nombre'));

        if ($nombre === '' && $this->input->isInteractive()) {
            $nombre = trim((string) $this->ask('Nombre del administrador'));
        }

        return $nombre === '' ? null : $nombre;
    }

    /**
     * Tres caminos, en orden de preferencia:
     *
     * 1. `--password=` explícita. Se avisa que queda en el historial de la
     *    shell — no se bloquea, a veces es lo que se quiere (scripts).
     * 2. Terminal interactiva: se pide con secret() y se confirma. Es el
     *    default a propósito — la contraseña no queda ni en el historial
     *    (a diferencia de --password) ni en el scrollback (a diferencia de
     *    una generada e impresa).
     * 3. Sin terminal interactiva (CI, un script, `--no-interaction`): se
     *    genera una random segura y se imprime UNA vez, porque no hay forma
     *    de preguntar y fallar ahí sería peor (dejaría el provisioning a
     *    medias sin alternativa).
     *
     * Los caminos 1 y 2 pasan por reglasDePassword() antes de devolver: el
     * comando exige lo mismo que el formulario web. El 3 no hace falta
     * validarlo — Str::password(20) genera por construcción algo más
     * fuerte que cualquier default razonable.
     *
     * Dónde se valida y dónde no, a propósito: la validación vive acá y no
     * al principio de handle() porque este método solo corre en el camino
     * "hay que crear el usuario". Si el usuario YA existe, --password se
     * ignora (se avisa en promoverAdmin()) y no tendría sentido abortar
     * una promoción por una opción que no se va a usar.
     *
     * @return string|null null = no se pudo resolver, abortar.
     */
    private function resolverPassword(): ?string
    {
        $password = (string) $this->option('password');

        if ($password !== '') {
            $this->warn('Ojo: la contraseña pasada por --password queda registrada en el historial de la shell.');

            return $this->esValido(['password' => $password], self::reglasDePassword()) ? $password : null;
        }

        if (! $this->input->isInteractive()) {
            $generada = Str::password(20);

            $this->warn('Contraseña generada (se muestra UNA sola vez, guardala ahora):');
            $this->line($generada);

            return $generada;
        }

        $password = (string) $this->secret('Contraseña del administrador');
        $confirmacion = (string) $this->secret('Repetí la contraseña');

        if ($password === '' || $password !== $confirmacion) {
            $this->error('Las contraseñas no coinciden (o quedó vacía). No se creó nada.');

            return null;
        }

        return $this->esValido(['password' => $password], self::reglasDePassword()) ? $password : null;
    }

    /**
     * Las mismas reglas que exige el camino web (ver
     * Admin\ComercioCreateRequest y UsuarioRequest): sin esto, el comando
     * aceptaba cualquier string como contraseña de la cuenta MÁS
     * privilegiada del sistema, mientras el formulario de alta de un
     * empleado cualquiera sí exigía Password::defaults(). Password::
     * defaults() y no una copia de las reglas para que endurecerlas un día
     * (longitud mínima, listas de filtradas) alcance a los dos caminos de
     * una sola vez.
     *
     * @return array<string, mixed>
     */
    private static function reglasDePassword(): array
    {
        return ['password' => ['required', Password::defaults()]];
    }

    /**
     * Valida y, si falla, imprime TODOS los mensajes antes de devolver
     * false — la validación de un comando de provisioning tiene que decir
     * de una qué está mal, no obligar a descubrirlo de a un error por
     * corrida. No se toca la base: todos los call sites abortan con
     * FAILURE antes de cualquier escritura.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $reglas
     */
    private function esValido(array $datos, array $reglas): bool
    {
        $validador = Validator::make($datos, $reglas);

        if ($validador->passes()) {
            return true;
        }

        foreach ($validador->errors()->all() as $mensaje) {
            $this->error($mensaje);
        }

        return false;
    }

    /**
     * El único mecanismo de la app que setea is_admin. Asignación directa
     * de propiedad + save(): NO pasa por fill()/mass-assignment, así que
     * `is_admin` puede quedar fuera de User::$fillable (ver el docblock de
     * la clase).
     */
    private function marcarComoAdmin(User $usuario): void
    {
        $usuario->is_admin = true;
        $usuario->save();
    }

    /**
     * `comercio_id` queda null a propósito: promover a un admin no es una
     * acción SOBRE un comercio, es sobre la plataforma entera (aunque el
     * usuario promovido pertenezca a uno, dato que igual queda en
     * `detalles`). `user_id` también queda null — lo resuelve
     * RegistroAuditoria::registrar() desde auth(), y en consola no hay
     * sesión; lo mismo con `ip`/`user_agent`.
     */
    private function auditarPromocion(User $usuario, bool $restaurado, bool $yaEraAdmin): void
    {
        RegistroAuditoria::registrar(
            RegistroAuditoria::ACCION_ADMIN_PROMOVIDO,
            detalles: [
                'email' => $usuario->email,
                'usuario_id' => $usuario->id,
                'restaurado_de_baja' => $restaurado,
                'ya_era_admin' => $yaEraAdmin,
                'comercio_id_del_usuario' => $usuario->comercio_id,
            ],
        );
    }
}
