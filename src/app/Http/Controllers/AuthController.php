<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Formulario de acceso del personal
    public function mostrar()
    {
        return view('auth.login');
    }

    public function entrar(Request $request)
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credenciales, remember: true)) {
            $request->session()->regenerate();

            return redirect()->intended(route('reservas.index'));
        }

        return back()
            ->withErrors(['email' => 'Correo o contraseña incorrectos.'])
            ->onlyInput('email');
    }

    // Cambio de contraseña del personal, desde la propia web
    // (en el hosting no habrá terminal para hacerlo de otra forma)
    public function editarContrasena()
    {
        return view('auth.cambiar-contrasena');
    }

    public function actualizarContrasena(Request $request)
    {
        $request->validate([
            'actual' => ['required', 'current_password'],
            'nueva' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'nueva.min' => 'La contraseña nueva debe tener al menos :min caracteres.',
            'nueva.confirmed' => 'La contraseña nueva y su repetición no coinciden.',
        ]);

        $request->user()->update(['password' => $request->nueva]);

        return redirect()
            ->route('reservas.index')
            ->with('exito', 'Contraseña cambiada correctamente.');
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('reservas.create');
    }
}
