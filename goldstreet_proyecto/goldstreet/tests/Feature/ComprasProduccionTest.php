<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\GoldStreetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase 2: facturas de compra, ficha de proveedor y producción/porcionado.
 */
class ComprasProduccionTest extends TestCase
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

    private function factura(array $cambios = []): array
    {
        return array_merge([
            'proveedor_id' => 1,
            'numero'       => 'F-00981',
            'fecha'        => today()->toDateString(),
            'lineas'       => [
                ['producto_id' => $this->producto('GS-001')->id, 'cantidad' => 5, 'precio_unitario' => 10.25],
                ['producto_id' => $this->producto('GS-004')->id, 'cantidad' => 8, 'precio_unitario' => 5.00],
            ],
        ], $cambios);
    }

    // ---- Facturas -----------------------------------------------------------

    public function test_bodega_registra_factura_y_sube_stock_y_costo(): void
    {
        $this->como('bodega');
        $lomo = $this->producto('GS-001');      // stock 24.5
        $pollo = $this->producto('GS-004');     // stock 18

        $this->postJson('/api/facturas', $this->factura())
            ->assertCreated()
            ->assertJsonPath('data.numero', 'F-00981')
            ->assertJsonPath('data.total', null);            // bodega no ve precios

        $this->assertEquals(29.5, $lomo->fresh()->stock);
        $this->assertEquals(26.0, $pollo->fresh()->stock);
        $this->assertEquals(10.25, $lomo->fresh()->costo);  // último precio de compra
        $this->assertEquals(91.25, Factura::first()->total); // 5×10.25 + 8×5

        $movs = MovimientoInventario::whereNotNull('factura_id')->get();
        $this->assertCount(2, $movs);
        $this->assertTrue($movs->every(fn ($m) => $m->tipo === 'entrada' && $m->clase === 'compra'));
        $this->assertStringContainsString('Factura F-00981', $movs->first()->referencia);
    }

    public function test_admin_ve_precios_de_la_factura(): void
    {
        $this->como('admin');
        $id = $this->postJson('/api/facturas', $this->factura())->json('data.id');

        $this->getJson("/api/facturas/{$id}")
            ->assertOk()
            ->assertJsonPath('data.total', 91.25)
            ->assertJsonPath('data.detalles.0.precio_unitario', 10.25);
    }

    public function test_no_se_registra_dos_veces_la_misma_factura_del_proveedor(): void
    {
        $this->como('bodega');
        $this->postJson('/api/facturas', $this->factura())->assertCreated();

        $this->postJson('/api/facturas', $this->factura())
            ->assertStatus(422)->assertJsonValidationErrors('numero');

        // El mismo número de OTRO proveedor sí se acepta
        $this->postJson('/api/facturas', $this->factura(['proveedor_id' => 2]))->assertCreated();
    }

    public function test_factura_valida_lineas_fecha_y_productos_repetidos(): void
    {
        $this->como('bodega');
        $id = $this->producto('GS-001')->id;

        $this->postJson('/api/facturas', $this->factura(['lineas' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->postJson('/api/facturas', $this->factura(['fecha' => today()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha');

        $this->postJson('/api/facturas', $this->factura(['lineas' => [
            ['producto_id' => $id, 'cantidad' => 1, 'precio_unitario' => 1],
            ['producto_id' => $id, 'cantidad' => 2, 'precio_unitario' => 1],
        ]]))->assertStatus(422)->assertJsonValidationErrors('lineas.1.producto_id');

        $this->assertSame(0, Factura::count());
    }

    public function test_cocina_no_accede_a_facturas(): void
    {
        $this->como('cocina');

        $this->getJson('/api/facturas')->assertStatus(403);
        $this->postJson('/api/facturas', $this->factura())->assertStatus(403);
    }

    public function test_admin_anula_factura_y_revierte_stock_sin_contar_como_consumo(): void
    {
        $this->como('admin');
        $lomo = $this->producto('GS-001');
        $id = $this->postJson('/api/facturas', $this->factura())->json('data.id');

        $this->postJson("/api/facturas/{$id}/anular", ['motivo' => 'Factura duplicada por el proveedor'])->assertOk();

        $this->assertEquals(24.5, $lomo->fresh()->stock);
        $this->assertSame('anulada', Factura::find($id)->estado);
        $this->getJson("/api/facturas/{$id}/anular")->assertStatus(405);
        $this->postJson("/api/facturas/{$id}/anular", ['motivo' => 'otra vez'])->assertStatus(409);

        // La reversión no es consumo de cocina
        $this->getJson('/api/reportes/consumo')->assertJsonPath('totales.costoSalidas', 0);
    }

    public function test_no_se_anula_si_lo_comprado_ya_se_uso(): void
    {
        $this->como('admin');
        $papas = $this->producto('GS-008');     // stock 0
        $id = $this->postJson('/api/facturas', $this->factura(['lineas' => [
            ['producto_id' => $papas->id, 'cantidad' => 10, 'precio_unitario' => 1.10],
        ]]))->json('data.id');

        $this->postJson('/api/movimientos', ['producto_id' => $papas->id, 'tipo' => 'salida', 'cantidad' => 4])->assertCreated();

        $this->postJson("/api/facturas/{$id}/anular", ['motivo' => 'error'])
            ->assertStatus(422)->assertJsonValidationErrors('factura');
        $this->assertSame('registrada', Factura::find($id)->estado);
    }

    public function test_solo_admin_anula(): void
    {
        $this->como('bodega');
        $id = $this->postJson('/api/facturas', $this->factura())->json('data.id');

        $this->postJson("/api/facturas/{$id}/anular", ['motivo' => 'x'])->assertStatus(403);
    }

    // ---- Ficha del proveedor --------------------------------------------------

    public function test_ficha_del_proveedor_muestra_productos_y_precios(): void
    {
        $this->como('admin');
        $lomo = $this->producto('GS-001')->id;

        $this->postJson('/api/facturas', $this->factura([
            'numero' => 'A-1', 'fecha' => today()->subDays(10)->toDateString(),
            'lineas' => [['producto_id' => $lomo, 'cantidad' => 10, 'precio_unitario' => 9.00]],
        ]))->assertCreated();
        $this->postJson('/api/facturas', $this->factura([
            'numero' => 'A-2',
            'lineas' => [['producto_id' => $lomo, 'cantidad' => 10, 'precio_unitario' => 11.00]],
        ]))->assertCreated();

        $this->getJson('/api/proveedores/1/ficha')
            ->assertOk()
            ->assertJsonPath('proveedor.nombre', 'Carnes El Roble')
            ->assertJsonPath('totales.facturas', 2)
            ->assertJsonPath('totales.total', 200)
            ->assertJsonPath('productos.0.compras', 2)
            ->assertJsonPath('productos.0.ultimo_precio', 11)
            ->assertJsonPath('productos.0.precio_anterior', 9)
            ->assertJsonPath('productos.0.precio_promedio', 10);
    }

    public function test_ficha_del_proveedor_es_solo_para_admin(): void
    {
        $this->como('bodega');
        $this->getJson('/api/proveedores/1/ficha')->assertStatus(403);
    }

    public function test_proveedor_con_facturas_no_se_elimina(): void
    {
        $this->como('admin');
        $this->postJson('/api/facturas', $this->factura(['proveedor_id' => 4]))->assertCreated();

        $this->deleteJson('/api/proveedores/4')->assertStatus(409);
    }

    // ---- Producción / porcionado ----------------------------------------------

    private function produccion(array $cambios = []): array
    {
        return array_merge([
            'producto_origen_id' => $this->producto('GS-009')->id,   // Puyazo, 21 lb
            'cantidad_origen'    => 21,
            'desperdicio'        => 0.64,
            'resultados'         => [['producto_id' => $this->producto('GS-011')->id, 'cantidad' => 41]],
            'notas'              => 'Corte del 24/10',
        ], $cambios);
    }

    public function test_cocina_registra_porcionado_con_desperdicio(): void
    {
        $this->como('cocina');

        $this->postJson('/api/producciones', $this->produccion())
            ->assertCreated()
            ->assertJsonPath('data.aprovechamiento', 97)
            ->assertJsonPath('data.resultados.0.cantidad', 41);

        $this->assertEqualsWithDelta(0.0, $this->producto('GS-009')->stock, 0.001);   // se usaron las 21 lb (SQLite guarda REAL)
        $this->assertEquals(41.0, $this->producto('GS-011')->stock);  // entraron 41 órdenes

        $movs = MovimientoInventario::whereNotNull('produccion_id')->get();
        $this->assertCount(3, $movs);                                 // salida + merma + entrada
        $this->assertEquals(20.36, (float) $movs->firstWhere(fn ($m) => $m->tipo === 'salida' && ! $m->causa)->cantidad);
        $this->assertSame('desperdicio', $movs->firstWhere('causa', '!=', null)->causa);
    }

    public function test_porcionado_no_cuenta_como_consumo_pero_el_desperdicio_si_es_merma(): void
    {
        $this->como('cocina');
        $this->postJson('/api/producciones', $this->produccion())->assertCreated();

        $this->como('admin');
        $this->getJson('/api/reportes/consumo')->assertJsonPath('totales.costoSalidas', 0);
        $this->getJson('/api/reportes/mermas')
            ->assertJsonPath('totales.registros', 1)
            ->assertJsonPath('totales.valor', round(0.64 * 4.80, 2));
        $this->getJson('/api/movimientos?tipo=produccion')->assertJsonPath('total', 3);
    }

    public function test_varios_resultados_como_en_el_cuaderno(): void
    {
        $this->como('cocina');

        // "Se partió 10.78 lb de lomo rollizo: 6 parrilladas de 8 oz, 3 puntas, 0.62 de desperdicio"
        $this->postJson('/api/producciones', $this->produccion([
            'producto_origen_id' => $this->producto('GS-010')->id,
            'cantidad_origen'    => 10.78,
            'desperdicio'        => 0.62,
            'resultados'         => [
                ['producto_id' => $this->producto('GS-012')->id, 'cantidad' => 6],
                ['producto_id' => $this->producto('GS-013')->id, 'cantidad' => 3],
            ],
        ]))->assertCreated();

        $this->assertEqualsWithDelta(48.22, $this->producto('GS-010')->stock, 0.001);
        $this->assertEquals(6.0, $this->producto('GS-012')->stock);
        $this->assertEquals(3.0, $this->producto('GS-013')->stock);
    }

    public function test_produccion_valida_stock_desperdicio_y_origen(): void
    {
        $this->como('cocina');

        $this->postJson('/api/producciones', $this->produccion(['cantidad_origen' => 30]))
            ->assertStatus(422)->assertJsonValidationErrors('cantidad_origen');

        $this->postJson('/api/producciones', $this->produccion(['desperdicio' => 21]))
            ->assertStatus(422)->assertJsonValidationErrors('desperdicio');

        $this->postJson('/api/producciones', $this->produccion([
            'resultados' => [['producto_id' => $this->producto('GS-009')->id, 'cantidad' => 5]],
        ]))->assertStatus(422)->assertJsonValidationErrors('resultados');

        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_bodega_consulta_produccion_pero_no_la_registra(): void
    {
        $this->como('bodega');

        $this->getJson('/api/producciones')->assertOk();
        $this->postJson('/api/producciones', $this->produccion())->assertStatus(403);
    }

    public function test_produccion_recalcula_el_costo_de_las_porciones(): void
    {
        $this->como('cocina');
        $this->postJson('/api/producciones', $this->produccion())->assertCreated();

        // 21 lb × $4.80 (desperdicio incluido) ÷ 41 órdenes = $2.46 por orden
        $this->assertEquals(2.46, (float) $this->producto('GS-011')->costo);
    }

    public function test_rendimiento_poco_comun_pide_confirmacion(): void
    {
        $this->como('cocina');
        $tipeo = $this->produccion(['resultados' => [['producto_id' => $this->producto('GS-011')->id, 'cantidad' => 410]]]);

        // 410 órdenes de 21 lb (19.5 por lb): se detiene sin mover inventario
        $this->postJson('/api/producciones', $tipeo)
            ->assertStatus(422)
            ->assertJsonPath('requiere_confirmacion', true);
        $this->assertSame(0, MovimientoInventario::count());

        // Con historial, el límite es el doble del promedio: con un promedio de 1.9 por lb, 6 por lb ya no pasa sin confirmar
        $this->postJson('/api/producciones', $this->produccion(['cantidad_origen' => 10, 'desperdicio' => 0.3,
            'resultados' => [['producto_id' => $this->producto('GS-011')->id, 'cantidad' => 19]]]))->assertCreated();
        $this->postJson('/api/producciones', $this->produccion(['cantidad_origen' => 5, 'desperdicio' => 0,
            'resultados' => [['producto_id' => $this->producto('GS-011')->id, 'cantidad' => 30]]]))
            ->assertStatus(422)->assertJsonPath('requiere_confirmacion', true);

        // Si cocina confirma que es correcto, se registra
        $this->postJson('/api/producciones', $this->produccion(['cantidad_origen' => 5, 'desperdicio' => 0, 'confirmar_rendimiento' => true,
            'resultados' => [['producto_id' => $this->producto('GS-011')->id, 'cantidad' => 30]]]))->assertCreated();
    }

    public function test_los_preparados_vienen_de_produccion_interna(): void
    {
        $this->como('admin');

        foreach (['GS-011', 'GS-012', 'GS-013'] as $codigo) {
            $this->assertSame('Producción interna (cocina)', $this->producto($codigo)->proveedor->nombre);
        }
    }

    public function test_reporte_de_rendimiento(): void
    {
        $this->como('admin');
        $this->postJson('/api/producciones', $this->produccion())->assertCreated();

        $this->getJson('/api/reportes/rendimiento')
            ->assertOk()
            ->assertJsonPath('totales.producciones', 1)
            ->assertJsonPath('data.0.origen', 'Puyazo Premium')
            ->assertJsonPath('data.0.aprovechamiento', 97)
            ->assertJsonPath('data.0.resultados.0.por_unidad', 1.95);   // 41 órdenes / 21 lb
    }

    public function test_vistas_de_fase_2_cargan(): void
    {
        foreach (['/facturas', '/produccion', '/proveedores/1'] as $url) {
            $this->get($url)->assertOk()->assertSee('GoldTrack');
        }
    }
}
