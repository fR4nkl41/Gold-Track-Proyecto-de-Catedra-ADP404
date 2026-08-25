<?php

use App\Support\TriggerStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (expansión, inspirada en los cuadernos del restaurante):
 *   facturas + factura_detalles         → compras a proveedores (cuaderno de compras)
 *   producciones + produccion_resultados → porcionado/cocción con desperdicio (cuaderno de producción)
 *   movimientos.factura_id / produccion_id → de qué documento salió cada movimiento
 * El stock lo sigue escribiendo solo el trigger: facturas y producciones generan movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->string('numero', 40);
            $table->date('fecha');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['registrada', 'anulada'])->default('registrada');
            $table->string('motivo_anulacion', 150)->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('usuarios');
            $table->timestamp('anulada_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['proveedor_id', 'numero']);   // la misma factura no se registra dos veces
            $table->index('fecha');
        });

        Schema::create('factura_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);
        });

        Schema::create('producciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('producto_origen_id')->constrained('productos');
            $table->decimal('cantidad_origen', 10, 2);
            $table->decimal('desperdicio', 10, 2)->default(0);
            $table->string('notas', 150)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });

        Schema::create('produccion_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produccion_id')->constrained('producciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 10, 2);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE factura_detalles ADD CONSTRAINT chk_fd_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE produccion_resultados ADD CONSTRAINT chk_pr_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE producciones ADD CONSTRAINT chk_prod_cantidad CHECK (cantidad_origen > 0 AND desperdicio >= 0 AND desperdicio < cantidad_origen)');
        }

        // SQLite reconstruye movimientos al agregar llaves foráneas: el trigger se quita y se recrea
        TriggerStock::eliminar();
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('factura_id')->nullable()->after('causa')->constrained('facturas');
            $table->foreignId('produccion_id')->nullable()->after('factura_id')->constrained('producciones');
        });
        TriggerStock::crear();
    }

    public function down(): void
    {
        TriggerStock::eliminar();
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produccion_id');
            $table->dropConstrainedForeignId('factura_id');
        });
        TriggerStock::crear();

        Schema::dropIfExists('produccion_resultados');
        Schema::dropIfExists('producciones');
        Schema::dropIfExists('factura_detalles');
        Schema::dropIfExists('facturas');
    }
};
