@extends('layouts.app')

@section('contenido')
    <div class="tarjeta">
        <h2 style="margin-bottom: 1.25rem;">Nueva reserva</h2>

        <form method="POST" action="{{ route('reservas.store') }}">
            @csrf

            <div class="fila">
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="30" placeholder="Nombre" autocomplete="given-name">
                </div>
                <div class="campo">
                    <label for="apellidos">Apellidos</label>
                    <input type="text" id="apellidos" name="apellidos" value="{{ old('apellidos') }}" required maxlength="30" placeholder="Apellidos" autocomplete="family-name">
                </div>
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="{{ old('telefono') }}" required maxlength="20"
                           placeholder="600 000 000" autocomplete="tel"
                           pattern="[0-9+\s().\-]{9,20}" title="9 dígitos, o con prefijo internacional (+33...)">
                </div>
                <div class="campo">
                    <label for="email">Email <span style="font-weight: 400; color: #737373;">(opcional)</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="Para enviarte la confirmación" autocomplete="email">
                </div>
            </div>

            {{-- Trampa antibots: campo invisible que las personas no rellenan --}}
            <div class="campo campo-fax" aria-hidden="true">
                <label for="fax">Fax</label>
                <input type="text" id="fax" name="fax" tabindex="-1" autocomplete="off">
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="fecha">Fecha</label>
                    <input type="date" id="fecha" name="fecha" value="{{ old('fecha', $corte['fecha']) }}" min="{{ $corte['fecha'] }}" required>
                </div>
                <div class="campo">
                    <label for="turno">Turno</label>
                    <select id="turno">
                        @foreach (array_keys($horasPorTurno) as $turno)
                            <option value="{{ $turno }}">{{ ucfirst($turno) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="campo">
                <label for="hora">Hora</label>
                <select id="hora" name="hora" required>
                    <option value="" disabled selected>Elegir hora</option>
                </select>
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="personas">Personas</label>
                    <input type="number" id="personas" name="personas" value="{{ old('personas', 2) }}" min="1" max="{{ $maxPersonas }}" required>
                </div>
                <div class="campo">
                    <label for="comedor">Comedor</label>
                    <select id="comedor" name="comedor" required>
                        <option value="dentro" @selected(old('comedor') === 'dentro')>Dentro</option>
                        <option value="terraza" @selected(old('comedor') === 'terraza')>Terraza</option>
                    </select>
                </div>
            </div>

            <div class="campo campo-checkbox">
                <input type="checkbox" id="perro" name="perro" value="1" @checked(old('perro'))>
                <label for="perro" style="margin-bottom: 0;">Voy con perro (la reserva será en la terraza)</label>
            </div>

            <div class="campo">
                <label for="observaciones">Observaciones <span style="font-weight: 400; color: #737373;">(opcional)</span></label>
                <textarea id="observaciones" name="observaciones" maxlength="200" rows="2"
                          placeholder="Alergias, trona para bebé, celebración...">{{ old('observaciones') }}</textarea>
            </div>

            <button type="submit" class="boton">Reservar mesa</button>

            <p style="color: #999999; font-size: .78rem; margin-top: 1rem;">
                Al reservar aceptas que EGOLIFE, S.L. (Il Capo) use estos datos únicamente
                para gestionar tu reserva. Más información en la
                <a href="{{ route('privacidad') }}">política de privacidad</a>.
            </p>
        </form>
    </div>

    <script>
        // Turno -> horas disponibles, sin ofrecer horas ya pasadas o fuera
        // de plazo. "corte" es el primer momento reservable ahora mismo.
        const horasPorTurno = @json($horasPorTurno);
        const corte = @json($corte);
        const fechaInput = document.getElementById('fecha');
        const turno = document.getElementById('turno');
        const hora = document.getElementById('hora');
        const horaPrevia = @json(old('hora'));

        function horasValidas(nombreTurno) {
            const fecha = fechaInput.value;
            let horas = horasPorTurno[nombreTurno] ?? [];
            if (!fecha || fecha < corte.fecha) return [];
            if (fecha === corte.fecha) horas = horas.filter(h => h > corte.hora);
            return horas;
        }

        function rellenarHoras() {
            const horas = horasValidas(turno.value);
            hora.innerHTML = '';
            const vacia = new Option(horas.length ? 'Elegir hora' : 'No quedan horas ese día', '');
            vacia.disabled = true;
            vacia.selected = true;
            hora.appendChild(vacia);

            for (const h of horas) {
                const opcion = new Option(h, h);
                opcion.selected = (h === horaPrevia);
                hora.appendChild(opcion);
            }
        }

        // Deshabilita los turnos que ya no llegan en la fecha elegida (p. ej.
        // "Comida" pasadas las 16:00 de hoy) y salta al primero disponible
        function ajustarTurnos() {
            for (const opcion of turno.options) {
                opcion.disabled = horasValidas(opcion.value).length === 0;
            }
            if (turno.selectedOptions[0] && turno.selectedOptions[0].disabled) {
                const libre = [...turno.options].find(o => !o.disabled);
                if (libre) turno.value = libre.value;
            }
            rellenarHoras();
        }

        // Si venimos de un error de validación, recuperar el turno y la hora elegidos
        if (horaPrevia) {
            for (const [nombre, horas] of Object.entries(horasPorTurno)) {
                if (horas.includes(horaPrevia)) {
                    turno.value = nombre;
                }
            }
        }

        fechaInput.addEventListener('change', ajustarTurnos);
        turno.addEventListener('change', rellenarHoras);
        ajustarTurnos();

        const perro = document.getElementById('perro');
        const comedor = document.getElementById('comedor');
        const opcionDentro = comedor.querySelector('option[value="dentro"]');

        function ajustarComedor() {
            if (perro.checked) {
                comedor.value = 'terraza';
            }
            opcionDentro.disabled = perro.checked;
        }

        perro.addEventListener('change', ajustarComedor);
        ajustarComedor();
    </script>
@endsection
