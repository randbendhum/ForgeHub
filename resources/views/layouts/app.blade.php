<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FORGEMOD HUB') — Mod Manager &amp; XML/JSON Validator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <header class="site-header">
        <div class="container site-header__bar">
            <a href="{{ route('catalogue.index') }}" class="brand-logo">
                <span>FORGEMOD//HUB</span>
                <span class="brand-logo__tag">XML+JSON</span>
            </a>

            <nav class="main-nav" aria-label="Primary Navigation">
                <a href="{{ route('catalogue.index') }}"
                   class="nav-link {{ request()->routeIs('catalogue.*') ? 'is-active' : '' }}">
                    [01] CATALOGUE
                </a>
                @auth
                    <a href="{{ route('upload.create') }}"
                       class="nav-link {{ request()->routeIs('upload.*') ? 'is-active' : '' }}">
                        [02] SUBMIT &amp; VALIDATE MOD
                    </a>
                    <a href="{{ route('inventory.index') }}"
                       class="nav-link {{ request()->routeIs('inventory.*') ? 'is-active' : '' }}">
                        [03] INVENTORY TABLE
                    </a>
                @endauth
            </nav>

            <div class="header-actions">
                @auth
                    <a href="{{ route('developers.show', auth()->user()) }}" class="nav-link">
                        {{ '@' . auth()->user()->username }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn--white btn--sm">LOGOUT</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn--white btn--sm">LOGIN</a>
                    <a href="{{ route('register') }}" class="btn btn--sm">REGISTER</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            @if (session('status'))
                <div class="flash-banner" role="status">
                    <span>&gt;&gt; {{ session('status') }}</span>
                    <span>[OK]</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="flash-banner flash-banner--error" role="alert">
                    <div>
                        <div>[FORM VALIDATION ERROR]</div>
                        @foreach ($errors->all() as $error)
                            <div>&gt; {{ $error }}</div>
                        @endforeach
                    </div>
                    <span>[422]</span>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="site-footer">
        <div class="container site-footer__inner">
            <div>
                <strong>FORGEMOD//HUB v1.0</strong> — MOD SUBMISSION, LIBXML/JSON PARSING &amp; VERSIONED STORAGE PORTAL (FARMING SIMULATOR &amp; MINECRAFT)
            </div>
            <div>
                PHP {{ PHP_VERSION }} // LARAVEL 11 // MYSQL // STORAGE DISK: MODS
            </div>
        </div>
    </footer>
</div>
</body>
</html>
