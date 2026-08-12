<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique('uq_productos_codigo');
            $table->string('nombre', 120);
            $table->foreignId('categoria_id')->nullable()->constrained('categorias');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->string('unidad', 20)->default('unidad');
            $table->decimal('stock', 10, 2)->default(0);             // SOLO lo escribe el trigger (4.2)
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
