@extends('layouts.app')

@section('contenido')
    <div class="tarjeta">
        <h2 style="margin-bottom: 1rem;">Mapa de mesas</h2>

        <form method="GET" action="{{ route('reservas.mapa') }}" style="display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <div>
                <label for="fecha">Día</label>
                <input type="date" id="fecha" name="fecha" value="{{ $fecha->toDateString() }}">
            </div>
            <div>
                <label for="hora">Hora</label>
                <select id="hora" name="hora">
                    @foreach ($horas as $opcion)
                        <option value="{{ $opcion }}" @selected($opcion === $hora)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="boton">Ver</button>
            <span style="margin-left: auto; font-size: .8rem; color: #999999;">
                <span class="mesa-leyenda mesa-libre"></span> libre
                <span class="mesa-leyenda mesa-ocupada" style="margin-left: .75rem;"></span> ocupada
            </span>
        </form>

        <p style="color: #999999; font-size: .8rem; margin-bottom: 1rem;">
            "Ocupar" marca la mesa para un cliente que llega sin reserva (queda bloqueada
            en esta franja y la web no puede asignarla). "Liberar" la devuelve al circuito.
        </p>

        @foreach (['dentro' => 'Comedor de dentro', 'terraza' => 'Terraza'] as $comedor => $titulo)
            <h3 style="font-weight: 600; font-size: .95rem;">{{ $titulo }}</h3>
            <div class="mapa-comedor">
                @foreach ($comedores[$comedor] ?? [] as $mesa)
                    @php $reserva = $ocupadas[$mesa->id] ?? null; @endphp
                    <div class="mesa {{ $reserva ? 'mesa-ocupada' : 'mesa-libre' }}"
                         @if ($reserva) title="{{ $reserva->nombre }} {{ $reserva->apellidos }} · {{ $reserva->personas }} pers. · {{ $reserva->fecha_hora->format('H:i') }}" @endif>
                        <strong>{{ $mesa->numero === 0 ? 'Pared' : $mesa->numero }}</strong>
                        <small>{{ $mesa->capacidad }} pers.</small>
                        @if ($reserva && $reserva->sin_reserva)
                            <small>Sin reserva</small>
                            <form method="POST" action="{{ route('mesas.liberar', $reserva) }}">
                                @csrf
                                <button type="submit" class="boton-mesa boton-mesa-liberar">Liberar</button>
                            </form>
                        @elseif ($reserva)
                            <small>{{ $reserva->fecha_hora->format('H:i') }} · {{ Str::limit($reserva->nombre, 10, '…') }}</small>
                        @else
                            <form method="POST" action="{{ route('mesas.ocupar') }}">
                                @csrf
                                <input type="hidden" name="mesa_id" value="{{ $mesa->id }}">
                                <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                                <input type="hidden" name="hora" value="{{ $hora }}">
                                <button type="submit" class="boton-mesa">Ocupar</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endsection
