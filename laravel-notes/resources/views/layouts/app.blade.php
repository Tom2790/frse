<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Notatki')</title>

    {{-- Vite wstrzykuje zbudowane (lub serwowane na żywo) CSS i JS. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">

{{--
    Cały interaktywny obszar strony jest w #app, bo tam montuje się Vue.
    Navbar musi być w środku — dzwonek powiadomień to komponent Vue.
--}}
<div id="app">
    <nav class="navbar navbar-expand bg-body border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('notes.index') }}">
                <i class="bi bi-journal-text me-1"></i>Notatki
            </a>

            @auth
                <div class="ms-auto d-flex align-items-center gap-3">
                    {{-- Zadanie 5a --}}
                    <notification-bell></notification-bell>

                    <span class="text-body-secondary small d-none d-sm-inline">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Wyloguj</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="container pb-5">
        @yield('content')
    </main>
</div>

</body>
</html>
