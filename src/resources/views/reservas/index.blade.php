@extends('layouts.app')

@section('contenido')
    <div class="tarjeta">
        <div style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
            <form method="GET" action="{{ route('reservas.index') }}" style="display: flex; gap: .75rem; align-items: flex-end;">
                <div>
                    <label for="fecha">Reservas del día</label>
                    <input type="date" id="fecha" name="fecha" value="{{ $fecha->toDateString() }}">
                </div>
                <button type="submit" class="boton">Ver</button>
            </form>

            <form method="GET" action="{{ route('reservas.index') }}" style="display: flex; gap: .75rem; align-items: flex-end;">
                <div>
                    <label for="buscar">Buscar reserva (cualquier fecha)</label>
                    <input type="text" id="buscar" name="buscar" value="{{ $busqueda }}"
                           placeholder="Localizador, nombre o teléfono" style="min-width: 230px;">
                </div>
                <button type="submit" class="boton">Buscar</button>
            </form>
        </div>

        @if ($busqueda !== '')
            <p style="margin-top: 1rem; color: #737373; font-size: .9rem;">
                Resultados de «{{ $busqueda }}» en todas las fechas ·
                <a href="{{ route('reservas.index') }}">volver al día de hoy</a>
            </p>
        @endif

        @if ($reservas->isEmpty())
            <p class="sin-datos">
                @if ($busqueda !== '')
                    Sin resultados para «{{ $busqueda }}».
                @else
                    No hay reservas para el {{ $fecha->format('d/m/Y') }}.
                @endif
            </p>
        @else
            <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        @if ($busqueda !== '')
                            <th>Fecha</th>
                        @endif
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
                            @if ($busqueda !== '')
                                <td>{{ $reserva->fecha_hora->format('d/m/Y') }}</td>
                            @endif
                            <td><strong>{{ $reserva->fecha_hora->format('H:i') }}</strong></td>
                            <td>
                                {{ $reserva->nombre }} {{ $reserva->apellidos }}@if ($reserva->perro) <span title="Con perro">🐕</span>@endif
                                @if ($reserva->observaciones)
                                    <div style="font-size: .78rem; color: #737373;">📝 {{ $reserva->observaciones }}</div>
                                @endif
                            </td>
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
