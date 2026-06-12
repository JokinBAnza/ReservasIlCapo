@extends('layouts.app')

@section('contenido')
    <div class="tarjeta" style="max-width: 420px; margin: 0 auto;">
        <h2 style="margin-bottom: 1.25rem;">Cambiar contraseña</h2>

        <form method="POST" action="{{ route('password.edit') }}">
            @csrf

            <div class="campo">
                <label for="actual">Contraseña actual</label>
                <input type="password" id="actual" name="actual" required autofocus>
            </div>

            <div class="campo">
                <label for="nueva">Contraseña nueva <span style="font-weight: 400; color: #737373;">(mínimo 10 caracteres)</span></label>
                <input type="password" id="nueva" name="nueva" required minlength="10">
            </div>

            <div class="campo">
                <label for="nueva_confirmation">Repite la contraseña nueva</label>
                <input type="password" id="nueva_confirmation" name="nueva_confirmation" required minlength="10">
            </div>

            <button type="submit" class="boton">Cambiar contraseña</button>
        </form>
    </div>
@endsection
