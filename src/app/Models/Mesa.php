<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    // Si no quieres created_at / updated_at
    public $timestamps = false;

    // Columnas que se pueden rellenar masivamente
    protected $fillable = [
        'numero',
        'capacidad',
        'comedor',
    ];
}