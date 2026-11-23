<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="clasico">
<head>
    @include('landings.partials.head')
</head>
<body class="antialiased">
    @include('landings.clasico.header')

    <main>
        @foreach ($sections as $section)
            @includeFirst(['landings.clasico.'.$section, 'landings.partials.'.$section])
        @endforeach
    </main>

    @include('landings.clasico.footer')
</body>
</html>
