<?php

use App\Support\TriggerStock;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Trigger trg_mov_stock (sección 4.2). El SQL vive en App\Support\TriggerStock. */
    public function up(): void
    {
        TriggerStock::crear();
    }

    public function down(): void
    {
        TriggerStock::eliminar();
    }
};
