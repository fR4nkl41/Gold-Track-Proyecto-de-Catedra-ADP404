<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('correo', 120)->unique('uq_usuarios_correo');
            $table->string('contrasena', 255);                       // hash bcrypt
            $table->enum('rol', ['admin', 'bodega', 'cocina'])->default('bodega');
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
