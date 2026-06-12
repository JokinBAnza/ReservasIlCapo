<?php

namespace App\Http\Controllers;

use App\Mail\ReservaConfirmada;
use App\Models\Mesa;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservaController extends Controller
{
    // Listado de reservas de un día (hoy por defecto)
    public function index(Request $request)
    {
        $fecha = $request->date('fecha') ?? today();

        $reservas = Reserva::with('mesas')
            ->whereDate('fecha_hora', $fecha)
            ->orderBy('fecha_hora')
            ->get();

        return view('reservas.index', [
            'reservas' => $reservas,
            'fecha' => $fecha,
        ]);
    }

    // Formulario de nueva reserva
    public function create()
    {
        return view('reservas.create', [
            'maxPersonas' => $this->maxPersonas(),
            'horasPorTurno' => $this->horasPorTurno(),
        ]);
    }

    // Crear la reserva asignando automáticamente mesa(s) libre(s)
    public function store(Request $request)
    {
        $maxPersonas = $this->maxPersonas();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'telefono' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora' => ['required', 'in:'.implode(',', $this->horasDisponibles())],
            'personas' => ['required', 'integer', 'min:1', "max:{$maxPersonas}"],
            'perro' => ['nullable', 'boolean'],
            'comedor' => ['required', 'in:dentro,terraza'],
        ], [
            'personas.max' => "Para grupos de más de :max personas, gestionad la reserva por teléfono.",
            'hora.in' => 'La hora tiene que estar dentro del horario de reservas.',
        ]);

        // Trampa antibots: el campo "fax" está oculto para las personas;
        // si llega relleno es un robot y fingimos que la reserva se hizo
        if ($request->filled('fax')) {
            return redirect()->route('reservas.confirmada')->with('reserva_confirmada', [
                'nombre' => $datos['nombre'],
                'fecha' => Carbon::parse($datos['fecha'])->format('d/m/Y'),
                'hora' => $datos['hora'],
                'personas' => $datos['personas'],
                'comedor' => $datos['comedor'],
                'localizador' => Reserva::generarLocalizador(),
            ]);
        }

        // Límite de reservas pendientes por teléfono (solo web pública)
        if (! Auth::check()) {
            $limiteTelefono = config('reservas.maximo_reservas_por_telefono');

            $pendientes = Reserva::where('telefono', $datos['telefono'])
                ->where('fecha_hora', '>=', now())
                ->count();

            if ($pendientes >= $limiteTelefono) {
                return back()
                    ->withInput()
                    ->withErrors(['telefono' => "Este teléfono ya tiene {$limiteTelefono} reservas pendientes. Para reservar más, llamadnos por teléfono."]);
            }
        }

        $conPerro = $request->boolean('perro');

        if ($conPerro && $datos['comedor'] === 'dentro') {
            return back()
                ->withInput()
                ->withErrors(['comedor' => 'Las reservas con perro tienen que ser en la terraza.']);
        }

        $fechaHora = Carbon::parse($datos['fecha'].' '.$datos['hora']);

        $limitePorHora = config('reservas.maximo_reservas_por_hora');

        if (Reserva::where('fecha_hora', $fechaHora)->count() >= $limitePorHora) {
            return back()
                ->withInput()
                ->withErrors(['disponibilidad' => "A las {$datos['hora']} ya hay el máximo de {$limitePorHora} reservas. Elegid otra hora."]);
        }

        $mesas = $this->buscarMesasLibres($fechaHora, $datos['personas'], $datos['comedor']);

        if (! $mesas) {
            return back()
                ->withInput()
                ->withErrors(['disponibilidad' => 'No quedan mesas libres para ese grupo a esa hora en el comedor elegido.']);
        }

        $reserva = Reserva::create([
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'personas' => $datos['personas'],
            'perro' => $conPerro,
            'fecha_hora' => $fechaHora,
        ]);
        $reserva->mesas()->attach($mesas->pluck('id'));

        // Confirmación por email con enlace para anular. Si el envío falla,
        // la reserva sigue siendo válida: solo lo dejamos anotado en el log.
        // (Si algún día queréis SMS o WhatsApp, este es el sitio para añadirlo.)
        if ($reserva->email) {
            try {
                Mail::to($reserva->email)->send(new ReservaConfirmada($reserva));
            } catch (\Throwable $e) {
                Log::error("No se pudo enviar la confirmación de la reserva {$reserva->id}: {$e->getMessage()}");
            }
        }

        // El personal vuelve al listado del día, con el detalle de mesas
        if (Auth::check()) {
            $numeros = $mesas->pluck('numero')->sort()->implode(' + ');
            $palabra = $mesas->count() > 1 ? 'mesas' : 'mesa';

            return redirect()
                ->route('reservas.index', ['fecha' => $datos['fecha']])
                ->with('exito', "Reserva confirmada: {$palabra} {$numeros} ({$datos['comedor']}) para {$datos['personas']} personas a las {$datos['hora']}.");
        }

        // El cliente ve una pantalla de confirmación (sin el número de mesa,
        // que es cosa interna y puede cambiarse en sala)
        return redirect()
            ->route('reservas.confirmada')
            ->with('reserva_confirmada', [
                'nombre' => $datos['nombre'],
                'fecha' => $fechaHora->format('d/m/Y'),
                'hora' => $datos['hora'],
                'personas' => $datos['personas'],
                'comedor' => $datos['comedor'],
                'email' => $reserva->email,
                'localizador' => $reserva->localizador,
            ]);
    }

    // Página de anulación a la que llega el cliente desde el enlace
    // firmado de su email (la firma la valida el middleware 'signed')
    public function anular(Reserva $reserva)
    {
        return view('reservas.anular', ['reserva' => $reserva]);
    }

    public function confirmarAnulacion(Reserva $reserva)
    {
        $reserva->delete();

        return redirect()
            ->route('reservas.create')
            ->with('exito', 'Tu reserva ha quedado anulada. ¡Esperamos verte pronto!');
    }

    // Pantalla de confirmación para el cliente, tras crear su reserva
    public function confirmada()
    {
        $datos = session('reserva_confirmada');

        if (! $datos) {
            return redirect()->route('reservas.create');
        }

        return view('reservas.confirmada', ['datos' => $datos]);
    }

    // Anular una reserva (las mesas se liberan al borrarse la pivote en cascada)
    public function destroy(Reserva $reserva)
    {
        $fecha = $reserva->fecha_hora->toDateString();
        $reserva->delete();

        return redirect()
            ->route('reservas.index', ['fecha' => $fecha])
            ->with('exito', 'Reserva anulada.');
    }

    /**
     * Busca dónde sentar al grupo. Primero intenta una sola mesa (la más
     * ajustada); si ninguna llega, prueba las combinaciones de mesas contiguas
     * de config/reservas.php, de menor a mayor capacidad total.
     */
    private function buscarMesasLibres(Carbon $fechaHora, int $personas, string $comedor): ?Collection
    {
        $duracion = config('reservas.duracion_horas');
        $inicio = $fechaHora->copy()->subHours($duracion);
        $fin = $fechaHora->copy()->addHours($duracion);

        // Comparación estricta: una reserva a las 20:00 deja la mesa
        // libre para otra a las 22:00 si la duración es de 2 horas.
        $mesasOcupadas = DB::table('mesa_reserva')
            ->join('reservas', 'reservas.id', '=', 'mesa_reserva.reserva_id')
            ->where('reservas.fecha_hora', '>', $inicio)
            ->where('reservas.fecha_hora', '<', $fin)
            ->pluck('mesa_reserva.mesa_id');

        $combinaciones = config('reservas.combinaciones');

        // 1) Una sola mesa, la más pequeña donde quepa el grupo. A igual
        //    capacidad, las mesas que forman parte de una combinación se
        //    asignan las últimas, para dejarlas libres para grupos grandes.
        $enCombinacion = collect($combinaciones)->flatten()->unique();

        $consulta = Mesa::where('comedor', $comedor)
            ->where('capacidad', '>=', $personas)
            ->whereNotIn('id', $mesasOcupadas)
            ->orderBy('capacidad');

        if ($enCombinacion->isNotEmpty()) {
            $marcadores = $enCombinacion->map(fn () => '?')->implode(',');
            $consulta->orderByRaw("CASE WHEN numero IN ({$marcadores}) THEN 1 ELSE 0 END", $enCombinacion->all());
        }

        $mesa = $consulta->orderBy('numero')->first();

        if ($mesa) {
            return collect([$mesa]);
        }

        // 2) Combinaciones de mesas contiguas con todas sus mesas libres,
        //    en el comedor pedido y con capacidad total suficiente

        return collect($combinaciones)
            ->map(fn (array $numeros) => Mesa::whereIn('numero', $numeros)
                ->where('comedor', $comedor)
                ->whereNotIn('id', $mesasOcupadas)
                ->get())
            ->filter(fn (Collection $mesas, int $i) => $mesas->count() === count($combinaciones[$i]))
            ->filter(fn (Collection $mesas) => $mesas->sum('capacidad') >= $personas)
            ->sortBy(fn (Collection $mesas) => $mesas->sum('capacidad'))
            ->first();
    }

    // Horas reservables de cada turno según config/reservas.php,
    // p. ej. ['comida' => ['13:00', '13:15', ...], 'cena' => [...]]
    private function horasPorTurno(): array
    {
        $intervalo = config('reservas.intervalo_minutos');
        $turnos = [];

        foreach (config('reservas.turnos') as $nombre => [$inicio, $fin]) {
            $hora = Carbon::createFromTimeString($inicio);
            $final = Carbon::createFromTimeString($fin);

            $horas = [];
            while ($hora->lte($final)) {
                $horas[] = $hora->format('H:i');
                $hora->addMinutes($intervalo);
            }

            $turnos[$nombre] = $horas;
        }

        return $turnos;
    }

    // Todas las horas reservables del día, sin distinguir turno
    private function horasDisponibles(): array
    {
        return array_merge(...array_values($this->horasPorTurno()));
    }

    // Tamaño máximo de grupo reservable: la mayor mesa o combinación del restaurante
    private function maxPersonas(): int
    {
        $capacidades = Mesa::pluck('capacidad', 'numero');

        $maxCombinacion = collect(config('reservas.combinaciones'))
            ->map(fn (array $numeros) => collect($numeros)->sum(fn (int $n) => $capacidades[$n] ?? 0))
            ->max() ?? 0;

        return max($capacidades->max() ?? 0, $maxCombinacion);
    }
}
