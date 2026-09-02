<?php

use App\Support\TriggerStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (expansión):
 *   platos + receta_ingredientes          → recetas y costo por plato
 *   productos.stock_maximo                → nivel ideal para la lista de compras sugerida
 *   productos.es_preparado                → porciones hechas en cocina: no se compran, se producen
 *   ordenes_compra + orden_compra_detalles → pedidos a proveedores
 *   facturas.condicion / fecha_vencimiento / orden_compra_id + pagos_factura → cuentas por pagar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('categoria', 60)->nullable();          // Entradas, Platos fuertes, Bebidas…
            $table->decimal('precio_venta', 10, 2);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('receta_ingredientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plato_id')->constrained('platos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 10, 3);                   // por plato, en la unidad del producto
            $table->unique(['plato_id', 'producto_id']);
        });

        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->enum('estado', ['pendiente', 'recibida', 'cancelada'])->default('pendiente');
            $table->date('fecha_entrega')->nullable();
            $table->string('notas', 255)->nullable();
            $table->decimal('total_estimado', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index('estado');
        });

        Schema::create('orden_compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_estimado', 10, 2)->default(0);
            $table->decimal('cantidad_recibida', 10, 2)->nullable();   // se llena al recibir la factura
        });

        Schema::create('pagos_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->enum('metodo', ['efectivo', 'transferencia', 'cheque', 'otro'])->default('efectivo');
            $table->string('referencia', 60)->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->enum('condicion', ['contado', 'credito'])->default('contado')->after('fecha');
            $table->date('fecha_vencimiento')->nullable()->after('condicion');
            $table->foreignId('orden_compra_id')->nullable()->after('fecha_vencimiento')->constrained('ordenes_compra');
        });

        // productos tiene el trigger encima: SQLite no permite reconstruirla con él puesto
        TriggerStock::eliminar();
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_maximo', 10, 2)->nullable()->after('stock_minimo');
            $table->boolean('es_preparado')->default(false)->after('stock_maximo');
        });
        TriggerStock::crear();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE receta_ingredientes ADD CONSTRAINT chk_ri_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE orden_compra_detalles ADD CONSTRAINT chk_ocd_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE pagos_factura ADD CONSTRAINT chk_pago_monto CHECK (monto > 0)');
        }
    }

    public function down(): void
    {
        TriggerStock::eliminar();
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['stock_maximo', 'es_preparado']);
        });
        TriggerStock::crear();

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
            $table->dropColumn(['condicion', 'fecha_vencimiento']);
        });

        Schema::dropIfExists('pagos_factura');
        Schema::dropIfExists('orden_compra_detalles');
        Schema::dropIfExists('ordenes_compra');
        Schema::dropIfExists('receta_ingredientes');
        Schema::dropIfExists('platos');
    }
};
