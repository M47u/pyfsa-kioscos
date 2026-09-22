<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Roles dueño/empleado (documento de alcance, módulo 3.5 "Usuarios"): sin
 * permisos granulares, un empleado solo vende y cobra fiado; todo lo demás
 * (Productos, Reportes, Zona horaria, gestión de Usuarios) es dueño-only.
 * Ver EnsureUserIsDueno y el sub-grupo dueño-only en routes/tenant.php.
 */
class RolTest extends TenantTestCase
{
    /**
     * `$this->user` (TenantTestCase::setUp()) ya fuerza `rol` a 'dueño' de
     * forma explícita (ver el comentario ahí — Eloquent::create() no relee
     * el default de la DB), así que no sirve para probar el default de la
     * migración en sí. Este test inserta un usuario "a mano" con
     * DB::table() (bypassea $fillable/Eloquent por completo, columna `rol`
     * directamente OMITIDA del INSERT) para simular un usuario YA
     * EXISTENTE antes de que existiera esta columna — el mismo escenario
     * real de la migración: `ALTER TABLE ... ADD COLUMN rol DEFAULT
     * 'dueño'` sobre filas que ya estaban ahí.
     */
    public function test_usuario_existente_sin_rol_explicito_queda_como_dueno_por_default(): void
    {
        $id = DB::connection(config('tenancy.database.central_connection'))->table('users')->insertGetId([
            'name' => 'Usuario Preexistente',
            'email' => 'preexistente@example.com',
            'password' => bcrypt('password'),
            'comercio_id' => $this->comercio->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $usuario = User::find($id);

        $this->assertSame(User::ROL_DUENO, $usuario->rol);
        $this->assertTrue($usuario->esDueno());
    }

    private function crearEmpleado(): User
    {
        return User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);
    }

    private function crearProducto(): Producto
    {
        $producto = Producto::create([
            'nombre' => 'Coca Cola 1.5L',
            'codigo_barras' => '7790895000000',
            'precio_costo' => 800,
            'precio_venta' => 1200,
            'stock_minimo' => 5,
        ]);

        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 20,
            'user_id' => $this->user->id,
        ]);

        return $producto;
    }

    public function test_empleado_recibe_403_en_las_secciones_dueno_only(): void
    {
        $empleado = $this->crearEmpleado();

        $this->actingAs($empleado)->get(route('productos.index'))->assertForbidden();
        $this->actingAs($empleado)->get(route('productos.importar'))->assertForbidden();
        $this->actingAs($empleado)->get(route('reportes.index'))->assertForbidden();
        $this->actingAs($empleado)->get(route('zona-horaria.edit'))->assertForbidden();
        $this->actingAs($empleado)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_empleado_puede_vender_y_cobrar_fiado_sin_restriccion(): void
    {
        $empleado = $this->crearEmpleado();
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        $this->actingAs($empleado)->get(route('panel'))->assertOk();

        // Caja (POS/UX, gap encontrado por el usuario): compartida
        // dueño+empleado, a diferencia de Productos/Reportes/Usuarios —
        // operar la caja es parte de vender.
        $this->actingAs($empleado)->get(route('caja.show'))->assertOk();

        $this->actingAs($empleado)->get(route('ventas.index'))->assertOk();
        $this->actingAs($empleado)->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2],
            ],
        ])->assertRedirect(route('ventas.index'));

        $this->actingAs($empleado)->get(route('clientes.index'))->assertOk();
        $this->actingAs($empleado)->get(route('clientes.show', $cliente))->assertOk();
        $this->actingAs($empleado)->post(route('clientes.pagos.store', $cliente), [
            'monto' => 100,
        ])->assertRedirect(route('clientes.show', $cliente));
    }

    public function test_dueno_accede_a_todo_sin_restriccion(): void
    {
        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        // Dueño-only.
        $this->actingAs($this->user)->get(route('productos.index'))->assertOk();
        $this->actingAs($this->user)->get(route('reportes.index'))->assertOk();
        $this->actingAs($this->user)->get(route('zona-horaria.edit'))->assertOk();
        $this->actingAs($this->user)->get(route('usuarios.index'))->assertOk();

        // Compartido con empleado.
        $this->actingAs($this->user)->get(route('panel'))->assertOk();
        $this->actingAs($this->user)->get(route('caja.show'))->assertOk();
        $this->actingAs($this->user)->get(route('ventas.index'))->assertOk();
        $this->actingAs($this->user)->get(route('clientes.index'))->assertOk();
        $this->actingAs($this->user)->get(route('clientes.show', $cliente))->assertOk();
    }
}
