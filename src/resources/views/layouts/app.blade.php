<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas · Il Capo</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f2ed; color: #2b2b2b; min-height: 100vh; }
        header { background: #7c2d12; color: #fff; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; }
        header h1 { font-size: 1.3rem; font-weight: 600; }
        nav { display: flex; gap: .5rem; }
        nav a { color: #fff; text-decoration: none; padding: .45rem .9rem; border-radius: 6px; background: rgba(255,255,255,.12); font-size: .95rem; }
        nav a:hover { background: rgba(255,255,255,.25); }
        nav button { color: #fff; padding: .45rem .9rem; border: none; border-radius: 6px; background: rgba(255,255,255,.12); font-size: .95rem; font-family: inherit; cursor: pointer; }
        nav button:hover { background: rgba(255,255,255,.25); }
        main { max-width: 900px; margin: 1.5rem auto; padding: 0 1rem; }
        .tarjeta { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; }
        .aviso-exito { background: #dcfce7; color: #166534; border: 1px solid #86efac; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .aviso-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .aviso-error ul { list-style: none; }
        label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .3rem; }
        input, select { width: 100%; padding: .55rem .7rem; border: 1px solid #d4d4d4; border-radius: 6px; font-size: 1rem; background: #fff; }
        input:focus, select:focus { outline: 2px solid #7c2d12; border-color: transparent; }
        .campo { margin-bottom: 1rem; }
        .campo-checkbox { display: flex; align-items: center; gap: .5rem; }
        .campo-fax { position: absolute; left: -9999px; top: -9999px; }
        .campo-checkbox input { width: auto; }
        .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .boton { display: inline-block; background: #7c2d12; color: #fff; border: none; padding: .65rem 1.4rem; border-radius: 6px; font-size: 1rem; cursor: pointer; text-decoration: none; }
        .boton:hover { background: #9a3412; }
        .boton-peligro { background: #b91c1c; padding: .35rem .8rem; font-size: .85rem; }
        .boton-peligro:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .6rem .5rem; border-bottom: 1px solid #e5e5e5; }
        th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #737373; }
        .etiqueta { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .etiqueta-dentro { background: #fef3c7; color: #92400e; }
        .etiqueta-terraza { background: #dbeafe; color: #1e40af; }
        .sin-datos { color: #737373; text-align: center; padding: 2rem 0; }
        @media (max-width: 600px) { .fila { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header>
        <h1>🍝 Reservas Il Capo</h1>
        <nav>
            @auth
                <a href="{{ route('reservas.index') }}">Reservas del día</a>
                <a href="{{ route('reservas.create') }}">+ Nueva reserva</a>
                <a href="{{ route('password.edit') }}">Contraseña</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            @else
                <a href="{{ route('reservas.create') }}">Reservar</a>
            @endauth
        </nav>
    </header>
    <main>
        @if (session('exito'))
            <div class="aviso-exito">{{ session('exito') }}</div>
        @endif

        @if ($errors->any())
            <div class="aviso-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
