<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Kopi Nako</title>
    @include('partials.head-assets')
    @stack('styles')
</head>

<body>
    @include('partials.sidebar')
    <div class="kn-overlay" id="sidebarOverlay"></div>

    <div class="kn-main">
        @include('partials.topbar')
        <main class="kn-content">
            @yield('content')
        </main>
    </div>

    @include('partials.logout-modal')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>

</html>
