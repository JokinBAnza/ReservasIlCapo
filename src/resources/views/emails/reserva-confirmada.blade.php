<x-mail::message>
# ¡Reserva confirmada! 🍝

Hola {{ $reserva->nombre }}:

Te esperamos en **Il Capo**:

- **Localizador:** {{ $reserva->localizador }}
- **Fecha:** {{ $reserva->fecha_hora->format('d/m/Y') }}
- **Hora:** {{ $reserva->fecha_hora->format('H:i') }}
- **Personas:** {{ $reserva->personas }}
- **Comedor:** {{ $reserva->mesas->first()?->comedor === 'terraza' ? 'Terraza' : 'Interior' }}

Si te surge un imprevisto, puedes anular la reserva con este botón:

<x-mail::button :url="$urlAnulacion" color="error">
Anular mi reserva
</x-mail::button>

¡Hasta pronto!<br>
Il Capo
</x-mail::message>
