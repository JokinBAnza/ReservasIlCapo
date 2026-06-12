@extends('layouts.app')

@section('contenido')
    <div class="tarjeta" style="max-width: 480px; margin: 0 auto; text-align: center;">
        @if ($reserva->fecha_hora->isPast())
            <h2 style="margin-bottom: 1rem;">Esta reserva ya ha pasado</h2>
            <p style="color: #737373;">No hace falta anularla. ¡Esperamos que disfrutaras!</p>
        @else
            <h2 style="margin-bottom: 1rem;">¿Anular tu reserva?</h2>

            <p style="margin-bottom: 1.5rem;">
                {{ $reserva->nombre }}, tu reserva
                <strong style="font-family: monospace;">{{ $reserva->localizador }}</strong>
                es el <strong>{{ $reserva->fecha_hora->format('d/m/Y') }}</strong>
                a las <strong>{{ $reserva->fecha_hora->format('H:i') }}</strong>
                para <strong>{{ $reserva->personas }}</strong>.
            </p>

            <form method="POST" action="{{ url()->full() }}">
                @csrf
                <button type="submit" class="boton boton-peligro" style="font-size: 1rem; padding: .65rem 1.4rem;">
                    Sí, anular la reserva
                </button>
            </form>

            <p style="margin-top: 1rem;">
                <a href="{{ route('reservas.create') }}" style="color: #737373;">No, volver</a>
            </p>
        @endif
    </div>
@endsection
