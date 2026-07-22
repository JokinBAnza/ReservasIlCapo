<?php

return [

    // Teléfono del restaurante: aparece en los mensajes que invitan a llamar
    // (grupos muy grandes, sin hueco online, etc.)
    'telefono_restaurante' => '688 716 226',

    // Dirección que recibe el aviso interno de cada reserva nueva hecha por
    // un cliente desde la web. Requiere el correo (Brevo) configurado; si no
    // lo está, el aviso simplemente no sale y la reserva funciona igual.
    'email_avisos' => 'info@ilcapo.net',

    // Horas que una reserva bloquea la mesa, antes y después de la hora reservada.
    'duracion_horas' => 2,

    // Meses que se conservan las reservas pasadas antes de borrarse solas
    // (protección de datos: no acumular datos personales sin necesidad).
    'meses_conservacion' => 12,

    // Antelación mínima (en minutos) para reservar desde la web pública.
    // Evita reservas online de última hora mientras se sienta a gente
    // que llega sin reserva. El personal logueado no tiene este límite.
    'antelacion_minima_minutos' => 60,

    // Máximo de reservas futuras que puede tener un mismo teléfono a la vez
    // desde la web pública (el personal no tiene límite). Frena el abuso.
    'maximo_reservas_por_telefono' => 2,

    // Máximo de reservas que se aceptan en una misma franja horaria
    // (p. ej. como mucho 10 reservas a las 14:00), para no saturar
    // la cocina y la sala con demasiadas llegadas a la vez.
    'maximo_reservas_por_hora' => 10,

    // Máximo de comensales (suma de personas) por franja horaria.
    // La franja se cierra cuando se alcanza cualquiera de los dos
    // límites: número de reservas o número de personas.
    'maximo_personas_por_hora' => 30,

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
    |
    | 'capacidad' es la de la combinación ya montada, que suele ser mayor que
    | la suma de las mesas sueltas (al juntarlas caben sillas extra en la
    | unión). Es la que manda para decidir si un grupo cabe.
    |
    | El comedor de la combinación es el de su PRIMERA mesa; las demás pueden
    | ser auxiliares de otro comedor que se mueven físicamente (p. ej. la
    | "pared", mesa 0, que vive dentro pero se saca a la terraza).
    |
    | Para añadir una combinación nueva, añade una línea con sus mesas y su
    | capacidad real: ['mesas' => [numero, numero, ...], 'capacidad' => N]
    */
    'combinaciones' => [
        // Terraza
        ['mesas' => [41, 30],     'capacidad' => 14],
        ['mesas' => [41, 30, 0],  'capacidad' => 16], // la 0 ("pared") se mueve desde dentro
        ['mesas' => [41, 30, 31], 'capacidad' => 18],
        ['mesas' => [41, 42],     'capacidad' => 20],

        // Comedor de dentro
        ['mesas' => [21, 16],     'capacidad' => 12],
        ['mesas' => [22, 17],     'capacidad' => 14],
        ['mesas' => [22, 17, 0],  'capacidad' => 16],
        ['mesas' => [21, 22, 16], 'capacidad' => 20], // 8 + 8 + 4
    ],

];
