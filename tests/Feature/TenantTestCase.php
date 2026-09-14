<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ProvisionaTenantConBaseReal;
use Tests\TestCase;

/**
 * Base para tests de features tenant-scoped (Productos, Ventas, Clientes).
 *
 * La app ya no ejecuta CREATE/DROP DATABASE (ver CLAUDE.md, decisión "sin
 * CREATE DATABASE en la app" y TenancyServiceProvider) — en producción,
 * PyFsa crea la base a mano en el panel del hosting antes de dar de alta
 * un comercio (ver Admin\ComercioController). Acá se hace lo mismo que va
 * a hacer PyFsa a mano: ProvisionaTenantConBaseReal crea una base con
 * nombre único contra la conexión central (en local, el usuario `root` sí
 * tiene ese permiso) y apunta el Comercio ahí con
 * Comercio::asignarBaseDeDatos() — de ahí en más, Jobs\MigrateDatabase
 * sigue corriendo automático como siempre, sin cambios.
 *
 * setUp crea la base + el Comercio + un User asociado e inicializa la
 * tenancy; tearDown termina la tenancy y borra el Comercio (fila central,
 * ya no dispara ningún DROP DATABASE) + la base con una sentencia directa.
 * Cada test corre contra su propia base de datos MySQL real, creada y
 * destruida al vuelo.
 */
abstract class TenantTestCase extends TestCase
{
    use ProvisionaTenantConBaseReal;
    use RefreshDatabase;

    protected Comercio $comercio;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->comercio = $this->crearComercioConBaseReal();

            // La zona horaria se elige una sola vez, al primer uso (ver
            // EnsureComercioTimezoneIsConfigured) — sin esto, CUALQUIER test
            // que pegue contra una ruta del panel quedaría redirigido a
            // /zona-horaria en vez de ver la respuesta que espera. El propio
            // gate y el flujo de configuración tienen su test dedicado en
            // ZonaHorariaTest, que arma su comercio SIN zona horaria a propósito.
            //
            // estado_suscripcion por el mismo motivo: el default 'prueba' de la
            // migración se aplica en la base al hacer el INSERT de arriba, pero
            // este objeto en memoria queda con estado_suscripcion en null (no
            // relee la fila después del INSERT). Como tenancy()->initialize()
            // (abajo) deja ESTE mismo objeto instalado durante todo el test
            // (Tenancy::initialize() no lo reemplaza si ya está inicializado
            // con la misma key — ver vendor/stancl/tenancy/src/Tenancy.php),
            // sin esto tieneAccesoActivo() daría false y CUALQUIER test que
            // pegue contra una ruta del panel quedaría redirigido a
            // /suscripcion-vencida. El propio gate tiene su test dedicado en
            // SuscripcionTest.
            $this->comercio->timezone = 'America/Argentina/Buenos_Aires';
            $this->comercio->estado_suscripcion = Comercio::ESTADO_PRUEBA;
            $this->comercio->save();

            // rol explícito en 'dueño' (aunque sea el default de la migración):
            // el objeto en memoria de create() nunca relee el default aplicado
            // por la DB (mismo motivo que el comentario de estado_suscripcion
            // arriba), y actingAs() usa esta instancia tal cual, sin releerla
            // — sin esto, $this->user->esDueno() daría false en cualquier test
            // que dependa del gate de rol (ver RolTest), aunque la fila en la
            // base sí tenga 'dueño'.
            $this->user = User::factory()->create([
                'comercio_id' => $this->comercio->id,
                'rol' => User::ROL_DUENO,
            ]);

            tenancy()->initialize($this->comercio);
        } catch (\Throwable $e) {
            // Si algo de lo de arriba falla a mitad (el User no se pudo
            // crear, por ejemplo), PHPUnit NO va a llamar a tearDown() para
            // este test (setUp() tirando marca el test como error y listo)
            // — sin este catch, la base recién creada quedaría huérfana.
            $this->borrarComerciosConBaseReal();

            throw $e;
        }
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        // Dispara TenantDeleted (pipeline vacío desde que se sacó
        // Jobs\DeleteDatabase, ver TenancyServiceProvider) — solo borra la
        // fila central, la base se borra aparte, abajo.
        $this->comercio->delete();

        $this->borrarComerciosConBaseReal();

        parent::tearDown();
    }
}
