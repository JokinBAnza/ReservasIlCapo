<?php

namespace Tests\Feature;

use App\Mail\ReservaConfirmada;
use App\Mail\ReservaRecibida;
use App\Models\Reserva;
use App\Models\User;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConfirmacionEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MesasSeeder::class);
    }

    // Reserva desde la web pública, sin iniciar sesión
    private function reservarPublico(array $datos = [])
    {
        return $this->post(route('reservas.store'), array_merge([
            'nombre' => 'Cliente',
            'apellidos' => 'López',
            'telefono' => '600000000',
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '13:00',
            'personas' => 2,
            'comedor' => 'dentro',
        ], $datos));
    }

    public function test_con_email_se_envia_la_confirmacion(): void
    {
        Mail::fake();

        $this->reservarPublico(['email' => 'cliente@example.com'])
            ->assertRedirect(route('reservas.confirmada'));

        Mail::assertSent(ReservaConfirmada::class, fn ($correo) => $correo->hasTo('cliente@example.com'));
        $this->assertSame('cliente@example.com', Reserva::first()->email);
    }

    public function test_sin_email_del_cliente_no_hay_confirmacion_pero_si_aviso_interno(): void
    {
        Mail::fake();

        $this->reservarPublico()->assertRedirect(route('reservas.confirmada'));

        Mail::assertNotSent(ReservaConfirmada::class);
        Mail::assertSent(ReservaRecibida::class);
        $this->assertSame(1, Reserva::count());
    }

    public function test_cada_reserva_de_cliente_avisa_al_restaurante(): void
    {
        Mail::fake();

        $this->reservarPublico();

        Mail::assertSent(ReservaRecibida::class, fn ($aviso) => $aviso->hasTo(config('reservas.email_avisos')));
    }

    public function test_las_reservas_del_personal_no_generan_aviso_interno(): void
    {
        Mail::fake();

        $personal = User::factory()->create();
        $this->actingAs($personal)->post(route('reservas.store'), [
            'nombre' => 'Telefonica',
            'apellidos' => 'DelPersonal',
            'telefono' => '600999888',
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '13:00',
            'personas' => 2,
            'comedor' => 'dentro',
        ])->assertSessionDoesntHaveErrors();

        Mail::assertNotSent(ReservaRecibida::class);
    }

    public function test_el_enlace_firmado_del_email_permite_anular(): void
    {
        $this->reservarPublico(['email' => 'cliente@example.com']);
        $reserva = Reserva::first();

        $enlace = URL::signedRoute('reservas.anular', ['reserva' => $reserva->id]);

        $this->get($enlace)->assertOk()->assertSee('¿Anular tu reserva?');

        $this->post($enlace)->assertRedirect(route('reservas.create'));
        $this->assertSame(0, Reserva::count());
    }

    public function test_un_enlace_sin_firma_valida_no_anula_nada(): void
    {
        $this->reservarPublico();
        $reserva = Reserva::first();

        // Sin firma: prohibido
        $this->get(route('reservas.anular', ['reserva' => $reserva->id]))->assertForbidden();
        $this->post(route('reservas.anular', ['reserva' => $reserva->id]))->assertForbidden();

        $this->assertSame(1, Reserva::count());
    }

    public function test_un_telefono_no_puede_acumular_mas_de_2_reservas_pendientes(): void
    {
        $this->reservarPublico(['hora' => '13:00'])->assertSessionDoesntHaveErrors();
        $this->reservarPublico(['hora' => '20:00'])->assertSessionDoesntHaveErrors();

        $this->reservarPublico(['hora' => '21:00'])->assertSessionHasErrors('telefono');
        $this->assertSame(2, Reserva::count());

        // Otro teléfono sí puede
        $this->reservarPublico(['hora' => '21:00', 'telefono' => '699999999'])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_la_pantalla_de_confirmacion_muestra_el_localizador(): void
    {
        $this->reservarPublico();

        $this->get(route('reservas.confirmada'))
            ->assertSee(Reserva::first()->localizador);
    }

    public function test_un_doble_envio_no_crea_reservas_duplicadas(): void
    {
        $this->reservarPublico()->assertSessionDoesntHaveErrors();

        // Segundo envío idéntico (doble clic): rechazado sin crear nada
        $this->reservarPublico()->assertSessionHasErrors('disponibilidad');
        $this->assertSame(1, Reserva::count());
    }

    public function test_el_cliente_puede_anular_con_localizador_y_telefono(): void
    {
        $this->reservarPublico();
        $reserva = Reserva::first();

        // El localizador en minúsculas y el teléfono con espacios también valen
        $respuesta = $this->post('/anular-reserva', [
            'localizador' => strtolower($reserva->localizador),
            'telefono' => '600 00 00 00',
        ]);

        $respuesta->assertRedirect();
        $destino = $respuesta->headers->get('Location');

        $this->get($destino)->assertOk()->assertSee('Anular tu reserva');

        $this->post($destino);
        $this->assertSame(0, Reserva::count());
    }

    public function test_un_localizador_incorrecto_no_encuentra_reserva(): void
    {
        $this->reservarPublico();

        $this->post('/anular-reserva', [
            'localizador' => 'XXXXXX',
            'telefono' => '600000000',
        ])->assertSessionHasErrors('localizador');

        $this->assertSame(1, Reserva::count());
    }

    public function test_el_localizador_con_telefono_ajeno_no_encuentra_reserva(): void
    {
        $this->reservarPublico();
        $reserva = Reserva::first();

        $this->post('/anular-reserva', [
            'localizador' => $reserva->localizador,
            'telefono' => '699999999',
        ])->assertSessionHasErrors('localizador');

        $this->assertSame(1, Reserva::count());
    }

    public function test_la_pagina_de_anular_reserva_es_publica(): void
    {
        $this->get('/anular-reserva')->assertOk()->assertSee('Localizador');
    }

    public function test_un_bot_que_rellena_el_campo_oculto_no_crea_reserva(): void
    {
        $this->reservarPublico(['fax' => 'spam'])
            ->assertRedirect(route('reservas.confirmada'));

        $this->assertSame(0, Reserva::count());
    }
}
