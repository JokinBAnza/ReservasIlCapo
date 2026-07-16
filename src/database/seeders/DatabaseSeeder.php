<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuario del personal del restaurante. ¡Cambiad la contraseña
        // desde phpMyAdmin o tinker antes de publicar la web!
        User::firstOrCreate(
            ['email' => 'personal@ilcapo.local'],
            [
                'name' => 'Personal Il Capo',
                'usuario' => 'personal',
                'password' => Hash::make('IlCapo-2026'),
            ],
        );

        if (Mesa::count() === 0) {
            $this->call(MesasSeeder::class);
        }
    }
}
