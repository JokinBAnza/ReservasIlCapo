<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Mesa;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AjusteController extends Controller
{
    public function editar()
    {
        // Turnos: lo guardado, completado con los valores de fábrica
        $guardados = Ajuste::valor('turnos', config('reservas.turnos'));
        $turnos = [];
        foreach (config('reservas.turnos') as $nombre => $porDefecto) {
            $turnos[$nombre] = [
                'activo' => isset($guardados[$nombre]),
                'inicio' => $guardados[$nombre][0] ?? $porDefecto[0],
                'fin' => $guardados[$nombre][1] ?? $porDefecto[1],
            ];
        }

        return view('ajustes.editar', [
            'diasAbiertos' => Ajuste::valor('dias_abiertos', [1, 2, 3, 4, 5, 6, 7]),
            'turnos' => $turnos,
            'fechasCerradas' => collect(Ajuste::valor('fechas_cerradas', []))
                ->filter(fn (string $f) => $f >= today()->toDateString()) // las pasadas ya no interesan
                ->sort()
                ->values(),
            'rangosCerrados' => collect(Ajuste::valor('rangos_cerrados', []))
                ->filter(fn (array $r) => $r[1] >= today()->toDateString())
                ->sortBy(fn (array $r) => $r[0])
                ->values(),
            'limitePorHora' => Ajuste::valor('maximo_reservas_por_hora', config('reservas.maximo_reservas_por_hora')),
            'antelacion' => Ajuste::valor('antelacion_minima_minutos', config('reservas.antelacion_minima_minutos')),
            'mesas' => Mesa::orderBy('numero')->get()->groupBy('comedor'),
        ]);
    }

    // Capacidad de cada mesa, editable sin tocar la base de datos a mano
    public function guardarMesas(Request $request)
    {
        $datos = $request->validate([
            'capacidades' => ['required', 'array'],
            'capacidades.*' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'capacidades.*.required' => 'A alguna mesa le falta la capacidad.',
            'capacidades.*.integer' => 'Las capacidades deben ser números enteros.',
            'capacidades.*.min' => 'Ninguna mesa puede tener capacidad menor que 1.',
            'capacidades.*.max' => 'Ninguna mesa puede tener capacidad mayor que 20.',
        ]);

        foreach ($datos['capacidades'] as $mesaId => $capacidad) {
            Mesa::where('id', $mesaId)->update(['capacidad' => (int) $capacidad]);
        }

        return back()->with('exito', 'Capacidades de las mesas guardadas.');
    }

    public function guardar(Request $request)
    {
        $nombresTurnos = array_keys(config('reservas.turnos'));

        $reglas = [
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'limite' => ['required', 'integer', 'min:1', 'max:50'],
            'antelacion' => ['nullable', 'integer', 'min:0', 'max:2880'],
        ];
        foreach ($nombresTurnos as $t) {
            $reglas["turnos.$t.inicio"] = ["required_with:turnos.$t.activo", 'date_format:H:i'];
            $reglas["turnos.$t.fin"] = ["required_with:turnos.$t.activo", 'date_format:H:i', "after:turnos.$t.inicio"];
        }

        $datos = $request->validate($reglas, [
            'dias.required' => 'Debe quedar al menos un día abierto a reservas. Para vacaciones, usad "Cerrar un periodo".',
            'dias.min' => 'Debe quedar al menos un día abierto a reservas. Para vacaciones, usad "Cerrar un periodo".',
            'turnos.*.fin.after' => 'La hora de fin de un turno debe ser posterior a la de inicio.',
            'turnos.*.inicio.required_with' => 'A un turno activo le falta la hora de inicio.',
            'turnos.*.fin.required_with' => 'A un turno activo le falta la hora de fin.',
            'turnos.*.inicio.date_format' => 'Las horas de los turnos deben tener formato HH:MM.',
            'turnos.*.fin.date_format' => 'Las horas de los turnos deben tener formato HH:MM.',
        ]);

        Ajuste::guardar('dias_abiertos', array_values(array_map('intval', $datos['dias'])));
        Ajuste::guardar('maximo_reservas_por_hora', (int) $datos['limite']);

        if ($request->filled('antelacion')) {
            Ajuste::guardar('antelacion_minima_minutos', (int) $datos['antelacion']);
        }

        if ($request->has('turnos')) {
            $turnos = [];
            foreach ($nombresTurnos as $t) {
                if ($request->boolean("turnos.$t.activo")) {
                    $turnos[$t] = [$datos['turnos'][$t]['inicio'], $datos['turnos'][$t]['fin']];
                }
            }

            if ($turnos === []) {
                return back()
                    ->withInput()
                    ->withErrors(['turnos' => 'Debe quedar al menos un turno activo.']);
            }

            Ajuste::guardar('turnos', $turnos);
        }

        return back()->with('exito', 'Ajustes guardados.');
    }

    public function cerrarFecha(Request $request)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $fecha = Carbon::parse($datos['fecha'])->toDateString();
        $fechas = Ajuste::valor('fechas_cerradas', []);

        if (! in_array($fecha, $fechas)) {
            $fechas[] = $fecha;
            Ajuste::guardar('fechas_cerradas', array_values($fechas));
        }

        return back()->with('exito', 'Día '.Carbon::parse($fecha)->format('d/m/Y').' cerrado a reservas.');
    }

    public function abrirFecha(Request $request)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        $fecha = Carbon::parse($datos['fecha'])->toDateString();
        $fechas = collect(Ajuste::valor('fechas_cerradas', []))
            ->reject(fn (string $f) => $f === $fecha)
            ->values()
            ->all();

        Ajuste::guardar('fechas_cerradas', $fechas);

        return back()->with('exito', 'Día '.Carbon::parse($fecha)->format('d/m/Y').' abierto de nuevo.');
    }

    // Cierre por periodo (vacaciones): del día "desde" al día "hasta", ambos incluidos
    public function cerrarRango(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['required', 'date', 'after_or_equal:today'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'El final del periodo no puede ser anterior al inicio.',
        ]);

        $rango = [Carbon::parse($datos['desde'])->toDateString(), Carbon::parse($datos['hasta'])->toDateString()];
        $rangos = Ajuste::valor('rangos_cerrados', []);

        if (! in_array($rango, $rangos)) {
            $rangos[] = $rango;
            Ajuste::guardar('rangos_cerrados', array_values($rangos));
        }

        return back()->with('exito', 'Cerrado del '.Carbon::parse($rango[0])->format('d/m/Y').' al '.Carbon::parse($rango[1])->format('d/m/Y').'.');
    }

    public function abrirRango(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date'],
        ]);

        $rango = [Carbon::parse($datos['desde'])->toDateString(), Carbon::parse($datos['hasta'])->toDateString()];
        $rangos = collect(Ajuste::valor('rangos_cerrados', []))
            ->reject(fn (array $r) => $r === $rango)
            ->values()
            ->all();

        Ajuste::guardar('rangos_cerrados', $rangos);

        return back()->with('exito', 'Periodo reabierto.');
    }
}
