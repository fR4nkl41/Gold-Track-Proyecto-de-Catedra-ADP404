<?php

use App\Support\TriggerStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea la BD con el diccionario de datos (sección 3.4 del documento) y agrega mermas:
 *   productos.unidad      → ENUM('kg','lb','L','und','caja')
 *   productos.categoria_id / proveedor_id → NOT NULL
 *   proveedores.direccion → VARCHAR(150) NULL
 *   movimientos.motivo    → movimientos.referencia VARCHAR(120)
 *   movimientos.causa     → ENUM de merma (NULL = salida normal por consumo)
 * Los datos existentes se normalizan antes de endurecer las columnas.
 */
return new class extends Migration
{
    public const UNIDADES = ['kg', 'lb', 'L', 'und', 'caja'];

    public function up(): void
    {
        // SQLite reconstruye las tablas al modificarlas y el trigger (que referencia
        // productos) lo impediría: se quita aquí y se recrea al final.
        TriggerStock::eliminar();

        // 1) Unidades escritas a mano → valores del ENUM
        $equivalencias = [
            'kg'   => ['kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos'],
            'lb'   => ['lb', 'lbs', 'libra', 'libras'],
            'L'    => ['l', 'lt', 'lts', 'litro', 'litros'],
            'caja' => ['caja', 'cajas'],
        ];
        foreach ($equivalencias as $valor => $variantes) {
            DB::table('productos')->whereIn(DB::raw('LOWER(TRIM(unidad))'), $variantes)->update(['unidad' => $valor]);
        }
        DB::table('productos')->whereNotIn('unidad', self::UNIDADES)->update(['unidad' => 'und']);

        // 2) Productos sin categoría/proveedor → registro "General" antes de volverlos obligatorios
        if (DB::table('productos')->whereNull('categoria_id')->exists()) {
            $cat = DB::table('categorias')->where('nombre', 'General')->value('id')
                ?? DB::table('categorias')->insertGetId(['nombre' => 'General', 'descripcion' => 'Asignada automáticamente']);
            DB::table('productos')->whereNull('categoria_id')->update(['categoria_id' => $cat]);
        }
        if (DB::table('productos')->whereNull('proveedor_id')->exists()) {
            $prov = DB::table('proveedores')->where('nombre', 'Proveedor general')->value('id')
                ?? DB::table('proveedores')->insertGetId(['nombre' => 'Proveedor general']);
            DB::table('productos')->whereNull('proveedor_id')->update(['proveedor_id' => $prov]);
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->enum('unidad', self::UNIDADES)->default('kg')->change();
            $table->unsignedBigInteger('categoria_id')->nullable(false)->change();
            $table->unsignedBigInteger('proveedor_id')->nullable(false)->change();
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('direccion', 150)->nullable()->after('correo');
        });

        // 3) motivo → referencia (factura, orden, motivo...) + causa de merma
        Schema::table('movimientos', function (Blueprint $table) {
            $table->renameColumn('motivo', 'referencia');
        });
        DB::table('movimientos')->whereRaw('LENGTH(referencia) > 120')
            ->update(['referencia' => DB::raw('SUBSTR(referencia, 1, 120)')]);
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('referencia', 120)->nullable()->change();
            $table->enum('causa', ['vencido', 'danado', 'mal_preparado', 'otro'])->nullable()->after('referencia');
        });

        TriggerStock::crear();
    }

    public function down(): void
    {
        TriggerStock::eliminar();

        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('causa');
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('referencia', 255)->nullable()->change();
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->renameColumn('referencia', 'motivo');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->string('unidad', 20)->default('unidad')->change();
            $table->unsignedBigInteger('categoria_id')->nullable()->change();
            $table->unsignedBigInteger('proveedor_id')->nullable()->change();
        });

        TriggerStock::crear();
    }
};
