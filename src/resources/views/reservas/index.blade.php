@extends('layouts.app')

@section('contenido')
    <div class="tarjeta">
        <form method="GET" action="{{ route('reservas.index') }}" style="display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label for="fecha">Reservas del día</label>
                <input type="date" id="fecha" name="fecha" value="{{ $fecha->toDateString() }}">
            </div>
            <button type="submit" class="boton">Ver</button>
        </form>

        @if ($reservas->isEmpty())
            <p class="sin-datos">No hay reservas para el {{ $fecha->format('d/m/Y') }}.</p>
        @else
            <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Localiz.</th>
                        <th>Pers.</th>
                        <th>Mesa</th>
                        <th>Comedor</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reservas as $reserva)
                        <tr>
                            <td><strong>{{ $reserva->fecha_hora->format('H:i') }}</strong></td>
                            <td>{{ $reserva->nombre }} {{ $reserva->apellidos }}@if ($reserva->perro) <span title="Traen perro">🐕</span>@endif</td>
                            <td>{{ $reserva->telefono }}</td>
                            <td style="font-family: monospace;">{{ $reserva->localizador }}</td>
                            <td>{{ $reserva->personas }}</td>
                            <td>{{ $reserva->mesas->pluck('numero')->sort()->implode(' + ') }}</td>
                            <td>
                                <span class="etiqueta etiqueta-{{ $reserva->mesas->first()->comedor }}">
                                    {{ $reserva->mesas->first()->comedor }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('reservas.destroy', $reserva) }}"
                                      onsubmit="return confirm('¿Anular la reserva de {{ $reserva->nombre }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="boton boton-peligro">Anular</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <p style="margin-top: 1rem; color: #737373; font-size: .9rem;">
                {{ $reservas->count() }} {{ Str::plural('reserva', $reservas->count()) }} ·
                {{ $reservas->sum('personas') }} comensales
            </p>
        @endif
    </div>
@endsection
