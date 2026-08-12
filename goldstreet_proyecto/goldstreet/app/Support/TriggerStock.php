<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Trigger trg_mov_stock (sección 4.2): cada movimiento insertado suma
 * (entrada) o resta (salida, incluidas las mermas) el stock del producto.
 * La BD es la única fuente de verdad. Se crea según el motor:
 * MySQL/MariaDB en uso real, SQLite en las pruebas automáticas.
 */
class TriggerStock
{
    public static function crear(): void
    {
        self::eliminar();

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_mov_stock AFTER INSERT ON movimientos
                BEGIN
                    UPDATE productos
                    SET stock = stock + CASE WHEN NEW.tipo = 'entrada' THEN NEW.cantidad ELSE -NEW.cantidad END
                    WHERE id = NEW.producto_id;
                END
            ");

            return;
        }

        DB::unprepared("
            CREATE TRIGGER trg_mov_stock
            AFTER INSERT ON movimientos
            FOR EACH ROW
            BEGIN
                IF NEW.tipo = 'entrada' THEN
                    UPDATE productos SET stock = stock + NEW.cantidad WHERE id = NEW.producto_id;
                ELSE
                    UPDATE productos SET stock = stock - NEW.cantidad WHERE id = NEW.producto_id;
                END IF;
            END
        ");
    }

    public static function eliminar(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mov_stock');
    }
}
