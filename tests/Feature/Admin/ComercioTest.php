<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Comercio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ProvisionaTenantConBaseReal;
use Tests\TestCase;

/**
 * Panel admin de PyFsa (/admin/comercios, ver routes/web.php) — rutas
 * CENTRALES, no tenant-scoped. No hereda de TenantTestCase a propósito
 * (mismo motivo que WelcomeTest): no hace falta tenancy()->initialize()
 * de un comercio en particular para pegarle a estas rutas.
 *
 * No aplica acá la regresión de binding de tenant que sí tienen
 * ProductoTest/PagoTest (SubstituteBindings corriendo antes que
 * InitializeTenancyByAuthenticatedUser): Comercio usa CentralConnection
 * (ver vendor/stancl/tenancy), que fuerza SIEMPRE la conexión central sin
 * importar el orden de middlewares — y estas rutas ni siquiera pasan por
 * InitializeTenancyByAuthenticatedUser.
 *
 * Los tests de comercios.create/comercios.store reemplazan a los que
 * antes vivían en RegistroTest (registro público, eliminado — ver
 * CLAUDE.md, decisión "sin CREATE DATABASE en la app"): la diferencia de
 * fondo es que ya no hay auto-login del dueño recién creado, porque esto
 * ya no lo hace el propio dueño, lo hace PyFsa desde el panel admin.
 */
class ComercioTest extends TestCase
{
    use ProvisionaTenantConBaseReal;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Comercio::delete() ya NO dispara ningún DROP DATABASE (ver
        // TenancyServiceProvider) — borrar la fila central y borrar la
        // base de datos real son dos pasos independientes de acá en
        // adelante. borrarComerciosConBaseReal() también limpia las bases
        // "sueltas" creadas con crearBaseDeDatosSuelta() que nunca
        // llegaron a tener un Comercio (el caso de "la base no existe").
        Comercio::all()->each->delete();
        $this->borrarComerciosConBaseReal();

        parent::tearDown();
    }

    public function test_usuario_sin_is_admin_no_puede_entrar_al_panel_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.comercios.index'));

