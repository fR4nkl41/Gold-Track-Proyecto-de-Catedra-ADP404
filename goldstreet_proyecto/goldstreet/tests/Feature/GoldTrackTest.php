<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\GoldStreetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de integración de las reglas de negocio del documento:
 * matriz de permisos (4.4), trigger de stock (4.2), validaciones y RF05.
 * Corren sobre SQLite en memoria con las migraciones (phpunit.xml).
 */
class GoldTrackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GoldStreetSeeder::class);
    }

    private function como(string $rol): Usuario
    {
        $u = Usuario::where('rol', $rol)->firstOrFail();
        Sanctum::actingAs($u);

        return $u;
    }

    private function producto(string $codigo): Producto
    {
        return Producto::where('codigo', $codigo)->firstOrFail();
    }

    // ---- Autenticación (RF01) ------------------------------------------------

    public function test_login_correcto_devuelve_token_y_rol(): void
    {
        $this->postJson('/api/login', ['correo' => 'bodega@goldstreet.com', 'contrasena' => 'bodega123'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'bodega')
            ->assertJsonStructure(['token']);
    }

    public function test_login_incorrecto_responde_401(): void
    {
        $this->postJson('/api/login', ['correo' => 'admin@goldstreet.com', 'contrasena' => 'mala'])
            ->assertStatus(401);
    }

    public function test_usuario_inactivo_no_puede_entrar(): void
    {
        Usuario::where('rol', 'cocina')->update(['activo' => false]);

        $this->postJson('/api/login', ['correo' => 'cocina@goldstreet.com', 'contrasena' => 'cocina123'])
            ->assertStatus(403);
    }

    public function test_api_sin_token_responde_401(): void
    {
        $this->getJson('/api/productos')->assertStatus(401);
    }

    // ---- Matriz de permisos (4.4) ---------------------------------------------

    public function test_bodega_registra_entradas_y_salidas(): void
    {
        $this->como('bodega');
        $id = $this->producto('GS-001')->id;

        $this->postJson('/api/movimientos', ['producto_id' => $id, 'tipo' => 'entrada', 'cantidad' => 1])
            ->assertCreated();
        $this->postJson('/api/movimientos', ['producto_id' => $id, 'tipo' => 'salida', 'cantidad' => 1])
            ->assertCreated();
    }

    public function test_cocina_no_puede_registrar_entradas(): void
    {
        $this->como('cocina');

        $this->postJson('/api/movimientos', [
            'producto_id' => $this->producto('GS-001')->id, 'tipo' => 'entrada', 'cantidad' => 1,
        ])->assertStatus(403);
    }

    public function test_solo_admin_gestiona_productos_usuarios_y_reportes(): void
    {
        $this->como('bodega');

        $this->postJson('/api/productos', [])->assertStatus(403);
        $this->getJson('/api/usuarios')->assertStatus(403);
        $this->getJson('/api/reportes/existencias')->assertStatus(403);
        $this->postJson('/api/categorias', ['nombre' => 'X'])->assertStatus(403);
    }

    // ---- Trigger de stock (4.2) y validaciones -------------------------------

    public function test_entrada_y_salida_actualizan_stock_via_trigger(): void
    {
        $this->como('admin');
        $p = $this->producto('GS-001');   // stock 24.5

        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'entrada', 'cantidad' => 5.5])
            ->assertCreated();
        $this->assertEquals(30.0, $p->fresh()->stock);

        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'cantidad' => 10])
            ->assertCreated();
        $this->assertEquals(20.0, $p->fresh()->stock);
    }

    public function test_salida_mayor_al_stock_responde_422_y_no_mueve_stock(): void
    {
        $this->como('cocina');
        $p = $this->producto('GS-005');   // stock 6.5

        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'cantidad' => 7])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cantidad');

        $this->assertEquals(6.5, $p->fresh()->stock);
        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_cantidad_cero_es_rechazada(): void
    {
        $this->como('admin');

        $this->postJson('/api/movimientos', [
            'producto_id' => $this->producto('GS-001')->id, 'tipo' => 'entrada', 'cantidad' => 0,
        ])->assertStatus(422);
    }

    // ---- RF05: alerta cuando stock <= mínimo ----------------------------------

    public function test_stock_igual_al_minimo_genera_alerta(): void
    {
        $this->como('cocina');
        $p = $this->producto('GS-003');   // stock 8.5, mínimo 8

        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'cantidad' => 0.5])
            ->assertCreated()
            ->assertJsonPath('producto.estado_stock', 'bajo')
            ->assertJsonPath('alerta', fn ($a) => $a !== null);
    }

    // ---- Productos (RF02) ----------------------------------------------------

    public function test_stock_inicial_se_registra_como_entrada_en_el_kardex(): void
    {
        $this->como('admin');

        $r = $this->postJson('/api/productos', [
            'codigo' => 'GS-100', 'nombre' => 'Arroz', 'unidad' => 'kg', 'categoria_id' => 4, 'proveedor_id' => 3,
            'stock' => 12, 'stock_minimo' => 5, 'costo' => 1.2,
        ])->assertCreated();

        $this->assertEquals(12.0, $r->json('data.stock'));
        $this->assertDatabaseHas('movimientos', [
            'producto_id' => $r->json('data.id'), 'tipo' => 'entrada', 'referencia' => 'Stock inicial (alta de producto)',
        ]);
    }

    public function test_producto_exige_categoria_proveedor_y_unidad_del_catalogo(): void
    {
        $this->como('admin');

        $this->postJson('/api/productos', [
            'codigo' => 'GS-101', 'nombre' => 'Sal', 'unidad' => 'kilos',
            'stock' => 1, 'stock_minimo' => 1, 'costo' => 0.5,
        ])->assertStatus(422)->assertJsonValidationErrors(['unidad', 'categoria_id', 'proveedor_id']);
    }

    public function test_editar_producto_no_modifica_el_stock(): void
    {
        $this->como('admin');
        $p = $this->producto('GS-001');

        $this->putJson("/api/productos/{$p->id}", [
            'codigo' => $p->codigo, 'nombre' => 'Lomo editado', 'unidad' => 'kg', 'categoria_id' => 1, 'proveedor_id' => 1,
            'stock' => 999, 'stock_minimo' => 10, 'costo' => 9.5,
        ])->assertOk();

        $this->assertEquals(24.5, $p->fresh()->stock);
        $this->assertSame('Lomo editado', $p->fresh()->nombre);
    }

    public function test_baja_logica_conserva_el_producto(): void
    {
        $this->como('admin');
        $p = $this->producto('GS-002');

        $this->deleteJson("/api/productos/{$p->id}")->assertOk();
        $this->assertSame('inactivo', $p->fresh()->estado);
    }

    // ---- Filtros, dashboard y reportes ---------------------------------------

    public function test_movimientos_se_filtran_por_tipo(): void
    {
        $this->como('admin');
        $id = $this->producto('GS-001')->id;
        $this->postJson('/api/movimientos', ['producto_id' => $id, 'tipo' => 'entrada', 'cantidad' => 1]);
        $this->postJson('/api/movimientos', ['producto_id' => $id, 'tipo' => 'salida', 'cantidad' => 1]);

        $this->getJson('/api/movimientos?tipo=salida')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.tipo', 'salida');
    }

    public function test_dashboard_separa_entradas_y_salidas_del_dia(): void
    {
        $this->como('admin');
        $id = $this->producto('GS-001')->id;
        $this->postJson('/api/movimientos', ['producto_id' => $id, 'tipo' => 'entrada', 'cantidad' => 2]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('resumen.entradasHoy', 1)
            ->assertJsonPath('resumen.salidasHoy', 0)
            ->assertJsonCount(7, 'consumoSemanal');
    }

    public function test_kardex_reconstruye_el_saldo(): void
    {
        $this->como('admin');
        $p = $this->producto('GS-007');   // stock 30
        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'entrada', 'cantidad' => 10]);
        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'cantidad' => 4]);

        $this->getJson("/api/reportes/kardex/{$p->id}")
            ->assertOk()
            ->assertJsonPath('saldoInicial', 30)
            ->assertJsonPath('data.0.saldo', 40)
            ->assertJsonPath('data.1.saldo', 36);
    }

    // ---- Mermas -------------------------------------------------------------

    public function test_bodega_puede_registrar_merma_y_descuenta_stock(): void
    {
        $this->como('bodega');
        $p = $this->producto('GS-002');   // stock 12

        $this->postJson('/api/movimientos', [
            'producto_id' => $p->id, 'tipo' => 'salida', 'causa' => 'vencido', 'cantidad' => 2, 'referencia' => 'Lote vencido',
        ])->assertCreated()->assertJsonPath('data.clase', 'merma');

        $this->assertEquals(10.0, $p->fresh()->stock);
    }

    public function test_merma_por_empaque_danado_o_desperdicio(): void
    {
        $this->como('cocina');
        $p = $this->producto('GS-002');   // stock 12

        foreach (['empaque', 'desperdicio'] as $causa) {
            $this->postJson('/api/movimientos', [
                'producto_id' => $p->id, 'tipo' => 'salida', 'causa' => $causa, 'cantidad' => 1,
            ])->assertCreated()->assertJsonPath('data.clase', 'merma');
        }

        $this->assertEquals(10.0, $p->fresh()->stock);
        $causas = collect($this->getJson('/api/movimientos?tipo=merma')->assertJsonPath('total', 2)->json('data'))->pluck('causa');
        $this->assertEqualsCanonicalizing(['Empaque dañado', 'Desperdicio'], $causas->all());
    }

    public function test_entrada_no_puede_llevar_causa_de_merma(): void
    {
        $this->como('admin');

        $this->postJson('/api/movimientos', [
            'producto_id' => $this->producto('GS-001')->id, 'tipo' => 'entrada', 'causa' => 'danado', 'cantidad' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('causa');
    }

    public function test_mermas_se_separan_del_consumo_en_filtros_y_reportes(): void
    {
        $this->como('admin');
        $p = $this->producto('GS-001');   // costo 9.50
        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'cantidad' => 3]);
        $this->postJson('/api/movimientos', ['producto_id' => $p->id, 'tipo' => 'salida', 'causa' => 'danado', 'cantidad' => 2]);

        $this->getJson('/api/movimientos?tipo=merma')->assertJsonPath('total', 1)->assertJsonPath('data.0.causa', 'Dañado');
        $this->getJson('/api/movimientos?tipo=salida')->assertJsonPath('total', 1);

        $this->getJson('/api/reportes/consumo')
            ->assertJsonPath('data.0.salidas', 3)
            ->assertJsonPath('data.0.mermas', 2)
            ->assertJsonPath('totales.costoMermas', 19);

        $this->getJson('/api/reportes/mermas')
            ->assertOk()
            ->assertJsonPath('totales.valor', 19)
            ->assertJsonPath('data.0.causas', 'Dañado');

        $this->getJson('/api/dashboard')
            ->assertJsonPath('resumen.salidasHoy', 1)
            ->assertJsonPath('resumen.mermasHoy', 1)
            ->assertJsonPath('resumen.valorMermasMes', 19)
            ->assertJsonPath('inventarioReciente.0.codigo', 'GS-001');
    }

    // ---- Usuarios y catálogos --------------------------------------------------

    public function test_admin_no_puede_quitarse_su_propio_rol(): void
    {
        $admin = $this->como('admin');

        $this->putJson("/api/usuarios/{$admin->id}", [
            'nombre' => $admin->nombre, 'correo' => $admin->correo, 'rol' => 'bodega',
        ])->assertStatus(422);
    }

    public function test_admin_crea_usuario_que_luego_puede_iniciar_sesion(): void
    {
        $this->como('admin');

        $this->postJson('/api/usuarios', [
            'nombre' => 'Nuevo Cocinero', 'correo' => 'nuevo@goldstreet.com', 'contrasena' => 'secreta1', 'rol' => 'cocina',
        ])->assertCreated();

        $this->postJson('/api/login', ['correo' => 'nuevo@goldstreet.com', 'contrasena' => 'secreta1'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'cocina');
    }

    public function test_proveedor_guarda_direccion(): void
    {
        $this->como('admin');

        $this->postJson('/api/proveedores', [
            'nombre' => 'Mercado Central', 'direccion' => 'Calle Arce #123, San Salvador',
        ])->assertCreated()->assertJsonPath('data.direccion', 'Calle Arce #123, San Salvador');
    }

    public function test_no_se_elimina_categoria_con_productos(): void
    {
        $this->como('admin');

        $this->deleteJson('/api/categorias/1')->assertStatus(409);
    }

    // ---- Vistas Blade ---------------------------------------------------------

    public function test_las_vistas_cargan(): void
    {
        foreach (['/login', '/dashboard', '/inventario', '/movimientos', '/reportes', '/usuarios', '/catalogos'] as $url) {
            $this->get($url)->assertOk()->assertSee('GoldTrack');
        }
    }

    public function test_login_muestra_ayudas_demo_solo_en_desarrollo(): void
    {
        $this->app['env'] = 'production';
        $this->get('/login')->assertOk()
            ->assertDontSee('Credenciales demo')->assertDontSee('admin123')->assertDontSee('id="gs-rol"', false);

        $this->app['env'] = 'local';
        $this->get('/login')->assertOk()
            ->assertSee('Credenciales demo')->assertSee('id="gs-rol"', false);
    }
}
