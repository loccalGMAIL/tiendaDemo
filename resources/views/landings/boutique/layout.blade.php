<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="boutique">
<head>
    @include('landings.partials.head')
</head>
<body class="antialiased">
    @include('landings.boutique.header')

    <main>
        @yield('content')
    </main>

    @include('landings.boutique.footer')
</body>
</html>
