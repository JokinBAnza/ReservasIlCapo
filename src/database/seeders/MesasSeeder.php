<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MesasSeeder extends Seeder
{
    public function run(): void
    {
        $mesas = [
            // =========================
            // Comedor dentro
            // Capacidad 2
            ['numero' => 1, 'capacidad' => 2, 'comedor' => 'dentro'],
            ['numero' => 2, 'capacidad' => 2, 'comedor' => 'dentro'],
            ['numero' => 3, 'capacidad' => 2, 'comedor' => 'dentro'],
            ['numero' => 4, 'capacidad' => 2, 'comedor' => 'dentro'],
            ['numero' => 9, 'capacidad' => 2, 'comedor' => 'dentro'],
            ['numero' => 14, 'capacidad' => 2, 'comedor' => 'dentro'],
            // Mesa auxiliar "pared": vive dentro, pero se mueve físicamente
            // (p. ej. a la terraza para las combinaciones de grupos grandes)
            ['numero' => 0, 'capacidad' => 2, 'comedor' => 'dentro'],

            // Capacidad 4
            ['numero' => 5, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 6, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 7, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 8, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 10, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 11, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 12, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 13, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 15, 'capacidad' => 4, 'comedor' => 'dentro'],
            ['numero' => 16, 'capacidad' => 4, 'comedor' => 'dentro'],

            // Capacidad 5
            ['numero' => 17, 'capacidad' => 5, 'comedor' => 'dentro'],
            ['numero' => 19, 'capacidad' => 5, 'comedor' => 'dentro'],

            // Capacidad 8
            ['numero' => 21, 'capacidad' => 8, 'comedor' => 'dentro'],
            ['numero' => 22, 'capacidad' => 8, 'comedor' => 'dentro'],

            // Capacidad 10
            ['numero' => 20, 'capacidad' => 10, 'comedor' => 'dentro'],
            ['numero' => 23, 'capacidad' => 10, 'comedor' => 'dentro'],

            // =========================
            // Comedor terraza
            // Capacidad 2
            ['numero' => 50, 'capacidad' => 2, 'comedor' => 'terraza'],
            ['numero' => 52, 'capacidad' => 2, 'comedor' => 'terraza'],
            ['numero' => 53, 'capacidad' => 2, 'comedor' => 'terraza'],
            ['numero' => 39, 'capacidad' => 2, 'comedor' => 'terraza'],

            // Capacidad 4
            ['numero' => 30, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 31, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 32, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 33, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 34, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 35, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 36, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 37, 'capacidad' => 4, 'comedor' => 'terraza'],
            ['numero' => 38, 'capacidad' => 4, 'comedor' => 'terraza'],

            // Capacidad 10
            ['numero' => 41, 'capacidad' => 10, 'comedor' => 'terraza'],
            ['numero' => 42, 'capacidad' => 10, 'comedor' => 'terraza'],
            ['numero' => 43, 'capacidad' => 10, 'comedor' => 'terraza'],

            // Capacidad 7
            ['numero' => 44, 'capacidad' => 7, 'comedor' => 'terraza'],
            ['numero' => 48, 'capacidad' => 7, 'comedor' => 'terraza'],
            ['numero' => 45, 'capacidad' => 7, 'comedor' => 'terraza'],
            ['numero' => 46, 'capacidad' => 7, 'comedor' => 'terraza'],
            ['numero' => 47, 'capacidad' => 7, 'comedor' => 'terraza'],
        ];

        DB::table('mesas')->insert($mesas);
    }
}