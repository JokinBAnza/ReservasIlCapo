@extends('layouts.app')

@section('contenido')
    <div class="tarjeta">
        <h2 style="margin-bottom: 1.25rem;">Nueva reserva</h2>

        <form method="POST" action="{{ route('reservas.store') }}">
            @csrf

            <div class="fila">
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="100" placeholder="Nombre">
                </div>
                <div class="campo">
                    <label for="apellidos">Apellidos</label>
                    <input type="text" id="apellidos" name="apellidos" value="{{ old('apellidos') }}" required maxlength="100" placeholder="Apellidos">
                </div>
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="{{ old('telefono') }}" required maxlength="20" placeholder="600 000 000">
                </div>
                <div class="campo">
                    <label for="email">Email <span style="font-weight: 400; color: #737373;">(opcional)</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="Para enviarte la confirmación">
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
                    <input type="date" id="fecha" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
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
                <label for="perro" style="margin-bottom: 0;">Traen perro (la reserva será en la terraza)</label>
            </div>

            <button type="submit" class="boton">Reservar mesa</button>
        </form>
    </div>

    <script>
        // Turno -> horas disponibles
        const horasPorTurno = @json($horasPorTurno);
        const turno = document.getElementById('turno');
        const hora = document.getElementById('hora');
        const horaPrevia = @json(old('hora'));

        function rellenarHoras() {
            hora.innerHTML = '';
            const vacia = new Option('Elegir hora', '');
            vacia.disabled = true;
            vacia.selected = true;
            hora.appendChild(vacia);

            for (const h of horasPorTurno[turno.value] ?? []) {
                const opcion = new Option(h, h);
                opcion.selected = (h === horaPrevia);
                hora.appendChild(opcion);
            }
        }

        // Si venimos de un error de validación, recuperar el turno y la hora elegidos
        if (horaPrevia) {
            for (const [nombre, horas] of Object.entries(horasPorTurno)) {
                if (horas.includes(horaPrevia)) {
                    turno.value = nombre;
                }
            }
        }

        turno.addEventListener('change', rellenarHoras);
        rellenarHoras();

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
