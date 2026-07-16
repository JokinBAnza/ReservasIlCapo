<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    public $timestamps = false;

    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor'];

    // Devuelve el valor guardado, o el valor por defecto si nunca se cambió
    public static function valor(string $clave, $porDefecto = null)
    {
        $fila = static::where('clave', $clave)->first();

        return $fila ? json_decode($fila->valor, true) : $porDefecto;
    }

    public static function guardar(string $clave, $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => json_encode($valor)]);
    }
}
