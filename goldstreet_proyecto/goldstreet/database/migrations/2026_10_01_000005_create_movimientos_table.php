<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->enum('tipo', ['entrada', 'salida']);
            $table->decimal('cantidad', 10, 2);
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['created_at', 'tipo']);                   // filtros por fecha/tipo y reportes
        });

        // Validación 4.4 también a nivel de BD: cantidad > 0
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE movimientos ADD CONSTRAINT chk_mov_cantidad CHECK (cantidad > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
