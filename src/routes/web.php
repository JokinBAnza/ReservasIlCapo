<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

// Público: reservar y ver la confirmación
Route::get('/', [ReservaController::class, 'create'])->name('reservas.create');
Route::post('/reservas', [ReservaController::class, 'store'])->name('reservas.store')->middleware('throttle:15,1');
Route::get('/reserva-confirmada', [ReservaController::class, 'confirmada'])->name('reservas.confirmada');

// Anulación pública con localizador + teléfono
Route::get('/anular-reserva', [ReservaController::class, 'buscarAnulacion'])->name('reservas.buscar-anulacion');
Route::post('/anular-reserva', [ReservaController::class, 'localizarAnulacion'])->middleware('throttle:10,1');

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
    Route::get('/mesas', [ReservaController::class, 'mapa'])->name('reservas.mapa');
    Route::post('/mesas/ocupar', [ReservaController::class, 'ocuparMesa'])->name('mesas.ocupar');
    Route::post('/mesas/liberar/{reserva}', [ReservaController::class, 'liberarMesa'])->name('mesas.liberar');
    Route::delete('/reservas/{reserva}', [ReservaController::class, 'destroy'])->name('reservas.destroy');
    Route::post('/cambiar-contrasena', [AuthController::class, 'actualizarContrasena'])->name('password.edit');
    Route::get('/ajustes', [AjusteController::class, 'editar'])->name('ajustes.editar');
    Route::post('/ajustes', [AjusteController::class, 'guardar']);
    Route::post('/ajustes/cerrar-fecha', [AjusteController::class, 'cerrarFecha'])->name('ajustes.cerrar');
    Route::post('/ajustes/abrir-fecha', [AjusteController::class, 'abrirFecha'])->name('ajustes.abrir');
    Route::post('/ajustes/cerrar-rango', [AjusteController::class, 'cerrarRango'])->name('ajustes.cerrar-rango');
    Route::post('/ajustes/abrir-rango', [AjusteController::class, 'abrirRango'])->name('ajustes.abrir-rango');
});
