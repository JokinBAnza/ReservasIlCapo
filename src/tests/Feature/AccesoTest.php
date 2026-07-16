<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\User;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_puede_reservar_sin_iniciar_sesion(): void
    {
        $this->seed(MesasSeeder::class);

        $respuesta = $this->post(route('reservas.store'), [
            'nombre' => 'Cliente',
            'apellidos' => 'López',
            'telefono' => '600000000',
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '13:00',
            'personas' => 2,
            'comedor' => 'dentro',
        ]);

        $respuesta->assertRedirect(route('reservas.confirmada'));
        $respuesta->assertSessionHas('reserva_confirmada');
        $this->assertSame(1, Reserva::count());

        // Y la pantalla de confirmación muestra sus datos
        $this->get(route('reservas.confirmada'))->assertSee('Cliente');
    }

    public function test_la_confirmacion_sin_reserva_reciente_redirige_al_formulario(): void
    {
        $this->get(route('reservas.confirmada'))->assertRedirect(route('reservas.create'));
    }

    public function test_el_listado_de_reservas_exige_iniciar_sesion(): void
    {
        $this->get(route('reservas.index'))->assertRedirect(route('login'));
    }

    public function test_un_cliente_no_puede_anular_reservas(): void
    {
        $this->seed(MesasSeeder::class);
        $reserva = Reserva::create([
            'nombre' => 'Cliente',
            'apellidos' => 'López',
            'telefono' => '600000000',
            'personas' => 2,
            'fecha_hora' => now()->addDay()->setTime(13, 0),
        ]);

        $this->delete(route('reservas.destroy', $reserva))->assertRedirect(route('login'));
        $this->assertSame(1, Reserva::count());
    }

    public function test_el_personal_puede_entrar_y_salir(): void
    {
        $personal = User::factory()->create(['usuario' => 'personal', 'password' => 'secreta-123']);

        $this->post(route('login'), [
            'usuario' => 'personal',
            'password' => 'secreta-123',
        ])->assertRedirect(route('reservas.index'));

        $this->assertAuthenticatedAs($personal);

        $this->post(route('logout'))->assertRedirect(route('reservas.create'));
        $this->assertGuest();
    }

    public function test_con_contrasena_incorrecta_no_se_entra(): void
    {
        User::factory()->create(['usuario' => 'personal', 'password' => 'secreta-123']);

        $this->post(route('login'), [
            'usuario' => 'personal',
            'password' => 'equivocada',
        ])->assertSessionHasErrors('usuario');

        $this->assertGuest();
    }

    public function test_el_login_se_bloquea_tras_demasiados_intentos(): void
    {
        User::factory()->create(['usuario' => 'personal', 'password' => 'secreta-123']);

        foreach (range(1, 5) as $ignorado) {
            $this->post(route('login'), ['usuario' => 'personal', 'password' => 'mal']);
        }

        // El sexto intento en el mismo minuto recibe un 429 (demasiadas peticiones)
        $this->post(route('login'), ['usuario' => 'personal', 'password' => 'secreta-123'])
            ->assertStatus(429);
    }

    public function test_el_personal_puede_cambiar_su_contrasena(): void
    {
        $personal = User::factory()->create(['usuario' => 'personal', 'password' => 'antigua-123']);

        $this->actingAs($personal)->post(route('password.edit'), [
            'actual' => 'antigua-123',
            'nueva' => 'nueva-segura-456',
            'nueva_confirmation' => 'nueva-segura-456',
        ])->assertRedirect(route('ajustes.editar'));

        $this->post(route('logout'));

        $this->post(route('login'), [
            'usuario' => 'personal',
            'password' => 'nueva-segura-456',
        ]);
        $this->assertAuthenticatedAs($personal);
    }

    public function test_sin_la_contrasena_actual_no_se_puede_cambiar(): void
    {
        $personal = User::factory()->create(['password' => 'antigua-123']);

        $this->actingAs($personal)->post(route('password.edit'), [
            'actual' => 'equivocada',
            'nueva' => 'nueva-segura-456',
            'nueva_confirmation' => 'nueva-segura-456',
        ])->assertSessionHasErrors('actual');
    }

    public function test_cambiar_la_contrasena_exige_estar_dentro(): void
    {
        $this->post(route('password.edit'), [
            'actual' => 'x',
            'nueva' => 'nueva-segura-456',
            'nueva_confirmation' => 'nueva-segura-456',
        ])->assertRedirect(route('login'));
    }
}
