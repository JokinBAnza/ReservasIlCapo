<x-mail::message>
# Nueva reserva desde la web

- **Nombre:** {{ $reserva->nombre }} {{ $reserva->apellidos }}
- **Teléfono:** {{ $reserva->telefono }}
- **Fecha:** {{ $reserva->fecha_hora->format('d/m/Y') }} a las {{ $reserva->fecha_hora->format('H:i') }}
- **Personas:** {{ $reserva->personas }}
- **Mesa(s):** {{ $reserva->mesas->pluck('numero')->sort()->implode(' + ') }} ({{ $reserva->mesas->first()?->comedor }})
- **Localizador:** {{ $reserva->localizador }}
@if ($reserva->perro)
- **Traen perro** 🐕
@endif
@if ($reserva->observaciones)
- **Observaciones:** {{ $reserva->observaciones }}
@endif

Il Capo · aviso automático del sistema de reservas
</x-mail::message>
