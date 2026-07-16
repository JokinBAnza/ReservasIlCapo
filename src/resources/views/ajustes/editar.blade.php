@extends('layouts.app')

@section('contenido')
    @php
        $nombresDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    @endphp

    <div class="tarjeta" style="margin-bottom: 1.5rem;">
        <h2 style="margin-bottom: 1.25rem;">Ajustes de reservas</h2>

        <form method="POST" action="{{ url('/ajustes') }}">
            @csrf

            <div class="campo">
                <label>Días de la semana abiertos a reservas</label>
                <div style="display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: .4rem;">
                    @foreach ($nombresDias as $numero => $nombre)
                        <label style="display: flex; align-items: center; gap: .4rem; font-weight: 400; margin: 0;">
                            <input type="checkbox" name="dias[]" value="{{ $numero }}"
                                   style="width: auto;" @checked(in_array($numero, $diasAbiertos))>
                            {{ $nombre }}
                        </label>
                    @endforeach
                </div>
                <p style="color: #737373; font-size: .85rem; margin-top: .5rem;">
                    Ejemplo: si cerráis los martes, desmarca "Martes". En verano, marca todos.
                    Para vacaciones largas no desmarquéis días: usad "Cerrar un periodo", más abajo.
                </p>
            </div>

            <div class="campo">
                <label>Turnos y horarios</label>
                @foreach ($turnos as $nombre => $turno)
                    <div style="display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-top: .5rem;">
                        <label style="display: flex; align-items: center; gap: .4rem; min-width: 110px; margin: 0;">
                            <input type="checkbox" name="turnos[{{ $nombre }}][activo]" value="1"
                                   style="width: auto;" @checked($turno['activo'])>
                            {{ ucfirst($nombre) }}
                        </label>
                        <span style="color: #737373;">de</span>
                        <input type="time" name="turnos[{{ $nombre }}][inicio]" value="{{ $turno['inicio'] }}" step="900" style="width: auto;">
                        <span style="color: #737373;">a</span>
                        <input type="time" name="turnos[{{ $nombre }}][fin]" value="{{ $turno['fin'] }}" step="900" style="width: auto;">
                    </div>
                @endforeach
                <p style="color: #737373; font-size: .85rem; margin-top: .5rem;">
                    Se ofrecen horas cada 15 minutos entre el inicio y el fin, ambos incluidos.
                    Desmarca un turno para no aceptar reservas en él (por ejemplo, sin cenas en invierno).
                </p>
            </div>

            <div class="fila" style="max-width: 660px;">
                <div class="campo">
                    <label for="limite">Máximo de reservas por franja horaria</label>
                    <input type="number" id="limite" name="limite" value="{{ old('limite', $limitePorHora) }}" min="1" max="50" required>
                </div>
                <div class="campo">
                    <label for="antelacion">Antelación mínima online (minutos)</label>
                    <input type="number" id="antelacion" name="antelacion" value="{{ old('antelacion', $antelacion) }}" min="0" max="2880" step="15">
                    <p style="color: #737373; font-size: .8rem; margin-top: .35rem;">
                        La web deja de aceptar reservas cuando falta menos de este tiempo
                        para la hora pedida: esas mesas quedan para quien llega sin reserva.
                        El personal no tiene este límite.
                    </p>
                </div>
            </div>

            <button type="submit" class="boton">Guardar ajustes</button>
        </form>
    </div>

    <div class="tarjeta">
        <h2 style="margin-bottom: .5rem;">Vacaciones y festivos</h2>
        <p style="color: #737373; font-size: .9rem; margin-bottom: 1.25rem;">
            Cerrar un día o un periodo <strong>no anula</strong> las reservas que ya existan
            en esas fechas: revisadlas en el listado y avisad a esos clientes.
        </p>

        <form method="POST" action="{{ route('ajustes.cerrar-rango') }}" style="display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1rem;">
            @csrf
            <div>
                <label for="desde">Cerrar un periodo — desde</label>
                <input type="date" id="desde" name="desde" min="{{ today()->toDateString() }}" required>
            </div>
            <div>
                <label for="hasta">hasta (incluido)</label>
                <input type="date" id="hasta" name="hasta" min="{{ today()->toDateString() }}" required>
            </div>
            <button type="submit" class="boton">Cerrar periodo</button>
        </form>

        @if ($rangosCerrados->isNotEmpty())
            <table style="max-width: 480px; margin-bottom: 1.5rem;">
                <tbody>
                    @foreach ($rangosCerrados as $rango)
                        <tr>
                            <td>Del {{ \Carbon\Carbon::parse($rango[0])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($rango[1])->format('d/m/Y') }}</td>
                            <td style="text-align: right;">
                                <form method="POST" action="{{ route('ajustes.abrir-rango') }}">
                                    @csrf
                                    <input type="hidden" name="desde" value="{{ $rango[0] }}">
                                    <input type="hidden" name="hasta" value="{{ $rango[1] }}">
                                    <button type="submit" class="boton boton-peligro">Reabrir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <form method="POST" action="{{ route('ajustes.cerrar') }}" style="display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1rem;">
            @csrf
            <div>
                <label for="fecha">Cerrar un día suelto</label>
                <input type="date" id="fecha" name="fecha" min="{{ today()->toDateString() }}" required>
            </div>
            <button type="submit" class="boton">Cerrar día</button>
        </form>

        @if ($fechasCerradas->isEmpty())
            <p class="sin-datos" style="padding: 1rem 0;">No hay fechas cerradas próximas.</p>
        @else
            <table style="max-width: 420px;">
                <tbody>
                    @foreach ($fechasCerradas as $fecha)
                        <tr>
                            <td>{{ $nombresDias[\Carbon\Carbon::parse($fecha)->dayOfWeekIso] }} {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</td>
                            <td style="text-align: right;">
                                <form method="POST" action="{{ route('ajustes.abrir') }}">
                                    @csrf
                                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                                    <button type="submit" class="boton boton-peligro">Reabrir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="tarjeta" style="margin-top: 1.5rem;">
        <h2 style="margin-bottom: .5rem;">Capacidad de las mesas</h2>
        <p style="color: #737373; font-size: .9rem; margin-bottom: 1rem;">
            Los cambios valen para las reservas nuevas; las ya asignadas no se recolocan.
            Los máximos de los grupos grandes (mesas combinadas) se recalculan solos.
        </p>

        <form method="POST" action="{{ route('ajustes.mesas') }}">
            @csrf

            @foreach (['dentro' => 'Comedor de dentro', 'terraza' => 'Terraza'] as $comedor => $titulo)
                <h3 style="font-weight: 600; font-size: .95rem;">{{ $titulo }}</h3>
                <div style="display: flex; flex-wrap: wrap; gap: .6rem; margin: .6rem 0 1.25rem;">
                    @foreach ($mesas[$comedor] ?? [] as $mesa)
                        <label style="width: 88px; margin: 0; text-align: center; font-weight: 400;">
                            <span style="display: block; font-weight: 600; font-size: .78rem; margin-bottom: .25rem;">
                                {{ $mesa->numero === 0 ? 'Pared' : 'Mesa '.$mesa->numero }}
                            </span>
                            <input type="number" name="capacidades[{{ $mesa->id }}]" value="{{ $mesa->capacidad }}"
                                   min="1" max="20" required style="text-align: center;">
                        </label>
                    @endforeach
                </div>
            @endforeach

            <button type="submit" class="boton">Guardar capacidades</button>
        </form>
    </div>

    <div class="tarjeta" style="margin-top: 1.5rem;">
        <h2 style="margin-bottom: 1.25rem;">Cambiar contraseña</h2>

        <form method="POST" action="{{ route('password.edit') }}" style="max-width: 380px;">
            @csrf

            <div class="campo">
                <label for="actual">Contraseña actual</label>
                <input type="password" id="actual" name="actual" required>
            </div>

            <div class="campo">
                <label for="nueva">Contraseña nueva <span style="font-weight: 400; color: #999999;">(mínimo 10 caracteres)</span></label>
                <input type="password" id="nueva" name="nueva" required minlength="10">
            </div>

            <div class="campo">
                <label for="nueva_confirmation">Repite la contraseña nueva</label>
                <input type="password" id="nueva_confirmation" name="nueva_confirmation" required minlength="10">
            </div>

            <button type="submit" class="boton">Cambiar contraseña</button>
        </form>
    </div>
@endsection
