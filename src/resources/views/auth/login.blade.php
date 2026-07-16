@extends('layouts.app')

@section('contenido')
    <div class="tarjeta" style="max-width: 420px; margin: 0 auto;">
        <h2 style="margin-bottom: 1.25rem;">Acceso del personal</h2>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="campo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" value="{{ old('usuario') }}" required autofocus autocomplete="username">
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="boton">Entrar</button>
        </form>
    </div>
@endsection