        $response->assertForbidden();
    }

    public function test_usuario_sin_is_admin_no_puede_ver_el_formulario_de_alta(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.comercios.create'))->assertForbidden();
    }

    public function test_usuario_sin_is_admin_no_puede_dar_de_alta_un_comercio(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $response = $this->actingAs($user)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Prohibido',
            'nombre_base' => $nombreBase,
            'name' => 'Dueño Colado',
            'email' => 'colado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Comercio::count());
        $this->assertNull(User::where('email', 'colado@example.com')->first());
    }

    public function test_usuario_admin_puede_ver_el_listado_de_comercios(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();

        $response = $this->actingAs($admin)->get(route('admin.comercios.index'));

        $response->assertOk();
        $response->assertSee($comercio->id);
    }

    public function test_usuario_admin_puede_cambiar_el_estado_de_un_comercio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();

        $response = $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            'estado_suscripcion' => Comercio::ESTADO_ACTIVA,
            'trial_termina_el' => null,
        ]);

        $response->assertRedirect(route('admin.comercios.index'));
        $this->assertSame(Comercio::ESTADO_ACTIVA, $comercio->fresh()->estado_suscripcion);
    }

    /**
     * Regresión del listado: la cantidad de usuarios se cuenta con una
     * query directa (User::where('comercio_id', ...)), no con una relación
     * Eloquent nueva.
     */
    public function test_el_listado_muestra_la_cantidad_de_usuarios_por_comercio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();
        User::factory()->count(2)->create(['comercio_id' => $comercio->id]);

        $response = $this->actingAs($admin)->get(route('admin.comercios.index'));

        $response->assertOk();
        $response->assertSee('2');
    }

    /**
     * Regresión del listado: el correo mostrado es el del DUEÑO (rol
     * explícito), no cualquier usuario del comercio — un empleado de
     * relleno en la misma lista no debe aparecer en su lugar.
     */
    public function test_el_listado_muestra_el_correo_del_dueno(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();
        User::factory()->create([
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_EMPLEADO,
            'email' => 'empleado@example.com',
        ]);
        User::factory()->create([
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_DUENO,
            'email' => 'dueno@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.comercios.index'));

        $response->assertOk();
        $response->assertSee('dueno@example.com');
    }

    public function test_usuario_admin_puede_restablecer_la_password_del_dueno(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();
        $dueno = User::factory()->create([
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_DUENO,
            'password' => 'password-vieja',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.comercios.restablecer-password', $comercio));

        $response->assertRedirect(route('admin.comercios.index'));
        $response->assertSessionHas('password_generada.email', $dueno->email);

        $nueva = session('password_generada')['password'];
        $this->assertTrue(Hash::check($nueva, $dueno->fresh()->password));
        $this->assertNotSame('password-vieja', $nueva);

        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => RegistroAuditoria::ACCION_COMERCIO_PASSWORD_RESETEADA,
            'comercio_id' => $comercio->id,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Estado recuperable pero real (ver el docblock de
     * ComercioController::store()): un comercio sin dueño no puede tirar un
     * 500 al intentar restablecerle una contraseña a nadie.
     */
    public function test_restablecer_password_falla_sin_romper_si_el_comercio_no_tiene_dueno(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();

        $response = $this->actingAs($admin)->post(route('admin.comercios.restablecer-password', $comercio));

        $response->assertSessionHasErrors('comercio');
        $this->assertDatabaseMissing('registros_auditoria', [
            'accion' => RegistroAuditoria::ACCION_COMERCIO_PASSWORD_RESETEADA,
        ]);
    }

    public function test_usuario_sin_is_admin_no_puede_restablecer_password(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $comercio = $this->crearComercioConBaseReal();
        $dueno = User::factory()->create([
            'comercio_id' => $comercio->id,
            'rol' => User::ROL_DUENO,
            'password' => 'password-vieja',
        ]);

        $response = $this->actingAs($user)->post(route('admin.comercios.restablecer-password', $comercio));

        $response->assertForbidden();
        $this->assertTrue(Hash::check('password-vieja', $dueno->fresh()->password));
    }

    /**
     * El caso que justifica el chequeo de accesibilidad en
     * ComercioCreateRequest::withValidator(): un typo en el nombre de la
     * base tiene que ser un error de formulario, NO un comercio a medio
     * crear. Se verifica explícitamente que no queda ni Comercio ni User.
     */
    public function test_falla_si_la_base_indicada_no_existe(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Fantasma',
            'nombre_base' => 'esta_base_no_existe_'.uniqid(),
            'name' => 'Dueño Fantasma',
            'email' => 'fantasma@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('nombre_base');
        $this->assertSame(0, Comercio::count());
        $this->assertNull(User::where('email', 'fantasma@example.com')->first());
    }

    public function test_falla_si_el_email_del_dueno_ya_esta_en_uso(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['email' => 'repetido@example.com']);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $response = $this->actingAs($admin)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Repetido',
            'nombre_base' => $nombreBase,
            'name' => 'Otro Dueño',
            'email' => 'repetido@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, Comercio::count());
    }

    public function test_falla_si_las_contrasenas_no_coinciden(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $response = $this->actingAs($admin)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Contraseña',
            'nombre_base' => $nombreBase,
            'name' => 'Dueño Distraído',
            'email' => 'distraido@example.com',
            'password' => 'password123',
            'password_confirmation' => 'otra-cosa',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, Comercio::count());
    }

    /**
     * El caso feliz completo: además de crear el Comercio y el dueño, hay
     * que confirmar que las migraciones de tenant CORRIERON DE VERDAD
     * contra la base indicada — no alcanza con confiar en que
     * $comercio->save() las dispara, se verifica consultando una tabla
     * real (`productos`) directamente contra esa base.
     */
    public function test_admin_crea_un_comercio_nuevo_con_su_dueno(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $response = $this->actingAs($admin)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Nuevo',
            'nombre_base' => $nombreBase,
            'name' => 'Dueño Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.comercios.index'));

        // `nombre` no es una columna real de `tenants` (vive en el JSON
        // `data`, ver VirtualColumn) — no se puede filtrar por WHERE en
        // SQL, hay que traer todo y filtrar en memoria.
        $comercio = Comercio::all()->firstWhere('nombre', 'Kiosco Nuevo');
        $this->assertNotNull($comercio);

        $dueno = User::where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($dueno);
        $this->assertSame($comercio->id, $dueno->comercio_id);
        $this->assertSame(User::ROL_DUENO, $dueno->rol);

        // Conexión directa a la base indicada (no la de tenancy, para no
        // depender de que asignarBaseDeDatos()/getName() ya se haya
        // ejercitado antes en este mismo test) — si las migraciones no
        // corrieron, esta tabla no existe.
        $central = config('tenancy.database.central_connection');
        config(["database.connections.verificacion_migraciones" => array_merge(
            config("database.connections.{$central}"),
            ['database' => $nombreBase],
        )]);

        $tieneTablaProductos = DB::connection('verificacion_migraciones')
            ->getSchemaBuilder()
            ->hasTable('productos');

        DB::purge('verificacion_migraciones');

        $this->assertTrue($tieneTablaProductos, 'Las migraciones de tenant no corrieron contra la base indicada.');
    }
}
