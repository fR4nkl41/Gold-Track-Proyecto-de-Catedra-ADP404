<?php

use App\Support\TriggerStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuevas causas de merma: empaque dañado (bolsa rota, lata golpeada...) y
 * desperdicio (recortes y sobras de la preparación).
 */
return new class extends Migration
{
    private const ANTES = ['vencido', 'danado', 'mal_preparado', 'otro'];
    private const AHORA = ['vencido', 'danado', 'mal_preparado', 'empaque', 'desperdicio', 'otro'];

    public function up(): void
    {
        $this->cambiarEnum(self::AHORA);
    }

    public function down(): void
    {
        // Las mermas con causas nuevas pasan a "otro" para no perder el registro
        DB::table('movimientos')->whereIn('causa', ['empaque', 'desperdicio'])->update(['causa' => 'otro']);
        $this->cambiarEnum(self::ANTES);
    }

    private function cambiarEnum(array $valores): void
    {
        // SQLite reconstruye la tabla al modificarla: el trigger se quita y se recrea
        TriggerStock::eliminar();
        Schema::table('movimientos', function (Blueprint $table) use ($valores) {
            $table->enum('causa', $valores)->nullable()->change();
        });
        TriggerStock::crear();
    }
};
