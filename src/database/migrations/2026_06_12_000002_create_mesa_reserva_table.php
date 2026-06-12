<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Una reserva pasa a poder ocupar varias mesas (grupos grandes que juntan
// mesas contiguas), así que la relación se mueve a una tabla pivote.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('mesa_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->unique(['reserva_id', 'mesa_id']);
        });

        // Pasar las reservas existentes (de una sola mesa) a la pivote
        $filas = DB::table('reservas')
            ->select('id as reserva_id', 'mesa_id')
            ->get()
            ->map(fn ($fila) => (array) $fila)
            ->all();

        if ($filas !== []) {
            DB::table('mesa_reserva')->insert($filas);
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mesa_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('mesa_id')->nullable()->after('id')->constrained('mesas')->cascadeOnDelete();
        });

        // Restaurar la primera mesa de cada reserva en la columna antigua
        DB::table('mesa_reserva')
            ->orderBy('id')
            ->get()
            ->groupBy('reserva_id')
            ->each(function ($filas, $reservaId) {
                DB::table('reservas')->where('id', $reservaId)->update(['mesa_id' => $filas->first()->mesa_id]);
            });

        Schema::dropIfExists('mesa_reserva');
    }
};
