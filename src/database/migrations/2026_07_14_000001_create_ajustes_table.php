<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ajustes que el personal puede cambiar desde la web (días abiertos,
// fechas cerradas, límites...). Clave-valor con el valor en JSON.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->text('valor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
