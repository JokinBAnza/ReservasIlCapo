<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ocupaciones "sin reserva": clientes que llegan al restaurante sin reservar
// y a los que el personal asigna mesa desde el mapa. Bloquean la mesa igual
// que una reserva normal para que nadie la pueda reservar online.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->boolean('sin_reserva')->default(false)->after('perro');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('sin_reserva');
        });
    }
};
