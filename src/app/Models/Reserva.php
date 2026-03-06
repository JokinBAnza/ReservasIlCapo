<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'mesa_id',
        'telefono',
        'fecha',
    ];

    // Relación con Mesa
    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }
}