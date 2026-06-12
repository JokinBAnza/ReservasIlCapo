<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

// Público: reservar y ver la confirmación
Route::get('/', [ReservaController::class, 'create'])->name('reservas.create');
Route::post('/reservas', [ReservaController::class, 'store'])->name('reservas.store')->middleware('throttle:15,1');
Route::get('/reserva-confirmada', [ReservaController::class, 'confirmada'])->name('reservas.confirmada');

// Anulación desde el enlace firmado del email de confirmación
Route::middleware('signed')->group(function () {
    Route::get('/reservas/anular/{reserva}', [ReservaController::class, 'anular'])->name('reservas.anular');
    Route::post('/reservas/anular/{reserva}', [ReservaController::class, 'confirmarAnulacion']);
});

// Acceso del personal (con freno de intentos contra fuerza bruta)
Route::get('/entrar', [AuthController::class, 'mostrar'])->name('login');
Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:5,1');
Route::post('/salir', [AuthController::class, 'salir'])->name('logout');

// Zona privada del personal
Route::middleware('auth')->group(function () {
    Route::get('/reservas', [ReservaController::class, 'index'])->name('reservas.index');
    Route::delete('/reservas/{reserva}', [ReservaController::class, 'destroy'])->name('reservas.destroy');
    Route::get('/cambiar-contrasena', [AuthController::class, 'editarContrasena'])->name('password.edit');
    Route::post('/cambiar-contrasena', [AuthController::class, 'actualizarContrasena']);
});
