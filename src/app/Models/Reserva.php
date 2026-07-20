<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'apellidos',
        'telefono',
        'email',
        'personas',
        'perro',
        'observaciones',
        'sin_reserva',
        'fecha_hora',
    ];

    protected $casts = [
        'perro' => 'boolean',
        'sin_reserva' => 'boolean',
        'fecha_hora' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reserva $reserva) {
            $reserva->localizador ??= self::generarLocalizador();
        });
    }

    // Código corto de reserva, p. ej. K7M3PD. Sin letras/cifras confundibles
    // (0/O, 1/I/L) para poder dictarlo por teléfono sin errores.
    public static function generarLocalizador(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $codigo = '';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (self::where('localizador', $codigo)->exists());

        return $codigo;
    }

    // Mesas ocupadas por la reserva (varias cuando se juntan mesas contiguas)
    public function mesas()
    {
        return $this->belongsToMany(Mesa::class);
    }
}
