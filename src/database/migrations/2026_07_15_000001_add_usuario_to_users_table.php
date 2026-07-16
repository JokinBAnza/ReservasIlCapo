<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// El personal entra con un nombre de usuario corto en vez del email
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario')->nullable()->unique()->after('name');
        });

        // A los usuarios existentes: la parte del email antes de la @
        foreach (DB::table('users')->whereNull('usuario')->get() as $u) {
            DB::table('users')->where('id', $u->id)->update(['usuario' => Str::before($u->email, '@')]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('usuario');
        });
    }
};
