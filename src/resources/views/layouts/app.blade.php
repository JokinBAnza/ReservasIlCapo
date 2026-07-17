<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas · Il Capo</title>
    {{-- Misma tipografía que ilcapo.net --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estética heredada de ilcapo.net: crema #FFF6EA, negro #222222,
           marrón vino #553735, Poppins, esquinas redondeadas de 20px */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', system-ui, sans-serif; background: #FFF6EA; color: #222222; min-height: 100vh; }
        header { background: #222222; color: #FFF6EA; padding: 1.1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; }
        header h1 { font-size: 1.05rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; }
        header h1 span { color: rgba(255,246,234,.64); font-weight: 400; }
        nav { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
        nav a, nav button { color: #FFF6EA; text-decoration: none; padding: .5rem 1.1rem; border-radius: 20px; background: transparent; border: 1px solid rgba(255,246,234,.28); font-size: .85rem; font-family: inherit; cursor: pointer; }
        nav a:hover, nav button:hover { background: #553735; border-color: #553735; }
        main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        h2 { font-weight: 600; }
        .tarjeta { background: #FFFFFF; border-radius: 20px; box-shadow: 0 4px 24px rgba(34,34,34,.08); padding: 2rem; }
        .aviso-exito { background: #ECF3E2; color: #3D5226; border: 1px solid #C9DBA8; padding: .8rem 1.1rem; border-radius: 12px; margin-bottom: 1rem; }
        .aviso-error { background: #F7E3DC; color: #7C2D12; border: 1px solid #E5BFAC; padding: .8rem 1.1rem; border-radius: 12px; margin-bottom: 1rem; }
        .aviso-error ul { list-style: none; }
        label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: .35rem; }
        /* 16px mínimo: evita que iPhone haga zoom al tocar un campo */
        input, select { width: 100%; padding: .6rem .8rem; border: none; border-radius: 10px; font-size: 1rem; font-family: inherit; background: #FFF6EA; box-shadow: inset 0 0 0 1px rgba(34,34,34,.15); color: #222222; }
        input:focus, select:focus { outline: 2px solid #553735; }
        .campo { margin-bottom: 1.1rem; }
        .campo-checkbox { display: flex; align-items: center; gap: .5rem; }
        .campo-fax { position: absolute; left: -9999px; top: -9999px; }
        .campo-checkbox input { width: auto; box-shadow: none; }
        .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .boton { display: inline-block; background: #222222; color: #FFF6EA; border: none; padding: .7rem 1.6rem; border-radius: 20px; font-size: .95rem; font-weight: 500; font-family: inherit; cursor: pointer; text-decoration: none; }
        .boton:hover { background: #553735; color: #FFFFFF; }
        .boton-peligro { background: #553735; padding: .4rem .95rem; font-size: .8rem; }
        .boton-peligro:hover { background: #6E4B48; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .65rem .5rem; border-bottom: 1px solid rgba(34,34,34,.09); }
        th { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #999999; font-weight: 600; }
        .etiqueta { display: inline-block; padding: .18rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .etiqueta-dentro { background: #EADBC3; color: #553735; }
        .etiqueta-terraza { background: #222222; color: #FFF6EA; }
        .sin-datos { color: #999999; text-align: center; padding: 2rem 0; }
        .mapa-comedor { display: flex; flex-wrap: wrap; gap: .75rem; margin: .75rem 0 1.75rem; }
        .mesa { width: 106px; height: 116px; border-radius: 14px; padding: .5rem .45rem; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: .1rem; }
        .boton-mesa { margin-top: .25rem; font-size: .68rem; padding: .18rem .7rem; border: none; border-radius: 10px; background: #222222; color: #FFF6EA; font-family: inherit; cursor: pointer; }
        .boton-mesa:hover { background: #553735; }
        .boton-mesa-liberar { background: #FFF6EA; color: #222222; }
        .boton-mesa-liberar:hover { background: #EADBC3; color: #222222; }
        .mesa strong { display: block; font-size: 1.15rem; }
        .mesa small { display: block; font-size: .7rem; opacity: .8; }
        .mesa-libre { background: #ECF3E2; box-shadow: inset 0 0 0 1px #C9DBA8; }
        .mesa-ocupada { background: #553735; color: #FFF6EA; }
        .mesa-leyenda { display: inline-block; width: 12px; height: 12px; border-radius: 4px; vertical-align: -1px; }
        a { color: #553735; }
        .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .tabla-scroll table { min-width: 640px; }
        @media (max-width: 600px) {
            .fila { grid-template-columns: 1fr; }
            main { margin: 1.25rem auto; }
            .tarjeta { padding: 1.25rem; border-radius: 16px; }
            header { padding: .9rem 1rem; }
            nav a, nav button { padding: .4rem .85rem; font-size: .8rem; }
        }
    </style>
</head>
<body>
    <header>
        <h1>
            <a href="{{ route('reservas.create') }}" style="color: inherit; text-decoration: none;">
                Il Capo <span>· {{ auth()->check() ? 'Administrador' : 'Reservas' }}</span>
            </a>
        </h1>
        <nav>
            @auth
                <a href="{{ route('reservas.index') }}">Reservas del día</a>
                <a href="{{ route('reservas.mapa') }}">Mapa</a>
                <a href="{{ route('ajustes.editar') }}">Ajustes</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            @else
                <a href="{{ route('reservas.create') }}">Reservar</a>
                <a href="{{ route('reservas.buscar-anulacion') }}">Anular reserva</a>
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
    <footer style="text-align: center; padding: 1.5rem 1rem 2rem; font-size: .8rem; color: #999999;">
        Il Capo · <a href="{{ route('privacidad') }}">Política de privacidad</a>
    </footer>
    <script>
        // Evita dobles envíos por doble clic (crearían reservas duplicadas)
        document.addEventListener('submit', function (evento) {
            for (const boton of evento.target.querySelectorAll('button[type="submit"]')) {
                boton.disabled = true;
            }
        });
    </script>
</body>
</html>
