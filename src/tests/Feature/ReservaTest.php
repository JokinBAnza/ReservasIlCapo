<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\User;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    use RefreshDatabase;

    private User $personal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MesasSeeder::class);
        $this->personal = User::factory()->create();
    }

    // Reserva hecha por el personal (logueado); el flujo público
    // tiene sus propios tests más abajo
    private function reservar(array $datos = [])
    {
        return $this->actingAs($this->personal)->post(route('reservas.store'), array_merge([
            'nombre' => 'Prueba',
            'apellidos' => 'García',
            'telefono' => '600000000',
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '13:00',
            'personas' => 4,
            'comedor' => 'dentro',
        ], $datos));
    }

    private function mesasDeLaUltimaReserva(): array
    {
        return Reserva::latest('id')->first()->mesas->pluck('numero')->sort()->values()->all();
    }

    public function test_asigna_la_mesa_libre_mas_ajustada(): void
    {
        $this->reservar()->assertSessionHas('exito');

        $this->assertSame([5], $this->mesasDeLaUltimaReserva());
    }

    public function test_una_mesa_ocupada_no_se_vuelve_a_asignar_a_la_misma_hora(): void
    {
        $this->reservar();
        $this->reservar(['hora' => '13:30']);

        $this->assertSame([6], $this->mesasDeLaUltimaReserva());
    }

    public function test_la_mesa_queda_libre_pasada_la_duracion_de_la_reserva(): void
    {
        $this->reservar();
        $this->reservar(['hora' => '15:00']);

        $this->assertSame([5], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_12_en_terraza_junta_las_mesas_41_y_30(): void
    {
        $this->reservar(['personas' => 12, 'comedor' => 'terraza'])->assertSessionHas('exito');

        $this->assertSame([30, 41], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_20_en_terraza_junta_las_mesas_41_y_42(): void
    {
        $this->reservar(['personas' => 20, 'comedor' => 'terraza'])->assertSessionHas('exito');

        $this->assertSame([41, 42], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_grande_sin_combinacion_posible_da_error_de_disponibilidad(): void
    {
        // Dentro no hay combinaciones definidas: un grupo de 12 no cabe
        $this->reservar(['personas' => 12, 'comedor' => 'dentro'])
            ->assertSessionHasErrors('disponibilidad');

        $this->assertSame(0, Reserva::count());
    }

    public function test_combinacion_con_una_mesa_ocupada_no_esta_disponible(): void
    {
        // La mesa 41 está en las dos combinaciones: ocupada, ningún grupo grande cabe
        $this->reservar(['personas' => 12, 'comedor' => 'terraza']);
        $this->reservar(['personas' => 20, 'comedor' => 'terraza'])
            ->assertSessionHasErrors('disponibilidad');

        $this->assertSame(1, Reserva::count());
    }

    public function test_solo_se_aceptan_horas_de_los_turnos_configurados(): void
    {
        // Entre el turno de comidas y el de cenas
        $this->reservar(['hora' => '17:00'])->assertSessionHasErrors('hora');

        // Dentro del turno pero fuera de los cuartos de hora
        $this->reservar(['hora' => '13:37'])->assertSessionHasErrors('hora');

        $this->assertSame(0, Reserva::count());

        // Cuartos de hora válidos de cada turno sí entran
        $this->reservar(['hora' => '14:45'])->assertSessionHas('exito');
        $this->reservar(['hora' => '21:15'])->assertSessionHas('exito');
    }

    public function test_grupos_mayores_que_la_capacidad_maxima_se_rechazan(): void
    {
        $this->reservar(['personas' => 21, 'comedor' => 'terraza'])
            ->assertSessionHasErrors('personas');
    }

    public function test_la_mesa_30_es_la_ultima_de_4_que_se_asigna_en_terraza(): void
    {
        // Las 9 mesas de 4 de la terraza que no forman parte de ninguna
        // combinación se asignan antes que la 30
        $otras = [31, 32, 33, 34, 35, 36, 37, 38, 48];

        foreach ($otras as $ignorada) {
            $this->reservar(['comedor' => 'terraza'])->assertSessionHas('exito');
        }

        $asignadas = Reserva::with('mesas')->get()
            ->flatMap(fn (Reserva $r) => $r->mesas->pluck('numero'))
            ->all();

        $this->assertEqualsCanonicalizing($otras, $asignadas);

        // La décima reserva de 4 ya solo puede ir a la mesa 30
        $this->reservar(['comedor' => 'terraza']);
        $this->assertSame([30], $this->mesasDeLaUltimaReserva());
    }

    public function test_con_perro_no_se_puede_reservar_dentro(): void
    {
        $this->reservar(['perro' => 1, 'comedor' => 'dentro'])
            ->assertSessionHasErrors('comedor');

        $this->assertSame(0, Reserva::count());
    }

    public function test_con_perro_se_reserva_en_terraza(): void
    {
        $this->reservar(['perro' => 1, 'comedor' => 'terraza'])->assertSessionHas('exito');

        $this->assertTrue(Reserva::first()->perro);
    }

    public function test_no_se_aceptan_mas_de_10_reservas_en_la_misma_franja(): void
    {
        foreach (range(1, 10) as $ignorada) {
            $this->reservar(['personas' => 2])->assertSessionHas('exito');
        }

        // La undécima a la misma hora se rechaza aunque queden mesas libres
        $this->reservar(['personas' => 2])->assertSessionHasErrors('disponibilidad');
        $this->assertSame(10, Reserva::count());

        // Al cuarto de hora siguiente sí se puede
        $this->reservar(['personas' => 2, 'hora' => '13:15'])->assertSessionHas('exito');
    }

    public function test_los_apellidos_son_obligatorios(): void
    {
        $this->reservar(['apellidos' => ''])->assertSessionHasErrors('apellidos');
        $this->assertSame(0, Reserva::count());
    }

    public function test_cada_reserva_recibe_un_localizador_unico(): void
    {
        $this->reservar();
        $this->reservar(['hora' => '13:30']);

        $codigos = Reserva::pluck('localizador');

        $this->assertSame(2, $codigos->unique()->count());
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $codigos->first());
    }

    public function test_anular_reserva_libera_sus_mesas(): void
    {
        $this->reservar(['personas' => 20, 'comedor' => 'terraza']);
        $reserva = Reserva::first();

        $this->actingAs($this->personal)->delete(route('reservas.destroy', $reserva));

        $this->assertSame(0, Reserva::count());
        $this->reservar(['personas' => 20, 'comedor' => 'terraza'])->assertSessionHas('exito');
        $this->assertSame([41, 42], $this->mesasDeLaUltimaReserva());
    }
}
