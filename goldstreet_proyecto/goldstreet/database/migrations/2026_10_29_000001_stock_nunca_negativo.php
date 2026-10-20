<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Red de seguridad para el trigger trg_mov_stock: el stock de un producto nunca puede quedar negativo.
 * La aplicación ya lo impide (bloqueo + validación); si algo se saltara esa regla, MySQL rechaza el
 * movimiento completo. En SQLite (pruebas) no se agrega: SQLite no permite añadir un CHECK a una tabla existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE productos ADD CONSTRAINT chk_productos_stock CHECK (stock >= 0)');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE productos DROP CONSTRAINT chk_productos_stock');   // MySQL 8.0.19+ y MariaDB
        }
    }
};
