<?php

namespace App\Http\Controllers;

use App\Mail\ReservaConfirmada;
use App\Models\Ajuste;
use App\Models\Mesa;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ReservaController extends Controller
{
    // Listado de reservas de un día (hoy por defecto)
    public function index(Request $request)
    {
        // Protección de datos: las reservas antiguas se borran solas. Se hace
        // aquí (cada vez que el personal abre el listado) porque en el hosting
        // compartido no hay tareas programadas garantizadas.
        Reserva::where('fecha_hora', '<', now()->subMonths(config('reservas.meses_conservacion')))->delete();

        $fecha = $request->date('fecha') ?? today();

        $reservas = Reserva::with('mesas')
            ->whereDate('fecha_hora', $fecha)
            ->orderBy('fecha_hora')
            ->orderBy('nombre')   // a igual hora, alfabético
            ->orderBy('apellidos')
            ->get();

        return view('reservas.index', [
            'reservas' => $reservas,
            'fecha' => $fecha,
        ]);
    }

    // Mapa de ocupación de las mesas en una fecha y hora concretas
    public function mapa(Request $request)
    {
        $fecha = $request->date('fecha') ?? today();
        $horas = $this->horasDisponibles();
        $hora = in_array($request->query('hora'), $horas) ? $request->query('hora') : $horas[0];

        $fechaHora = Carbon::parse($fecha->toDateString().' '.$hora);
        $duracion = config('reservas.duracion_horas');

        // Qué reserva ocupa cada mesa en esa franja
        $ocupadas = [];
        Reserva::with('mesas')
            ->where('fecha_hora', '>', $fechaHora->copy()->subHours($duracion))
            ->where('fecha_hora', '<', $fechaHora->copy()->addHours($duracion))
            ->get()
            ->each(function (Reserva $reserva) use (&$ocupadas) {
                foreach ($reserva->mesas as $mesa) {
                    $ocupadas[$mesa->id] = $reserva;
                }
            });

        return view('reservas.mapa', [
            'comedores' => Mesa::orderBy('numero')->get()->groupBy('comedor'),
            'ocupadas' => $ocupadas,
            'fecha' => $fecha,
            'hora' => $hora,
            'horas' => $horas,
        ]);
    }

    // El personal marca una mesa como ocupada por un cliente que llegó sin
    // reserva: queda bloqueada en su franja y la web ya no puede asignarla
    public function ocuparMesa(Request $request)
    {
        $datos = $request->validate([
            'mesa_id' => ['required', 'exists:mesas,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
        ]);

        $mesa = Mesa::findOrFail($datos['mesa_id']);
        $fechaHora = Carbon::parse($datos['fecha'].' '.$datos['hora']);

        try {
            $resultado = Cache::lock('crear-reserva', 10)->block(5, function () use ($mesa, $fechaHora) {
                $duracion = config('reservas.duracion_horas');

                $ocupada = DB::table('mesa_reserva')
                    ->join('reservas', 'reservas.id', '=', 'mesa_reserva.reserva_id')
                    ->where('mesa_reserva.mesa_id', $mesa->id)
                    ->where('reservas.fecha_hora', '>', $fechaHora->copy()->subHours($duracion))
                    ->where('reservas.fecha_hora', '<', $fechaHora->copy()->addHours($duracion))
                    ->exists();

                if ($ocupada) {
                    return ['error' => "La mesa {$mesa->numero} ya está ocupada en esa franja."];
                }

                DB::transaction(function () use ($mesa, $fechaHora) {
                    $reserva = Reserva::create([
                        'nombre' => 'Sin reserva',
                        'apellidos' => '',
                        'telefono' => '—',
                        'personas' => $mesa->capacidad,
                        'sin_reserva' => true,
                        'fecha_hora' => $fechaHora,
                    ]);
                    $reserva->mesas()->attach($mesa->id);
                });

                return ['ok' => true];
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['mapa' => 'Hay mucha actividad ahora mismo. Inténtalo de nuevo en unos segundos.']);
        }

        if (isset($resultado['error'])) {
            return back()->withErrors(['mapa' => $resultado['error']]);
        }

        return back()->with('exito', "Mesa {$mesa->numero} marcada como ocupada (cliente sin reserva).");
    }

    public function liberarMesa(Reserva $reserva)
    {
        abort_unless($reserva->sin_reserva, 404);

        $reserva->delete();

        return back()->with('exito', 'Mesa liberada.');
    }

    // Formulario de nueva reserva
    public function create()
    {
        // Primer momento reservable ahora mismo: el formulario no ofrece
        // horas anteriores (el personal solo tiene el límite de "ya pasó")
        $margen = Auth::check()
            ? now()
            : now()->addMinutes((int) Ajuste::valor('antelacion_minima_minutos', config('reservas.antelacion_minima_minutos')));

        return view('reservas.create', [
            'maxPersonas' => $this->maxPersonas(),
            'horasPorTurno' => $this->horasPorTurno(),
            'corte' => ['fecha' => $margen->toDateString(), 'hora' => $margen->format('H:i')],
        ]);
    }

    // Crear la reserva asignando automáticamente mesa(s) libre(s)
    public function store(Request $request)
    {
        $maxPersonas = $this->maxPersonas();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'telefono' => [
                'required', 'string', 'max:20',
                // Solo cifras, espacios, +, guiones, puntos y paréntesis
                'regex:/^[0-9+\s().\-]+$/',
                // Y con un número de dígitos real: 9 (España) hasta 15 (internacional)
                function (string $atributo, mixed $valor, \Closure $fallo) {
                    $digitos = strlen(preg_replace('/\D+/', '', (string) $valor));
                    if ($digitos < 9 || $digitos > 15) {
                        $fallo('Escribe un teléfono válido: 9 dígitos, o con prefijo internacional (+33...).');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora' => ['required', 'in:'.implode(',', $this->horasDisponibles())],
            'personas' => ['required', 'integer', 'min:1', "max:{$maxPersonas}"],
            'perro' => ['nullable', 'boolean'],
            'comedor' => ['required', 'in:dentro,terraza'],
        ], [
            'personas.max' => "Para grupos de más de :max personas, gestionad la reserva por teléfono.",
            'hora.in' => 'La hora tiene que estar dentro del horario de reservas.',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios y el prefijo +.',
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

        // Días cerrados: por día de la semana, fecha puntual o periodo de vacaciones
        $diasAbiertos = Ajuste::valor('dias_abiertos', [1, 2, 3, 4, 5, 6, 7]);
        $fechasCerradas = Ajuste::valor('fechas_cerradas', []);
        $fecha = $fechaHora->toDateString();
        $enVacaciones = collect(Ajuste::valor('rangos_cerrados', []))
            ->contains(fn (array $rango) => $fecha >= $rango[0] && $fecha <= $rango[1]);

        if (! in_array($fechaHora->dayOfWeekIso, $diasAbiertos)
            || in_array($fecha, $fechasCerradas)
            || $enVacaciones) {
            return back()
                ->withInput()
                ->withErrors(['fecha' => 'Ese día el restaurante está cerrado. Elegid otra fecha, por favor.']);
        }

        // Nadie puede reservar una hora que ya ha pasado, tampoco el personal
        if ($fechaHora->lt(now())) {
            return back()
                ->withInput()
                ->withErrors(['hora' => 'Esa hora ya ha pasado. Elegid otra, por favor.']);
        }

        // Antelación mínima para la web pública: durante el servicio, las
        // mesas libres son para quien llega por la puerta. El personal
        // logueado no tiene este límite (reservas telefónicas de última hora).
        if (! Auth::check()) {
            $antelacion = (int) Ajuste::valor('antelacion_minima_minutos', config('reservas.antelacion_minima_minutos'));

            if ($fechaHora->lt(now()->addMinutes($antelacion))) {
                return back()
                    ->withInput()
                    ->withErrors(['hora' => "Las reservas online se cierran {$antelacion} minutos antes de la hora. Llámanos por teléfono y te buscamos hueco."]);
            }
        }

        // Torno de entrada: las reservas se procesan de una en una para que
        // dos peticiones simultáneas no puedan quedarse la misma mesa. La
        // comprobación de disponibilidad y la creación van juntas bajo llave.
        try {
            $resultado = Cache::lock('crear-reserva', 10)->block(5, function () use ($datos, $fechaHora, $conPerro) {
                // Mismo teléfono a la misma hora exacta: es un doble envío o un
                // despiste; se rechaza para no duplicar (el personal sí puede,
                // p. ej. al partir un grupo muy grande en dos reservas)
                if (! Auth::check() && Reserva::where('telefono', $datos['telefono'])->where('fecha_hora', $fechaHora)->exists()) {
                    return ['error' => 'Ya existe una reserva con este teléfono a esa misma hora. Si quieres cambiarla, anúlala primero.'];
                }

                $limitePorHora = Ajuste::valor('maximo_reservas_por_hora', config('reservas.maximo_reservas_por_hora'));

                if (Reserva::where('fecha_hora', $fechaHora)->count() >= $limitePorHora) {
                    return ['error' => "A las {$datos['hora']} ya hay el máximo de {$limitePorHora} reservas. Elegid otra hora."];
                }

                // Segundo tope de la franja: comensales totales. Se cierra la
                // franja al llegar a cualquiera de los dos límites.
                $limitePersonas = Ajuste::valor('maximo_personas_por_hora', config('reservas.maximo_personas_por_hora'));
                $personasEnFranja = (int) Reserva::where('fecha_hora', $fechaHora)->sum('personas');

                if ($personasEnFranja + (int) $datos['personas'] > $limitePersonas) {
                    return ['error' => "A las {$datos['hora']} ya está completo el cupo de comensales. Elegid otra hora, por favor."];
                }

                $mesas = $this->buscarMesasLibres($fechaHora, $datos['personas'], $datos['comedor']);

                if (! $mesas) {
                    return ['error' => 'No quedan mesas libres para ese grupo a esa hora en el comedor elegido.'];
                }

                $reserva = DB::transaction(function () use ($datos, $fechaHora, $conPerro, $mesas) {
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

                    return $reserva;
                });

                return ['reserva' => $reserva, 'mesas' => $mesas];
            });
        } catch (LockTimeoutException) {
            return back()
                ->withInput()
                ->withErrors(['disponibilidad' => 'Ahora mismo hay mucha gente reservando a la vez. Inténtalo de nuevo en unos segundos.']);
        }

        if (isset($resultado['error'])) {
            return back()
                ->withInput()
                ->withErrors(['disponibilidad' => $resultado['error']]);
        }

        $reserva = $resultado['reserva'];
        $mesas = $resultado['mesas'];

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

    // Página pública para anular con el localizador (para quien no dio email
    // o ya no encuentra el correo): localizador + teléfono de la reserva
    public function buscarAnulacion()
    {
        return view('reservas.buscar-anulacion');
    }

    public function localizarAnulacion(Request $request)
    {
        $datos = $request->validate([
            'localizador' => ['required', 'string', 'max:10'],
            'telefono' => ['required', 'string', 'max:20'],
        ]);

        $reserva = Reserva::where('localizador', strtoupper(trim($datos['localizador'])))->first();

        // El teléfono se compara solo por sus dígitos ("600 11 12 22" = "600111222")
        $coincide = $reserva !== null
            && preg_replace('/\D+/', '', $reserva->telefono) === preg_replace('/\D+/', '', $datos['telefono']);

        if (! $coincide) {
            return back()
                ->withInput()
                ->withErrors(['localizador' => 'No encontramos ninguna reserva con ese localizador y ese teléfono.']);
        }

        // Reutiliza la misma página segura de anulación que el enlace del email
        return redirect()->to(URL::signedRoute('reservas.anular', ['reserva' => $reserva->id]));
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

        // 2) Combinaciones de mesas con todas sus mesas libres y capacidad
        //    suficiente. El comedor de la combinación lo marca su primera
        //    mesa: las demás pueden ser auxiliares de otro comedor que se
        //    mueven físicamente (como la "pared").

        return collect($combinaciones)
            ->map(fn (array $numeros) => Mesa::whereIn('numero', $numeros)
                ->whereNotIn('id', $mesasOcupadas)
                ->get())
            ->filter(fn (Collection $mesas, int $i) => $mesas->count() === count($combinaciones[$i]))
            ->filter(fn (Collection $mesas, int $i) => $mesas->firstWhere('numero', $combinaciones[$i][0])?->comedor === $comedor)
            ->filter(fn (Collection $mesas) => $mesas->sum('capacidad') >= $personas)
            ->sortBy(fn (Collection $mesas) => $mesas->sum('capacidad'))
            ->first();
    }

    // Horas reservables de cada turno. Los horarios los edita el personal
    // en /ajustes; config/reservas.php aporta los valores de fábrica.
    private function horasPorTurno(): array
    {
        $intervalo = config('reservas.intervalo_minutos');
        $turnos = [];

        foreach (Ajuste::valor('turnos', config('reservas.turnos')) as $nombre => [$inicio, $fin]) {
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
