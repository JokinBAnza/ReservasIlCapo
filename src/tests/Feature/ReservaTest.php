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
        // Dentro la mayor combinación es de 16: un grupo de 18 no cabe
        $this->reservar(['personas' => 18, 'comedor' => 'dentro'])
            ->assertSessionHasErrors('disponibilidad');

        $this->assertSame(0, Reserva::count());
    }

    public function test_grupo_de_12_dentro_junta_las_mesas_21_y_16(): void
    {
        $this->reservar(['personas' => 12, 'comedor' => 'dentro'])->assertSessionHas('exito');

        $this->assertSame([16, 21], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_14_dentro_junta_las_mesas_22_y_17(): void
    {
        // 22+17 suman 13 sueltas, pero montadas dan para 14
        $this->reservar(['personas' => 14, 'comedor' => 'dentro'])->assertSessionHas('exito');

        $this->assertSame([17, 22], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_16_dentro_junta_las_mesas_22_17_y_pared(): void
    {
        $this->reservar(['personas' => 16, 'comedor' => 'dentro'])->assertSessionHas('exito');

        $this->assertSame([0, 17, 22], $this->mesasDeLaUltimaReserva());
    }

    public function test_la_pared_no_puede_servir_a_dos_combinaciones_a_la_vez(): void
    {
        // La "pared" (mesa 0) está en 41+30+0 (terraza) y en 22+17+0 (dentro).
        // Si un grupo de 15 se la lleva a la terraza, el de 16 de dentro
        // ya no puede montarse.
        $this->reservar(['personas' => 15, 'comedor' => 'terraza'])->assertSessionHas('exito');
        $this->assertSame([0, 30, 41], $this->mesasDeLaUltimaReserva());

        $this->reservar(['personas' => 16, 'comedor' => 'dentro'])
            ->assertSessionHasErrors('disponibilidad');

        $this->assertSame(1, Reserva::count());
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

    public function test_las_mesas_de_combinaciones_son_las_ultimas_de_4_en_terraza(): void
    {
        // Cupo de comensales amplio: este test llena 9 mesas de 4 en la
        // misma franja y aquí lo que se prueba es el orden de asignación
        \App\Models\Ajuste::guardar('maximo_personas_por_hora', 100);

        // Las 7 mesas de 4 de la terraza que no forman parte de ninguna
        // combinación se asignan antes que la 30 y la 31 (que sí combinan)
        $otras = [32, 33, 34, 35, 36, 37, 38];

        foreach ($otras as $ignorada) {
            $this->reservar(['comedor' => 'terraza'])->assertSessionHas('exito');
        }

        $asignadas = Reserva::with('mesas')->get()
            ->flatMap(fn (Reserva $r) => $r->mesas->pluck('numero'))
            ->all();

        $this->assertEqualsCanonicalizing($otras, $asignadas);

        // Las dos siguientes ya solo pueden ser la 30 y la 31, en ese orden
        $this->reservar(['comedor' => 'terraza']);
        $this->assertSame([30], $this->mesasDeLaUltimaReserva());

        $this->reservar(['comedor' => 'terraza']);
        $this->assertSame([31], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_15_en_terraza_junta_las_mesas_41_30_y_pared(): void
    {
        $this->reservar(['personas' => 15, 'comedor' => 'terraza'])->assertSessionHas('exito');

        $this->assertSame([0, 30, 41], $this->mesasDeLaUltimaReserva());
    }

    public function test_grupo_de_18_en_terraza_junta_las_mesas_41_30_y_31(): void
    {
        $this->reservar(['personas' => 18, 'comedor' => 'terraza'])->assertSessionHas('exito');

        $this->assertSame([30, 31, 41], $this->mesasDeLaUltimaReserva());
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

    public function test_el_telefono_debe_ser_un_numero_real(): void
    {
        // Con letras: no
        $this->reservar(['telefono' => 'hola que tal'])->assertSessionHasErrors('telefono');

        // Demasiado corto: no
        $this->reservar(['telefono' => '12345'])->assertSessionHasErrors('telefono');

        $this->assertSame(0, Reserva::count());

        // 9 dígitos con espacios: sí
        $this->reservar(['telefono' => '600 11 12 22'])->assertSessionDoesntHaveErrors();

        // Internacional francés: sí
        $this->reservar(['telefono' => '+33 6 12 34 56 78', 'hora' => '13:30'])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Reserva::count());
    }

    public function test_los_apellidos_son_obligatorios(): void
    {
        $this->reservar(['apellidos' => ''])->assertSessionHasErrors('apellidos');
        $this->assertSame(0, Reserva::count());
    }

    public function test_las_observaciones_se_guardan_y_se_ven_en_el_listado(): void
    {
        $this->reservar(['observaciones' => 'Alergia a los frutos secos'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Alergia a los frutos secos', Reserva::first()->observaciones);

        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['fecha' => now()->addDay()->toDateString()]))
            ->assertSee('Alergia a los frutos secos');
    }

    public function test_las_observaciones_no_pueden_pasar_de_200_caracteres(): void
    {
        $this->reservar(['observaciones' => str_repeat('a', 201)])
            ->assertSessionHasErrors('observaciones');

        $this->assertSame(0, Reserva::count());
    }

    public function test_el_mensaje_de_grupo_grande_incluye_el_telefono(): void
    {
        $this->reservar(['personas' => 25]);

        $mensaje = session('errors')->get('personas')[0];
        $this->assertStringContainsString('688 716 226', $mensaje);
    }

    public function test_nombre_y_apellidos_no_pueden_pasar_de_30_caracteres(): void
    {
        $largo = str_repeat('a', 31);

        $this->reservar(['nombre' => $largo])->assertSessionHasErrors('nombre');
        $this->reservar(['apellidos' => $largo])->assertSessionHasErrors('apellidos');
        $this->assertSame(0, Reserva::count());

        $this->reservar(['nombre' => str_repeat('a', 30)])->assertSessionDoesntHaveErrors();
    }

    public function test_cada_reserva_recibe_un_localizador_unico(): void
    {
        $this->reservar();
        $this->reservar(['hora' => '13:30']);

        $codigos = Reserva::pluck('localizador');

        $this->assertSame(2, $codigos->unique()->count());
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $codigos->first());
    }

    public function test_el_mapa_de_mesas_exige_iniciar_sesion(): void
    {
        auth()->logout();

        $this->get(route('reservas.mapa'))->assertRedirect(route('login'));
    }

    public function test_el_mapa_marca_las_mesas_ocupadas_en_su_franja(): void
    {
        $this->reservar(); // mañana 13:00, mesa 5, a nombre de "Prueba García"
        $manana = now()->addDay()->toDateString();

        $mapa = $this->actingAs($this->personal)
            ->get(route('reservas.mapa', ['fecha' => $manana, 'hora' => '13:00']));

        $mapa->assertOk()
            ->assertSee('mesa-ocupada')
            ->assertSee('Prueba García');

        // Dos horas después la mesa vuelve a estar libre
        $this->actingAs($this->personal)
            ->get(route('reservas.mapa', ['fecha' => $manana, 'hora' => '15:00']))
            ->assertOk()
            ->assertDontSee('Prueba García');
    }

    public function test_si_el_torno_esta_ocupado_se_pide_reintentar_sin_crear_nada(): void
    {
        // Simula otra reserva en curso: el candado está cogido
        $candado = \Illuminate\Support\Facades\Cache::lock('crear-reserva', 30);
        $this->assertTrue($candado->get());

        try {
            $this->reservar()->assertSessionHasErrors('disponibilidad');
            $this->assertSame(0, Reserva::count());
        } finally {
            $candado->release();
        }

        // Liberado el candado, la reserva entra con normalidad
        $this->reservar()->assertSessionHas('exito');
        $this->assertSame(1, Reserva::count());
    }

    public function test_nadie_puede_reservar_una_hora_que_ya_paso_ni_siquiera_el_personal(): void
    {
        // Son las 18:00 de hoy...
        $this->travelTo(today()->setTime(18, 0));

        // ...y el personal intenta reservar hoy a las 13:00
        $this->reservar(['fecha' => today()->toDateString(), 'hora' => '13:00'])
            ->assertSessionHasErrors('hora');
        $this->assertSame(0, Reserva::count());

        // Hoy a las 20:00 (aún futuro) sí puede
        $this->reservar(['fecha' => today()->toDateString(), 'hora' => '20:00'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Reserva::count());
    }

    public function test_ocupar_una_mesa_sin_reserva_la_bloquea_para_la_web(): void
    {
        $manana = now()->addDay()->toDateString();
        $mesa5 = \App\Models\Mesa::where('numero', 5)->first();

        // El personal sienta a alguien sin reserva en la mesa 5
        $this->actingAs($this->personal)->post(route('mesas.ocupar'), [
            'mesa_id' => $mesa5->id,
            'fecha' => $manana,
            'hora' => '13:00',
        ])->assertSessionHas('exito');

        // La siguiente reserva de 4 ya no puede recibir la mesa 5
        $this->reservar();
        $this->assertSame([6], $this->mesasDeLaUltimaReserva());

        // Y el mapa la muestra como "Sin reserva" con su botón de liberar
        $this->actingAs($this->personal)
            ->get(route('reservas.mapa', ['fecha' => $manana, 'hora' => '13:00']))
            ->assertSee('Sin reserva')
            ->assertSee('Liberar');
    }

    public function test_liberar_una_mesa_la_devuelve_al_circuito(): void
    {
        $manana = now()->addDay()->toDateString();
        $mesa5 = \App\Models\Mesa::where('numero', 5)->first();

        $this->actingAs($this->personal)->post(route('mesas.ocupar'), [
            'mesa_id' => $mesa5->id,
            'fecha' => $manana,
            'hora' => '13:00',
        ]);

        $ocupacion = Reserva::where('sin_reserva', true)->first();
        $this->actingAs($this->personal)->post(route('mesas.liberar', $ocupacion))
            ->assertSessionHas('exito');

        $this->reservar();
        $this->assertSame([5], $this->mesasDeLaUltimaReserva());
    }

    public function test_ocupar_una_mesa_ya_ocupada_avisa_sin_duplicar(): void
    {
        $manana = now()->addDay()->toDateString();
        $mesa5 = \App\Models\Mesa::where('numero', 5)->first();

        $datos = ['mesa_id' => $mesa5->id, 'fecha' => $manana, 'hora' => '13:00'];

        $this->actingAs($this->personal)->post(route('mesas.ocupar'), $datos);
        $this->actingAs($this->personal)->post(route('mesas.ocupar'), $datos)
            ->assertSessionHasErrors('mapa');

        $this->assertSame(1, Reserva::count());
    }

    public function test_ocupar_y_liberar_mesas_exige_iniciar_sesion(): void
    {
        auth()->logout();

        $this->post(route('mesas.ocupar'), [])->assertRedirect(route('login'));
    }

    public function test_el_listado_ordena_por_hora_y_despues_alfabeticamente(): void
    {
        $manana = now()->addDay()->toDateString();

        $this->reservar(['nombre' => 'Zacarias', 'hora' => '13:00']);
        $this->reservar(['nombre' => 'Ana', 'hora' => '13:00', 'telefono' => '611111111']);
        $this->reservar(['nombre' => 'Alberto', 'hora' => '14:00', 'telefono' => '622222222']);

        // Misma hora: Ana antes que Zacarias; hora posterior al final aunque empiece por A
        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['fecha' => $manana]))
            ->assertSeeInOrder(['Ana', 'Zacarias', 'Alberto']);
    }

    public function test_el_buscador_encuentra_por_localizador_nombre_o_telefono(): void
    {
        $this->reservar(); // mañana 13:00, Prueba García, 600000000
        $reserva = Reserva::first();

        // Por localizador (incluso en minúsculas)
        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['buscar' => strtolower($reserva->localizador)]))
            ->assertSee('Prueba García');

        // Por apellidos
        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['buscar' => 'García']))
            ->assertSee($reserva->localizador);

        // Por teléfono
        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['buscar' => '600000000']))
            ->assertSee('Prueba García');

        // Sin coincidencias
        $this->actingAs($this->personal)
            ->get(route('reservas.index', ['buscar' => 'ZZZZ99']))
            ->assertSee('Sin resultados');
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
