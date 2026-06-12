<?php

return [

    // Horas que una reserva bloquea la mesa, antes y después de la hora reservada.
    'duracion_horas' => 2,

    // Máximo de reservas futuras que puede tener un mismo teléfono a la vez
    // desde la web pública (el personal no tiene límite). Frena el abuso.
    'maximo_reservas_por_telefono' => 2,

    // Máximo de reservas que se aceptan en una misma franja horaria
    // (p. ej. como mucho 10 reservas a las 14:00), para no saturar
    // la cocina y la sala con demasiadas llegadas a la vez.
    'maximo_reservas_por_hora' => 10,

    // Turnos en los que se aceptan reservas (de inicio a fin, ambos incluidos)
    // y separación en minutos entre las horas que se ofrecen.
    'intervalo_minutos' => 15,
    'turnos' => [
        'comida' => ['13:00', '15:00'],
        'cena' => ['20:00', '22:00'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Combinaciones de mesas contiguas
    |--------------------------------------------------------------------------
    | Grupos de mesas (por su número) que se pueden juntar para grupos grandes.
    | Todas las mesas de una combinación deben estar en el mismo comedor.
    | Para añadir una combinación nueva, añade una línea: [numero, numero, ...]
    */
    'combinaciones' => [
        [41, 30], // terraza, 10 + 4  = 14 personas
        [41, 42], // terraza, 10 + 10 = 20 personas
    ],

];
