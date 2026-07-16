<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjustesTest extends TestCase
{
    use RefreshDatabase;

    private User $personal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MesasSeeder::class);
        $this->personal = User::factory()->create();
    }

    private function reservarPublico(string $fecha, array $extra = [])
    {
        return $this->post(route('reservas.store'), array_merge([
            'nombre' => 'Cliente',
            'apellidos' => 'López',
            'telefono' => '60000'.rand(1000, 9999),
            'fecha' => $fecha,
            'hora' => '13:00',
            'personas' => 2,
            'comedor' => 'dentro',
        ], $extra));
    }

    public function test_la_pagina_de_ajustes_exige_iniciar_sesion(): void
    {
        $this->get(route('ajustes.editar'))->assertRedirect(route('login'));

        $this->actingAs($this->personal)->get(route('ajustes.editar'))->assertOk();
    }

    public function test_cerrar_los_martes_rechaza_reservas_de_martes(): void
    {
        // Abrir todos menos el martes (2)
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 3, 4, 5, 6, 7],
            'limite' => 10,
        ])->assertSessionHas('exito');

        $martes = Carbon::parse('next tuesday')->toDateString();
        $miercoles = Carbon::parse('next wednesday')->toDateString();

        auth()->logout();

        $this->reservarPublico($martes)->assertSessionHasErrors('fecha');
        $this->assertSame(0, Reserva::count());

        $this->reservarPublico($miercoles)->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Reserva::count());
    }

    public function test_una_fecha_cerrada_puntual_rechaza_reservas_y_al_reabrir_las_acepta(): void
    {
        $fecha = now()->addDays(3)->toDateString();

        $this->actingAs($this->personal)->post(route('ajustes.cerrar'), ['fecha' => $fecha]);
        auth()->logout();

        $this->reservarPublico($fecha)->assertSessionHasErrors('fecha');

        $this->actingAs($this->personal)->post(route('ajustes.abrir'), ['fecha' => $fecha]);
        auth()->logout();

        $this->reservarPublico($fecha)->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Reserva::count());
    }

    public function test_el_limite_por_franja_se_puede_cambiar_desde_ajustes(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 1,
        ]);
        auth()->logout();

        $fecha = now()->addDay()->toDateString();

        $this->reservarPublico($fecha)->assertSessionDoesntHaveErrors();
        $this->reservarPublico($fecha)->assertSessionHasErrors('disponibilidad');
        $this->assertSame(1, Reserva::count());
    }

    public function test_un_periodo_de_vacaciones_cierra_las_reservas_y_se_puede_reabrir(): void
    {
        $desde = now()->addDays(10)->toDateString();
        $hasta = now()->addDays(30)->toDateString();

        $this->actingAs($this->personal)
            ->post(route('ajustes.cerrar-rango'), ['desde' => $desde, 'hasta' => $hasta])
            ->assertSessionHas('exito');
        auth()->logout();

        // Dentro del periodo: rechazada. Después del periodo: aceptada.
        $this->reservarPublico(now()->addDays(20)->toDateString())->assertSessionHasErrors('fecha');
        $this->reservarPublico(now()->addDays(35)->toDateString())->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Reserva::count());

        $this->actingAs($this->personal)
            ->post(route('ajustes.abrir-rango'), ['desde' => $desde, 'hasta' => $hasta]);
        auth()->logout();

        $this->reservarPublico(now()->addDays(20)->toDateString())->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Reserva::count());
    }

    public function test_los_horarios_de_los_turnos_son_editables(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 10,
            'turnos' => [
                'comida' => ['activo' => 1, 'inicio' => '12:00', 'fin' => '14:00'],
                'cena' => ['activo' => 1, 'inicio' => '20:00', 'fin' => '23:00'],
            ],
        ])->assertSessionHas('exito');
        auth()->logout();

        $fecha = now()->addDay()->toDateString();

        // Horas del horario nuevo: entran
        $this->reservarPublico($fecha, ['hora' => '12:15'])->assertSessionDoesntHaveErrors();
        $this->reservarPublico($fecha, ['hora' => '22:45'])->assertSessionDoesntHaveErrors();

        // Hora del horario antiguo que ya no existe: rechazada
        $this->reservarPublico($fecha, ['hora' => '14:30'])->assertSessionHasErrors('hora');
        $this->assertSame(2, Reserva::count());
    }

    public function test_desactivar_el_turno_de_cena_rechaza_sus_horas(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 10,
            'turnos' => [
                'comida' => ['activo' => 1, 'inicio' => '13:00', 'fin' => '15:00'],
                'cena' => ['inicio' => '20:00', 'fin' => '22:00'], // sin 'activo'
            ],
        ])->assertSessionHas('exito');
        auth()->logout();

        $fecha = now()->addDay()->toDateString();

        $this->reservarPublico($fecha, ['hora' => '21:00'])->assertSessionHasErrors('hora');
        $this->reservarPublico($fecha, ['hora' => '13:00'])->assertSessionDoesntHaveErrors();
    }

    public function test_debe_quedar_al_menos_un_turno_activo(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 10,
            'turnos' => [
                'comida' => ['inicio' => '13:00', 'fin' => '15:00'],
                'cena' => ['inicio' => '20:00', 'fin' => '22:00'],
            ],
        ])->assertSessionHasErrors('turnos');

        $this->assertNull(Ajuste::valor('turnos'));
    }

    public function test_el_fin_de_un_turno_debe_ser_posterior_al_inicio(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 10,
            'turnos' => [
                'comida' => ['activo' => 1, 'inicio' => '15:00', 'fin' => '13:00'],
                'cena' => ['activo' => 1, 'inicio' => '20:00', 'fin' => '22:00'],
            ],
        ])->assertSessionHasErrors('turnos.comida.fin');
    }

    public function test_la_antelacion_minima_frena_las_reservas_online_de_ultima_hora(): void
    {
        // 48 horas de antelación: ni siquiera mañana es reservable online
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'limite' => 10,
            'antelacion' => 2880,
        ])->assertSessionHas('exito');
        auth()->logout();

        $manana = now()->addDay()->toDateString();

        $this->reservarPublico($manana)->assertSessionHasErrors('hora');
        $this->assertSame(0, Reserva::count());

        // Pero el personal sí puede (reservas telefónicas de última hora)
        $this->actingAs($this->personal)->post(route('reservas.store'), [
            'nombre' => 'Telefono',
            'apellidos' => 'DeUltimaHora',
            'telefono' => '600777888',
            'fecha' => $manana,
            'hora' => '13:00',
            'personas' => 2,
            'comedor' => 'dentro',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Reserva::count());
    }

    public function test_no_se_pueden_cerrar_todos_los_dias(): void
    {
        $this->actingAs($this->personal)->post('/ajustes', [
            'dias' => [],
            'limite' => 10,
        ])->assertSessionHasErrors('dias');

        $this->assertNull(Ajuste::valor('dias_abiertos'));
    }
}
