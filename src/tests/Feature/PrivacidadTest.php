<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\User;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_politica_de_privacidad_es_publica_y_completa(): void
    {
        $this->get(route('privacidad'))
            ->assertOk()
            ->assertSee('EGOLIFE, S.L.')
            ->assertSee('B20843397')
            ->assertSee('info@ilcapo.net')
            ->assertSee('12 meses');
    }

    public function test_el_formulario_enlaza_a_la_politica_de_privacidad(): void
    {
        $this->seed(MesasSeeder::class);

        $this->get('/')
            ->assertSee('política de privacidad')
            ->assertSee(route('privacidad'));
    }

    public function test_las_reservas_antiguas_se_borran_solas(): void
    {
        $this->seed(MesasSeeder::class);

        $antigua = Reserva::create([
            'nombre' => 'Antigua',
            'apellidos' => 'DeHaceDosAnos',
            'telefono' => '600000001',
            'personas' => 2,
            'fecha_hora' => now()->subYears(2),
        ]);

        $reciente = Reserva::create([
            'nombre' => 'Reciente',
            'apellidos' => 'DelMesPasado',
            'telefono' => '600000002',
            'personas' => 2,
            'fecha_hora' => now()->subMonth(),
        ]);

        // Al abrir el listado, la purga se ejecuta
        $personal = User::factory()->create();
        $this->actingAs($personal)->get(route('reservas.index'))->assertOk();

        $this->assertNull(Reserva::find($antigua->id));
        $this->assertNotNull(Reserva::find($reciente->id));
    }
}
