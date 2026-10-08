<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\Plato;
use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\GoldStreetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase 3: recetas y costo por plato, lista de compras / órdenes de compra y cuentas por pagar.
 */
class RecetasComprasPagosTest extends TestCase
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

    private function plato(array $platos, string $nombre): array
    {
        return collect($platos)->firstWhere('nombre', $nombre);
    }

    private function facturaCredito(array $cambios = []): array
    {
        return array_merge([
            'proveedor_id'      => 1,
            'numero'            => 'CR-100',
            'fecha'             => today()->toDateString(),
            'condicion'         => 'credito',
            'fecha_vencimiento' => today()->addDays(15)->toDateString(),
            'lineas'            => [
                ['producto_id' => $this->producto('GS-001')->id, 'cantidad' => 5, 'precio_unitario' => 10.25],
                ['producto_id' => $this->producto('GS-004')->id, 'cantidad' => 8, 'precio_unitario' => 5.00],
            ],
        ], $cambios);
    }

    // ---- Recetas y costo por plato --------------------------------------------

    public function test_admin_ve_costo_margen_food_cost_y_porciones_posibles(): void
    {
        $this->como('admin');
        $platos = $this->getJson('/api/platos')->assertOk()->json('data');

        // 1 orden (2.45) + 0.2 kg papas (0.22) + 0.03 L aceite (0.126) + 0.05 kg tomate (0.095)
        $puyazo = $this->plato($platos, 'Puyazo a la parrilla');
        $this->assertEquals(2.89, $puyazo['costo']);
        $this->assertEquals(7.06, $puyazo['margen']);
        $this->assertEquals(29.0, $puyazo['food_cost']);
        $this->assertSame(0, $puyazo['porciones_posibles']);           // no hay órdenes de puyazo

        // Harina 30/0.15 = 200 · crema 12/0.12 = 100 · queso 6.5/0.08 = 81 → alcanza para 81
        $pasta = $this->plato($platos, 'Pasta Alfredo');
        $this->assertSame(81, $pasta['porciones_posibles']);
        $this->assertEquals(1.0, $pasta['costo']);
    }

    public function test_cocina_ve_la_receta_sin_costos(): void
    {
        $this->como('cocina');
        $pasta = $this->plato($this->getJson('/api/platos')->assertOk()->json('data'), 'Pasta Alfredo');

        $this->assertArrayNotHasKey('costo', $pasta);
        $this->assertArrayNotHasKey('precio_venta', $pasta);
        $this->assertNull($pasta['ingredientes'][0]['costo']);
        $this->assertCount(3, $pasta['ingredientes']);
    }

    public function test_el_costo_del_plato_sube_cuando_sube_el_ingrediente(): void
    {
        $this->como('admin');
        $this->postJson('/api/facturas', [
            'proveedor_id' => 2, 'numero' => 'Q-1', 'fecha' => today()->toDateString(),
            'lineas' => [['producto_id' => $this->producto('GS-005')->id, 'cantidad' => 2, 'precio_unitario' => 8.00]],
        ])->assertCreated();

        // queso: 0.08 × 8.00 = 0.64 (antes 0.544)
        $pasta = $this->plato($this->getJson('/api/platos')->json('data'), 'Pasta Alfredo');
        $this->assertEquals(1.10, $pasta['costo']);
    }

    public function test_admin_crea_edita_y_elimina_un_plato(): void
    {
        $this->como('admin');
        $id = $this->postJson('/api/platos', [
            'nombre' => 'Lomo encebollado', 'categoria' => 'Platos fuertes', 'precio_venta' => 8.50,
            'ingredientes' => [['producto_id' => $this->producto('GS-001')->id, 'cantidad' => 0.2]],
        ])->assertCreated()->assertJsonPath('data.costo', 1.9)->json('data.id');

        $this->putJson("/api/platos/{$id}", [
            'nombre' => 'Lomo encebollado', 'precio_venta' => 9.00,
            'ingredientes' => [
                ['producto_id' => $this->producto('GS-001')->id, 'cantidad' => 0.25],
                ['producto_id' => $this->producto('GS-003')->id, 'cantidad' => 0.1],
            ],
        ])->assertOk()->assertJsonPath('data.costo', 2.57);   // 0.25×9.50 + 0.1×1.90

        $this->deleteJson("/api/platos/{$id}")->assertOk();
        $this->assertNull(Plato::find($id));
    }

    public function test_plato_valida_ingredientes_y_nombre(): void
    {
        $this->como('admin');
        $lomo = $this->producto('GS-001')->id;

        $this->postJson('/api/platos', ['nombre' => 'Sin receta', 'precio_venta' => 5, 'ingredientes' => []])
            ->assertStatus(422)->assertJsonValidationErrors('ingredientes');

        $this->postJson('/api/platos', ['nombre' => 'Repetido', 'precio_venta' => 5, 'ingredientes' => [
            ['producto_id' => $lomo, 'cantidad' => 0.1], ['producto_id' => $lomo, 'cantidad' => 0.2],
        ]])->assertStatus(422)->assertJsonValidationErrors('ingredientes.1.producto_id');

        $this->postJson('/api/platos', ['nombre' => 'Pasta Alfredo', 'precio_venta' => 5, 'ingredientes' => [
            ['producto_id' => $lomo, 'cantidad' => 0.1],
        ]])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_solo_admin_administra_el_menu(): void
    {
        $this->como('cocina');
        $this->postJson('/api/platos', ['nombre' => 'X', 'precio_venta' => 1, 'ingredientes' => [
            ['producto_id' => $this->producto('GS-001')->id, 'cantidad' => 1],
        ]])->assertStatus(403);
    }

    // ---- Lista de compras y órdenes de compra -----------------------------------

    public function test_lista_sugerida_agrupa_por_proveedor_y_separa_lo_que_se_prepara(): void
    {
        $this->como('admin');
        // Pechuga 18 → 10 kg (mínimo 12): entra a la lista
        $this->postJson('/api/movimientos', ['producto_id' => $this->producto('GS-004')->id, 'tipo' => 'salida', 'cantidad' => 8])->assertCreated();

        $r = $this->getJson('/api/compras/sugerencia')->assertOk();

        $carnes = collect($r->json('data'))->firstWhere('proveedor_id', 1);
        $pechuga = collect($carnes['productos'])->firstWhere('codigo', 'GS-004');
        $this->assertEquals(14.0, $pechuga['sugerido']);           // objetivo 2 × 12 = 24 − 10
        $this->assertEquals(73.5, $carnes['total']);               // 14 × 5.25

        $istmo = collect($r->json('data'))->firstWhere('proveedor_id', 3);
        $this->assertEquals(18.0, collect($istmo['productos'])->firstWhere('codigo', 'GS-008')['sugerido']);  // papas 0 → 18

        // Las porciones (es_preparado) no se compran: van en "por preparar"
        $this->assertNotContains('GS-011', collect($r->json('data'))->flatMap(fn ($g) => collect($g['productos'])->pluck('codigo'))->all());
        $this->assertContains('Orden de puyazo', collect($r->json('porPreparar'))->pluck('producto')->all());
    }

    public function test_stock_ideal_define_cuanto_pedir_y_lo_pedido_no_se_repite(): void
    {
        $this->como('admin');
        $papas = $this->producto('GS-008');
        $papas->update(['stock_maximo' => 30]);

        $sugerido = fn () => collect($this->getJson('/api/compras/sugerencia')->json('data'))
            ->flatMap(fn ($g) => $g['productos'])->firstWhere('codigo', 'GS-008')['sugerido'] ?? 0;

        $this->assertEquals(30.0, $sugerido());

        $this->postJson('/api/ordenes-compra', [
            'proveedor_id' => 3, 'lineas' => [['producto_id' => $papas->id, 'cantidad' => 30]],
        ])->assertCreated()->assertJsonPath('data.numero', 'OC-00001');

        $this->assertEquals(0, $sugerido());                        // ya viene en camino
    }

    public function test_recibir_la_orden_con_su_factura_compara_pedido_y_recibido(): void
    {
        $this->como('bodega');
        $pollo = $this->producto('GS-004');
        $lomo = $this->producto('GS-001');

        $orden = $this->postJson('/api/ordenes-compra', [
            'proveedor_id' => 1,
            'lineas' => [['producto_id' => $pollo->id, 'cantidad' => 14], ['producto_id' => $lomo->id, 'cantidad' => 5]],
        ])->assertCreated()->assertJsonPath('data.total', null)->json('data');   // bodega no ve precios

        // Llegaron 12 kg de pollo y nada de lomo
        $this->postJson('/api/facturas', [
            'proveedor_id' => 1, 'numero' => 'F-77', 'fecha' => today()->toDateString(), 'orden_compra_id' => $orden['id'],
            'lineas' => [['producto_id' => $pollo->id, 'cantidad' => 12, 'precio_unitario' => 5.10]],
        ])->assertCreated()->assertJsonPath('data.orden', 'OC-00001');

        $detalle = $this->getJson("/api/ordenes-compra/{$orden['id']}")->assertOk()
            ->assertJsonPath('data.estado', 'recibida')
            ->assertJsonPath('data.factura.numero', 'F-77')
            ->json('data.detalles');

        $this->assertEquals(-2.0, collect($detalle)->firstWhere('producto_id', $pollo->id)['diferencia']);
        $this->assertEquals(0.0, collect($detalle)->firstWhere('producto_id', $lomo->id)['cantidad_recibida']);
        $this->assertEquals(30.0, $pollo->fresh()->stock);

        $this->postJson("/api/ordenes-compra/{$orden['id']}/cancelar")->assertStatus(409);
    }

    public function test_orden_de_otro_proveedor_o_ya_recibida_no_se_puede_usar(): void
    {
        $this->como('admin');
        $id = $this->postJson('/api/ordenes-compra', [
            'proveedor_id' => 1, 'lineas' => [['producto_id' => $this->producto('GS-004')->id, 'cantidad' => 3]],
        ])->json('data.id');

        $this->postJson('/api/facturas', [
            'proveedor_id' => 2, 'numero' => 'X-1', 'fecha' => today()->toDateString(), 'orden_compra_id' => $id,
            'lineas' => [['producto_id' => $this->producto('GS-002')->id, 'cantidad' => 1, 'precio_unitario' => 2]],
        ])->assertStatus(422)->assertJsonValidationErrors('orden_compra_id');

        $this->postJson("/api/ordenes-compra/{$id}/cancelar")->assertOk();
        $this->assertSame('cancelada', OrdenCompra::find($id)->estado);
    }

    public function test_anular_la_factura_devuelve_la_orden_a_pendiente(): void
    {
        $this->como('admin');
        $pollo = $this->producto('GS-004')->id;
        $orden = $this->postJson('/api/ordenes-compra', ['proveedor_id' => 1, 'lineas' => [['producto_id' => $pollo, 'cantidad' => 3]]])->json('data.id');
        $factura = $this->postJson('/api/facturas', [
            'proveedor_id' => 1, 'numero' => 'F-1', 'fecha' => today()->toDateString(), 'orden_compra_id' => $orden,
            'lineas' => [['producto_id' => $pollo, 'cantidad' => 3, 'precio_unitario' => 5]],
        ])->json('data.id');

        $this->postJson("/api/facturas/{$factura}/anular", ['motivo' => 'Mercadería devuelta'])->assertOk();
        $this->assertSame('pendiente', OrdenCompra::find($orden)->estado);
    }

    public function test_cocina_no_accede_a_compras(): void
    {
        $this->como('cocina');
        $this->getJson('/api/compras/sugerencia')->assertStatus(403);
        $this->getJson('/api/ordenes-compra')->assertStatus(403);
    }

    // ---- Cuentas por pagar --------------------------------------------------------

    public function test_factura_al_credito_queda_por_pagar_y_se_abona(): void
    {
        $this->como('admin');
        $id = $this->postJson('/api/facturas', $this->facturaCredito())->assertCreated()->json('data.id');

        $this->getJson('/api/cuentas-por-pagar')->assertOk()
            ->assertJsonPath('totales.pendiente', 91.25)
            ->assertJsonPath('data.0.estado_pago', 'pendiente')
            ->assertJsonPath('data.0.dias', 15);

        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 50, 'fecha' => today()->toDateString(), 'metodo' => 'transferencia'])
            ->assertCreated()->assertJsonPath('data.saldo', 41.25);

        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 50, 'fecha' => today()->toDateString(), 'metodo' => 'efectivo'])
            ->assertStatus(422)->assertJsonValidationErrors('monto');

        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 41.25, 'fecha' => today()->toDateString(), 'metodo' => 'cheque', 'referencia' => 'CH-0045'])
            ->assertCreated()->assertJsonPath('data.estado_pago', 'pagada')->assertJsonCount(2, 'data.pagos');

        $this->getJson('/api/cuentas-por-pagar')->assertJsonPath('totales.pendiente', 0)->assertJsonCount(0, 'data');
        $this->getJson('/api/cuentas-por-pagar?estado=pagada')->assertJsonCount(1, 'data');
    }

    public function test_factura_vencida(): void
    {
        $this->como('admin');
        $this->postJson('/api/facturas', $this->facturaCredito([
            'fecha' => today()->subDays(40)->toDateString(), 'fecha_vencimiento' => today()->subDays(10)->toDateString(),
        ]))->assertCreated();

        $this->getJson('/api/cuentas-por-pagar')
            ->assertJsonPath('data.0.estado_pago', 'vencida')
            ->assertJsonPath('data.0.dias', -10)
            ->assertJsonPath('totales.vencido', 91.25);
    }

    public function test_credito_necesita_vencimiento_y_contado_no_genera_deuda(): void
    {
        $this->como('admin');
        $this->postJson('/api/facturas', $this->facturaCredito(['fecha_vencimiento' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_vencimiento');

        $id = $this->postJson('/api/facturas', $this->facturaCredito(['condicion' => 'contado', 'fecha_vencimiento' => null]))
            ->assertCreated()->assertJsonPath('data.estado_pago', 'contado')->json('data.id');

        $this->getJson('/api/cuentas-por-pagar')->assertJsonPath('totales.pendiente', 0);
        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 1, 'fecha' => today()->toDateString(), 'metodo' => 'efectivo'])
            ->assertStatus(409);
    }

    public function test_factura_con_pagos_no_se_anula(): void
    {
        $this->como('admin');
        $id = $this->postJson('/api/facturas', $this->facturaCredito())->json('data.id');
        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 10, 'fecha' => today()->toDateString(), 'metodo' => 'efectivo'])->assertCreated();

        $this->postJson("/api/facturas/{$id}/anular", ['motivo' => 'error'])->assertStatus(409);
        $this->assertSame('registrada', Factura::find($id)->estado);
    }

    public function test_cuentas_por_pagar_solo_admin_y_bodega_no_ve_saldos(): void
    {
        $this->como('bodega');
        $id = $this->postJson('/api/facturas', $this->facturaCredito())->assertCreated()->json('data.id');

        $this->getJson('/api/facturas')->assertOk()->assertJsonMissingPath('data.0.saldo');
        $this->getJson('/api/cuentas-por-pagar')->assertStatus(403);
        $this->postJson("/api/facturas/{$id}/pagos", ['monto' => 1, 'fecha' => today()->toDateString(), 'metodo' => 'efectivo'])
            ->assertStatus(403);
    }

    public function test_vistas_de_fase_3_cargan(): void
    {
        foreach (['/recetas', '/compras', '/cuentas-por-pagar'] as $url) {
            $this->get($url)->assertOk()->assertSee('GoldTrack');
        }
    }
}
