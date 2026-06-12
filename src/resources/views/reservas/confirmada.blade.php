@extends('layouts.app')

@section('contenido')
    <div class="tarjeta" style="max-width: 480px; margin: 0 auto; text-align: center;">
        <div style="font-size: 3rem;">✅</div>
        <h2 style="margin: .5rem 0 1.25rem;">¡Reserva confirmada!</h2>

        <p style="margin-bottom: 1.25rem;">
            {{ $datos['nombre'] }}, te esperamos el <strong>{{ $datos['fecha'] }}</strong>
            a las <strong>{{ $datos['hora'] }}</strong>,
            mesa para <strong>{{ $datos['personas'] }}</strong>
            ({{ $datos['comedor'] === 'terraza' ? 'en la terraza' : 'en el comedor interior' }}).
        </p>

        @if (! empty($datos['localizador']))
            <p style="background: #f5f2ed; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem;">
                Tu localizador es
                <strong style="font-family: monospace; font-size: 1.2rem; letter-spacing: .1em;">{{ $datos['localizador'] }}</strong><br>
                <span style="color: #737373; font-size: .85rem;">Guárdalo: te lo podemos pedir al llegar o si nos llamas.</span>
            </p>
        @endif

        @if (! empty($datos['email']))
            <p style="color: #737373; font-size: .9rem; margin-bottom: 1.5rem;">
                Te hemos enviado la confirmación a <strong>{{ $datos['email'] }}</strong>.
                En el email tienes un enlace para anular la reserva si te surge un imprevisto.
            </p>
        @else
            <p style="color: #737373; font-size: .9rem; margin-bottom: 1.5rem;">
                Si no puedes venir, llámanos para anular la reserva, por favor. ¡Gracias!
            </p>
        @endif

        <a href="{{ route('reservas.create') }}" class="boton">Hacer otra reserva</a>
    </div>
@endsection
