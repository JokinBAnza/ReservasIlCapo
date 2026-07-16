@extends('layouts.app')

@section('contenido')
    <div class="tarjeta" style="max-width: 480px; margin: 0 auto;">
        <h2 style="margin-bottom: .5rem;">Anular una reserva</h2>
        <p style="color: #737373; font-size: .9rem; margin-bottom: 1.25rem;">
            Introduce el <strong>localizador</strong> de tu reserva (aparece en la pantalla
            de confirmación y en el email) y el <strong>teléfono</strong> con el que reservaste.
        </p>

        <form method="POST" action="{{ url('/anular-reserva') }}">
            @csrf

            <div class="campo">
                <label for="localizador">Localizador</label>
                <input type="text" id="localizador" name="localizador" value="{{ old('localizador') }}"
                       required maxlength="10" placeholder="K7M3PD"
                       style="text-transform: uppercase; font-family: monospace; letter-spacing: .15em;">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono de la reserva</label>
                <input type="tel" id="telefono" name="telefono" value="{{ old('telefono') }}" required maxlength="20" placeholder="600 000 000">
            </div>

            <button type="submit" class="boton">Buscar mi reserva</button>
        </form>
    </div>
@endsection
