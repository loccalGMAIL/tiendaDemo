<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="clasico">
<head>
    @include('landings.partials.head')
</head>
<body class="antialiased">
    @include('landings.clasico.header')

    <main>
        @yield('content')
    </main>

    @include('landings.clasico.footer')
</body>
</html>
